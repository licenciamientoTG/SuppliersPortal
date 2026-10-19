@extends('layouts.zircos')

@section('title', 'Resumen de Recepciones Pendientes')
@section('page.title', 'Resumen de Recepciones Pendientes')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Recepciones Pendientes</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12">

        {{-- Alertas de sesión --}}
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                <i class="ti ti-check me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="card shadow-sm border-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="ti ti-package-import me-2"></i>Órdenes Pendientes de Recepción
                </h5>
                <span class="badge bg-warning fs-12">
                    {{ $regularCount + $directCount }} pendientes en total
                </span>
            </div>

            <div class="card-body">

                {{-- Leyenda del semáforo --}}
                <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
                    <small class="text-muted fw-semibold">Semáforo de días transcurridos:</small>
                    <span class="badge bg-success"><i class="ti ti-circle-check me-1"></i>0–7 días — En tiempo</span>
                    <span class="badge bg-warning"><i class="ti ti-alert-triangle me-1"></i>8–15 días — Demorada</span>
                    <span class="badge bg-danger"><i class="ti ti-circle-x me-1"></i>16+ días — Crítica</span>
                </div>

                {{-- Tabs OC Regular / OCD --}}
                <ul class="nav nav-tabs nav-bordered mb-3" id="overviewTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active"
                                id="tab-regular-btn"
                                data-bs-toggle="tab"
                                data-bs-target="#tab-regular"
                                type="button"
                                role="tab"
                                aria-controls="tab-regular"
                                aria-selected="true">
                            <i class="ti ti-list me-1"></i>
                            OC Estándar
                            <span class="badge bg-soft-primary text-primary ms-1">{{ $regularCount }}</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link"
                                id="tab-direct-btn"
                                data-bs-toggle="tab"
                                data-bs-target="#tab-direct"
                                type="button"
                                role="tab"
                                aria-controls="tab-direct"
                                aria-selected="false">
                            <i class="ti ti-bolt me-1"></i>
                            OC Directas
                            <span class="badge bg-soft-warning text-warning ms-1">{{ $directCount }}</span>
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="overviewTabsContent">

                    {{-- TAB 1: OC ESTÁNDAR --}}
                    <div class="tab-pane fade show active" id="tab-regular" role="tabpanel" aria-labelledby="tab-regular-btn">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover w-100" id="regular-pending-table">
                                <thead class="table-light">
                                    <tr>
                                        <th>Folio</th>
                                        <th>Proveedor</th>
                                        <th>Punto de Entrega</th>
                                        <th>Estado</th>
                                        <th>Fecha Emisión</th>
                                        <th>Días Transcurridos</th>
                                        <th>Días Restantes</th>
                                        <th width="100px">Acciones</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>

                    {{-- TAB 2: OC DIRECTAS --}}
                    <div class="tab-pane fade" id="tab-direct" role="tabpanel" aria-labelledby="tab-direct-btn">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover w-100" id="direct-pending-table">
                                <thead class="table-light">
                                    <tr>
                                        <th>Folio</th>
                                        <th>Proveedor</th>
                                        <th>Punto de Entrega</th>
                                        <th>Solicitante</th>
                                        <th>Estado</th>
                                        <th>Fecha Emisión</th>
                                        <th>Días Transcurridos</th>
                                        <th>Días Restantes</th>
                                        <th width="100px">Acciones</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>

                </div>{{-- /tab-content --}}
            </div>
        </div>

    </div>
</div>
@endsection

