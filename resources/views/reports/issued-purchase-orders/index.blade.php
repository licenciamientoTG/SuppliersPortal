@extends('layouts.zircos')

@section('title', 'Órdenes de compra emitidas')

@push('styles')
<style>
    .ipo-report { --ipo-border:#e2e9f0; }
    .ipo-report .report-card { background:#fff; border:1px solid var(--ipo-border); border-radius:.8rem; box-shadow:0 4px 16px rgba(24,54,82,.05); }
    .ipo-report .report-head { background:#f7fbff; border:1px solid var(--ipo-border); border-radius:.8rem; }
    .ipo-report .filter-card { padding:1.25rem 1.4rem 1rem; }
    .ipo-report .filter-heading h2 { margin:0; color:#26384b; font-size:1rem; font-weight:650; }
    .ipo-report .filter-heading p { margin:.2rem 0 1.15rem; color:#8291a3; font-size:.82rem; }
    .ipo-report .filter-grid { display:grid; grid-template-columns:repeat(12,minmax(0,1fr)); gap:1rem .9rem; }
    .ipo-report .filter-field { grid-column:span 3; min-width:0; }
    .ipo-report .filter-field .form-label { margin-bottom:.4rem; color:#536477; font-size:.78rem; font-weight:600; }
    .ipo-report .filter-field .form-control, .ipo-report .filter-field .form-select { min-height:43px; border-color:#dbe4ed; border-radius:.55rem; font-size:.87rem; }
    .ipo-report .filter-field .select2-container { width:100%!important; }
    .ipo-report .filter-footer { display:flex; justify-content:flex-end; gap:.55rem; margin-top:1.1rem; padding-top:.9rem; border-top:1px solid #edf1f5; }
    .ipo-report .filter-error:empty { display:none; }
    .ipo-report .kpi { padding:1rem 1.1rem; height:100%; }
    .ipo-report .kpi .label { color:#7a8a9b; font-size:.75rem; text-transform:uppercase; letter-spacing:.04em; }
    .ipo-report .kpi .value { font-size:1.35rem; font-weight:650; color:#26384b; font-variant-numeric:tabular-nums; }
    .ipo-report .kpi .hint { font-size:.75rem; color:#8291a3; }
    .ipo-report .table-wrap { max-height:70vh; overflow:auto; }
    .ipo-report table { font-size:.82rem; }
    .ipo-report thead th { position:sticky; top:0; z-index:2; background:#f4f7fa; white-space:nowrap; font-size:.72rem; text-transform:uppercase; letter-spacing:.03em; color:#637386; }
    .ipo-report .num { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
    .ipo-report .busy { opacity:.62; pointer-events:none; }
    @media (max-width: 1199.98px) { .ipo-report .filter-field { grid-column:span 4; } }
    @media (max-width: 767.98px) { .ipo-report .filter-field { grid-column:span 12; } }
</style>
@endpush

@section('content')
<div class="container-fluid ipo-report py-3" id="issuedPurchaseOrdersReport">
    <div class="report-head p-4 mb-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="text-primary small fw-semibold text-uppercase">Compras · RC-02</div>
            <h1 class="h3 mb-1">Órdenes de compra emitidas</h1>
            <p class="text-muted mb-0">Universo de OC y OCD ya emitidas: origen, moneda, total en MXN, acuse del proveedor y % de compra directa.</p>
        </div>
        @can('reportes.purchase_orders_issued.exportar')
            <div class="d-flex gap-2">
                <a class="btn btn-outline-primary js-export" data-format="csv" href="{{ route('issued-purchase-orders-reports.export', ['format' => 'csv']) }}"><i class="ti ti-file-text me-1" aria-hidden="true"></i>CSV</a>
                <a class="btn btn-primary js-export" data-format="xlsx" href="{{ route('issued-purchase-orders-reports.export', ['format' => 'xlsx']) }}"><i class="ti ti-file-spreadsheet me-1" aria-hidden="true"></i>Descargar Excel</a>
            </div>
        @endcan
    </div>

    <section class="report-card filter-card mb-3">
        <div class="filter-heading"><h2>Filtros del reporte</h2><p>Por defecto: todas las órdenes emitidas, sin acotar fechas.</p></div>
        <form id="ipoFilters">
            <div class="filter-grid">
                <div class="filter-field"><label class="form-label" for="ipoCompanies">Empresa(s)</label><select class="form-select js-multi" id="ipoCompanies" name="company_ids[]" multiple data-placeholder="Todas las empresas">@foreach($companies as $company)<option value="{{ $company->id }}">{{ $company->name }}</option>@endforeach</select></div>
                <div class="filter-field"><label class="form-label" for="ipoSupplier">Proveedor</label><select class="form-select js-single" id="ipoSupplier" name="supplier_id" data-placeholder="Todos"><option value=""></option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->company_name }}</option>@endforeach</select></div>
                <div class="filter-field"><label class="form-label" for="ipoBuyer">Comprador</label><select class="form-select js-single" id="ipoBuyer" name="buyer_id" data-placeholder="Todos"><option value=""></option>@foreach($buyers as $buyer)<option value="{{ $buyer->id }}">{{ $buyer->name }}</option>@endforeach</select></div>
                <div class="filter-field"><label class="form-label" for="ipoOrigin">Origen</label><select class="form-select js-multi" id="ipoOrigin" name="origin[]" multiple data-placeholder="Todos"><option value="REQUISICION">Requisición</option><option value="CONTRATO">Contrato</option><option value="DIRECTA">Directa</option></select></div>
                <div class="filter-field"><label class="form-label" for="ipoFrom">Emitidas del</label><input class="form-control" id="ipoFrom" name="date_from" type="date"></div>
                <div class="filter-field"><label class="form-label" for="ipoTo">al</label><input class="form-control" id="ipoTo" name="date_to" type="date"></div>
            </div>
            <div class="filter-footer"><button class="btn btn-light" type="reset">Limpiar filtros</button><button class="btn btn-primary" type="submit"><i class="ti ti-filter me-1" aria-hidden="true"></i>Aplicar filtros</button></div>
        </form>
        <div id="ipoError" class="filter-error text-danger small mt-2" role="alert"></div>
    </section>

    <div class="row g-2 mb-3" id="ipoKpis" aria-live="polite"></div>

    <section class="report-card">
        <div class="d-flex border-bottom px-3 py-2 align-items-center"><span class="small text-muted" id="ipoCount"></span></div>
        <div class="table-wrap">
            <table class="table table-hover mb-0">
                <thead><tr><th>Tipo</th><th>Origen</th><th>Folio</th><th>Proveedor</th><th>Centro de costo</th><th>Comprador</th><th>Estatus</th><th class="num">Total</th><th class="num">Total MXN</th><th>Emisión</th><th>Acuse</th><th class="num">Hrs. a acuse</th><th>Excepción</th></tr></thead>
                <tbody id="ipoRows"><tr><td colspan="13" class="text-center text-muted py-4">Cargando datos…</td></tr></tbody>
            </table>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const root = document.getElementById('issuedPurchaseOrdersReport');
    const form = document.getElementById('ipoFilters');
    const body = document.getElementById('ipoRows');
    const baseUrl = @json(url('reportes/compras/oc-emitidas'));

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
        const exception = r.is_exception ? '<span class="badge bg-warning text-dark">Sí</span>' : '<span class="text-muted">No</span>';
        return `<tr><td><span class="badge bg-secondary">${esc(r.type)}</span></td><td>${esc(r.origin)}</td>`
            + `<td><a href="${esc(r.url)}" target="_blank" rel="noopener">${esc(r.folio)}</a></td>`
            + `<td>${esc(r.supplier_name)}</td><td>${esc(r.cost_center_label || '—')}</td><td>${esc(r.buyer_name)}</td><td>${esc(r.status_label)}</td>`
            + `<td class="num">${money(r.total)} ${esc(r.currency)}</td><td class="num">${money(r.total_mxn)}</td>`
            + `<td>${date(r.sent_at)}</td><td>${date(r.supplier_ack_at)}</td><td class="num">${r.hours_to_ack ?? '<span class="text-muted">—</span>'}</td><td>${exception}</td></tr>`;
    }

    function renderKpis(k) {
        const card = (label, value, hint) => `<div class="col-6 col-md-3 col-xl"><div class="report-card kpi"><div class="label">${label}</div><div class="value">${value}</div><div class="hint">${hint ?? ''}</div></div></div>`;
        document.getElementById('ipoKpis').innerHTML =
            card('Órdenes emitidas', k.total_orders, fmt.format(k.total_amount_mxn))
            + card('% directas (conteo)', k.direct_pct_count.toFixed(1) + '%', `${k.direct_count} OCD`)
            + card('% directas (importe)', k.direct_pct_amount.toFixed(1) + '%', fmt.format(k.direct_amount_mxn))
            + card('% sin acuse > 48h', k.no_ack_over_48h_pct === null ? 'N/D' : k.no_ack_over_48h_pct.toFixed(1) + '%', `${k.no_ack_over_48h_count} de ${k.no_ack_over_48h_eligible} elegibles`);
    }

    async function load() {
        root.classList.add('busy');
        try {
            const response = await fetch(`${baseUrl}/data?${params()}`, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error(response.status === 403 ? 'No tienes permiso para consultar este reporte.' : response.status === 422 ? 'Revisa los filtros: algún valor no es válido.' : 'No se pudo cargar el reporte. Inténtalo de nuevo.');
            const payload = await response.json();
            body.innerHTML = payload.rows.length ? payload.rows.map(row).join('') : '<tr><td colspan="13" class="text-center text-muted py-4">No hay órdenes emitidas con estos filtros.</td></tr>';
            renderKpis(payload.kpis);
            document.getElementById('ipoCount').textContent = `${payload.rows.length} orden(es)`;
            document.getElementById('ipoError').textContent = '';
            document.querySelectorAll('.js-export').forEach(link => { link.href = `${baseUrl}/export/${link.dataset.format}?${params(false)}`; });
        } catch (error) {
            document.getElementById('ipoError').textContent = error.message;
            body.innerHTML = '<tr><td colspan="13" class="text-center text-danger py-4">No fue posible cargar el reporte.</td></tr>';
        } finally {
            root.classList.remove('busy');
        }
    }

    form.addEventListener('submit', event => { event.preventDefault(); load(); });
    form.addEventListener('reset', event => {
        event.preventDefault();
        form.querySelectorAll('input').forEach(input => { input.value = ''; });
        if (window.jQuery) {
            jQuery('#ipoCompanies').val(null).trigger('change');
            jQuery('#ipoSupplier').val(null).trigger('change');
            jQuery('#ipoBuyer').val(null).trigger('change');
            jQuery('#ipoOrigin').val(null).trigger('change');
        }
        load();
    });
    load();
})();
</script>
@endpush
