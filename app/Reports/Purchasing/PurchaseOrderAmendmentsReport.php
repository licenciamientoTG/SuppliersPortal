<?php

namespace App\Reports\Purchasing;

use App\Models\DirectPurchaseOrder;
use App\Models\DirectPurchaseOrderApproval;
use App\Models\PurchaseOrder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * RC-04 · Modificaciones, adendas y cancelaciones de OC. Una fila por evento (no por
 * orden): cancelación (OC/OCD), rechazo (OC), devolución a revisión (OCD) y reemisión
 * tras una revisión (OCD). Ver docs/reportes/RC-04_mapeo_y_huecos.md: no existe hoy un
 * snapshot de precio/cantidad/proveedor antes de una edición, así que ningún evento
 * trae un "monto antes" ni un diff de campos — solo el monto actual de la orden.
 */
class PurchaseOrderAmendmentsReport
{
    public function __construct(private readonly IssuedPurchaseOrdersReport $scope) {}

    /** Centros de costo que el usuario puede consultar; mismo alcance que RC-02. */
    public function visibleCostCenters(User $user)
    {
        return $this->scope->visibleCostCenters($user);
    }

    /**
     * @param  array{company_ids: int[], supplier_id: ?int, buyer_id: ?int, order_type: string[], event_type: string[], date_from: ?string, date_to: ?string}  $params
     * @return array{rows: Collection, kpis: array}
     */
    public function build(User $user, array $params): array
    {
        $visibleCenterIds = $this->visibleCostCenters($user)->pluck('id')->all();
        $isFullScope = $user->hasRole('superadmin') || $user->hasRole(IssuedPurchaseOrdersReport::FULL_SCOPE_ROLES);
        $visibleCompanyIds = $isFullScope && ! $user->hasRole('superadmin') ? $user->companies()->pluck('companies.id')->all() : [];

        $rows = $this->cancelledOcRows($visibleCenterIds, $visibleCompanyIds, $isFullScope)
            ->concat($this->cancelledOcdRows($visibleCenterIds, $visibleCompanyIds, $isFullScope))
            ->concat($this->rejectedOcRows($visibleCenterIds, $visibleCompanyIds, $isFullScope))
            ->concat($this->returnedToRevisionRows($visibleCenterIds, $visibleCompanyIds, $isFullScope))
            ->concat($this->reissuedAfterRevisionRows($visibleCenterIds, $visibleCompanyIds, $isFullScope))
            ->filter(fn (array $row) => $this->matches($row, $params))
            ->sortByDesc('occurred_at')
            ->values();

        return [
            'rows' => $rows,
            'kpis' => $this->kpis($rows),
        ];
    }

    private function cancelledOcRows(array $visibleCenterIds, array $visibleCompanyIds, bool $isFullScope): Collection
    {
        return PurchaseOrder::query()
            ->whereNotNull('cancelled_at')
            ->with(['supplier', 'requisition', 'creator', 'canceller', 'items.requisitionItem.costCenter'])
            ->get()
            ->filter(fn (PurchaseOrder $order) => $this->isVisible(
                $order->requisition?->company_id,
                $order->items->pluck('requisitionItem.costCenter.id'),
                $visibleCenterIds,
                $visibleCompanyIds,
                $isFullScope
            ))
            ->map(fn (PurchaseOrder $order) => $this->row(
                $order,
                'OC',
                'CANCELLED',
                $order->requisition?->company_id,
                $this->costCenterLabel($order->items->pluck('requisitionItem.costCenter')->filter()->unique('id')),
                (float) $order->total,
                $order->cancelled_at,
                $order->canceller?->name,
                $order->cancellation_reason
            ));
    }

