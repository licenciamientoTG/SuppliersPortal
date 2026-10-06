<?php

namespace App\Reports\Budget;

use App\Models\BudgetCommitment;
use App\Models\BudgetException;
use App\Models\User;
use App\Services\BudgetPositionService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * RP-03 · Alertas de agotamiento, sobregiro y excepciones. Evalúa cada renglón mensual (igual que
 * el bloqueo) con BudgetPositionService, la misma fuente que RP-01.
 */
class BudgetAlertsReport
{
    public const THRESHOLDS = [80, 90, 100];

    public function __construct(
        private readonly BudgetPositionService $positions,
        private readonly BudgetVsActualReport $budget,
    ) {}

    /** Mismo alcance que RP-01: el filtro se aplica en servidor. */
    public function visibleCostCenters(User $user): Builder
    {
        return $this->budget->visibleCostCenters($user);
    }

    /**
     * @param  array{fiscal_year: int, threshold: int, months: int[], company_ids: int[], cost_center_ids: int[], responsible_user_id: ?int, exceptions_from: string, exceptions_to: string, exception_status: ?string}  $params
     */
    public function build(User $user, array $params): array
    {
        $centerIds = $this->allowedCenterIds($user, $params);
        $lines = $centerIds->isEmpty() ? collect() : $this->riskLines($centerIds, $params);
        $exceptions = $this->exceptions($user, $params, $centerIds);
        $approved = $exceptions->where('status', 'APPROVED');

        return [
            'lines' => $lines,
            'exceptions' => $exceptions,
            'kpis' => [
                'level_80' => $lines->where('alert_level', 80)->count(),
                'level_90' => $lines->where('alert_level', 90)->count(),
                'level_100' => $lines->where('alert_level', 100)->count(),
                'lines' => $lines->count(),
                'approved_exceptions' => $approved->count(),
                'approved_exceptions_amount' => round((float) $approved->sum('approved_excess'), 2),
                'pending_exceptions' => $exceptions->where('status', 'PENDING')->count(),
                'incomplete_exceptions' => $approved->where('complete', false)->count(),
            ],
        ];
    }

    /**
     * Renglones mensuales en riesgo: consumo ≥ umbral, sobregiro o excepción aprobada en el periodo.
     * Ritmo de gasto = promedio de ejercido de los últimos 3 meses cerrados del renglón (como la
     * proyección de RP-01); meses para agotarse = disponible que queda en el año ÷ ritmo.
     */
    public function riskLines(Collection $centerIds, array $params, ?CarbonInterface $today = null): Collection
    {
        $today ??= now(config('app.timezone'));
        $year = (int) $params['fiscal_year'];
        $positions = $this->positions->positions(['fiscal_year' => $year, 'cost_center_ids' => $centerIds->all()]);
        if ($positions->isEmpty()) {
            return collect();
        }

        $closedThrough = match (true) {
            $year < $today->year => 12,
            $year === $today->year => $today->month - 1,
            default => 0,
        };
        $basisMonths = $closedThrough > 0 ? range(max(1, $closedThrough - 2), $closedThrough) : [];
        $isCurrentYear = $year === $today->year;
        $stats = $positions->groupBy(fn ($p) => $this->lineKey($p))->map(fn (Collection $lines) => [
            'burn_rate' => $basisMonths === [] ? null : round($lines->whereIn('month', $basisMonths)->sum('exercised_total') / count($basisMonths), 2),
            'remaining' => round((float) $lines->where('month', '>=', $isCurrentYear ? $today->month : 1)->sum('available'), 2),
        ]);

        $pending = $this->pendingDocuments($positions);
        $exceptionKeys = $this->approvedExceptionKeys($positions, $params);
        $threshold = $params['threshold'] / 100;
        $months = $params['months'] ?? [];

        $rows = $positions
            ->filter(fn ($p) => $months === [] || in_array($p['month'], $months, true))
            ->map(function (array $p) use ($stats, $pending, $exceptionKeys, $today, $year, $isCurrentYear) {
                $line = $stats[$this->lineKey($p)];
                $documents = $pending->get($this->monthKey($p), collect());
                $pendingAmount = round((float) $documents->sum('amount'), 2);
                // Solo tiene sentido proyectar el agotamiento del ejercicio en curso o futuro.
                $projectable = $year >= $today->year && $line['burn_rate'] > 0;
                $monthsLeft = $projectable ? max(0, round($line['remaining'] / $line['burn_rate'], 1)) : null;

                return $p + [
                    'alert_level' => $this->alertLevel($p),
                    'burn_rate_3m' => $line['burn_rate'],
                    'remaining_year_available' => $isCurrentYear || $year > $today->year ? $line['remaining'] : null,
                    'months_to_exhaustion' => $monthsLeft,
                    'projected_exhaustion_month' => $monthsLeft === null ? null
                        : Carbon::create($today->year, $today->month, 1)->addMonthsNoOverflow((int) floor($monthsLeft))->format('Y-m'),
                    'pending_documents' => $documents->values()->all(),
                    'pending_docs_count' => $documents->count(),
                    'pending_docs_amount' => $pendingAmount,
                    // Los documentos en trámite ya apartan presupuesto; esto es lo que quedaría si se rechazan.
                    'available_if_rejected' => round($p['available'] + $pendingAmount, 2),
                    'approved_exceptions' => $exceptionKeys->get($this->monthKey($p), 0),
                ];
            })
            ->filter(fn ($p) => ($p['progress_pct'] !== null && $p['progress_pct'] >= $threshold - 0.00001)
                || $p['available'] < 0 || $p['alert_level'] === 100 || $p['approved_exceptions'] > 0);

        return $this->budget->describe($rows->values())
            ->sortBy([['available', 'asc'], ['progress_pct', 'desc'], ['company_name', 'asc'], ['cost_center_code', 'asc']])
            ->values();
    }

