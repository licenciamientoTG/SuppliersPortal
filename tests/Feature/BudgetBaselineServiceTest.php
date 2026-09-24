<?php

namespace Tests\Feature;

use App\Models\AnnualBudget;
use App\Models\BudgetBaseline;
use App\Models\BudgetCedula;
use App\Models\BudgetMonthlyDistribution;
use App\Models\BudgetMovement;
use App\Models\BudgetMovementDetail;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\BudgetBaselineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetBaselineServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private CostCenter $costCenter;

    private ExpenseCategory $category;

    private BudgetCedula $cedula;

    private AnnualBudget $budget;

    private BudgetBaselineService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->costCenter = CostCenter::factory()->create();
        $this->category = ExpenseCategory::factory()->create();
        $this->cedula = BudgetCedula::factory()->create(['expense_category_id' => $this->category->id]);
        $this->budget = AnnualBudget::create(['cost_center_id' => $this->costCenter->id, 'fiscal_year' => 2026, 'total_annual_amount' => 3000, 'status' => 'APROBADO', 'created_by' => $this->user->id]);
        foreach ([1 => 1000, 2 => 2000] as $month => $amount) {
            BudgetMonthlyDistribution::create(['annual_budget_id' => $this->budget->id, 'budget_cedula_id' => $this->cedula->id, 'expense_category_id' => $this->category->id, 'month' => $month, 'assigned_amount' => $amount, 'created_by' => $this->user->id]);
        }
        $this->service = app(BudgetBaselineService::class);
    }

    public function test_capture_stores_original_amount_per_line_once(): void
    {
        $this->assertSame(2, $this->service->capture($this->budget, BudgetBaseline::SOURCE_APPROVAL, $this->user->id));
        $this->assertSame(0, $this->service->capture($this->budget, BudgetBaseline::SOURCE_APPROVAL, $this->user->id));

        $this->assertDatabaseHas('budget_baselines', ['annual_budget_id' => $this->budget->id, 'month' => 2, 'budget_cedula_id' => $this->cedula->id, 'original_amount' => 2000, 'source' => 'APPROVAL']);
        $this->assertSame(2, BudgetBaseline::count());
    }

    public function test_capture_with_overwrite_replaces_the_previous_snapshot(): void
    {
        $this->service->capture($this->budget, BudgetBaseline::SOURCE_APPROVAL);
        BudgetMonthlyDistribution::where('month', 1)->update(['assigned_amount' => 1500]);

        $this->service->capture($this->budget, BudgetBaseline::SOURCE_IMPORT, null, true);

        $this->assertSame(2, BudgetBaseline::count());
        $this->assertDatabaseHas('budget_baselines', ['month' => 1, 'original_amount' => 1500, 'source' => 'IMPORT']);
    }

    public function test_reconstruct_missing_subtracts_approved_movements(): void
    {
        $movement = BudgetMovement::create(['movement_type' => 'AMPLIACION', 'fiscal_year' => 2026, 'movement_date' => '2026-02-10', 'total_amount' => 500, 'justification' => 'Ampliación de prueba para reconstrucción.', 'status' => 'APROBADO', 'created_by' => $this->user->id]);
        BudgetMovementDetail::create(['budget_movement_id' => $movement->id, 'detail_type' => 'AJUSTE', 'cost_center_id' => $this->costCenter->id, 'month' => 2, 'expense_category_id' => $this->category->id, 'budget_cedula_id' => $this->cedula->id, 'amount' => 500]);
        $pending = BudgetMovement::create(['movement_type' => 'AMPLIACION', 'fiscal_year' => 2026, 'movement_date' => '2026-02-11', 'total_amount' => 99, 'justification' => 'Pendiente que no debe contar.', 'status' => 'PENDIENTE_DIRECCION', 'created_by' => $this->user->id]);
        BudgetMovementDetail::create(['budget_movement_id' => $pending->id, 'detail_type' => 'AJUSTE', 'cost_center_id' => $this->costCenter->id, 'month' => 2, 'expense_category_id' => $this->category->id, 'budget_cedula_id' => $this->cedula->id, 'amount' => 99]);

        $this->assertSame(1, $this->service->reconstructMissing($this->user->id));
        $this->assertSame(0, $this->service->reconstructMissing($this->user->id));

        $this->assertDatabaseHas('budget_baselines', ['month' => 2, 'original_amount' => 1500, 'source' => 'RECONSTRUCTED']);
        $this->assertDatabaseHas('budget_baselines', ['month' => 1, 'original_amount' => 1000, 'source' => 'RECONSTRUCTED']);
    }

    public function test_approved_movement_effects_split_increases_and_decreases(): void
    {
        $movement = BudgetMovement::create(['movement_type' => 'TRANSFERENCIA', 'fiscal_year' => 2026, 'movement_date' => '2026-01-10', 'total_amount' => 300, 'justification' => 'Traspaso de prueba entre meses.', 'status' => 'APROBADO', 'created_by' => $this->user->id]);
        BudgetMovementDetail::create(['budget_movement_id' => $movement->id, 'detail_type' => 'ORIGEN', 'cost_center_id' => $this->costCenter->id, 'month' => 1, 'expense_category_id' => $this->category->id, 'budget_cedula_id' => $this->cedula->id, 'amount' => -300]);
        BudgetMovementDetail::create(['budget_movement_id' => $movement->id, 'detail_type' => 'DESTINO', 'cost_center_id' => $this->costCenter->id, 'month' => 2, 'expense_category_id' => $this->category->id, 'budget_cedula_id' => $this->cedula->id, 'amount' => 300]);

        $effects = $this->service->approvedMovementEffects($this->costCenter->id, 2026);

        $this->assertEqualsWithDelta(-300.0, $effects[BudgetBaselineService::lineKey(1, $this->category->id, $this->cedula->id)]['decreases'], 0.001);
        $this->assertEqualsWithDelta(300.0, $effects[BudgetBaselineService::lineKey(2, $this->category->id, $this->cedula->id)]['increases'], 0.001);
    }
}
