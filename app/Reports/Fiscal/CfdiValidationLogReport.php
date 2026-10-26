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
        $query = $this->baseQuery();

        $visibleCompanyIds = $this->visibleCompanyIds($user);
        if ($visibleCompanyIds !== null) {
            $query->whereIn('company_id', $visibleCompanyIds);
        }

        return $this->buildFrom($query, $params);
    }

    /** Vista del proveedor: solo sus propios intentos, sin alcance por empresa. */
    public function buildForSupplier(Supplier $supplier, array $params): array
    {
        $query = $this->baseQuery()->where('supplier_id', $supplier->id);

        return $this->buildFrom($query, $params);
    }

    /**
     * attempt_no se calcula sobre TODO el universo visible (alcance de empresa/proveedor),
     * antes de aplicar resultado/código/fechas: esos son filtros de pantalla y no deben
     * alterar la posición histórica real de un intento ni las tasas de las KPI.
     */
    private function buildFrom(Builder $query, array $params): array
    {
        $rows = $this->toRowsWithAttemptNo($query->get())->filter(fn (array $row) => $this->matches($row, $params))->values();

        return ['rows' => $rows, 'kpis' => $this->kpis($rows)];
    }

    private function baseQuery(): Builder
    {
        return CfdiValidationLog::query()->with(['company', 'supplier', 'user', 'rejectionCode']);
    }

    private function matches(array $row, array $params): bool
    {
        if (! empty($params['result']) && $row['result'] !== $params['result']) {
            return false;
        }
        if (! empty($params['rejection_code']) && ! in_array($row['rejection_code'], (array) $params['rejection_code'], true)) {
            return false;
        }
        if (! empty($params['supplier_id']) && $row['supplier_id'] !== $params['supplier_id']) {
            return false;
        }
        if (! empty($params['date_from']) && (! $row['attempted_at'] || Carbon::parse($row['attempted_at'])->lt(Carbon::parse($params['date_from'])->startOfDay()))) {
            return false;
        }
        if (! empty($params['date_to']) && (! $row['attempted_at'] || Carbon::parse($row['attempted_at'])->gt(Carbon::parse($params['date_to'])->endOfDay()))) {
            return false;
        }

        return true;
    }

    /**
     * attempt_no = ROW_NUMBER() OVER (PARTITION BY uuid ORDER BY attempted_at), calculado en
     * PHP (no persistido) para que nunca quede desfasado. Las filas sin UUID (XML_MALFORMADO)
     * no tienen grupo de reintentos: cada una es su propio intento 1.
     */
    private function toRowsWithAttemptNo(Collection $logs): Collection
    {
        return $logs
            ->groupBy(fn (CfdiValidationLog $log) => $log->uuid ?? 'no-uuid-'.$log->id)
            ->flatMap(function (Collection $group) {
                return $group->sortBy('attempted_at')->values()
                    ->map(fn (CfdiValidationLog $log, int $index) => $this->row($log, $index + 1));
            })
            ->sortByDesc('attempted_at')
            ->values();
    }

    private function row(CfdiValidationLog $log, int $attemptNo): array
    {
        return [
            'id' => $log->id,
            'attempted_at' => $log->attempted_at?->toIso8601String(),
            'uuid' => $log->uuid,
            'issuer_rfc' => $log->issuer_rfc,
            'issuer_name' => $log->issuer_name,
            'receiver_rfc' => $log->receiver_rfc,
            'company_id' => $log->company_id,
            'company_name' => $log->company?->name,
            'cfdi_type' => $log->cfdi_type,
            'total' => $log->total !== null ? (float) $log->total : null,
            'currency' => $log->currency,
            'result' => $log->result,
            'rejection_code' => $log->rejection_code,
            'rule_description' => $log->rejectionCode?->rule_description,
            'supplier_message' => $log->rejectionCode?->supplier_message,
            'attempt_no' => $attemptNo,
            'channel' => $log->channel,
            'supplier_id' => $log->supplier_id,
            'supplier_name' => $log->supplier?->company_name,
            'user_id' => $log->user_id,
            'user_name' => $log->user?->name,
            'supplier_invoice_id' => $log->supplier_invoice_id,
            'xml_sha256' => $log->xml_sha256,
        ];
    }

    private function kpis(Collection $rows): array
    {
        $uuidGroups = $rows->filter(fn (array $row) => $row['uuid'] !== null)->groupBy('uuid');

        $firstAttemptRejected = $uuidGroups->filter(
            fn (Collection $group) => optional($group->firstWhere('attempt_no', 1))['result'] === CfdiValidationLog::RESULT_REJECTED
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
