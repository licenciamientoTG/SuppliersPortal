<?php

namespace App\Services;

use App\Models\AnnualBudget;
use App\Models\BudgetDistributionBaseline;
use App\Models\BudgetMonthlyDistribution;
use App\Models\BudgetMovement;
use App\Models\BudgetMovementDetail;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BudgetBaselineService
{
    public const SOURCE_FILE = 'APPROVED_SOURCE_FILE';

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

    /**
     * Capture the baseline from the approved budget workbook (a sheet report from the 2026 import analyzers).
     * Lines absent from the file start at zero; file lines without a current distribution are reported, not stored.
     *
     * @return array{captured: int, skipped: bool, unmatched: list<array{month: int, expense_category_id: int, budget_cedula_id: ?int, amount: float}>}
     */
    public function captureFromSource(AnnualBudget $budget, array $sheetReport, ?int $actorId = null): array
    {
        return DB::transaction(function () use ($budget, $sheetReport, $actorId) {
            $budget = AnnualBudget::query()->lockForUpdate()->findOrFail($budget->id);
            if (BudgetDistributionBaseline::where('annual_budget_id', $budget->id)->exists()) {
                return ['captured' => 0, 'skipped' => true, 'unmatched' => []];
            }

            $source = $this->sourceAmounts($sheetReport);
            $capturedAt = now();
            $captured = 0;
            foreach ($this->distributions($budget) as $row) {
                $key = $this->key($row->month, $row->expense_category_id, $row->budget_cedula_id);
                BudgetDistributionBaseline::create([
                    'annual_budget_id' => $budget->id,
                    'budget_monthly_distribution_id' => $row->id,
                    'month' => $row->month,
                    'expense_category_id' => $row->expense_category_id,
                    'budget_cedula_id' => $row->budget_cedula_id,
                    // Duplicated distributions of the same line keep the file amount only once.
                    'original_amount' => round($source[$key]['amount'] ?? 0, 2),
                    'source' => self::SOURCE_FILE,
                    'captured_by' => $actorId,
                    'captured_at' => $capturedAt,
                ]);
                unset($source[$key]);
                $captured++;
            }

            return ['captured' => $captured, 'skipped' => false, 'unmatched' => array_values($source)];
        });
    }

    /** Compare the approved workbook against the current budget plus approved movements, without writing. */
    public function previewFromSource(AnnualBudget $budget, array $sheetReport): array
    {
        $lines = [];
        foreach ($this->sourceAmounts($sheetReport) as $key => $line) {
            $lines[$key] = $line + ['original' => $line['amount'], 'movements' => 0.0, 'current' => 0.0];
        }
        foreach ($this->distributions($budget) as $row) {
            $key = $this->key($row->month, $row->expense_category_id, $row->budget_cedula_id);
            $lines[$key] ??= ['month' => (int) $row->month, 'expense_category_id' => (int) $row->expense_category_id, 'budget_cedula_id' => $row->budget_cedula_id, 'original' => 0.0, 'movements' => 0.0, 'current' => 0.0];
            $lines[$key]['current'] += (float) $row->assigned_amount;
        }
        $movements = BudgetMovementDetail::query()
            ->join('budget_movements as bm', 'bm.id', '=', 'budget_movement_details.budget_movement_id')
            ->where('bm.status', BudgetMovement::STATUS_APPROVED)->where('bm.fiscal_year', $budget->fiscal_year)
            ->where('budget_movement_details.cost_center_id', $budget->cost_center_id)
            ->get(['budget_movement_details.month', 'budget_movement_details.expense_category_id', 'budget_movement_details.budget_cedula_id', 'budget_movement_details.amount']);
        foreach ($movements as $detail) {
            $key = $this->key($detail->month, $detail->expense_category_id, $detail->budget_cedula_id);
            $lines[$key] ??= ['month' => (int) $detail->month, 'expense_category_id' => (int) $detail->expense_category_id, 'budget_cedula_id' => $detail->budget_cedula_id, 'original' => 0.0, 'movements' => 0.0, 'current' => 0.0];
            $lines[$key]['movements'] += (float) $detail->amount;
        }

        $lines = collect($lines)->map(function (array $line) {
            unset($line['amount']);
            // Same sign as the RP-02 reconciliation: reconstructed (original + movements) minus current.
            $line['difference'] = round($line['original'] + $line['movements'] - $line['current'], 2);

            return $line;
        })->values();

        return [
            'lines' => $lines,
            'totals' => [
                'original' => round($lines->sum('original'), 2), 'movements' => round($lines->sum('movements'), 2),
                'current' => round($lines->sum('current'), 2), 'difference' => round($lines->sum('difference'), 2),
            ],
            'lines_with_difference' => $lines->filter(fn ($line) => abs($line['difference']) >= 0.005)->count(),
        ];
    }

    private function sourceAmounts(array $sheetReport): array
    {
        $lines = [];
        foreach ($sheetReport['rows'] ?? [] as $row) {
            foreach ($row['months'] as $month => $amount) {
                if (abs((float) $amount) < 0.000001) {
                    continue;
                }
                $key = $this->key($month, $row['expense_category_id'], $row['budget_cedula_id']);
                $lines[$key] ??= ['month' => (int) $month, 'expense_category_id' => (int) $row['expense_category_id'], 'budget_cedula_id' => $row['budget_cedula_id'], 'amount' => 0.0];
                $lines[$key]['amount'] += (float) $amount;
            }
        }

        return $lines;
    }

    private function distributions(AnnualBudget $budget)
    {
        return BudgetMonthlyDistribution::query()->where('annual_budget_id', $budget->id)
            ->whereNull('deleted_at')->orderBy('id')->get();
    }

    private function key($month, $categoryId, $cedulaId): string
    {
        return (int) $month.'|'.(int) $categoryId.'|'.($cedulaId ? (int) $cedulaId : 0);
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
