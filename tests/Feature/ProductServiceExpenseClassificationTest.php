<?php

namespace Tests\Feature;

use App\Models\BudgetCedula;
use App\Models\ExpenseCategory;
use App\Models\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductServiceExpenseClassificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_service_can_be_linked_to_multiple_expense_categories_and_cedulas(): void
    {
        $categoryA = ExpenseCategory::factory()->create();
        $categoryB = ExpenseCategory::factory()->create();
        $cedulaA = BudgetCedula::factory()->create(['expense_category_id' => $categoryA->id]);
        $cedulaB = BudgetCedula::factory()->create(['expense_category_id' => $categoryB->id]);

        $product = ProductService::factory()->create();
        $product->expenseCategories()->sync([$categoryA->id, $categoryB->id]);
        $product->budgetCedulas()->sync([$cedulaA->id, $cedulaB->id]);
        $product->refresh();

        $this->assertCount(2, $product->expenseCategories);
        $this->assertCount(2, $product->budgetCedulas);
        $this->assertTrue($product->expenseCategories->pluck('id')->contains($categoryA->id));
        $this->assertTrue($product->budgetCedulas->pluck('id')->contains($cedulaB->id));
    }

    public function test_product_service_can_have_no_classification(): void
    {
        $product = ProductService::factory()->create();

        $this->assertCount(0, $product->expenseCategories);
        $this->assertCount(0, $product->budgetCedulas);
    }

    public function test_single_column_classification_no_longer_exists(): void
    {
        $this->assertFalse(Schema::hasColumn('products_services', 'company_id'));
        $this->assertFalse(Schema::hasColumn('products_services', 'cost_center_id'));
        $this->assertFalse(Schema::hasColumn('products_services', 'category_id'));
        $this->assertFalse(Schema::hasColumn('products_services', 'expense_category_id'));
        $this->assertFalse(Schema::hasColumn('products_services', 'budget_cedula_id'));
        $this->assertTrue(Schema::hasTable('expense_category_product_service'));
        $this->assertTrue(Schema::hasTable('budget_cedula_product_service'));
    }
}
