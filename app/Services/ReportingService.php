<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportingService
{
    public const REPORTS = [
        'requisition-traceability' => ['Trazabilidad por requisitor', 'Requisiciones', 'ti-route'],
        'requester-ranking' => ['Ranking de requisitores', 'Requisiciones', 'ti-users'],
        'requisition-funnel' => ['Embudo y antigüedad', 'Requisiciones', 'ti-filter'],
        'purchasing-sla' => ['SLA de validación de Compras', 'Requisiciones', 'ti-clock-hour-4'],
        'requisitions-by-department' => ['Requisiciones por departamento', 'Requisiciones', 'ti-building-community'],
        'supplier-performance' => ['Gasto y desempeño de proveedores', 'Compras y proveedores', 'ti-building-store'],
        'purchase-orders-control' => ['Control de OC y ODC', 'Órdenes y recepciones', 'ti-shopping-cart'],
        'critical-orders' => ['Órdenes críticas', 'Órdenes y recepciones', 'ti-alert-triangle'],
        'receptions-differences' => ['Recepciones y diferencias', 'Órdenes y recepciones', 'ti-package-export'],
        'budget-execution' => ['Ejecución presupuestal', 'Presupuesto y contratos', 'ti-chart-bar'],
        'budget-movements-risk' => ['Movimientos y riesgo presupuestal', 'Presupuesto y contratos', 'ti-arrows-exchange'],
        'contracts-usage' => ['Uso y vigencia de contratos', 'Presupuesto y contratos', 'ti-file-certificate'],
    ];

    public function definition(string $report): array { abort_unless(isset(self::REPORTS[$report]), 404); return self::REPORTS[$report]; }

    public function filters(): array
    {
        return [
            'companies' => DB::table('companies')->orderBy('name')->get(['id', 'name']),
            'costCenters' => DB::table('cost_centers')->whereNull('deleted_at')->orderBy('name')->get(['id', 'code', 'name']),
            'departments' => DB::table('departments')->orderBy('name')->get(['id', 'name']),
            'users' => DB::table('users')->orderBy('name')->get(['id', 'name']),
            'suppliers' => DB::table('suppliers')->orderBy('company_name')->get(['id', 'company_name']),
            'contracts' => DB::table('contracts')->orderByDesc('end_date')->get(['id', 'folio']),
        ];
    }

    public function result(string $report, array $filters): array
    {
        [$from, $to] = $this->dates($filters);
        return match ($report) {
            'requisition-traceability' => $this->traceability($from, $to, $filters),
            'requester-ranking' => $this->requesterRanking($from, $to, $filters),
            'requisition-funnel' => $this->funnel($from, $to, $filters),
            'purchasing-sla' => $this->sla($from, $to, $filters),
            'requisitions-by-department' => $this->byDepartment($from, $to, $filters),
            'supplier-performance' => $this->suppliers($from, $to, $filters),
            'purchase-orders-control' => $this->orders($from, $to, $filters),
            'critical-orders' => $this->criticalOrders($from, $to, $filters),
            'receptions-differences' => $this->receptions($from, $to, $filters),
            'budget-execution' => $this->budget($from, $to, $filters),
            'budget-movements-risk' => $this->movements($from, $to, $filters),
            'contracts-usage' => $this->contracts($from, $to, $filters),
        };
    }

    private function dates(array $filters): array { $year = now()->year; return [$filters['date_from'] ?? "$year-01-01", $filters['date_to'] ?? "$year-12-31"]; }
    private function baseReq(string $from, string $to, array $f): Builder {
        $q = DB::table('requisitions as r')->whereNull('r.deleted_at')->whereBetween('r.created_at', [$from, Carbon::parse($to)->endOfDay()]);
        foreach (['company_id' => 'r.company_id', 'department_id' => 'r.department_id', 'requisitioner_id' => 'r.requested_by'] as $key => $column) if (!empty($f[$key])) $q->where($column, $f[$key]);
        if (!empty($f['cost_center_id'])) $q->whereExists(fn (Builder $items) => $items->selectRaw('1')->from('requisition_items as ri')->whereColumn('ri.requisition_id', 'r.id')->where('ri.cost_center_id', $f['cost_center_id']));
        if (!empty($f['status'])) $q->where('r.status', $f['status']); return $q;
    }
    private function pack(array $columns, Collection $rows, array $kpis = []): array
    {
        $rows = $rows->map(function ($row) {
            if (isset($row->status)) {
                $row->status = $this->statusLabel((string) $row->status);
            }

            foreach ([
                'created_at', 'validated_at', 'cotizacion_aprobada', 'oc_emitida', 'recibido',
                'issued_at', 'received_at', 'supplier_delivered_at', 'reception_deadline_at',
            ] as $field) {
                if (! empty($row->{$field} ?? null)) {
                    $row->{$field} = Carbon::parse($row->{$field})->format('Y-m-d H:i:s');
                }
            }

            foreach (['start_date', 'end_date'] as $field) {
                if (! empty($row->{$field} ?? null)) {
                    $row->{$field} = Carbon::parse($row->{$field})->toDateString();
                }
            }

            return $row;
        });

        return compact('columns', 'rows', 'kpis');
    }

    private function statusLabel(string $status): string
    {
        return match (strtoupper($status)) {
            'DRAFT' => 'Borrador',
            'PENDING_VALIDATION' => 'Pendiente de validación',
            'VALIDATED' => 'Validada',
            'PENDING_RFQ' => 'Pendiente de cotización',
            'RFQ_SENT' => 'Cotización solicitada',
            'QUOTED', 'RECEIVED_QUOTES', 'IN_QUOTATION' => 'En cotización',
            'PENDING_APPROVAL' => 'Pendiente de aprobación',
            'APPROVED' => 'Aprobada',
            'REJECTED' => 'Rechazada',
            'RETURNED' => 'Devuelta a revisión',
            'ISSUED' => 'Emitida',
            'DELIVERED_PENDING_RECEPTION' => 'Entregada, pendiente de recepción',
            'PARTIALLY_RECEIVED' => 'Recibida parcialmente',
            'RECEIVED' => 'Recibida',
            'COMPLETED' => 'Completada',
            'CLOSED_BY_INACTIVITY' => 'Cerrada por inactividad',
            'CANCELLED' => 'Cancelada',
            'PAID' => 'Pagada',
            'PENDING' => 'Pendiente',
            'IN_PROGRESS' => 'En proceso',
            'ACTIVE', 'VIGENTE' => 'Vigente',
            'EXPIRED', 'EXPIRED_CONTRACT' => 'Vencido',
            'SUSPENDED' => 'Suspendido',
            default => str($status)->replace('_', ' ')->lower()->ucfirst()->toString(),
        };
    }
    private function ageExpression(string $from, string $to): string { return DB::getDriverName() === 'sqlsrv' ? 'DATEDIFF(day, r.created_at, CURRENT_TIMESTAMP)' : "CAST(julianday('now') - julianday(r.created_at) AS INTEGER)"; }
    private function betweenExpression(string $from, string $to): string { return DB::getDriverName() === 'sqlsrv' ? 'DATEDIFF(day,r.created_at,r.validated_at)' : 'CAST(julianday(r.validated_at) - julianday(r.created_at) AS INTEGER)'; }

    private function traceability($from,$to,$f): array {
        $q=$this->baseReq($from,$to,$f)->leftJoin('users as u','u.id','=','r.requested_by')->leftJoin('departments as d','d.id','=','r.department_id')->leftJoin('quotation_summaries as qs','qs.requisition_id','=','r.id')->leftJoin('purchase_orders as po','po.requisition_id','=','r.id')->selectRaw("r.folio, u.name as requisitor, COALESCE(d.name, 'Sin departamento') as departamento, r.status, r.created_at, r.validated_at, MIN(qs.approved_at) as cotizacion_aprobada, MIN(po.issued_at) as oc_emitida, MIN(po.received_at) as recibido, MAX(qs.total) as monto")->groupBy('r.id','r.folio','u.name','d.name','r.status','r.created_at','r.validated_at');
        $rows=$q->orderByDesc('r.created_at')->get()->map(function($r){$inicio=Carbon::parse($r->created_at)->startOfDay();$fin=Carbon::parse($r->recibido ?? now())->startOfDay();$r->dias_ciclo=(int) $inicio->diffInDays($fin);return $r;});
        return $this->pack(['Folio','Requisitor','Departamento','Estatus','Creada','Validada','Cotización aprobada','OC emitida','Recibida','Monto','Días ciclo'],$rows,['Requisiciones'=>$rows->count(),'Completadas'=>$rows->whereNotNull('recibido')->count(),'Monto adjudicado'=>$rows->sum('monto')]);
    }
    private function requesterRanking($from,$to,$f): array { $rows=$this->baseReq($from,$to,$f)->join('users as u','u.id','=','r.requested_by')->leftJoin('quotation_summaries as qs','qs.requisition_id','=','r.id')->selectRaw('u.name as requisitor, COUNT(DISTINCT r.id) as requisiciones, SUM(CASE WHEN r.status = \'COMPLETED\' THEN 1 ELSE 0 END) as completadas, SUM(CASE WHEN r.status IN (\'CANCELLED\',\'REJECTED\') THEN 1 ELSE 0 END) as no_procedentes, SUM(qs.total) as monto_adjudicado')->groupBy('u.id','u.name')->orderByDesc('requisiciones')->get(); return $this->pack(['Requisitor','Requisiciones','Completadas','Canceladas/Rechazadas','Monto adjudicado'],$rows,['Requisitores'=>$rows->count(),'Requisiciones'=>$rows->sum('requisiciones'),'Monto'=>$rows->sum('monto_adjudicado')]); }
    private function funnel($from,$to,$f): array { $rows=$this->baseReq($from,$to,$f)->selectRaw('r.status, COUNT(*) as requisiciones, AVG('.$this->ageExpression($from,$to).') as edad_promedio_dias')->groupBy('r.status')->orderBy('r.status')->get(); return $this->pack(['Estatus','Requisiciones','Edad promedio (días)'],$rows,['Total'=>$rows->sum('requisiciones'),'En proceso'=>$rows->whereNotIn('status',['COMPLETED','CANCELLED','REJECTED'])->sum('requisiciones')]); }
    private function sla($from,$to,$f): array { $rows=$this->baseReq($from,$to,$f)->leftJoin('users as b','b.id','=','r.validated_by')->whereNotNull('r.validated_at')->selectRaw('r.folio, b.name as comprador, r.created_at, r.validated_at, '.$this->betweenExpression($from,$to).' as dias_validacion, r.status')->orderByDesc('dias_validacion')->get(); return $this->pack(['Folio','Comprador','Creada','Validada','Días validación','Estatus'],$rows,['Validadas'=>$rows->count(),'Promedio días'=>round($rows->avg('dias_validacion') ?? 0,1)]); }
    private function byDepartment($from,$to,$f): array { $rows=$this->baseReq($from,$to,$f)->leftJoin('departments as d','d.id','=','r.department_id')->leftJoin('quotation_summaries as qs','qs.requisition_id','=','r.id')->selectRaw("COALESCE(d.name,'Sin departamento') as departamento, COUNT(DISTINCT r.id) as requisiciones, COUNT(DISTINCT r.requested_by) as requisitores, SUM(qs.total) as monto, SUM(CASE WHEN r.status='COMPLETED' THEN 1 ELSE 0 END) as completadas")->groupBy('d.name')->orderByDesc('requisiciones')->get(); return $this->pack(['Departamento','Requisiciones','Requisitores','Monto','Completadas'],$rows,['Departamentos'=>$rows->count(),'Requisiciones'=>$rows->sum('requisiciones'),'Monto'=>$rows->sum('monto')]); }
    private function suppliers($from,$to,$f): array { $q=DB::table('purchase_orders as po')->join('suppliers as s','s.id','=','po.supplier_id')->whereNull('po.deleted_at')->whereBetween('po.created_at',[$from,Carbon::parse($to)->endOfDay()]); if(!empty($f['supplier_id']))$q->where('s.id',$f['supplier_id']); $rows=$q->selectRaw("s.company_name as proveedor, COUNT(*) as ordenes, SUM(po.total) as monto, SUM(CASE WHEN po.status='RECEIVED' THEN 1 ELSE 0 END) as recibidas, SUM(CASE WHEN po.status='DELIVERED_PENDING_RECEPTION' THEN 1 ELSE 0 END) as pendientes_recepcion")->groupBy('s.id','s.company_name')->orderByDesc('monto')->get(); return $this->pack(['Proveedor','Órdenes','Monto','Recibidas','Pendientes recepción'],$rows,['Proveedores'=>$rows->count(),'Monto'=>$rows->sum('monto'),'Órdenes'=>$rows->sum('ordenes')]); }
    private function orders($from,$to,$f): array { $regular=DB::table('purchase_orders')->whereNull('deleted_at')->whereBetween('created_at',[$from,Carbon::parse($to)->endOfDay()])->selectRaw("folio, 'OC' as tipo, status, total, issued_at, received_at"); $rows=DB::table('odc_direct_purchase_orders')->whereNull('deleted_at')->whereBetween('created_at',[$from,Carbon::parse($to)->endOfDay()])->selectRaw("folio, 'ODC' as tipo, status, total, issued_at, received_at")->unionAll($regular)->orderByDesc('issued_at')->get(); return $this->pack(['Folio','Tipo','Estatus','Monto','Emitida','Recibida'],$rows,['Órdenes'=>$rows->count(),'Monto'=>$rows->sum('total'),'Recibidas'=>$rows->where('status','RECEIVED')->count()]); }
    private function criticalOrders($from,$to,$f): array { $rows=DB::table('purchase_orders as po')->join('suppliers as s','s.id','=','po.supplier_id')->whereNull('po.deleted_at')->whereBetween('po.created_at',[$from,Carbon::parse($to)->endOfDay()])->whereIn('po.status',['DELIVERED_PENDING_RECEPTION','ISSUED','PARTIALLY_RECEIVED','CLOSED_BY_INACTIVITY'])->select('po.folio','s.company_name as proveedor','po.status','po.supplier_delivered_at','po.reception_deadline_at','po.issued_at')->orderBy('po.reception_deadline_at')->get(); return $this->pack(['Folio','Proveedor','Estatus','Entrega proveedor','Límite recepción','Emitida'],$rows,['Críticas'=>$rows->count(),'Pendientes recepción'=>$rows->where('status','DELIVERED_PENDING_RECEPTION')->count()]); }
    private function receptions($from,$to,$f): array { $rows=DB::table('receptions as r')->join('receiving_locations as l','l.id','=','r.receiving_location_id')->leftJoin('users as u','u.id','=','r.received_by')->whereNull('r.deleted_at')->whereBetween('r.received_at',[$from,Carbon::parse($to)->endOfDay()])->selectRaw('r.folio, l.name as ubicacion, u.name as receptor, r.status, r.received_at, (SELECT COUNT(*) FROM reception_items ri WHERE ri.reception_id=r.id AND ri.conformity=\'NO_CONFORME\') as no_conformes')->orderByDesc('r.received_at')->get(); return $this->pack(['Folio','Ubicación','Receptor','Estatus','Recibida','No conformes'],$rows,['Recepciones'=>$rows->count(),'No conformes'=>$rows->sum('no_conformes')]); }
    private function budget($from,$to,$f): array { $year=Carbon::parse($from)->year; $q=DB::table('budget_monthly_distributions as b')->join('annual_budgets as ab','ab.id','=','b.annual_budget_id')->join('cost_centers as cc','cc.id','=','ab.cost_center_id')->where('ab.fiscal_year',$year); if(!empty($f['cost_center_id']))$q->where('cc.id',$f['cost_center_id']); $rows=$q->selectRaw('cc.name as centro_costo, b.month, SUM(b.assigned_amount) as asignado, SUM(b.committed_amount) as comprometido, SUM(b.consumed_amount) as consumido, SUM(b.assigned_amount-b.committed_amount-b.consumed_amount) as disponible')->groupBy('cc.name','b.month')->orderBy('cc.name')->orderBy('b.month')->get(); return $this->pack(['Centro de costo','Mes','Asignado','Comprometido','Consumido','Disponible'],$rows,['Asignado'=>$rows->sum('asignado'),'Comprometido'=>$rows->sum('comprometido'),'Disponible'=>$rows->sum('disponible')]); }
    private function movements($from,$to,$f): array { $rows=DB::table('budget_movements as bm')->leftJoin('users as u','u.id','=','bm.created_by')->whereBetween('bm.created_at',[$from,Carbon::parse($to)->endOfDay()])->selectRaw('bm.id as folio, bm.status, bm.created_at, u.name as solicitante')->orderByDesc('bm.created_at')->get(); return $this->pack(['Folio','Estatus','Fecha','Solicitante'],$rows,['Movimientos'=>$rows->count(),'Pendientes'=>$rows->where('status','PENDING')->count()]); }
    private function contracts($from,$to,$f): array { $q=DB::table('contracts as c')->join('suppliers as s','s.id','=','c.supplier_id')->join('companies as co','co.id','=','c.company_id')->whereBetween('c.end_date',[$from,$to]); if(!empty($f['contract_id']))$q->where('c.id',$f['contract_id']); $rows=$q->selectRaw('c.folio, s.company_name as proveedor, co.name as empresa, c.contract_amount as monto_contratado, c.start_date, c.end_date, c.status')->orderBy('c.end_date')->get(); return $this->pack(['Folio','Proveedor','Empresa','Monto contratado','Inicio','Vencimiento','Estatus'],$rows,['Contratos'=>$rows->count(),'Monto contratado'=>$rows->sum('monto'),'Vencen en 30 días'=>$rows->filter(fn($r)=>Carbon::parse($r->end_date)->between(now(),now()->addDays(30)))->count()]); }
}
