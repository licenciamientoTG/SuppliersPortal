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

    public function test_edit_form_preselects_current_classification(): void
    {
        $user = User::factory()->create();
        $this->seed(RolePermissionSeeder::class);
        $user->assignRole('catalog_admin');

        $category = ExpenseCategory::factory()->create();
        $cedula = BudgetCedula::factory()->create(['expense_category_id' => $category->id, 'name' => 'Cedula Precargada']);
        $product = ProductService::factory()->create([
            'expense_category_id' => $category->id,
            'budget_cedula_id' => $cedula->id,
            'is_inventoriable' => true,
        ]);

        $response = $this->actingAs($user)->get(route('products-services.edit', $product));

        $response->assertOk();
        $response->assertSee('name="expense_category_id"', false);
        $response->assertSee('name="budget_cedula_id"', false);
        $response->assertSee('Cedula Precargada', false);
        $response->assertSee('checked', false);
    }
}