    private function cancelledOcdRows(array $visibleCenterIds, array $visibleCompanyIds, bool $isFullScope): Collection
    {
        return DirectPurchaseOrder::query()
            ->whereNotNull('cancelled_at')
            ->with(['supplier', 'creator', 'canceller', 'items.costCenter'])
            ->get()
            ->filter(fn (DirectPurchaseOrder $order) => $this->isVisible(
                $order->primaryCompanyId(),
                $order->items->pluck('costCenter.id'),
                $visibleCenterIds,
                $visibleCompanyIds,
                $isFullScope
            ))
            ->map(fn (DirectPurchaseOrder $order) => $this->row(
                $order,
                'OCD',
                'CANCELLED',
                $order->primaryCompanyId(),
                $this->costCenterLabel($order->items->pluck('costCenter')->filter()->unique('id')),
                $order->convertItemAmountToMxn((float) $order->total),
                $order->cancelled_at,
                $order->canceller?->name,
                $order->cancellation_reason
            ));
    }

    private function rejectedOcRows(array $visibleCenterIds, array $visibleCompanyIds, bool $isFullScope): Collection
    {
        return PurchaseOrder::query()
            ->whereNotNull('rejected_at')
            ->with(['supplier', 'requisition', 'creator', 'items.requisitionItem.costCenter', 'approvals'])
            ->get()
            ->filter(fn (PurchaseOrder $order) => $this->isVisible(
                $order->requisition?->company_id,
                $order->items->pluck('requisitionItem.costCenter.id'),
                $visibleCenterIds,
                $visibleCompanyIds,
                $isFullScope
            ))
            ->map(function (PurchaseOrder $order) {
                $rejection = $order->approvals->where('action', 'REJECTED')->sortByDesc('approved_at')->first();

                return $this->row(
                    $order,
                    'OC',
                    'REJECTED',
                    $order->requisition?->company_id,
                    $this->costCenterLabel($order->items->pluck('requisitionItem.costCenter')->filter()->unique('id')),
                    (float) $order->total,
                    $order->rejected_at,
                    $rejection?->approver?->name,
                    $rejection?->comments
                );
            });
    }

    private function returnedToRevisionRows(array $visibleCenterIds, array $visibleCompanyIds, bool $isFullScope): Collection
    {
        return DirectPurchaseOrderApproval::query()
            ->where('action', 'RETURNED')
            ->with(['approver', 'directPurchaseOrder.supplier', 'directPurchaseOrder.creator', 'directPurchaseOrder.items.costCenter'])
            ->get()
            ->filter(fn (DirectPurchaseOrderApproval $approval) => $approval->directPurchaseOrder && $this->isVisible(
                $approval->directPurchaseOrder->primaryCompanyId(),
                $approval->directPurchaseOrder->items->pluck('costCenter.id'),
                $visibleCenterIds,
                $visibleCompanyIds,
                $isFullScope
            ))
            ->map(function (DirectPurchaseOrderApproval $approval) {
                $order = $approval->directPurchaseOrder;

                return $this->row(
                    $order,
                    'OCD',
                    'RETURNED_TO_REVISION',
                    $order->primaryCompanyId(),
                    $this->costCenterLabel($order->items->pluck('costCenter')->filter()->unique('id')),
                    $order->convertItemAmountToMxn((float) $order->total),
                    $approval->approved_at,
                    $approval->approver?->name,
                    $approval->comments
                );
            });
    }

