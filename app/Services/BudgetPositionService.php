<?php

namespace App\Services;

use App\Models\BudgetCommitment;
use App\Models\BudgetMonthlyDistribution;
use App\Models\BudgetMovement;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Posición presupuestal por renglón (RP-01). Fuente única del disponible:
 * la usan el reporte y el bloqueo (BudgetAllocationService::checkAvailability).
 *
 * Por renglón se cumple: reservado + comprometido + devengado + disponible + sin conciliar = vigente.
 */
class BudgetPositionService
{
    /** Montos que se suman tal cual al agrupar renglones. */
    private const SUMMABLE = ['current_budget', 'reserved', 'committed', 'accrued', 'available', 'unreconciled', 'released', 'increases', 'decreases'];

    /**
     * Posición por renglón con autorizado, ampliaciones y reducciones (misma reconstrucción que RP-02).
     *
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

        return $this->withBudgetChanges($this->fromDistributions($query->orderBy('id')->get()));
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

            return $this->withIndicators([
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
            ]);
        })->values();
    }

    /**
     * Suma renglones (subtotal o total): los montos se suman y los indicadores se recalculan,
     * nunca se promedian. Si algún renglón no tiene base, el autorizado del grupo es null.
     */
    public function summarize(Collection $positions): array
    {
        $total = [];
        foreach (self::SUMMABLE as $field) {
            $total[$field] = round((float) $positions->sum(fn ($p) => $p[$field] ?? 0), 2);
        }
        $total['paid'] = null;
        $total['authorized_amount'] = $positions->isNotEmpty() && $positions->every(fn ($p) => array_key_exists('authorized_amount', $p) && $p['authorized_amount'] !== null)
            ? round((float) $positions->sum('authorized_amount'), 2) : null;
        $total['budget_difference'] = $total['authorized_amount'] === null ? null
            : round($total['authorized_amount'] + $total['increases'] - $total['decreases'] - $total['current_budget'], 2);
        $total['baseline_status'] = $total['budget_difference'] === null ? 'SIN_BASE'
            : (abs($total['budget_difference']) < 0.005 ? 'CONCILIA' : 'DIFERENCIA');
        // Acepta renglones sueltos o grupos ya resumidos (que traen su propio conteo).
        $total['lines_without_baseline'] = (int) $positions->sum(fn ($p) => $p['lines_without_baseline'] ?? (($p['baseline_status'] ?? null) === 'SIN_BASE' ? 1 : 0));

        return $this->withIndicators($total);
    }

    /**
     * Acumulado del ejercicio al mes (period_scope ACU) por centro + cuenta + subcuenta,
     * con proyección de cierre: consumido acumulado + promedio mensual ejercido de los
     * últimos meses cerrados × meses restantes del ejercicio.
     */
    public function accumulated(array $filters, int $periodMonth, ?CarbonInterface $today = null): Collection
    {
        if (empty($filters['fiscal_year'])) {
            throw new InvalidArgumentException('El acumulado requiere el ejercicio.');
        }
        if ($periodMonth < 1 || $periodMonth > 12) {
            throw new InvalidArgumentException('El mes debe estar entre 1 y 12.');
        }

        $year = (int) $filters['fiscal_year'];
        $today ??= now(config('app.timezone'));
        $closedThrough = match (true) {
            $year < $today->year => 12,
            $year === $today->year => $today->month - 1,
            default => 0,
        };
        $basisMonths = $closedThrough > 0
            ? range(max(1, $closedThrough - (int) config('budget_position.projection_months', 3) + 1), $closedThrough)
            : [];

        unset($filters['months']);

        return $this->positions($filters)
            ->groupBy(fn ($p) => implode('|', [$p['cost_center_id'], $p['expense_category_id'], $p['budget_cedula_id'] ?? 'null']))
            ->map(function (Collection $lines) use ($periodMonth, $basisMonths) {
                $first = $lines->first();
                $row = $this->summarize($lines->where('month', '<=', $periodMonth)) + [
                    'cost_center_id' => $first['cost_center_id'],
                    'fiscal_year' => $first['fiscal_year'],
                    'period_month' => $periodMonth,
                    'period_scope' => 'ACU',
                    'expense_category_id' => $first['expense_category_id'],
                    'budget_cedula_id' => $first['budget_cedula_id'],
                ];

                $average = $basisMonths === [] ? null
                    : $lines->whereIn('month', $basisMonths)->sum('exercised_total') / count($basisMonths);
                $row['projected_close'] = $average === null ? null
                    : round($row['consumed_total'] + $average * (12 - $periodMonth), 2);
                // Meses cerrados usados; menos que los configurados indica poca historia.
                $row['projection_basis_months'] = count($basisMonths);

                return $row;
            })
            ->values();
    }

