<?php

namespace App\Reports\Budget;

use App\Models\BudgetCedula;
use App\Models\BudgetCommitment;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\BudgetPositionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * RP-01 · Presupuesto vs. ejercido. Arma filas con nombres, subtotales y KPIs sobre
 * BudgetPositionService, que es la misma fuente que usa el bloqueo de presupuesto.
 */
class BudgetVsActualReport
{
    /** Roles que ven todos los centros (acotados a sus empresas asignadas, si tienen). */
    public const FULL_SCOPE_ROLES = ['superadmin', 'general_director', 'accounting'];

    public const BUCKETS = ['reserved', 'committed', 'accrued', 'released'];

    public function __construct(private readonly BudgetPositionService $positions) {}

    /** Centros de costo que el usuario puede consultar; el alcance se aplica siempre en servidor. */
    public function visibleCostCenters(User $user): Builder
    {
        $query = CostCenter::query();
        if ($user->hasRole('superadmin')) {
            return $query;
        }
        if ($user->hasRole(self::FULL_SCOPE_ROLES)) {
            $companyIds = $user->companies()->pluck('companies.id');

            return $companyIds->isEmpty() ? $query : $query->whereIn('company_id', $companyIds);
        }

        // Jefes de departamento y demás: solo los centros donde son responsables.
        return $query->where('responsible_user_id', $user->id);
    }

    /**
     * @param  array{fiscal_year: int, period_month: int, scope: string, company_ids?: int[], cost_center_ids?: int[], expense_category_ids?: int[], responsible_user_id?: ?int}  $params
     * @return array{rows: Collection, cost_centers: Collection, companies: Collection, total: array, kpis: array}
     */
    public function build(User $user, array $params): array
    {
        $centerIds = $this->allowedCenterIds($user, $params);
        $rows = $centerIds->isEmpty() ? collect() : $this->rows($centerIds, $params);

        $costCenters = $rows->groupBy('cost_center_id')->map(fn (Collection $lines) => $this->subtotal($lines));
        $companies = $rows->groupBy('company_id')->map(fn (Collection $lines) => $this->subtotal($lines));
        $total = $this->subtotal($rows);

        return [
            'rows' => $rows,
            'cost_centers' => $costCenters,
            'companies' => $companies,
            'total' => $total,
            'kpis' => [
                'current_budget' => $total['current_budget'],
                'consumed_total' => $total['consumed_total'],
                'available' => $total['available'],
                'progress_pct' => $total['progress_pct'],
                'red_lines' => $rows->where('traffic_light', 'ROJO')->count(),
                'lines' => $rows->count(),
                'unreconciled' => $total['unreconciled'],
                'lines_without_baseline' => $total['lines_without_baseline'],
            ],
        ];
    }

    /**
     * Documentos que componen un monto de una línea (drill-down).
     *
     * @param  array{fiscal_year: int, period_month: int, scope: string, cost_center_id: int, expense_category_id: int, budget_cedula_id: ?int, bucket: string}  $params
     */
    public function documents(User $user, array $params): Collection
    {
        abort_unless($this->visibleCostCenters($user)->whereKey($params['cost_center_id'])->exists(), 403);

        $months = collect($params['scope'] === 'ACU' ? range(1, $params['period_month']) : [$params['period_month']])
            ->map(fn ($month) => sprintf('%04d-%02d', $params['fiscal_year'], $month));

        $query = BudgetCommitment::query()
            ->with(['purchaseOrder:id,folio', 'directPurchaseOrder:id,folio', 'quotationSummary.rfq:id,folio', 'quotationSummary.requisition:id,folio'])
            ->where('cost_center_id', $params['cost_center_id'])
            ->where('expense_category_id', $params['expense_category_id'])
            ->whereIn('application_month', $months)
            ->when($params['budget_cedula_id'] ?? null,
                fn ($q, $cedula) => $q->where('budget_cedula_id', $cedula),
                fn ($q) => $q->whereNull('budget_cedula_id'));

        // Mismas reglas que BudgetPositionService para que el detalle sume lo que muestra la fila.
        match ($params['bucket']) {
            'reserved' => $query->where('status', 'COMMITTED')->whereNotNull('quotation_summary_id'),
            'committed' => $query->where('status', 'COMMITTED')->whereNull('quotation_summary_id'),
            'accrued' => $query->whereIn('status', ['COMMITTED', 'RECEIVED'])->where('consumed_amount', '>', 0),
            'released' => $query->where('status', 'RELEASED'),
        };

        return $query->orderBy('application_month')->orderBy('id')->get()->map(function (BudgetCommitment $c) use ($params) {
            $pending = round((float) $c->committed_amount - (float) $c->consumed_amount, 2);

            return [
                'type' => $c->getOrderType(),
                'folio' => $c->getOrderFolio() ?? $c->quotationSummary?->requisition?->folio,
                'url' => $this->documentUrl($c),
                'application_month' => $c->application_month,
                'status' => $c->status,
                'committed_amount' => round((float) $c->committed_amount, 2),
                'consumed_amount' => round((float) $c->consumed_amount, 2),
                'amount' => match ($params['bucket']) {
                    'accrued' => round((float) $c->consumed_amount, 2),
                    'released' => round((float) $c->committed_amount, 2),
                    default => $pending,
                },
                'committed_at' => $c->committed_at?->format('d/m/Y'),
            ];
        });
    }

