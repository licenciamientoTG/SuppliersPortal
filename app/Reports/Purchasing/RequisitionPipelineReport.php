<?php

namespace App\Reports\Purchasing;

use App\Enum\RequisitionStatus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * RC-01 · Pipeline de requisiciones y tiempos de ciclo. Una fila por requisición con
 * la etapa en la que está, con quién y cuántas horas lleva; los tiempos salen de
 * RequisitionTimeline.
 */
class RequisitionPipelineReport
{
    /** Roles que ven todas las requisiciones de sus empresas asignadas (o todas si no tienen). */
    public const FULL_SCOPE_ROLES = ['superadmin', 'general_director', 'accounting', 'buyer'];

    /** Estatus fuera del filtro por defecto ("todas menos cerradas"). */
    public const DEFAULT_EXCLUDED = ['COMPLETED', 'CANCELLED'];

    public const PURCHASING_QUEUE = 'Compras (cola)';

    private const CHUNK = 1000;

    public function __construct(private readonly RequisitionTimeline $timeline) {}

    /** Requisiciones que el usuario puede consultar; el alcance se aplica siempre en servidor. */
    public function visibleRequisitions(User $user): Builder
    {
        $query = DB::table('requisitions as r')->whereNull('r.deleted_at');

        if ($user->hasRole('superadmin')) {
            return $query;
        }
        if ($user->hasRole(self::FULL_SCOPE_ROLES)) {
            $companyIds = $user->companies()->pluck('companies.id');

            return $companyIds->isEmpty() ? $query : $query->whereIn('r.company_id', $companyIds);
        }

        // Jefes de departamento y demás: sus centros de costo o su departamento.
        return $query->where(function (Builder $scope) use ($user) {
            $scope->whereExists(fn (Builder $items) => $items->from('requisition_items as ri')
                ->join('cost_centers as cc', 'cc.id', '=', 'ri.cost_center_id')
                ->whereColumn('ri.requisition_id', 'r.id')
                ->where('cc.responsible_user_id', $user->id))
                ->orWhereExists(fn (Builder $departments) => $departments->from('departments as d')
                    ->whereColumn('d.id', 'r.department_id')
                    ->where('d.manager_user_id', $user->id));
        });
    }

    /**
     * @param  array{statuses: string[], pending_approver_id: ?int, older_than_days: int, cost_center_ids: int[], amount_from: ?float, amount_to: ?float, company_ids: int[], date_from: string, date_to: string}  $params
     * @return array{rows: Collection, kpis: array, stages: Collection, approvers: Collection, timelines: Collection}
     */
    public function build(User $user, array $params, ?Carbon $now = null): array
    {
        $now ??= now();
        $requisitions = $this->baseQuery($user, $params)->get([
            'r.id', 'r.folio', 'r.status', 'r.created_at', 'r.company_id', 'r.rejection_reason', 'r.cancellation_reason',
            'company.name as company_name', 'requester.name as requester_name', 'requester.id as requester_id',
        ]);
        $timelines = $this->timeline->build($requisitions, $now);
        $details = $this->details($requisitions->pluck('id')->map(fn ($id) => (int) $id)->all());

        $rows = $requisitions
            ->map(fn ($requisition) => $this->row($requisition, $timelines->get((int) $requisition->id), $details, $now))
            ->filter(fn (array $row) => $this->matches($row, $params))
            ->sort(fn (array $a, array $b) => [$b['hours_in_current_step'] ?? -1, $b['age_days']] <=> [$a['hours_in_current_step'] ?? -1, $a['age_days']])
            ->values();
        $timelines = $timelines->only($rows->pluck('id')->all());

        return [
            'rows' => $rows,
            'kpis' => $this->kpis($rows),
            'stages' => $this->stageSummary($timelines),
            'approvers' => $this->approverSummary($timelines),
            'timelines' => $timelines,
        ];
    }

    /** Etapas de una requisición, solo si está dentro del alcance del usuario. */
    public function steps(User $user, int $requisitionId): ?array
    {
        $requisition = $this->visibleRequisitions($user)->where('r.id', $requisitionId)->first(['r.id', 'r.created_at']);

        return $requisition ? $this->timeline->build(collect([$requisition]))->get($requisitionId) : null;
    }

    private function baseQuery(User $user, array $params): Builder
    {
        $timezone = config('app.timezone');

        return $this->visibleRequisitions($user)
            ->join('companies as company', 'company.id', '=', 'r.company_id')
            ->leftJoin('users as requester', 'requester.id', '=', 'r.requested_by')
            ->whereIn('r.status', $params['statuses'])
            ->where('r.created_at', '>=', Carbon::parse($params['date_from'], $timezone)->startOfDay())
            ->where('r.created_at', '<=', Carbon::parse($params['date_to'], $timezone)->endOfDay())
            ->when($params['company_ids'], fn (Builder $q, array $ids) => $q->whereIn('r.company_id', $ids))
            ->when($params['cost_center_ids'], fn (Builder $q, array $ids) => $q->whereExists(fn (Builder $items) => $items->from('requisition_items as fi')
                ->whereColumn('fi.requisition_id', 'r.id')->whereIn('fi.cost_center_id', $ids)))
            // Excluye borradores que nunca se enviaron a Compras.
            ->where(fn (Builder $q) => $q->where('r.status', '!=', RequisitionStatus::DRAFT->value)
                ->orWhereExists(fn (Builder $sent) => $sent->from('requisition_status_histories as sh')
                    ->whereColumn('sh.requisition_id', 'r.id')->where('sh.to_status', RequisitionStatus::PENDING->value)));
    }

