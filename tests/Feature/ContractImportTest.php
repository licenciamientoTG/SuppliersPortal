<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ContractImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ContractImportTest extends TestCase
{
    use RefreshDatabase;

    private function makeCsvFile(string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'contract_import_') . '.csv';
        file_put_contents($path, $content);
        return new UploadedFile($path, 'contratos.csv', 'text/csv', null, true);
    }

    public function test_preview_returns_valid_and_error_rows(): void
    {
        $company  = Company::factory()->create(['code' => 'TG001', 'is_active' => true]);
        $supplier = Supplier::factory()->create(['rfc' => 'AAA010101AAA', 'status' => 'activo']);
        $product  = \App\Models\ProductService::factory()->create(['code' => 'PROD-001', 'is_active' => true, 'status' => 'ACTIVE']);

        $csv = "empresa_code,supplier_rfc,start_date,end_date,contract_amount,product_code,unit_price,currency\n"
             . "TG001,AAA010101AAA,2026-01-01,2026-12-31,50000,PROD-001,250.00,MXN\n"
             . "TG001,BADAAA,2026-01-01,2026-12-31,0,PROD-001,100,MXN\n"; // RFC inválido

        $service = app(ContractImportService::class);
        $result  = $service->preview($this->makeCsvFile($csv));

        $this->assertCount(1, $result['valid']);
        $this->assertCount(1, $result['errors']);
    }

    public function test_confirm_creates_contracts(): void
    {
        $user     = User::factory()->create();
        $company  = Company::factory()->create(['code' => 'TG001', 'is_active' => true]);
        $supplier = Supplier::factory()->create(['rfc' => 'AAA010101AAA', 'status' => 'activo']);
        $product  = \App\Models\ProductService::factory()->create(['code' => 'PROD-001', 'is_active' => true, 'status' => 'ACTIVE']);

        $csv = "empresa_code,supplier_rfc,start_date,end_date,contract_amount,product_code,unit_price,currency\n"
             . "TG001,AAA010101AAA,2026-01-01,2026-12-31,50000,PROD-001,250.00,MXN\n";

        $service = app(ContractImportService::class);
        $preview = $service->preview($this->makeCsvFile($csv));

        $this->actingAs($user);
        $created = $service->confirm($preview['valid']);

        $this->assertEquals(1, $created);
        $this->assertDatabaseCount('contracts', 1);
        $this->assertDatabaseCount('contract_products', 1);
    }

    public function test_preview_rejects_file_over_500_rows(): void
    {
        $header = "empresa_code,supplier_rfc,start_date,end_date,contract_amount,product_code,unit_price,currency\n";
        $row    = "TG001,AAA010101AAA,2026-01-01,2026-12-31,0,P1,1,MXN\n";
        $csv    = $header . str_repeat($row, 501);

        $service = app(ContractImportService::class);
        $result  = $service->preview($this->makeCsvFile($csv));

        $this->assertArrayHasKey('error', $result);
    }
}
