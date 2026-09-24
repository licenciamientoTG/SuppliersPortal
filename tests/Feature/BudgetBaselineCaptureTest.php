<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckLockScreen;
use App\Models\AnnualBudget;
use App\Models\BudgetBaseline;
use App\Models\BudgetCedula;
use App\Models\BudgetMonthlyDistribution;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BudgetBaselineCaptureTest extends TestCase
{
    use RefreshDatabase;

    public function test_approving_an_annual_budget_captures_its_baseline(): void
    {
        $this->withoutMiddleware(CheckLockScreen::class);
        $admin = User::factory()->create()->assignRole(Role::findOrCreate('superadmin', 'web'));
        $category = ExpenseCategory::factory()->create();
        $cedula = BudgetCedula::factory()->create(['expense_category_id' => $category->id]);
        $budget = AnnualBudget::create(['cost_center_id' => CostCenter::factory()->create()->id, 'fiscal_year' => 2026, 'total_annual_amount' => 1200, 'status' => 'PLANIFICACION', 'created_by' => $admin->id]);
        BudgetMonthlyDistribution::create(['annual_budget_id' => $budget->id, 'budget_cedula_id' => $cedula->id, 'expense_category_id' => $category->id, 'month' => 3, 'assigned_amount' => 1200, 'created_by' => $admin->id]);

        $this->actingAs($admin)->post(route('annual_budgets.approve.store', $budget), ['notes' => 'Aprobado en prueba'])->assertRedirect();

        $this->assertSame('APROBADO', $budget->fresh()->status);
        $this->assertDatabaseHas('budget_baselines', ['annual_budget_id' => $budget->id, 'month' => 3, 'original_amount' => 1200, 'source' => BudgetBaseline::SOURCE_APPROVAL, 'captured_by' => $admin->id]);
    }

    public function test_reconstruct_command_fills_missing_baselines(): void
    {
        $user = User::factory()->create();
        $category = ExpenseCategory::factory()->create();
        $budget = AnnualBudget::create(['cost_center_id' => CostCenter::factory()->create()->id, 'fiscal_year' => 2026, 'total_annual_amount' => 700, 'status' => 'APROBADO', 'created_by' => $user->id]);
        BudgetMonthlyDistribution::create(['annual_budget_id' => $budget->id, 'budget_cedula_id' => BudgetCedula::factory()->create(['expense_category_id' => $category->id])->id, 'expense_category_id' => $category->id, 'month' => 1, 'assigned_amount' => 700, 'created_by' => $user->id]);

        $this->artisan('budget:reconstruct-baselines')->expectsOutputToContain('1')->assertSuccessful();

        $this->assertDatabaseHas('budget_baselines', ['annual_budget_id' => $budget->id, 'source' => BudgetBaseline::SOURCE_RECONSTRUCTED]);
    }
}
