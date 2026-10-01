<?php

namespace Tests\Feature;

use App\Models\AnnualBudget;
use App\Models\BudgetException;
use App\Models\BudgetMonthlyDistribution;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BudgetAlertsReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_risk_report_exposes_negative_available_and_marks_missing_history(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        Permission::findOrCreate('reportes.budget_alerts.ver', 'web');
        $user->givePermissionTo('reportes.budget_alerts.ver');
        $company = Company::factory()->create();
        $center = CostCenter::factory()->create(['company_id' => $company->id, 'responsible_user_id' => $user->id]);
        $category = ExpenseCategory::factory()->create();
        $budget = AnnualBudget::create(['cost_center_id' => $center->id, 'fiscal_year' => now()->year, 'total_annual_amount' => 1000, 'status' => 'APROBADO', 'created_by' => $user->id]);
        BudgetMonthlyDistribution::create(['annual_budget_id' => $budget->id, 'expense_category_id' => $category->id, 'month' => now()->month, 'assigned_amount' => 100, 'consumed_amount' => 120, 'created_by' => $user->id]);

        $this->actingAs($user)->getJson(route('budget-alert-reports.data', ['year' => now()->year]))
            ->assertOk()->assertJsonPath('total', 1)
            ->assertJsonPath('lines.0.available', -20)
            ->assertJsonPath('lines.0.history_status', 'Sin historial suficiente')
            ->assertJsonPath('lines.0.consumed_pct', 120);
    }

    public function test_exception_decision_endpoint_is_not_available_to_non_directors(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        Permission::findOrCreate('reportes.budget_alerts.excepcion.aprobar', 'web');
        $user->givePermissionTo('reportes.budget_alerts.excepcion.aprobar');
        Role::findOrCreate('general_director', 'web');
        $company = Company::factory()->create();
        $center = CostCenter::factory()->create(['company_id' => $company->id]);
        $category = ExpenseCategory::factory()->create();
        $exception = BudgetException::create(['document_type' => 'direct_purchase_order', 'document_id' => 1, 'document_line_id' => 1, 'cost_center_id' => $center->id, 'expense_category_id' => $category->id, 'application_month' => now()->format('Y-m'), 'line_amount' => 100, 'available_at_request' => 0, 'requested_excess' => 100, 'reason' => 'Motivo de prueba de excepción.', 'status' => 'PENDING', 'requested_by' => $user->id, 'requested_at' => now()]);

        $this->actingAs($user)->postJson(route('budget-alert-reports.exceptions.decide', ['exception' => $exception->id]), ['decision' => 'APPROVED'])
            ->assertForbidden();
    }
}
