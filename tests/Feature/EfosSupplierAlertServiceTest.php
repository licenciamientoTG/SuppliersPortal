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
    }

    public function test_it_sends_one_notification_per_recipient_with_all_listed_active_suppliers(): void
    {
        Notification::fake();
        $supplier = Supplier::factory()->create(['rfc' => 'ABC010101ABC', 'is_active' => true]);
        $secondSupplier = Supplier::factory()->create(['rfc' => 'DEF010101DEF', 'is_active' => true]);
        $buyer = User::factory()->create();
        $superadmin = User::factory()->create();
        $buyer->assignRole('buyer');
        $superadmin->assignRole('superadmin');
        $this->addEfosRecord($supplier, 'Definitivo');
        $this->addEfosRecord($secondSupplier, 'Presunto');

        $notified = app(EfosSupplierAlertService::class)->notifyListedActiveSuppliers();

        $this->assertSame(2, $notified);
        Notification::assertSentTo($buyer, SupplierListedInEfosNotification::class, function (SupplierListedInEfosNotification $notification) {
            return count($notification->suppliers) === 2;
        });
        Notification::assertSentTo($superadmin, SupplierListedInEfosNotification::class, function (SupplierListedInEfosNotification $notification) {
            return count($notification->suppliers) === 2;
        });
    }

    public function test_it_sends_an_alert_even_if_the_supplier_was_already_listed_before_sync(): void
    {
        Notification::fake();
        $supplier = Supplier::factory()->create(['rfc' => 'ABC010101ABC', 'is_active' => true]);
        $buyer = User::factory()->create();
        $buyer->assignRole('buyer');
        $this->addEfosRecord($supplier, 'Presunto');

        $notified = app(EfosSupplierAlertService::class)->notifyListedActiveSuppliers();

        $this->assertSame(1, $notified);
        Notification::assertSentTo($buyer, SupplierListedInEfosNotification::class);
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

    public function test_efos_email_uses_the_corporate_notification_template(): void
    {
        $recipient = User::factory()->create(['name' => 'Compras TotalGas']);
        $notification = new SupplierListedInEfosNotification([
            ['id' => 1, 'name' => 'Proveedor EFOS', 'rfc' => 'ABC010101ABC'],
        ]);

        $mail = $notification->toMail($recipient);

        $this->assertSame('emails.notifications.supplier-listed-in-efos', $mail->view);
        $this->assertSame('Compras TotalGas', $mail->viewData['name']);
        $this->assertSame('ABC010101ABC', $mail->viewData['suppliers'][0]['rfc']);
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
