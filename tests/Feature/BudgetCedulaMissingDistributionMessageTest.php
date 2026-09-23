<?php

namespace Tests\Feature;

use App\Models\AnnualBudget;
use App\Models\BudgetCedula;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\BudgetAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class BudgetCedulaMissingDistributionMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_distribution_message_names_the_cedula(): void
    {
        $user = User::factory()->create();
        $costCenter = CostCenter::factory()->create(['budget_type' => 'ANNUAL']);
        $expenseCategory = ExpenseCategory::factory()->create();
        $cedula = BudgetCedula::factory()->create([
            'expense_category_id' => $expenseCategory->id,
            'name' => 'Papelería y artículos de oficina',
        ]);

        AnnualBudget::create([
            'cost_center_id' => $costCenter->id,
            'fiscal_year' => 2026,
            'total_annual_amount' => 1000,
            'status' => 'APROBADO',
            'created_by' => $user->id,
        ]);

        try {
            app(BudgetAllocationService::class)->checkAvailability(
                $costCenter->id, 2026, 3, $expenseCategory->id, 100, $cedula->id
            );
            $this->fail('Expected exception');
        } catch (RuntimeException $e) {
            $this->assertSame(
                'No existe distribución mensual para la cédula "Papelería y artículos de oficina" en 3/2026.',
                $e->getMessage()
            );
        }
    }
}
