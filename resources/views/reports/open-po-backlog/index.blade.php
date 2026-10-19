@extends('layouts.zircos')

@section('title', 'Backlog de OC abiertas')

@push('styles')
<style>
    .popb-report { --popb-border:#e2e9f0; }
    .popb-report .report-card { background:#fff; border:1px solid var(--popb-border); border-radius:.8rem; box-shadow:0 4px 16px rgba(24,54,82,.05); }
    .popb-report .report-head { background:#f7fbff; border:1px solid var(--popb-border); border-radius:.8rem; }
    .popb-report .filter-card { padding:1.25rem 1.4rem 1rem; }
    .popb-report .filter-heading h2 { margin:0; color:#26384b; font-size:1rem; font-weight:650; }
    .popb-report .filter-heading p { margin:.2rem 0 1.15rem; color:#8291a3; font-size:.82rem; }
    .popb-report .filter-grid { display:grid; grid-template-columns:repeat(12,minmax(0,1fr)); gap:1rem .9rem; }
    .popb-report .filter-field { grid-column:span 3; min-width:0; }
    .popb-report .filter-field .form-label { margin-bottom:.4rem; color:#536477; font-size:.78rem; font-weight:600; }
    .popb-report .filter-field .form-control, .popb-report .filter-field .form-select { min-height:43px; border-color:#dbe4ed; border-radius:.55rem; font-size:.87rem; }
    .popb-report .filter-field .select2-container { width:100%!important; }
    .popb-report .filter-field.check { display:flex; align-items:center; padding-top:1.6rem; gap:.5rem; }
    .popb-report .filter-footer { display:flex; justify-content:flex-end; gap:.55rem; margin-top:1.1rem; padding-top:.9rem; border-top:1px solid #edf1f5; }
    .popb-report .filter-error:empty { display:none; }
    .popb-report .kpi { padding:1rem 1.1rem; height:100%; }
    .popb-report .kpi .label { color:#7a8a9b; font-size:.75rem; text-transform:uppercase; letter-spacing:.04em; }
    .popb-report .kpi .value { font-size:1.35rem; font-weight:650; color:#26384b; font-variant-numeric:tabular-nums; }
    .popb-report .kpi .hint { font-size:.75rem; color:#8291a3; }
    .popb-report .table-wrap { max-height:70vh; overflow:auto; }
    .popb-report table { font-size:.82rem; }
    .popb-report thead th { position:sticky; top:0; z-index:2; background:#f4f7fa; white-space:nowrap; font-size:.72rem; text-transform:uppercase; letter-spacing:.03em; color:#637386; }
    .popb-report .num { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
    .popb-report .busy { opacity:.62; pointer-events:none; }
    @media (max-width: 1199.98px) { .popb-report .filter-field { grid-column:span 4; } }
    @media (max-width: 767.98px) { .popb-report .filter-field { grid-column:span 12; } }
</style>
@endpush

@section('content')
<div class="container-fluid popb-report py-3" id="openPoBacklogReport">
    <div class="report-head p-4 mb-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="text-primary small fw-semibold text-uppercase">Compras · RC-03</div>
            <h1 class="h3 mb-1">Backlog de órdenes de compra abiertas</h1>
            <p class="text-muted mb-0">Renglones de OC y OCD con saldo pendiente de recibir: atraso contra la fecha prometida y OC "zombi" (sin actividad de recepción reciente).</p>
        </div>
        @can('reportes.open_po_backlog.exportar')
            <div class="d-flex gap-2">
                <a class="btn btn-outline-primary js-export" data-format="csv" href="{{ route('open-po-backlog-reports.export', ['format' => 'csv']) }}"><i class="ti ti-file-text me-1" aria-hidden="true"></i>CSV</a>
                <a class="btn btn-primary js-export" data-format="xlsx" href="{{ route('open-po-backlog-reports.export', ['format' => 'xlsx']) }}"><i class="ti ti-file-spreadsheet me-1" aria-hidden="true"></i>Descargar Excel</a>
            </div>
        @endcan
    </div>

    <section class="report-card filter-card mb-3">
        <div class="filter-heading"><h2>Filtros del reporte</h2><p>Por defecto: fecha de corte hoy, umbral de zombi 90 días sin recepción.</p></div>
        <form id="popbFilters">
            <div class="filter-grid">
                <div class="filter-field"><label class="form-label" for="popbCompanies">Empresa(s)</label><select class="form-select js-multi" id="popbCompanies" name="company_ids[]" multiple data-placeholder="Todas las empresas">@foreach($companies as $company)<option value="{{ $company->id }}">{{ $company->name }}</option>@endforeach</select></div>
                <div class="filter-field"><label class="form-label" for="popbSupplier">Proveedor</label><select class="form-select js-single" id="popbSupplier" name="supplier_id" data-placeholder="Todos"><option value=""></option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->company_name }}</option>@endforeach</select></div>
                <div class="filter-field"><label class="form-label" for="popbAsOf">Fecha de corte</label><input class="form-control" id="popbAsOf" name="as_of_date" type="date" value="{{ now()->toDateString() }}" data-default="{{ now()->toDateString() }}"></div>
                <div class="filter-field"><label class="form-label" for="popbZombieDays">Días para marcar zombi</label><input class="form-control" id="popbZombieDays" name="zombie_days" type="number" min="1" max="3650" value="90" data-default="90"></div>
                <div class="filter-field check"><input class="form-check-input" id="popbOnlyLate" name="only_late" type="checkbox" value="1"><label class="form-check-label" for="popbOnlyLate">Solo atrasadas</label></div>
                <div class="filter-field check"><input class="form-check-input" id="popbOnlyZombie" name="only_zombie" type="checkbox" value="1"><label class="form-check-label" for="popbOnlyZombie">Solo zombi</label></div>
            </div>
            <div class="filter-footer"><button class="btn btn-light" type="reset">Limpiar filtros</button><button class="btn btn-primary" type="submit"><i class="ti ti-filter me-1" aria-hidden="true"></i>Aplicar filtros</button></div>
        </form>
        <div id="popbError" class="filter-error text-danger small mt-2" role="alert"></div>
    </section>

    <div class="row g-2 mb-3" id="popbKpis" aria-live="polite"></div>

    <section class="report-card">
        <div class="d-flex border-bottom px-3 py-2 align-items-center"><span class="small text-muted" id="popbCount"></span></div>
        <div class="table-wrap">
            <table class="table table-hover mb-0">
                <thead><tr><th>Tipo</th><th>Folio</th><th>Proveedor</th><th>Descripción</th><th>Centro de costo</th><th>Cuenta / Subcuenta</th><th class="num">Pendiente</th><th class="num">Importe pendiente</th><th>Fecha prometida</th><th class="num">Días de atraso</th><th>Vigencia</th><th>Última recepción</th><th>Zombi</th></tr></thead>
                <tbody id="popbRows"><tr><td colspan="13" class="text-center text-muted py-4">Cargando datos…</td></tr></tbody>
            </table>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const root = document.getElementById('openPoBacklogReport');
    const form = document.getElementById('popbFilters');
    const body = document.getElementById('popbRows');
    const baseUrl = @json(url('reportes/compras/oc-abiertas'));

    if (window.jQuery && jQuery.fn.select2) {
        jQuery('.js-multi, .js-single').each(function () {
            jQuery(this).select2({ theme: 'bootstrap-5', width: '100%', placeholder: this.dataset.placeholder, closeOnSelect: this.classList.contains('js-single'), dropdownParent: jQuery(document.body), allowClear: this.classList.contains('js-single') });
        });
    }

    const fmt = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });
    const money = v => v === null || v === undefined ? '<span class="text-muted">—</span>' : fmt.format(v);
    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const date = v => v ? new Date(v).toLocaleDateString('es-MX') : '<span class="text-muted">Sin fecha prometida</span>';

    function params(withSuffix = true) {
        const query = new URLSearchParams();
        const data = new FormData(form);
        if (!form.querySelector('#popbOnlyLate').checked) data.delete('only_late');
        if (!form.querySelector('#popbOnlyZombie').checked) data.delete('only_zombie');
        for (const [key, value] of data.entries()) if (value !== '') query.append(key, value);
        return withSuffix ? query : query;
    }

    function row(r) {
        const zombieBadge = r.is_zombie ? '<span class="badge bg-danger">Zombi</span>' : '<span class="text-muted">No</span>';
        const vigencia = r.is_expired ? '<span class="badge bg-warning text-dark">Vencida</span>' : '<span class="text-muted">Vigente</span>';
        const daysLate = r.days_late === null ? '<span class="text-muted">—</span>' : (r.days_late > 0 ? `<span class="text-danger fw-semibold">${r.days_late}</span>` : '0');
        return `<tr><td><span class="badge bg-secondary">${esc(r.type)}</span></td>`
            + `<td><a href="${esc(r.url)}" target="_blank" rel="noopener">${esc(r.folio)}</a></td>`
            + `<td>${esc(r.supplier_name)}</td><td style="min-width:220px">${esc(r.description)}</td>`
            + `<td>${esc(r.cost_center_label || '—')}</td><td>${esc(r.expense_category_label || '—')}${r.budget_cedula_label ? ' · ' + esc(r.budget_cedula_label) : ''}</td>`
            + `<td class="num">${Number(r.pending_qty).toLocaleString('es-MX', { maximumFractionDigits: 3 })}</td><td class="num">${money(r.pending_amount)}</td>`
            + `<td>${date(r.promised_date)}</td><td class="num">${daysLate}</td><td>${vigencia}</td>`
            + `<td>${date(r.last_received_at)}</td><td>${zombieBadge}</td></tr>`;
    }

    function renderKpis(k, reconciliation) {
        const card = (label, value, hint) => `<div class="col-6 col-md-3 col-xl"><div class="report-card kpi"><div class="label">${label}</div><div class="value">${value}</div><div class="hint">${hint ?? ''}</div></div></div>`;
        const diff = k.pending_amount_total - reconciliation.budget_commitments_pending;
        document.getElementById('popbKpis').innerHTML =
            card('Renglones pendientes', k.lines, `${k.suppliers} proveedor(es)`)
            + card('Importe pendiente', fmt.format(k.pending_amount_total), 'Sin IVA')
            + card('Atrasados', k.late_lines)
            + card('OC zombi', k.zombie_lines, fmt.format(k.zombie_amount) + ' atrapado')
            + card('Diferencia vs. comprometido RP-01', fmt.format(diff), 'Cuadre global, sin filtros de pantalla');
    }

    async function load() {
        root.classList.add('busy');
        try {
            const response = await fetch(`${baseUrl}/data?${params()}`, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error(response.status === 403 ? 'No tienes permiso para consultar este reporte.' : response.status === 422 ? 'Revisa los filtros: algún valor no es válido.' : 'No se pudo cargar el reporte. Inténtalo de nuevo.');
            const payload = await response.json();
            body.innerHTML = payload.rows.length ? payload.rows.map(row).join('') : '<tr><td colspan="13" class="text-center text-muted py-4">No hay renglones pendientes de recibir con estos filtros.</td></tr>';
            renderKpis(payload.kpis, payload.reconciliation);
            document.getElementById('popbCount').textContent = `${payload.rows.length} renglón(es)`;
            document.getElementById('popbError').textContent = '';
            document.querySelectorAll('.js-export').forEach(link => { link.href = `${baseUrl}/export/${link.dataset.format}?${params(false)}`; });
        } catch (error) {
            document.getElementById('popbError').textContent = error.message;
            body.innerHTML = '<tr><td colspan="13" class="text-center text-danger py-4">No fue posible cargar el reporte.</td></tr>';
        } finally {
            root.classList.remove('busy');
        }
    }

    form.addEventListener('submit', event => { event.preventDefault(); load(); });
    form.addEventListener('reset', event => {
        event.preventDefault();
        form.querySelectorAll('input').forEach(input => { input.type === 'checkbox' ? input.checked = false : input.value = input.dataset.default ?? ''; });
        if (window.jQuery) {
            jQuery('#popbCompanies').val(null).trigger('change');
            jQuery('#popbSupplier').val(null).trigger('change');
        }
        load();
    });
    load();
})();
</script>
@endpush
