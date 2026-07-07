<?php

namespace Tests\Feature;

use App\Models\BudgetCedula;
use App\Models\ExpenseCategory;
use App\Models\ProductService;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductServiceEditFormClassificationFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_form_preselects_current_multi_classification(): void
    {
        $user = User::factory()->create();
        $this->seed(RolePermissionSeeder::class);
        $user->assignRole('catalog_admin');

        $categoryA = ExpenseCategory::factory()->create();
        $categoryB = ExpenseCategory::factory()->create();
        $cedulaA = BudgetCedula::factory()->create(['expense_category_id' => $categoryA->id, 'name' => 'Cedula Precargada A']);
        $cedulaB = BudgetCedula::factory()->create(['expense_category_id' => $categoryB->id, 'name' => 'Cedula Precargada B']);

        $product = ProductService::factory()->create();
        $product->expenseCategories()->sync([$categoryA->id, $categoryB->id]);
        $product->budgetCedulas()->sync([$cedulaA->id, $cedulaB->id]);

        $response = $this->actingAs($user)->get(route('products-services.edit', $product));
        $content = $response->getContent();

        $response->assertOk();
        $response->assertSee('name="expense_category_ids[]"', false);
        $response->assertSee('Cedula Precargada A', false);
        $response->assertSee('Cedula Precargada B', false);
        $this->assertMatchesRegularExpression(
            '/<option value="' . $categoryA->id . '"\s+selected/',
            $content
        );
        $this->assertMatchesRegularExpression(
            '/<option value="' . $categoryB->id . '"\s+selected/',
            $content
        );
    }
}
