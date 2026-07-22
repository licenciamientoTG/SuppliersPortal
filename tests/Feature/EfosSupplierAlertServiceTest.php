<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use App\Notifications\SupplierListedInEfosNotification;
use App\Services\EfosSupplierAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EfosSupplierAlertServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'buyer', 'guard_name' => 'web']);
        Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_it_deactivates_listed_active_suppliers_and_sends_one_notification_per_recipient(): void
    {
        Notification::fake();
        $supplier = Supplier::factory()->create(['rfc' => 'ABC010101ABC', 'is_active' => true]);
        $secondSupplier = Supplier::factory()->create(['rfc' => 'DEF010101DEF', 'is_active' => true]);
        $buyer = User::factory()->create();
        $superadmin = User::factory()->create();
        $admin = User::factory()->create();
        $buyer->assignRole('buyer');
        $superadmin->assignRole('superadmin');
        $admin->assignRole('admin');
        $this->addEfosRecord($supplier, 'Definitivo');
        $this->addEfosRecord($secondSupplier, 'Presunto');

        $notified = app(EfosSupplierAlertService::class)->notifyListedActiveSuppliers();

        $this->assertSame(2, $notified);
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'is_active' => false]);
        $this->assertDatabaseHas('suppliers', ['id' => $secondSupplier->id, 'is_active' => false]);
        Notification::assertSentTo($buyer, SupplierListedInEfosNotification::class, function (SupplierListedInEfosNotification $notification) {
            return count($notification->suppliers) === 2
                && collect($notification->suppliers)->pluck('situation')->sort()->values()->all() === ['Definitivo', 'Presunto'];
        });
        Notification::assertSentTo($superadmin, SupplierListedInEfosNotification::class, function (SupplierListedInEfosNotification $notification) {
            return count($notification->suppliers) === 2;
        });
        Notification::assertSentTo($admin, SupplierListedInEfosNotification::class, function (SupplierListedInEfosNotification $notification) {
            return count($notification->suppliers) === 2;
        });
    }

    public function test_it_does_not_send_an_alert_for_suppliers_already_deactivated_by_a_previous_sync(): void
    {
        Notification::fake();
        $supplier = Supplier::factory()->create(['rfc' => 'ABC010101ABC', 'is_active' => true]);
        $buyer = User::factory()->create();
        $buyer->assignRole('buyer');
        $this->addEfosRecord($supplier, 'Presunto');

        $firstSync = app(EfosSupplierAlertService::class)->notifyListedActiveSuppliers();

        $this->assertSame(1, $firstSync);
        Notification::assertSentTo($buyer, SupplierListedInEfosNotification::class);

        Notification::fake();
        $secondSync = app(EfosSupplierAlertService::class)->notifyListedActiveSuppliers();

        $this->assertSame(0, $secondSync);
        Notification::assertNothingSent();
    }

    public function test_it_ignores_inactive_suppliers(): void
    {
        Notification::fake();
        $supplier = Supplier::factory()->create(['rfc' => 'ABC010101ABC', 'is_active' => false]);
        $buyer = User::factory()->create();
        $buyer->assignRole('buyer');
        $this->addEfosRecord($supplier, 'Definitivo');

        $notified = app(EfosSupplierAlertService::class)->notifyListedActiveSuppliers();

        $this->assertSame(0, $notified);
        Notification::assertNothingSent();
    }

    public function test_it_does_not_deactivate_active_suppliers_that_are_not_in_efos(): void
    {
        Notification::fake();
        $supplier = Supplier::factory()->create(['rfc' => 'ABC010101ABC', 'is_active' => true]);

        $notified = app(EfosSupplierAlertService::class)->notifyListedActiveSuppliers();

        $this->assertSame(0, $notified);
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'is_active' => true]);
        Notification::assertNothingSent();
    }

    public function test_efos_email_uses_the_corporate_notification_template(): void
    {
        $recipient = User::factory()->create(['name' => 'Compras TotalGas']);
        $notification = new SupplierListedInEfosNotification([
            ['id' => 1, 'name' => 'Proveedor EFOS', 'rfc' => 'ABC010101ABC', 'situation' => 'Definitivo'],
        ]);

        $mail = $notification->toMail($recipient);

        $this->assertSame('emails.notifications.supplier-listed-in-efos', $mail->view);
        $this->assertSame('Alerta EFOS: proveedores desactivados', $mail->subject);
        $this->assertSame('Compras TotalGas', $mail->viewData['name']);
        $this->assertSame('ABC010101ABC', $mail->viewData['suppliers'][0]['rfc']);
        $this->assertSame('Definitivo', $mail->viewData['suppliers'][0]['situation']);
        $this->assertSame(
            'Se desactivaron 1 proveedor(es) por aparecer en la lista EFOS del SAT.',
            $notification->toArray($recipient)['message']
        );
    }

    public function test_efos_email_template_tolerates_legacy_notifications_without_a_situation(): void
    {
        $html = view('emails.notifications.supplier-listed-in-efos', [
            'name' => 'Compras TotalGas',
            'suppliers' => [
                ['id' => 1, 'name' => 'Proveedor EFOS', 'rfc' => 'ABC010101ABC'],
            ],
            'url' => 'https://example.test/sat-efos-69b',
        ])->render();

        $this->assertStringContainsString('Estatus EFOS: No identificado', $html);
    }

    private function addEfosRecord(Supplier $supplier, string $situation): void
    {
        DB::table('sat_efos_69b')->insert([
            'number' => 1,
            'rfc' => $supplier->rfc,
            'company_name' => $supplier->company_name,
            'situation' => $situation,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
