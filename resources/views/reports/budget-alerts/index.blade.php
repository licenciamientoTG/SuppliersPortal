@extends('layouts.zircos')

@section('title', 'Alertas presupuestales')

@push('styles')
<style>
    .bal-report { --bal-border:#e2e9f0; --bal-80:#f4a62a; --bal-90:#ef7d32; --bal-100:#d64545; }
    .bal-report .report-card { background:#fff; border:1px solid var(--bal-border); border-radius:.8rem; box-shadow:0 4px 16px rgba(24,54,82,.05); }
    .bal-report .report-head { background:#f7fbff; border:1px solid var(--bal-border); border-radius:.8rem; }
    .bal-report .filter-card { padding:1.25rem 1.4rem 1rem; }
    .bal-report .filter-heading h2 { margin:0; color:#26384b; font-size:1rem; font-weight:650; }
    .bal-report .filter-heading p { margin:.2rem 0 1.15rem; color:#8291a3; font-size:.82rem; }
    .bal-report .filter-grid { display:grid; grid-template-columns:repeat(12,minmax(0,1fr)); gap:1rem .9rem; }
    .bal-report .filter-field { grid-column:span 3; min-width:0; }
    .bal-report .filter-field .form-label { margin-bottom:.4rem; color:#536477; font-size:.78rem; font-weight:600; }
    .bal-report .filter-field .form-control, .bal-report .filter-field .form-select { min-height:43px; border-color:#dbe4ed; border-radius:.55rem; font-size:.87rem; }
    .bal-report .filter-field .select2-container { width:100%!important; }
    .bal-report .threshold-toggle .btn { min-height:43px; }
    .bal-report .filter-footer { display:flex; justify-content:flex-end; gap:.55rem; margin-top:1.1rem; padding-top:.9rem; border-top:1px solid #edf1f5; }
    .bal-report .filter-error:empty, .bal-report .notice:empty { display:none; }
    .bal-report .kpi { padding:1rem 1.1rem; height:100%; }
    .bal-report .kpi .label { color:#7a8a9b; font-size:.75rem; text-transform:uppercase; letter-spacing:.04em; }
    .bal-report .kpi .value { font-size:1.35rem; font-weight:650; color:#26384b; font-variant-numeric:tabular-nums; }
    .bal-report .kpi .hint { font-size:.75rem; color:#8291a3; }
    .bal-report .kpi.level-80 { border-left:4px solid var(--bal-80); } .bal-report .kpi.level-90 { border-left:4px solid var(--bal-90); } .bal-report .kpi.level-100 { border-left:4px solid var(--bal-100); }
    .bal-report .tab-button { border:0; background:transparent; padding:.7rem 1rem; color:#66778a; }
    .bal-report .tab-button.active { color:#188ae2; border-bottom:2px solid #188ae2; font-weight:600; }
    .bal-report .table-wrap { max-height:70vh; overflow:auto; }
    .bal-report table { font-size:.82rem; }
    .bal-report thead th { position:sticky; top:0; z-index:2; background:#f4f7fa; white-space:nowrap; font-size:.72rem; text-transform:uppercase; letter-spacing:.03em; color:#637386; }
    .bal-report .col-line { position:sticky; left:0; z-index:1; background:#fff; min-width:250px; max-width:320px; }
    .bal-report thead .col-line { z-index:3; background:#f4f7fa; }
    .bal-report .amount { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
    .bal-report .amount.negative { color:#c83d4d; font-weight:600; }
    .bal-report .level { display:inline-block; min-width:3.6rem; text-align:center; color:#fff; }
    .bal-report .level-80 .level, .bal-report .level.l80 { background:var(--bal-80); } .bal-report .level.l90 { background:var(--bal-90); } .bal-report .level.l100 { background:var(--bal-100); } .bal-report .level.lexc { background:#6c7a89; }
    .bal-report .meter { width:120px; height:8px; border-radius:4px; background:#eef2f6; overflow:hidden; }
    .bal-report .meter span { display:block; height:100%; }
    .bal-report .docs-toggle { border:0; background:none; padding:0; color:#188ae2; text-decoration:underline dotted; text-underline-offset:3px; }
    .bal-report tr.docs-row td { background:#f9fbfd; }
    .bal-report .busy { opacity:.62; pointer-events:none; }
    @media (max-width: 1199.98px) { .bal-report .filter-field { grid-column:span 4; } }
    @media (max-width: 767.98px) { .bal-report .filter-field { grid-column:span 6; } }
    @media (max-width: 575.98px) { .bal-report .filter-field { grid-column:span 12; } .bal-report .col-line { min-width:200px; } }
</style>
@endpush

@section('content')
<div class="container-fluid bal-report py-3" id="budgetAlertsReport">
    <div class="report-head p-4 mb-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="text-primary small fw-semibold text-uppercase">Presupuesto · RP-03</div>
            <h1 class="h3 mb-1">Alertas de agotamiento, sobregiro y excepciones</h1>
            <p class="text-muted mb-0">Renglones mensuales que ya cruzaron el umbral o están sobregirados, con el ritmo de gasto y cuándo se agotaría el presupuesto. Usa el mismo cálculo que RP-01 y que el bloqueo.</p>
        </div>
        @can('reportes.budget_alerts.exportar')
            <div class="d-flex gap-2">
                <a class="btn btn-outline-primary js-export" data-format="csv" href="{{ route('budget-alert-reports.export', ['format' => 'csv']) }}"><i class="ti ti-file-text me-1" aria-hidden="true"></i>CSV</a>
                <a class="btn btn-primary js-export" data-format="xlsx" href="{{ route('budget-alert-reports.export', ['format' => 'xlsx']) }}"><i class="ti ti-file-spreadsheet me-1" aria-hidden="true"></i>Descargar Excel</a>
            </div>
        @endcan
    </div>

    <section class="report-card filter-card mb-3">
        <div class="filter-heading"><h2>Filtros del reporte</h2><p>Elige desde qué porcentaje de consumo quieres ver los renglones.</p></div>
        <form id="balFilters">
            <div class="filter-grid">
                <div class="filter-field"><label class="form-label" for="balYear">Ejercicio</label><input class="form-control" id="balYear" name="fiscal_year" type="number" min="2020" max="2100" value="{{ now()->year }}" required></div>
                <div class="filter-field"><span class="form-label d-block">Umbral de consumo</span><div class="btn-group w-100 threshold-toggle" role="group" aria-label="Umbral de consumo">@foreach([80, 90, 100] as $threshold)<input type="radio" class="btn-check" name="threshold" id="threshold{{ $threshold }}" value="{{ $threshold }}" @checked($threshold === 80)><label class="btn btn-outline-primary" for="threshold{{ $threshold }}">{{ $threshold === 100 ? '≥ 100 %' : $threshold.' %' }}</label>@endforeach</div></div>
                <div class="filter-field"><label class="form-label" for="balMonths">Mes(es) del renglón</label><select class="form-select js-multi" id="balMonths" name="months[]" multiple data-placeholder="Todos los meses">@foreach(range(1, 12) as $month)<option value="{{ $month }}">{{ ucfirst(\Carbon\Carbon::create(null, $month, 1)->locale('es')->monthName) }}</option>@endforeach</select></div>
                <div class="filter-field"><label class="form-label" for="balResponsible">Responsable</label><select class="form-select" id="balResponsible" name="responsible_user_id"><option value="">Todos</option>@foreach($responsibles as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></div>
                <div class="filter-field"><label class="form-label" for="balCompanies">Empresa(s)</label><select class="form-select js-multi" id="balCompanies" name="company_ids[]" multiple data-placeholder="Todas las empresas">@foreach($companies as $company)<option value="{{ $company->id }}">{{ $company->name }}</option>@endforeach</select></div>
                <div class="filter-field"><label class="form-label" for="balCenters">Centro(s) de costo</label><select class="form-select js-multi" id="balCenters" name="cost_center_ids[]" multiple data-placeholder="Todos los centros">@foreach($centers as $center)<option value="{{ $center->id }}" data-company-id="{{ $center->company_id }}">{{ $center->code }} · {{ $center->name }}</option>@endforeach</select></div>
                <div class="filter-field"><label class="form-label" for="balExFrom">Excepciones desde</label><input class="form-control" id="balExFrom" name="exceptions_from" type="date"></div>
                <div class="filter-field"><label class="form-label" for="balExTo">Excepciones hasta</label><input class="form-control" id="balExTo" name="exceptions_to" type="date"></div>
                <div class="filter-field"><label class="form-label" for="balExStatus">Estatus de excepción</label><select class="form-select" id="balExStatus" name="exception_status"><option value="">Todos</option><option value="PENDING">Pendiente</option><option value="APPROVED">Aprobada</option><option value="REJECTED">Rechazada</option></select></div>
            </div>
            <div class="filter-footer"><button class="btn btn-light" type="reset">Limpiar filtros</button><button class="btn btn-primary" type="submit"><i class="ti ti-filter me-1" aria-hidden="true"></i>Aplicar filtros</button></div>
        </form>
        <div id="balError" class="filter-error text-danger small mt-2" role="alert"></div>
    </section>

    <div class="row g-2 mb-3" id="balKpis" aria-live="polite"></div>
    <div id="balNotice" class="notice mb-3"></div>

    <section class="report-card">
        <div class="d-flex border-bottom px-2"><button class="tab-button active" type="button" data-tab="risk">Renglones en riesgo</button><button class="tab-button" type="button" data-tab="exceptions">Excepciones</button><span class="ms-auto align-self-center small text-muted pe-3" id="balCount"></span></div>
        <div id="riskPane">
            <div class="table-wrap"><table class="table table-hover mb-0"><thead><tr><th class="col-line">Centro de costo / renglón</th><th>Mes</th><th>Nivel</th><th>Consumo</th><th class="amount">Vigente</th><th class="amount">Consumido</th><th class="amount">Disponible</th><th class="amount" title="Promedio de lo ejercido en los últimos 3 meses cerrados del renglón">Ritmo 3 meses</th><th title="Disponible que queda en el año ÷ ritmo de gasto">Agotamiento</th><th class="amount" title="Cotizaciones en aprobación y OCD por autorizar; ya apartan presupuesto">En trámite</th><th class="amount" title="Disponible + documentos en trámite">Si se rechazan</th></tr></thead><tbody id="balRows"><tr><td colspan="11" class="text-center text-muted py-4">Cargando datos…</td></tr></tbody></table></div>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3 border-top">
                <div class="d-flex align-items-center gap-2"><label class="small text-muted" for="balPerPage">Filas</label><select class="form-select form-select-sm w-auto" id="balPerPage"><option>25</option><option>50</option><option>100</option></select></div>
                <div class="d-flex align-items-center gap-2"><button class="btn btn-sm btn-outline-secondary" id="balPrev" type="button">Anterior</button><span class="small text-muted" id="balPageInfo"></span><button class="btn btn-sm btn-outline-secondary" id="balNext" type="button">Siguiente</button></div>
            </div>
        </div>
        <div id="exceptionsPane" class="d-none">
            <div class="table-wrap"><table class="table table-hover mb-0"><thead><tr><th class="col-line">Documento / renglón</th><th class="amount">Importe partida</th><th class="amount">Excedente</th><th>Motivo</th><th>Solicitó</th><th>Autorizó</th><th>Estatus</th><th>Decisión</th></tr></thead><tbody id="balExceptions"><tr><td colspan="8" class="text-center text-muted py-4">Cargando datos…</td></tr></tbody></table></div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const root = document.getElementById('budgetAlertsReport');
    const form = document.getElementById('balFilters');
    const body = document.getElementById('balRows');
    const perPage = document.getElementById('balPerPage');
    const canDecide = @json(auth()->user()->hasRole('general_director') && auth()->user()->can('reportes.budget_alerts.excepcion.aprobar'));
    const decideUrl = @json(url('reportes/presupuesto/alertas/excepciones'));
    let page = 1;
    let currentLines = [];

    if (window.jQuery && jQuery.fn.select2) {
        jQuery('.js-multi').each(function () {
            jQuery(this).select2({ theme: 'bootstrap-5', width: '100%', placeholder: this.dataset.placeholder, closeOnSelect: false, dropdownParent: jQuery(document.body) });
        });
    }
    const companySelect = document.getElementById('balCompanies');
    const centerSelect = document.getElementById('balCenters');
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
    const money = v => v === null || v === undefined ? '—' : fmt.format(v);
    const pct = v => v === null || v === undefined ? '—' : `${(v * 100).toFixed(1)} %`;
    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const monthName = ym => { if (!ym) return '—'; const [y, m] = ym.split('-'); return new Date(+y, +m - 1, 1).toLocaleDateString('es-MX', { month: 'short', year: 'numeric' }); };
    const STATUS = { PENDING: ['bg-warning text-dark', 'Pendiente'], APPROVED: ['bg-success', 'Aprobada'], REJECTED: ['bg-secondary', 'Rechazada'] };

    function params(withPage = true) {
        const query = new URLSearchParams();
        for (const [key, value] of new FormData(form).entries()) if (value !== '') query.append(key, value);
        if (withPage) { query.set('page', page); query.set('per_page', perPage.value); }
        return query;
    }

    function level(row) {
        if (!row.alert_level) return '<span class="badge level lexc" title="Aparece por tener una excepción aprobada">Excepción</span>';
        return `<span class="badge level l${row.alert_level}">${row.alert_level === 100 ? '≥ 100 %' : row.alert_level + ' %'}</span>`;
    }

    function meter(row) {
        const value = row.progress_pct === null ? 1 : row.progress_pct;
        const color = value >= 1 ? 'var(--bal-100)' : (value >= .9 ? 'var(--bal-90)' : 'var(--bal-80)');
        return `<div class="meter"><span style="width:${Math.min(100, value * 100)}%;background:${color}"></span></div><span class="small">${pct(row.progress_pct)}</span>`;
    }

    function exhaustion(row) {
        if (row.burn_rate_3m === null) return '<span class="small text-muted">Sin meses cerrados</span>';
        if (row.months_to_exhaustion === null) return '<span class="small text-muted">Sin gasto reciente</span>';
        if (row.months_to_exhaustion === 0) return '<span class="text-danger fw-semibold">Agotado</span>';
        return `${monthName(row.projected_exhaustion_month)}<small class="d-block text-muted">${row.months_to_exhaustion} meses · quedan ${money(row.remaining_year_available)}</small>`;
    }

    function lineRow(row, index) {
        const pending = row.pending_docs_count
            ? `<button type="button" class="docs-toggle" data-index="${index}">${money(row.pending_docs_amount)}</button><small class="d-block text-muted">${row.pending_docs_count} doc.</small>`
            : '<span class="text-muted">—</span>';
        const exceptions = row.approved_exceptions ? `<small class="d-block text-muted">${row.approved_exceptions} excepción(es) aprobada(s)</small>` : '';
        return `<tr><td class="col-line"><small class="d-block text-muted">${esc(row.company_name)} · ${esc(row.cost_center_code)} ${esc(row.cost_center_name)}</small>${esc(row.budget_line_code)} · ${esc(row.budget_line_name)}${row.budget_cedula_name ? `<small class="d-block text-muted">${esc(row.budget_cedula_name)}</small>` : ''}${row.responsible_name ? `<small class="d-block text-muted">Responsable: ${esc(row.responsible_name)}</small>` : ''}${exceptions}</td>`
            + `<td class="text-nowrap">${monthName(`${row.fiscal_year}-${String(row.month).padStart(2, '0')}`)}</td><td>${level(row)}</td><td>${meter(row)}</td>`
            + `<td class="amount">${money(row.current_budget)}</td><td class="amount">${money(row.consumed_total)}</td><td class="amount fw-semibold ${row.available < 0 ? 'negative' : ''}">${money(row.available)}</td>`
            + `<td class="amount">${row.burn_rate_3m === null ? '—' : money(row.burn_rate_3m) + '<small class="d-block text-muted">por mes</small>'}</td><td>${exhaustion(row)}</td>`
            + `<td class="amount">${pending}</td><td class="amount ${row.available_if_rejected < 0 ? 'negative' : ''}">${row.pending_docs_count ? money(row.available_if_rejected) : '—'}</td></tr>`;
    }

    function docsRow(row) {
        const items = row.pending_documents.map(d => `<li>${esc(d.type)} ${d.url ? `<a href="${esc(d.url)}" target="_blank" rel="noopener">${esc(d.folio || 'sin folio')}</a>` : esc(d.folio || 'sin folio')} · ${money(d.amount)}</li>`).join('');
        return `<tr class="docs-row"><td colspan="11"><div class="small"><strong>Documentos en trámite</strong> (ya apartan presupuesto; si se rechazan, el disponible sería ${money(row.available_if_rejected)}):<ul class="mb-0">${items}</ul></div></td></tr>`;
    }

    function exceptionRow(e) {
        const [cls, label] = STATUS[e.status] || ['bg-secondary', e.status];
        const decide = canDecide && e.status === 'PENDING'
            ? `<button type="button" class="btn btn-sm btn-success js-decide" data-id="${e.id}" data-decision="APPROVED">Aprobar</button> <button type="button" class="btn btn-sm btn-outline-danger js-decide" data-id="${e.id}" data-decision="REJECTED">Rechazar</button>`
            : (e.decision_comment ? `<small>${esc(e.decision_comment)}</small>` : '—');
        const date = v => v ? new Date(v).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' }) : '';
        return `<tr><td class="col-line">${esc(e.document_type)} ${e.document_url ? `<a href="${esc(e.document_url)}" target="_blank" rel="noopener">${esc(e.document_folio)}</a>` : esc(e.document_folio)} <small class="text-muted">partida ${esc(e.document_line_id)}</small><small class="d-block text-muted">${esc(e.cost_center)} · ${esc(e.budget_line)}${e.budget_cedula ? ' / ' + esc(e.budget_cedula) : ''} · ${monthName(e.application_month)}</small>${e.complete ? '' : '<span class="badge bg-danger">Registro incompleto</span>'}</td>`
            + `<td class="amount">${money(e.line_amount)}<small class="d-block text-muted">disponible ${money(e.available_at_request)}</small></td><td class="amount">${money(e.approved_excess ?? e.requested_excess)}<small class="d-block text-muted">${e.approved_excess === null ? 'solicitado' : 'aprobado'}</small></td>`
            + `<td style="min-width:220px">${esc(e.reason)}</td><td>${esc(e.requester || '—')}<small class="d-block text-muted">${date(e.requested_at)}</small></td><td>${esc(e.decider || '—')}<small class="d-block text-muted">${date(e.decided_at)}</small></td>`
            + `<td><span class="badge ${cls}">${label}</span></td><td class="text-nowrap">${decide}</td></tr>`;
    }

    function renderKpis(k) {
        const card = (label, value, hint, cls = '') => `<div class="col-6 col-md-4 col-xl"><div class="report-card kpi ${cls}"><div class="label">${label}</div><div class="value">${value}</div><div class="hint">${hint}</div></div></div>`;
        document.getElementById('balKpis').innerHTML =
            card('En 80 %', k.level_80, 'Renglones entre 80 y 90 %', 'level-80')
            + card('En 90 %', k.level_90, 'Renglones entre 90 y 100 %', 'level-90')
            + card('≥ 100 % o sobregiro', k.level_100, 'Ya no pueden comprometer', 'level-100')
            + card('Excepciones aprobadas', money(k.approved_exceptions_amount), `${k.approved_exceptions} en el periodo`)
            + card('Por decidir', k.pending_exceptions, 'Excepciones pendientes de Dirección');
        document.getElementById('balNotice').innerHTML = k.incomplete_exceptions > 0
            ? `<div class="alert alert-danger py-2 small mb-0">${k.incomplete_exceptions} excepción(es) aprobada(s) no tienen autorizador, motivo o fecha de decisión. Revísalas.</div>` : '';
    }

    async function load() {
        root.classList.add('busy');
        try {
            const response = await fetch(`{{ route('budget-alert-reports.data') }}?${params()}`, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error(response.status === 403 ? 'No tienes permiso para consultar este reporte.' : response.status === 422 ? 'Revisa los filtros: algún valor no es válido.' : 'No se pudo cargar el reporte. Inténtalo de nuevo.');
            const payload = await response.json();
            currentLines = payload.lines;
            page = payload.pagination.current_page;
            body.innerHTML = currentLines.length ? currentLines.map(lineRow).join('') : '<tr><td colspan="11" class="text-center text-muted py-4">Ningún renglón alcanza el umbral con estos filtros.</td></tr>';
            document.getElementById('balExceptions').innerHTML = payload.exceptions.length ? payload.exceptions.map(exceptionRow).join('') : '<tr><td colspan="8" class="text-center text-muted py-4">No hay excepciones en el periodo.</td></tr>';
            renderKpis(payload.kpis);
            document.getElementById('balCount').textContent = `${payload.pagination.total} renglones · ${payload.exceptions.length} excepciones`;
            document.getElementById('balPageInfo').textContent = `Página ${payload.pagination.current_page} de ${payload.pagination.last_page}`;
            document.getElementById('balPrev').disabled = payload.pagination.current_page <= 1;
            document.getElementById('balNext').disabled = payload.pagination.current_page >= payload.pagination.last_page;
            document.getElementById('balError').textContent = '';
            document.querySelectorAll('.js-export').forEach(link => { link.href = `{{ url('reportes/presupuesto/alertas/export') }}/${link.dataset.format}?${params(false)}`; });
        } catch (error) {
            document.getElementById('balError').textContent = error.message;
            body.innerHTML = '<tr><td colspan="11" class="text-center text-danger py-4">No fue posible cargar el reporte.</td></tr>';
        } finally {
            root.classList.remove('busy');
        }
    }

    async function decide(id, decision) {
        const approve = decision === 'APPROVED';
        const result = await Swal.fire({
            icon: approve ? 'question' : 'warning',
            title: approve ? '¿Aprobar esta excepción?' : '¿Rechazar esta excepción?',
            html: approve
                ? 'Se autorizará comprar por encima del presupuesto disponible. Quedará registrado tu nombre, la fecha y el comentario.'
                : 'La compra no podrá exceder el presupuesto disponible. Quedará registrado tu nombre, la fecha y el comentario.',
            input: 'textarea',
            inputLabel: 'Comentario de Dirección General (opcional)',
            inputAttributes: { maxlength: 2000 },
            showCancelButton: true,
            confirmButtonText: approve ? 'Sí, aprobar' : 'Sí, rechazar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            focusCancel: true,
            customClass: { confirmButton: approve ? 'btn btn-success ms-2' : 'btn btn-danger ms-2', cancelButton: 'btn btn-light' },
            buttonsStyling: false,
        });
        if (!result.isConfirmed) return;
        const response = await fetch(`${decideUrl}/${id}/decision`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) }, body: JSON.stringify({ decision, comment: result.value || '' }) });
        if (!response.ok) {
            Swal.fire({ icon: 'error', title: 'No se guardó la decisión', text: 'Puede que otra persona ya la haya decidido. Recarga el reporte e inténtalo de nuevo.', customClass: { confirmButton: 'btn btn-primary' }, buttonsStyling: false });
            return;
        }
        load();
    }

    body.addEventListener('click', event => {
        const button = event.target.closest('.docs-toggle');
        if (!button) return;
        const row = button.closest('tr');
        if (row.nextElementSibling?.classList.contains('docs-row')) { row.nextElementSibling.remove(); return; }
        row.insertAdjacentHTML('afterend', docsRow(currentLines[button.dataset.index]));
    });
    document.getElementById('balExceptions').addEventListener('click', event => {
        const button = event.target.closest('.js-decide');
        if (button) decide(button.dataset.id, button.dataset.decision);
    });
    document.querySelectorAll('.tab-button').forEach(button => button.addEventListener('click', () => {
        document.querySelectorAll('.tab-button').forEach(tab => tab.classList.toggle('active', tab === button));
        document.getElementById('riskPane').classList.toggle('d-none', button.dataset.tab !== 'risk');
        document.getElementById('exceptionsPane').classList.toggle('d-none', button.dataset.tab !== 'exceptions');
    }));
    form.addEventListener('submit', event => { event.preventDefault(); page = 1; load(); });
    form.addEventListener('reset', () => setTimeout(() => { if (window.jQuery) jQuery('.js-multi').val(null).trigger('change'); page = 1; load(); }, 0));
    perPage.addEventListener('change', () => { page = 1; load(); });
    document.getElementById('balPrev').addEventListener('click', () => { page = Math.max(1, page - 1); load(); });
    document.getElementById('balNext').addEventListener('click', () => { page++; load(); });
    load();
})();
</script>
@endpush
