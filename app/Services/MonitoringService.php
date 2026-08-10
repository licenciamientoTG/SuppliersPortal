<?php

namespace App\Services;

use App\Models\BudgetMonthlyDistribution;
use App\Models\BudgetMovement;
use App\Models\PurchaseOrder;
use App\Models\Requisition;
use App\Models\Rfq;
use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Models\User;
use App\Models\UserSessionActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

/**
 * Read-only queries for the administrative monitoring boards.
 * Operational users are always constrained to both their assigned companies
 * and active cost centers; empty assignments deliberately produce no data.
 */
class MonitoringService
{
    public function __construct(private readonly ModuleAccessService $moduleAccess) {}

    public function build(string $monitor, User $user): array
    {
        return match ($monitor) {
            'alerts' => $this->alerts($user),
            'operations' => $this->operations($user),
            'budget' => $this->budget($user),
            'suppliers' => $this->suppliers($user),
            'security' => $this->security($user),
            default => abort(404),
        };
    }

    private function alerts(User $user): array
    {
        $requisitions = $this->requisitions($user);
        $rfqs = $this->rfqs($user);
        $orders = $this->orders($user);
        $budget = $this->budgetDistributions($user);
        $suppliers = $this->suppliersQuery($user);
        $now = now();

        $items = collect();
        if ($this->can($user, 'monitoring_operations')) {
            $items->push($this->alert('Requisiciones pendientes', (clone $requisitions)->whereIn('status', ['PENDING', 'PAUSED', 'PENDING_BUDGET_ADJUSTMENT'])->count(), 'warning', route('requisitions.index'), 'Pendientes de validación, catálogo o ajuste presupuestal.'));
            $items->push($this->alert('RFQ vencidas', (clone $rfqs)->where('status', 'SENT')->whereNotNull('response_deadline')->where('response_deadline', '<', $now)->count(), 'danger', route('rfq.inbox.pending'), 'Solicitudes enviadas cuya fecha límite ya pasó.'));
            $items->push($this->alert('Órdenes por recibir', (clone $orders)->whereIn('status', ['ISSUED', 'PARTIALLY_RECEIVED', 'DELIVERED_PENDING_RECEPTION'])->count(), 'primary', route('purchase-orders.index'), 'Órdenes activas pendientes de recepción total.'));
        }
        if ($this->can($user, 'monitoring_budget')) {
            $items->push($this->alert('Presupuesto crítico', (clone $budget)->whereRaw('assigned_amount - consumed_amount - committed_amount <= assigned_amount * 0.30')->count(), 'danger', route('budget_movements.dashboard'), 'Distribuciones con disponibilidad menor o igual al 30%.'));
        }
        if ($this->can($user, 'monitoring_suppliers')) {
            $items->push($this->alert('Proveedores por revisar', (clone $suppliers)->where(fn (Builder $q) => $q->whereIn('approval_status', ['pending', 'PENDING'])->orWhereIn('document_status', ['pending', 'rejected', 'in_review']))->count(), 'warning', route('admin.review.index'), 'Altas o expedientes que requieren revisión.'));
        }
        $items = $items->filter(fn (array $item) => $item['count'] > 0)->values();

        $kpis = collect([$this->kpi('Alertas activas', $items->sum('count'), 'danger')]);
        if ($this->can($user, 'monitoring_operations')) {
            $kpis->push($this->kpi('RFQ por vencer', (clone $rfqs)->where('status', 'SENT')->whereBetween('response_deadline', [$now, $now->copy()->addDays(3)])->count(), 'warning'));
        }
        if ($this->can($user, 'monitoring_suppliers')) {
            $kpis->push($this->kpi('Documentos por vencer', (clone $suppliers)->whereHas('documents', fn (Builder $q) => $q->whereBetween('document_expiration_date', [$now->toDateString(), $now->copy()->addDays(30)->toDateString()]))->count(), 'primary'));
        }

        return $this->board('Centro de alertas', 'Prioriza los pendientes operativos y abre el módulo correspondiente para resolverlos.', 'ti-bell-ringing', $kpis->all(), [[
            'title' => 'Pendientes que requieren atención', 'icon' => 'ti-alert-triangle', 'columns' => ['Alerta', 'Cantidad', 'Contexto', 'Acción'], 'items' => $items->all(),
        ]], $user);
    }

