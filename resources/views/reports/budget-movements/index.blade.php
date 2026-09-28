@extends('layouts.zircos')

@section('title', 'Movimientos presupuestales')

@push('styles')
<style>
    .bm-report { --bm-blue:#188ae2; --bm-border:#e2e9f0; }
    .bm-report .report-card { background:#fff; border:1px solid var(--bm-border); border-radius:.8rem; box-shadow:0 4px 16px rgba(24,54,82,.05); }
    .bm-report .report-head { background:#f7fbff; border:1px solid var(--bm-border); border-radius:.8rem; }
    .bm-report .table thead th { white-space:nowrap; font-size:.76rem; text-transform:uppercase; letter-spacing:.04em; color:#637386; }
    .bm-report .filter-card { padding:1.25rem 1.4rem 1rem; }
    .bm-report .filter-heading { display:flex; justify-content:space-between; align-items:center; gap:1rem; margin-bottom:1.15rem; }
    .bm-report .filter-heading h2 { margin:0; color:#26384b; font-size:1rem; font-weight:650; }
    .bm-report .filter-heading p { margin:.2rem 0 0; color:#8291a3; font-size:.82rem; }
    .bm-report .filter-grid { display:grid; grid-template-columns:repeat(12,minmax(0,1fr)); gap:1rem .9rem; }
    .bm-report .filter-field { grid-column:span 3; min-width:0; }
    .bm-report .filter-field .form-label { margin-bottom:.4rem; color:#536477; font-size:.78rem; font-weight:600; }
    .bm-report .filter-field .form-control, .bm-report .filter-field .form-select { min-height:43px; border-color:#dbe4ed; border-radius:.55rem; color:#34475b; font-size:.87rem; box-shadow:none; }
    .bm-report .filter-field .form-control:focus, .bm-report .filter-field .form-select:focus { border-color:#83bff0; box-shadow:0 0 0 .2rem rgba(24,138,226,.12); }
    .bm-report .filter-field .select2-container { width:100%!important; }
    .bm-report .filter-field .select2-container--bootstrap-5 .select2-selection--multiple { min-height:43px; border-color:#dbe4ed; border-radius:.55rem; }
    .bm-report .filter-field .select2-container--bootstrap-5.select2-container--focus .select2-selection { border-color:#83bff0; box-shadow:0 0 0 .2rem rgba(24,138,226,.12); }
    .bm-report .filter-field .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__rendered { display:flex; flex-wrap:wrap; gap:.2rem; padding:.2rem .45rem; }
    .bm-report .filter-field .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice { margin:0; border:0; border-radius:99px; background:#edf6fd; color:#25618d; font-size:.75rem; }
    .bm-report .filter-date-range { grid-column:span 6; }
    .bm-report .date-range-inputs { display:grid; grid-template-columns:1fr 1fr; gap:.6rem; }
    .bm-report .date-range-inputs label { margin-bottom:.35rem; color:#8291a3; font-size:.72rem; }
    .bm-report .filter-check { display:flex; align-items:center; min-height:43px; padding:.55rem .75rem; border:1px solid #dbe4ed; border-radius:.55rem; background:#fbfdff; }
    .bm-report .filter-check .form-check-input { flex:0 0 auto; margin:0 .55rem 0 0; }
    .bm-report .filter-check .form-check-label { color:#536477; font-size:.81rem; line-height:1.35; }
    .bm-report .filter-footer { display:flex; justify-content:flex-end; align-items:center; gap:.55rem; margin-top:1.1rem; padding-top:.9rem; border-top:1px solid #edf1f5; }
    .bm-report .filter-footer .btn { min-height:39px; padding-inline:1rem; border-radius:.5rem; }
    .bm-report .filter-footer .btn-light { border:1px solid #e1e8ef; color:#5d6d7e; background:#fff; }
    .bm-report .filter-error:empty { display:none; }
    .bm-report .table td { vertical-align:middle; }
    .bm-report .amount { white-space:nowrap; font-variant-numeric:tabular-nums; }
    .bm-report .status-pill { border-radius:99px; padding:.25rem .6rem; font-size:.75rem; background:#edf4fb; }
    .bm-report .recon-good { color:#168454; } .bm-report .recon-bad { color:#c83d4d; }
    .bm-report .movement-detail { font-size:.8rem; color:#586b7e; }
    .bm-report .tab-button { border:0; background:transparent; padding:.7rem 1rem; color:#66778a; }
    .bm-report .tab-button.active { color:var(--bm-blue); border-bottom:2px solid var(--bm-blue); font-weight:600; }
    .bm-report .busy { opacity:.62; pointer-events:none; }
    @media (max-width: 1199.98px) { .bm-report .filter-field { grid-column:span 4; } .bm-report .filter-date-range { grid-column:span 8; } }
    @media (max-width: 767.98px) { .bm-report .filter-field, .bm-report .filter-date-range { grid-column:span 6; } }
    @media (max-width: 575.98px) { .bm-report .filter-card { padding:1rem; } .bm-report .filter-field, .bm-report .filter-date-range { grid-column:span 12; } .bm-report .filter-heading { align-items:flex-start; } .bm-report .date-range-inputs { grid-template-columns:1fr; } .bm-report .filter-footer { display:grid; grid-template-columns:1fr 1fr; } }
    @media (prefers-reduced-motion:no-preference) { .bm-report .report-card { transition:box-shadow .16s ease,transform .16s ease; } .bm-report .report-card:hover { box-shadow:0 8px 22px rgba(24,54,82,.09); transform:translateY(-1px); } }
    @media (prefers-reduced-motion:reduce) { .bm-report *, .bm-report *::before, .bm-report *::after { animation:none!important; transition:none!important; scroll-behavior:auto!important; } }
</style>
@endpush

@section('content')
<div class="container-fluid bm-report py-3" id="budgetMovementReport">
        <div class="report-head p-4 mb-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div><div class="text-primary small fw-semibold text-uppercase">Presupuesto · RP-02</div><h1 class="h3 mb-1">Movimientos y traspasos presupuestales</h1><p class="text-muted mb-0">Historial de cambios autorizados y reconstrucción del presupuesto vigente.</p></div>
        <div class="d-flex gap-2"><a class="btn btn-outline-primary" href="{{ route('budget-movement-reports.export',['format'=>'csv']) }}" id="exportCsv">CSV</a><a class="btn btn-primary" href="{{ route('budget-movement-reports.export',['format'=>'xlsx']) }}" id="exportXlsx">Descargar Excel</a></div>
        </div>
    <div id="movementSummary" class="d-flex flex-wrap gap-2 mb-3" aria-live="polite"></div>
    <section class="report-card filter-card mb-3">
        <div class="filter-heading"><div><h2>Filtros del reporte</h2><p>Define el periodo y los movimientos que quieres consultar.</p></div><span class="small text-muted">Los filtros se pueden combinar</span></div>
        <form id="movementFilters">
            <div class="filter-grid">
                <div class="filter-field"><label class="form-label" for="year">Ejercicio</label><input class="form-control" id="year" name="fiscal_year" type="number" min="2020" max="2100" value="{{ now()->year }}"></div>
                <div class="filter-field"><label class="form-label" for="status">Estatus</label><select class="form-select" id="status" name="status"><option value="APROBADO" selected>Aprobados</option><option value="TODOS">Todos los estatus</option><option value="PENDIENTE_ORIGEN">Pendiente de origen</option><option value="PENDIENTE_DIRECCION">Pendiente de Dirección</option><option value="DEVUELTO">Devuelto</option><option value="RECHAZADO">Rechazado</option></select></div>
                <div class="filter-field"><label class="form-label" for="company">Empresa(s)</label><select class="form-select js-company-filter" id="company" name="company_ids[]" multiple data-placeholder="Todas las empresas">@foreach($companies as $company)<option value="{{ $company->id }}">{{ $company->name }}</option>@endforeach</select></div>
                <div class="filter-field"><label class="form-label" for="type">Tipo de movimiento</label><select class="form-select" id="type" name="movement_type[]"><option value="">Todos</option><option value="AMPLIACION">Ampliación</option><option value="REDUCCION">Reducción</option><option value="TRANSFERENCIA">Transferencia</option></select></div>
                <div class="filter-field"><label class="form-label" for="originCenter">Centro origen</label><select class="form-select" id="originCenter" name="origin_cost_center_id"><option value="">Todos</option>@foreach($centers as $center)<option value="{{ $center->id }}">{{ $center->code }} · {{ $center->name }}</option>@endforeach</select></div>
                <div class="filter-field"><label class="form-label" for="destinationCenter">Centro destino</label><select class="form-select" id="destinationCenter" name="destination_cost_center_id"><option value="">Todos</option>@foreach($centers as $center)<option value="{{ $center->id }}">{{ $center->code }} · {{ $center->name }}</option>@endforeach</select></div>
                <div class="filter-field"><label class="form-label" for="month">Mes del renglón</label><select class="form-select" id="month" name="month"><option value="">Todos</option>@foreach(range(1,12) as $month)<option value="{{ $month }}">{{ \Carbon\Carbon::create()->month($month)->locale('es')->monthName }}</option>@endforeach</select></div>
                <div class="filter-field"><label class="form-label" for="amount">Importe mínimo</label><input class="form-control" id="amount" name="amount_min" type="number" min="0" step="0.01" placeholder="$ 0.00"></div>
                <div class="filter-date-range"><label class="form-label d-block">Fecha del movimiento</label><div class="date-range-inputs"><div><label for="from">Desde</label><input class="form-control" id="from" name="date_from" type="date"></div><div><label for="to">Hasta</label><input class="form-control" id="to" name="date_to" type="date"></div></div></div>
                <div class="filter-field"><label class="form-label" for="authorizer">Autorizador</label><select class="form-select" id="authorizer" name="authorized_by"><option value="">Todos</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></div>
                <div class="filter-field"><div class="form-check filter-check"><input class="form-check-input" id="violations" name="only_level_violations" type="checkbox" value="1"><label class="form-check-label" for="violations">Solo cruces sin aprobación de Dirección</label></div></div>
            </div>
            <div class="filter-footer"><button class="btn btn-light" type="reset" id="clearFilters">Limpiar filtros</button><button class="btn btn-primary" type="submit"><i class="mdi mdi-filter-outline me-1" aria-hidden="true"></i>Aplicar filtros</button></div>
        </form>
        <div id="filterError" class="filter-error text-danger small mt-2" role="alert"></div>
    </section>
    <section class="report-card">
        <div class="d-flex border-bottom px-2"><button class="tab-button active" type="button" data-tab="movements">Movimientos</button><button class="tab-button" type="button" data-tab="reconciliation">Reconstrucción</button><span class="ms-auto align-self-center small text-muted pe-3" id="resultCount"></span></div>
        <div class="tab-pane p-3" id="movementsPane"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Folio / fecha</th><th>Tipo / estado</th><th>Origen → destino</th><th>Importe</th><th>Solicitó / autorizó</th><th>Autorización</th><th>Soporte / detalle</th></tr></thead><tbody id="movementRows"><tr><td colspan="7" class="text-center text-muted py-4">Cargando datos…</td></tr></tbody></table></div><div class="d-flex justify-content-between align-items-center mt-3"><button class="btn btn-sm btn-outline-secondary" id="prevPage" type="button">Anterior</button><span class="small text-muted" id="pageInfo"></span><button class="btn btn-sm btn-outline-secondary" id="nextPage" type="button">Siguiente</button></div></div>
        <div class="tab-pane p-3 d-none" id="reconciliationPane"><div class="alert alert-info py-2 small">La base se captura al aprobar el presupuesto. Históricos sin esa evidencia se identifican como “Sin base”; no se infiere el original desde el saldo actual. Esta conciliación siempre suma todos los movimientos aprobados del ejercicio; los filtros de fecha, tipo, autorizador e importe solo afectan la lista de movimientos.</div><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr><th>Empresa / centro</th><th>Periodo</th><th>Cuenta / subcuenta</th><th>Original</th><th>Movimientos</th><th>Reconstruido</th><th>Vigente</th><th>Diferencia</th><th>Estado</th></tr></thead><tbody id="reconciliationRows"><tr><td colspan="9" class="text-center text-muted py-4">Cargando datos…</td></tr></tbody></table></div></div>
    </section>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const root = document.getElementById('budgetMovementReport');
    const form = document.getElementById('movementFilters');
    const rows = document.getElementById('movementRows');
    const reconRows = document.getElementById('reconciliationRows');
    let page = 1;
    if (window.jQuery && jQuery.fn.select2) {
        jQuery('.js-company-filter').select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: 'Todas las empresas',
            closeOnSelect: false,
            dropdownParent: jQuery(document.body),
        });
    }
    const money = value => value === null || value === undefined ? '—' : new Intl.NumberFormat('es-MX',{style:'currency',currency:'MXN'}).format(value);
    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    function params(includePage=true) {
        const data = new FormData(form); const query = new URLSearchParams();
        for (const [key,value] of data.entries()) if (value !== '') query.append(key, value);
        if (includePage) { query.set('page',page); query.set('per_page','25'); }
        return query;
    }
    function renderMovement(row) {
        const origin = row.origin ? `${esc(row.origin.company)} · ${esc(row.origin.cost_center)} <small class="d-block">${esc(row.origin.account ?? '')} / ${esc(row.origin.subaccount ?? '')} · mes ${row.origin.month}</small>` : '—';
        const destination = row.destination ? `${esc(row.destination.company)} · ${esc(row.destination.cost_center)} <small class="d-block">${esc(row.destination.account ?? '')} / ${esc(row.destination.subaccount ?? '')} · mes ${row.destination.month}</small>` : (row.adjustment ? `${esc(row.adjustment.company)} · ${esc(row.adjustment.cost_center)} <small class="d-block">${esc(row.adjustment.account ?? '')} / ${esc(row.adjustment.subaccount ?? '')} · mes ${row.adjustment.month}</small>` : '—');
        const details = [row.origin,row.destination,row.adjustment].filter(Boolean).map(item=>money(item.amount)).join(' / ');
        const attachments = row.attachments.map(a=>`<a href="/budget-movement-attachments/${a.id}/download" title="SHA-256 ${esc(a.sha256)}">${esc(a.name)}</a>`).join('<br>') || '<span class="text-muted">Sin archivo</span>';
        return `<tr><td><strong>${esc(row.folio)}</strong><small class="d-block text-muted">${esc(row.movement_date)}</small>${row.reversal_of?`<small class="d-block">Reversa de #${row.reversal_of}</small>`:''}</td><td><span class="status-pill">${esc(row.movement_type)}</span><small class="d-block text-muted">${esc(row.status)}</small></td><td class="movement-detail">${origin}<span class="d-block text-center">↓</span>${destination}${row.cross_company?'<span class="badge bg-info text-dark mt-1">Cruza empresas</span>':''}</td><td class="amount fw-semibold">${money(row.total_amount)}<small class="d-block text-muted">${details}</small></td><td>${esc(row.requester)}<small class="d-block text-muted">${esc(row.final_authorizer || 'Pendiente')}</small></td><td>${row.authorization_violation?'<span class="text-danger">Falta Dirección</span>':(row.authorization_level_applied || 'Pendiente')}<small class="d-block text-muted">Requerido: ${esc(row.authorization_level_required || (row.movement_type === 'TRANSFERENCIA' ? 'DIRECCION' : '—'))}</small></td><td>${attachments}<small class="d-block"><a href="/budget_movements/${row.id}">Ver trazabilidad</a></small></td></tr>`;
    }
    function renderRecon(row) {
        const status = row.reconciliation_status === 'CONCILIA' ? '<span class="recon-good">Conciliado</span>' : (row.reconciliation_status === 'SIN_BASE' ? '<span class="text-warning">Sin base</span>' : '<span class="recon-bad">Diferencia</span>');
        return `<tr><td>${esc(row.company_name)}<small class="d-block text-muted">${esc(row.cost_center_code)} · ${esc(row.cost_center_name)}</small></td><td>${row.fiscal_year}-${String(row.month).padStart(2,'0')}</td><td>${esc(row.expense_category_name)}<small class="d-block text-muted">${esc(row.budget_cedula_name || '—')}</small></td><td class="amount">${money(row.original_amount)}</td><td class="amount">${money(row.movement_total)}</td><td class="amount">${money(row.reconstructed_amount)}</td><td class="amount">${money(row.current_amount)}</td><td class="amount">${money(row.difference)}</td><td>${status}</td></tr>`;
    }
    async function load() {
        root.classList.add('busy');
        try {
            const response = await fetch(`{{ route('budget-movement-reports.data') }}?${params().toString()}`, {headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});
            if (!response.ok) throw new Error(response.status === 403 ? 'No tienes permiso para consultar este reporte.' : 'No se pudo cargar el reporte. Revisa los filtros e inténtalo de nuevo.');
            const payload = await response.json(); const result = payload.movements;
            rows.innerHTML = result.rows.length ? result.rows.map(renderMovement).join('') : '<tr><td colspan="7" class="text-center text-muted py-4">No hay movimientos para estos filtros.</td></tr>';
            reconRows.innerHTML = payload.reconciliation.length ? payload.reconciliation.map(renderRecon).join('') : '<tr><td colspan="9" class="text-center text-muted py-4">No hay partidas de presupuesto para este alcance.</td></tr>';
            document.getElementById('resultCount').textContent = `${result.pagination.total} movimientos · ${result.summary.reduce((n,x)=>n+x.count,0)} por tipo`;
            document.getElementById('movementSummary').innerHTML = result.summary.map(item=>`<div class="report-card px-3 py-2"><span class="small text-muted">${esc(item.type)} · ${item.count}</span><strong class="d-block">${money(item.amount)}</strong></div>`).join('');
            document.getElementById('pageInfo').textContent = `Página ${result.pagination.current_page} de ${result.pagination.last_page}`;
            document.getElementById('prevPage').disabled = result.pagination.current_page <= 1;
            document.getElementById('nextPage').disabled = result.pagination.current_page >= result.pagination.last_page;
            document.getElementById('filterError').textContent = '';
            for (const format of ['csv','xlsx']) document.getElementById(format === 'csv' ? 'exportCsv':'exportXlsx').href = `{{ url('/reportes/presupuesto/movimientos/export') }}/${format}?${params(false).toString()}`;
        } catch (error) {
            document.getElementById('filterError').textContent = error.message;
            rows.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">No fue posible cargar el reporte.</td></tr>';
        } finally { root.classList.remove('busy'); }
    }
    form.addEventListener('submit', event => { event.preventDefault(); page=1; load(); });
    form.addEventListener('reset', () => setTimeout(()=>{page=1;load();},0));
    document.getElementById('prevPage').addEventListener('click',()=>{page=Math.max(1,page-1);load();});
    document.getElementById('nextPage').addEventListener('click',()=>{page++;load();});
    document.querySelectorAll('.tab-button').forEach(button=>button.addEventListener('click',()=>{
        document.querySelectorAll('.tab-button').forEach(tab=>tab.classList.toggle('active',tab===button));
        document.getElementById('movementsPane').classList.toggle('d-none',button.dataset.tab!=='movements');
        document.getElementById('reconciliationPane').classList.toggle('d-none',button.dataset.tab!=='reconciliation');
    }));
    load();
})();
</script>
@endpush
