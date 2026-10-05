<?php

namespace Tests\Feature;

use App\Models\AnnualBudget;
use App\Models\BudgetCedula;
use App\Models\BudgetMonthlyDistribution;
use App\Models\BudgetMovement;
use App\Models\BudgetMovementDetail;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\BudgetBaselineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BudgetBaselineFromSourceTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private CostCenter $center;

    private ExpenseCategory $category;

    private BudgetCedula $cedula;

    private AnnualBudget $budget;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = User::factory()->create(['is_active' => true]);
        Permission::findOrCreate('reportes.budget_movements.ver', 'web');
        $this->actor->givePermissionTo('reportes.budget_movements.ver');
        $this->actor->assignRole(Role::findOrCreate('superadmin', 'web'));
        $this->center = CostCenter::factory()->create(['company_id' => Company::factory()->create()->id]);
        $this->category = ExpenseCategory::factory()->create();
        $this->cedula = BudgetCedula::create(['expense_category_id' => $this->category->id, 'name' => 'Papelería', 'status' => 'ACTIVO', 'created_by' => $this->actor->id]);
        $this->budget = AnnualBudget::create([
            'cost_center_id' => $this->center->id, 'fiscal_year' => 2026, 'total_annual_amount' => 1200,
            'status' => 'APROBADO', 'created_by' => $this->actor->id,
        ]);
    }

    public function test_original_comes_from_the_approved_file_even_after_movements(): void
    {
        $this->distribution(4, 1200);
        $this->approvedIncrease(4, 200);

        $result = app(BudgetBaselineService::class)->captureFromSource($this->budget, $this->sheet([4 => 1000]), $this->actor->id);

        $this->assertSame(1, $result['captured']);
        $this->assertDatabaseHas('budget_distribution_baselines', [
            'annual_budget_id' => $this->budget->id, 'month' => 4, 'original_amount' => 1000, 'source' => BudgetBaselineService::SOURCE_FILE,
        ]);
        $this->reconciliation()->assertJsonPath('reconciliation.0.original_amount', 1000)
            ->assertJsonPath('reconciliation.0.difference', 0)
            ->assertJsonPath('reconciliation.0.reconciliation_status', 'CONCILIA');
    }

    public function test_direct_edits_after_approval_show_up_as_a_difference(): void
    {
        $this->distribution(4, 1500);
        $this->approvedIncrease(4, 200);

        app(BudgetBaselineService::class)->captureFromSource($this->budget, $this->sheet([4 => 1000]), $this->actor->id);

        $this->reconciliation()->assertJsonPath('reconciliation.0.difference', -300)
            ->assertJsonPath('reconciliation.0.reconciliation_status', 'DIFERENCIA');
    }

    public function test_line_absent_from_the_file_starts_at_zero(): void
    {
        $this->distribution(4, 1000);
        $this->distribution(5, 300);
        $this->approvedIncrease(5, 300);

        $result = app(BudgetBaselineService::class)->captureFromSource($this->budget, $this->sheet([4 => 1000]), $this->actor->id);

        $this->assertSame(2, $result['captured']);
        $this->assertDatabaseHas('budget_distribution_baselines', ['month' => 5, 'original_amount' => 0]);
    }

    public function test_file_lines_without_a_current_distribution_are_reported_not_stored(): void
    {
        $this->distribution(4, 1000);

        $result = app(BudgetBaselineService::class)->captureFromSource($this->budget, $this->sheet([4 => 1000, 9 => 250]), $this->actor->id);

        $this->assertSame(1, $result['captured']);
        $this->assertCount(1, $result['unmatched']);
        $this->assertSame(9, $result['unmatched'][0]['month']);
        $this->assertEquals(250, $result['unmatched'][0]['amount']);
        $this->assertDatabaseCount('budget_distribution_baselines', 1);
    }

    public function test_existing_baseline_is_never_replaced(): void
    {
        $this->distribution(4, 1000);
        app(BudgetBaselineService::class)->capture($this->budget, $this->actor->id);

        $result = app(BudgetBaselineService::class)->captureFromSource($this->budget, $this->sheet([4 => 700]), $this->actor->id);

        $this->assertSame(0, $result['captured']);
        $this->assertTrue($result['skipped']);
        $this->assertDatabaseHas('budget_distribution_baselines', ['month' => 4, 'original_amount' => 1000]);
    }

    public function test_preview_compares_file_against_current_without_writing(): void
    {
        $this->distribution(4, 1500);
        $this->approvedIncrease(4, 200);

        $preview = app(BudgetBaselineService::class)->previewFromSource($this->budget, $this->sheet([4 => 1000]));

        $this->assertDatabaseCount('budget_distribution_baselines', 0);
        $this->assertEquals(['original' => 1000.0, 'movements' => 200.0, 'current' => 1500.0, 'difference' => -300.0], $preview['totals']);
        $this->assertSame(1, $preview['lines_with_difference']);
    }

    private function sheet(array $months): array
    {
        return [
            'cost_center_id' => $this->center->id,
            'rows' => [[
                'expense_category_id' => $this->category->id, 'budget_cedula_id' => $this->cedula->id,
                'months' => $months,
            ]],
        ];
    }

    private function distribution(int $month, float $amount): BudgetMonthlyDistribution
    {
        return BudgetMonthlyDistribution::create([
            'annual_budget_id' => $this->budget->id, 'expense_category_id' => $this->category->id,
            'budget_cedula_id' => $this->cedula->id, 'month' => $month, 'assigned_amount' => $amount,
            'created_by' => $this->actor->id,
        ]);
    }

    private function approvedIncrease(int $month, float $amount): void
    {
        $movement = BudgetMovement::create([
            'movement_type' => BudgetMovement::TYPE_INCREASE, 'fiscal_year' => 2026,
            'movement_date' => '2026-04-05', 'total_amount' => $amount,
            'justification' => 'Ampliación aprobada antes de capturar la base.',
            'status' => BudgetMovement::STATUS_APPROVED, 'created_by' => $this->actor->id,
            'approved_by' => $this->actor->id, 'approved_at' => now(), 'approval_level' => BudgetMovement::LEVEL_DIRECTION,
        ]);
        BudgetMovementDetail::create([
            'budget_movement_id' => $movement->id, 'detail_type' => BudgetMovementDetail::TYPE_ADJUSTMENT,
            'cost_center_id' => $this->center->id, 'month' => $month, 'expense_category_id' => $this->category->id,
            'budget_cedula_id' => $this->cedula->id, 'amount' => $amount,
        ]);
    }

    private function reconciliation()
    {
        return $this->actingAs($this->actor)
            ->getJson(route('budget-movement-reports.data', ['fiscal_year' => 2026]))->assertOk();
    }
}
