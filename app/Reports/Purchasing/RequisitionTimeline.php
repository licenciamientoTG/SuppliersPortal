<?php

namespace App\Reports\Purchasing;

use App\Enum\RequisitionStatus;
use App\Models\QuotationSummary;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * RC-01 · Etapas de cada requisición a partir de requisition_status_histories.
 *
 * Cada evento de estatus abre una etapa que termina en el siguiente evento, así que
 * las etapas son contiguas. Las horas se redondean por etapa y el ciclo es la suma
 * de esas horas redondeadas: Σ horas por etapa = horas de ciclo, siempre.
 * Es la única fuente de tiempos del reporte (tabla, detalle, exportación y cuadre).
 */
class RequisitionTimeline
{
    /** Desde esta fecha el portal registra cada cambio de estatus. */
    public const HISTORY_SINCE = '2026-08-31';

    /** Estatus que detienen el reloj si ya no hay eventos posteriores. */
    public const CLOSED = ['COMPLETED', 'CANCELLED', 'REJECTED'];

    public const STAGES = [
        'DRAFT' => 'Captura',
        'PENDING' => 'Validación de Compras',
        'PAUSED' => 'Pausada por catálogo',
        'APPROVED' => 'Cotización',
        'IN_QUOTATION' => 'Cotización',
        'QUOTED' => 'Adjudicación',
        'IN_APPROVAL' => 'Autorización de cotización',
        'PENDING_BUDGET_ADJUSTMENT' => 'Ajuste presupuestal',
        'COMPLETED' => 'OC emitida',
        'CANCELLED' => 'Cancelada',
        'REJECTED' => 'Rechazada',
    ];

    private const EVENT_TYPES = ['CREATED', 'STATUS_CHANGED'];

    private const CHUNK = 1000;

    /**
     * @param  Collection<int, object{id:int, created_at:mixed}>  $requisitions
     * @return Collection<int, array{has_history: bool, steps: list<array>, total_cycle_hours: ?float, current: ?array}> por id de requisición
     */
    public function build(Collection $requisitions, ?CarbonInterface $now = null): Collection
    {
        $now = $now ? Carbon::instance($now) : now();
        $ids = $requisitions->pluck('id')->map(fn ($id) => (int) $id)->all();
        $events = $this->events($ids);
        $decisions = $this->decisions($ids);
        $since = Carbon::parse(self::HISTORY_SINCE, config('app.timezone'))->startOfDay();

        return $requisitions->mapWithKeys(function ($requisition) use ($events, $decisions, $since, $now) {
            $id = (int) $requisition->id;
            $rows = $events->get($id, collect())->values();

            if ($rows->isEmpty() || Carbon::parse($requisition->created_at)->lt($since)) {
                return [$id => ['has_history' => false, 'steps' => [], 'total_cycle_hours' => null, 'current' => null]];
            }

            $steps = $this->steps($rows, $decisions->get($id, collect()), $now);

            return [$id => [
                'has_history' => true,
                'steps' => $steps,
                'total_cycle_hours' => round(array_sum(array_column($steps, 'hours')), 1),
                'current' => end($steps) ?: null,
            ]];
        });
    }

    /** Nombre de la etapa para un estatus. */
    public static function stageName(string $status): string
    {
        return self::STAGES[$status] ?? str($status)->replace('_', ' ')->lower()->ucfirst()->toString();
    }

    private function steps(Collection $rows, Collection $decisions, Carbon $now): array
    {
        $steps = [];

        foreach ($rows as $index => $event) {
            $next = $rows->get($index + 1);
            $status = strtoupper((string) $event->to_status);
            $entered = Carbon::parse($event->occurred_at);
            $closed = $next === null && in_array($status, self::CLOSED, true);
            $exited = $next ? Carbon::parse($next->occurred_at) : ($closed ? $entered : null);
            $seconds = max(0, $entered->diffInSeconds($exited ?? $now, false));

            $steps[] = [
                'step_order' => $index + 1,
                'status' => $status,
                'step_name' => $this->name($status, $index, $next !== null),
                'entered_at' => $entered->toIso8601String(),
                'exited_at' => $exited?->toIso8601String(),
                'is_open' => $next === null && ! $closed,
                'hours' => round($seconds / 3600, 1),
                'entered_by' => $event->user_name,
                'resolved_by' => $next?->user_name,
                'exit_to' => $next ? self::stageName(strtoupper((string) $next->to_status)) : null,
                'exit_status' => $next ? strtoupper((string) $next->to_status) : null,
                'decisions' => $status === 'IN_APPROVAL'
                    ? $decisions->filter(fn ($decision) => Carbon::parse($decision['acted_at'])->betweenIncluded($entered, $exited ?? $now))->values()->all()
                    : [],
            ];
        }

        return $steps;
    }

    private function name(string $status, int $index, bool $hasNext): string
    {
        if ($status === RequisitionStatus::DRAFT->value && $index > 0) {
            return 'Corrección del requisitor';
        }
        if ($status === RequisitionStatus::REJECTED->value && $hasNext) {
            return 'Devuelta al requisitor';
        }

        return self::stageName($status);
    }

    /** @return Collection<int, Collection> eventos por requisición, en orden */
    private function events(array $ids): Collection
    {
        return collect(array_chunk($ids, self::CHUNK))->flatMap(fn (array $chunk) => DB::table('requisition_status_histories as h')
            ->leftJoin('users as u', 'u.id', '=', 'h.user_id')
            ->whereIn('h.requisition_id', $chunk)
            ->whereIn('h.event_type', self::EVENT_TYPES)
            ->orderBy('h.occurred_at')->orderBy('h.id')
            ->get(['h.requisition_id', 'h.to_status', 'h.occurred_at', 'u.name as user_name']))
            ->groupBy(fn ($event) => (int) $event->requisition_id);
    }

    /** @return Collection<int, Collection> decisiones de autorización de cotizaciones por requisición */
    private function decisions(array $ids): Collection
    {
        $type = (new QuotationSummary)->getMorphClass();

        return collect(array_chunk($ids, self::CHUNK))->flatMap(fn (array $chunk) => DB::table('approval_decisions as d')
            ->join('quotation_summaries as qs', 'qs.id', '=', 'd.approvable_id')
            ->leftJoin('users as principal', 'principal.id', '=', 'd.assigned_principal_user_id')
            ->leftJoin('users as actor', 'actor.id', '=', 'd.acted_by_user_id')
            ->where('d.approvable_type', $type)
            ->whereIn('qs.requisition_id', $chunk)
            ->orderBy('d.acted_at')->orderBy('d.id')
            ->get(['qs.requisition_id', 'd.action', 'd.acted_at', 'd.comments', 'principal.name as principal_name', 'actor.name as actor_name']))
            ->groupBy(fn ($decision) => (int) $decision->requisition_id)
            ->map(fn (Collection $rows) => $rows->map(fn ($row) => [
                'action' => $row->action,
                'acted_at' => Carbon::parse($row->acted_at)->toIso8601String(),
                'comments' => $row->comments,
                'principal' => $row->principal_name,
                'actor' => $row->actor_name,
            ]));
    }
}
