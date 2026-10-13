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
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** RP-02: accounting y general_director ven todo acotado a sus empresas asignadas, igual que RP-01. */
class BudgetMovementReportScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('reportes.budget_movements.ver', 'web');
    }

    public function test_accounting_with_assigned_company_only_sees_movements_of_that_company(): void
    {
        $user = $this->viewer('accounting');
        $mine = Company::factory()->create();
        $user->companies()->attach($mine->id);
        $this->approvedAdjustment($this->center($mine), 100);
        $this->approvedAdjustment($this->center(Company::factory()->create()), 200);

        $this->data($user)->assertJsonPath('movements.pagination.total', 1)
            ->assertJsonPath('movements.rows.0.total_amount', 100);
    }

    public function test_accounting_without_assigned_company_sees_every_movement(): void
    {
        $user = $this->viewer('accounting');
        $this->approvedAdjustment($this->center(Company::factory()->create()), 100);
        $this->approvedAdjustment($this->center(Company::factory()->create()), 200);

        $this->data($user)->assertJsonPath('movements.pagination.total', 2);
    }

    public function test_general_director_with_assigned_company_only_sees_cost_centers_of_that_company(): void
    {
        $user = $this->viewer('general_director');
        $mine = Company::factory()->create();
        $user->companies()->attach($mine->id);
        $myCenter = $this->center($mine);
        $this->approvedAdjustment($myCenter, 100);
        $this->approvedAdjustment($this->center(Company::factory()->create()), 200);

        $this->actingAs($user)->get(route('budget-movement-reports.index'))
            ->assertOk()
            ->assertViewHas('centers', fn ($centers) => $centers->pluck('id')->all() === [$myCenter->id]);
    }

    private function viewer(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(Role::findOrCreate($role, 'web'));
        $user->givePermissionTo('reportes.budget_movements.ver');

        return $user;
    }

    private function center(Company $company): CostCenter
    {
        return CostCenter::factory()->create(['company_id' => $company->id]);
    }

    private function approvedAdjustment(CostCenter $center, float $amount): BudgetMovement
    {
        $creator = User::factory()->create(['is_active' => true]);
        $movement = BudgetMovement::create([
            'movement_type' => BudgetMovement::TYPE_INCREASE, 'fiscal_year' => 2026,
            'movement_date' => '2026-04-05', 'total_amount' => $amount,
            'justification' => 'Ampliación de prueba para validar el alcance por empresa.',
            'status' => BudgetMovement::STATUS_APPROVED, 'created_by' => $creator->id,
            'approved_by' => $creator->id, 'approved_at' => now(),
        ]);
        BudgetMovementDetail::create([
            'budget_movement_id' => $movement->id, 'detail_type' => BudgetMovementDetail::TYPE_ADJUSTMENT,
            'cost_center_id' => $center->id, 'month' => 4, 'expense_category_id' => ExpenseCategory::factory()->create()->id,
            'amount' => $amount,
        ]);
        BudgetMovementDecision::create([
            'budget_movement_id' => $movement->id, 'stage' => BudgetMovementDecision::STAGE_EXECUTIVE,
            'action' => BudgetMovementDecision::ACTION_APPROVED, 'actor_user_id' => $creator->id,
        ]);

        return $movement;
    }

    private function data(User $user, array $params = [])
    {
        return $this->actingAs($user)
            ->getJson(route('budget-movement-reports.data', ['fiscal_year' => 2026] + $params))
            ->assertOk();
    }
}
