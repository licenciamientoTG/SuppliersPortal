<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractProduct;
use App\Models\Supplier;
use App\Models\User;
use App\Models\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractCrudTest extends TestCase
{
    use RefreshDatabase;

    private function buyer(): User
    {
        \Spatie\Permission\Models\Role::findOrCreate('buyer', 'web');

        return User::factory()->create()->assignRole('buyer');
    }

    private function validPayload(Company $company, Supplier $supplier, array $overrides = []): array
    {
        return array_merge([
            'company_id'      => $company->id,
            'supplier_id'     => $supplier->id,
            'start_date'      => now()->toDateString(),
            'end_date'        => now()->addYear()->toDateString(),
            'contract_amount' => 50000,
            'products'        => [[
                'product_service_id' => ProductService::factory()->create()->id,
                'unit_price'         => 100.00,
                'currency_code'      => 'MXN',
                'unit_of_measure'    => 'PZA',
                'notes'              => null,
            ]],
        ], $overrides);
    }

    public function test_store_creates_contract_and_products(): void
    {
        $user     = $this->buyer();
        $company  = Company::factory()->create(['is_active' => true]);
        $supplier = Supplier::factory()->create(['status' => 'activo']);

        $response = $this->actingAs($user)
            ->post(route('contracts.store'), $this->validPayload($company, $supplier));

        $response->assertRedirect(route('contracts.index'));
        $this->assertDatabaseHas('contracts', [
            'company_id'  => $company->id,
            'supplier_id' => $supplier->id,
            'status'      => 'active',
        ]);
        $this->assertDatabaseCount('contract_products', 1);
    }

    public function test_folio_format_is_correct(): void
    {
        $user     = $this->buyer();
        $company  = Company::factory()->create(['is_active' => true]);
        $supplier = Supplier::factory()->create(['status' => 'activo']);

        $this->actingAs($user)
            ->post(route('contracts.store'), $this->validPayload($company, $supplier));

        $folio = Contract::latest()->value('folio');
        $this->assertMatchesRegularExpression('/^CONT-\d{4}-\d{3}$/', $folio);
    }

    public function test_store_fails_when_end_date_before_start_date(): void
    {
        $user     = $this->buyer();
        $company  = Company::factory()->create(['is_active' => true]);
        $supplier = Supplier::factory()->create(['status' => 'activo']);

        $response = $this->actingAs($user)->post(route('contracts.store'),
            $this->validPayload($company, $supplier, [
                'start_date' => now()->toDateString(),
                'end_date'   => now()->subDay()->toDateString(),
            ])
        );

        $response->assertSessionHasErrors('end_date');
        $this->assertDatabaseCount('contracts', 0);
    }

    public function test_cancel_sets_status_to_cancelled(): void
    {
        $user     = $this->buyer();
        $contract = Contract::factory()->create(['status' => 'active', 'end_date' => now()->addYear()]);

        $response = $this->actingAs($user)->post(route('contracts.cancel', $contract), [
            'cancellation_reason' => 'Proveedor no cumplió condiciones pactadas.',
        ]);

        $response->assertRedirect(route('contracts.show', $contract));
        $this->assertDatabaseHas('contracts', [
            'id'     => $contract->id,
            'status' => 'cancelled',
        ]);
        $contract->refresh();
        $this->assertNotNull($contract->cancelled_at);
    }

    public function test_cancel_requires_reason(): void
    {
        $user     = $this->buyer();
        $contract = Contract::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->post(route('contracts.cancel', $contract), [
            'cancellation_reason' => 'corto',
        ]);

        $response->assertSessionHasErrors('cancellation_reason');
        $contract->refresh();
        $this->assertEquals('active', $contract->getRawOriginal('status'));
    }

    public function test_update_replaces_products(): void
    {
        $user     = $this->buyer();
        $contract = Contract::factory()->create(['status' => 'active', 'end_date' => now()->addYear()]);
        $old      = ContractProduct::factory()->create(['contract_id' => $contract->id]);
        $newProd  = ProductService::factory()->create();

        $this->actingAs($user)->put(route('contracts.update', $contract), [
            'start_date'      => $contract->start_date->toDateString(),
            'end_date'        => $contract->end_date->toDateString(),
            'contract_amount' => 0,
            'products'        => [[
                'product_service_id' => $newProd->id,
                'unit_price'         => 200,
                'currency_code'      => 'MXN',
                'unit_of_measure'    => 'KG',
                'notes'              => null,
            ]],
        ]);

        $this->assertDatabaseMissing('contract_products', ['id' => $old->id]);
        $this->assertDatabaseHas('contract_products', ['product_service_id' => $newProd->id]);
    }
}
