<?php

namespace App\Services;

use App\Models\AnnualBudget;
use App\Models\BudgetDistributionBaseline;
use App\Models\BudgetMonthlyDistribution;
use App\Models\BudgetMovement;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BudgetBaselineService
{
    /** Capture approved monthly assignments once, before movement adjustments are applied. */
    public function capture(AnnualBudget $budget, ?int $actorId = null, string $source = 'ANNUAL_BUDGET_APPROVAL'): void
    {
        DB::transaction(function () use ($budget, $actorId, $source) {
            $budget = AnnualBudget::query()->lockForUpdate()->findOrFail($budget->id);
            if (BudgetDistributionBaseline::where('annual_budget_id', $budget->id)->exists()) {
                return;
            }

            if (BudgetMovement::query()->where('fiscal_year', $budget->fiscal_year)
                ->where('status', BudgetMovement::STATUS_APPROVED)
                ->whereHas('details', fn ($q) => $q->where('cost_center_id', $budget->cost_center_id))
                ->exists()) {
                throw new RuntimeException('No se puede capturar el saldo actual como base original: existen movimientos aprobados previos. Recupera la fuente aprobada antes de conciliar.');
            }

            $capturedAt = now();
            $rows = BudgetMonthlyDistribution::query()
                ->where('annual_budget_id', $budget->id)
                ->whereNull('deleted_at')
                ->get();

            foreach ($rows as $row) {
                BudgetDistributionBaseline::create([
                    'annual_budget_id' => $budget->id,
                    'budget_monthly_distribution_id' => $row->id,
                    'month' => $row->month,
                    'expense_category_id' => $row->expense_category_id,
                    'budget_cedula_id' => $row->budget_cedula_id,
                    'original_amount' => $row->assigned_amount,
                    'source' => $source,
                    'captured_by' => $actorId,
                    'captured_at' => $capturedAt,
                ]);
            }
        });
    }

    /** Block replacing an already approved baseline through an import. */
    public function assertCanReplace(AnnualBudget $budget): void
    {
        if ($budget->status === 'APROBADO' && (BudgetDistributionBaseline::where('annual_budget_id', $budget->id)->exists() || BudgetMovement::query()
            ->where('fiscal_year', $budget->fiscal_year)
            ->where('status', BudgetMovement::STATUS_APPROVED)
            ->whereHas('details', fn ($q) => $q->where('cost_center_id', $budget->cost_center_id))
            ->exists())) {
            throw new RuntimeException("El presupuesto {$budget->id} tiene movimientos aprobados y no puede reemplazarse mediante importación.");
        }
    }
}