@push('styles')
<style>
@keyframes pulse-urgente {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.5; }
}
.urgente-pulse {
    animation: pulse-urgente 1.4s ease-in-out infinite;
}
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function () {

    // ─── DataTable: OC ESTÁNDAR pendientes ────────────────────────────────────
    const regularTable = $('#regular-pending-table').DataTable({
        processing: true,
        // Sin serverSide: la ajax trae todas las órdenes pendientes de una vez, para que
        // las exportaciones incluyan todos los renglones y no solo la página visible.
        ajax: {
            url: "{{ route('receptions.datatable.regular-pending') }}",
            type: 'GET'
        },
        columns: [
            { data: 'folio',             name: 'folio',                         orderable: true },
            { data: 'proveedor',         name: 'supplier.company_name',         orderable: true },
            { data: 'punto_entrega',     name: 'receivingLocation.name',        orderable: true },
            { data: 'estado',            name: 'status',                        orderable: true },
            { data: 'emision',           name: 'issued_at',                     orderable: true },
            { data: 'dias_transcurridos',name: 'issued_at',                     orderable: false, searchable: false },
            { data: 'dias_restantes',    name: 'dias_restantes',                orderable: false, searchable: false },
            { data: 'actions',           name: 'actions',                       orderable: false, searchable: false }
        ],
        createdRow: function (row, data, index) {
            if ($(row).attr('data-urgent') === '1') {
                $(row).addClass('table-danger');
            }
        },
        order: [[4, 'asc']], // Más antiguas primero (más urgentes)
        language: {
            url: "{{ asset('assets/vendor/datatables.net/es-MX.json') }}"
        },
        pageLength: 25,
        dom: '<"top"Bf>rt<"bottom"lip>',
        buttons: [
            {
                extend: 'excel',
                text: '<i class="ti ti-file-spreadsheet me-1"></i> Excel',
                className: 'btn btn-success btn-sm',
                exportOptions: {
                    columns: ':not(:last-child)' // Excluir columna de Acciones
                }
            },
            {
                extend: 'copy',
                text: '<i class="ti ti-copy me-1"></i> Copiar',
                className: 'btn btn-warning btn-sm',
                exportOptions: {
                    columns: ':not(:last-child)'
                }
            }
        ],
        drawCallback: function () {
            $(".dataTables_paginate > .pagination").addClass("pagination-rounded");
        }
    });

    // ─── DataTable: OC DIRECTAS pendientes (lazy — se inicia al abrir el tab) ─
    let directTable = null;

    $('#tab-direct-btn').on('shown.bs.tab', function () {
        if (directTable === null) {
            directTable = $('#direct-pending-table').DataTable({
                processing: true,
                // Sin serverSide: ver nota arriba.
                ajax: {
                    url: "{{ route('receptions.datatable.direct-pending') }}",
                    type: 'GET'
                },
                columns: [
                    { data: 'folio',             name: 'folio',                     orderable: true },
                    { data: 'proveedor',         name: 'supplier.company_name',     orderable: true },
                    { data: 'punto_entrega',     name: 'receivingLocation.name',    orderable: true },
                    { data: 'solicitante',       name: 'creator.name',              orderable: true },
                    { data: 'estado',            name: 'status',                    orderable: true },
                    { data: 'emision',           name: 'issued_at',                 orderable: true },
                    { data: 'dias_transcurridos',name: 'issued_at',                 orderable: false, searchable: false },
                    { data: 'dias_restantes',    name: 'dias_restantes',            orderable: false, searchable: false },
                    { data: 'actions',           name: 'actions',                   orderable: false, searchable: false }
                ],
                createdRow: function (row, data, index) {
                    if ($(row).attr('data-urgent') === '1') {
                        $(row).addClass('table-danger');
                    }
                },
                order: [[5, 'asc']], // Más antiguas primero
                language: {
                    url: "{{ asset('assets/vendor/datatables.net/es-MX.json') }}"
                },
                pageLength: 25,
                dom: '<"top"Bf>rt<"bottom"lip>',
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="ti ti-file-spreadsheet me-1"></i> Excel',
                        className: 'btn btn-success btn-sm',
                        exportOptions: {
                            columns: ':not(:last-child)' // Excluir columna de Acciones
                        }
                    },
                    {
                        extend: 'copy',
                        text: '<i class="ti ti-copy me-1"></i> Copiar',
                        className: 'btn btn-warning btn-sm',
                        exportOptions: {
                            columns: ':not(:last-child)'
                        }
                    }
                ],
                drawCallback: function () {
                    $(".dataTables_paginate > .pagination").addClass("pagination-rounded");
                }
            });
        } else {
            directTable.draw();
        }
    });

    // Ajustar columnas al cambiar de tab
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function () {
        $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
    });

});
</script>
@endpush
