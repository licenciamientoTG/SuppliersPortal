@extends('layouts.zircos')

@section('title', 'Cuentas contables')
@section('page.title', 'Cuentas contables')
@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
    <li class="breadcrumb-item">Administración</li>
    <li class="breadcrumb-item active">Cuentas contables</li>
@endsection

@push('styles')
<style>
    .ledger-summary { border: 1px solid #e2e9f0; border-radius: .75rem; background: #fff; box-shadow: 0 3px 12px rgba(36, 59, 83, .04); }
    .ledger-summary__label { color: #6c7a89; font-size: .75rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; }
    .ledger-summary__value { color: #253858; font-size: 1.5rem; font-weight: 700; }
    .ledger-table thead th { background: #f7fbff; color: #355070; border-bottom-color: #dce8f3; white-space: nowrap; }
    .ledger-table tbody tr { transition: background-color .18s ease, transform .18s ease; }
    .ledger-table tbody tr:hover { background: #f7fbff; transform: translateY(-1px); }
    @media (prefers-reduced-motion: reduce) { .ledger-table tbody tr { transition: none; } }
</style>
@endpush

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-body border-bottom py-3 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2">
        <div>
            <h5 class="mb-1"><i class="ti ti-building-bank text-primary me-1"></i> Catálogo de cuentas contables</h5>
            <p class="text-muted mb-0">Plan contable importado de One Goal. Es de consulta para preservar su estructura y vínculos fiscales.</p>
        </div>
        <a href="{{ route('ledger-accounts.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i> Nueva cuenta</a>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-4"><div class="ledger-summary p-3"><div class="ledger-summary__label">Cuentas</div><div class="ledger-summary__value">{{ $totalAccounts }}</div></div></div>
            <div class="col-6 col-lg-4"><div class="ledger-summary p-3"><div class="ledger-summary__label">Activas</div><div class="ledger-summary__value text-success">{{ $activeAccounts }}</div></div></div>
            <div class="col-12 col-lg-4"><div class="ledger-summary p-3"><div class="ledger-summary__label">Usadas en impuestos</div><div class="ledger-summary__value text-primary">{{ $linkedAccounts }}</div></div></div>
        </div>
        <div class="table-responsive">
            <table id="ledgerAccountsTable" class="table ledger-table table-hover align-middle w-100">
                <thead><tr><th>Cuenta</th><th>Nombre</th><th>Cuenta padre</th><th class="text-center">Nivel</th><th class="text-center">Uso fiscal</th><th class="text-center">Estado</th><th class="text-end">Acciones</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(function () {
        $('#ledgerAccountsTable').DataTable({
            processing: true,
            pageLength: 50,
            ajax: { url: "{{ route('ledger-accounts.datatable') }}", type: 'GET' },
            columns: [
                { data: 'code', name: 'code' },
                { data: 'name', name: 'name' },
                { data: 'parent', name: 'parent', orderable: false, searchable: false },
                { data: 'account_level', name: 'account_level', className: 'text-center' },
                { data: 'usage', name: 'usage', className: 'text-center', orderable: false, searchable: false },
                { data: 'status', name: 'status', className: 'text-center', orderable: false, searchable: false },
                { data: 'actions', name: 'actions', className: 'text-end', orderable: false, searchable: false }
            ],
            order: [[0, 'asc']],
            language: { url: "{{ asset('assets/vendor/datatables.net/es-MX.json') }}" },
            drawCallback: function () { $('.dataTables_paginate > .pagination').addClass('pagination-rounded'); }
        });
    });
</script>
@endpush
