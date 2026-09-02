<?php

namespace Tests\Feature;

use App\Jobs\SendDeliveryAlertDay0Job;
use App\Jobs\SendDeliveryAlertDay2Job;
use App\Jobs\SendDeliveryAlertDay3Job;
use App\Mail\DeliveryAlertDay0Mail;
use App\Mail\DeliveryAlertDay2Mail;
use App\Mail\DeliveryAlertDay3Mail;
use App\Models\DirectPurchaseOrder;
use App\Models\Reception;
use App\Models\ReceptionItem;
use App\Http\Middleware\EnsureSupplierIsApproved;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\DirectPurchaseOrderItem;
use App\Models\ExpenseCategory;
use App\Models\ReceivingLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ReceptionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReceptionFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['supplier', 'receiver', 'buyer', 'superadmin'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_supplier_delivery_deadline_is_based_on_physical_delivery_date(): void
    {
        Queue::fake();
        Carbon::setTestNow('2026-05-15 12:00:00');

        // El gating documental del portal se cubre en RFQ-02/RFQ-03; aqui se verifica
        // unicamente el calculo del plazo de recepcion a partir de la entrega fisica.
        $this->withoutMiddleware(EnsureSupplierIsApproved::class);

        [$order, , $supplier] = $this->createDirectOrder(['status' => 'ISSUED']);
        $publicTestDisk = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'suppliersportal-test-public';
        config()->set('filesystems.disks.public.root', $publicTestDisk);
        if (! is_dir($publicTestDisk)) {
            mkdir($publicTestDisk, 0777, true);
        }
        app('filesystem')->forgetDisk('public');

        $this->actingAs($supplier, 'supplier')->post(route('supplier.deliveries.store'), [
            'order_type' => 'direct',
            'order_id' => $order->id,
            'remission_file' => UploadedFile::fake()->create('remision.pdf', 100, 'application/pdf'),
            'delivered_at' => '2026-05-08T10:00',
        ])->assertRedirect(route('supplier.deliveries.index'));

        $order->refresh();

        $this->assertSame('DELIVERED_PENDING_RECEPTION', $order->status);
        $this->assertSame('2026-05-08 10:00:00', $order->supplier_delivered_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-13', $order->reception_deadline_at->format('Y-m-d'));

        Queue::assertPushed(SendDeliveryAlertDay0Job::class);
        Queue::assertPushed(SendDeliveryAlertDay2Job::class, fn ($job) => $job->delay?->format('Y-m-d H:i:s') === '2026-05-12 09:00:00');
        Queue::assertPushed(SendDeliveryAlertDay3Job::class, fn ($job) => $job->delay?->format('Y-m-d H:i:s') === '2026-05-13 09:00:00');
    }

    public function test_delivery_alert_day_zero_includes_receiver_buyer_and_finance(): void
    {
        Mail::fake();

        [$order] = $this->createDeliveredOrderWithAlertRecipients();

        (new SendDeliveryAlertDay0Job('direct', $order->id, 'https://example.test/remision.pdf'))->handle();

        $this->assertMailableWasSentToAllAlertRecipients(DeliveryAlertDay0Mail::class);
    }

    public function test_follow_up_delivery_alerts_include_receiver_buyer_and_finance(): void
    {
        Mail::fake();

        [$order] = $this->createDeliveredOrderWithAlertRecipients();

        (new SendDeliveryAlertDay2Job('direct', $order->id))->handle();
        (new SendDeliveryAlertDay3Job('direct', $order->id))->handle();

        $this->assertMailableWasSentToAllAlertRecipients(DeliveryAlertDay2Mail::class);
        $this->assertMailableWasSentToAllAlertRecipients(DeliveryAlertDay3Mail::class);
    }

    public function test_nonconforming_reception_does_not_complete_order_quantities(): void
    {
        Notification::fake();

        [$order, $location] = $this->createDirectOrder(['status' => 'DELIVERED_PENDING_RECEPTION']);
        $item = $order->items()->first();
        $receiver = User::factory()->create();

        $reception = app(ReceptionService::class)->receive($order, [[
            'receivable_item_id' => $item->id,
            'quantity_received' => 5,
            'conformity' => ReceptionItem::CONFORMITY_FAIL,
            'nonconformity_type' => 'defective',
            'nonconformity_notes' => str_repeat('Producto no conforme. ', 6),
            'photos' => ['recepciones/fotos/no-conforme.jpg'],
        ]], $receiver, [
            'receiving_location_id' => $location->id,
            'remission_path' => 'remisiones/remision.pdf',
            'received_at' => now(),
        ]);

        $this->assertSame(Reception::STATUS_PARTIAL, $reception->status);
        $this->assertSame('PARTIALLY_RECEIVED', $order->refresh()->status);
        $this->assertSame(0.0, (float) $item->refresh()->quantity_received);
        $this->assertDatabaseHas('reception_items', [
            'reception_id' => $reception->id,
            'quantity_received' => 5,
            'conformity' => ReceptionItem::CONFORMITY_FAIL,
        ]);
    }

    public function test_reception_rejects_quantities_above_pending_amount(): void
    {
        Notification::fake();

        [$order, $location] = $this->createDirectOrder(['status' => 'ISSUED']);
        $item = $order->items()->first();
        $receiver = User::factory()->create();

        try {
            app(ReceptionService::class)->receive($order, [[
                'receivable_item_id' => $item->id,
                'quantity_received' => 6,
                'conformity' => ReceptionItem::CONFORMITY_OK,
            ]], $receiver, [
                'receiving_location_id' => $location->id,
                'remission_path' => 'remisiones/remision.pdf',
                'received_at' => now(),
            ]);

            $this->fail('Expected reception quantity validation to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('supera la cantidad pendiente', $exception->getMessage());
        }

        $this->assertDatabaseCount('receptions', 0);
        $this->assertSame(0.0, (float) $item->refresh()->quantity_received);
    }

    public function test_receiver_can_access_only_assigned_reception_location(): void
    {
        [$order, $location] = $this->createDirectOrder(['status' => 'ISSUED']);
        $assignedReceiver = User::factory()->create();
        $otherReceiver = User::factory()->create();

        $assignedReceiver->assignRole('receiver');
        $otherReceiver->assignRole('receiver');
        $location->users()->attach($assignedReceiver->id);

        $this->actingAs($assignedReceiver)
            ->get(route('receptions.create-direct', $order))
            ->assertOk();

        $this->actingAs($otherReceiver)
            ->get(route('receptions.create-direct', $order))
            ->assertForbidden();
    }

    private function createDirectOrder(array $attributes = []): array
    {
        $creator = User::factory()->create();
        $supplier = Supplier::factory()->create();

        $company = Company::factory()->create(['name' => 'TotalGas']);
        $costCenter = CostCenter::factory()->create([
            'company_id' => $company->id,
            'responsible_user_id' => $creator->id,
            'budget_type' => 'FREE_CONSUMPTION',
            'created_by' => $creator->id,
        ]);
        $expenseCategory = ExpenseCategory::factory()->create(['created_by' => $creator->id]);
        $location = ReceivingLocation::factory()->create([
            'company_id' => $company->id,
            'name' => 'Estacion prueba',
            'type' => 'service_station',
        ]);

        $order = DirectPurchaseOrder::create(array_merge([
            'folio' => uniqid('OCD-2026-'),
            'supplier_id' => $supplier->id,
            'cost_center_id' => $costCenter->id,
            'receiving_location_id' => $location->id,
            'application_month' => '2026-05',
            'justification' => 'Compra directa de prueba',
            'subtotal' => 500,
            'iva_amount' => 80,
            'total' => 580,
            'currency' => 'MXN',
            'status' => 'ISSUED',
            'created_by' => $creator->id,
            'issued_at' => now(),
        ], $attributes));

        DirectPurchaseOrderItem::factory()->create([
            'direct_purchase_order_id' => $order->id,
            'expense_category_id' => $expenseCategory->id,
            'cost_center_id' => $costCenter->id,
            'description' => 'Producto prueba',
            'quantity' => 5,
            'quantity_received' => 0,
            'unit_price' => 100,
            'iva_rate' => 16,
            'subtotal' => 500,
            'iva_amount' => 80,
            'total' => 580,
            'unit_of_measure' => 'pz',
        ]);

        return [$order->fresh(['items', 'supplier', 'receivingLocation']), $location, $supplier];
    }

    private function createDeliveredOrderWithAlertRecipients(): array
    {
        [$order, $location] = $this->createDirectOrder([
            'status' => 'DELIVERED_PENDING_RECEPTION',
            'supplier_delivered_at' => '2026-05-08 10:00:00',
            'reception_deadline_at' => '2026-05-13 10:00:00',
        ]);

        $receiver = User::factory()->create(['email' => 'receiver@example.com']);
        $buyer = User::factory()->create(['email' => 'buyer@example.com']);
        $finance = User::factory()->create(['email' => 'finance@example.com']);

        $receiver->assignRole('receiver');
        $buyer->assignRole('buyer');
        $finance->assignRole('superadmin');
        $location->users()->attach($receiver->id);

        return [$order, $location];
    }

    private function assertMailableWasSentToAllAlertRecipients(string $mailableClass): void
    {
        Mail::assertSent($mailableClass, function ($mail) {
            $recipients = collect($mail->to)->pluck('address')->all();

            return in_array('receiver@example.com', $recipients, true)
                && in_array('buyer@example.com', $recipients, true)
                && in_array('finance@example.com', $recipients, true);
        });
    }
}
