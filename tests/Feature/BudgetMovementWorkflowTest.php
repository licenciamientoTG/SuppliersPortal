<?php

namespace Tests\Feature;

use App\Models\AnnualBudget;
use App\Models\BudgetCedula;
use App\Models\BudgetMonthlyDistribution;
use App\Models\BudgetMovement;
use App\Models\BudgetMovementApprovalSetting;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BudgetMovementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;

    private User $originOwner;

    private User $director;

    private CostCenter $origin;

    private CostCenter $destination;

    private ExpenseCategory $category;

    private BudgetCedula $cedula;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('general_director');
        $this->requester = User::factory()->create(['is_active' => true]);
        $this->originOwner = User::factory()->create(['is_active' => true]);
        $this->director = User::factory()->create(['is_active' => true]);
        $this->director->assignRole('general_director');
        BudgetMovementApprovalSetting::create(['director_user_id' => $this->director->id]);
        $this->origin = CostCenter::factory()->create(['responsible_user_id' => $this->originOwner->id]);
        $this->destination = CostCenter::factory()->create(['responsible_user_id' => $this->requester->id]);
        $this->category = ExpenseCategory::factory()->create();
        $this->cedula = BudgetCedula::factory()->create(['expense_category_id' => $this->category->id]);
    }

    public function test_destination_owner_submits_transfer_then_origin_owner_sends_it_to_executive_approval(): void
    {
        $this->actingAs($this->requester)->post(route('budget_movements.store'), $this->transferPayload())->assertRedirect();
        $movement = BudgetMovement::firstOrFail();
        $this->assertSame(BudgetMovement::STATUS_PENDING_ORIGIN, $movement->status);

        $this->actingAs($this->originOwner)->post(route('budget_movements.origin-approve', $movement))->assertRedirect();
        $this->assertDatabaseHas('budget_movements', ['id' => $movement->id, 'status' => BudgetMovement::STATUS_PENDING_EXECUTIVE]);
        $this->assertDatabaseHas('budget_movement_decisions', ['budget_movement_id' => $movement->id, 'stage' => 'ORIGEN', 'action' => 'APROBADO', 'actor_user_id' => $this->originOwner->id]);
    }

    public function test_origin_owner_can_return_transfer_for_correction_and_requester_can_edit_it(): void
    {
        $this->actingAs($this->requester)->post(route('budget_movements.store'), $this->transferPayload());
        $movement = BudgetMovement::firstOrFail();
        $this->actingAs($this->originOwner)->post(route('budget_movements.return', $movement), ['comments' => 'El mes de origen no corresponde al presupuesto disponible.'])->assertRedirect();
        $this->assertDatabaseHas('budget_movements', ['id' => $movement->id, 'status' => BudgetMovement::STATUS_RETURNED]);
        $this->actingAs($this->requester)->get(route('budget_movements.edit', $movement))->assertOk();
    }

    public function test_only_cost_center_owner_can_submit_a_movement(): void
    {
        $outsider = User::factory()->create(['is_active' => true]);
        $this->actingAs($outsider)->post(route('budget_movements.store'), $this->transferPayload())->assertForbidden();
    }

    public function test_only_configured_director_can_reject_after_origin_approval(): void
    {
        $this->actingAs($this->requester)->post(route('budget_movements.store'), $this->transferPayload());
        $movement = BudgetMovement::firstOrFail();
        $this->actingAs($this->originOwner)->post(route('budget_movements.origin-approve', $movement));
        $this->actingAs($this->requester)->post(route('budget_movements.reject', $movement), ['comments' => 'No procede por prioridad operativa.'])->assertForbidden();
        $this->actingAs($this->director)->post(route('budget_movements.reject', $movement), ['comments' => 'No procede por prioridad operativa.'])->assertRedirect();
        $this->assertDatabaseHas('budget_movements', ['id' => $movement->id, 'status' => BudgetMovement::STATUS_REJECTED, 'approved_by' => $this->director->id]);
    }

    public function test_active_temporary_substitute_can_take_the_executive_decision(): void
    {
        $substitute = User::factory()->create(['is_active' => true]);
        BudgetMovementApprovalSetting::query()->update([
            'substitute_user_id' => $substitute->id,
            'substitute_starts_at' => now()->subHour(),
            'substitute_ends_at' => now()->addHour(),
        ]);

        $this->actingAs($this->requester)->post(route('budget_movements.store'), $this->transferPayload());
        $movement = BudgetMovement::firstOrFail();
        $this->actingAs($this->originOwner)->post(route('budget_movements.origin-approve', $movement));
        $this->actingAs($substitute)->post(route('budget_movements.reject', $movement), ['comments' => 'La solicitud debe revisarse durante el siguiente periodo presupuestal.'])->assertRedirect();

        $this->assertDatabaseHas('budget_movements', ['id' => $movement->id, 'status' => BudgetMovement::STATUS_REJECTED, 'approved_by' => $substitute->id]);
    }

    public function test_executive_approval_applies_the_transfer_atomically(): void
    {
        $originBudget = AnnualBudget::create(['cost_center_id' => $this->origin->id, 'fiscal_year' => now()->year, 'total_annual_amount' => 5000, 'status' => 'APROBADO', 'created_by' => $this->director->id]);
        $destinationBudget = AnnualBudget::create(['cost_center_id' => $this->destination->id, 'fiscal_year' => now()->year, 'total_annual_amount' => 1000, 'status' => 'APROBADO', 'created_by' => $this->director->id]);
        BudgetMonthlyDistribution::create(['annual_budget_id' => $originBudget->id, 'budget_cedula_id' => $this->cedula->id, 'expense_category_id' => $this->category->id, 'month' => 1, 'assigned_amount' => 5000, 'created_by' => $this->director->id]);
        BudgetMonthlyDistribution::create(['annual_budget_id' => $destinationBudget->id, 'budget_cedula_id' => $this->cedula->id, 'expense_category_id' => $this->category->id, 'month' => 2, 'assigned_amount' => 1000, 'created_by' => $this->director->id]);

        $this->actingAs($this->requester)->post(route('budget_movements.store'), $this->transferPayload());
        $movement = BudgetMovement::firstOrFail();
        $this->actingAs($this->originOwner)->post(route('budget_movements.origin-approve', $movement));
        $this->actingAs($this->director)->post(route('budget_movements.approve', $movement))->assertRedirect();

        $this->assertDatabaseHas('budget_movements', ['id' => $movement->id, 'status' => BudgetMovement::STATUS_APPROVED]);
        $this->assertDatabaseHas('budget_monthly_distributions', ['annual_budget_id' => $originBudget->id, 'month' => 1, 'assigned_amount' => 4000]);
        $this->assertDatabaseHas('budget_monthly_distributions', ['annual_budget_id' => $destinationBudget->id, 'month' => 2, 'assigned_amount' => 2000]);
    }

    public function test_owner_can_view_current_and_projected_budget_for_a_selected_subaccount(): void
    {
        $budget = AnnualBudget::create(['cost_center_id' => $this->destination->id, 'fiscal_year' => now()->year, 'total_annual_amount' => 5000, 'status' => 'APROBADO', 'created_by' => $this->director->id]);
        BudgetMonthlyDistribution::create(['annual_budget_id' => $budget->id, 'budget_cedula_id' => $this->cedula->id, 'expense_category_id' => $this->category->id, 'month' => 2, 'assigned_amount' => 5000, 'consumed_amount' => 1200, 'committed_amount' => 300, 'created_by' => $this->director->id]);

        $this->actingAs($this->requester)->getJson(route('budget_movements.budget-snapshot', [
            'cost_center_id' => $this->destination->id,
            'fiscal_year' => now()->year,
            'month' => 2,
            'expense_category_id' => $this->category->id,
            'budget_cedula_id' => $this->cedula->id,
            'amount' => 1000,
            'effect' => 'DECREASE',
            'context' => 'single',
        ]))->assertOk()->assertJsonPath('available_amount', 3500)->assertJsonPath('projected_available_amount', 2500)->assertJsonPath('has_sufficient_available', true);
    }

    public function test_user_cannot_view_another_center_as_a_single_center_preview(): void
    {
        $this->actingAs($this->requester)->getJson(route('budget_movements.budget-snapshot', [
            'cost_center_id' => $this->origin->id,
            'fiscal_year' => now()->year,
            'month' => 1,
            'expense_category_id' => $this->category->id,
            'budget_cedula_id' => $this->cedula->id,
            'amount' => 1000,
            'effect' => 'DECREASE',
            'context' => 'single',
        ]))->assertForbidden();
    }

    private function transferPayload(): array
    {
        return ['movement_type' => 'TRANSFERENCIA', 'fiscal_year' => now()->year, 'movement_date' => now()->toDateString(), 'total_amount' => 1000, 'justification' => 'Se requiere redistribuir presupuesto para una necesidad operativa.', 'origin_cost_center_id' => $this->origin->id, 'origin_month' => 1, 'origin_expense_category_id' => $this->category->id, 'origin_budget_cedula_id' => $this->cedula->id, 'destination_cost_center_id' => $this->destination->id, 'destination_month' => 2, 'destination_expense_category_id' => $this->category->id, 'destination_budget_cedula_id' => $this->cedula->id];
    }
}
