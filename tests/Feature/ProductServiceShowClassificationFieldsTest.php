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

    public function test_show_page_displays_expense_category_cedula_and_inventoriable(): void
    {
        $user = User::factory()->create();
        $this->seed(RolePermissionSeeder::class);
        $user->assignRole('catalog_admin');

        $category = ExpenseCategory::factory()->create(['name' => 'Mantenimiento']);
        $cedula = BudgetCedula::factory()->create(['expense_category_id' => $category->id, 'name' => 'Cedula Visible']);
        $product = ProductService::factory()->create([
            'expense_category_id' => $category->id,
            'budget_cedula_id' => $cedula->id,
            'is_inventoriable' => true,
        ]);

        $response = $this->actingAs($user)->get(route('products-services.show', $product));

        $response->assertOk();
        $response->assertSee('Mantenimiento');
        $response->assertSee('Cedula Visible');
        $response->assertSee('Inventariable');
    }

    public function test_show_page_handles_product_without_classification(): void
    {
        $user = User::factory()->create();
        $this->seed(RolePermissionSeeder::class);
        $user->assignRole('catalog_admin');

        $product = ProductService::factory()->create([
            'expense_category_id' => null,
            'budget_cedula_id' => null,
        ]);

        $response = $this->actingAs($user)->get(route('products-services.show', $product));

        $response->assertOk();
    }
}