    /** Excepciones presupuestales visibles para el usuario en el periodo. */
    public function exceptions(User $user, array $params, Collection $centerIds): Collection
    {
        $query = BudgetException::query()
            ->with(['requester:id,name', 'decider:id,name', 'costCenter:id,code,name,company_id', 'costCenter.company:id,name', 'expenseCategory:id,code,name', 'budgetCedula:id,name'])
            ->whereBetween('requested_at', [Carbon::parse($params['exceptions_from'])->startOfDay(), Carbon::parse($params['exceptions_to'])->endOfDay()])
            ->when($params['exception_status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->where(function ($scope) use ($user, $centerIds) {
                $scope->whereIn('cost_center_id', $centerIds);
                // Quien solicita siempre ve sus propias solicitudes, salvo que filtre otros centros.
                if (! $user->hasRole(BudgetVsActualReport::FULL_SCOPE_ROLES)) {
                    $scope->orWhere('requested_by', $user->id);
                }
            })
            ->latest('requested_at');

        $orders = \App\Models\DirectPurchaseOrder::query()
            ->whereIn('id', (clone $query)->where('document_type', 'direct_purchase_order')->pluck('document_id'))
            ->pluck('folio', 'id');

        return $query->get()->map(fn (BudgetException $e) => [
            'id' => $e->id,
            'document_type' => $e->document_type === 'direct_purchase_order' ? 'OCD' : $e->document_type,
            'document_folio' => $orders[$e->document_id] ?? '#'.$e->document_id,
            'document_url' => $e->document_type === 'direct_purchase_order' ? route('direct-purchase-orders.show', $e->document_id) : null,
            'document_line_id' => $e->document_line_id,
            'company_name' => $e->costCenter?->company?->name,
            'cost_center' => trim($e->costCenter?->code.' · '.$e->costCenter?->name, ' ·'),
            'budget_line' => trim($e->expenseCategory?->code.' · '.$e->expenseCategory?->name, ' ·'),
            'budget_cedula' => $e->budgetCedula?->name,
            'application_month' => $e->application_month,
            'line_amount' => round((float) $e->line_amount, 2),
            'available_at_request' => round((float) $e->available_at_request, 2),
            'requested_excess' => round((float) $e->requested_excess, 2),
            'approved_excess' => $e->approved_excess === null ? null : round((float) $e->approved_excess, 2),
            'reason' => $e->reason,
            'requester' => $e->requester?->name,
            'decider' => $e->decider?->name,
            'decision_comment' => $e->decision_comment,
            'status' => $e->status,
            'requested_at' => $e->requested_at?->toIso8601String(),
            'decided_at' => $e->decided_at?->toIso8601String(),
            // Una excepción aprobada debe tener autorizador, motivo y sello de tiempo.
            'complete' => $e->status !== 'APPROVED' || ($e->decided_by && $e->decided_at && trim((string) $e->reason) !== ''),
        ]);
    }

