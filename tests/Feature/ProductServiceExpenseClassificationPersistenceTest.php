<?php

namespace Tests\Feature;

use App\Models\BudgetCedula;
use App\Models\Category;
use App\Models\Company;
use App\Models\CostCenter;
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

    public function test_store_persists_expense_category_cedula_and_inventoriable(): void
    {
        [$user, $company, $costCenter] = $this->createContext();
        $category = ExpenseCategory::factory()->create();
        $cedula = BudgetCedula::factory()->create(['expense_category_id' => $category->id]);

        $response = $this->actingAs($user)->post(route('products-services.store'), [
            'product_type' => 'PRODUCTO',
            'company_id' => $company->id,
            'cost_center_id' => $costCenter->id,
            'unit_of_measure' => 'PIEZA',
            'estimated_price' => 100,
            'expense_category_id' => $category->id,
            'budget_cedula_id' => $cedula->id,
            'is_inventoriable' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('products_services', [
            'expense_category_id' => $category->id,
            'budget_cedula_id' => $cedula->id,
            'is_inventoriable' => true,
        ]);
    }

    public function test_store_without_classification_persists_nulls(): void
    {
        [$user, $company, $costCenter] = $this->createContext();

        $response = $this->actingAs($user)->post(route('products-services.store'), [
            'product_type' => 'SERVICIO',
            'company_id' => $company->id,
            'cost_center_id' => $costCenter->id,
            'unit_of_measure' => 'SERVICIO',
            'estimated_price' => 100,
        ]);

        $response->assertRedirect();
        $product = ProductService::latest('id')->first();
        $this->assertNull($product->expense_category_id);
        $this->assertNull($product->budget_cedula_id);
        $this->assertFalse((bool) $product->is_inventoriable);
    }

    public function test_update_persists_new_classification(): void
    {
        [$user, $company, $costCenter] = $this->createContext();
        $category = ExpenseCategory::factory()->create();
        $cedula = BudgetCedula::factory()->create(['expense_category_id' => $category->id]);

        $product = ProductService::factory()->create([
            'company_id' => $company->id,
            'cost_center_id' => $costCenter->id,
        ]);

        $response = $this->actingAs($user)->put(route('products-services.update', $product), [
            'product_type' => $product->product_type,
            'company_id' => $company->id,
            'cost_center_id' => $costCenter->id,
            'unit_of_measure' => $product->unit_of_measure,
            'estimated_price' => $product->estimated_price,
            'expense_category_id' => $category->id,
            'budget_cedula_id' => $cedula->id,
            'is_inventoriable' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('products_services', [
            'id' => $product->id,
            'expense_category_id' => $category->id,
            'budget_cedula_id' => $cedula->id,
            'is_inventoriable' => true,
        ]);
    }

    private function createContext(): array
    {
        $user = User::factory()->create();
        $user->assignRole('catalog_admin');

        $company = Company::factory()->create();
        $category = Category::factory()->create();
        $costCenter = CostCenter::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
        ]);

        return [$user, $company, $costCenter];
    }
}
