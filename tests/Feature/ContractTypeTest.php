<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contract;
use App\Models\ProductService;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ContractImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContractTypeTest extends TestCase
{
    use RefreshDatabase;

    private const CSV_HEADER = "empresa_code,supplier_rfc,start_date,end_date,contract_amount,product_code,unit_price,currency,unit_of_measure,contract_type\n";

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('buyer', 'web');
        $this->buyer = User::factory()->create()->assignRole('buyer');
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'company_id'      => Company::factory()->create(['is_active' => true])->id,
            'supplier_id'     => Supplier::factory()->create()->id,
            'contract_type'   => 'iguala',
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

    private function makeCsvFile(string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'contract_type_') . '.csv';
        file_put_contents($path, $content);

        return new UploadedFile($path, 'contratos.csv', 'text/csv', null, true);
    }

    public function test_store_requires_valid_contract_type(): void
    {
        $response = $this->actingAs($this->buyer)
            ->post(route('contracts.store'), $this->validPayload(['contract_type' => null]));
        $response->assertSessionHasErrors('contract_type');

        $response = $this->actingAs($this->buyer)
            ->post(route('contracts.store'), $this->validPayload(['contract_type' => 'otro']));
        $response->assertSessionHasErrors('contract_type');

        $this->assertDatabaseCount('contracts', 0);
    }

    public function test_store_persists_contract_type(): void
    {
        $this->actingAs($this->buyer)
            ->post(route('contracts.store'), $this->validPayload(['contract_type' => 'convenio']));

        $this->assertDatabaseHas('contracts', ['contract_type' => 'convenio']);
    }

    public function test_create_view_explains_both_contract_types(): void
    {
        $response = $this->actingAs($this->buyer)->get(route('contracts.create'));

        $response->assertOk();
        $response->assertSee('Monto fijo ya comprometido y autorizado al contratar', false);
        $response->assertSee('Solo pacta precios con el proveedor; no compromete gasto', false);
        $response->assertSee('Convenio de precios');
        $response->assertSee('Iguala');
    }

    public function test_show_displays_contract_type(): void
    {
        $contract = Contract::factory()->create([
            'status'        => 'active',
            'contract_type' => 'convenio',
            'end_date'      => now()->addYear(),
        ]);

        $response = $this->actingAs($this->buyer)->get(route('contracts.show', $contract));

        $response->assertOk();
        $response->assertSee('Convenio de precios');
    }

    public function test_import_requires_valid_contract_type(): void
    {
        Company::factory()->create(['code' => 'TG001', 'is_active' => true]);
        Supplier::factory()->create(['rfc' => 'AAA010101AAA']);
        ProductService::factory()->create(['code' => 'PROD-001', 'is_active' => true, 'status' => 'ACTIVE']);
        ProductService::factory()->create(['code' => 'PROD-002', 'is_active' => true, 'status' => 'ACTIVE']);

        $csv = self::CSV_HEADER
             . "TG001,AAA010101AAA,2026-01-01,2026-12-31,50000,PROD-001,250.00,MXN,PZA,\n"
             . "TG001,AAA010101AAA,2026-01-01,2026-12-31,50000,PROD-002,250.00,MXN,PZA,otro\n";

        $result = app(ContractImportService::class)->preview($this->makeCsvFile($csv));

        $this->assertCount(0, $result['valid']);
        $this->assertCount(2, $result['errors']);
    }

    public function test_import_persists_contract_type(): void
    {
        Company::factory()->create(['code' => 'TG001', 'is_active' => true]);
        Supplier::factory()->create(['rfc' => 'AAA010101AAA']);
        ProductService::factory()->create(['code' => 'PROD-001', 'is_active' => true, 'status' => 'ACTIVE']);

        $csv = self::CSV_HEADER
             . "TG001,AAA010101AAA,2026-01-01,2026-12-31,50000,PROD-001,250.00,MXN,PZA,convenio\n";

        $service = app(ContractImportService::class);
        $preview = $service->preview($this->makeCsvFile($csv));

        $this->actingAs($this->buyer);
        $service->confirm($preview['valid']);

        $this->assertDatabaseHas('contracts', ['contract_type' => 'convenio']);
    }
}
