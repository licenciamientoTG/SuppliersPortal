<?php

namespace App\Reports\Fiscal;

use App\Models\CfdiValidationLog;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * RF-01 · Bitácora de validación de CFDI. Una fila por intento de carga
 * (aceptado o rechazado). Ver docs/reportes/RF-01_mapeo_y_huecos.md.
 */
class CfdiValidationLogReport
{
    /** Roles que ven todo el universo de sus empresas asignadas (o todas si no tienen). */
    public const FULL_SCOPE_ROLES = ['superadmin', 'general_director', 'accounting'];

    /** null = sin restricción (ve todas); [] = no ve ninguna. */
    public function visibleCompanyIds(User $user): ?array
    {
        if ($user->hasRole('superadmin')) {
            return null;
        }

        if (! $user->hasRole(self::FULL_SCOPE_ROLES)) {
            return [];
        }

        $ids = $user->companies()->pluck('companies.id')->all();

        return empty($ids) ? null : $ids;
    }

    /**
     * @param  array{result: ?string, rejection_code: array<string>, supplier_id: ?int, date_from: ?string, date_to: ?string}  $params
     * @return array{rows: Collection, kpis: array}
     */
    public function build(User $user, array $params): array
    {
        $query = CfdiValidationLog::query()->with(['company', 'supplier', 'user', 'rejectionCode']);

        $visibleCompanyIds = $this->visibleCompanyIds($user);
        if ($visibleCompanyIds !== null) {
            $query->whereIn('company_id', $visibleCompanyIds);
        }

        $this->applyFilters($query, $params);

        $rows = $this->withAttemptNo($query->get());

        return ['rows' => $rows, 'kpis' => $this->kpis($rows)];
    }

    /** Vista del proveedor: solo sus propios intentos, sin alcance por empresa. */
    public function buildForSupplier(Supplier $supplier, array $params): array
    {
        $query = CfdiValidationLog::query()
            ->with(['company', 'rejectionCode'])
            ->where('supplier_id', $supplier->id);

        $this->applyFilters($query, $params);

        $rows = $this->withAttemptNo($query->get());

        return ['rows' => $rows, 'kpis' => $this->kpis($rows)];
    }

    private function applyFilters(Builder $query, array $params): void
    {
        if (! empty($params['result'])) {
            $query->where('result', $params['result']);
        }
        if (! empty($params['rejection_code'])) {
            $query->whereIn('rejection_code', (array) $params['rejection_code']);
        }
        if (! empty($params['supplier_id'])) {
            $query->where('supplier_id', $params['supplier_id']);
        }
        if (! empty($params['date_from'])) {
            $query->where('attempted_at', '>=', Carbon::parse($params['date_from'])->startOfDay());
        }
        if (! empty($params['date_to'])) {
            $query->where('attempted_at', '<=', Carbon::parse($params['date_to'])->endOfDay());
        }
    }

    /**
     * attempt_no = ROW_NUMBER() OVER (PARTITION BY uuid ORDER BY attempted_at), calculado en
     * PHP (no persistido) para que nunca quede desfasado. Las filas sin UUID (XML_MALFORMADO)
     * no tienen grupo de reintentos: cada una es su propio intento 1.
     */
    private function withAttemptNo(Collection $logs): Collection
    {
        return $logs
            ->groupBy(fn (CfdiValidationLog $log) => $log->uuid ?? 'no-uuid-'.$log->id)
            ->flatMap(function (Collection $group) {
                return $group->sortBy('attempted_at')->values()->map(function (CfdiValidationLog $log, int $index) {
                    $log->attempt_no = $index + 1;

                    return $log;
                });
            })
            ->sortByDesc('attempted_at')
            ->values();
    }

    private function kpis(Collection $rows): array
    {
        $uuidGroups = $rows->filter(fn (CfdiValidationLog $log) => $log->uuid !== null)->groupBy('uuid');

        $firstAttemptRejected = $uuidGroups->filter(
            fn (Collection $group) => optional($group->firstWhere('attempt_no', 1))->result === CfdiValidationLog::RESULT_REJECTED
        )->count();

        $topRejectionCode = $rows->where('result', CfdiValidationLog::RESULT_REJECTED)
            ->groupBy('rejection_code')
            ->sortByDesc(fn (Collection $group) => $group->count())
            ->keys()
            ->first();

        return [
            'total_attempts' => $rows->count(),
            'first_attempt_rejection_rate' => $uuidGroups->isEmpty()
                ? 0.0
                : round($firstAttemptRejected / $uuidGroups->count() * 100, 2),
            'top_rejection_code' => $topRejectionCode,
            'average_retries' => $uuidGroups->isEmpty()
                ? 0.0
                : round($uuidGroups->avg(fn (Collection $group) => $group->count() - 1), 2),
        ];
    }
}
