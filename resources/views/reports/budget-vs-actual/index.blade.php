@extends('layouts.zircos')

@section('title', 'Presupuesto vs. ejercido')

@push('styles')
<style>
    .bva-report { --bva-border:#e2e9f0; --bva-reserved:#f4a62a; --bva-committed:#188ae2; --bva-accrued:#7a5bd1; --bva-unreconciled:#9aa5b1; --bva-available:#21b573; }
    .bva-report .report-card { background:#fff; border:1px solid var(--bva-border); border-radius:.8rem; box-shadow:0 4px 16px rgba(24,54,82,.05); }
    .bva-report .report-head { background:#f7fbff; border:1px solid var(--bva-border); border-radius:.8rem; }
    .bva-report .filter-card { padding:1.25rem 1.4rem 1rem; }
    .bva-report .filter-heading { display:flex; justify-content:space-between; align-items:center; gap:1rem; margin-bottom:1.15rem; }
    .bva-report .filter-heading h2 { margin:0; color:#26384b; font-size:1rem; font-weight:650; }
    .bva-report .filter-heading p { margin:.2rem 0 0; color:#8291a3; font-size:.82rem; }
    .bva-report .filter-grid { display:grid; grid-template-columns:repeat(12,minmax(0,1fr)); gap:1rem .9rem; }
    .bva-report .filter-field { grid-column:span 3; min-width:0; }
    .bva-report .filter-field.wide { grid-column:span 6; }
    .bva-report .filter-field .form-label { margin-bottom:.4rem; color:#536477; font-size:.78rem; font-weight:600; }
    .bva-report .filter-field .form-control, .bva-report .filter-field .form-select { min-height:43px; border-color:#dbe4ed; border-radius:.55rem; font-size:.87rem; }
    .bva-report .filter-field .select2-container { width:100%!important; }
    .bva-report .filter-check { display:flex; align-items:center; min-height:43px; padding:.55rem .75rem; border:1px solid #dbe4ed; border-radius:.55rem; background:#fbfdff; }
    .bva-report .filter-check .form-check-input { margin:0 .55rem 0 0; }
    .bva-report .filter-check .form-check-label { color:#536477; font-size:.81rem; }
    .bva-report .scope-toggle .btn { min-height:43px; }
    .bva-report .filter-footer { display:flex; justify-content:flex-end; gap:.55rem; margin-top:1.1rem; padding-top:.9rem; border-top:1px solid #edf1f5; }
    .bva-report .filter-error:empty { display:none; }
    .bva-report .kpi { padding:1rem 1.1rem; height:100%; }
    .bva-report .kpi .label { color:#7a8a9b; font-size:.75rem; text-transform:uppercase; letter-spacing:.04em; }
    .bva-report .kpi .value { font-size:1.35rem; font-weight:650; color:#26384b; font-variant-numeric:tabular-nums; }
    .bva-report .kpi .hint { font-size:.75rem; color:#8291a3; }
    .bva-report .notice:empty { display:none; }
    .bva-report .legend { display:flex; flex-wrap:wrap; gap:1rem; font-size:.78rem; color:#536477; }
    .bva-report .legend i { display:inline-block; width:.75rem; height:.75rem; border-radius:3px; margin-right:.35rem; vertical-align:-1px; }
    .bva-report .table-wrap { max-height:70vh; overflow:auto; }
    .bva-report table { font-size:.82rem; }
    .bva-report thead th { position:sticky; top:0; z-index:2; background:#f4f7fa; white-space:nowrap; font-size:.72rem; text-transform:uppercase; letter-spacing:.03em; color:#637386; }
    .bva-report .col-line { position:sticky; left:0; z-index:1; background:#fff; min-width:260px; max-width:320px; }
    .bva-report thead .col-line { z-index:3; background:#f4f7fa; }
    .bva-report .amount { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
    .bva-report .amount.negative { color:#c83d4d; font-weight:600; }
    .bva-report .bucket-link { border:0; background:none; padding:0; color:inherit; text-decoration:underline dotted; text-underline-offset:3px; }
    .bva-report .bucket-link:hover, .bva-report .bucket-link:focus-visible { color:#188ae2; }
    .bva-report tr.subtotal td { background:#f7fafc; font-weight:600; border-top:2px solid #e2e9f0; }
    .bva-report tr.subtotal.company td { background:#edf4fb; }
    .bva-report tr.grand-total td { background:#26384b; color:#fff; font-weight:650; }
    .bva-report tr.subtotal .col-line { background:#f7fafc; } .bva-report tr.subtotal.company .col-line { background:#edf4fb; } .bva-report tr.grand-total .col-line { background:#26384b; }
    .bva-report .stack { display:flex; width:160px; height:12px; border-radius:6px; overflow:hidden; background:#eef2f6; }
    .bva-report .stack span { display:block; height:100%; }
    .bva-report .overdraft { font-size:.7rem; color:#c83d4d; font-weight:600; }
    .bva-report .light { display:inline-block; min-width:4.6rem; text-align:center; }
    .bva-report .busy { opacity:.62; pointer-events:none; }
    @media (max-width: 1199.98px) { .bva-report .filter-field { grid-column:span 4; } .bva-report .filter-field.wide { grid-column:span 8; } }
    @media (max-width: 767.98px) { .bva-report .filter-field, .bva-report .filter-field.wide { grid-column:span 6; } }
    @media (max-width: 575.98px) { .bva-report .filter-field, .bva-report .filter-field.wide { grid-column:span 12; } .bva-report .col-line { min-width:200px; } }
</style>
@endpush

@section('content')
<div class="container-fluid bva-report py-3" id="budgetVsActualReport">
    <div class="report-head p-4 mb-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="text-primary small fw-semibold text-uppercase">Presupuesto · RP-01</div>
            <h1 class="h3 mb-1">Presupuesto vs. ejercido por departamento y renglón</h1>
            <p class="text-muted mb-0">Saldo realmente disponible por renglón. Usa el mismo cálculo que el bloqueo de presupuesto: lo que aquí aparece como disponible es lo que el sistema deja comprometer.</p>
        </div>
        @can('reportes.budget_vs_actual.exportar')
            <div class="d-flex gap-2">
                <a class="btn btn-outline-primary js-export" data-format="csv" href="{{ route('budget-vs-actual-reports.export', ['format' => 'csv']) }}" title="Renglones con los filtros aplicados"><i class="ti ti-file-text me-1" aria-hidden="true"></i>CSV</a>
                <a class="btn btn-primary js-export" data-format="xlsx" href="{{ route('budget-vs-actual-reports.export', ['format' => 'xlsx']) }}" title="Resumen con subtotales y detalle por documento"><i class="ti ti-file-spreadsheet me-1" aria-hidden="true"></i>Descargar Excel</a>
            </div>
        @endcan
    </div>

    <section class="report-card filter-card mb-3">
        <div class="filter-heading"><div><h2>Filtros del reporte</h2><p>Elige el periodo y el alcance que quieres revisar.</p></div></div>
        <form id="bvaFilters">
            <div class="filter-grid">
                <div class="filter-field"><label class="form-label" for="bvaYear">Ejercicio</label><input class="form-control" id="bvaYear" name="fiscal_year" type="number" min="2020" max="2100" value="{{ now()->year }}" required></div>
                <div class="filter-field"><label class="form-label" for="bvaMonth">Mes</label><select class="form-select" id="bvaMonth" name="period_month">@foreach(range(1, 12) as $month)<option value="{{ $month }}" @selected($month === now()->month)>{{ ucfirst(\Carbon\Carbon::create(null, $month, 1)->locale('es')->monthName) }}</option>@endforeach</select></div>
                <div class="filter-field"><span class="form-label d-block">Vista</span><div class="btn-group w-100 scope-toggle" role="group" aria-label="Vista del periodo"><input type="radio" class="btn-check" name="scope" id="scopeMonth" value="MES" checked><label class="btn btn-outline-primary" for="scopeMonth">Del mes</label><input type="radio" class="btn-check" name="scope" id="scopeYtd" value="ACU"><label class="btn btn-outline-primary" for="scopeYtd">Acumulado al mes</label></div></div>
                <div class="filter-field"><label class="form-label" for="bvaResponsible">Responsable</label><select class="form-select" id="bvaResponsible" name="responsible_user_id"><option value="">Todos</option>@foreach($responsibles as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></div>
                <div class="filter-field"><label class="form-label" for="bvaCompanies">Empresa(s)</label><select class="form-select js-multi" id="bvaCompanies" name="company_ids[]" multiple data-placeholder="Todas las empresas">@foreach($companies as $company)<option value="{{ $company->id }}">{{ $company->name }}</option>@endforeach</select></div>
                <div class="filter-field"><label class="form-label" for="bvaCenters">Centro(s) de costo</label><select class="form-select js-multi" id="bvaCenters" name="cost_center_ids[]" multiple data-placeholder="Todos los centros">@foreach($centers as $center)<option value="{{ $center->id }}" data-company-id="{{ $center->company_id }}">{{ $center->code }} · {{ $center->name }}</option>@endforeach</select></div>
                <div class="filter-field"><label class="form-label" for="bvaCategories">Cuenta(s) de gasto</label><select class="form-select js-multi" id="bvaCategories" name="expense_category_ids[]" multiple data-placeholder="Todas las cuentas">@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->code }} · {{ $category->name }}</option>@endforeach</select></div>
                <div class="filter-field"><div class="form-check filter-check mt-md-4"><input class="form-check-input" id="bvaCancelled" name="include_cancelled_po" type="checkbox" value="1"><label class="form-check-label" for="bvaCancelled">Mostrar OC canceladas (informativo, no suma)</label></div></div>
            </div>
            <div class="filter-footer"><button class="btn btn-light" type="reset">Limpiar filtros</button><button class="btn btn-primary" type="submit"><i class="ti ti-filter me-1" aria-hidden="true"></i>Aplicar filtros</button></div>
        </form>
        <div id="bvaError" class="filter-error text-danger small mt-2" role="alert"></div>
    </section>

    <div class="row g-2 mb-3" id="bvaKpis" aria-live="polite"></div>
    <div id="bvaNotice" class="notice mb-3"></div>

    <section class="report-card">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 py-2 border-bottom">
            <div class="legend" aria-label="Colores de la barra">
                <span><i style="background:var(--bva-reserved)"></i>Reservado</span>
                <span><i style="background:var(--bva-committed)"></i>Comprometido</span>
                <span><i style="background:var(--bva-accrued)"></i>Devengado</span>
                <span><i style="background:var(--bva-unreconciled)"></i>Sin conciliar</span>
                <span><i style="background:var(--bva-available)"></i>Disponible</span>
            </div>
            <span class="small text-muted" id="bvaCount"></span>
        </div>
        <div class="table-wrap">
            <table class="table table-hover mb-0">
                <thead id="bvaHead"></thead>
                <tbody id="bvaRows"><tr><td class="text-center text-muted py-4">Cargando datos…</td></tr></tbody>
            </table>
        </div>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3 border-top">
            <div class="d-flex align-items-center gap-2"><label class="small text-muted" for="bvaPerPage">Filas</label><select class="form-select form-select-sm w-auto" id="bvaPerPage"><option>25</option><option>50</option><option>100</option></select></div>
            <div class="d-flex align-items-center gap-2"><button class="btn btn-sm btn-outline-secondary" id="bvaPrev" type="button">Anterior</button><span class="small text-muted" id="bvaPageInfo"></span><button class="btn btn-sm btn-outline-secondary" id="bvaNext" type="button">Siguiente</button></div>
        </div>
    </section>
</div>

<div class="modal fade" id="bvaDetailModal" tabindex="-1" aria-labelledby="bvaDetailTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><div><h5 class="modal-title" id="bvaDetailTitle">Documentos</h5><div class="small text-muted" id="bvaDetailContext"></div></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body" id="bvaDetailBody"></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const root = document.getElementById('budgetVsActualReport');
    const form = document.getElementById('bvaFilters');
    const body = document.getElementById('bvaRows');
    const head = document.getElementById('bvaHead');
    const perPage = document.getElementById('bvaPerPage');
    const detailModal = new bootstrap.Modal(document.getElementById('bvaDetailModal'));
    const BUCKET_LABELS = { reserved: 'Reservado', committed: 'Comprometido', accrued: 'Devengado', released: 'OC canceladas' };
    let page = 1;

    if (window.jQuery && jQuery.fn.select2) {
        jQuery('.js-multi').each(function () {
            jQuery(this).select2({ theme: 'bootstrap-5', width: '100%', placeholder: this.dataset.placeholder, closeOnSelect: false, dropdownParent: jQuery(document.body) });
        });
    }

    // Al elegir empresas, la lista de centros se limita a esas empresas.
    const companySelect = document.getElementById('bvaCompanies');
    const centerSelect = document.getElementById('bvaCenters');
    const allCenters = Array.from(centerSelect.options).map(option => option.cloneNode(true));
    function refreshCenters() {
        const companies = window.jQuery ? (jQuery(companySelect).val() || []) : Array.from(companySelect.selectedOptions, o => o.value);
        const selected = window.jQuery ? (jQuery(centerSelect).val() || []) : Array.from(centerSelect.selectedOptions, o => o.value);
        const options = allCenters.filter(o => companies.length === 0 || companies.includes(o.dataset.companyId)).map(o => o.cloneNode(true));
        options.forEach(o => { o.selected = selected.includes(o.value); });
        centerSelect.replaceChildren(...options);
        if (window.jQuery) jQuery(centerSelect).trigger('change.select2');
    }
    window.jQuery ? jQuery(companySelect).on('change', refreshCenters) : companySelect.addEventListener('change', refreshCenters);

    const fmt = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });
    const money = value => value === null || value === undefined ? '—' : fmt.format(value);
    const pct = value => value === null || value === undefined ? '—' : `${(value * 100).toFixed(1)} %`;
    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const amountCell = (value, extra = '') => `<td class="amount ${value < 0 ? 'negative' : ''} ${extra}">${money(value)}</td>`;
    const light = value => {
        const map = { VERDE: ['bg-success', 'Verde'], AMARILLO: ['bg-warning text-dark', 'Amarillo'], ROJO: ['bg-danger', 'Rojo'] };
        const [cls, label] = map[value] || ['bg-secondary', '—'];
        return `<span class="badge light ${cls}">${label}</span>`;
    };

    function params(withPage = true) {
        const query = new URLSearchParams();
        for (const [key, value] of new FormData(form).entries()) if (value !== '') query.append(key, value);
        if (withPage) { query.set('page', page); query.set('per_page', perPage.value); }
        return query;
    }

    const showCancelled = () => document.getElementById('bvaCancelled').checked;

    function renderHead() {
        head.innerHTML = `<tr><th class="col-line">Centro de costo / renglón</th><th class="amount">Autorizado</th><th class="amount">Ampliaciones</th><th class="amount">Reducciones</th><th class="amount">Vigente</th><th class="amount">Reservado</th><th class="amount">Comprometido</th><th class="amount">Devengado</th><th class="amount" title="El portal no registra pagos a proveedores">Pagado</th><th class="amount">Disponible</th>${showCancelled() ? '<th class="amount">OC canceladas</th>' : ''}<th>Avance</th><th class="amount">Proyección cierre</th><th>Distribución</th></tr>`;
    }

    function stack(row) {
        const base = row.current_budget;
        if (!base || base <= 0) return '<span class="small text-muted">Sin vigente</span>';
        const parts = [['reserved', 'reserved'], ['committed', 'committed'], ['accrued', 'accrued'], ['unreconciled', 'unreconciled'], ['available', 'available']];
        const bars = parts.map(([key, color]) => {
            const width = Math.max(0, Math.min(100, (row[key] || 0) / base * 100));
            return width > 0 ? `<span style="width:${width}%;background:var(--bva-${color})" title="${BUCKET_LABELS[key] || (key === 'available' ? 'Disponible' : 'Sin conciliar')}: ${money(row[key])}"></span>` : '';
        }).join('');
        return `<div class="stack">${bars}</div>${row.available < 0 ? `<div class="overdraft">Sobregiro ${money(-row.available)}</div>` : ''}`;
    }

    function bucket(row, key) {
        const value = row[key];
        if (!value) return amountCell(value);
        return `<td class="amount"><button type="button" class="bucket-link" data-bucket="${key}" data-center="${row.cost_center_id}" data-category="${row.expense_category_id}" data-cedula="${row.budget_cedula_id ?? ''}" data-label="${esc(row.cost_center_code + ' · ' + row.budget_line_code + ' ' + row.budget_line_name + (row.budget_cedula_name ? ' / ' + row.budget_cedula_name : ''))}">${money(value)}</button></td>`;
    }

    function cells(row, interactive) {
        const authorized = row.authorized_amount === null
            ? `<td class="amount"><span class="badge bg-light text-warning border" title="Este renglón no tiene base aprobada capturada">Sin base</span></td>`
            : amountCell(row.authorized_amount);
        const projection = row.projected_close === null || row.projected_close === undefined
            ? '<td class="amount text-muted" title="Aún no hay meses cerrados en el ejercicio">—</td>'
            : `<td class="amount ${row.projected_close > row.current_budget ? 'negative' : ''}" ${row.projection_basis_months !== undefined && row.projection_basis_months < 3 ? `title="Calculada con ${row.projection_basis_months} mes(es) cerrado(s)"` : ''}>${money(row.projected_close)}</td>`;
        return authorized + amountCell(row.increases) + amountCell(row.decreases) + amountCell(row.current_budget, 'fw-semibold')
            + (interactive ? bucket(row, 'reserved') + bucket(row, 'committed') + bucket(row, 'accrued') : amountCell(row.reserved) + amountCell(row.committed) + amountCell(row.accrued))
            + '<td class="amount text-muted" title="El portal no registra pagos a proveedores">N/D</td>'
            + amountCell(row.available, 'fw-semibold')
            + (showCancelled() ? (interactive ? bucket(row, 'released') : amountCell(row.released)) : '')
            + `<td class="text-nowrap">${light(row.traffic_light)} <span class="small">${pct(row.progress_pct)}</span></td>`
            + projection
            + `<td>${stack(row)}</td>`;
    }

    function lineRow(row) {
        const line = `${esc(row.budget_line_code)} · ${esc(row.budget_line_name)}${row.budget_cedula_name ? `<small class="d-block text-muted">${esc(row.budget_cedula_name)}</small>` : ''}`;
        const diff = row.baseline_status === 'DIFERENCIA' ? `<small class="d-block text-danger" title="Autorizado + ampliaciones − reducciones no coincide con el vigente">Diferencia de conciliación ${money(row.budget_difference)}</small>` : '';
        return `<tr><td class="col-line"><small class="text-muted d-block">${esc(row.company_name)} · ${esc(row.cost_center_code)} ${esc(row.cost_center_name)}</small>${line}${diff}</td>${cells(row, true)}</tr>`;
    }

    function subtotalRow(label, totals, cls) {
        return `<tr class="subtotal ${cls}"><td class="col-line">${label}</td>${cells(totals, false)}</tr>`;
    }

    function render(payload) {
        renderHead();
        const rows = payload.rows;
        if (!rows.length) {
            body.innerHTML = `<tr><td colspan="15" class="text-center text-muted py-4">No hay renglones de presupuesto aprobado para estos filtros.</td></tr>`;
            return;
        }
        let html = '';
        rows.forEach(row => {
            html += lineRow(row);
            if (row.last_of_cost_center) html += subtotalRow(`Subtotal ${esc(row.cost_center_code)} · ${esc(row.cost_center_name)}${row.responsible_name ? `<small class="d-block fw-normal text-muted">Responsable: ${esc(row.responsible_name)}</small>` : ''}`, payload.cost_centers[row.cost_center_id], '');
            if (row.last_of_company) html += subtotalRow(`Subtotal ${esc(row.company_name)}${row.company_rfc ? `<small class="d-block fw-normal text-muted">RFC ${esc(row.company_rfc)}</small>` : ''}`, payload.companies[row.company_id], 'company');
        });
        if (payload.pagination.current_page === payload.pagination.last_page) {
            html += `<tr class="grand-total"><td class="col-line">Total general</td>${cells(payload.total, false)}</tr>`;
        }
        body.innerHTML = html;
    }

    function renderKpis(kpis) {
        const card = (label, value, hint = '', cls = '') => `<div class="col-6 col-md-4 col-xl"><div class="report-card kpi"><div class="label">${label}</div><div class="value ${cls}">${value}</div><div class="hint">${hint}</div></div></div>`;
        document.getElementById('bvaKpis').innerHTML =
            card('Presupuesto vigente', money(kpis.current_budget), `${kpis.lines} renglones`)
            + card('Consumido', money(kpis.consumed_total), 'Reservado + comprometido + devengado')
            + card('Disponible', money(kpis.available), 'Lo que todavía se puede comprometer', kpis.available < 0 ? 'text-danger' : '')
            + card('Avance', pct(kpis.progress_pct), 'Consumido / vigente')
            + card('Renglones en rojo', kpis.red_lines, 'Consumo ≥ 100 % del vigente', kpis.red_lines > 0 ? 'text-danger' : '');

        const notices = [];
        if (kpis.lines_without_baseline > 0) notices.push(`<strong>${kpis.lines_without_baseline}</strong> renglón(es) no tienen base aprobada capturada; su autorizado se muestra como “Sin base”.`);
        if (Math.abs(kpis.unreconciled) >= 0.005) notices.push(`Hay <strong>${money(kpis.unreconciled)}</strong> comprometidos en presupuesto sin documento que los respalde (“Sin conciliar”). Se muestran para revisión, no se ocultan.`);
        notices.push('“Pagado” no está disponible: el portal todavía no registra pagos a proveedores, así que lo recibido permanece en Devengado.');
        document.getElementById('bvaNotice').innerHTML = `<div class="alert alert-info py-2 small mb-0">${notices.join('<br>')}</div>`;
    }

    async function load() {
        root.classList.add('busy');
        try {
            const response = await fetch(`{{ route('budget-vs-actual-reports.data') }}?${params()}`, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error(response.status === 403 ? 'No tienes permiso para consultar este reporte.' : response.status === 422 ? 'Revisa los filtros: algún valor no es válido.' : 'No se pudo cargar el reporte. Inténtalo de nuevo.');
            const payload = await response.json();
            page = payload.pagination.current_page;
            render(payload);
            renderKpis(payload.kpis);
            document.getElementById('bvaCount').textContent = `${payload.pagination.total} renglones`;
            document.getElementById('bvaPageInfo').textContent = `Página ${payload.pagination.current_page} de ${payload.pagination.last_page}`;
            document.getElementById('bvaPrev').disabled = payload.pagination.current_page <= 1;
            document.getElementById('bvaNext').disabled = payload.pagination.current_page >= payload.pagination.last_page;
            document.getElementById('bvaError').textContent = '';
            // La descarga reproduce exactamente los filtros de la consulta que se ve en pantalla.
            document.querySelectorAll('.js-export').forEach(link => {
                link.href = `{{ url('reportes/presupuesto/ejercido/export') }}/${link.dataset.format}?${params(false)}`;
            });
        } catch (error) {
            document.getElementById('bvaError').textContent = error.message;
            body.innerHTML = '<tr><td colspan="15" class="text-center text-danger py-4">No fue posible cargar el reporte.</td></tr>';
        } finally {
            root.classList.remove('busy');
        }
    }

    async function openDetail(button) {
        const query = params(false);
        query.set('bucket', button.dataset.bucket);
        query.set('cost_center_id', button.dataset.center);
        query.set('expense_category_id', button.dataset.category);
        if (button.dataset.cedula) query.set('budget_cedula_id', button.dataset.cedula);
        const title = document.getElementById('bvaDetailTitle');
        const detailBody = document.getElementById('bvaDetailBody');
        title.textContent = `${BUCKET_LABELS[button.dataset.bucket]} · documentos`;
        document.getElementById('bvaDetailContext').textContent = button.dataset.label;
        detailBody.innerHTML = '<p class="text-muted mb-0">Cargando documentos…</p>';
        detailModal.show();
        try {
            const response = await fetch(`{{ route('budget-vs-actual-reports.detail') }}?${query}`, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error();
            const payload = await response.json();
            detailBody.innerHTML = payload.documents.length
                ? `<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Documento</th><th>Mes</th><th>Estatus</th><th class="amount">Comprometido</th><th class="amount">Recibido</th><th class="amount">Importe en este monto</th></tr></thead><tbody>${payload.documents.map(d => `<tr><td>${esc(d.type)} ${d.url ? `<a href="${esc(d.url)}" target="_blank" rel="noopener">${esc(d.folio || 'sin folio')}</a>` : esc(d.folio || 'sin folio')}<small class="d-block text-muted">${esc(d.committed_at || '')}</small></td><td>${esc(d.application_month)}</td><td>${esc(d.status)}</td><td class="amount">${money(d.committed_amount)}</td><td class="amount">${money(d.consumed_amount)}</td><td class="amount fw-semibold">${money(d.amount)}</td></tr>`).join('')}</tbody><tfoot><tr><td colspan="5" class="text-end fw-semibold">Total</td><td class="amount fw-semibold">${money(payload.total)}</td></tr></tfoot></table></div>`
                : '<p class="text-muted mb-0">No hay documentos para este monto.</p>';
        } catch (error) {
            detailBody.innerHTML = '<p class="text-danger mb-0">No fue posible cargar los documentos.</p>';
        }
    }

    body.addEventListener('click', event => {
        const button = event.target.closest('.bucket-link');
        if (button) openDetail(button);
    });
    form.addEventListener('submit', event => { event.preventDefault(); page = 1; load(); });
    form.addEventListener('reset', () => setTimeout(() => {
        if (window.jQuery) jQuery('.js-multi').val(null).trigger('change');
        page = 1; load();
    }, 0));
    perPage.addEventListener('change', () => { page = 1; load(); });
    document.getElementById('bvaPrev').addEventListener('click', () => { page = Math.max(1, page - 1); load(); });
    document.getElementById('bvaNext').addEventListener('click', () => { page++; load(); });
    load();
})();
</script>
@endpush
