<?php

namespace Tests\Feature;

use App\Models\ContractProduct;
use App\Models\ProductService;
use App\Models\User;
use Database\Seeders\AccountSubaccountSeeder;
use Database\Seeders\BudgetCedulaSeeder;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\ProductBudgetCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductBudgetCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create([
            'name' => 'Super Administrador',
            'email' => 'licenciamiento@totalgas.com',
        ]);
        $this->seed([
            ExpenseCategorySeeder::class,
            BudgetCedulaSeeder::class,
            AccountSubaccountSeeder::class,
        ]);
    }

    public function test_it_synchronizes_the_versioned_catalog_and_is_idempotent(): void
    {
        $this->seed(ProductBudgetCatalogSeeder::class);
        $this->seed(ProductBudgetCatalogSeeder::class);

        $this->assertDatabaseCount('products_services', 331);
        $this->assertDatabaseMissing('products_services', ['short_name' => 'PROYECTOS']);
        $this->assertDatabaseMissing('products_services', ['short_name' => 'FUMIGACION']);

        $product = ProductService::query()->where('short_name', 'SERVICIO DE GASTOS DE ENTREGA DIESEL')->firstOrFail();
        $this->assertSame('SERVICIO', $product->product_type);
        $this->assertSame('SERVICIO', $product->unit_of_measure);
        $this->assertSame('FLETE DIESEL', $product->subaccounts()->sole()->name);
        $this->assertSame('FLETE DIESEL', $product->budgetCedulas()->sole()->name);

        $maintenance = ProductService::query()->where('short_name', 'Rotulación de gajos')->firstOrFail();
        $this->assertSame('Mantenimiento General', $maintenance->subaccounts()->sole()->name);
    }

    public function test_it_preserves_pending_inventoriables_and_purges_other_non_inventoriables(): void
    {
        ProductService::factory()->create([
            'short_name' => 'MONOGRADO SAE 40',
            'technical_description' => 'Pendiente de clasificación inventariable.',
            'is_inventoriable' => false,
        ]);
        ProductService::factory()->create([
            'short_name' => 'PRODUCTO FUERA DE CATÁLOGO',
            'technical_description' => 'Debe eliminarse porque está fuera del catálogo vigente.',
            'is_inventoriable' => false,
        ]);

        $this->seed(ProductBudgetCatalogSeeder::class);

        $this->assertDatabaseHas('products_services', ['short_name' => 'MONOGRADO SAE 40']);
        $this->assertDatabaseMissing('products_services', ['short_name' => 'PRODUCTO FUERA DE CATÁLOGO']);
    }

    public function test_it_refuses_to_delete_an_out_of_scope_product_with_operational_references(): void
    {
        $product = ProductService::factory()->create([
            'short_name' => 'PRODUCTO REFERENCIADO FUERA DE CATÁLOGO',
            'technical_description' => 'No debe eliminarse porque forma parte de un contrato.',
            'is_inventoriable' => false,
        ]);
        ContractProduct::factory()->create(['product_service_id' => $product->id]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('contract_products');

        $this->seed(ProductBudgetCatalogSeeder::class);
    }
}
