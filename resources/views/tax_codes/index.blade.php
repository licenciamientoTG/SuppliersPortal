@extends('layouts.zircos')

@section('title', 'Catálogo de impuestos')
@section('page.title', 'Catálogo de impuestos')
@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
    <li class="breadcrumb-item">Administración</li>
    <li class="breadcrumb-item active">Catálogo de impuestos</li>
@endsection

@push('styles')
<style>
    .tax-catalog-summary { border: 1px solid #e2e9f0; border-radius: .75rem; background: #fff; box-shadow: 0 3px 12px rgba(36, 59, 83, .04); }
    .tax-catalog-summary__label { color: #6c7a89; font-size: .75rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; }
    .tax-catalog-summary__value { color: #253858; font-size: 1.5rem; font-weight: 700; }
    .tax-catalog-table thead th { background: #f7fbff; color: #355070; border-bottom-color: #dce8f3; white-space: nowrap; }
    .tax-catalog-table tbody tr { transition: background-color .18s ease, transform .18s ease; }
    .tax-catalog-table tbody tr:hover { background: #f7fbff; transform: translateY(-1px); }
    .badge.bg-purple { background-color: #7868e6; color: #fff; }
    @media (prefers-reduced-motion: reduce) { .tax-catalog-table tbody tr { transition: none; } }
</style>
@endpush

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-body border-bottom py-3">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2">
            <div>
                <h5 class="mb-1"><i class="ti ti-receipt-tax text-primary me-1"></i> Catálogo simple de impuestos</h5>
                <p class="text-muted mb-0">Códigos importados de One Goal. Los catálogos compuestos se configurarán por separado.</p>
            </div>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">Origen: One Goal</span>
        </div>
    </div>

    <div class="card-body">
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3"><div class="tax-catalog-summary p-3"><div class="tax-catalog-summary__label">Códigos</div><div class="tax-catalog-summary__value">{{ $totalCodes }}</div></div></div>
            <div class="col-6 col-lg-3"><div class="tax-catalog-summary p-3"><div class="tax-catalog-summary__label">Disponibles</div><div class="tax-catalog-summary__value text-success">{{ $selectableCodes }}</div></div></div>
            <div class="col-6 col-lg-3"><div class="tax-catalog-summary p-3"><div class="tax-catalog-summary__label">Retenciones</div><div class="tax-catalog-summary__value text-danger">{{ $withholdings }}</div></div></div>
            <div class="col-6 col-lg-3"><div class="tax-catalog-summary p-3"><div class="tax-catalog-summary__label">IEPS</div><div class="tax-catalog-summary__value text-primary">{{ $iepsCodes }}</div></div></div>
        </div>

        <div class="table-responsive">
            <table id="taxCodesTable" class="table tax-catalog-table table-hover align-middle w-100">
                <thead>
                    <tr>
                        <th class="text-center">ID One Goal</th>
                        <th>Nombre</th>
                        <th>Clasificación</th>
                        <th class="text-end">Tasa / cuota</th>
                        <th class="text-center">Cálculo</th>
                        <th class="text-center">Vínculo SAT</th>
                        <th class="text-center">Estado</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(function () {
        $('#taxCodesTable').DataTable({
            processing: true,
            responsive: false,
            pageLength: 50,
            dom: '<"d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center mb-3"Bf>rt<"d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center mt-3"lip>',
            buttons: [
                { extend: 'excel', text: '<i class="ti ti-file-spreadsheet me-1"></i> Exportar Excel', className: 'btn btn-success btn-sm' },
                { extend: 'copy', text: '<i class="ti ti-copy me-1"></i> Copiar', className: 'btn btn-outline-secondary btn-sm' }
            ],
            ajax: { url: "{{ route('tax-codes.datatable') }}", type: 'GET' },
            columns: [
                { data: 'one_goal_id', name: 'one_goal_id', className: 'text-center' },
                { data: 'name', name: 'name' },
                { data: 'classification', name: 'classification', orderable: false, searchable: false },
                { data: 'rate', name: 'rate', className: 'text-end' },
                { data: 'calculation_type', name: 'calculation_type', className: 'text-center', orderable: false, searchable: false },
                { data: 'sat_links', name: 'sat_links', className: 'text-center', orderable: false, searchable: false },
                { data: 'status', name: 'status', className: 'text-center', orderable: false, searchable: false }
            ],
            language: { url: "{{ asset('assets/vendor/datatables.net/es-MX.json') }}" },
            drawCallback: function () { $('.dataTables_paginate > .pagination').addClass('pagination-rounded'); }
        });
    });
</script>
@endpush
