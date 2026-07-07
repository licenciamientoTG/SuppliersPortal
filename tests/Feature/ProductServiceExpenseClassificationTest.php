<?php

namespace Tests\Feature;

use App\Models\BudgetCedula;
use App\Models\ExpenseCategory;
use App\Models\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductServiceExpenseClassificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_service_can_be_linked_to_expense_category_and_cedula(): void
    {
        $category = ExpenseCategory::factory()->create();
        $cedula = BudgetCedula::factory()->create(['expense_category_id' => $category->id]);

        $product = ProductService::factory()->create([
            'expense_category_id' => $category->id,
            'budget_cedula_id' => $cedula->id,
            'is_inventoriable' => true,
        ]);

        $this->assertTrue($product->expenseCategory->is($category));
        $this->assertTrue($product->budgetCedula->is($cedula));
        $this->assertTrue($product->fresh()->is_inventoriable);
    }

    public function test_expense_category_and_cedula_are_nullable(): void
    {
        $product = ProductService::factory()->create([
            'expense_category_id' => null,
            'budget_cedula_id' => null,
        ]);

        $this->assertNull($product->fresh()->expense_category_id);
        $this->assertNull($product->fresh()->budget_cedula_id);
    }

    public function test_migration_backfills_is_inventoriable_from_product_type(): void
    {
        Artisan::call('migrate:rollback', ['--step' => 1]);

        $now = now();
        $id = DB::table('products_services')->insertGetId([
            'code' => 'PROD-BKFL01',
            'technical_description' => 'Servicio de prueba para backfill',
            'product_type' => 'SERVICIO',
            'category_id' => \App\Models\Category::factory()->create()->id,
            'cost_center_id' => \App\Models\CostCenter::factory()->create()->id,
            'company_id' => \App\Models\Company::factory()->create()->id,
            'unit_of_measure' => 'SERVICIO',
            'estimated_price' => 100,
            'status' => 'ACTIVE',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Artisan::call('migrate');

        $row = DB::table('products_services')->find($id);
        $this->assertEquals(0, $row->is_inventoriable);
    }
}
