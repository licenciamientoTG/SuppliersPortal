<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CostCenter;
use App\Models\DirectPurchaseOrder;
use App\Models\DirectPurchaseOrderItem;
use App\Models\ExpenseCategory;
use App\Models\ReceivingLocation;
use App\Models\Reception;
use App\Models\ReceptionAttachment;
use App\Models\ReceptionItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ReceptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReceptionAttachmentHashTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['receiver', 'buyer', 'superadmin', 'staff', 'accounting'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_receiving_with_remission_data_records_its_sha256_and_metadata(): void
    {
        Notification::fake();

        [$order, $location] = $this->createDirectOrder();
        $item = $order->items()->first();
        $receiver = User::factory()->create();

        $reception = app(ReceptionService::class)->receive($order, [[
            'receivable_item_id' => $item->id,
            'quantity_received' => 5,
            'conformity' => ReceptionItem::CONFORMITY_OK,
        ]], $receiver, [
            'receiving_location_id' => $location->id,
            'received_at' => now(),
            'remission_path' => 'remisiones/remision.pdf',
            'remission_sha256' => hash('sha256', 'contenido de prueba'),
            'remission_meta' => [
                'original_name' => 'remision.pdf',
                'mime_type' => 'application/pdf',
                'size' => 123,
            ],
        ]);

        $attachment = ReceptionAttachment::where('reception_id', $reception->id)
            ->where('attachable_type', ReceptionAttachment::TYPE_REMISSION)
            ->firstOrFail();

        $this->assertSame('remisiones/remision.pdf', $attachment->path);
        $this->assertSame(hash('sha256', 'contenido de prueba'), $attachment->sha256);
        $this->assertSame('remision.pdf', $attachment->original_name);
        $this->assertSame('application/pdf', $attachment->mime_type);
        $this->assertSame(123, $attachment->size);
        $this->assertSame(ReceptionAttachment::HASH_ORIGIN_EN_CARGA, $attachment->hash_origin);
        $this->assertSame($receiver->id, $attachment->uploaded_by);
        $this->assertNull($attachment->reception_item_id);
    }

    public function test_receiving_with_a_photo_records_its_sha256_and_links_to_the_item(): void
    {
        Notification::fake();

        [$order, $location] = $this->createDirectOrder();
        $item = $order->items()->first();
        $receiver = User::factory()->create();

        $reception = app(ReceptionService::class)->receive($order, [[
            'receivable_item_id' => $item->id,
            'quantity_received' => 5,
            'conformity' => ReceptionItem::CONFORMITY_FAIL,
            'nonconformity_type' => 'defective',
            'nonconformity_notes' => str_repeat('Producto no conforme. ', 6),
            'photos' => ['recepciones/fotos/foto1.jpg'],
            'photo_attachments' => [[
                'path' => 'recepciones/fotos/foto1.jpg',
                'sha256' => hash('sha256', 'foto de prueba'),
                'original_name' => 'foto1.jpg',
                'mime_type' => 'image/jpeg',
                'size' => 456,
            ]],
        ]], $receiver, [
            'receiving_location_id' => $location->id,
            'received_at' => now(),
            'remission_path' => 'remisiones/remision.pdf',
            'remission_sha256' => hash('sha256', 'contenido de prueba'),
        ]);

        $receptionItem = $reception->items()->first();

        $attachment = ReceptionAttachment::where('attachable_type', ReceptionAttachment::TYPE_PHOTO)
            ->where('reception_item_id', $receptionItem->id)
            ->firstOrFail();

        $this->assertSame('recepciones/fotos/foto1.jpg', $attachment->path);
        $this->assertSame(hash('sha256', 'foto de prueba'), $attachment->sha256);
        $this->assertSame('foto1.jpg', $attachment->original_name);
        $this->assertSame($reception->id, $attachment->reception_id);
    }

    public function test_receiving_without_attachment_metadata_does_not_create_attachment_rows(): void
    {
        Notification::fake();

        [$order, $location] = $this->createDirectOrder();
        $item = $order->items()->first();
        $receiver = User::factory()->create();

        app(ReceptionService::class)->receive($order, [[
            'receivable_item_id' => $item->id,
            'quantity_received' => 5,
            'conformity' => ReceptionItem::CONFORMITY_OK,
        ]], $receiver, [
            'receiving_location_id' => $location->id,
            'received_at' => now(),
            'remission_path' => 'remisiones/remision.pdf',
        ]);

        $this->assertDatabaseCount('reception_attachments', 0);
    }

    public function test_http_reception_persists_remission_attachment_with_matching_sha256(): void
    {
        Notification::fake();
        Storage::fake('public');

        [$order, $location] = $this->createDirectOrder();
        $item = $order->items()->first();
        $receiver = User::factory()->create();
        $receiver->assignRole('receiver');

        $this->actingAs($receiver)->post(route('receptions.store-direct', $order), [
            'receiving_location_id' => $location->id,
            'received_at' => now()->format('Y-m-d H:i:s'),
            'remission_file' => UploadedFile::fake()->create('remision.pdf', 50, 'application/pdf'),
            'items' => [[
                'receivable_item_id' => $item->id,
                'quantity_received' => 5,
                'conformity' => 'CONFORME',
            ]],
        ])->assertSessionHasNoErrors();

        $reception = Reception::firstOrFail();
        $attachment = ReceptionAttachment::where('reception_id', $reception->id)
            ->where('attachable_type', ReceptionAttachment::TYPE_REMISSION)
            ->firstOrFail();

        $storedContents = Storage::disk('public')->get($reception->remission_path);

        $this->assertSame(hash('sha256', $storedContents), $attachment->sha256);
        $this->assertSame('remision.pdf', $attachment->original_name);
        $this->assertSame(ReceptionAttachment::HASH_ORIGIN_EN_CARGA, $attachment->hash_origin);
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

        return [$order->fresh(['items', 'supplier', 'receivingLocation']), $location];
    }
}
