@extends('layouts.zircos')

@section('title', 'Catálogo de Productos y Servicios')
@section('page.title', 'Catálogo de Productos y Servicios')
@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Catálogo de Productos y Servicios</li>
@endsection

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="ti ti-package me-1"></i> Catálogo de Productos y Servicios</h5>
        </div>

        <div class="card-body">
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

            <div class="table-responsive">
                <table id="productsTable" class="table-bordered table-hover w-100 table">
                    <thead class="table-light">
                        <tr>
                            <th style="width:60px;">#</th>
                            <th>Código</th>
                            <th>Tipo</th>
                            <th>Descripción</th>
                            <th>Código SAT</th>
                            <th>Cuenta / Subcuenta</th>
                            <th>Unidad</th>
                            <th>Precio Est.</th>
                            <th>Estatus</th>
                            <th class="text-end" style="width:140px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Modal para rechazar --}}
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="rejectForm" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="rejectModalLabel">Rechazar Producto/Servicio</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="rejection_reason" class="form-label">Motivo del Rechazo <span
                                    class="text-danger">*</span></label>
                            <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="4" required minlength="10"
                                maxlength="500" placeholder="Explica claramente el motivo del rechazo..."></textarea>
                            <div class="form-text">Mínimo 10 caracteres, máximo 500.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning">Rechazar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    {{-- DataTables y sus dependencias se incluyen una vez desde layouts.zircos. --}}
    <script>
        $(function() {
            const table = $('#productsTable').DataTable({
                responsive: false,
                processing: true,
                serverSide: true,
                dom: '<"top"Bf>rt<"bottom"lip>',
                pageLength: 50,
                buttons: [{
                        text: '<i class="ti ti-plus me-1"></i> Nuevo Producto/Servicio',
                        className: 'btn btn-primary btn-sm',
                        action: function() {
                            window.location.href = "{{ route('products-services.create') }}";
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
                        orientation: 'landscape',
                        pageSize: 'A4'
                    }
                ],
                ajax: {
                    url: "{{ route('products-services.datatable') }}",
                    type: "GET",
                    error: function(xhr) {
                        console.error('Error en DataTable:', xhr.responseText);
                    }
                },
                columns: [{
                        data: 'id',
                        name: 'id',
                        width: '60px'
                    },
                    {
                        data: 'code',
                        name: 'code'
                    },
                    {
                        data: 'product_type_badge',
                        name: 'product_type',
                        orderable: false,
                        searchable: true
                    },
                    {
                        data: 'technical_description',
                        name: 'technical_description'
                    },
                    {
                        data: 'sat_product_code',
                        name: 'sat_product_code',
                        defaultContent: '—'
                    },
                    {
                        data: 'cuenta_subcuenta',
                        name: 'cuenta_subcuenta',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'unit_of_measure',
                        name: 'unit_of_measure'
                    },
                    {
                        data: 'estimated_price',
                        name: 'estimated_price'
                    },
                    {
                        data: 'status',
                        name: 'status',
                        orderable: false,
                        searchable: false
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
                drawCallback: function() {
                    $(".dataTables_paginate > .pagination").addClass("pagination-rounded");
                }
            });

            // SweetAlert para eliminar
            $(document).on('click', '.js-delete-btn', function(e) {
                e.preventDefault();
                const btn = $(this);
                const form = btn.closest('form');
                const nombre = btn.data('entity') || 'este producto/servicio';

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
                }).then((res) => {
                    if (res.isConfirmed) form.submit();
                });
            });

            // Activar/Desactivar vía AJAX, sin salir de la página
            $(document).on('submit', '.js-status-action-form', function(e) {
                e.preventDefault();
                const form = $(this);

                $.ajax({
                    url: form.attr('action'),
                    type: 'POST',
                    data: form.serialize(),
                    dataType: 'json'
                }).done(function(res) {
                    table.ajax.reload(null, false);
                    toastOk(res.message);
                }).fail(function(xhr) {
                    const msg = xhr.responseJSON?.message || 'No se pudo completar la acción.';
                    Swal.fire({
                        icon: 'error',
                        title: 'No se pudo completar',
                        text: msg
                    });
                });
            });

            // Modal para rechazar
            $(document).on('click', '.js-reject-btn', function(e) {
                e.preventDefault();
                const url = $(this).data('url');
                const entity = $(this).data('entity');

                $('#rejectForm').attr('action', url);
                $('#rejectModalLabel').text(`Rechazar Producto/Servicio: ${entity}`);
                $('#rejectModal').modal('show');
            });

            // SweetAlert para aprobar
            $(document).on('submit', '.js-approve-form', function(e) {
                e.preventDefault();
                const form = $(this);

                Swal.fire({
                    title: '¿Aprobar producto/servicio?',
                    text: 'El producto quedará disponible para requisiciones.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, aprobar',
                    cancelButtonText: 'Cancelar',
                    reverseButtons: true,
                    customClass: {
                        confirmButton: 'btn btn-success',
                        cancelButton: 'btn btn-light'
                    },
                    buttonsStyling: false
                }).then((res) => {
                    if (res.isConfirmed) form[0].submit();
                });
            });
        });

        window.toastOk = window.toastOk || function(msg = 'Operación exitosa') {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true
            });
            Toast.fire({
                icon: 'success',
                title: msg
            });
        };
    </script>
@endpush
