<?php

namespace Tests\Feature;

use App\Models\AnnualBudget;
use App\Models\BudgetMonthlyDistribution;
use App\Models\BudgetMovement;
use App\Models\BudgetMovementDecision;
use App\Models\BudgetMovementDetail;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\BudgetBaselineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BudgetMovementReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_and_reconciliation_use_approved_movement_and_captured_opening_baseline(): void
    {
        $actor = User::factory()->create(['is_active' => true]);
        Permission::findOrCreate('reportes.budget_movements.ver', 'web');
        Permission::findOrCreate('reportes.budget_movements.exportar', 'web');
        $actor->givePermissionTo(['reportes.budget_movements.ver', 'reportes.budget_movements.exportar']);
        $company = Company::factory()->create();
        $center = CostCenter::factory()->create(['company_id' => $company->id, 'responsible_user_id' => $actor->id]);
        $category = ExpenseCategory::factory()->create();
        $budget = AnnualBudget::create([
            'cost_center_id' => $center->id, 'fiscal_year' => 2026, 'total_annual_amount' => 1200,
            'status' => 'APROBADO', 'created_by' => $actor->id,
        ]);
        $distribution = BudgetMonthlyDistribution::create([
            'annual_budget_id' => $budget->id, 'expense_category_id' => $category->id,
            'month' => 4, 'assigned_amount' => 1000, 'created_by' => $actor->id,
        ]);
        app(BudgetBaselineService::class)->capture($budget, $actor->id);

        $movement = BudgetMovement::create([
            'movement_type' => BudgetMovement::TYPE_INCREASE, 'fiscal_year' => 2026,
            'movement_date' => '2026-04-05', 'total_amount' => 200,
            'justification' => 'Ampliación autorizada para el renglón de prueba.',
            'status' => BudgetMovement::STATUS_APPROVED, 'created_by' => $actor->id,
            'approved_by' => $actor->id, 'approved_at' => now(),
        ]);
        BudgetMovementDetail::create([
            'budget_movement_id' => $movement->id, 'detail_type' => BudgetMovementDetail::TYPE_ADJUSTMENT,
            'cost_center_id' => $center->id, 'month' => 4, 'expense_category_id' => $category->id,
            'amount' => 200,
        ]);
        BudgetMovementDecision::create([
            'budget_movement_id' => $movement->id, 'stage' => 'DIRECCION', 'action' => 'APROBADO',
            'actor_user_id' => $actor->id,
        ]);
        $distribution->update(['assigned_amount' => 1200, 'updated_by' => $actor->id]);

        $this->actingAs($actor)->get(route('budget-movement-reports.index'))->assertOk();
        $this->actingAs($actor)->getJson(route('budget-movement-reports.data', ['fiscal_year' => 2026, 'company_ids' => [$company->id]]))
            ->assertOk()
            ->assertJsonPath('movements.pagination.total', 1)
            ->assertJsonPath('movements.rows.0.folio', 'MP-'.$movement->id)
            ->assertJsonPath('reconciliation.0.original_amount', 1000)
            ->assertJsonPath('reconciliation.0.movement_total', 200)
            ->assertJsonPath('reconciliation.0.current_amount', 1200)
            ->assertJsonPath('reconciliation.0.difference', 0)
            ->assertJsonPath('reconciliation.0.reconciliation_status', 'CONCILIA');
        $this->actingAs($actor)->get(route('budget-movement-reports.export', ['format' => 'xlsx', 'fiscal_year' => 2026]))
            ->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_users_without_report_permission_receive_forbidden(): void
    {
        $actor = User::factory()->create(['is_active' => true]);

        $this->actingAs($actor)->get(route('budget-movement-reports.index'))->assertForbidden();
    }

    public function test_cross_company_transfer_without_direction_decision_is_flagged(): void
    {
        $actor = User::factory()->create(['is_active' => true]);
        Permission::findOrCreate('reportes.budget_movements.ver', 'web');
        $actor->givePermissionTo('reportes.budget_movements.ver');
        $originCompany = Company::factory()->create();
        $destinationCompany = Company::factory()->create();
        $origin = CostCenter::factory()->create(['company_id' => $originCompany->id]);
        $destination = CostCenter::factory()->create(['company_id' => $destinationCompany->id]);
        $category = ExpenseCategory::factory()->create();
        $movement = BudgetMovement::create([
            'movement_type' => BudgetMovement::TYPE_TRANSFER, 'fiscal_year' => 2026,
            'movement_date' => '2026-06-02', 'total_amount' => 50,
            'justification' => 'Transferencia entre dos empresas en espera de Dirección.',
            'status' => BudgetMovement::STATUS_PENDING_EXECUTIVE, 'created_by' => $actor->id,
        ]);
        foreach ([['ORIGEN', $origin, -50], ['DESTINO', $destination, 50]] as [$type, $center, $amount]) {
            BudgetMovementDetail::create([
                'budget_movement_id' => $movement->id, 'detail_type' => $type,
                'cost_center_id' => $center->id, 'month' => 6,
                'expense_category_id' => $category->id, 'amount' => $amount,
            ]);
        }
        BudgetMovementDecision::create([
            'budget_movement_id' => $movement->id, 'stage' => 'ORIGEN', 'action' => 'APROBADO', 'actor_user_id' => $actor->id,
        ]);

        $this->actingAs($actor)->getJson(route('budget-movement-reports.data', [
            'fiscal_year' => 2026, 'status' => 'TODOS', 'only_level_violations' => 1,
        ]))->assertOk()
            ->assertJsonPath('movements.pagination.total', 1)
            ->assertJsonPath('movements.rows.0.cross_company', true)
            ->assertJsonPath('movements.rows.0.authorization_level_required', 'DIRECCION')
            ->assertJsonPath('movements.rows.0.authorization_violation', true);
    }

    public function test_missing_opening_baseline_is_shown_as_unreconciled_and_cannot_be_captured_after_movements(): void
    {
        $actor = User::factory()->create(['is_active' => true]);
        Permission::findOrCreate('reportes.budget_movements.ver', 'web');
        $actor->givePermissionTo('reportes.budget_movements.ver');
        $company = Company::factory()->create();
        $center = CostCenter::factory()->create(['company_id' => $company->id, 'responsible_user_id' => $actor->id]);
        $category = ExpenseCategory::factory()->create();
        $budget = AnnualBudget::create([
            'cost_center_id' => $center->id, 'fiscal_year' => 2026, 'total_annual_amount' => 1200,
            'status' => 'APROBADO', 'created_by' => $actor->id,
        ]);
        BudgetMonthlyDistribution::create([
            'annual_budget_id' => $budget->id, 'expense_category_id' => $category->id,
            'month' => 4, 'assigned_amount' => 1200, 'created_by' => $actor->id,
        ]);
        $movement = BudgetMovement::create([
            'movement_type' => BudgetMovement::TYPE_INCREASE, 'fiscal_year' => 2026,
            'movement_date' => '2026-04-05', 'total_amount' => 200,
            'justification' => 'Movimiento histórico anterior a la captura de base.',
            'status' => BudgetMovement::STATUS_APPROVED, 'created_by' => $actor->id,
            'approved_by' => $actor->id, 'approved_at' => now(),
        ]);
        BudgetMovementDetail::create([
            'budget_movement_id' => $movement->id, 'detail_type' => BudgetMovementDetail::TYPE_ADJUSTMENT,
            'cost_center_id' => $center->id, 'month' => 4, 'expense_category_id' => $category->id, 'amount' => 200,
        ]);

        $this->actingAs($actor)->getJson(route('budget-movement-reports.data', ['fiscal_year' => 2026]))
            ->assertOk()->assertJsonPath('reconciliation.0.reconciliation_status', 'SIN_BASE')
            ->assertJsonPath('reconciliation.0.original_amount', null);

        try {
            app(BudgetBaselineService::class)->capture($budget, $actor->id);
            $this->fail('A baseline must not be captured from an already adjusted balance.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('saldo actual como base original', $exception->getMessage());
        }
        $this->assertDatabaseCount('budget_distribution_baselines', 0);
    }
}