    /**
     * Detalle por documento de todas las filas (hoja de detalle del Excel). Un compromiso puede
     * aparecer en dos montos: lo pendiente en Reservado/Comprometido y lo recibido en Devengado.
     */
    public function documentLines(array $params, Collection $rows): Collection
    {
        if ($rows->isEmpty()) {
            return collect();
        }

        $rowsByLine = $rows->keyBy(fn ($row) => $this->lineKey($row));
        $months = collect($params['scope'] === 'ACU' ? range(1, $params['period_month']) : [$params['period_month']])
            ->map(fn ($month) => sprintf('%04d-%02d', $params['fiscal_year'], $month));
        $statuses = $params['include_cancelled_po'] ? ['COMMITTED', 'RECEIVED', 'RELEASED'] : ['COMMITTED', 'RECEIVED'];

        return BudgetCommitment::query()
            ->with(['purchaseOrder:id,folio', 'directPurchaseOrder:id,folio', 'quotationSummary.rfq:id,folio', 'quotationSummary.requisition:id,folio'])
            ->whereIn('cost_center_id', $rows->pluck('cost_center_id')->unique())
            ->whereIn('expense_category_id', $rows->pluck('expense_category_id')->unique())
            ->whereIn('application_month', $months)
            ->whereIn('status', $statuses)
            ->orderBy('cost_center_id')->orderBy('application_month')->orderBy('id')
            ->get()
            ->flatMap(function (BudgetCommitment $c) use ($rowsByLine) {
                $row = $rowsByLine->get($this->lineKey([
                    'cost_center_id' => $c->cost_center_id, 'expense_category_id' => $c->expense_category_id, 'budget_cedula_id' => $c->budget_cedula_id,
                ]));
                if (! $row) {
                    return [];
                }

                $pending = round((float) $c->committed_amount - (float) $c->consumed_amount, 2);
                $entries = [];
                if ($c->status === 'COMMITTED' && abs($pending) >= 0.005) {
                    $entries[] = [$c->quotation_summary_id ? 'reserved' : 'committed', $pending];
                }
                if ($c->status !== 'RELEASED' && (float) $c->consumed_amount > 0) {
                    $entries[] = ['accrued', round((float) $c->consumed_amount, 2)];
                }
                if ($c->status === 'RELEASED') {
                    $entries[] = ['released', round((float) $c->committed_amount, 2)];
                }

                return array_map(fn ($entry) => [
                    'company_name' => $row['company_name'],
                    'cost_center_code' => $row['cost_center_code'],
                    'cost_center_name' => $row['cost_center_name'],
                    'budget_line_code' => $row['budget_line_code'],
                    'budget_line_name' => $row['budget_line_name'],
                    'budget_cedula_name' => $row['budget_cedula_name'],
                    'bucket' => $entry[0],
                    'type' => $c->getOrderType(),
                    'folio' => $c->getOrderFolio() ?? $c->quotationSummary?->requisition?->folio,
                    'application_month' => $c->application_month,
                    'status' => $c->status,
                    'committed_at' => $c->committed_at,
                    'committed_amount' => round((float) $c->committed_amount, 2),
                    'consumed_amount' => round((float) $c->consumed_amount, 2),
                    'amount' => $entry[1],
                ], $entries);
            })
            ->values();
    }

    private function allowedCenterIds(User $user, array $params): Collection
    {
        return $this->visibleCostCenters($user)
            ->when(! empty($params['company_ids']), fn ($q) => $q->whereIn('company_id', $params['company_ids']))
            ->when(! empty($params['cost_center_ids']), fn ($q) => $q->whereIn('id', $params['cost_center_ids']))
            ->when(! empty($params['responsible_user_id']), fn ($q) => $q->where('responsible_user_id', $params['responsible_user_id']))
            ->pluck('id');
    }