    private function operations(User $user): array
    {
        $requisitions = $this->requisitions($user);
        $rfqs = $this->rfqs($user);
        $orders = $this->orders($user);
        $openOrders = (clone $orders)->whereIn('status', ['ISSUED', 'PARTIALLY_RECEIVED', 'DELIVERED_PENDING_RECEPTION']);

        return $this->board('Operación de compras', 'Seguimiento del flujo desde la requisición hasta la recepción, sin modificar su información.', 'ti-route-2', [
            $this->kpi('Requisiciones activas', (clone $requisitions)->whereNotIn('status', ['DRAFT', 'CANCELLED', 'COMPLETED'])->count(), 'primary'),
            $this->kpi('RFQ abiertas', (clone $rfqs)->whereIn('status', ['SENT', 'RECEIVED', 'EVALUATED'])->count(), 'warning'),
            $this->kpi('Órdenes activas', $openOrders->count(), 'info'),
            $this->kpi('Recepción parcial', (clone $orders)->where('status', 'PARTIALLY_RECEIVED')->count(), 'danger'),
        ], [[
            'title' => 'Embudo operativo', 'icon' => 'ti-git-merge', 'columns' => ['Etapa', 'Cantidad', 'Detalle', 'Acción'], 'items' => [
                $this->row('Requisiciones pendientes', (clone $requisitions)->whereIn('status', ['PENDING', 'PAUSED'])->count(), 'Esperan una definición de Compras o catálogo.', route('requisitions.index')),
                $this->row('En cotización', (clone $requisitions)->whereIn('status', ['IN_QUOTATION', 'QUOTED'])->count(), 'Requisiciones en RFQ o esperando decisión de cotización.', route('rfq.index')),
                $this->row('RFQ vencidas', (clone $rfqs)->where('status', 'SENT')->where('response_deadline', '<', now())->count(), 'Solicitudes sin cierre dentro de su fecha límite.', route('rfq.inbox.pending'), 'danger'),
                $this->row('Órdenes por recibir', (clone $orders)->whereIn('status', ['ISSUED', 'PARTIALLY_RECEIVED', 'DELIVERED_PENDING_RECEPTION'])->count(), 'Órdenes emitidas que siguen abiertas.', route('purchase-orders.index')),
            ],
        ]], $user);
    }

    private function budget(User $user): array
    {
        $budgets = $this->budgetDistributions($user);
        $totals = (clone $budgets)->selectRaw('COALESCE(SUM(assigned_amount),0) as assigned, COALESCE(SUM(consumed_amount),0) as consumed, COALESCE(SUM(committed_amount),0) as committed')->first();
        $available = max(0, (float) $totals->assigned - (float) $totals->consumed - (float) $totals->committed);
        $critical = (clone $budgets)->with(['annualBudget.costCenter', 'expenseCategory'])->whereRaw('assigned_amount - consumed_amount - committed_amount <= assigned_amount * 0.30')->orderByRaw('assigned_amount - consumed_amount - committed_amount')->limit(10)->get();

        return $this->board('Presupuesto y gasto', 'Consulta disponibilidad y compromisos de los centros de costo autorizados.', 'ti-chart-pie', [
            $this->kpi('Autorizado', $this->money($totals->assigned), 'primary'),
            $this->kpi('Comprometido', $this->money($totals->committed), 'warning'),
            $this->kpi('Consumido', $this->money($totals->consumed), 'info'),
            $this->kpi('Disponible', $this->money($available), $available > 0 ? 'success' : 'danger'),
        ], [[
            'title' => 'Distribuciones críticas o agotadas', 'icon' => 'ti-alert-octagon', 'columns' => ['Centro de costo', 'Categoría', 'Disponible', 'Estado'], 'items' => $critical->map(fn (BudgetMonthlyDistribution $item) => [
                'label' => $item->annualBudget?->costCenter?->name ?? 'Sin centro de costo', 'count' => $item->expenseCategory?->name ?? 'Sin categoría', 'context' => $this->money($item->getAvailableAmount()), 'badge' => $item->status, 'tone' => strtolower($item->status), 'url' => route('annual_budgets.show', $item->annual_budget_id), 'action' => 'Ver presupuesto',
            ])->all(),
        ], [
            'title' => 'Movimientos pendientes', 'icon' => 'ti-arrows-exchange', 'columns' => ['Movimiento', 'Monto', 'Fecha', 'Acción'], 'items' => $this->budgetMovements($user)->where('status', BudgetMovement::STATUS_PENDING)->latest('movement_date')->limit(10)->get()->map(fn (BudgetMovement $item) => $this->row($item->movement_type, $this->money($item->total_amount), optional($item->movement_date)->format('d/m/Y') ?: 'Sin fecha', route('budget_movements.show', $item)))->all(),
        ]], $user);
    }

