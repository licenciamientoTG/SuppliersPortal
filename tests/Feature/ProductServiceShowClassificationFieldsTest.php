<?php

namespace Tests\Feature;

use App\Models\BudgetCedula;
use App\Models\ExpenseCategory;
use App\Models\ProductService;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductServiceShowClassificationFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_page_displays_multiple_categories_and_cedulas(): void
    {
        $user = User::factory()->create();
        $this->seed(RolePermissionSeeder::class);
        $user->assignRole('catalog_admin');

        $categoryA = ExpenseCategory::factory()->create(['name' => 'Mantenimiento']);
        $categoryB = ExpenseCategory::factory()->create(['name' => 'Gastos Fijos']);
        $cedulaA = BudgetCedula::factory()->create(['expense_category_id' => $categoryA->id, 'name' => 'Cedula Uno']);
        $cedulaB = BudgetCedula::factory()->create(['expense_category_id' => $categoryB->id, 'name' => 'Cedula Dos']);

        $product = ProductService::factory()->create(['is_inventoriable' => true]);
        $product->expenseCategories()->sync([$categoryA->id, $categoryB->id]);
        $product->budgetCedulas()->sync([$cedulaA->id, $cedulaB->id]);

        $response = $this->actingAs($user)->get(route('products-services.show', $product));

        $response->assertOk();
        $response->assertSee('Mantenimiento');
        $response->assertSee('Gastos Fijos');
        $response->assertSee('Cedula Uno');
        $response->assertSee('Cedula Dos');
        $response->assertSee('Sí');
    }

    public function test_show_page_handles_product_without_classification(): void
    {
        $user = User::factory()->create();
        $this->seed(RolePermissionSeeder::class);
        $user->assignRole('catalog_admin');

        $product = ProductService::factory()->create();

        $response = $this->actingAs($user)->get(route('products-services.show', $product));

        $response->assertOk();
        $response->assertSee('Sin clasificar');
    }
}
