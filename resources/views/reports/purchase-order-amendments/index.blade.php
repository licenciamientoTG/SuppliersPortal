@extends('layouts.zircos')

@section('title', 'Modificaciones, adendas y cancelaciones de OC')

@push('styles')
<style>
    .poa-report { --poa-border:#e2e9f0; }
    .poa-report .report-card { background:#fff; border:1px solid var(--poa-border); border-radius:.8rem; box-shadow:0 4px 16px rgba(24,54,82,.05); }
    .poa-report .report-head { background:#f7fbff; border:1px solid var(--poa-border); border-radius:.8rem; }
    .poa-report .filter-card { padding:1.25rem 1.4rem 1rem; }
    .poa-report .filter-heading h2 { margin:0; color:#26384b; font-size:1rem; font-weight:650; }
    .poa-report .filter-heading p { margin:.2rem 0 1.15rem; color:#8291a3; font-size:.82rem; }
    .poa-report .filter-grid { display:grid; grid-template-columns:repeat(12,minmax(0,1fr)); gap:1rem .9rem; }
    .poa-report .filter-field { grid-column:span 3; min-width:0; }
    .poa-report .filter-field .form-label { margin-bottom:.4rem; color:#536477; font-size:.78rem; font-weight:600; }
    .poa-report .filter-field .form-control, .poa-report .filter-field .form-select { min-height:43px; border-color:#dbe4ed; border-radius:.55rem; font-size:.87rem; }
    .poa-report .filter-field .select2-container { width:100%!important; }
    .poa-report .filter-footer { display:flex; justify-content:flex-end; gap:.55rem; margin-top:1.1rem; padding-top:.9rem; border-top:1px solid #edf1f5; }
    .poa-report .filter-error:empty { display:none; }
    .poa-report .kpi { padding:1rem 1.1rem; height:100%; }
    .poa-report .kpi .label { color:#7a8a9b; font-size:.75rem; text-transform:uppercase; letter-spacing:.04em; }
    .poa-report .kpi .value { font-size:1.35rem; font-weight:650; color:#26384b; font-variant-numeric:tabular-nums; }
    .poa-report .kpi .hint { font-size:.75rem; color:#8291a3; }
    .poa-report .table-wrap { max-height:70vh; overflow:auto; }
    .poa-report table { font-size:.82rem; }
    .poa-report thead th { position:sticky; top:0; z-index:2; background:#f4f7fa; white-space:nowrap; font-size:.72rem; text-transform:uppercase; letter-spacing:.03em; color:#637386; }
    .poa-report .num { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
    .poa-report .busy { opacity:.62; pointer-events:none; }
    @media (max-width: 1199.98px) { .poa-report .filter-field { grid-column:span 4; } }
    @media (max-width: 767.98px) { .poa-report .filter-field { grid-column:span 12; } }
</style>
@endpush

@section('content')
<div class="container-fluid poa-report py-3" id="purchaseOrderAmendmentsReport">
    <div class="report-head p-4 mb-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="text-primary small fw-semibold text-uppercase">Compras · RC-04</div>
            <h1 class="h3 mb-1">Modificaciones, adendas y cancelaciones de OC</h1>
            <p class="text-muted mb-0">Una fila por evento: cancelación, rechazo, devolución a revisión o reemisión. No es un histórico de campos — ver aviso abajo.</p>
        </div>
        @can('reportes.po_amendments.exportar')
            <div class="d-flex gap-2">
                <a class="btn btn-outline-primary js-export" data-format="csv" href="{{ route('purchase-order-amendments-reports.export', ['format' => 'csv']) }}"><i class="ti ti-file-text me-1" aria-hidden="true"></i>CSV</a>
                <a class="btn btn-primary js-export" data-format="xlsx" href="{{ route('purchase-order-amendments-reports.export', ['format' => 'xlsx']) }}"><i class="ti ti-file-spreadsheet me-1" aria-hidden="true"></i>Descargar Excel</a>
            </div>
        @endcan
    </div>

    <section class="report-card filter-card mb-3">
        <div class="filter-heading"><h2>Filtros del reporte</h2><p>Por defecto: todos los eventos registrados, sin acotar fechas.</p></div>
        <form id="poaFilters">
            <div class="filter-grid">
                <div class="filter-field"><label class="form-label" for="poaCompanies">Empresa(s)</label><select class="form-select js-multi" id="poaCompanies" name="company_ids[]" multiple data-placeholder="Todas las empresas">@foreach($companies as $company)<option value="{{ $company->id }}">{{ $company->name }}</option>@endforeach</select></div>
                <div class="filter-field"><label class="form-label" for="poaSupplier">Proveedor</label><select class="form-select js-single" id="poaSupplier" name="supplier_id" data-placeholder="Todos"><option value=""></option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->company_name }}</option>@endforeach</select></div>
                <div class="filter-field"><label class="form-label" for="poaBuyer">Comprador</label><select class="form-select js-single" id="poaBuyer" name="buyer_id" data-placeholder="Todos"><option value=""></option>@foreach($buyers as $buyer)<option value="{{ $buyer->id }}">{{ $buyer->name }}</option>@endforeach</select></div>
                <div class="filter-field"><label class="form-label" for="poaOrderType">Tipo de orden</label><select class="form-select js-multi" id="poaOrderType" name="order_type[]" multiple data-placeholder="Todos"><option value="OC">OC</option><option value="OCD">OCD</option></select></div>
                <div class="filter-field"><label class="form-label" for="poaEventType">Evento</label><select class="form-select js-multi" id="poaEventType" name="event_type[]" multiple data-placeholder="Todos">
                    <option value="CANCELLED">Cancelación</option>
                    <option value="REJECTED">Rechazo</option>
                    <option value="RETURNED_TO_REVISION">Devolución a revisión</option>
                    <option value="REISSUED_AFTER_REVISION">Reemisión tras revisión</option>
                </select></div>
                <div class="filter-field"><label class="form-label" for="poaFrom">Evento del</label><input class="form-control" id="poaFrom" name="date_from" type="date"></div>
                <div class="filter-field"><label class="form-label" for="poaTo">al</label><input class="form-control" id="poaTo" name="date_to" type="date"></div>
            </div>
            <div class="filter-footer"><button class="btn btn-light" type="reset">Limpiar filtros</button><button class="btn btn-primary" type="submit"><i class="ti ti-filter me-1" aria-hidden="true"></i>Aplicar filtros</button></div>
        </form>
        <div id="poaError" class="filter-error text-danger small mt-2" role="alert"></div>
    </section>

    <div class="alert alert-warning mb-3" role="note">
        Este reporte muestra eventos, no un histórico de cambios: no existe hoy un control que capture el precio,
        cantidad o proveedor de una OCD antes de editarla en revisión, así que el monto mostrado siempre es el
        <strong>actual</strong> de la orden, nunca el que tenía al momento del evento. Las OC regulares no tienen
        ruta de edición en el sistema: solo pueden generar eventos de cancelación o rechazo; devolución a revisión
        y reemisión solo aplican a OCD.
    </div>

    <div class="row g-2 mb-3" id="poaKpis" aria-live="polite"></div>

    <section class="report-card">
        <div class="d-flex border-bottom px-3 py-2 align-items-center"><span class="small text-muted" id="poaCount"></span></div>
        <div class="table-wrap">
            <table class="table table-hover mb-0">
                <thead><tr><th>Tipo</th><th>Evento</th><th>Folio</th><th>Proveedor</th><th>Centro de costo</th><th>Comprador</th><th>Fecha del evento</th><th>Responsable</th><th>Motivo</th><th class="num">Monto actual (MXN)</th></tr></thead>
                <tbody id="poaRows"><tr><td colspan="10" class="text-center text-muted py-4">Cargando datos…</td></tr></tbody>
            </table>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const root = document.getElementById('purchaseOrderAmendmentsReport');
    const form = document.getElementById('poaFilters');
    const body = document.getElementById('poaRows');
    const baseUrl = @json(url('reportes/compras/modificaciones-oc'));

    if (window.jQuery && jQuery.fn.select2) {
        jQuery('.js-multi, .js-single').each(function () {
            jQuery(this).select2({ theme: 'bootstrap-5', width: '100%', placeholder: this.dataset.placeholder, closeOnSelect: this.classList.contains('js-single'), dropdownParent: jQuery(document.body), allowClear: this.classList.contains('js-single') });
        });
    }

    const fmt = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });
    const money = v => v === null || v === undefined ? '<span class="text-muted">—</span>' : fmt.format(v);
    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const date = v => v ? new Date(v).toLocaleString('es-MX') : '<span class="text-muted">—</span>';
    const eventLabels = { CANCELLED: 'Cancelación', REJECTED: 'Rechazo', RETURNED_TO_REVISION: 'Devolución a revisión', REISSUED_AFTER_REVISION: 'Reemisión tras revisión' };

    function params(withSuffix = true) {
        const query = new URLSearchParams();
        const data = new FormData(form);
        for (const [key, value] of data.entries()) if (value !== '') query.append(key, value);
        return withSuffix ? query : query;
    }

    function row(r) {
        return `<tr><td><span class="badge bg-secondary">${esc(r.order_type)}</span></td><td>${esc(eventLabels[r.event_type] || r.event_type)}</td>`
            + `<td><a href="${esc(r.url)}" target="_blank" rel="noopener">${esc(r.folio)}</a></td>`
            + `<td>${esc(r.supplier_name)}</td><td>${esc(r.cost_center_label || '—')}</td><td>${esc(r.buyer_name)}</td>`
            + `<td>${date(r.occurred_at)}</td><td>${esc(r.actor_name)}</td><td>${esc(r.reason || '—')}</td>`
            + `<td class="num">${money(r.total_mxn_current)}</td></tr>`;
    }

    function renderKpis(k) {
        const card = (label, value, hint) => `<div class="col-6 col-md-3 col-xl"><div class="report-card kpi"><div class="label">${label}</div><div class="value">${value}</div><div class="hint">${hint ?? ''}</div></div></div>`;
        document.getElementById('poaKpis').innerHTML =
            card('Eventos totales', k.total_events)
            + card('Cancelaciones', k.by_event_type.CANCELLED ?? 0)
            + card('Rechazos', k.by_event_type.REJECTED ?? 0)
            + card('Devoluciones a revisión', k.by_event_type.RETURNED_TO_REVISION ?? 0)
            + card('Reemisiones tras revisión', k.by_event_type.REISSUED_AFTER_REVISION ?? 0);
    }

    async function load() {
        root.classList.add('busy');
        try {
            const response = await fetch(`${baseUrl}/data?${params()}`, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error(response.status === 403 ? 'No tienes permiso para consultar este reporte.' : response.status === 422 ? 'Revisa los filtros: algún valor no es válido.' : 'No se pudo cargar el reporte. Inténtalo de nuevo.');
            const payload = await response.json();
            body.innerHTML = payload.rows.length ? payload.rows.map(row).join('') : '<tr><td colspan="10" class="text-center text-muted py-4">No hay eventos con estos filtros.</td></tr>';
            renderKpis(payload.kpis);
            document.getElementById('poaCount').textContent = `${payload.rows.length} evento(s)`;
            document.getElementById('poaError').textContent = '';
            document.querySelectorAll('.js-export').forEach(link => { link.href = `${baseUrl}/export/${link.dataset.format}?${params(false)}`; });
        } catch (error) {
            document.getElementById('poaError').textContent = error.message;
            body.innerHTML = '<tr><td colspan="10" class="text-center text-danger py-4">No fue posible cargar el reporte.</td></tr>';
        } finally {
            root.classList.remove('busy');
        }
    }

    form.addEventListener('submit', event => { event.preventDefault(); load(); });
    form.addEventListener('reset', event => {
        event.preventDefault();
        form.querySelectorAll('input').forEach(input => { input.value = ''; });
        if (window.jQuery) {
            jQuery('#poaCompanies').val(null).trigger('change');
            jQuery('#poaSupplier').val(null).trigger('change');
            jQuery('#poaBuyer').val(null).trigger('change');
            jQuery('#poaOrderType').val(null).trigger('change');
            jQuery('#poaEventType').val(null).trigger('change');
        }
        load();
    });
    load();
})();
</script>
@endpush
