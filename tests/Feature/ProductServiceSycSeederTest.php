<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ProductServiceSycSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        file_put_contents($this->csvPath, implode("\n", [
            'empresa,des,udm_inv,clas1_nombre',
            '1G_TGS_SERVICIOSYC,MAGNA,LT,GASOLINA T-MAXIMA',
            '1G_TGS_SERVICIOSYC,SERVICIO DE GASTOS DE ENTREGA,SVR,FLETE PEMEX MAGNA',
        ]));

        $this->seed(ProductServiceSycSeeder::class);
        $this->seed(ProductServiceSycSeeder::class);

        $this->assertDatabaseCount('products_services', 2);
        $this->assertDatabaseHas('products_services', [
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

        $response = $this->actingAs($user)->post(route('products-services.store'), [
            'short_name' => 'Producto sin descripcion tecnica',
            'product_type' => 'PRODUCTO',
            'unit_of_measure' => 'PIEZA',
            'estimated_price' => 0,
            'currency_code' => 'MXN',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('products_services', [
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
