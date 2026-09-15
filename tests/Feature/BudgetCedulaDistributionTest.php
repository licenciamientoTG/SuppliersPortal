<?php

namespace Tests\Feature;

use App\Models\AnnualBudget;
use App\Models\BudgetCedula;
use App\Models\BudgetMonthlyDistribution;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetCedulaDistributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_budget_movement_availability_aggregates_multiple_cedulas_under_same_category(): void
    {
        $context = $this->createBudgetContext();

        BudgetMonthlyDistribution::create([
            'annual_budget_id' => $context['budget']->id,
            'budget_cedula_id' => $context['cedulas'][0]->id,
            'expense_category_id' => $context['category']->id,
            'month' => 1,
            'assigned_amount' => 1000,
            'consumed_amount' => 100,
            'committed_amount' => 200,
            'created_by' => $context['user']->id,
        ]);

        BudgetMonthlyDistribution::create([
            'annual_budget_id' => $context['budget']->id,
            'budget_cedula_id' => $context['cedulas'][1]->id,
            'expense_category_id' => $context['category']->id,
            'month' => 1,
            'assigned_amount' => 500,
            'consumed_amount' => 50,
            'committed_amount' => 25,
            'created_by' => $context['user']->id,
        ]);

        $response = $this->actingAs($context['user'])
            ->get(route('budget_movements.check_budget', [
                'cost_center_id' => $context['costCenter']->id,
                'fiscal_year' => 2026,
                'expense_category_id' => $context['category']->id,
                'month' => 1,
            ]));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'has_budget' => true,
                'assigned_amount' => 1500.0,
                'consumed_amount' => 150.0,
                'committed_amount' => 225.0,
                'available_amount' => 1125.0,
                'status' => 'NORMAL',
            ]);
    }

    private function createBudgetContext(): array
    {
        $user = User::factory()->create();

        $company = Company::create([
            'code' => 'DGA',
            'name' => 'Diaz Gas',
            'legal_name' => 'Diaz Gas SA',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'OPERACIONES',
            'description' => 'Operaciones',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $expenseCategory = ExpenseCategory::create([
            'code' => 'E',
            'name' => 'Gastos de Operación',
            'description' => 'Gastos operativos',
            'status' => 'ACTIVO',
            'created_by' => $user->id,
        ]);

        $costCenter = CostCenter::create([
            'code' => '00177',
            'name' => 'MANTENIMIENTO',
            'purchase_type' => 'Gasto Operativo',
            'category_id' => $category->id,
            'company_id' => $company->id,
            'responsible_user_id' => $user->id,
            'budget_type' => 'ANNUAL',
            'status' => 'ACTIVO',
            'created_by' => $user->id,
        ]);

        $budget = AnnualBudget::create([
            'cost_center_id' => $costCenter->id,
            'fiscal_year' => 2026,
            'total_annual_amount' => 18000,
            'status' => 'PLANIFICACION',
            'created_by' => $user->id,
        ]);

        $cedulas = collect([
            BudgetCedula::create([
                'expense_category_id' => $expenseCategory->id,
                'name' => 'Propaganda y publicidad',
                'status' => 'ACTIVO',
                'created_by' => $user->id,
            ]),
            BudgetCedula::create([
                'expense_category_id' => $expenseCategory->id,
                'name' => 'Eventos',
                'status' => 'ACTIVO',
                'created_by' => $user->id,
            ]),
        ]);

        return [
            'user' => $user,
            'company' => $company,
            'category' => $expenseCategory,
            'costCenter' => $costCenter,
            'budget' => $budget,
            'cedulas' => $cedulas,
        ];
    }
}
