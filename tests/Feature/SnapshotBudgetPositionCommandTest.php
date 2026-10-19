<?php

namespace Tests\Feature;

use App\Models\AnnualBudget;
use App\Models\BudgetCedula;
use App\Models\BudgetMonthlyDistribution;
use App\Models\BudgetPositionSnapshot;
use App\Models\Category;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** RP-01 (punto #6): comando `budget:snapshot-position`, programado a las 23:55. */
class SnapshotBudgetPositionCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_captures_todays_position_and_reports_how_many_lines(): void
    {
        $this->line();

        $this->artisan('budget:snapshot-position')
            ->expectsOutputToContain('Renglones guardados: 1')
            ->assertSuccessful();

        $this->assertSame(1, BudgetPositionSnapshot::whereDate('snapshot_date', now()->toDateString())->count());
    }

    public function test_accepts_a_specific_date_to_backfill(): void
    {
        $this->line();

        $this->artisan('budget:snapshot-position', ['--date' => '2026-10-01'])->assertSuccessful();

        $this->assertSame(1, BudgetPositionSnapshot::whereDate('snapshot_date', '2026-10-01')->count());
    }

    private function line(float $assigned = 10000, int $month = 3): BudgetMonthlyDistribution
    {
        $center = CostCenter::factory()->create([
            'company_id' => Company::factory(),
            'category_id' => Category::factory(),
            'responsible_user_id' => $this->user->id,
            'budget_type' => 'ANNUAL',
            'global_amount' => 0,
            'status' => 'ACTIVO',
            'purchase_type' => 'Gasto Operativo',
        ]);
        $category = ExpenseCategory::factory()->create();
        $budget = AnnualBudget::create([
            'cost_center_id' => $center->id,
            'fiscal_year' => (int) now()->year,
            'total_annual_amount' => 0,
            'status' => 'APROBADO',
            'created_by' => $this->user->id,
        ]);

        return BudgetMonthlyDistribution::create([
            'annual_budget_id' => $budget->id,
            'budget_cedula_id' => BudgetCedula::factory()->create(['expense_category_id' => $category->id])->id,
            'expense_category_id' => $category->id,
            'month' => $month,
            'assigned_amount' => $assigned,
            'consumed_amount' => 0,
            'committed_amount' => 0,
            'created_by' => $this->user->id,
        ])->load('annualBudget.costCenter', 'expenseCategory');
    }
}
