<?php

namespace Tests\Feature;

use App\Models\BudgetCedula;
use App\Models\Category;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductServiceExpenseClassificationValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_store_rejects_cedula_that_does_not_belong_to_any_selected_category(): void
    {
        [$user, $company, $costCenter] = $this->createContext();

        $categoryA = ExpenseCategory::factory()->create();
        $categoryB = ExpenseCategory::factory()->create();
        $cedulaOfB = BudgetCedula::factory()->create(['expense_category_id' => $categoryB->id]);

        $response = $this->actingAs($user)->post(route('products-services.store'), $this->basePayload($company, $costCenter, [
            'expense_category_ids' => [$categoryA->id],
            'budget_cedula_ids' => [$cedulaOfB->id],
        ]));

        $response->assertSessionHasErrors('budget_cedula_ids');
    }

    public function test_store_accepts_cedulas_that_belong_to_selected_categories(): void
    {
        [$user, $company, $costCenter] = $this->createContext();

        $categoryA = ExpenseCategory::factory()->create();
        $categoryB = ExpenseCategory::factory()->create();
        $cedulaOfA = BudgetCedula::factory()->create(['expense_category_id' => $categoryA->id]);
        $cedulaOfB = BudgetCedula::factory()->create(['expense_category_id' => $categoryB->id]);

        $response = $this->actingAs($user)->post(route('products-services.store'), $this->basePayload($company, $costCenter, [
            'expense_category_ids' => [$categoryA->id, $categoryB->id],
            'budget_cedula_ids' => [$cedulaOfA->id, $cedulaOfB->id],
        ]));

        $response->assertSessionHasNoErrors();
    }

    public function test_store_accepts_no_classification_at_all(): void
    {
        [$user, $company, $costCenter] = $this->createContext();

        $response = $this->actingAs($user)->post(route('products-services.store'), $this->basePayload($company, $costCenter, []));

        $response->assertSessionHasNoErrors();
    }

    private function basePayload(Company $company, CostCenter $costCenter, array $overrides): array
    {
        return array_merge([
            'product_type' => 'PRODUCTO',
            'company_id' => $company->id,
            'cost_center_id' => $costCenter->id,
            'unit_of_measure' => 'PIEZA',
            'estimated_price' => 100,
        ], $overrides);
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