    private function row(object $requisition, array $timeline, array $details, Carbon $now): array
    {
        $id = (int) $requisition->id;
        $status = strtoupper((string) $requisition->status);
        $current = $timeline['current'];
        $open = ! in_array($status, RequisitionTimeline::CLOSED, true);
        [$approver, $approverIds] = $this->pendingApprover($status, $requisition, $details, $id);
        $centers = $details['centers']->get($id, collect());

        return [
            'id' => $id,
            'folio' => $requisition->folio,
            'url' => route('requisitions.show', $id),
            'company_name' => $requisition->company_name,
            'created_at' => Carbon::parse($requisition->created_at)->toIso8601String(),
            'age_days' => (int) floor(Carbon::parse($requisition->created_at)->diffInDays($now)),
            'requester_name' => $requisition->requester_name,
            'cost_centers' => $centers->pluck('label')->implode(', '),
            'cost_center_ids' => $centers->pluck('id')->all(),
            'estimated_amount' => $details['amounts']->has($id) ? round((float) $details['amounts']->get($id), 2) : null,
            'is_repse' => $details['repse']->contains($id),
            'status' => $status,
            'status_label' => RequisitionStatus::tryFrom($status)?->label() ?? $status,
            'status_badge' => RequisitionStatus::tryFrom($status)?->badgeClass() ?? 'secondary',
            'current_step' => $current['step_name'] ?? RequisitionTimeline::stageName($status),
            'pending_approver' => $approver,
            'pending_approver_ids' => $approverIds,
            'hours_in_current_step' => $timeline['has_history'] ? ($open && $current['is_open'] ? $current['hours'] : 0.0) : null,
            'total_cycle_hours' => $timeline['total_cycle_hours'],
            'has_history' => $timeline['has_history'],
            'step_count' => count($timeline['steps']),
            'outcome_reason' => match ($status) {
                'REJECTED' => $requisition->rejection_reason,
                'CANCELLED' => $requisition->cancellation_reason,
                default => null,
            },
            'po_folios' => $details['orders']->get($id, collect())->implode(', '),
        ];
    }

    /** @return array{0: string, 1: int[]} nombre visible y usuarios con la requisición en su bandeja */
    private function pendingApprover(string $status, object $requisition, array $details, int $id): array
    {
        return match ($status) {
            'IN_APPROVAL' => [
                $details['approvers']->get($id, collect())->pluck('name')->unique()->implode(', ') ?: 'Sin aprobador asignado',
                $details['approvers']->get($id, collect())->pluck('id')->map(fn ($v) => (int) $v)->unique()->values()->all(),
            ],
            'PENDING', 'APPROVED', 'IN_QUOTATION', 'QUOTED' => [self::PURCHASING_QUEUE, []],
            'PAUSED' => ['Compras · catálogo', []],
            'DRAFT' => [$requisition->requester_name ? 'Requisitor: '.$requisition->requester_name : 'Requisitor', $requisition->requester_id ? [(int) $requisition->requester_id] : []],
            'PENDING_BUDGET_ADJUSTMENT' => [
                $details['centers']->get($id, collect())->pluck('responsible_name')->filter()->unique()->implode(', ') ?: 'Responsable del centro de costo',
                $details['centers']->get($id, collect())->pluck('responsible_id')->filter()->map(fn ($v) => (int) $v)->unique()->values()->all(),
            ],
            default => ['—', []],
        };
    }

    private function matches(array $row, array $params): bool
    {
        if ($params['pending_approver_id'] && ! in_array($params['pending_approver_id'], $row['pending_approver_ids'], true)) {
            return false;
        }
        if ($params['older_than_days'] > 0 && $row['age_days'] < $params['older_than_days']) {
            return false;
        }
        if ($params['amount_from'] !== null && ($row['estimated_amount'] === null || $row['estimated_amount'] < $params['amount_from'])) {
            return false;
        }

        return ! ($params['amount_to'] !== null && ($row['estimated_amount'] === null || $row['estimated_amount'] > $params['amount_to']));
    }