    private function rows(Collection $centerIds, array $params): Collection
    {
        $filters = [
            'fiscal_year' => $params['fiscal_year'],
            'cost_center_ids' => $centerIds->all(),
            'expense_category_ids' => $params['expense_category_ids'] ?? [],
        ];

        // La proyección siempre sale del acumulado de la línea; en vista MES se adjunta a la fila del mes.
        $accumulated = $this->positions->accumulated($filters, $params['period_month'])->keyBy(fn ($row) => $this->lineKey($row));

        $rows = $params['scope'] === 'ACU'
            ? $accumulated->values()
            : $this->positions->positions($filters + ['months' => [$params['period_month']]])
                ->groupBy(fn ($p) => $this->lineKey($p))
                ->map(function (Collection $lines, string $key) use ($params, $accumulated) {
                    $first = $lines->first();

                    return $this->positions->summarize($lines) + [
                        'cost_center_id' => $first['cost_center_id'],
                        'fiscal_year' => $first['fiscal_year'],
                        'period_month' => $params['period_month'],
                        'period_scope' => 'MES',
                        'expense_category_id' => $first['expense_category_id'],
                        'budget_cedula_id' => $first['budget_cedula_id'],
                        'projected_close' => $accumulated[$key]['projected_close'] ?? null,
                        'projection_basis_months' => $accumulated[$key]['projection_basis_months'] ?? 0,
                    ];
                })->values();

        return $this->decorate($rows);
    }

    /** Agrega nombres, ordena y marca dónde termina cada centro y empresa (para pintar subtotales). */
    private function decorate(Collection $rows): Collection
    {
        if ($rows->isEmpty()) {
            return $rows;
        }

        $rows = $this->describe($rows)->sortBy([
            ['company_name', 'asc'], ['cost_center_code', 'asc'], ['budget_line_code', 'asc'], ['budget_cedula_name', 'asc'],
        ])->values();

        return $rows->map(function (array $row, int $index) use ($rows) {
            $next = $rows[$index + 1] ?? null;
            $row['last_of_cost_center'] = $next === null || $next['cost_center_id'] !== $row['cost_center_id'];
            $row['last_of_company'] = $next === null || $next['company_id'] !== $row['company_id'];

            return $row;
        });
    }

    /** Agrega empresa, centro, responsable, renglón y subcuenta a filas con sus ids (también lo usa RP-03). */
    public function describe(Collection $rows): Collection
    {
        if ($rows->isEmpty()) {
            return $rows;
        }

        $centers = CostCenter::query()->with(['company:id,name,rfc', 'responsible:id,name'])
            ->whereIn('id', $rows->pluck('cost_center_id')->unique())->get()->keyBy('id');
        $categories = ExpenseCategory::query()->whereIn('id', $rows->pluck('expense_category_id')->unique())->get(['id', 'code', 'name'])->keyBy('id');
        $cedulas = BudgetCedula::query()->whereIn('id', $rows->pluck('budget_cedula_id')->filter()->unique())->get(['id', 'name'])->keyBy('id');

        return $rows->map(function (array $row) use ($centers, $categories, $cedulas) {
            $center = $centers[$row['cost_center_id']] ?? null;
            $category = $categories[$row['expense_category_id']] ?? null;

            return $row + [
                'company_id' => $center?->company_id,
                'company_name' => $center?->company?->name,
                'company_rfc' => $center?->company?->rfc,
                'cost_center_code' => $center?->code,
                'cost_center_name' => $center?->name,
                'responsible_user_id' => $center?->responsible_user_id,
                'responsible_name' => $center?->responsible?->name,
                'budget_line_code' => $category?->code,
                'budget_line_name' => $category?->name,
                'budget_cedula_name' => $row['budget_cedula_id'] ? ($cedulas[$row['budget_cedula_id']]->name ?? null) : null,
                // El portal aún no liga renglones con el catálogo contable (ver RP-01_mapeo_y_huecos.md).
                'accounting_account' => null,
            ];
        })->values();
    }

    private function subtotal(Collection $rows): array
    {
        $total = $this->positions->summarize($rows);
        $projections = $rows->pluck('projected_close')->filter(fn ($value) => $value !== null);
        $total['projected_close'] = $projections->isEmpty() ? null : round((float) $projections->sum(), 2);

        return $total;
    }

    private function lineKey(array $row): string
    {
        return implode('|', [$row['cost_center_id'], $row['expense_category_id'], $row['budget_cedula_id'] ?? 'null']);
    }

    public function documentUrl(BudgetCommitment $commitment): ?string
    {
        return match (true) {
            (bool) $commitment->purchase_order_id => route('purchase-orders.show', $commitment->purchase_order_id),
            (bool) $commitment->direct_purchase_order_id => route('direct-purchase-orders.show', $commitment->direct_purchase_order_id),
            (bool) $commitment->quotationSummary?->requisition_id => route('requisitions.show', $commitment->quotationSummary->requisition_id),
            default => null,
        };
    }
}
