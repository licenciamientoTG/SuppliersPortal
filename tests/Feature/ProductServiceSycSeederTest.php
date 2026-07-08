<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CostCenter;
use App\Models\User;
use Database\Seeders\ProductServiceSycSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductServiceSycSeederTest extends TestCase
{
    use RefreshDatabase;

    private string $csvPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->csvPath = base_path('storage/app/tmp/Productos_syc.csv');
        if (! is_dir(dirname($this->csvPath))) {
            mkdir(dirname($this->csvPath), 0777, true);
        }
    }

    public function test_syc_seeder_imports_products_and_is_idempotent(): void
    {
        $admin = User::factory()->create(['name' => 'Super Administrador']);
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'ESTACIONES',
            'description' => 'Categoria estaciones',
            'is_active' => true,
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $company = Company::create([
            'code' => 'SSY',
            'name' => 'Servicio SYC',
            'legal_name' => 'Servicio SYC',
            'rfc' => 'SSY940520271',
            'locale' => 'es_MX',
            'timezone' => 'America/Ciudad_Juarez',
            'currency_code' => 'MXN',
            'is_active' => true,
        ]);

        $costCenter = CostCenter::create([
            'code' => '1376',
            'name' => 'Delicias',
            'description' => null,
            'purchase_type' => 'Gasto Operativo',
            'company_id' => $company->id,
            'category_id' => $categoryId,
            'responsible_user_id' => $admin->id,
            'budget_type' => 'ANNUAL',
            'global_amount' => null,
            'free_consumption_justification' => null,
            'authorized_by' => null,
            'authorized_at' => null,
            'validity_date' => null,
            'status' => 'ACTIVO',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        file_put_contents($this->csvPath, implode("\n", [
            'empresa,des,udm_inv,clas1_nombre',
            '1G_TGS_SERVICIOSYC,MAGNA,LT,GASOLINA T-MAXIMA',
            '1G_TGS_SERVICIOSYC,SERVICIO DE GASTOS DE ENTREGA,SVR,FLETE PEMEX MAGNA',
        ]));

        $this->seed(ProductServiceSycSeeder::class);
        $this->seed(ProductServiceSycSeeder::class);

        $this->assertDatabaseCount('products_services', 2);
        $this->assertDatabaseHas('products_services', [
            'company_id' => $company->id,
            'cost_center_id' => $costCenter->id,
            'category_id' => $categoryId,
            'short_name' => 'MAGNA',
            'technical_description' => null,
            'product_type' => 'PRODUCTO',
            'unit_of_measure' => 'LITRO',
            'estimated_price' => 0,
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('products_services', [
            'short_name' => 'SERVICIO DE GASTOS DE ENTREGA',
            'unit_of_measure' => 'SERVICIO',
        ]);
    }

    public function test_store_allows_missing_technical_description(): void
    {
        $this->withoutMiddleware();

        $user = User::factory()->create();
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'OPERACIONES',
            'description' => 'Categoria operaciones',
            'is_active' => true,
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $companyId = DB::table('companies')->insertGetId([
            'code' => 'TST',
            'name' => 'Compania prueba',
            'legal_name' => 'Compania prueba SA de CV',
            'rfc' => 'TCP010101AAA',
            'locale' => 'es_MX',
            'timezone' => 'America/Mexico_City',
            'currency_code' => 'MXN',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $costCenterId = DB::table('cost_centers')->insertGetId([
            'code' => 'CC-PRUEBA',
            'name' => 'Centro Prueba',
            'description' => null,
            'purchase_type' => 'Gasto Operativo',
            'company_id' => $companyId,
            'category_id' => $categoryId,
            'responsible_user_id' => $user->id,
            'budget_type' => 'ANNUAL',
            'status' => 'ACTIVO',
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('products-services.store'), [
            'short_name' => 'Producto sin descripcion tecnica',
            'product_type' => 'PRODUCTO',
            'company_id' => $companyId,
            'cost_center_id' => $costCenterId,
            'unit_of_measure' => 'PIEZA',
            'estimated_price' => 0,
            'currency_code' => 'MXN',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('products_services', [
            'company_id' => $companyId,
            'cost_center_id' => $costCenterId,
            'short_name' => 'Producto sin descripcion tecnica',
            'technical_description' => null,
        ]);
    }

    protected function tearDown(): void
    {
        if (is_file($this->csvPath)) {
            @unlink($this->csvPath);
        }

        parent::tearDown();
    }
}