    private function suppliers(User $user): array
    {
        $suppliers = $this->suppliersQuery($user);
        $soon = now()->addDays(30)->toDateString();
        $documents = SupplierDocument::query()->whereHas('supplier', fn (Builder $q) => $this->applySupplierScope($q, $user));

        return $this->board('Proveedores y cumplimiento', 'Vigila altas, expedientes y coincidencias EFOS disponibles para tu alcance.', 'ti-shield-check', [
            $this->kpi('Altas pendientes', (clone $suppliers)->whereIn('approval_status', ['pending', 'PENDING'])->count(), 'warning'),
            $this->kpi('Expedientes por revisar', (clone $suppliers)->whereIn('document_status', ['pending', 'rejected', 'in_review'])->count(), 'danger'),
            $this->kpi('Documentos por vencer', (clone $documents)->whereBetween('document_expiration_date', [now()->toDateString(), $soon])->count(), 'warning'),
            $this->kpi('Coincidencias EFOS', (clone $suppliers)->whereExists(fn ($q) => $q->selectRaw('1')->from('sat_efos_69b')->whereColumn('sat_efos_69b.rfc', 'suppliers.rfc')->whereIn('situation', ['Definitivo', 'Presunto']))->count(), 'danger'),
        ], [[
            'title' => 'Expedientes prioritarios', 'icon' => 'ti-file-alert', 'columns' => ['Proveedor', 'Estatus', 'Documentos', 'Acción'], 'items' => (clone $suppliers)->where(function (Builder $q) {
                $q->whereIn('approval_status', ['pending', 'PENDING'])->orWhereIn('document_status', ['pending', 'rejected', 'in_review']);
            })->latest()->limit(10)->get()->map(fn (Supplier $item) => [
                'label' => $item->name, 'count' => ucfirst((string) ($item->approval_status ?? 'Sin estatus')), 'context' => ucfirst((string) ($item->document_status ?? 'Sin estatus')), 'badge' => $item->is_efos ? 'EFOS: '.$item->efos_status : 'Sin alerta EFOS', 'tone' => $item->is_efos ? 'danger' : 'secondary', 'url' => route('admin.review.suppliers.show', $item), 'action' => 'Revisar',
            ])->all(),
        ]], $user);
    }

    private function security(User $user): array
    {
        abort_unless($user->hasRole('superadmin'), 403);
        $active = DB::table(config('session.table'))->whereNotNull('user_id')->where('last_activity', '>=', now()->subMinutes(config('session.lifetime'))->getTimestamp())->count();
        $activities = class_exists(Activity::class) && Schema::hasTable('activity_log')
            ? Activity::query()->with('causer:id,name,email')->latest()->limit(10)->get()
            : collect();

        return $this->board('Seguridad y auditoría', 'Consulta actividad administrativa y estado de acceso. Esta pantalla no permite modificar ni borrar evidencia.', 'ti-lock-check', [
            $this->kpi('Sesiones activas', $active, 'success'),
            $this->kpi('Accesos recientes', UserSessionActivity::query()->where('started_at', '>=', now()->subDay())->count(), 'primary'),
            $this->kpi('Eventos auditados', $activities->count(), 'info'),
        ], [[
            'title' => 'Actividad auditada reciente', 'icon' => 'ti-history', 'columns' => ['Evento', 'Usuario', 'Fecha', 'Acción'], 'items' => $activities->map(fn (Activity $item) => $this->row($item->description ?: 'Cambio registrado', $item->causer?->name ?? 'Sistema', $item->created_at?->format('d/m/Y H:i') ?? 'Sin fecha', route('admin.user-sessions.index')))->all(),
        ], [
            'title' => 'Accesos recientes', 'icon' => 'ti-login', 'columns' => ['Usuario', 'Inicio', 'Estado', 'Acción'], 'items' => UserSessionActivity::query()->with('user:id,name,email')->latest('started_at')->limit(10)->get()->map(fn (UserSessionActivity $item) => $this->row($item->user?->full_name ?: ($item->user?->name ?? 'Usuario eliminado'), $item->started_at?->format('d/m/Y H:i') ?? 'Sin fecha', $item->ended_at ? 'Cerrada' : 'Sin cierre registrado', route('admin.user-sessions.index')))->all(),
        ]], $user);
    }