    private function alertLevel(array $p): ?int
    {
        if ($p['progress_pct'] === null) {
            return $p['consumed_total'] > 0.005 ? 100 : null;
        }

        foreach (array_reverse(self::THRESHOLDS) as $threshold) {
            if ($p['progress_pct'] >= $threshold / 100 - 0.00001) {
                return $threshold;
            }
        }

        return null;
    }

    /** Cotizaciones en aprobación y OCD pendientes de autorizar: ya apartan presupuesto del renglón. */
    private function pendingDocuments(Collection $positions): Collection
    {
        $months = $positions->map(fn ($p) => sprintf('%04d-%02d', $p['fiscal_year'], $p['month']))->unique()->values();

        return BudgetCommitment::query()
            ->with(['quotationSummary:id,rfq_id,requisition_id,approval_status', 'quotationSummary.rfq:id,folio', 'quotationSummary.requisition:id,folio', 'directPurchaseOrder:id,folio,status'])
            ->where('status', 'COMMITTED')
            ->whereIn('cost_center_id', $positions->pluck('cost_center_id')->unique())
            ->whereIn('expense_category_id', $positions->pluck('expense_category_id')->unique())
            ->whereIn('application_month', $months)
            ->where(fn ($q) => $q
                ->whereHas('quotationSummary', fn ($s) => $s->where('approval_status', 'pending'))
                ->orWhereHas('directPurchaseOrder', fn ($o) => $o->where('status', 'PENDING_APPROVAL')))
            ->get()
            ->map(fn (BudgetCommitment $c) => [
                'key' => $this->monthKey(['cost_center_id' => $c->cost_center_id, 'application_month' => $c->application_month, 'expense_category_id' => $c->expense_category_id, 'budget_cedula_id' => $c->budget_cedula_id]),
                'type' => $c->quotation_summary_id ? 'Cotización en aprobación' : 'OCD por autorizar',
                'folio' => $c->getOrderFolio() ?? $c->quotationSummary?->requisition?->folio,
                'url' => $this->budget->documentUrl($c),
                'amount' => round((float) $c->committed_amount - (float) $c->consumed_amount, 2),
            ])
            ->groupBy('key')
            ->map(fn (Collection $docs) => $docs->map(fn ($doc) => collect($doc)->except('key')->all()));
    }

    /** Número de excepciones aprobadas en el periodo por renglón mensual. */
    private function approvedExceptionKeys(Collection $positions, array $params): Collection
    {
        return BudgetException::query()
            ->where('status', 'APPROVED')
            ->whereIn('cost_center_id', $positions->pluck('cost_center_id')->unique())
            ->whereBetween('decided_at', [Carbon::parse($params['exceptions_from'])->startOfDay(), Carbon::parse($params['exceptions_to'])->endOfDay()])
            ->get(['cost_center_id', 'application_month', 'expense_category_id', 'budget_cedula_id'])
            ->countBy(fn ($e) => $this->monthKey($e->only(['cost_center_id', 'application_month', 'expense_category_id', 'budget_cedula_id'])));
    }

    private function allowedCenterIds(User $user, array $params): Collection
    {
        return $this->visibleCostCenters($user)
            ->when(! empty($params['company_ids']), fn ($q) => $q->whereIn('company_id', $params['company_ids']))
            ->when(! empty($params['cost_center_ids']), fn ($q) => $q->whereIn('id', $params['cost_center_ids']))
            ->when(! empty($params['responsible_user_id']), fn ($q) => $q->where('responsible_user_id', $params['responsible_user_id']))
            ->pluck('id');
    }

    /** Centro + cuenta + subcuenta (todos los meses del renglón). */
    private function lineKey(array $row): string
    {
        return implode('|', [$row['cost_center_id'], $row['expense_category_id'], $row['budget_cedula_id'] ?? 'null']);
    }

    /** Centro + mes de aplicación + cuenta + subcuenta (un renglón mensual). */
    private function monthKey(array $row): string
    {
        $month = $row['application_month'] ?? sprintf('%04d-%02d', $row['fiscal_year'], $row['month']);

        return implode('|', [(int) $row['cost_center_id'], $month, (int) $row['expense_category_id'], $row['budget_cedula_id'] ? (int) $row['budget_cedula_id'] : 'null']);
    }
}
