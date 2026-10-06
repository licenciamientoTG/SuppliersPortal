<?php

namespace App\Services;

use App\Models\BudgetCommitment;
use App\Models\BudgetMonthlyDistribution;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Posición presupuestal por renglón (RP-01). Fuente única del disponible:
 * la usan el reporte y el bloqueo (BudgetAllocationService::checkAvailability).
 *
 * Por renglón se cumple: reservado + comprometido + devengado + disponible + sin conciliar = vigente.
 */
class BudgetPositionService
{
    /**
     * @param  array{company_ids?: int[], fiscal_year?: int, months?: int[], cost_center_ids?: int[], expense_category_ids?: int[], budget_cedula_ids?: int[]}  $filters
     */
    public function positions(array $filters): Collection
    {
        $query = BudgetMonthlyDistribution::query()
            ->with('annualBudget.costCenter')
            ->whereHas('annualBudget', function ($budget) use ($filters) {
                $budget->where('status', 'APROBADO');
                if (! empty($filters['fiscal_year'])) {
                    $budget->where('fiscal_year', (int) $filters['fiscal_year']);
                }
                if (! empty($filters['cost_center_ids'])) {
                    $budget->whereIn('cost_center_id', $filters['cost_center_ids']);
                }
                if (! empty($filters['company_ids'])) {
                    $budget->whereHas('costCenter', fn ($center) => $center->whereIn('company_id', $filters['company_ids']));
                }
            });

        foreach (['months' => 'month', 'expense_category_ids' => 'expense_category_id', 'budget_cedula_ids' => 'budget_cedula_id'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->whereIn($column, $filters[$filter]);
            }
        }

        return $this->fromDistributions($query->orderBy('id')->get());
    }

    /** @param  Collection<int, BudgetMonthlyDistribution>  $distributions */
    public function fromDistributions(Collection $distributions): Collection
    {
        $distributions = (new EloquentCollection($distributions->all()))->loadMissing('annualBudget');
        $ledger = $this->ledger($distributions);

        return $distributions->map(function (BudgetMonthlyDistribution $line) use ($ledger) {
            $documents = $ledger->get($this->key(
                $line->annualBudget->cost_center_id,
                sprintf('%04d-%02d', $line->annualBudget->fiscal_year, $line->month),
                $line->expense_category_id,
                $line->budget_cedula_id
            ), ['reserved' => 0.0, 'committed' => 0.0, 'released' => 0.0]);

            $counter = round((float) $line->committed_amount, 2);

            return [
                'distribution_id' => $line->id,
                'cost_center_id' => $line->annualBudget->cost_center_id,
                'fiscal_year' => (int) $line->annualBudget->fiscal_year,
                'month' => (int) $line->month,
                'expense_category_id' => $line->expense_category_id,
                'budget_cedula_id' => $line->budget_cedula_id,
                'current_budget' => round((float) $line->assigned_amount, 2),
                'reserved' => $documents['reserved'],
                'committed' => $documents['committed'],
                'accrued' => round((float) $line->consumed_amount, 2),
                // El portal no registra pagos: se informa como no disponible, nunca como cero.
                'paid' => null,
                'available' => $line->getBalanceAmount(),
                'unreconciled' => round($counter - $documents['reserved'] - $documents['committed'], 2),
                'released' => $documents['released'],
            ];
        })->values();
    }

    /** Compromisos por documento agrupados por renglón, en una sola consulta. */
    private function ledger(Collection $distributions): Collection
    {
        if ($distributions->isEmpty()) {
            return collect();
        }

        $centers = $distributions->map(fn ($line) => $line->annualBudget->cost_center_id)->unique()->values();
        $months = $distributions->map(fn ($line) => sprintf('%04d-%02d', $line->annualBudget->fiscal_year, $line->month))->unique()->values();
        $categories = $distributions->pluck('expense_category_id')->unique()->values();

        return BudgetCommitment::query()
            ->whereIn('cost_center_id', $centers)
            ->whereIn('application_month', $months)
            ->whereIn('expense_category_id', $categories)
            ->whereIn('status', ['COMMITTED', 'RELEASED'])
            ->get(['cost_center_id', 'application_month', 'expense_category_id', 'budget_cedula_id', 'quotation_summary_id', 'committed_amount', 'consumed_amount', 'status'])
            ->groupBy(fn ($c) => $this->key($c->cost_center_id, $c->application_month, $c->expense_category_id, $c->budget_cedula_id))
            ->map(function (Collection $commitments) {
                $open = $commitments->where('status', 'COMMITTED');
                $pending = fn ($c) => round((float) $c->committed_amount - (float) $c->consumed_amount, 2);

                return [
                    'reserved' => round($open->whereNotNull('quotation_summary_id')->sum($pending), 2),
                    'committed' => round($open->whereNull('quotation_summary_id')->sum($pending), 2),
                    'released' => round($commitments->where('status', 'RELEASED')->sum(fn ($c) => (float) $c->committed_amount), 2),
                ];
            });
    }

    private function key($costCenterId, $applicationMonth, $expenseCategoryId, $budgetCedulaId): string
    {
        return implode('|', [(int) $costCenterId, $applicationMonth, (int) $expenseCategoryId, $budgetCedulaId === null ? 'null' : (int) $budgetCedulaId]);
    }
}
