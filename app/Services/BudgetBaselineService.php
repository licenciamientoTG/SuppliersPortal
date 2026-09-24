<?php

namespace App\Services;

use App\Models\AnnualBudget;
use App\Models\BudgetBaseline;
use App\Models\BudgetMonthlyDistribution;
use App\Models\BudgetMovement;
use App\Models\BudgetMovementDetail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BudgetBaselineService
{
    public static function lineKey(int|string $month, int|string $categoryId, int|string|null $cedulaId): string
    {
        return (int) $month.'|'.(int) $categoryId.'|'.($cedulaId === null ? '-' : (int) $cedulaId);
    }

    /** Guarda la foto del presupuesto actual. Sin $overwrite no toca una foto existente. */
    public function capture(AnnualBudget $budget, string $source, ?int $userId = null, bool $overwrite = false): int
    {
        return DB::transaction(function () use ($budget, $source, $userId, $overwrite) {
            $query = BudgetBaseline::where('annual_budget_id', $budget->id);
            if ($overwrite) {
                $query->delete();
            } elseif ($query->exists()) {
                return 0;
            }

            $lines = $this->currentLines($budget->id);
            foreach ($lines as $line) {
                $this->storeLine($budget->id, $line, (float) $line->amount, $source, $userId);
            }

            return $lines->count();
        });
    }

    /** Aumentos (positivos) y disminuciones (negativas) de movimientos APROBADOS por renglón. */
    public function approvedMovementEffects(int $costCenterId, int $fiscalYear): array
    {
        return BudgetMovementDetail::query()
            ->join('budget_movements as bm', 'bm.id', '=', 'budget_movement_details.budget_movement_id')
            ->where('bm.status', BudgetMovement::STATUS_APPROVED)
            ->where('bm.fiscal_year', $fiscalYear)
            ->where('budget_movement_details.cost_center_id', $costCenterId)
            ->groupBy('budget_movement_details.month', 'budget_movement_details.expense_category_id', 'budget_movement_details.budget_cedula_id')
            ->selectRaw('budget_movement_details.month, budget_movement_details.expense_category_id, budget_movement_details.budget_cedula_id')
            ->selectRaw('SUM(CASE WHEN budget_movement_details.amount > 0 THEN budget_movement_details.amount ELSE 0 END) as increases')
            ->selectRaw('SUM(CASE WHEN budget_movement_details.amount < 0 THEN budget_movement_details.amount ELSE 0 END) as decreases')
            ->get()
            ->mapWithKeys(fn ($row) => [self::lineKey($row->month, $row->expense_category_id, $row->budget_cedula_id) => [
                'increases' => (float) $row->increases,
                'decreases' => (float) $row->decreases,
            ]])
            ->all();
    }

    /** Presupuestos APROBADOS sin foto: original = asignado actual − efecto de movimientos aprobados. */
    public function reconstructMissing(?int $userId = null): int
    {
        $count = 0;

        AnnualBudget::where('status', 'APROBADO')->whereDoesntHave('baselines')->get()
            ->each(function (AnnualBudget $budget) use ($userId, &$count) {
                $effects = $this->approvedMovementEffects($budget->cost_center_id, (int) $budget->fiscal_year);

                DB::transaction(function () use ($budget, $effects, $userId) {
                    foreach ($this->currentLines($budget->id) as $line) {
                        $effect = $effects[self::lineKey($line->month, $line->expense_category_id, $line->budget_cedula_id)] ?? ['increases' => 0, 'decreases' => 0];
                        $original = (float) $line->amount - $effect['increases'] - $effect['decreases'];
                        $this->storeLine($budget->id, $line, $original, BudgetBaseline::SOURCE_RECONSTRUCTED, $userId);
                    }
                });

                $count++;
            });

        return $count;
    }

    private function currentLines(int $annualBudgetId): Collection
    {
        return BudgetMonthlyDistribution::where('annual_budget_id', $annualBudgetId)
            ->groupBy('month', 'expense_category_id', 'budget_cedula_id')
            ->selectRaw('month, expense_category_id, budget_cedula_id, SUM(assigned_amount) as amount')
            ->get();
    }

    private function storeLine(int $annualBudgetId, object $line, float $amount, string $source, ?int $userId): void
    {
        BudgetBaseline::create([
            'annual_budget_id' => $annualBudgetId,
            'month' => (int) $line->month,
            'expense_category_id' => $line->expense_category_id,
            'budget_cedula_id' => $line->budget_cedula_id,
            'original_amount' => round($amount, 2),
            'source' => $source,
            'captured_at' => now(),
            'captured_by' => $userId,
        ]);
    }
}
