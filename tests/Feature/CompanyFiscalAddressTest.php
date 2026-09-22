<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckLockScreen;
use App\Http\Middleware\ModuleAccess;
use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\Requisition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyFiscalAddressTest extends TestCase
{
    use RefreshDatabase;

    private const FISCAL_DATA = [
        'tax_regime' => '601',
        'fiscal_street' => 'Av. Insurgentes Sur',
        'fiscal_exterior_number' => '1234',
        'fiscal_interior_number' => '5',
        'fiscal_neighborhood' => 'Del Valle',
        'fiscal_municipality' => 'Benito Juárez',
        'fiscal_state' => 'Ciudad de México',
        'fiscal_postal_code' => '03100',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([ModuleAccess::class, CheckLockScreen::class]);
        $this->actingAs(User::factory()->create());
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'TGAS',
            'name' => 'TotalGas',
            'legal_name' => 'Servicios Gasolineros de México S.A. de C.V.',
            'rfc' => 'SGM120315AB1',
            'is_active' => 1,
        ], $overrides);
    }

    public function test_company_can_be_created_with_tax_regime_and_fiscal_address(): void
    {
        $this->postJson(route('companies.store'), $this->payload(self::FISCAL_DATA))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('companies', ['code' => 'TGAS'] + self::FISCAL_DATA);
    }

    public function test_company_can_still_be_saved_without_fiscal_address(): void
    {
        $this->postJson(route('companies.store'), $this->payload())->assertOk();

        $this->assertDatabaseHas('companies', [
            'code' => 'TGAS',
            'tax_regime' => null,
            'fiscal_street' => null,
            'fiscal_postal_code' => null,
        ]);
    }

    public function test_partial_fiscal_address_requires_the_remaining_fields(): void
    {
        $this->postJson(route('companies.store'), $this->payload(['fiscal_street' => 'Av. Insurgentes Sur']))
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'fiscal_exterior_number',
                'fiscal_neighborhood',
                'fiscal_municipality',
                'fiscal_state',
                'fiscal_postal_code',
            ])
            ->assertJsonMissingValidationErrors(['fiscal_street', 'fiscal_interior_number']);

        $this->assertDatabaseMissing('companies', ['code' => 'TGAS']);
    }

    public function test_invalid_postal_code_and_tax_regime_are_rejected(): void
    {
        $this->postJson(route('companies.store'), $this->payload(array_merge(self::FISCAL_DATA, [
            'tax_regime' => '999',
            'fiscal_postal_code' => '31A',
        ])))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tax_regime', 'fiscal_postal_code']);
    }

    public function test_updating_a_company_adds_fiscal_data_without_touching_other_companies(): void
    {
        $company = Company::factory()->create(['code' => 'TGAS', 'name' => 'TotalGas']);
        $other = Company::factory()->create();
        $otherBefore = $other->only(['code', 'name', 'legal_name', 'rfc']);

        $this->putJson(route('companies.update', $company), $this->payload(self::FISCAL_DATA + [
            'legal_name' => $company->legal_name,
            'rfc' => $company->rfc,
        ]))->assertOk();

        $this->assertDatabaseHas('companies', ['id' => $company->id, 'rfc' => $company->rfc] + self::FISCAL_DATA);
        $this->assertDatabaseHas('companies', ['id' => $other->id, 'tax_regime' => null] + $otherBefore);
        $this->assertDatabaseCount('companies', 2);
    }

    public function test_edit_form_shows_saved_fiscal_data(): void
    {
        $company = Company::factory()->create(self::FISCAL_DATA);

        $this->get(route('companies.edit', $company))
            ->assertOk()
            ->assertSee('Datos fiscales')
            ->assertSee('value="601" selected', false)
            ->assertSee('value="Av. Insurgentes Sur"', false)
            ->assertSee('value="03100"', false);
    }

    public function test_datatable_includes_tax_regime_and_fiscal_address(): void
    {
        $company = Company::factory()->create(self::FISCAL_DATA);
        $withoutAddress = Company::factory()->create();

        $rows = collect($this->getJson(route('companies.datatable'))->assertOk()->json('data'))->keyBy('id');

        $this->assertSame('601 · General de Ley Personas Morales', $rows[$company->id]['tax_regime_label']);
        $this->assertStringContainsString('Av. Insurgentes Sur 1234, Int. 5, Del Valle', $rows[$company->id]['fiscal_address']);
        $this->assertStringContainsString('C.P. 03100', $rows[$company->id]['fiscal_address']);
        $this->assertStringContainsString('Sin capturar', $rows[$withoutAddress->id]['fiscal_address']);
    }

    public function test_purchase_order_pdf_letterhead_shows_fiscal_address(): void
    {
        $company = Company::factory()->create(self::FISCAL_DATA);
        $purchaseOrder = (new PurchaseOrder(['folio' => 'OC-TEST-001', 'currency' => 'MXN', 'subtotal' => 0, 'iva_amount' => 0, 'total' => 0]))
            ->setRelation('items', collect())
            ->setRelation('requisition', (new Requisition)->setRelation('company', $company));

        $html = view('purchase-orders.pdf', [
            'purchaseOrder' => $purchaseOrder,
            'logoPath' => public_path('images/logos/logo_TotalGas_hor.png'),
        ])->render();

        $this->assertStringContainsString('Régimen 601', $html);
        $this->assertStringContainsString('Av. Insurgentes Sur 1234, Int. 5, Del Valle', $html);
        $this->assertStringContainsString('Benito Juárez, Ciudad de México · C.P. 03100', $html);
    }
}