    /** Ejercido, consumido, % de avance y semáforo de un renglón o grupo. */
    private function withIndicators(array $row): array
    {
        $row['exercised_total'] = round($row['committed'] + $row['accrued'], 2);
        // Todo lo que ya no está disponible (incluye lo sin conciliar): mismo criterio que el bloqueo.
        $row['consumed_total'] = round($row['current_budget'] - $row['available'], 2);
        $row['progress_pct'] = abs($row['current_budget']) < 0.005 ? null
            : round($row['consumed_total'] / $row['current_budget'], 4);
        $row['traffic_light'] = $this->trafficLight($row['progress_pct'], $row['consumed_total']);

        return $row;
    }

    private function trafficLight(?float $progress, float $consumed): string
    {
        if ($progress === null) {
            return $consumed > 0.005 ? 'ROJO' : 'VERDE';
        }

        return match (true) {
            $progress >= (float) config('budget_position.traffic_light.red', 1.0) => 'ROJO',
            $progress >= (float) config('budget_position.traffic_light.yellow', 0.8) => 'AMARILLO',
            default => 'VERDE',
        };
    }

    /**
     * Autorizado original (base capturada), ampliaciones y reducciones aprobadas por renglón,
     * con la misma llave que la conciliación de RP-02. Si una línea tiene varias distribuciones,
     * los importes de la línea se asignan a la primera para no duplicarlos.
     */
    private function withBudgetChanges(Collection $positions): Collection
    {
        if ($positions->isEmpty()) {
            return $positions;
        }

        $centers = $positions->pluck('cost_center_id')->unique()->values();
        $years = $positions->pluck('fiscal_year')->unique()->values();
        $lineKey = fn ($center, $year, $month, $category, $cedula) => implode('|', [(int) $center, (int) $year, (int) $month, (int) $category, $cedula ? (int) $cedula : 0]);

        $baselines = DB::table('budget_distribution_baselines as b')
            ->join('annual_budgets as ab', 'ab.id', '=', 'b.annual_budget_id')
            ->whereIn('ab.cost_center_id', $centers)->whereIn('ab.fiscal_year', $years)
            ->get(['ab.cost_center_id', 'ab.fiscal_year', 'b.month', 'b.expense_category_id', 'b.budget_cedula_id', 'b.original_amount'])
            ->groupBy(fn ($b) => $lineKey($b->cost_center_id, $b->fiscal_year, $b->month, $b->expense_category_id, $b->budget_cedula_id))
            ->map(fn (Collection $rows) => round((float) $rows->sum('original_amount'), 2));

        $movements = DB::table('budget_movement_details as d')
            ->join('budget_movements as bm', 'bm.id', '=', 'd.budget_movement_id')
            ->where('bm.status', BudgetMovement::STATUS_APPROVED)
            ->whereIn('bm.fiscal_year', $years)->whereIn('d.cost_center_id', $centers)
            ->get(['d.cost_center_id', 'bm.fiscal_year', 'd.month', 'd.expense_category_id', 'd.budget_cedula_id', 'd.amount'])
            ->groupBy(fn ($d) => $lineKey($d->cost_center_id, $d->fiscal_year, $d->month, $d->expense_category_id, $d->budget_cedula_id))
            ->map(fn (Collection $rows) => [
                'increases' => round((float) $rows->where('amount', '>', 0)->sum('amount'), 2),
                'decreases' => round(-1 * (float) $rows->where('amount', '<', 0)->sum('amount'), 2),
            ]);

        $keyOf = fn (array $p) => $lineKey($p['cost_center_id'], $p['fiscal_year'], $p['month'], $p['expense_category_id'], $p['budget_cedula_id']);
        $currentByLine = $positions->groupBy($keyOf)->map(fn (Collection $rows) => round((float) $rows->sum('current_budget'), 2));
        $seen = [];

        return $positions->map(function (array $p) use ($baselines, $movements, $currentByLine, $keyOf, &$seen) {
            $key = $keyOf($p);
            $owner = ! isset($seen[$key]);
            $seen[$key] = true;

            $p['increases'] = $owner ? ($movements[$key]['increases'] ?? 0.0) : 0.0;
            $p['decreases'] = $owner ? ($movements[$key]['decreases'] ?? 0.0) : 0.0;

            if (! $baselines->has($key)) {
                $p['authorized_amount'] = null;
                $p['budget_difference'] = null;
                $p['baseline_status'] = 'SIN_BASE';

                return $p;
            }

            // Igual que RP-02: (autorizado + movimientos) − vigente, a nivel de línea.
            $difference = round($baselines[$key] + ($movements[$key]['increases'] ?? 0.0) - ($movements[$key]['decreases'] ?? 0.0) - $currentByLine[$key], 2);
            $p['authorized_amount'] = $owner ? $baselines[$key] : 0.0;
            $p['budget_difference'] = $owner ? $difference : 0.0;
            $p['baseline_status'] = abs($difference) < 0.005 ? 'CONCILIA' : 'DIFERENCIA';

            return $p;
        });
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
