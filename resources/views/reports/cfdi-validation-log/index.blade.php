@extends('layouts.zircos')

@section('title', 'Bitácora de validación de CFDI')

@push('styles')
<style>
    .cvl-report { --cvl-border:#e2e9f0; }
    .cvl-report .report-card { background:#fff; border:1px solid var(--cvl-border); border-radius:.8rem; box-shadow:0 4px 16px rgba(24,54,82,.05); }
    .cvl-report .report-head { background:#f7fbff; border:1px solid var(--cvl-border); border-radius:.8rem; }
    .cvl-report .filter-card { padding:1.25rem 1.4rem 1rem; }
    .cvl-report .filter-heading h2 { margin:0; color:#26384b; font-size:1rem; font-weight:650; }
    .cvl-report .filter-heading p { margin:.2rem 0 1.15rem; color:#8291a3; font-size:.82rem; }
    .cvl-report .filter-grid { display:grid; grid-template-columns:repeat(12,minmax(0,1fr)); gap:1rem .9rem; }
    .cvl-report .filter-field { grid-column:span 3; min-width:0; }
    .cvl-report .filter-field .form-label { margin-bottom:.4rem; color:#536477; font-size:.78rem; font-weight:600; }
    .cvl-report .filter-field .form-control, .cvl-report .filter-field .form-select { min-height:43px; border-color:#dbe4ed; border-radius:.55rem; font-size:.87rem; }
    .cvl-report .filter-field .select2-container { width:100%!important; }
    .cvl-report .filter-footer { display:flex; justify-content:flex-end; gap:.55rem; margin-top:1.1rem; padding-top:.9rem; border-top:1px solid #edf1f5; }
    .cvl-report .filter-error:empty { display:none; }
    .cvl-report .kpi { padding:1rem 1.1rem; height:100%; }
    .cvl-report .kpi .label { color:#7a8a9b; font-size:.75rem; text-transform:uppercase; letter-spacing:.04em; }
    .cvl-report .kpi .value { font-size:1.35rem; font-weight:650; color:#26384b; font-variant-numeric:tabular-nums; }
    .cvl-report .kpi .hint { font-size:.75rem; color:#8291a3; }
    .cvl-report .table-wrap { max-height:70vh; overflow:auto; }
    .cvl-report table { font-size:.82rem; }
    .cvl-report thead th { position:sticky; top:0; z-index:2; background:#f4f7fa; white-space:nowrap; font-size:.72rem; text-transform:uppercase; letter-spacing:.03em; color:#637386; }
    .cvl-report .num { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
    .cvl-report .busy { opacity:.62; pointer-events:none; }
    @media (max-width: 1199.98px) { .cvl-report .filter-field { grid-column:span 4; } }
    @media (max-width: 767.98px) { .cvl-report .filter-field { grid-column:span 12; } }
</style>
@endpush

@section('content')
<div class="container-fluid cvl-report py-3" id="cfdiValidationLogReport">
    <div class="report-head p-4 mb-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="text-primary small fw-semibold text-uppercase">Fiscal · RF-01</div>
            <h1 class="h3 mb-1">Bitácora de validación de CFDI</h1>
            <p class="text-muted mb-0">Cada intento de carga de CFDI (aceptado o rechazado), con su código de rechazo, regla incumplida y mensaje al proveedor.</p>
        </div>
        @can('reportes.cfdi_validation_log.exportar')
            <div class="d-flex gap-2">
                <a class="btn btn-outline-primary js-export" data-format="csv" href="{{ route('cfdi-validation-log-reports.export', ['format' => 'csv']) }}"><i class="ti ti-file-text me-1" aria-hidden="true"></i>CSV</a>
                <a class="btn btn-primary js-export" data-format="xlsx" href="{{ route('cfdi-validation-log-reports.export', ['format' => 'xlsx']) }}"><i class="ti ti-file-spreadsheet me-1" aria-hidden="true"></i>Descargar Excel</a>
            </div>
        @endcan
    </div>

    <section class="report-card filter-card mb-3">
        <div class="filter-heading"><h2>Filtros del reporte</h2><p>Por defecto: todos los intentos, sin acotar fechas.</p></div>
        <form id="cvlFilters">
            <div class="filter-grid">
                <div class="filter-field"><label class="form-label" for="cvlResult">Resultado</label><select class="form-select js-single" id="cvlResult" name="result" data-placeholder="Todos"><option value=""></option><option value="ACEPTADO">Aceptado</option><option value="RECHAZADO">Rechazado</option></select></div>
                <div class="filter-field"><label class="form-label" for="cvlRejectionCode">Código de rechazo</label><select class="form-select js-multi" id="cvlRejectionCode" name="rejection_code[]" multiple data-placeholder="Todos">@foreach($rejectionCodes as $code)<option value="{{ $code->code }}">{{ $code->code }}</option>@endforeach</select></div>
                <div class="filter-field"><label class="form-label" for="cvlSupplier">Proveedor</label><select class="form-select js-single" id="cvlSupplier" name="supplier_id" data-placeholder="Todos"><option value=""></option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->company_name }}</option>@endforeach</select></div>
                <div class="filter-field"><label class="form-label" for="cvlFrom">Del</label><input class="form-control" id="cvlFrom" name="date_from" type="date"></div>
                <div class="filter-field"><label class="form-label" for="cvlTo">al</label><input class="form-control" id="cvlTo" name="date_to" type="date"></div>
            </div>
            <div class="filter-footer"><button class="btn btn-light" type="reset">Limpiar filtros</button><button class="btn btn-primary" type="submit"><i class="ti ti-filter me-1" aria-hidden="true"></i>Aplicar filtros</button></div>
        </form>
        <div id="cvlError" class="filter-error text-danger small mt-2" role="alert"></div>
    </section>

    <div class="alert alert-warning mb-3" role="note">
        El estatus en el SAT (vigente/cancelado) no está disponible: el portal no consulta hoy el webservice de verificación de CFDI.
    </div>

    <div class="row g-2 mb-3" id="cvlKpis" aria-live="polite"></div>

    <section class="report-card">
        <div class="d-flex border-bottom px-3 py-2 align-items-center"><span class="small text-muted" id="cvlCount"></span></div>
        <div class="table-wrap">
            <table class="table table-hover mb-0">
                <thead><tr><th>Fecha/hora</th><th>UUID</th><th>RFC emisor</th><th>Empresa</th><th class="num">Total</th><th>Resultado</th><th>Código</th><th>Mensaje al proveedor</th><th class="num">Intento</th><th>Canal</th><th>Proveedor</th><th>Usuario</th></tr></thead>
                <tbody id="cvlRows"><tr><td colspan="12" class="text-center text-muted py-4">Cargando datos…</td></tr></tbody>
            </table>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const root = document.getElementById('cfdiValidationLogReport');
    const form = document.getElementById('cvlFilters');
    const body = document.getElementById('cvlRows');
    const baseUrl = @json(url('reportes/fiscal/bitacora-cfdi'));

    if (window.jQuery && jQuery.fn.select2) {
        jQuery('.js-multi, .js-single').each(function () {
            jQuery(this).select2({ theme: 'bootstrap-5', width: '100%', placeholder: this.dataset.placeholder, closeOnSelect: this.classList.contains('js-single'), dropdownParent: jQuery(document.body), allowClear: this.classList.contains('js-single') });
        });
    }

    const fmt = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });
    const money = v => v === null || v === undefined ? '<span class="text-muted">—</span>' : fmt.format(v);
    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const date = v => v ? new Date(v).toLocaleString('es-MX') : '<span class="text-muted">—</span>';

    function params(withSuffix = true) {
        const query = new URLSearchParams();
        const data = new FormData(form);
        for (const [key, value] of data.entries()) if (value !== '') query.append(key, value);
        return withSuffix ? query : query;
    }

    function row(r) {
        const badge = r.result === 'ACEPTADO' ? 'bg-success' : 'bg-danger';
        return `<tr><td>${date(r.attempted_at)}</td><td>${esc(r.uuid)}</td><td>${esc(r.issuer_rfc)}</td><td>${esc(r.company_name)}</td>`
            + `<td class="num">${money(r.total)} ${esc(r.currency)}</td><td><span class="badge ${badge}">${esc(r.result)}</span></td>`
            + `<td>${esc(r.rejection_code)}</td><td>${esc(r.supplier_message)}</td><td class="num">${r.attempt_no}</td>`
            + `<td>${esc(r.channel)}</td><td>${esc(r.supplier_name)}</td><td>${esc(r.user_name)}</td></tr>`;
    }

    function renderKpis(k) {
        const card = (label, value, hint) => `<div class="col-6 col-md-3 col-xl"><div class="report-card kpi"><div class="label">${label}</div><div class="value">${value}</div><div class="hint">${hint ?? ''}</div></div></div>`;
        document.getElementById('cvlKpis').innerHTML =
            card('Intentos', k.total_attempts)
            + card('% rechazo en 1er intento', k.first_attempt_rejection_rate.toFixed(1) + '%')
            + card('Código top', k.top_rejection_code ?? 'N/D')
            + card('Reintentos promedio', k.average_retries.toFixed(2));
    }

    async function load() {
        root.classList.add('busy');
        try {
            const response = await fetch(`${baseUrl}/data?${params()}`, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error(response.status === 403 ? 'No tienes permiso para consultar este reporte.' : response.status === 422 ? 'Revisa los filtros: algún valor no es válido.' : 'No se pudo cargar el reporte. Inténtalo de nuevo.');
            const payload = await response.json();
            body.innerHTML = payload.rows.length ? payload.rows.map(row).join('') : '<tr><td colspan="12" class="text-center text-muted py-4">No hay intentos con estos filtros.</td></tr>';
            renderKpis(payload.kpis);
            document.getElementById('cvlCount').textContent = `${payload.rows.length} intento(s)`;
            document.getElementById('cvlError').textContent = '';
            document.querySelectorAll('.js-export').forEach(link => { link.href = `${baseUrl}/export/${link.dataset.format}?${params(false)}`; });
        } catch (error) {
            document.getElementById('cvlError').textContent = error.message;
            body.innerHTML = '<tr><td colspan="12" class="text-center text-danger py-4">No fue posible cargar el reporte.</td></tr>';
        } finally {
            root.classList.remove('busy');
        }
    }

    form.addEventListener('submit', event => { event.preventDefault(); load(); });
    form.addEventListener('reset', event => {
        event.preventDefault();
        form.querySelectorAll('input').forEach(input => { input.value = ''; });
        if (window.jQuery) {
            jQuery('#cvlResult').val(null).trigger('change');
            jQuery('#cvlRejectionCode').val(null).trigger('change');
            jQuery('#cvlSupplier').val(null).trigger('change');
        }
        load();
    });
    load();
})();
</script>
@endpush
