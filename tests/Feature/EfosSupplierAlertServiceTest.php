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

    public function test_it_notifies_buyers_and_administrators_for_every_listed_active_supplier(): void
    {
        Notification::fake();
        $supplier = Supplier::factory()->create(['rfc' => 'ABC010101ABC', 'is_active' => true]);
        $buyer = User::factory()->create();
        $superadmin = User::factory()->create();
        $admin = User::factory()->create();
        $buyer->assignRole('buyer');
        $superadmin->assignRole('superadmin');
        $admin->assignRole('admin');
        $this->addEfosRecord($supplier, 'Definitivo');

        $notified = app(EfosSupplierAlertService::class)->notifyListedActiveSuppliers();

        $this->assertSame(1, $notified);
        Notification::assertSentTo([$buyer, $superadmin, $admin], SupplierListedInEfosNotification::class);
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
