@extends('layouts.zircos')

@section('title', 'Grupos de impuestos')
@section('page.title', 'Grupos de impuestos')
@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
    <li class="breadcrumb-item">Administración</li>
    <li class="breadcrumb-item active">Grupos de impuestos</li>
@endsection

@push('styles')
<style>
    .tax-group-summary { border: 1px solid #e2e9f0; border-radius: .75rem; background: #fff; box-shadow: 0 3px 12px rgba(36, 59, 83, .04); }
    .tax-group-summary__label { color: #6c7a89; font-size: .75rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; }
    .tax-group-summary__value { color: #253858; font-size: 1.5rem; font-weight: 700; }
    .tax-group-table thead th { background: #f7fbff; color: #355070; border-bottom-color: #dce8f3; white-space: nowrap; }
    .tax-group-table tbody tr { transition: background-color .18s ease, transform .18s ease; }
    .tax-group-table tbody tr:hover { background: #f7fbff; transform: translateY(-1px); }
    @media (prefers-reduced-motion: reduce) { .tax-group-table tbody tr { transition: none; } }
</style>
@endpush

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-body border-bottom py-3 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2">
        <div>
            <h5 class="mb-1"><i class="ti ti-layers-linked text-primary me-1"></i> Grupos compuestos de impuestos</h5>
            <p class="text-muted mb-0">Configuración importada de One Goal: cada componente conserva su impuesto simple y cuenta contable.</p>
        </div>
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">Origen: One Goal</span>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-4"><div class="tax-group-summary p-3"><div class="tax-group-summary__label">Grupos</div><div class="tax-group-summary__value">{{ $totalGroups }}</div></div></div>
            <div class="col-6 col-lg-4"><div class="tax-group-summary p-3"><div class="tax-group-summary__label">Activos</div><div class="tax-group-summary__value text-success">{{ $activeGroups }}</div></div></div>
            <div class="col-12 col-lg-4"><div class="tax-group-summary p-3"><div class="tax-group-summary__label">Con cuenta configurada</div><div class="tax-group-summary__value text-primary">{{ $groupsWithAccounts }}</div></div></div>
        </div>
        <div class="table-responsive">
            <table id="taxGroupsTable" class="table tax-group-table table-hover align-middle w-100">
                <thead><tr><th class="text-center">ID One Goal</th><th>Grupo</th><th class="text-center">Componentes</th><th class="text-center">Estado</th><th class="text-end">Acciones</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(function () {
        $('#taxGroupsTable').DataTable({
            processing: true,
            pageLength: 25,
            ajax: { url: "{{ route('tax-groups.datatable') }}", type: 'GET' },
            columns: [
                { data: 'one_goal_id', name: 'one_goal_id', className: 'text-center' },
                { data: 'name', name: 'name' },
                { data: 'components', name: 'components', className: 'text-center', orderable: false, searchable: false },
                { data: 'status', name: 'status', className: 'text-center', orderable: false, searchable: false },
                { data: 'actions', name: 'actions', className: 'text-end', orderable: false, searchable: false }
            ],
            language: { url: "{{ asset('assets/vendor/datatables.net/es-MX.json') }}" },
            drawCallback: function () { $('.dataTables_paginate > .pagination').addClass('pagination-rounded'); }
        });
    });
</script>
@endpush
