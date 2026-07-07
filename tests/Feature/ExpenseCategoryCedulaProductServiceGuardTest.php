<?php

namespace Tests\Feature;

use App\Models\BudgetCedula;
use App\Models\ExpenseCategory;
use App\Models\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseCategoryCedulaProductServiceGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_is_in_use_when_linked_to_a_product(): void
    {
        $category = ExpenseCategory::factory()->create();
        $product = ProductService::factory()->create();
        $product->expenseCategories()->attach($category->id);

        $this->assertTrue($category->isInUse());
    }

    public function test_category_deactivation_is_blocked_when_linked_to_a_product(): void
    {
        $category = ExpenseCategory::factory()->create(['status' => 'ACTIVO']);
        $product = ProductService::factory()->create();
        $product->expenseCategories()->attach($category->id);

        $this->expectException(\Exception::class);
        $category->update(['status' => 'INACTIVO']);
    }

    public function test_category_without_products_is_not_in_use(): void
    {
        $category = ExpenseCategory::factory()->create();

        $this->assertFalse($category->isInUse());
    }

    public function test_cedula_is_in_use_when_linked_to_a_product(): void
    {
        $category = ExpenseCategory::factory()->create();
        $cedula = BudgetCedula::factory()->create(['expense_category_id' => $category->id]);
        $product = ProductService::factory()->create();
        $product->budgetCedulas()->attach($cedula->id);

        $this->assertTrue($cedula->isInUse());
    }

    public function test_cedula_deactivation_is_blocked_when_linked_to_a_product(): void
    {
        $category = ExpenseCategory::factory()->create();
        $cedula = BudgetCedula::factory()->create([
            'expense_category_id' => $category->id,
            'status' => 'ACTIVO',
        ]);
        $product = ProductService::factory()->create();
        $product->budgetCedulas()->attach($cedula->id);

        $this->expectException(\Exception::class);
        $cedula->update(['status' => 'INACTIVO']);
    }
}
