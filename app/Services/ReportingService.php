<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\ReportSetting;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportingService
{
    public const VALIDATION_SLA_DAYS = 2;

    public const REPORTS = [
        'requisition-traceability' => ['Trazabilidad por requisitor', 'Requisiciones', 'ti-route', 'Sigue cada requisición desde su creación hasta la recepción, con responsables, fechas y tiempos de ciclo.'],
        'requester-ranking' => ['Demanda por requisitor', 'Requisiciones', 'ti-users', 'Compara el volumen, monto adjudicado y resultado de las requisiciones generadas por cada solicitante.'],
        'requisition-funnel' => ['Embudo y antigüedad', 'Requisiciones', 'ti-filter', 'Identifica cuántas requisiciones hay en cada etapa y cuánto tiempo llevan esperando atención.'],
        'purchasing-sla' => ['SLA de validación de Compras', 'Requisiciones', 'ti-clock-hour-4', 'Mide el tiempo de validación de Compras e identifica los casos que exceden la meta de SLA.'],
        'requisitions-by-department' => ['Demanda por departamento', 'Requisiciones', 'ti-building-community', 'Analiza la carga de requisiciones, solicitantes, montos y cierres por departamento.'],
        'supplier-performance' => ['Gasto y cumplimiento de proveedores', 'Compras y proveedores', 'ti-building-store', 'Evalúa el gasto por proveedor y su cumplimiento de recepción, incluyendo órdenes vencidas.'],
        'purchase-orders-control' => ['Control de OC y ODC', 'Órdenes y recepciones', 'ti-shopping-cart', 'Consulta las órdenes de compra y directas emitidas, su monto, estatus y avance de recepción.'],
        'critical-orders' => ['Órdenes en riesgo', 'Órdenes y recepciones', 'ti-alert-triangle', 'Prioriza órdenes próximas a vencer o vencidas para atender el riesgo de recepción y el monto expuesto.'],
        'receptions-differences' => ['Recepciones y no conformidades', 'Órdenes y recepciones', 'ti-package-export', 'Da seguimiento a las recepciones realizadas y a las partidas reportadas como no conformes.'],
        'budget-execution' => ['Ejecución presupuestal', 'Presupuesto y contratos', 'ti-chart-bar', 'Muestra el presupuesto asignado, comprometido, consumido y disponible por centro de costo y mes.'],
        'budget-movements-risk' => ['Movimientos presupuestales', 'Presupuesto y contratos', 'ti-arrows-exchange', 'Revisa movimientos presupuestales y detecta los pendientes que requieren aprobación o seguimiento.'],
        'contracts-usage' => ['Consumo y vigencia de contratos', 'Presupuesto y contratos', 'ti-file-certificate', 'Controla el consumo frente al monto contratado y anticipa contratos próximos a vencer.'],
    ];

    private const FILTERS = [
        'requisition-traceability' => ['company_id', 'cost_center_id', 'department_id', 'requisitioner_id', 'status'],
        'requester-ranking' => ['company_id', 'cost_center_id', 'department_id', 'requisitioner_id', 'status'],
        'requisition-funnel' => ['company_id', 'cost_center_id', 'department_id', 'requisitioner_id'],
        'purchasing-sla' => ['company_id', 'cost_center_id', 'department_id', 'requisitioner_id', 'status'],
        'requisitions-by-department' => ['company_id', 'cost_center_id', 'department_id', 'requisitioner_id', 'status'],
        'supplier-performance' => ['company_id', 'supplier_id'],
        'purchase-orders-control' => ['company_id', 'supplier_id', 'status'],
        'critical-orders' => ['company_id', 'supplier_id'],
        'receptions-differences' => ['company_id'],
        'budget-execution' => ['company_id', 'cost_center_id'],
        'budget-movements-risk' => [],
        'contracts-usage' => ['company_id', 'supplier_id', 'contract_id', 'status'],
    ];

    public function definition(string $report): array { abort_unless(isset(self::REPORTS[$report]), 404); return self::REPORTS[$report]; }

    public function currentMonthStatusSummary(?string $month = null): array
    {
        $period = now()->startOfMonth();
        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            try {
                $period = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
                if ($period->greaterThan(now()->startOfMonth())) {
                    $period = now()->startOfMonth();
                }
            } catch (\Throwable) {
                $period = now()->startOfMonth();
            }
        }
        $rows = DB::table('requisitions as r')
            ->leftJoin('users as requester', 'requester.id', '=', 'r.requested_by')
            ->leftJoin('departments as department', 'department.id', '=', 'requester.department_id')
            ->whereNull('r.deleted_at')
            ->whereBetween('r.created_at', [$period->copy()->startOfMonth(), $period->copy()->endOfMonth()])
            ->selectRaw("COALESCE(department.name, 'Sin departamento') as department, r.status, COUNT(*) as total")
            ->groupBy('department.name', 'r.status')
            ->get();

        $statuses = $rows->pluck('status')->unique()->sortBy(fn ($status) => $this->label((string) $status))->values();
        $departments = $rows->pluck('department')->unique()->sort()->values();
        $series = $statuses->map(fn ($status) => [
            'name' => $this->label((string) $status),
            'color' => $this->statusColor((string) $status),
            'data' => $departments->map(fn ($department) => (int) ($rows->first(fn ($row) => $row->department === $department && $row->status === $status)?->total ?? 0))->all(),
        ])->all();

        return [
            'period' => $period->translatedFormat('F Y'),
            'month' => $period->format('Y-m'),
            'previous_month' => $period->copy()->subMonth()->format('Y-m'),
            'next_month' => $period->copy()->addMonth()->format('Y-m'),
            'can_next' => $period->copy()->addMonth()->lessThanOrEqualTo(now()->startOfMonth()),
            'total' => (int) $rows->sum('total'),
            'departments' => $departments->all(),
            'series' => $series,
        ];
    }
    public function metadata(string $report): array
    {
        [$title, $group, , $description] = $this->definition($report);
        return ['title' => $title, 'group' => $group, 'filters' => self::FILTERS[$report], 'money_fields' => $this->moneyFields($report), 'sla_days' => $report === 'purchasing-sla' ? $this->validationSlaDays() : null, 'description' => $description];
    }
    public function filters(): array
    {
        return ['companies' => DB::table('companies')->orderBy('name')->get(['id','name']), 'costCenters' => DB::table('cost_centers')->whereNull('deleted_at')->orderBy('name')->get(['id','code','name']), 'departments' => DB::table('departments')->orderBy('name')->get(['id','name']), 'users' => DB::table('users')->orderBy('name')->get(['id','name']), 'suppliers' => DB::table('suppliers')->orderBy('company_name')->get(['id','company_name']), 'contracts' => DB::table('contracts')->orderByDesc('end_date')->get(['id','folio']), 'statuses' => ['DRAFT','PENDING_VALIDATION','VALIDATED','PENDING_RFQ','IN_QUOTATION','PENDING_APPROVAL','APPROVED','ISSUED','DELIVERED_PENDING_RECEPTION','PARTIALLY_RECEIVED','RECEIVED','COMPLETED','CANCELLED','REJECTED']];
    }
    public function result(string $report, array $filters): array
    {
        [$from,$to] = $this->dates($filters);
        $result = match ($report) {
            'requisition-traceability' => $this->traceability($from,$to,$filters), 'requester-ranking' => $this->ranking($from,$to,$filters), 'requisition-funnel' => $this->funnel($from,$to,$filters), 'purchasing-sla' => $this->sla($from,$to,$filters), 'requisitions-by-department' => $this->departments($from,$to,$filters), 'supplier-performance' => $this->suppliers($from,$to,$filters), 'purchase-orders-control' => $this->orders($from,$to,$filters), 'critical-orders' => $this->critical($from,$to,$filters), 'receptions-differences' => $this->receptions($from,$to,$filters), 'budget-execution' => $this->budget($from,$to,$filters), 'budget-movements-risk' => $this->movements($from,$to), 'contracts-usage' => $this->contracts($from,$to,$filters),
        };
        return $result + ['meta' => $this->metadata($report) + ['period' => $from->format('d/m/Y').' – '.$to->format('d/m/Y'), 'generated_at' => now()->format('d/m/Y H:i')]];
    }
    private function dates(array $filters): array { $year=now()->year; $from=Carbon::parse($filters['date_from'] ?? "$year-01-01")->startOfDay(); $to=Carbon::parse($filters['date_to'] ?? now()->toDateString())->endOfDay(); return [$from,$to]; }
    private function req(Carbon $from, Carbon $to, array $f): Builder
    {
        $q=DB::table('requisitions as r')->leftJoin('users as requester','requester.id','=','r.requested_by')->whereNull('r.deleted_at')->whereBetween('r.created_at',[$from,$to]);
        foreach(['company_id'=>'r.company_id','requisitioner_id'=>'r.requested_by'] as $key=>$column) if(!empty($f[$key])) $q->where($column,$f[$key]);
        if(!empty($f['department_id'])) $q->where('requester.department_id',$f['department_id']);
        if(!empty($f['cost_center_id'])) $q->whereExists(fn(Builder $i)=>$i->selectRaw('1')->from('requisition_items as ri')->whereColumn('ri.requisition_id','r.id')->where('ri.cost_center_id',$f['cost_center_id']));
        if(!empty($f['status'])) $q->where('r.status',$f['status']);
        return $q;
    }
    private function pack(array $columns, Collection $rows, array $kpis): array
    {
        $rows=$rows->map(function($row){ foreach(get_object_vars($row) as $field=>$value){ if($field==='status' && $value!==null) $row->status=$this->label($value); if(in_array($field,['created_at','validated_at','issued_at','received_at','supplier_delivered_at','reception_deadline_at'],true) && $value) $row->{$field}=Carbon::parse($value)->format('Y-m-d H:i'); if(in_array($field,['start_date','end_date'],true) && $value) $row->{$field}=Carbon::parse($value)->toDateString(); } return $row; });
        return compact('columns','rows','kpis');
    }
    private function label(string $status): string { return match(strtoupper($status)) {'DRAFT'=>'Borrador','PENDING'=>'Pendiente','PENDING_VALIDATION'=>'Pendiente de validación','VALIDATED'=>'Validada','PENDING_RFQ'=>'Pendiente de cotización','IN_QUOTATION'=>'En cotización','PENDING_APPROVAL'=>'Pendiente de aprobación','APPROVED'=>'Aprobada','REJECTED'=>'Rechazada','RETURNED'=>'Devuelta','ISSUED'=>'Emitida','DELIVERED_PENDING_RECEPTION'=>'Pendiente de recepción','PARTIALLY_RECEIVED'=>'Recibida parcialmente','RECEIVED','COMPLETED'=>'Completada','CLOSED_BY_INACTIVITY'=>'Cerrada por inactividad','CANCELLED'=>'Cancelada','PENDIENTE_DIRECCION'=>'Pendiente',default=>str($status)->replace('_',' ')->lower()->ucfirst()->toString()}; }
    private function statusColor(string $status): string { return match(strtoupper($status)) {'COMPLETED','RECEIVED'=>'#4bd396','REJECTED','CANCELLED'=>'#ef5f5f','PENDING','PENDING_VALIDATION','PENDING_RFQ','PENDING_APPROVAL','RETURNED'=>'#f0ad4e','IN_QUOTATION'=>'#7c6ee6',default=>'#188ae2'}; }
    private function traceability(Carbon $from, Carbon $to, array $f): array
    {
        $asOf=$to->isFuture()?now():$to;$rows=$this->req($from,$to,$f)->leftJoin('users as u','u.id','=','r.requested_by')->leftJoin('departments as d','d.id','=','requester.department_id')->leftJoin('quotation_summaries as qs','qs.requisition_id','=','r.id')->leftJoin('purchase_orders as po','po.requisition_id','=','r.id')->selectRaw("r.folio,u.name as requisitor,COALESCE(d.name,'Sin departamento') as departamento,r.status,r.created_at,r.validated_at,MIN(qs.approved_at) as cotizacion_aprobada,MIN(po.issued_at) as issued_at,MIN(po.received_at) as received_at,MAX(qs.total) as monto")->groupBy('r.id','r.folio','u.name','d.name','r.status','r.created_at','r.validated_at')->orderByDesc('r.created_at')->get()->map(function($row)use($asOf){$row->dias_ciclo=Carbon::parse($row->created_at)->startOfDay()->diffInDays(Carbon::parse($row->received_at ?? $asOf)->startOfDay());return $row;});
        return $this->pack(['Folio','Requisitor','Departamento','Estatus','Creada','Validada','Cotización aprobada','OC emitida','Recibida','Monto','Días ciclo'],$rows,['Requisiciones'=>$rows->count(),'Completadas'=>$rows->whereNotNull('received_at')->count(),'Monto adjudicado'=>$rows->sum('monto')]);
    }
    private function ranking(Carbon $from, Carbon $to, array $f): array { $rows=$this->req($from,$to,$f)->join('users as u','u.id','=','r.requested_by')->leftJoin('quotation_summaries as qs','qs.requisition_id','=','r.id')->selectRaw("u.name as requisitor,COUNT(DISTINCT r.id) as requisiciones,SUM(CASE WHEN r.status='COMPLETED' THEN 1 ELSE 0 END) as completadas,SUM(CASE WHEN r.status IN ('CANCELLED','REJECTED') THEN 1 ELSE 0 END) as no_procedentes,SUM(qs.total) as monto_adjudicado")->groupBy('u.id','u.name')->orderByDesc('requisiciones')->get(); return $this->pack(['Requisitor','Requisiciones','Completadas','Canceladas/Rechazadas','Monto adjudicado'],$rows,['Requisitores'=>$rows->count(),'Requisiciones'=>$rows->sum('requisiciones'),'Monto'=>$rows->sum('monto_adjudicado')]); }
    private function funnel(Carbon $from, Carbon $to, array $f): array { $asOf=($to->isFuture()?now():$to)->toDateTimeString(); $age=DB::getDriverName()==='sqlsrv'?"DATEDIFF(day,r.created_at,'$asOf')":"CAST(julianday('$asOf')-julianday(r.created_at) AS INTEGER)"; $rows=$this->req($from,$to,$f)->selectRaw("r.status,COUNT(*) as requisiciones,AVG($age) as edad_promedio_dias")->groupBy('r.status')->orderBy('r.status')->get(); return $this->pack(['Estatus','Requisiciones','Edad promedio (días)'],$rows,['Total'=>$rows->sum('requisiciones'),'En proceso'=>$rows->whereNotIn('status',['COMPLETED','CANCELLED','REJECTED'])->sum('requisiciones')]); }
    private function sla(Carbon $from, Carbon $to, array $f): array { $target=$this->validationSlaDays();$days=DB::getDriverName()==='sqlsrv'?'DATEDIFF(day,r.created_at,r.validated_at)':'CAST(julianday(r.validated_at)-julianday(r.created_at) AS INTEGER)'; $rows=$this->req($from,$to,$f)->leftJoin('users as b','b.id','=','r.validated_by')->whereNotNull('r.validated_at')->selectRaw("r.folio,b.name as comprador,r.created_at,r.validated_at,$days as dias_validacion,r.status")->orderByDesc('dias_validacion')->get()->map(function($r)use($target){$r->cumplimiento=$r->dias_validacion<=$target?'Dentro de SLA':'Fuera de SLA';return $r;}); $within=$rows->where('cumplimiento','Dentro de SLA')->count(); return $this->pack(['Folio','Comprador','Creada','Validada','Días validación','Estatus','Cumplimiento'],$rows,['Meta SLA (días)'=>$target,'Validadas'=>$rows->count(),'Dentro de SLA'=>$within,'Cumplimiento %'=>$rows->isEmpty()?0:round($within/$rows->count()*100,1),'Promedio días'=>round($rows->avg('dias_validacion')??0,1)]); }

    private function validationSlaDays(): int
    {
        if (! Schema::hasTable('report_settings')) {
            return self::VALIDATION_SLA_DAYS;
        }

        return (int) (ReportSetting::query()->where('key', 'purchasing_validation_sla_days')->value('value') ?? self::VALIDATION_SLA_DAYS);
    }

    private function moneyFields(string $report): array
    {
        return match ($report) {
            'requisition-traceability' => ['monto'],
            'requester-ranking' => ['monto_adjudicado'],
            'requisitions-by-department' => ['monto'],
            'supplier-performance', 'critical-orders', 'budget-movements-risk' => ['monto'],
            'purchase-orders-control' => ['total'],
            'budget-execution' => ['asignado', 'comprometido', 'consumido', 'disponible'],
            'contracts-usage' => ['monto_contratado', 'monto_utilizado'],
            default => [],
        };
    }
    private function departments(Carbon $from, Carbon $to, array $f): array { $rows=$this->req($from,$to,$f)->leftJoin('departments as d','d.id','=','requester.department_id')->leftJoin('quotation_summaries as qs','qs.requisition_id','=','r.id')->selectRaw("COALESCE(d.name,'Sin departamento') as departamento,COUNT(DISTINCT r.id) as requisiciones,COUNT(DISTINCT r.requested_by) as requisitores,SUM(qs.total) as monto,SUM(CASE WHEN r.status='COMPLETED' THEN 1 ELSE 0 END) as completadas")->groupBy('d.name')->orderByDesc('requisiciones')->get();return $this->pack(['Departamento','Requisiciones','Requisitores','Monto','Completadas'],$rows,['Departamentos'=>$rows->count(),'Requisiciones'=>$rows->sum('requisiciones'),'Monto'=>$rows->sum('monto')]); }
    private function suppliers(Carbon $from, Carbon $to, array $f): array { $q=DB::table('purchase_orders as po')->join('suppliers as s','s.id','=','po.supplier_id')->join('requisitions as r','r.id','=','po.requisition_id')->whereNull('po.deleted_at')->whereBetween('po.created_at',[$from,$to]);if(!empty($f['supplier_id']))$q->where('po.supplier_id',$f['supplier_id']);if(!empty($f['company_id']))$q->where('r.company_id',$f['company_id']);$rows=$q->selectRaw("s.company_name as proveedor,COUNT(*) as ordenes,SUM(po.total) as monto,SUM(CASE WHEN po.status='RECEIVED' THEN 1 ELSE 0 END) as recibidas,SUM(CASE WHEN po.reception_deadline_at < ? AND po.status IN ('ISSUED','DELIVERED_PENDING_RECEPTION','PARTIALLY_RECEIVED') THEN 1 ELSE 0 END) as vencidas",[$to])->groupBy('s.id','s.company_name')->orderByDesc('monto')->get()->map(function($r){$r->cumplimiento_recepcion_pct=$r->ordenes?round($r->recibidas/$r->ordenes*100,1):0;return $r;});return $this->pack(['Proveedor','Órdenes','Monto','Recibidas','Vencidas','Cumplimiento recepción %'],$rows,['Proveedores'=>$rows->count(),'Monto'=>$rows->sum('monto'),'Órdenes'=>$rows->sum('ordenes'),'Órdenes vencidas'=>$rows->sum('vencidas')]); }
    private function orders(Carbon $from, Carbon $to, array $f): array { $oc=DB::table('purchase_orders as po')->join('requisitions as r','r.id','=','po.requisition_id')->whereNull('po.deleted_at')->whereBetween('po.created_at',[$from,$to]);if(!empty($f['company_id']))$oc->where('r.company_id',$f['company_id']);if(!empty($f['supplier_id']))$oc->where('po.supplier_id',$f['supplier_id']);if(!empty($f['status']))$oc->where('po.status',$f['status']);$oc->selectRaw("po.folio,'OC' as tipo,po.status,po.total,po.issued_at,po.received_at");$odc=DB::table('odc_direct_purchase_orders as odc')->join('receiving_locations as rl','rl.id','=','odc.receiving_location_id')->whereNull('odc.deleted_at')->whereBetween('odc.created_at',[$from,$to]);if(!empty($f['company_id']))$odc->where('rl.company_id',$f['company_id']);if(!empty($f['supplier_id']))$odc->where('odc.supplier_id',$f['supplier_id']);if(!empty($f['status']))$odc->where('odc.status',$f['status']);$rows=$odc->selectRaw("odc.folio,'ODC' as tipo,odc.status,odc.total,odc.issued_at,odc.received_at")->unionAll($oc)->orderByDesc('issued_at')->get();return $this->pack(['Folio','Tipo','Estatus','Monto','Emitida','Recibida'],$rows,['Órdenes'=>$rows->count(),'Monto'=>$rows->sum('total'),'Recibidas'=>$rows->where('status','RECEIVED')->count()]); }
    private function critical(Carbon $from, Carbon $to, array $f): array { $q=DB::table('purchase_orders as po')->join('suppliers as s','s.id','=','po.supplier_id')->join('requisitions as r','r.id','=','po.requisition_id')->whereNull('po.deleted_at')->whereBetween('po.created_at',[$from,$to])->whereIn('po.status',['DELIVERED_PENDING_RECEPTION','ISSUED','PARTIALLY_RECEIVED']);if(!empty($f['company_id']))$q->where('r.company_id',$f['company_id']);if(!empty($f['supplier_id']))$q->where('po.supplier_id',$f['supplier_id']);$rows=$q->select('po.folio','s.company_name as proveedor','po.status','po.total as monto','po.supplier_delivered_at','po.reception_deadline_at','po.issued_at')->orderBy('po.reception_deadline_at')->get()->map(function($r)use($to){$r->dias_para_limite=$r->reception_deadline_at?Carbon::parse($to)->startOfDay()->diffInDays(Carbon::parse($r->reception_deadline_at)->startOfDay(),false):null;$r->prioridad=$r->dias_para_limite===null||$r->dias_para_limite>2?'Seguimiento':($r->dias_para_limite>=0?'Atención':'Crítica');return $r;});return $this->pack(['Folio','Proveedor','Estatus','Monto','Entrega proveedor','Límite recepción','Días para límite','Prioridad','Emitida'],$rows,['En riesgo'=>$rows->count(),'Críticas'=>$rows->where('prioridad','Crítica')->count(),'Monto en riesgo'=>$rows->filter(fn($r)=>in_array($r->prioridad,['Crítica','Atención']))->sum('monto')]); }
    private function receptions(Carbon $from, Carbon $to, array $f): array { $q=DB::table('receptions as r')->join('receiving_locations as l','l.id','=','r.receiving_location_id')->leftJoin('users as u','u.id','=','r.received_by')->whereNull('r.deleted_at')->whereBetween('r.received_at',[$from,$to]);if(!empty($f['company_id']))$q->where('l.company_id',$f['company_id']);$rows=$q->selectRaw("r.folio,l.name as ubicacion,u.name as receptor,r.status,r.received_at,(SELECT COUNT(*) FROM reception_items ri WHERE ri.reception_id=r.id AND ri.conformity='NO_CONFORME') as no_conformes")->orderByDesc('r.received_at')->get();return $this->pack(['Folio','Ubicación','Receptor','Estatus','Recibida','No conformes'],$rows,['Recepciones'=>$rows->count(),'No conformes'=>$rows->sum('no_conformes'),'Con diferencias %'=>$rows->isEmpty()?0:round($rows->where('no_conformes','>',0)->count()/$rows->count()*100,1)]); }
    private function budget(Carbon $from, Carbon $to, array $f): array { $q=DB::table('budget_monthly_distributions as b')->join('annual_budgets as ab','ab.id','=','b.annual_budget_id')->join('cost_centers as cc','cc.id','=','ab.cost_center_id')->whereNull('b.deleted_at')->whereNull('ab.deleted_at')->where('ab.fiscal_year',$from->year)->whereBetween('b.month',[$from->month,$to->month]);if(!empty($f['cost_center_id']))$q->where('cc.id',$f['cost_center_id']);if(!empty($f['company_id']))$q->where('cc.company_id',$f['company_id']);$rows=$q->selectRaw('cc.name as centro_costo,b.month,SUM(b.assigned_amount) as asignado,SUM(b.committed_amount) as comprometido,SUM(b.consumed_amount) as consumido,SUM(b.assigned_amount-b.committed_amount-b.consumed_amount) as disponible')->groupBy('cc.name','b.month')->orderBy('cc.name')->orderBy('b.month')->get()->map(function($r){$r->ejecucion_pct=$r->asignado?round(($r->comprometido+$r->consumido)/$r->asignado*100,1):0;return $r;});return $this->pack(['Centro de costo','Mes','Asignado','Comprometido','Consumido','Disponible','Ejecución %'],$rows,['Asignado'=>$rows->sum('asignado'),'Comprometido'=>$rows->sum('comprometido'),'Consumido'=>$rows->sum('consumido'),'Disponible'=>$rows->sum('disponible')]); }
    private function movements(Carbon $from, Carbon $to): array { $rows=DB::table('budget_movements as bm')->leftJoin('users as u','u.id','=','bm.created_by')->whereBetween('bm.movement_date',[$from->toDateString(),$to->toDateString()])->selectRaw('bm.id as folio,bm.movement_type as tipo,bm.status,bm.movement_date as fecha,bm.total_amount as monto,u.name as solicitante')->orderByDesc('bm.movement_date')->get();$pending=$rows->filter(fn($r)=>str_starts_with((string)$r->status,'PENDIENTE'));return $this->pack(['Folio','Tipo','Estatus','Fecha','Monto','Solicitante'],$rows,['Movimientos'=>$rows->count(),'Pendientes'=>$pending->count(),'Monto pendiente'=>$pending->sum('monto')]); }
    private function contracts(Carbon $from, Carbon $to, array $f): array { $q=DB::table('contracts as c')->join('suppliers as s','s.id','=','c.supplier_id')->join('companies as co','co.id','=','c.company_id')->leftJoin('requisition_items as ri','ri.contract_id','=','c.id')->leftJoin('requisitions as r',function($j)use($from,$to){$j->on('r.id','=','ri.requisition_id')->whereNull('r.deleted_at')->whereBetween('r.created_at',[$from,$to]);})->where('c.start_date','<=',$to->toDateString())->where('c.end_date','>=',$from->toDateString());foreach(['company_id'=>'c.company_id','supplier_id'=>'c.supplier_id','contract_id'=>'c.id','status'=>'c.status'] as $key=>$column)if(!empty($f[$key]))$q->where($column,$f[$key]);$rows=$q->selectRaw('c.folio,s.company_name as proveedor,co.name as empresa,c.contract_amount as monto_contratado,COALESCE(SUM(CASE WHEN r.id IS NOT NULL THEN ri.quantity*COALESCE(ri.unit_price,0) ELSE 0 END),0) as monto_utilizado,c.start_date,c.end_date,c.status')->groupBy('c.id','c.folio','s.company_name','co.name','c.contract_amount','c.start_date','c.end_date','c.status')->orderBy('c.end_date')->get()->map(function($r)use($to){$r->uso_pct=$r->monto_contratado?round($r->monto_utilizado/$r->monto_contratado*100,1):0;$r->dias_vigencia=Carbon::parse($to)->startOfDay()->diffInDays(Carbon::parse($r->end_date)->startOfDay(),false);return $r;});return $this->pack(['Folio','Proveedor','Empresa','Monto contratado','Monto utilizado','Uso %','Inicio','Vencimiento','Días vigencia','Estatus'],$rows,['Contratos'=>$rows->count(),'Monto contratado'=>$rows->sum('monto_contratado'),'Monto utilizado'=>$rows->sum('monto_utilizado'),'Vencen en 30 días'=>$rows->filter(fn($r)=>$r->dias_vigencia>=0&&$r->dias_vigencia<=30)->count()]); }
}
