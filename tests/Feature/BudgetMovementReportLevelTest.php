<?php

namespace Tests\Feature;

use App\Models\BudgetMovement;
use App\Models\BudgetMovementDecision;
use App\Models\BudgetMovementDetail;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BudgetMovementReportLevelTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = User::factory()->create(['is_active' => true]);
        Permission::findOrCreate('reportes.budget_movements.ver', 'web');
        $this->actor->givePermissionTo('reportes.budget_movements.ver');
        $this->actor->assignRole(\Spatie\Permission\Models\Role::findOrCreate('superadmin', 'web'));
        $this->category = ExpenseCategory::factory()->create();
    }

    public function test_transfer_between_cost_centers_of_same_company_approved_by_substitute_is_a_violation(): void
    {
        $company = Company::factory()->create();
        $movement = $this->approvedTransfer(
            CostCenter::factory()->create(['company_id' => $company->id]),
            CostCenter::factory()->create(['company_id' => $company->id]),
            BudgetMovement::LEVEL_SUBSTITUTE,
        );

        $this->data(['only_level_violations' => 1])
            ->assertJsonPath('movements.pagination.total', 1)
            ->assertJsonPath('movements.rows.0.id', $movement->id)
            ->assertJsonPath('movements.rows.0.cross_cost_center', true)
            ->assertJsonPath('movements.rows.0.cross_company', false)
            ->assertJsonPath('movements.rows.0.authorization_level_required', 'DIRECCION')
            ->assertJsonPath('movements.rows.0.authorization_level_applied', 'SUPLENTE')
            ->assertJsonPath('movements.rows.0.authorization_violation', true);
    }

    public function test_transfer_between_cost_centers_approved_by_direction_is_not_a_violation(): void
    {
        $company = Company::factory()->create();
        $this->approvedTransfer(
            CostCenter::factory()->create(['company_id' => $company->id]),
            CostCenter::factory()->create(['company_id' => $company->id]),
            BudgetMovement::LEVEL_DIRECTION,
        );

        $this->data()->assertJsonPath('movements.rows.0.authorization_level_applied', 'DIRECCION')
            ->assertJsonPath('movements.rows.0.authorization_violation', false);
        $this->data(['only_level_violations' => 1])->assertJsonPath('movements.pagination.total', 0);
    }

    public function test_approved_cross_company_transfer_without_recorded_level_is_a_violation(): void
    {
        $this->approvedTransfer(
            CostCenter::factory()->create(['company_id' => Company::factory()->create()->id]),
            CostCenter::factory()->create(['company_id' => Company::factory()->create()->id]),
            null,
        );

        $this->data(['only_level_violations' => 1])
            ->assertJsonPath('movements.pagination.total', 1)
            ->assertJsonPath('movements.rows.0.cross_company', true)
            ->assertJsonPath('movements.rows.0.authorization_level_applied', null)
            ->assertJsonPath('movements.rows.0.authorization_violation', true);
    }

    public function test_transfer_within_the_same_cost_center_requires_no_level(): void
    {
        $center = CostCenter::factory()->create(['company_id' => Company::factory()->create()->id]);
        $this->approvedTransfer($center, $center, BudgetMovement::LEVEL_SUBSTITUTE);

        $this->data()->assertJsonPath('movements.rows.0.authorization_level_required', null)
            ->assertJsonPath('movements.rows.0.authorization_violation', false);
        $this->data(['only_level_violations' => 1])->assertJsonPath('movements.pagination.total', 0);
    }

    public function test_pending_cross_center_transfer_is_not_flagged_until_approved(): void
    {
        $company = Company::factory()->create();
        $movement = $this->approvedTransfer(
            CostCenter::factory()->create(['company_id' => $company->id]),
            CostCenter::factory()->create(['company_id' => $company->id]),
            null,
        );
        BudgetMovement::query()->whereKey($movement->id)->toBase()->update(['status' => BudgetMovement::STATUS_PENDING_EXECUTIVE, 'approved_at' => null]);

        $this->data(['status' => 'TODOS'])->assertJsonPath('movements.rows.0.authorization_level_required', 'DIRECCION')
            ->assertJsonPath('movements.rows.0.authorization_violation', false);
        $this->data(['status' => 'TODOS', 'only_level_violations' => 1])->assertJsonPath('movements.pagination.total', 0);
    }

    public function test_rows_are_ordered_by_date_then_folio_ascending(): void
    {
        $center = CostCenter::factory()->create(['company_id' => Company::factory()->create()->id]);
        $late = $this->approvedTransfer($center, $center, BudgetMovement::LEVEL_DIRECTION, '2026-05-10');
        $early = $this->approvedTransfer($center, $center, BudgetMovement::LEVEL_DIRECTION, '2026-02-01');

        $this->data()->assertJsonPath('movements.rows.0.id', $early->id)
            ->assertJsonPath('movements.rows.1.id', $late->id);
    }

    private function data(array $params = [])
    {
        return $this->actingAs($this->actor)
            ->getJson(route('budget-movement-reports.data', ['fiscal_year' => 2026] + $params))
            ->assertOk();
    }

    private function approvedTransfer(CostCenter $origin, CostCenter $destination, ?string $level, string $date = '2026-06-02'): BudgetMovement
    {
        $movement = BudgetMovement::create([
            'movement_type' => BudgetMovement::TYPE_TRANSFER, 'fiscal_year' => 2026,
            'movement_date' => $date, 'total_amount' => 50,
            'justification' => 'Traspaso de prueba para validar el nivel de autorización.',
            'status' => BudgetMovement::STATUS_APPROVED, 'created_by' => $this->actor->id,
            'approved_by' => $this->actor->id, 'approved_at' => now(), 'approval_level' => $level,
        ]);
        foreach ([[BudgetMovementDetail::TYPE_ORIGIN, $origin, -50, 6], [BudgetMovementDetail::TYPE_DESTINATION, $destination, 50, 7]] as [$type, $center, $amount, $month]) {
            BudgetMovementDetail::create([
                'budget_movement_id' => $movement->id, 'detail_type' => $type, 'cost_center_id' => $center->id,
                'month' => $month, 'expense_category_id' => $this->category->id, 'amount' => $amount,
            ]);
        }
        BudgetMovementDecision::create([
            'budget_movement_id' => $movement->id, 'stage' => BudgetMovementDecision::STAGE_EXECUTIVE,
            'action' => BudgetMovementDecision::ACTION_APPROVED, 'actor_user_id' => $this->actor->id,
        ]);

        return $movement;
    }
}
