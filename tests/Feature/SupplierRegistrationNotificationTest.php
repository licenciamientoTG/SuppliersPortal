<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use App\Notifications\NewSupplierRegistrationForBuyerNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupplierRegistrationNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        Role::create(['name' => 'buyer', 'guard_name' => 'web']);
        Role::create(['name' => 'supplier', 'guard_name' => 'web']);
    }

    public function test_successful_public_registration_notifies_all_buyers(): void
    {
        Notification::fake();

        $buyers = User::factory()->count(2)->create();
        foreach ($buyers as $buyer) {
            $buyer->assignRole('buyer');
        }

        $response = $this->post(route('register'), $this->validPayload([
            'email' => 'proveedor@example.com',
            'rfc' => 'ABC123456T12',
        ]));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        foreach ($buyers as $buyer) {
            Notification::assertSentTo($buyer, NewSupplierRegistrationForBuyerNotification::class);
        }

        $this->assertDatabaseHas('suppliers', [
            'company_name' => 'Proveedor Demo SA de CV',
            'rfc' => 'ABC123456T12',
            'email' => 'proveedor@example.com',
            'postal_code' => '32500',
        ]);

        $supplier = Supplier::where('rfc', 'ABC123456T12')->firstOrFail();
        $this->assertSame('moral', $supplier->person_type);
        $this->assertSame(['601', '626'], collect($supplier->tax_regimes)->pluck('code')->all());
        $this->assertSame(['Comercializacion de insumos'], $supplier->economic_activity);
        $this->assertSame('32500', $supplier->postal_code);
    }

    public function test_notification_is_sent_via_mail_only(): void
    {
        Notification::fake();

        $buyer = User::factory()->create(['email' => 'buyer@example.com']);
        $buyer->assignRole('buyer');

        $this->post(route('register'), $this->validPayload([
            'email' => 'proveedor2@example.com',
            'rfc' => 'DEF123456T34',
        ]));

        Notification::assertSentTo($buyer, NewSupplierRegistrationForBuyerNotification::class, function ($notification) {
            return in_array('mail', $notification->via($notification));
        });

        Notification::assertSentTo($buyer, NewSupplierRegistrationForBuyerNotification::class, function ($notification) {
            return ! in_array('database', $notification->via($notification));
        });
    }

    public function test_registration_does_not_notify_when_rfc_is_in_efos(): void
    {
        Notification::fake();

        $buyer = User::factory()->create(['email' => 'buyer@example.com']);
        $buyer->assignRole('buyer');

        DB::table('sat_efos_69b')->insert([
            'number' => 1,
            'rfc' => 'GHI123456T56',
            'company_name' => 'Proveedor EFOS',
            'situation' => 'Definitivo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->from(route('register'))
            ->post(route('register'), $this->validPayload([
                'email' => 'proveedor3@example.com',
                'rfc' => 'GHI123456T56',
            ]));

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors('rfc');

        Notification::assertNothingSent();
        $this->assertDatabaseMissing('suppliers', ['rfc' => 'GHI123456T56']);
    }

    public function test_registration_does_not_notify_when_validation_fails(): void
    {
        Notification::fake();

        $buyer = User::factory()->create(['email' => 'buyer@example.com']);
        $buyer->assignRole('buyer');

        $response = $this->from(route('register'))
            ->post(route('register'), $this->validPayload([
                'company_name' => '',
                'email' => 'correo-invalido',
                'rfc' => 'RFCINVALIDO',
            ]));

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors(['company_name', 'email', 'rfc']);

        Notification::assertNothingSent();
    }

    public function test_failed_registration_redirects_back_to_the_first_step_with_errors(): void
    {
        Notification::fake();

        $response = $this->from(route('register'))
            ->post(route('register'), $this->validPayload([
                'company_name' => '',
                'rfc' => 'ABC123456T12',
            ]));

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors(['company_name']);
        $response->assertSessionHas('supplier_registration_step', 2);

        Notification::assertNothingSent();
    }

    public function test_successful_registration_without_buyers_still_completes(): void
    {
        Notification::fake();

        $response = $this->post(route('register'), $this->validPayload([
            'email' => 'sinbuyers@example.com',
            'rfc' => 'JKL123456T78',
        ]));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        Notification::assertNothingSent();

        $this->assertDatabaseHas('suppliers', [
            'rfc' => 'JKL123456T78',
            'email' => 'sinbuyers@example.com',
            'postal_code' => '32500',
        ]);
    }

    public function test_foreign_supplier_can_register_without_tax_regimes(): void
    {
        Notification::fake();

        $response = $this->post(route('register'), $this->validPayload([
            'email' => 'extranjero@example.com',
            'rfc' => 'EXT123456T90',
            'person_type' => 'extranjero',
            'tax_regimes' => [],
            'economic_activity' => ['Servicios internacionales', 'Consultoria tecnica'],
        ]));

        $response->assertRedirect(route('dashboard'));

        $supplier = Supplier::where('rfc', 'EXT123456T90')->firstOrFail();
        $this->assertSame('extranjero', $supplier->person_type);
        $this->assertSame([], $supplier->tax_regimes);
        $this->assertSame(['Servicios internacionales', 'Consultoria tecnica'], $supplier->economic_activity);
        $this->assertSame('32500', $supplier->postal_code);
    }

    public function test_registration_requires_tax_regimes_for_non_foreign_suppliers(): void
    {
        Notification::fake();

        $response = $this->from(route('register'))
            ->post(route('register'), $this->validPayload([
                'tax_regimes' => [],
                'person_type' => 'fisica',
                'rfc' => 'MOR123456T11',
            ]));

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors(['tax_regimes']);
        $response->assertSessionHas('supplier_registration_step', 2);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Juan',
            'last_name' => 'Proveedor',
            'email' => 'proveedor@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'company_name' => 'Proveedor Demo SA de CV',
            'rfc' => 'ABC123456T12',
            'supplier_type' => 'product_service',
            'person_type' => 'moral',
            'tax_regimes' => ['601', '626'],
            'address' => 'Av. Principal 123, Chihuahua',
            'postal_code' => '32500',
            'phone_number' => '6561234567',
            'contact_person' => 'Juan Proveedor',
            'contact_phone' => '6567654321',
            'provides_specialized_services' => '0',
            'economic_activity' => ['Comercializacion de insumos'],
            'default_payment_terms' => 'NET_30',
        ], $overrides);
    }
}
