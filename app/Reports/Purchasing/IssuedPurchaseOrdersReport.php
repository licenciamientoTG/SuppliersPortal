<?php

namespace App\Reports\Purchasing;

use App\Models\CostCenter;
use App\Models\DirectPurchaseOrder;
use App\Models\PurchaseOrder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * RC-02 · Órdenes de compra emitidas. Una fila por OC/OCD ya emitida: origen, moneda,
 * total_mxn, acuse del proveedor y % de compra directa.
 */
class IssuedPurchaseOrdersReport
{
    /** Roles que ven todo el universo de sus empresas asignadas (o todo si no tienen). */
    public const FULL_SCOPE_ROLES = ['superadmin', 'general_director', 'accounting', 'buyer'];

    /** Umbral de horas del KPI "% sin acuse". */
    public const ACK_SLA_HOURS = 48;

    /**
     * El acuse automático del proveedor (supplier_acknowledged_at) se desplegó en producción
     * esta fecha; antes de ella ninguna orden pudo haberlo recibido, así que el KPI de "% sin
     * acuse" se calcula solo sobre órdenes emitidas a partir de aquí.
     */
    public const ACK_TRACKING_SINCE = '2026-10-20';

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

        return $query->where('responsible_user_id', $user->id);
    }

    /**
     * @param  array{company_ids: int[], supplier_id: ?int, buyer_id: ?int, origin: string[], status: string[], date_from: ?string, date_to: ?string}  $params
     * @return array{rows: Collection, exceptions: Collection, kpis: array}
     */
    public function build(User $user, array $params): array
    {
        $visibleCenterIds = $this->visibleCostCenters($user)->pluck('id')->all();
        $isFullScope = $user->hasRole('superadmin') || $user->hasRole(self::FULL_SCOPE_ROLES);
        $visibleCompanyIds = $isFullScope && ! $user->hasRole('superadmin') ? $user->companies()->pluck('companies.id')->all() : [];

        $rows = $this->ocRows($visibleCenterIds, $visibleCompanyIds, $isFullScope)
            ->concat($this->ocdRows($visibleCenterIds, $visibleCompanyIds, $isFullScope))
            ->filter(fn (array $row) => $this->matches($row, $params))
            ->sortByDesc('issued_at')
            ->values();

        return [
            'rows' => $rows,
            'exceptions' => $rows->filter(fn (array $row) => $row['is_exception'])->values(),
            'kpis' => $this->kpis($rows),
        ];
    }

    private function ocRows(array $visibleCenterIds, array $visibleCompanyIds, bool $isFullScope): Collection
    {
        return PurchaseOrder::query()
            ->whereNotNull('issued_at')
            ->with(['supplier', 'requisition', 'creator', 'items.requisitionItem.costCenter', 'items.requisitionItem.contract'])
            ->get()
            ->filter(fn (PurchaseOrder $order) => $this->isVisible(
                $order->requisition?->company_id,
                $order->items->pluck('requisitionItem.costCenter.id'),
                $visibleCenterIds,
                $visibleCompanyIds,
                $isFullScope
            ))
            ->map(fn (PurchaseOrder $order) => $this->ocRow($order));
    }

    private function ocdRows(array $visibleCenterIds, array $visibleCompanyIds, bool $isFullScope): Collection
    {
        return DirectPurchaseOrder::query()
            ->whereNotNull('issued_at')
            ->with(['supplier', 'creator', 'items.costCenter'])
            ->get()
            ->filter(fn (DirectPurchaseOrder $order) => $this->isVisible(
                $order->primaryCompanyId(),
                $order->items->pluck('costCenter.id'),
                $visibleCenterIds,
                $visibleCompanyIds,
                $isFullScope
            ))
            ->map(fn (DirectPurchaseOrder $order) => $this->ocdRow($order));
    }

    /**
     * Una orden sin ningún centro de costo ligado (dato huérfano/histórico) no se puede acotar
     * por centro de costo — se expone solo a los roles de alcance completo (igual que RC-03 no
     * oculta validaciones en 0), y solo dentro de las empresas que de verdad tienen asignadas
     * ($visibleCompanyIds): un comprador/contador acotado a la empresa A no debe ver huérfanos
     * de la empresa B solo porque su rol es de alcance completo. Los jefes de departamento no
     * tienen alcance por empresa, así que nunca ven huérfanos (no hay nada que atribuirles).
     */
    private function isVisible(?int $companyId, Collection $centerIds, array $visibleCenterIds, array $visibleCompanyIds, bool $isFullScope): bool
    {
        $centerIds = $centerIds->filter();
        if ($centerIds->isNotEmpty()) {
            return $centerIds->intersect($visibleCenterIds)->isNotEmpty();
        }

        if (! $isFullScope) {
            return false;
        }
        if (empty($visibleCompanyIds)) {
            return true;
        }

        return $companyId !== null && in_array($companyId, $visibleCompanyIds, true);
    }

    private function ocRow(PurchaseOrder $order): array
    {
        $centerIds = $order->items->pluck('requisitionItem.costCenter.id')->filter()->unique();
        $contractIds = $order->items->pluck('requisitionItem.contract_id')->filter()->unique();

        return $this->row($order, 'OC', $order->source_type === 'contract' ? 'CONTRATO' : 'REQUISICION', [
            'company_id' => $order->requisition?->company_id,
            'cost_center_label' => $this->costCenterLabel($order->items->pluck('requisitionItem.costCenter')->filter()->unique('id')),
            'total_mxn' => (float) $order->total,
            'enabling_contract_id' => $contractIds->count() === 1 ? $contractIds->first() : null,
            'has_cost_center' => $centerIds->isNotEmpty(),
        ]);
    }

    private function ocdRow(DirectPurchaseOrder $order): array
    {
        return $this->row($order, 'OCD', 'DIRECTA', [
            'company_id' => $order->primaryCompanyId(),
            'cost_center_label' => $this->costCenterLabel($order->items->pluck('costCenter')->filter()->unique('id')),
            'total_mxn' => $order->convertItemAmountToMxn((float) $order->total),
            'enabling_contract_id' => null,
            'has_cost_center' => $order->items->pluck('cost_center_id')->filter()->isNotEmpty(),
        ]);
    }

    private function row(PurchaseOrder|DirectPurchaseOrder $order, string $type, string $origin, array $specific): array
    {
        $issuedAt = $order->issued_at ? Carbon::parse($order->issued_at) : null;
        $ackAt = $order->supplier_acknowledged_at ? Carbon::parse($order->supplier_acknowledged_at) : null;

        return [
            'type' => $type,
            'origin' => $origin,
            'order_id' => $order->id,
            'folio' => $order->folio,
            'url' => $type === 'OC' ? route('purchase-orders.show', $order->id) : route('direct-purchase-orders.show', $order->id),
            'company_id' => $specific['company_id'] !== null ? (int) $specific['company_id'] : null,
            'supplier_id' => $order->supplier_id,
            'supplier_name' => $order->supplier?->company_name,
            'supplier_rfc' => $order->supplier?->rfc,
            'currency' => $order->currency,
            'subtotal' => (float) $order->subtotal,
            'transferred_taxes' => (float) $order->iva_amount,
            'total' => (float) $order->total,
            'total_mxn' => round($specific['total_mxn'], 2),
            'cost_center_label' => $specific['cost_center_label'],
            'enabling_contract_id' => $specific['enabling_contract_id'],
            'buyer_id' => $order->created_by,
            'buyer_name' => $order->creator?->name,
            'status' => $order->status,
            'status_label' => $order->getStatusLabel(),
            'issued_at' => $issuedAt?->toIso8601String(),
            'sent_at' => $issuedAt?->toIso8601String(),
            'supplier_ack_at' => $ackAt?->toIso8601String(),
            'hours_to_ack' => ($issuedAt && $ackAt) ? round($issuedAt->diffInMinutes($ackAt) / 60, 2) : null,
            'is_exception' => $type === 'OCD' || ! $specific['has_cost_center'],
        ];
    }

    private function costCenterLabel(Collection $centers): ?string
    {
        if ($centers->isEmpty()) {
            return null;
        }
        if ($centers->count() > 1) {
            return 'Varios';
        }

        $center = $centers->first();

        return trim(($center->code ? $center->code.' · ' : '').$center->name);
    }

    private function matches(array $row, array $params): bool
    {
        if ($params['company_ids'] && ! in_array($row['company_id'], $params['company_ids'], true)) {
            return false;
        }
        if ($params['supplier_id'] && $row['supplier_id'] !== $params['supplier_id']) {
            return false;
        }
        if ($params['buyer_id'] && $row['buyer_id'] !== $params['buyer_id']) {
            return false;
        }
        if ($params['origin'] && ! in_array($row['origin'], $params['origin'], true)) {
            return false;
        }
        if ($params['status'] && ! in_array($row['status'], $params['status'], true)) {
            return false;
        }
        if ($params['date_from'] && (! $row['issued_at'] || Carbon::parse($row['issued_at'])->lt(Carbon::parse($params['date_from'])->startOfDay()))) {
            return false;
        }

        return ! ($params['date_to'] && (! $row['issued_at'] || Carbon::parse($row['issued_at'])->gt(Carbon::parse($params['date_to'])->endOfDay())));
    }

    private function kpis(Collection $rows): array
    {
        $direct = $rows->where('origin', 'DIRECTA');
        $totalAmount = round((float) $rows->sum('total_mxn'), 2);
        $directAmount = round((float) $direct->sum('total_mxn'), 2);

        $trackingSince = Carbon::parse(self::ACK_TRACKING_SINCE);
        $eligible = $rows->filter(function (array $row) use ($trackingSince) {
            if (! $row['issued_at'] || Carbon::parse($row['issued_at'])->lt($trackingSince)) {
                return false;
            }

            return Carbon::parse($row['issued_at'])->diffInHours(now()) >= self::ACK_SLA_HOURS;
        });
        $withoutAck = $eligible->filter(fn (array $row) => $row['supplier_ack_at'] === null);

        return [
            'total_orders' => $rows->count(),
            'total_amount_mxn' => $totalAmount,
            'direct_count' => $direct->count(),
            'direct_amount_mxn' => $directAmount,
            'direct_pct_count' => $rows->isEmpty() ? 0.0 : round($direct->count() / $rows->count() * 100, 2),
            'direct_pct_amount' => $totalAmount == 0.0 ? 0.0 : round($directAmount / $totalAmount * 100, 2),
            'no_ack_over_48h_eligible' => $eligible->count(),
            'no_ack_over_48h_count' => $withoutAck->count(),
            'no_ack_over_48h_pct' => $eligible->isEmpty() ? null : round($withoutAck->count() / $eligible->count() * 100, 2),
        ];
    }
}
