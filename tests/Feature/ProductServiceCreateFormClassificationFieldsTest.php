<?php

namespace Tests\Feature;

use App\Models\BudgetCedula;
use App\Models\ExpenseCategory;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductServiceCreateFormClassificationFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_shows_single_cedula_select_with_category_prefix(): void
    {
        $user = User::factory()->create();
        $this->seed(RolePermissionSeeder::class);
        $user->assignRole('catalog_admin');

        $category = ExpenseCategory::factory()->create(['code' => 'MAN', 'name' => 'Mantenimiento']);
        BudgetCedula::factory()->create(['expense_category_id' => $category->id, 'name' => 'Mantenimiento de Estaciones']);

        $response = $this->actingAs($user)->get(route('products-services.create'));

        $response->assertOk();
        $response->assertDontSee('name="expense_category_ids[]"', false);
        $response->assertSee('name="budget_cedula_ids[]"', false);
        $response->assertSee('MAN - Mantenimiento de Estaciones', false);
    }
}
