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

        $response->assertRedirect(route('supplier.documents.index'));
        $this->assertAuthenticated('supplier');

        foreach ($buyers as $buyer) {
            Notification::assertSentTo($buyer, NewSupplierRegistrationForBuyerNotification::class);
        }

        $this->assertDatabaseHas('suppliers', [
            'company_name' => 'Proveedor Demo SA de CV',
            'rfc' => 'ABC123456T12',
            'email' => 'proveedor@example.com',
            'postal_code' => '32500',
            'approval_status' => 'pending',
            'document_status' => 'pending',
        ]);

        $supplier = Supplier::where('rfc', 'ABC123456T12')->firstOrFail();
        $this->assertSame('moral', $supplier->person_type);
        $this->assertSame(['601', '626'], collect($supplier->tax_regimes)->pluck('code')->all());
        $this->assertSame(['Comercializacion de insumos'], $supplier->economic_activity);
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
