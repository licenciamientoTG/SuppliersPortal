@extends('layouts.zircos')

@section('title', 'Centros de Costo')
@section('page.title', 'Centros de Costo')
@section('page.breadcrumbs')
<li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
<li class="breadcrumb-item active">Centros de Costo</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="ti ti-hierarchy-2 me-1"></i> Centros de Costo</h5>
        <div class="d-flex gap-2">
            <a href="{{ route('cost-centers.import.template') }}" class="btn btn-outline-success btn-sm">
                <i class="ti ti-download me-1"></i> Descargar layout
            </a>
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#importCostCentersModal">
                <i class="ti ti-file-upload me-1"></i> Carga masiva
            </button>
        </div>
    </div>

    <div class="card-body">
        {{-- Flash --}}
        @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>No se pudo validar el archivo:</strong>
            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        <div class="table-responsive">
            <table id="costCentersTable" class="table-bordered table-hover w-100 table">
                <thead class="table-light">
                    <tr>
                        <th style="width:60px;">#</th>
                        <th>Compañía</th>
                        <th>Categoría</th>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Tipo de Compra</th>
                        <th>Responsable</th>
                        <th class="text-center">Presupuesto</th>
                        <th class="text-center">Estado</th>
                        <th class="text-end" style="width:140px;">Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="importCostCentersModal" tabindex="-1" aria-labelledby="importCostCentersModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('cost-centers.import.preview') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="importCostCentersModalLabel">
                        <i class="ti ti-file-upload me-1"></i> Carga masiva de centros de costo
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>Flujo sugerido:</strong>
                        descarga el layout, llena el archivo con los selectores del sistema, súbelo aquí y revisa el preview antes de confirmar.
                    </div>

                    <div class="mb-3">
                        <label for="excel_file" class="form-label">Archivo Excel (.xlsx) <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="excel_file" name="excel_file" accept=".xlsx" required>
                        <div class="form-text">Solo se aceptan archivos `.xlsx` generados con el layout del portal.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-search me-1"></i> Validar archivo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
{{-- DataTables --}}
<script>
    document.addEventListener('click', function(e) {
        // Delegación: detecta clicks en botones con clase .js-delete-btn
        const btn = e.target.closest('.js-delete-btn');
        if (!btn) return;

        e.preventDefault();

        // El form contenedor (d-inline)
        const form = btn.closest('form');
        if (!form) return;

        const nombre = btn.getAttribute('data-entity') || 'este registro';

        Swal.fire({
            title: '¿Eliminar?',
            text: `Vas a eliminar ${nombre}. Esta acción no se puede deshacer.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            customClass: {
                confirmButton: 'btn btn-danger',
                cancelButton: 'btn btn-light'
            },
            buttonsStyling: false
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });

    $(function() {
        // DataTable
        const table = $('#costCentersTable').DataTable({
            responsive: false,
            serverSide: false,
            processing: true,
            dom: '<"top"Bf>rt<"bottom"lip>',
            pageLength: 50,
            order: [
                [0, 'desc']
            ],
            buttons: [{
                    text: '<i class="ti ti-hierarchy-2 me-1"></i> Nuevo centro',
                    className: 'btn btn-primary btn-sm',
                    attr: {
                        id: 'btnCreateCostCenter',
                        title: 'Crear nuevo centro de costo'
                    },
                    action: function() {
                        window.location.href = "{{ route('cost-centers.create') }}";
                    }
                },
                {
                    extend: 'excel',
                    text: '<i class="ti ti-file-spreadsheet me-1"></i> Excel',
                    className: 'btn btn-success btn-sm'
                },
                {
                    extend: 'copy',
                    text: '<i class="ti ti-copy me-1"></i> Copiar',
                    className: 'btn btn-warning btn-sm'
                },
                {
                    extend: 'pdf',
                    text: '<i class="ti ti-file-text me-1"></i> PDF',
                    className: 'btn btn-info btn-sm',
                    orientation: 'portrait',
                    pageSize: 'A4'
                }
            ],
            ajax: {
                url: "{{ route('cost-centers.datatable') }}",
                type: "GET",
                error: function(xhr) {
                    console.error('Error en DataTable:', xhr.responseText);
                }
            },
            columns: [{
                    data: 'id',
                    name: 'id',
                    width: '25px'
                },
                {
                    data: 'company_name',
                    name: 'company.name',
                    defaultContent: '—'
                },
                {
                    data: 'category_name',
                    name: 'category.name',
                    defaultContent: '—'
                },
                {
                    data: 'code',
                    name: 'code'
                },
                {
                    data: 'name',
                    name: 'name'
                },
                {
                    data: 'purchase_type_label',
                    name: 'purchase_type',
                    defaultContent: '—'
                },
                {
                    data: 'responsible_name',
                    name: 'responsible.name',
                    defaultContent: '—'
                },
                {
                    data: 'budget_type_label',
                    name: 'budget_type',
                    orderable: false,
                    searchable: false,
                    className: 'text-center'
                },
                {
                    data: 'status',
                    name: 'status',
                    orderable: false,
                    searchable: false,
                    className: 'text-center'
                },
                {
                    data: 'actions',
                    name: 'actions',
                    orderable: false,
                    searchable: false,
                    className: 'text-end'
                }
            ],
            language: {
                url: "{{ asset('assets/vendor/datatables.net/es-MX.json') }}"
            },

            pageLength: 25,
            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, "Todos"]
            ],

            ordering: true,
            order: [
                [0, 'desc']
            ],

            drawCallback: function() {
                $(".dataTables_paginate > .pagination").addClass("pagination-rounded");
            }
        });
    });
</script>
@endpush
