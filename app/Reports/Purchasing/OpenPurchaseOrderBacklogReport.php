<?php

namespace App\Reports\Purchasing;

use App\Models\CostCenter;
use App\Models\DirectPurchaseOrder;
use App\Models\PurchaseOrder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * RC-03 · Backlog de órdenes de compra abiertas. Una fila por renglón de OC/OCD con
 * saldo pendiente de recibir: importe pendiente (sin IVA, mismo criterio que el
 * comprometido de RP-01), atraso contra la fecha prometida y marca de OC "zombi".
 */
class OpenPurchaseOrderBacklogReport
{
    /** Estatus en los que la orden aún puede recibir mercancía (igual que *::canBeReceived()). */
    public const RECEIVABLE_STATUSES = ['ISSUED', 'PARTIALLY_RECEIVED', 'DELIVERED_PENDING_RECEPTION'];

    public const DEFAULT_ZOMBIE_DAYS = 90;

    /** Roles que ven todo el backlog de sus empresas asignadas (o todo si no tienen). */
    public const FULL_SCOPE_ROLES = ['superadmin', 'general_director', 'accounting', 'buyer'];

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
     * @param  array{company_ids: int[], supplier_id: ?int, cost_center_ids: int[], only_late: bool, only_zombie: bool, zombie_days: int, as_of_date: string}  $params
     * @return array{rows: Collection, kpis: array, reconciliation: array}
     */
    public function build(User $user, array $params): array
    {
        $asOf = Carbon::parse($params['as_of_date'])->setTimezone(config('app.timezone'))->endOfDay();
        $visibleCenterIds = $this->visibleCostCenters($user)->pluck('id')->all();

        $rows = $this->ocItems($visibleCenterIds, $params)
            ->concat($this->ocdItems($visibleCenterIds, $params))
            ->map(fn (array $item) => $this->row($item, $asOf, (int) $params['zombie_days']))
            ->filter(fn (array $row) => $this->matches($row, $params))
            ->sort(fn (array $a, array $b) => [$b['is_zombie'], $b['days_late'] ?? -1, $b['pending_amount']] <=> [$a['is_zombie'], $a['days_late'] ?? -1, $a['pending_amount']])
            ->values();

        return [
            'rows' => $rows,
            'kpis' => $this->kpis($rows),
            'reconciliation' => $this->reconciliation(),
        ];
    }

