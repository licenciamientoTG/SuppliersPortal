@extends('layouts.zircos')

@section('title', 'Pipeline de requisiciones')

@push('styles')
<style>
    .rpl-report { --rpl-border:#e2e9f0; }
    .rpl-report .report-card { background:#fff; border:1px solid var(--rpl-border); border-radius:.8rem; box-shadow:0 4px 16px rgba(24,54,82,.05); }
    .rpl-report .report-head { background:#f7fbff; border:1px solid var(--rpl-border); border-radius:.8rem; }
    .rpl-report .filter-card { padding:1.25rem 1.4rem 1rem; }
    .rpl-report .filter-heading h2 { margin:0; color:#26384b; font-size:1rem; font-weight:650; }
    .rpl-report .filter-heading p { margin:.2rem 0 1.15rem; color:#8291a3; font-size:.82rem; }
    .rpl-report .filter-grid { display:grid; grid-template-columns:repeat(12,minmax(0,1fr)); gap:1rem .9rem; }
    .rpl-report .filter-field { grid-column:span 3; min-width:0; }
    .rpl-report .filter-field.wide { grid-column:span 6; }
    .rpl-report .filter-field .form-label { margin-bottom:.4rem; color:#536477; font-size:.78rem; font-weight:600; }
    .rpl-report .filter-field .form-control, .rpl-report .filter-field .form-select { min-height:43px; border-color:#dbe4ed; border-radius:.55rem; font-size:.87rem; }
    .rpl-report .filter-field .select2-container { width:100%!important; }
    .rpl-report .filter-footer { display:flex; justify-content:flex-end; gap:.55rem; margin-top:1.1rem; padding-top:.9rem; border-top:1px solid #edf1f5; }
    .rpl-report .filter-error:empty, .rpl-report .notice:empty { display:none; }
    .rpl-report .kpi { padding:1rem 1.1rem; height:100%; }
    .rpl-report .kpi .label { color:#7a8a9b; font-size:.75rem; text-transform:uppercase; letter-spacing:.04em; }
    .rpl-report .kpi .value { font-size:1.35rem; font-weight:650; color:#26384b; font-variant-numeric:tabular-nums; }
    .rpl-report .kpi .hint { font-size:.75rem; color:#8291a3; }
    .rpl-report .kpi ol { margin:.3rem 0 0; padding-left:1.1rem; font-size:.82rem; }
    .rpl-report .tab-button { border:0; background:transparent; padding:.7rem 1rem; color:#66778a; }
    .rpl-report .tab-button.active { color:#188ae2; border-bottom:2px solid #188ae2; font-weight:600; }
    .rpl-report .table-wrap { max-height:70vh; overflow:auto; }
    .rpl-report table { font-size:.82rem; }
    .rpl-report thead th { position:sticky; top:0; z-index:2; background:#f4f7fa; white-space:nowrap; font-size:.72rem; text-transform:uppercase; letter-spacing:.03em; color:#637386; }
    .rpl-report .col-folio { position:sticky; left:0; z-index:1; background:#fff; min-width:170px; }
    .rpl-report thead .col-folio { z-index:3; background:#f4f7fa; }
    .rpl-report .num { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
    .rpl-report .steps-toggle { border:0; background:none; padding:0; color:#188ae2; }
    .rpl-report tr.steps-row td { background:#f9fbfd; }
    .rpl-report .timeline { list-style:none; margin:0; padding:0; }
    .rpl-report .timeline li { display:grid; grid-template-columns:2rem minmax(180px,1.4fr) minmax(220px,2fr) 5rem; gap:.6rem; padding:.45rem 0; border-bottom:1px dashed #e4ebf2; }
    .rpl-report .timeline li:last-child { border-bottom:0; }
    .rpl-report .timeline .order { color:#8291a3; }
    .rpl-report .bar { height:10px; border-radius:5px; background:#188ae2; min-width:2px; }
    .rpl-report .bar-row { display:grid; grid-template-columns:minmax(160px,220px) 1fr 9rem; gap:.75rem; align-items:center; padding:.35rem 0; }
    .rpl-report .busy { opacity:.62; pointer-events:none; }
    @media (max-width: 1199.98px) { .rpl-report .filter-field { grid-column:span 4; } .rpl-report .filter-field.wide { grid-column:span 8; } }
    @media (max-width: 767.98px) { .rpl-report .filter-field, .rpl-report .filter-field.wide { grid-column:span 12; } .rpl-report .timeline li { grid-template-columns:2rem 1fr; } .rpl-report .bar-row { grid-template-columns:1fr; } }
</style>
@endpush

@section('content')
<div class="container-fluid rpl-report py-3" id="requisitionPipelineReport">
    <div class="report-head p-4 mb-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="text-primary small fw-semibold text-uppercase">Compras · RC-01</div>
            <h1 class="h3 mb-1">Pipeline de requisiciones y tiempos de ciclo</h1>
            <p class="text-muted mb-0">Dónde está detenida cada requisición, con quién y cuántas horas lleva ahí. Las horas de ciclo son la suma de las horas de cada etapa (horas naturales).</p>
        </div>
        @can('reportes.requisition_pipeline.exportar')
            <div class="d-flex gap-2">
                <a class="btn btn-outline-primary js-export" data-format="csv" href="{{ route('requisition-pipeline-reports.export', ['format' => 'csv']) }}"><i class="ti ti-file-text me-1" aria-hidden="true"></i>CSV</a>
                <a class="btn btn-primary js-export" data-format="xlsx" href="{{ route('requisition-pipeline-reports.export', ['format' => 'xlsx']) }}"><i class="ti ti-file-spreadsheet me-1" aria-hidden="true"></i>Descargar Excel</a>
            </div>
        @endcan
    </div>

    <section class="report-card filter-card mb-3">
        <div class="filter-heading"><h2>Filtros del reporte</h2><p>Por defecto: requisiciones creadas en los últimos 90 días que no estén completadas ni canceladas.</p></div>
        <form id="rplFilters">
            <div class="filter-grid">
                <div class="filter-field"><label class="form-label" for="rplFrom">Creadas desde</label><input class="form-control" id="rplFrom" name="date_from" type="date" value="{{ now()->subDays(90)->toDateString() }}" data-default="{{ now()->subDays(90)->toDateString() }}"></div>
                <div class="filter-field"><label class="form-label" for="rplTo">Creadas hasta</label><input class="form-control" id="rplTo" name="date_to" type="date" value="{{ now()->toDateString() }}" data-default="{{ now()->toDateString() }}"></div>
                <div class="filter-field wide"><label class="form-label" for="rplStatuses">Estatus</label><select class="form-select js-multi" id="rplStatuses" name="statuses[]" multiple data-placeholder="Todos menos completadas y canceladas">@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(in_array($status->value, $defaultStatuses, true))>{{ $status->label() }}</option>@endforeach</select></div>
                <div class="filter-field"><label class="form-label" for="rplCompanies">Empresa(s)</label><select class="form-select js-multi" id="rplCompanies" name="company_ids[]" multiple data-placeholder="Todas las empresas">@foreach($companies as $company)<option value="{{ $company->id }}">{{ $company->name }}</option>@endforeach</select></div>
                <div class="filter-field"><label class="form-label" for="rplCenters">Centro(s) de costo</label><select class="form-select js-multi" id="rplCenters" name="cost_center_ids[]" multiple data-placeholder="Todos los centros">@foreach($centers as $center)<option value="{{ $center->id }}" data-company-id="{{ $center->company_id }}">{{ $center->code }} · {{ $center->name }}</option>@endforeach</select></div>
                <div class="filter-field"><label class="form-label" for="rplApprover">Pendiente con</label><select class="form-select" id="rplApprover" name="pending_approver_id"><option value="">Cualquiera</option>@foreach($approvers as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></div>
                <div class="filter-field"><label class="form-label" for="rplOlder">Antigüedad mayor a (días)</label><input class="form-control" id="rplOlder" name="older_than_days" type="number" min="0" max="3650" placeholder="0"></div>
                <div class="filter-field"><label class="form-label" for="rplAmountFrom">Importe cotizado desde</label><input class="form-control" id="rplAmountFrom" name="amount_from" type="number" min="0" step="0.01"></div>
                <div class="filter-field"><label class="form-label" for="rplAmountTo">Importe cotizado hasta</label><input class="form-control" id="rplAmountTo" name="amount_to" type="number" min="0" step="0.01"></div>
            </div>
            <div class="filter-footer"><button class="btn btn-light" type="reset">Limpiar filtros</button><button class="btn btn-primary" type="submit"><i class="ti ti-filter me-1" aria-hidden="true"></i>Aplicar filtros</button></div>
        </form>
        <div id="rplError" class="filter-error text-danger small mt-2" role="alert"></div>
    </section>

    <div class="row g-2 mb-3" id="rplKpis" aria-live="polite"></div>
    <div id="rplNotice" class="notice mb-3"></div>

    <section class="report-card">
        <div class="d-flex border-bottom px-2"><button class="tab-button active" type="button" data-tab="rows">Requisiciones</button><button class="tab-button" type="button" data-tab="summary">Horas por etapa y persona</button><span class="ms-auto align-self-center small text-muted pe-3" id="rplCount"></span></div>
        <div id="rowsPane">
            <div class="table-wrap"><table class="table table-hover mb-0"><thead><tr><th class="col-folio">Folio</th><th>Creada</th><th>Solicitante</th><th>Centro(s) de costo</th><th class="num" title="Lo cotizado en cotizaciones vigentes; la requisición no lleva precio">Importe</th><th>REPSE</th><th>Estatus</th><th>Etapa actual</th><th>Pendiente con</th><th class="num">Horas en etapa</th><th class="num">Horas de ciclo</th><th>Motivo</th><th>OC</th></tr></thead><tbody id="rplRows"><tr><td colspan="13" class="text-center text-muted py-4">Cargando datos…</td></tr></tbody></table></div>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3 border-top">
                <div class="d-flex align-items-center gap-2"><label class="small text-muted" for="rplPerPage">Filas</label><select class="form-select form-select-sm w-auto" id="rplPerPage"><option>25</option><option>50</option><option>100</option></select></div>
                <div class="d-flex align-items-center gap-2"><button class="btn btn-sm btn-outline-secondary" id="rplPrev" type="button">Anterior</button><span class="small text-muted" id="rplPageInfo"></span><button class="btn btn-sm btn-outline-secondary" id="rplNext" type="button">Siguiente</button></div>
            </div>
        </div>
        <div id="summaryPane" class="d-none p-3">
            <h2 class="h6">Horas promedio por etapa</h2>
            <div id="rplStageBars" class="mb-4"></div>
            <h2 class="h6">Por persona que resolvió la etapa</h2>
            <div class="table-wrap"><table class="table table-sm mb-0"><thead><tr><th>Persona</th><th class="num">Etapas</th><th class="num">Horas promedio</th><th class="num">Mediana</th></tr></thead><tbody id="rplApprovers"></tbody></table></div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const root = document.getElementById('requisitionPipelineReport');
    const form = document.getElementById('rplFilters');
    const body = document.getElementById('rplRows');
    const perPage = document.getElementById('rplPerPage');
    const stepsUrl = @json(url('reportes/compras/pipeline-requisiciones'));
    const defaultStatuses = @json($defaultStatuses);
    let page = 1;
    let currentRows = [];

    if (window.jQuery && jQuery.fn.select2) {
        jQuery('.js-multi').each(function () {
            jQuery(this).select2({ theme: 'bootstrap-5', width: '100%', placeholder: this.dataset.placeholder, closeOnSelect: false, dropdownParent: jQuery(document.body) });
        });
    }
    const companySelect = document.getElementById('rplCompanies');
    const centerSelect = document.getElementById('rplCenters');
    const allCenters = Array.from(centerSelect.options).map(o => o.cloneNode(true));
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
    const money = v => v === null || v === undefined ? '<span class="text-muted">Sin cotizar</span>' : fmt.format(v);
    const hours = v => v === null || v === undefined ? '<span class="text-muted" title="Creada antes de que el portal registrara el historial">Sin historial</span>' : `${Number(v).toLocaleString('es-MX', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} h`;
    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const dateTime = v => v ? new Date(v).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' }) : '—';

    function params(withPage = true) {
        const query = new URLSearchParams();
        for (const [key, value] of new FormData(form).entries()) if (value !== '') query.append(key, value);
        if (withPage) { query.set('page', page); query.set('per_page', perPage.value); }
        return query;
    }

    function row(r, index) {
        const toggle = r.has_history && r.step_count ? `<button type="button" class="steps-toggle" data-index="${index}" aria-expanded="false" title="Ver etapas"><i class="ti ti-chevron-right" aria-hidden="true"></i></button> ` : '';
        return `<tr><td class="col-folio">${toggle}<a href="${esc(r.url)}" target="_blank" rel="noopener">${esc(r.folio)}</a><small class="d-block text-muted">${esc(r.company_name)}</small></td>`
            + `<td class="text-nowrap">${dateTime(r.created_at)}<small class="d-block text-muted">${r.age_days} días</small></td><td>${esc(r.requester_name || '—')}</td><td style="min-width:180px">${esc(r.cost_centers || '—')}</td>`
            + `<td class="num">${money(r.estimated_amount)}</td><td>${r.is_repse ? '<span class="badge bg-warning text-dark">Sí</span>' : 'No'}</td>`
            + `<td><span class="badge bg-${esc(r.status_badge)}">${esc(r.status_label)}</span></td><td>${esc(r.current_step)}</td><td>${esc(r.pending_approver)}</td>`
            + `<td class="num fw-semibold">${hours(r.hours_in_current_step)}</td><td class="num">${hours(r.total_cycle_hours)}</td>`
            + `<td style="min-width:200px">${esc(r.outcome_reason || '')}</td><td>${esc(r.po_folios || '—')}</td></tr>`;
    }

    function stepsRow(timeline) {
        const items = timeline.steps.map(s => {
            const decisions = s.decisions.length ? `<small class="d-block text-muted">${s.decisions.map(d => `${esc(d.action)} · ${esc(d.actor || '')}${d.principal && d.principal !== d.actor ? ' por ' + esc(d.principal) : ''} · ${dateTime(d.acted_at)}`).join('<br>')}</small>` : '';
            const exit = s.is_open ? '<span class="badge bg-info">En curso</span>' : (s.exit_to ? `→ ${esc(s.exit_to)}${s.resolved_by ? ' · ' + esc(s.resolved_by) : ''}` : 'Fin del ciclo');
            return `<li><span class="order">${s.step_order}</span><span><strong>${esc(s.step_name)}</strong>${s.entered_by ? `<small class="d-block text-muted">Movió: ${esc(s.entered_by)}</small>` : ''}</span><span>${dateTime(s.entered_at)} – ${s.exited_at ? dateTime(s.exited_at) : 'ahora'}<small class="d-block">${exit}</small>${decisions}</span><span class="num">${hours(s.hours)}</span></li>`;
        }).join('');
        return `<tr class="steps-row"><td colspan="13"><ul class="timeline">${items}</ul><div class="small text-muted text-end pt-1">Ciclo: ${hours(timeline.total_cycle_hours)}</div></td></tr>`;
    }

    function renderKpis(k) {
        const card = (label, value, hint) => `<div class="col-6 col-md-3 col-xl"><div class="report-card kpi"><div class="label">${label}</div><div class="value">${value}</div><div class="hint">${hint}</div></div></div>`;
        const top = k.top_pending_approvers.length
            ? `<ol>${k.top_pending_approvers.map(a => `<li>${esc(a.name)} <span class="text-muted">(${a.count})</span></li>`).join('')}</ol>` : '<div class="hint">Sin pendientes</div>';
        document.getElementById('rplKpis').innerHTML =
            card('Requisiciones abiertas', k.open, `${k.requisitions} con estos filtros`)
            + card('Horas promedio en la etapa actual', k.avg_hours_in_current_step === null ? '—' : hours(k.avg_hours_in_current_step), 'Solo abiertas con historial')
            + `<div class="col-12 col-md-6 col-xl-4"><div class="report-card kpi"><div class="label">Con más pendientes</div>${top}</div></div>`;
        document.getElementById('rplNotice').innerHTML = k.without_history > 0
            ? `<div class="alert alert-info py-2 small mb-0">${k.without_history} requisición(es) se crearon antes de que el portal registrara el historial: se muestran sin horas y no cuentan en los promedios.</div>` : '';
    }

    function renderSummary(stages, approvers) {
        const max = Math.max(1, ...stages.map(s => s.avg_hours));
        document.getElementById('rplStageBars').innerHTML = stages.length
            ? stages.map(s => `<div class="bar-row"><span>${esc(s.name)} <small class="text-muted">(${s.steps})</small></span><div><div class="bar" style="width:${(s.avg_hours / max) * 100}%"></div></div><span class="num small">${hours(s.avg_hours)} · mediana ${hours(s.median_hours)}</span></div>`).join('')
            : '<p class="text-muted small mb-0">No hay etapas con historial en estos filtros.</p>';
        document.getElementById('rplApprovers').innerHTML = approvers.length
            ? approvers.map(a => `<tr><td>${esc(a.name)}</td><td class="num">${a.steps}</td><td class="num">${hours(a.avg_hours)}</td><td class="num">${hours(a.median_hours)}</td></tr>`).join('')
            : '<tr><td colspan="4" class="text-center text-muted py-3">Sin etapas resueltas.</td></tr>';
    }

    async function load() {
        root.classList.add('busy');
        try {
            const response = await fetch(`{{ route('requisition-pipeline-reports.data') }}?${params()}`, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error(response.status === 403 ? 'No tienes permiso para consultar este reporte.' : response.status === 422 ? 'Revisa los filtros: algún valor no es válido.' : 'No se pudo cargar el reporte. Inténtalo de nuevo.');
            const payload = await response.json();
            currentRows = payload.rows;
            page = payload.pagination.current_page;
            body.innerHTML = currentRows.length ? currentRows.map(row).join('') : '<tr><td colspan="13" class="text-center text-muted py-4">No hay requisiciones con estos filtros.</td></tr>';
            renderKpis(payload.kpis);
            renderSummary(payload.stages, payload.approvers);
            document.getElementById('rplCount').textContent = `${payload.pagination.total} requisiciones`;
            document.getElementById('rplPageInfo').textContent = `Página ${payload.pagination.current_page} de ${payload.pagination.last_page}`;
            document.getElementById('rplPrev').disabled = payload.pagination.current_page <= 1;
            document.getElementById('rplNext').disabled = payload.pagination.current_page >= payload.pagination.last_page;
            document.getElementById('rplError').textContent = '';
            document.querySelectorAll('.js-export').forEach(link => { link.href = `${stepsUrl}/export/${link.dataset.format}?${params(false)}`; });
        } catch (error) {
            document.getElementById('rplError').textContent = error.message;
            body.innerHTML = '<tr><td colspan="13" class="text-center text-danger py-4">No fue posible cargar el reporte.</td></tr>';
        } finally {
            root.classList.remove('busy');
        }
    }

    body.addEventListener('click', async event => {
        const button = event.target.closest('.steps-toggle');
        if (!button) return;
        const tr = button.closest('tr');
        if (tr.nextElementSibling?.classList.contains('steps-row')) {
            tr.nextElementSibling.remove();
            button.setAttribute('aria-expanded', 'false');
            button.innerHTML = '<i class="ti ti-chevron-right" aria-hidden="true"></i>';
            return;
        }
        const response = await fetch(`${stepsUrl}/${currentRows[button.dataset.index].id}/pasos`, { headers: { 'Accept': 'application/json' } });
        if (!response.ok) return;
        tr.insertAdjacentHTML('afterend', stepsRow(await response.json()));
        button.setAttribute('aria-expanded', 'true');
        button.innerHTML = '<i class="ti ti-chevron-down" aria-hidden="true"></i>';
    });
    document.querySelectorAll('.tab-button').forEach(button => button.addEventListener('click', () => {
        document.querySelectorAll('.tab-button').forEach(tab => tab.classList.toggle('active', tab === button));
        document.getElementById('rowsPane').classList.toggle('d-none', button.dataset.tab !== 'rows');
        document.getElementById('summaryPane').classList.toggle('d-none', button.dataset.tab !== 'summary');
    }));
    form.addEventListener('submit', event => { event.preventDefault(); page = 1; load(); });
    form.addEventListener('reset', event => {
        event.preventDefault();
        form.querySelectorAll('input').forEach(input => { input.value = input.dataset.default ?? ''; });
        document.getElementById('rplApprover').value = '';
        if (window.jQuery) {
            jQuery('#rplCompanies, #rplCenters').val(null).trigger('change');
            jQuery('#rplStatuses').val(defaultStatuses).trigger('change');
        } else {
            Array.from(document.getElementById('rplStatuses').options).forEach(o => { o.selected = defaultStatuses.includes(o.value); });
        }
        page = 1;
        load();
    });
    perPage.addEventListener('change', () => { page = 1; load(); });
    document.getElementById('rplPrev').addEventListener('click', () => { page = Math.max(1, page - 1); load(); });
    document.getElementById('rplNext').addEventListener('click', () => { page++; load(); });
    load();
})();
</script>
@endpush
