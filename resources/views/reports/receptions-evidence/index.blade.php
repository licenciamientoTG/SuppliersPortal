@extends('layouts.zircos')

@section('title', 'Recepciones y su evidencia')

@push('styles')
<style>
    .rev-report { --rev-border:#e2e9f0; }
    .rev-report .report-card { background:#fff; border:1px solid var(--rev-border); border-radius:.8rem; box-shadow:0 4px 16px rgba(24,54,82,.05); }
    .rev-report .report-head { background:#f7fbff; border:1px solid var(--rev-border); border-radius:.8rem; }
    .rev-report .filter-card { padding:1.25rem 1.4rem 1rem; }
    .rev-report .filter-heading h2 { margin:0; color:#26384b; font-size:1rem; font-weight:650; }
    .rev-report .filter-heading p { margin:.2rem 0 1.15rem; color:#8291a3; font-size:.82rem; }
    .rev-report .filter-grid { display:grid; grid-template-columns:repeat(12,minmax(0,1fr)); gap:1rem .9rem; }
    .rev-report .filter-field { grid-column:span 3; min-width:0; }
    .rev-report .filter-field .form-label { margin-bottom:.4rem; color:#536477; font-size:.78rem; font-weight:600; }
    .rev-report .filter-field .form-control, .rev-report .filter-field .form-select { min-height:43px; border-color:#dbe4ed; border-radius:.55rem; font-size:.87rem; }
    .rev-report .filter-field .select2-container { width:100%!important; }
    .rev-report .filter-footer { display:flex; justify-content:flex-end; gap:.55rem; margin-top:1.1rem; padding-top:.9rem; border-top:1px solid #edf1f5; }
    .rev-report .filter-error:empty { display:none; }
    .rev-report .kpi { padding:1rem 1.1rem; height:100%; }
    .rev-report .kpi .label { color:#7a8a9b; font-size:.75rem; text-transform:uppercase; letter-spacing:.04em; }
    .rev-report .kpi .value { font-size:1.35rem; font-weight:650; color:#26384b; font-variant-numeric:tabular-nums; }
    .rev-report .kpi .hint { font-size:.75rem; color:#8291a3; }
    .rev-report .table-wrap { max-height:70vh; overflow:auto; }
    .rev-report table { font-size:.82rem; }
    .rev-report thead th { position:sticky; top:0; z-index:2; background:#f4f7fa; white-space:nowrap; font-size:.72rem; text-transform:uppercase; letter-spacing:.03em; color:#637386; }
    .rev-report .num { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
    .rev-report .busy { opacity:.62; pointer-events:none; }
    .rev-report .attachment-row { background:#f9fbfd; }
    .rev-report .attachment-row td { padding:.5rem .75rem; }
    .rev-report .hash { font-family: monospace; font-size:.74rem; word-break:break-all; }
    .rev-report .js-toggle-detail { cursor:pointer; }
    @media (max-width: 1199.98px) { .rev-report .filter-field { grid-column:span 4; } }
    @media (max-width: 767.98px) { .rev-report .filter-field { grid-column:span 12; } }
</style>
@endpush

@section('content')
<div class="container-fluid rev-report py-3" id="receptionsEvidenceReport">
    <div class="report-head p-4 mb-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="text-primary small fw-semibold text-uppercase">Recepciones · RR-01</div>
            <h1 class="h3 mb-1">Recepciones y su evidencia</h1>
            <p class="text-muted mb-0">Una fila por evento de recepción, con sus archivos de evidencia (remisión y fotos) y su SHA-256.</p>
        </div>
        @can('reportes.receptions_evidence.exportar')
            <div class="d-flex gap-2">
                <a class="btn btn-outline-primary js-export" data-format="csv" href="{{ route('receptions-evidence-reports.export', ['format' => 'csv']) }}"><i class="ti ti-file-text me-1" aria-hidden="true"></i>CSV</a>
                <a class="btn btn-primary js-export" data-format="xlsx" href="{{ route('receptions-evidence-reports.export', ['format' => 'xlsx']) }}"><i class="ti ti-file-spreadsheet me-1" aria-hidden="true"></i>Descargar Excel</a>
            </div>
        @endcan
    </div>

    <section class="report-card filter-card mb-3">
        <div class="filter-heading"><h2>Filtros del reporte</h2><p>Por defecto: todas las recepciones registradas, sin acotar fechas.</p></div>
        <form id="revFilters">
            <div class="filter-grid">
                <div class="filter-field"><label class="form-label" for="revCompanies">Empresa(s)</label><select class="form-select js-multi" id="revCompanies" name="company_ids[]" multiple data-placeholder="Todas las empresas">@foreach($companies as $company)<option value="{{ $company->id }}">{{ $company->name }}</option>@endforeach</select></div>
                <div class="filter-field"><label class="form-label" for="revLocations">Ubicación</label><select class="form-select js-multi" id="revLocations" name="location_ids[]" multiple data-placeholder="Todas">@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></div>
                <div class="filter-field"><label class="form-label" for="revType">Tipo</label><select class="form-select js-multi" id="revType" name="reception_type[]" multiple data-placeholder="Todos"><option value="BIEN">Bien</option><option value="SERVICIO">Servicio</option></select></div>
                <div class="filter-field"><label class="form-label" for="revReceiver">Recibió / validó</label><select class="form-select js-single" id="revReceiver" name="received_by" data-placeholder="Todos"><option value=""></option>@foreach($receivers as $receiver)<option value="{{ $receiver->id }}">{{ $receiver->name }}</option>@endforeach</select></div>
                <div class="filter-field"><label class="form-label" for="revFrom">Del</label><input class="form-control" id="revFrom" name="date_from" type="date"></div>
                <div class="filter-field"><label class="form-label" for="revTo">al</label><input class="form-control" id="revTo" name="date_to" type="date"></div>
                <div class="filter-field">
                    <label class="form-label" for="revEvidence">Evidencia</label>
                    <select class="form-select" id="revEvidence" name="evidence_filter">
                        <option value="TODAS">Todas</option>
                        <option value="COMPLETA">Completa</option>
                        <option value="INCOMPLETA">Incompleta</option>
                    </select>
                </div>
            </div>
            <div class="filter-footer"><button class="btn btn-light" type="reset">Limpiar filtros</button><button class="btn btn-primary" type="submit"><i class="ti ti-filter me-1" aria-hidden="true"></i>Aplicar filtros</button></div>
        </form>
        <div id="revError" class="filter-error text-danger small mt-2" role="alert"></div>
    </section>

    <div class="alert alert-warning mb-3" role="note">
        No existe un segundo rol de "validación" en el sistema: quien recibe es quien valida (mismo acto, mismo usuario). No existe el tipo de recepción HITO.
    </div>

    <div class="row g-2 mb-3" id="revKpis" aria-live="polite"></div>

    <section class="report-card">
        <div class="d-flex border-bottom px-3 py-2 align-items-center"><span class="small text-muted" id="revCount"></span></div>
        <div class="table-wrap">
            <table class="table table-hover mb-0">
                <thead><tr><th></th><th>Folio recepción</th><th>Tipo</th><th>Folio OC/OCD</th><th>Proveedor</th><th>Tipo (bien/servicio)</th><th>Fecha</th><th>Ubicación</th><th>Recibió / validó</th><th>Segregación</th><th class="num">Evidencia</th><th>Completa</th></tr></thead>
                <tbody id="revRows"><tr><td colspan="12" class="text-center text-muted py-4">Cargando datos…</td></tr></tbody>
            </table>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const root = document.getElementById('receptionsEvidenceReport');
    const form = document.getElementById('revFilters');
    const body = document.getElementById('revRows');
    const baseUrl = @json(url('reportes/recepciones/evidencia'));
    const verifyBaseUrl = @json(url('reportes/recepciones/evidencia/adjuntos'));
    const csrfToken = @json(csrf_token());

    if (window.jQuery && jQuery.fn.select2) {
        jQuery('.js-multi, .js-single').each(function () {
            jQuery(this).select2({ theme: 'bootstrap-5', width: '100%', placeholder: this.dataset.placeholder, closeOnSelect: this.classList.contains('js-single'), dropdownParent: jQuery(document.body), allowClear: this.classList.contains('js-single') });
        });
    }

    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const date = v => v ? new Date(v).toLocaleString('es-MX') : '<span class="text-muted">—</span>';

    function params(withSuffix = true) {
        const query = new URLSearchParams();
        const data = new FormData(form);
        for (const [key, value] of data.entries()) if (value !== '') query.append(key, value);
        return withSuffix ? query : query;
    }

    function attachmentRow(reception, attachment, index) {
        const attachmentId = `${reception.reception_id}-${index}`;
        return `<tr class="attachment-row" data-attachment-parent="${reception.reception_id}">
            <td>${esc(attachment.attachable_type)} · ${esc(attachment.file_name)}</td>
            <td class="hash">${esc(attachment.sha256)} <span class="badge bg-light text-dark">${esc(attachment.hash_origin)}</span></td>
            <td>${date(attachment.uploaded_at)} · ${esc(attachment.uploaded_by_name)}</td>
            <td>
                <button type="button" class="btn btn-sm btn-outline-secondary js-verify-hash" data-attachment-id="${attachment.id ?? ''}" data-target="verify-${attachmentId}">Verificar hash</button>
                <span id="verify-${attachmentId}" class="small ms-2"></span>
            </td>
        </tr>`;
    }

    function attachmentsTable(reception) {
        const rows = (reception.attachments || []).map((a, i) => attachmentRow(reception, a, i)).join('');
        if (! rows) {
            return '<em class="text-muted small">Sin archivos registrados.</em>';
        }

        return `<table class="table table-sm mb-0">
            <thead><tr><th>Archivo</th><th>SHA-256</th><th>Cargado</th><th>Verificación</th></tr></thead>
            <tbody>${rows}</tbody>
        </table>`;
    }

    function row(r) {
        const sod = r.sod_flag ? '<span class="badge bg-danger">Sí</span>' : '<span class="text-muted">No</span>';
        const complete = r.evidence_complete ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-warning text-dark">No</span>';
        const main = `<tr>
            <td><button type="button" class="btn btn-sm btn-link js-toggle-detail" data-target="detail-${r.reception_id}">+</button></td>
            <td>${esc(r.reception_folio)}</td>
            <td><span class="badge bg-secondary">${esc(r.order_type)}</span></td>
            <td><a href="${esc(r.order_url)}" target="_blank" rel="noopener">${esc(r.po_folio)}</a></td>
            <td>${esc(r.supplier_name)}</td>
            <td>${esc(r.reception_type)}</td>
            <td>${date(r.reception_date)}</td>
            <td>${esc(r.location_name)}</td>
            <td>${esc(r.received_by_name)}</td>
            <td>${sod}</td>
            <td class="num">${r.evidence_count}</td>
            <td>${complete}</td>
        </tr>`;
        const detail = `<tr class="d-none" id="detail-${r.reception_id}" data-detail-for="${r.reception_id}">
            <td></td><td colspan="11">${attachmentsTable(r)}</td>
        </tr>`;

        return main + detail;
    }

    function renderKpis(k) {
        const card = (label, value, hint) => `<div class="col-6 col-md-3 col-xl"><div class="report-card kpi"><div class="label">${label}</div><div class="value">${value}</div><div class="hint">${hint ?? ''}</div></div></div>`;
        document.getElementById('revKpis').innerHTML =
            card('Recepciones', k.total_receptions)
            + card('% evidencia completa', k.evidence_complete_pct.toFixed(1) + '%', `${k.evidence_complete_count} de ${k.total_receptions}`)
            + card('Conflictos de segregación', k.sod_conflicts_count, 'recibió = solicitó la orden');
    }

    async function load() {
        root.classList.add('busy');
        try {
            const response = await fetch(`${baseUrl}/data?${params()}`, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error(response.status === 403 ? 'No tienes permiso para consultar este reporte.' : response.status === 422 ? 'Revisa los filtros: algún valor no es válido.' : 'No se pudo cargar el reporte. Inténtalo de nuevo.');
            const payload = await response.json();
            body.innerHTML = payload.rows.length ? payload.rows.map(row).join('') : '<tr><td colspan="12" class="text-center text-muted py-4">No hay recepciones con estos filtros.</td></tr>';
            renderKpis(payload.kpis);
            document.getElementById('revCount').textContent = `${payload.rows.length} recepción(es)`;
            document.getElementById('revError').textContent = '';
            document.querySelectorAll('.js-export').forEach(link => { link.href = `${baseUrl}/export/${link.dataset.format}?${params(false)}`; });
        } catch (error) {
            document.getElementById('revError').textContent = error.message;
            body.innerHTML = '<tr><td colspan="12" class="text-center text-danger py-4">No fue posible cargar el reporte.</td></tr>';
        } finally {
            root.classList.remove('busy');
        }
    }

    body.addEventListener('click', async event => {
        const toggle = event.target.closest('.js-toggle-detail');
        if (toggle) {
            document.getElementById(toggle.dataset.target)?.classList.toggle('d-none');

            return;
        }

        const verify = event.target.closest('.js-verify-hash');
        if (verify && verify.dataset.attachmentId) {
            const target = document.getElementById(verify.dataset.target);
            target.textContent = 'Verificando…';
            try {
                const response = await fetch(`${verifyBaseUrl}/${verify.dataset.attachmentId}/verificar-hash`, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                });
                const payload = await response.json();
                target.innerHTML = payload.matches
                    ? '<span class="badge bg-success">Coincide</span>'
                    : `<span class="badge bg-danger">${esc(payload.error || 'No coincide')}</span>`;
            } catch (error) {
                target.innerHTML = '<span class="badge bg-danger">Error al verificar</span>';
            }
        }
    });

    form.addEventListener('submit', event => { event.preventDefault(); load(); });
    form.addEventListener('reset', event => {
        event.preventDefault();
        form.querySelectorAll('input').forEach(input => { input.value = ''; });
        if (window.jQuery) {
            jQuery('#revCompanies').val(null).trigger('change');
            jQuery('#revLocations').val(null).trigger('change');
            jQuery('#revType').val(null).trigger('change');
            jQuery('#revReceiver').val(null).trigger('change');
        }
        document.getElementById('revEvidence').value = 'TODAS';
        load();
    });
    load();
})();
</script>
@endpush
