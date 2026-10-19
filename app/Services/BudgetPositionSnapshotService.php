<?php

namespace App\Services;

use App\Models\BudgetPositionSnapshot;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Foto diaria de BudgetPositionService::positions() (RP-01, punto #6) y lectura
 * por fecha de corte histórica. El job `budget:snapshot-position` llama a capture().
 */
class BudgetPositionSnapshotService
{
    public function __construct(private readonly BudgetPositionService $positions) {}

    /** Congela la posición de todos los presupuestos aprobados a la fecha dada (hoy por defecto). */
    public function capture(?CarbonInterface $date = null): int
    {
        $date ??= now(config('app.timezone'));
        $snapshotDate = $date->toDateString();
        $count = 0;

        foreach ($this->positions->positions([]) as $position) {
            BudgetPositionSnapshot::updateOrCreate([
                'snapshot_date' => $snapshotDate,
                'cost_center_id' => $position['cost_center_id'],
                'fiscal_year' => $position['fiscal_year'],
                'month' => $position['month'],
                'expense_category_id' => $position['expense_category_id'],
                'budget_cedula_id' => $position['budget_cedula_id'],
            ], ['payload' => $position]);
            $count++;
        }

        return $count;
    }

    /**
     * Renglones congelados al corte más reciente que no sea posterior a $date.
     *
     * @param  array{fiscal_year?: int, months?: int[], cost_center_ids?: int[], expense_category_ids?: int[]}  $filters
     * @return array{0: ?string, 1: Collection} fecha del corte usado (null si no hay ninguno) y los renglones
     */
    public function positionsAsOf(CarbonInterface $date, array $filters): array
    {
        $snapshotDate = BudgetPositionSnapshot::query()
            ->whereDate('snapshot_date', '<=', $date->toDateString())
            ->max('snapshot_date');

        if (! $snapshotDate) {
            return [null, collect()];
        }

        $rows = BudgetPositionSnapshot::query()
            ->whereDate('snapshot_date', $snapshotDate)
            ->when(! empty($filters['fiscal_year']), fn ($q) => $q->where('fiscal_year', $filters['fiscal_year']))
            ->when(! empty($filters['months']), fn ($q) => $q->whereIn('month', $filters['months']))
            ->when(! empty($filters['cost_center_ids']), fn ($q) => $q->whereIn('cost_center_id', $filters['cost_center_ids']))
            ->when(! empty($filters['expense_category_ids']), fn ($q) => $q->whereIn('expense_category_id', $filters['expense_category_ids']))
            ->get()
            ->map(fn (BudgetPositionSnapshot $snapshot) => $snapshot->payload)
            ->values();

        return [$snapshotDate, $rows];
    }
}
