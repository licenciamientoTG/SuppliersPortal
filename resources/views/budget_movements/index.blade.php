@extends('layouts.zircos')

@section('title', 'Movimientos Presupuestales')

@section('page.title', 'Movimientos Presupuestales')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('budget_movements.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Movimientos Presupuestales</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="ti ti-arrows-exchange me-2"></i>
            Movimientos Presupuestales
        </h5>
    </div>
    <div class="card-body">
        <!-- Tabla de movimientos -->
        <div class="table-responsive">
            <table id="movementsTable" class="table-bordered table-hover w-100 table">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Tipo</th>
                        <th>Año Fiscal</th>
                        <th>Fecha</th>
                        <th>Monto</th>
                        <th>Estado</th>
                        <th>Creado por</th>
                        <th>Aprobado por</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- DataTables cargará los datos aquí -->
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        // Inicializar DataTable
        const table = $('#movementsTable').DataTable({
            processing: true,
            // Sin serverSide: la ajax trae todos los movimientos de una vez, para que
            // las exportaciones incluyan todos los renglones y no solo la página visible.
            dom: '<"top"Bf>rt<"bottom"lip>',
            pageLength: 25,
            buttons: [
                {
                    text: '<i class="ti ti-plus me-1"></i> Nuevo Movimiento',
                    className: 'btn btn-primary btn-sm',
                    action: function() {
                        window.location.href = "{{ route('budget_movements.create') }}";
                    }
                },
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
            ajax: {
                url: "{{ route('budget_movements.index') }}",
                type: 'GET'
            },
            columns: [{
                    data: 'id',
                    name: 'id'
                },
                {
                    data: 'type_badge',
                    name: 'movement_type',
                    orderable: false,
                    searchable: false
                },
                {
                    data: 'fiscal_year',
                    name: 'fiscal_year'
                },
                {
                    data: 'movement_date',
                    name: 'movement_date'
                },
                {
                    data: 'formatted_amount',
                    name: 'total_amount',
                    orderable: true,
                    searchable: false
                },
                {
                    data: 'status_badge',
                    name: 'status',
                    orderable: false,
                    searchable: false
                },
                {
                    data: 'creator_name',
                    name: 'creator.name'
                },
                {
                    data: 'approver_name',
                    name: 'approver.name'
                },
                {
                    data: 'actions',
                    name: 'actions',
                    orderable: false,
                    searchable: false
                }
            ],
            order: [
                [0, 'desc']
            ],
            language: {
                url: "{{ asset('assets/vendor/datatables.net/es-MX.json') }}"
            },
            responsive: true
        });

        // Aprobar movimiento
        $(document).on('click', '.btn-approve', function() {
            const movementId = $(this).data('id');

            // Crear el modal HTML
            const modalHtml = `
            <div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="approveModalLabel">
                                <i class="ti ti-check me-2"></i>
                                Aprobar Movimiento Presupuestal
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-warning mb-3">
                                <i class="ti ti-alert-triangle me-2"></i>
                                <strong>Atención:</strong> Esta acción aplicará cambios permanentes al presupuesto.
                            </div>
                            
                            <p class="mb-3">Antes de aprobar, por favor verifique lo siguiente:</p>
                            
                            <div class="form-check mb-2">
                                <input class="form-check-input approve-check" type="checkbox" id="check1">
                                <label class="form-check-label" for="check1">
                                    He revisado los <strong>montos</strong> del movimiento
                                </label>
                            </div>
                            
                            <div class="form-check mb-2">
                                <input class="form-check-input approve-check" type="checkbox" id="check2">
                                <label class="form-check-label" for="check2">
                                    He verificado los <strong>centros de costo</strong> afectados
                                </label>
                            </div>
                            
                            <div class="form-check mb-2">
                                <input class="form-check-input approve-check" type="checkbox" id="check3">
                                <label class="form-check-label" for="check3">
                                    He revisado la <strong>justificación</strong> del movimiento
                                </label>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input approve-check" type="checkbox" id="check4">
                                <label class="form-check-label" for="check4">
                                    Confirmo que tengo <strong>autorización</strong> para aprobar este movimiento
                                </label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="ti ti-x me-1"></i>
                                Cancelar
                            </button>
                            <button type="button" class="btn btn-success" id="confirmApproveBtn" disabled>
                                <i class="ti ti-check me-1"></i>
                                Aprobar Movimiento
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

            // Remover modal anterior si existe
            $('#approveModal').remove();

            // Agregar modal al body
            $('body').append(modalHtml);

            // Mostrar el modal
            const modal = new bootstrap.Modal(document.getElementById('approveModal'));
            modal.show();

            // Habilitar botón solo cuando todos los checks estén marcados
            $('.approve-check').on('change', function() {
                const allChecked = $('.approve-check:checked').length === $('.approve-check').length;
                $('#confirmApproveBtn').prop('disabled', !allChecked);
            });

            // Manejar la aprobación cuando se hace clic en el botón
            $('#confirmApproveBtn').on('click', function() {
                const btn = $(this);
                btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Procesando...');

                $.ajax({
                    url: `/budget_movements/${movementId}/approve`,
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        modal.hide();
                        Swal.fire({
                            title: '¡Aprobado!',
                            text: response.message,
                            icon: 'success',
                            customClass: {
                                confirmButton: 'btn btn-success'
                            },
                            buttonsStyling: false
                        });
                        table.ajax.reload();
                    },
                    error: function(xhr) {
                        modal.hide();
                        Swal.fire({
                            title: 'Error',
                            text: xhr.responseJSON?.message || 'Error al aprobar el movimiento',
                            icon: 'error',
                            iconHtml: '<i class="ti ti-alert-triangle"></i>',
                            customClass: {
                                confirmButton: 'btn btn-danger'
                            },
                            buttonsStyling: false
                        });
                    },
                    complete: function() {
                        $('#approveModal').remove();
                    }
                });
            });

            // Limpiar modal cuando se cierre
            $('#approveModal').on('hidden.bs.modal', function() {
                $(this).remove();
            });
        });

        // Rechazar movimiento
        $(document).on('click', '.btn-reject', function() {
            const movementId = $(this).data('id');

            Swal.fire({
                title: '¿Rechazar movimiento?',
                text: 'El movimiento no se aplicará al presupuesto.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="ti ti-x me-1"></i>Sí, rechazar',
                cancelButtonText: '<i class="ti ti-arrow-back me-1"></i>Cancelar',
                customClass: {
                    confirmButton: 'btn btn-danger',
                    cancelButton: 'btn btn-secondary'
                },
                buttonsStyling: false,
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: `/budget_movements/${movementId}/reject`,
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            Swal.fire({
                                title: 'Rechazado',
                                text: response.message,
                                icon: 'info',
                                iconHtml: '<i class="ti ti-info-circle"></i>',
                                customClass: {
                                    confirmButton: 'btn btn-primary'
                                },
                                buttonsStyling: false
                            });
                            table.ajax.reload();
                        },
                        error: function(xhr) {
                            Swal.fire({
                                title: 'Error',
                                text: xhr.responseJSON?.message || 'Error al rechazar el movimiento',
                                icon: 'error',
                                iconHtml: '<i class="ti ti-alert-triangle"></i>',
                                customClass: {
                                    confirmButton: 'btn btn-danger'
                                },
                                buttonsStyling: false
                            });
                        }
                    });
                }
            });
        });

        // Eliminar movimiento
        $(document).on('click', '.btn-delete', function() {
            const movementId = $(this).data('id');

            Swal.fire({
                title: '¿Eliminar movimiento?',
                text: 'Esta acción no se puede deshacer.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="ti ti-trash me-1"></i>Sí, eliminar',
                cancelButtonText: '<i class="ti ti-x me-1"></i>Cancelar',
                customClass: {
                    confirmButton: 'btn btn-danger',
                    cancelButton: 'btn btn-secondary'
                },
                buttonsStyling: false,
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: `/budget_movements/${movementId}`,
                        type: 'DELETE',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            Swal.fire({
                                title: '¡Eliminado!',
                                text: response.message,
                                icon: 'success',
                                iconHtml: '<i class="ti ti-check"></i>',
                                customClass: {
                                    confirmButton: 'btn btn-success'
                                },
                                buttonsStyling: false
                            });
                            table.ajax.reload();
                        },
                        error: function(xhr) {
                            Swal.fire({
                                title: 'Error',
                                text: xhr.responseJSON?.message || 'Error al eliminar el movimiento',
                                icon: 'error',
                                iconHtml: '<i class="ti ti-alert-triangle"></i>',
                                customClass: {
                                    confirmButton: 'btn btn-danger'
                                },
                                buttonsStyling: false
                            });
                        }
                    });
                }
            });
        });
    });
</script>
@endpush