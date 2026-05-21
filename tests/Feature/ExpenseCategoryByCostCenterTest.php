<?php

namespace Tests\Feature;

use App\Models\AnnualBudget;
use App\Models\BudgetCedula;
use App\Models\BudgetMonthlyDistribution;
use App\Models\Category;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseCategoryByCostCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Carbon::setTestNow('2026-05-21 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_staff_can_load_expense_categories_for_an_assigned_cost_center(): void
    {
        $context = $this->createAnnualBudgetContext();

        $response = $this->actingAs($context['user'])
            ->getJson(route('expense-categories.by-cost-center', [
                'cost_center_id' => $context['costCenter']->id,
            ]));

        $response->assertOk();
        $response->assertJsonFragment([
            'success' => true,
            'budget_type' => 'ANNUAL',
        ]);
        $response->assertJsonFragment([
            'id' => $context['expenseCategory']->id,
            'code' => 'TEC',
            'name' => 'Tecnologia',
        ]);
    }

    public function test_staff_cannot_load_expense_categories_for_an_unassigned_cost_center(): void
    {
        $context = $this->createAnnualBudgetContext();

        $otherUser = User::factory()->create();
        $otherUser->assignRole('staff');

        $response = $this->actingAs($otherUser)
            ->getJson(route('expense-categories.by-cost-center', [
                'cost_center_id' => $context['costCenter']->id,
            ]));

        $response->assertForbidden();
        $response->assertJsonFragment([
            'success' => false,
            'message' => 'No tienes permiso para consultar este centro de costo.',
        ]);
    }

    private function createAnnualBudgetContext(): array
    {
        $user = User::factory()->create();
        $user->assignRole('staff');

        $company = Company::create([
            'code' => 'DGA',
            'name' => 'Diaz Gas',
            'legal_name' => 'Diaz Gas SA',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'STAFF',
            'description' => 'Staff',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $costCenter = CostCenter::create([
            'code' => '00184',
            'name' => 'APLICACIONES Y SOFTWARE',
            'purchase_type' => 'Gasto Staff',
            'category_id' => $category->id,
            'company_id' => $company->id,
            'responsible_user_id' => $user->id,
            'budget_type' => 'ANNUAL',
            'status' => 'ACTIVO',
            'created_by' => $user->id,
        ]);

        $expenseCategory = ExpenseCategory::create([
            'code' => 'TEC',
            'name' => 'Tecnologia',
            'description' => 'Software y hardware',
            'status' => 'ACTIVO',
            'created_by' => $user->id,
        ]);

        $budget = AnnualBudget::create([
            'cost_center_id' => $costCenter->id,
            'fiscal_year' => 2026,
            'total_annual_amount' => 500000,
            'status' => 'APROBADO',
            'created_by' => $user->id,
        ]);

        $cedula = BudgetCedula::create([
            'expense_category_id' => $expenseCategory->id,
            'name' => 'Tecnologia Staff',
            'status' => 'ACTIVO',
            'created_by' => $user->id,
        ]);

        BudgetMonthlyDistribution::create([
            'annual_budget_id' => $budget->id,
            'budget_cedula_id' => $cedula->id,
            'expense_category_id' => $expenseCategory->id,
            'month' => 5,
            'assigned_amount' => 150000,
            'consumed_amount' => 0,
            'committed_amount' => 0,
            'created_by' => $user->id,
        ]);

        $user->companies()->attach($company->id);
        $user->costCenters()->attach($costCenter->id, [
            'is_active' => true,
            'is_default' => true,
        ]);

        return [
            'user' => $user,
            'costCenter' => $costCenter,
            'expenseCategory' => $expenseCategory,
        ];
    }
}