    /** Centros, importes cotizados, REPSE, aprobadores pendientes y OC por requisición. */
    private function details(array $ids): array
    {
        $load = fn (callable $query) => collect(array_chunk($ids, self::CHUNK))->flatMap(fn (array $chunk) => $query($chunk)->get());

        $centers = $load(fn (array $chunk) => DB::table('requisition_items as ri')
            ->join('cost_centers as cc', 'cc.id', '=', 'ri.cost_center_id')
            ->leftJoin('users as responsible', 'responsible.id', '=', 'cc.responsible_user_id')
            ->whereIn('ri.requisition_id', $chunk)->distinct()
            ->select('ri.requisition_id', 'cc.id', 'cc.code', 'cc.name', 'responsible.id as responsible_id', 'responsible.name as responsible_name'))
            ->groupBy(fn ($row) => (int) $row->requisition_id)
            ->map(fn (Collection $rows) => $rows->unique('id')->sortBy('code')->map(fn ($row) => [
                'id' => (int) $row->id,
                'label' => trim(($row->code ? $row->code.' · ' : '').$row->name),
                'responsible_id' => $row->responsible_id,
                'responsible_name' => $row->responsible_name,
            ])->values());

        // Importe: lo cotizado en cotizaciones vigentes (la requisición no lleva precio).
        $amounts = $load(fn (array $chunk) => DB::table('quotation_summaries')
            ->whereIn('requisition_id', $chunk)->whereNull('deleted_at')
            ->whereIn('approval_status', ['pending', 'approved', 'partially_approved'])
            ->groupBy('requisition_id')->select('requisition_id', DB::raw('SUM(total) as amount')))
            ->mapWithKeys(fn ($row) => [(int) $row->requisition_id => (float) $row->amount]);

        // REPSE: alguna partida de la categoría de servicios, igual que la validación en recepciones.
        $repse = $load(fn (array $chunk) => DB::table('requisition_items as ri')
            ->join('expense_categories as ec', 'ec.id', '=', 'ri.expense_category_id')
            ->whereIn('ri.requisition_id', $chunk)->where('ec.code', 'SER')
            ->distinct()->select('ri.requisition_id'))
            ->map(fn ($row) => (int) $row->requisition_id)->unique()->values();

        // Mismo criterio que la bandeja de autorizaciones: aprobador vigente de cotizaciones pendientes.
        $approvers = $load(fn (array $chunk) => DB::table('quotation_summaries as qs')
            ->join('users as approver', 'approver.id', '=', 'qs.current_approver_user_id')
            ->whereIn('qs.requisition_id', $chunk)->whereNull('qs.deleted_at')->where('qs.approval_status', 'pending')
            ->select('qs.requisition_id', 'approver.id', 'approver.name'))
            ->groupBy(fn ($row) => (int) $row->requisition_id);

        $orders = $load(fn (array $chunk) => DB::table('purchase_orders')
            ->whereIn('requisition_id', $chunk)->whereNull('deleted_at')->orderBy('folio')
            ->select('requisition_id', 'folio'))
            ->groupBy(fn ($row) => (int) $row->requisition_id)
            ->map(fn (Collection $rows) => $rows->pluck('folio')->filter()->unique()->values());

        return compact('centers', 'amounts', 'repse', 'approvers', 'orders');
    }

    private function kpis(Collection $rows): array
    {
        $open = $rows->filter(fn (array $row) => ! in_array($row['status'], RequisitionTimeline::CLOSED, true));
        $timed = $open->whereNotNull('hours_in_current_step');

        return [
            'requisitions' => $rows->count(),
            'open' => $open->count(),
            'without_history' => $rows->where('has_history', false)->count(),
            'avg_hours_in_current_step' => $timed->isEmpty() ? null : round((float) $timed->avg('hours_in_current_step'), 1),
            'top_pending_approvers' => $open->countBy('pending_approver')->sortDesc()->take(5)
                ->map(fn (int $count, string $name) => ['name' => $name, 'count' => $count])->values()->all(),
        ];
    }

    /** Horas promedio y mediana por etapa, solo con requisiciones con historial. */
    private function stageSummary(Collection $timelines): Collection
    {
        return $this->summarize($timelines->flatMap(fn (array $timeline) => $timeline['steps'])
            ->reject(fn (array $step) => in_array($step['status'], RequisitionTimeline::CLOSED, true) && $step['exited_at'] === $step['entered_at']), 'step_name');
    }

    /** Horas promedio y mediana por quien resolvió la etapa. */
    private function approverSummary(Collection $timelines): Collection
    {
        return $this->summarize($timelines->flatMap(fn (array $timeline) => $timeline['steps'])
            ->filter(fn (array $step) => $step['resolved_by'] !== null), 'resolved_by');
    }

    private function summarize(Collection $steps, string $key): Collection
    {
        return $steps->groupBy($key)->map(fn (Collection $group, string $name) => [
            'name' => $name,
            'steps' => $group->count(),
            'avg_hours' => round((float) $group->avg('hours'), 1),
            'median_hours' => round((float) $group->median('hours'), 1),
        ])->sortByDesc('avg_hours')->values();
    }
}
