<?php

namespace Tests\Feature;

use App\Models\BudgetCedula;
use App\Models\ExpenseCategory;
use App\Models\ProductService;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductServiceExpenseClassificationPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_store_persists_multiple_categories_cedulas_and_inventoriable(): void
    {
        $user = $this->createUser();
        $categoryA = ExpenseCategory::factory()->create();
        $categoryB = ExpenseCategory::factory()->create();
        $cedulaA = BudgetCedula::factory()->create(['expense_category_id' => $categoryA->id]);
        $cedulaB = BudgetCedula::factory()->create(['expense_category_id' => $categoryB->id]);

        $response = $this->actingAs($user)->post(route('products-services.store'), [
            'product_type' => 'PRODUCTO',
            'unit_of_measure' => 'PIEZA',
            'estimated_price' => 100,
            'budget_cedula_ids' => [$cedulaA->id, $cedulaB->id],
            'is_inventoriable' => '1',
        ]);

        $response->assertRedirect();
        $product = ProductService::latest('id')->first();
        $this->assertCount(2, $product->expenseCategories);
        $this->assertEqualsCanonicalizing(
            [$categoryA->id, $categoryB->id],
            $product->expenseCategories->pluck('id')->all()
        );
        $this->assertCount(2, $product->budgetCedulas);
        $this->assertTrue($product->is_inventoriable);
    }

    public function test_store_without_classification_persists_empty_relations(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->post(route('products-services.store'), [
            'product_type' => 'SERVICIO',
            'unit_of_measure' => 'SERVICIO',
            'estimated_price' => 100,
        ]);

        $response->assertRedirect();
        $product = ProductService::latest('id')->first();
        $this->assertCount(0, $product->expenseCategories);
        $this->assertCount(0, $product->budgetCedulas);
    }

    public function test_update_replaces_classification(): void
    {
        $user = $this->createUser();
        $categoryA = ExpenseCategory::factory()->create();
        $categoryB = ExpenseCategory::factory()->create();
        $cedulaA = BudgetCedula::factory()->create(['expense_category_id' => $categoryA->id]);
        $cedulaB = BudgetCedula::factory()->create(['expense_category_id' => $categoryB->id]);

        $product = ProductService::factory()->create();
        $product->expenseCategories()->sync([$categoryA->id]);
        $product->budgetCedulas()->sync([$cedulaA->id]);

        $response = $this->actingAs($user)->put(route('products-services.update', $product), [
            'product_type' => $product->product_type,
            'unit_of_measure' => $product->unit_of_measure,
            'estimated_price' => $product->estimated_price,
            'budget_cedula_ids' => [$cedulaB->id],
            'is_inventoriable' => '1',
        ]);

        $response->assertRedirect();
        $product->refresh();
        $this->assertEquals([$categoryB->id], $product->expenseCategories->pluck('id')->all());
        $this->assertEquals([$cedulaB->id], $product->budgetCedulas->pluck('id')->all());
    }

    private function createUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('catalog_admin');

        return $user;
    }
}
