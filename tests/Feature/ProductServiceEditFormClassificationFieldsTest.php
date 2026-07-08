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

    public function test_edit_form_preselects_current_cedulas(): void
    {
        $user = User::factory()->create();
        $this->seed(RolePermissionSeeder::class);
        $user->assignRole('catalog_admin');

        $categoryA = ExpenseCategory::factory()->create(['code' => 'MNT']);
        $categoryB = ExpenseCategory::factory()->create(['code' => 'TEC']);
        $cedulaA = BudgetCedula::factory()->create(['expense_category_id' => $categoryA->id, 'name' => 'Cedula Precargada A']);
        $cedulaB = BudgetCedula::factory()->create(['expense_category_id' => $categoryB->id, 'name' => 'Cedula Precargada B']);

        $product = ProductService::factory()->create();
        $product->expenseCategories()->sync([$categoryA->id, $categoryB->id]);
        $product->budgetCedulas()->sync([$cedulaA->id, $cedulaB->id]);

        $response = $this->actingAs($user)->get(route('products-services.edit', $product));
        $content = $response->getContent();

        $response->assertOk();
        $response->assertDontSee('name="expense_category_ids[]"', false);
        $response->assertSee('MNT - Cedula Precargada A', false);
        $response->assertSee('TEC - Cedula Precargada B', false);
        $this->assertMatchesRegularExpression(
            '/<option value="' . $cedulaA->id . '"\s+selected/',
            $content
        );
        $this->assertMatchesRegularExpression(
            '/<option value="' . $cedulaB->id . '"\s+selected/',
            $content
        );
    }
}