    private function ocItems(array $visibleCenterIds, array $params): Collection
    {
        return DB::table('purchase_order_items as poi')
            ->join('purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id')
            ->join('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->leftJoin('requisition_items as ri', 'ri.id', '=', 'poi.requisition_item_id')
            ->leftJoin('cost_centers as cc', 'cc.id', '=', 'ri.cost_center_id')
            ->leftJoin('expense_categories as ec', 'ec.id', '=', 'ri.expense_category_id')
            ->leftJoin('budget_cedulas as bc', 'bc.id', '=', 'ri.budget_cedula_id')
            ->whereIn('po.status', self::RECEIVABLE_STATUSES)
            ->whereNull('po.deleted_at')
            ->whereColumn('poi.quantity', '>', 'poi.quantity_received')
            ->whereIn('cc.id', $visibleCenterIds)
            ->when($params['company_ids'], fn ($q, $ids) => $q->whereIn('cc.company_id', $ids))
            ->when($params['cost_center_ids'], fn ($q, $ids) => $q->whereIn('cc.id', $ids))
            ->when($params['supplier_id'], fn ($q, $id) => $q->where('po.supplier_id', $id))
            ->select([
                'poi.id as item_id', 'poi.description', 'poi.quantity', 'poi.quantity_received', 'poi.unit_price',
                'po.id as order_id', 'po.folio', 'po.issued_at', 'po.created_at as order_created_at',
                'po.estimated_delivery_days', 'po.currency',
                's.id as supplier_id', 's.company_name as supplier_name',
                'cc.id as cost_center_id', 'cc.code as cost_center_code', 'cc.name as cost_center_name', 'cc.company_id',
                'ec.name as expense_category_name', 'bc.name as budget_cedula_name',
            ])
            ->get()
            ->map(fn ($r) => $this->mapCommon('OC', $r, null));
    }

    private function ocdItems(array $visibleCenterIds, array $params): Collection
    {
        return DB::table('odc_direct_purchase_order_items as poi')
            ->join('odc_direct_purchase_orders as po', 'po.id', '=', 'poi.direct_purchase_order_id')
            ->join('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->leftJoin('cost_centers as cc', 'cc.id', '=', 'poi.cost_center_id')
            ->leftJoin('expense_categories as ec', 'ec.id', '=', 'poi.expense_category_id')
            ->leftJoin('budget_cedulas as bc', 'bc.id', '=', 'poi.budget_cedula_id')
            ->whereIn('po.status', self::RECEIVABLE_STATUSES)
            ->whereNull('po.deleted_at')
            ->whereColumn('poi.quantity', '>', 'poi.quantity_received')
            ->whereIn('cc.id', $visibleCenterIds)
            ->when($params['company_ids'], fn ($q, $ids) => $q->whereIn('cc.company_id', $ids))
            ->when($params['cost_center_ids'], fn ($q, $ids) => $q->whereIn('cc.id', $ids))
            ->when($params['supplier_id'], fn ($q, $id) => $q->where('po.supplier_id', $id))
            ->select([
                'poi.id as item_id', 'poi.description', 'poi.quantity', 'poi.quantity_received', 'poi.unit_price',
                'po.id as order_id', 'po.folio', 'po.issued_at', 'po.submitted_at', 'po.created_at as order_created_at',
                'po.estimated_delivery_days', 'po.currency',
                's.id as supplier_id', 's.company_name as supplier_name',
                'cc.id as cost_center_id', 'cc.code as cost_center_code', 'cc.name as cost_center_name', 'cc.company_id',
                'ec.name as expense_category_name', 'bc.name as budget_cedula_name',
            ])
            ->get()
            ->map(fn ($r) => $this->mapCommon('OCD', $r, $r->submitted_at ?? null));
    }

    private function mapCommon(string $type, object $r, ?string $submittedAt): array
    {
        return (array) $r + ['type' => $type, 'submitted_at' => $submittedAt];
    }

    private function row(array $r, Carbon $asOf, int $zombieDays): array
    {
        $pendingQty = max(0.0, (float) $r['quantity'] - (float) $r['quantity_received']);
        // unit_price ya es el precio sin IVA (mismo criterio que BudgetAllocationService::getOrderBudgetLines()).
        $pendingAmount = round((float) $r['unit_price'] * $pendingQty, 2);
        $issuedAt = $r['issued_at'] ? Carbon::parse($r['issued_at']) : null;

        $promisedDate = ($issuedAt && $r['estimated_delivery_days'] !== null)
            ? $issuedAt->copy()->addDays((int) $r['estimated_delivery_days'])
            : null;
        // Días naturales de calendario desde la fecha prometida, sin horas: "hoy" nunca cuenta como atraso (0 días de gracia).
        $daysLate = null;
        if ($promisedDate) {
            $promisedDay = $promisedDate->copy()->startOfDay();
            $asOfDay = $asOf->copy()->startOfDay();
            $daysLate = $promisedDay->lt($asOfDay) ? (int) abs($asOfDay->diffInDays($promisedDay)) : 0;
        }

        $deadline = $this->vigenciaDeadline($r, $issuedAt);
        $isExpired = $deadline !== null && $asOf->gt($deadline);

        $lastReceivedAt = $this->lastReceivedAt($r['type'], (int) $r['item_id']);
        $referenceDate = $lastReceivedAt ?? $issuedAt ?? Carbon::parse($r['order_created_at']);
        $isZombie = $pendingQty > 0 && $referenceDate->lte($asOf->copy()->subDays($zombieDays));

        return [
            'type' => $r['type'],
            'item_id' => (int) $r['item_id'],
            'order_id' => (int) $r['order_id'],
            'folio' => $r['folio'],
            'url' => $r['type'] === 'OC' ? route('purchase-orders.show', $r['order_id']) : route('direct-purchase-orders.show', $r['order_id']),
            'supplier_id' => (int) $r['supplier_id'],
            'supplier_name' => $r['supplier_name'],
            'description' => $r['description'],
            'company_id' => $r['company_id'] !== null ? (int) $r['company_id'] : null,
            'cost_center_id' => $r['cost_center_id'] !== null ? (int) $r['cost_center_id'] : null,
            'cost_center_label' => trim(($r['cost_center_code'] ? $r['cost_center_code'].' · ' : '').$r['cost_center_name']),
            'expense_category_label' => $r['expense_category_name'],
            'budget_cedula_label' => $r['budget_cedula_name'],
            'issued_at' => $issuedAt?->toIso8601String(),
            'ordered_qty' => (float) $r['quantity'],
            'received_qty' => (float) $r['quantity_received'],
            'pending_qty' => $pendingQty,
            'unit_price' => (float) $r['unit_price'],
            'pending_amount' => $pendingAmount,
            'promised_date' => $promisedDate?->toDateString(),
            'days_late' => $daysLate,
            'is_expired' => $isExpired,
            'last_received_at' => $lastReceivedAt?->toIso8601String(),
            'is_zombie' => $isZombie,
        ];
    }

    /** Vigencia: mismo plazo que ya usa el cierre automático de cada tipo de orden. */
    private function vigenciaDeadline(array $r, ?Carbon $issuedAt): ?Carbon
    {
        if ($r['type'] === 'OC') {
            return ($issuedAt ?? Carbon::parse($r['order_created_at']))->copy()->addDays(PurchaseOrder::INACTIVITY_DAYS);
        }

        // OCD: getAutoCloseDeadline() se basa en submitted_at; si nunca se envió, no se puede determinar vigencia.
        return $r['submitted_at'] ? Carbon::parse($r['submitted_at'])->copy()->addDays(DirectPurchaseOrder::INACTIVITY_DAYS) : null;
    }

    private function lastReceivedAt(string $type, int $itemId): ?Carbon
    {
        $itemClass = $type === 'OC' ? \App\Models\PurchaseOrderItem::class : \App\Models\DirectPurchaseOrderItem::class;

        $max = DB::table('reception_items as ri')
            ->join('receptions as r', 'r.id', '=', 'ri.reception_id')
            ->where('ri.receivable_item_type', $itemClass)
            ->where('ri.receivable_item_id', $itemId)
            ->whereNull('r.deleted_at')
            ->max('r.received_at');

        return $max ? Carbon::parse($max) : null;
    }

    private function matches(array $row, array $params): bool
    {
        if ($params['only_late'] && (($row['days_late'] ?? 0) <= 0)) {
            return false;
        }

        return ! ($params['only_zombie'] && ! $row['is_zombie']);
    }

    private function kpis(Collection $rows): array
    {
        $zombies = $rows->where('is_zombie', true);

        return [
            'lines' => $rows->count(),
            'pending_amount_total' => round((float) $rows->sum('pending_amount'), 2),
            'late_lines' => $rows->filter(fn (array $r) => ($r['days_late'] ?? 0) > 0)->count(),
            'zombie_lines' => $zombies->count(),
            'zombie_amount' => round((float) $zombies->sum('pending_amount'), 2),
            'suppliers' => $rows->pluck('supplier_id')->unique()->count(),
        ];
    }

    /**
     * Cuadre contra el comprometido vigente de RP-01 (BudgetCommitment, sin filtros de pantalla:
     * es un número global de sanidad, no una conciliación renglón a renglón).
     */
    private function reconciliation(): array
    {
        $pending = DB::table('budget_commitments')
            ->where('status', 'COMMITTED')
            ->selectRaw('SUM(committed_amount - consumed_amount) as pending')
            ->value('pending');

        return ['budget_commitments_pending' => round((float) ($pending ?? 0), 2)];
    }
}