    /**
     * No se usan returned_at/approved_by del encabezado: prepareApprovalFlow() los limpia al
     * reenviar una OCD por la ruta de resolución de autorizador (ver
     * DirectPurchaseOrderController::prepareApprovalFlow(), ~línea 625), así que el encabezado
     * no es fuente confiable para detectar que hubo una reemisión. El historial de aprobaciones
     * nunca se borra, y approve() solo crea una fila APPROVED en el mismo momento en que el
     * estatus pasa a ISSUED (nunca en niveles intermedios de una cadena de aprobación), así que
     * emparejar cada RETURNED con el siguiente APPROVED del mismo registro reconstruye cada ciclo
     * de devolución/reemisión de forma confiable, incluso si hubo varios.
     */
    private function reissuedAfterRevisionRows(array $visibleCenterIds, array $visibleCompanyIds, bool $isFullScope): Collection
    {
        return DirectPurchaseOrder::query()
            ->whereHas('approvals', fn ($query) => $query->where('action', 'RETURNED'))
            ->with(['supplier', 'creator', 'items.costCenter', 'approvals.approver'])
            ->get()
            ->filter(fn (DirectPurchaseOrder $order) => $this->isVisible(
                $order->primaryCompanyId(),
                $order->items->pluck('costCenter.id'),
                $visibleCenterIds,
                $visibleCompanyIds,
                $isFullScope
            ))
            ->flatMap(fn (DirectPurchaseOrder $order) => $this->reissuancesFor($order));
    }

    private function reissuancesFor(DirectPurchaseOrder $order): Collection
    {
        $events = $order->approvals->whereIn('action', ['RETURNED', 'APPROVED', 'REJECTED'])->sortBy('approved_at')->values();

        $rows = collect();
        $awaitingReissue = false;
        foreach ($events as $event) {
            if ($event->action === 'RETURNED') {
                $awaitingReissue = true;

                continue;
            }
            if ($event->action === 'APPROVED' && $awaitingReissue) {
                $rows->push($this->row(
                    $order,
                    'OCD',
                    'REISSUED_AFTER_REVISION',
                    $order->primaryCompanyId(),
                    $this->costCenterLabel($order->items->pluck('costCenter')->filter()->unique('id')),
                    $order->convertItemAmountToMxn((float) $order->total),
                    $event->approved_at,
                    $event->approver?->name,
                    null
                ));
            }
            $awaitingReissue = false;
        }

        return $rows;
    }

    /** Ver isVisible() de IssuedPurchaseOrdersReport (RC-02): mismo criterio de alcance. */
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

    private function row(
        PurchaseOrder|DirectPurchaseOrder $order,
        string $orderType,
        string $eventType,
        ?int $companyId,
        ?string $costCenterLabel,
        float $totalMxn,
        string|Carbon|null $occurredAt,
        ?string $actorName,
        ?string $reason
    ): array {
        return [
            'order_type' => $orderType,
            'event_type' => $eventType,
            'order_id' => $order->id,
            'folio' => $order->folio,
            'url' => $orderType === 'OC' ? route('purchase-orders.show', $order->id) : route('direct-purchase-orders.show', $order->id),
            'company_id' => $companyId,
            'supplier_id' => $order->supplier_id,
            'supplier_name' => $order->supplier?->company_name,
            'cost_center_label' => $costCenterLabel,
            'buyer_id' => $order->created_by,
            'buyer_name' => $order->creator?->name,
            'occurred_at' => $occurredAt ? Carbon::parse($occurredAt)->toIso8601String() : null,
            'actor_name' => $actorName,
            'reason' => $reason,
            'total_mxn_current' => round($totalMxn, 2),
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
        if ($params['order_type'] && ! in_array($row['order_type'], $params['order_type'], true)) {
            return false;
        }
        if ($params['event_type'] && ! in_array($row['event_type'], $params['event_type'], true)) {
            return false;
        }
        if ($params['date_from'] && (! $row['occurred_at'] || Carbon::parse($row['occurred_at'])->lt(Carbon::parse($params['date_from'])->startOfDay()))) {
            return false;
        }

        return ! ($params['date_to'] && (! $row['occurred_at'] || Carbon::parse($row['occurred_at'])->gt(Carbon::parse($params['date_to'])->endOfDay())));
    }

    private function kpis(Collection $rows): array
    {
        return [
            'total_events' => $rows->count(),
            'by_event_type' => $rows->groupBy('event_type')->map(fn ($group) => $group->count())->all(),
            'by_order_type' => $rows->groupBy('order_type')->map(fn ($group) => $group->count())->all(),
        ];
    }
}