    private function requisitions(User $user): Builder
    {
        return $this->applyRequisitionScope(Requisition::query(), $user);
    }

    private function rfqs(User $user): Builder
    {
        return Rfq::query()->whereHas('requisition', fn (Builder $q) => $this->applyRequisitionScope($q, $user));
    }

    private function orders(User $user): Builder
    {
        return PurchaseOrder::query()->whereHas('requisition', fn (Builder $q) => $this->applyRequisitionScope($q, $user));
    }

    private function budgetDistributions(User $user): Builder
    {
        return BudgetMonthlyDistribution::query()->whereHas('annualBudget', fn (Builder $q) => $this->applyBudgetScope($q, $user));
    }

    private function budgetMovements(User $user): Builder
    {
        if ($this->isGlobal($user)) {
            return BudgetMovement::query();
        }
        [, $costCenters] = $this->assignments($user);

        return $costCenters->isEmpty() ? BudgetMovement::query()->whereRaw('1 = 0') : BudgetMovement::query()->whereHas('details', fn (Builder $q) => $q->whereIn('cost_center_id', $costCenters));
    }

    private function suppliersQuery(User $user): Builder
    {
        return $this->applySupplierScope(Supplier::query(), $user);
    }

    private function applyRequisitionScope(Builder $query, User $user): Builder
    {
        if ($this->isGlobal($user)) {
            return $query;
        }
        [$companies, $costCenters] = $this->assignments($user);
        if ($companies->isEmpty() || $costCenters->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('company_id', $companies)->whereHas('items', fn (Builder $items) => $items->whereIn('cost_center_id', $costCenters));
    }

    private function applyBudgetScope(Builder $query, User $user): Builder
    {
        if ($this->isGlobal($user)) {
            return $query;
        }
        [, $costCenters] = $this->assignments($user);

        return $costCenters->isEmpty() ? $query->whereRaw('1 = 0') : $query->whereIn('cost_center_id', $costCenters);
    }

    private function applySupplierScope(Builder $query, User $user): Builder
    {
        if ($this->isGlobal($user)) {
            return $query;
        }

        return $query->whereHas('rfqs.requisition', fn (Builder $q) => $this->applyRequisitionScope($q, $user));
    }

    private function isGlobal(User $user): bool
    {
        return $user->hasAnyRole(['superadmin', 'general_director']);
    }

    private function can(User $user, string $module): bool
    {
        return $this->moduleAccess->userCanAccessModule($user, $module);
    }

    private function assignments(User $user): array
    {
        return [$user->companies()->pluck('companies.id'), $user->activeCostCenters()->pluck('cost_centers.id')];
    }

    private function money(float|string|null $amount): string
    {
        return '$'.number_format((float) $amount, 2);
    }

    private function kpi(string $label, mixed $value, string $tone): array
    {
        return compact('label', 'value', 'tone');
    }

    private function alert(string $label, int $count, string $tone, string $url, string $context): array
    {
        return compact('label', 'count', 'tone', 'url', 'context') + ['action' => 'Revisar'];
    }

    private function row(string $label, mixed $count, string $context, string $url, string $tone = 'secondary'): array
    {
        return compact('label', 'count', 'context', 'url', 'tone') + ['action' => 'Abrir'];
    }

    private function board(string $title, string $description, string $icon, array $kpis, array $sections, User $user): array
    {
        return compact('title', 'description', 'icon', 'kpis', 'sections') + ['is_global' => $this->isGlobal($user)];
    }
}
