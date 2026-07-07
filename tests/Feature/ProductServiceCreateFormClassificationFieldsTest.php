<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ExpenseCategory;
use App\Models\BudgetCedula;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductServiceCreateFormClassificationFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_shows_expense_category_and_cedula_and_inventoriable_fields(): void
    {
        $user = User::factory()->create();
        $this->seed(RolePermissionSeeder::class);
        $user->assignRole('catalog_admin');

        $category = ExpenseCategory::factory()->create(['name' => 'Mantenimiento']);
        BudgetCedula::factory()->create(['expense_category_id' => $category->id, 'name' => 'Mantenimiento de Estaciones']);

        $response = $this->actingAs($user)->get(route('products-services.create'));

        $response->assertOk();
        $response->assertSee('name="expense_category_id"', false);
        $response->assertSee('name="budget_cedula_id"', false);
        $response->assertSee('name="is_inventoriable"', false);
        $response->assertSee('Mantenimiento', false);
        $response->assertSee('Mantenimiento de Estaciones', false);
    }
}
