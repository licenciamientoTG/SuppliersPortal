@extends('layouts.zircos')

@section('title', 'Contratos Comerciales')
@section('page.title', 'Contratos Comerciales')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Contratos</li>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row mb-3">
        <div class="col">
            <a href="{{ route('contracts.create') }}" class="btn btn-primary">
                <i class="ti ti-plus me-1"></i> Nuevo Contrato
            </a>
            <a href="{{ route('contracts.import') }}" class="btn btn-outline-secondary ms-2">
                <i class="ti ti-upload me-1"></i> Carga Masiva
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table id="contracts-table" class="table table-hover w-100">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Folio</th>
                        <th>Proveedor</th>
                        <th>Empresa</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function () {
    $('#contracts-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("contracts.datatable") }}',
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'folio_col', name: 'folio' },
            { data: 'supplier_name', name: 'supplier.company_name' },
            { data: 'company_name', name: 'company.name' },
            { data: 'start_date_col', name: 'start_date' },
            { data: 'end_date_col', name: 'end_date' },
            { data: 'status_col', name: 'status', orderable: false },
            { data: 'actions', orderable: false, searchable: false },
        ],
    });
});
</script>
@endpush
