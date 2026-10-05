<?php

namespace Tests\Feature;

use App\Models\BudgetCedula;
use App\Models\BudgetMovement;
use App\Models\BudgetMovementApprovalSetting;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BudgetMovementApprovalLevelTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;

    private User $director;

    private User $substitute;

    private CostCenter $center;

    private ExpenseCategory $category;

    private BudgetCedula $cedula;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Role::findOrCreate('general_director');
        $this->requester = User::factory()->create(['is_active' => true]);
        $this->director = User::factory()->create(['is_active' => true]);
        $this->director->assignRole('general_director');
        $this->substitute = User::factory()->create(['is_active' => true]);
        BudgetMovementApprovalSetting::create(['director_user_id' => $this->director->id, 'substitute_user_id' => $this->substitute->id, 'substitute_starts_at' => now()->subHour(), 'substitute_ends_at' => now()->addHour()]);
        $this->center = CostCenter::factory()->create(['responsible_user_id' => $this->requester->id]);
        $this->category = ExpenseCategory::factory()->create();
        $this->cedula = BudgetCedula::factory()->create(['expense_category_id' => $this->category->id]);
        // applyMovement() exige un presupuesto anual del año; la distribución la crea él mismo.
        \App\Models\AnnualBudget::create(['cost_center_id' => $this->center->id, 'fiscal_year' => now()->year, 'total_annual_amount' => 0, 'status' => 'APROBADO', 'created_by' => $this->director->id]);
    }

    private function submitIncrease(): BudgetMovement
    {
        $this->actingAs($this->requester)->post(route('budget_movements.store'), [
            'movement_type' => 'AMPLIACION', 'fiscal_year' => now()->year, 'movement_date' => now()->toDateString(), 'total_amount' => 500,
            'justification' => 'Ampliación para prueba de nivel de autorización.', 'supporting_document' => UploadedFile::fake()->create('soporte.pdf', 20, 'application/pdf'), 'cost_center_id' => $this->center->id, 'month' => 3,
            'expense_category_id' => $this->category->id, 'budget_cedula_id' => $this->cedula->id,
        ])->assertRedirect();

        return BudgetMovement::latest('id')->firstOrFail();
    }

    public function test_director_approval_records_direction_level(): void
    {
        $movement = $this->submitIncrease();
        $this->actingAs($this->director)->post(route('budget_movements.approve', $movement))->assertRedirect();

        $this->assertSame(BudgetMovement::LEVEL_DIRECTION, $movement->fresh()->approval_level);
    }

    public function test_substitute_approval_records_substitute_level(): void
    {
        $movement = $this->submitIncrease();
        $this->actingAs($this->substitute)->post(route('budget_movements.approve', $movement))->assertRedirect();

        $this->assertSame(BudgetMovement::LEVEL_SUBSTITUTE, $movement->fresh()->approval_level);
    }
}
