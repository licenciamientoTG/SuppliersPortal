@extends('layouts.zircos')

@section('title', 'Detalle del Movimiento Presupuestal')

@section('page.title', 'Detalle del Movimiento Presupuestal')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('budget_movements.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('budget_movements.index') }}">Movimientos Presupuestales</a></li>
    <li class="breadcrumb-item active">Detalle #{{ $budgetMovement->id }}</li>
@endsection

@section('content')
<div class="row">
    <!-- Información General -->
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="ti ti-info-circle me-2"></i>
                    Información General
                </h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Tipo de Movimiento:</strong>
                        @php
                        $typeBadges = [
                        'TRANSFERENCIA' => '<span class="badge bg-info">Transferencia</span>',
                        'AMPLIACION' => '<span class="badge bg-primary">Ampliación</span>',
                        'REDUCCION' => '<span class="badge bg-secondary">Reducción</span>',
                        ];
                        @endphp
                        {!! $typeBadges[$budgetMovement->movement_type] ?? '' !!}
                    </div>
                    <div class="col-md-6">
                        <strong>Estado:</strong>
                        @php
                        $statusBadges = [
                        'PENDIENTE' => '<span class="badge bg-warning">Pendiente</span>',
                        'APROBADO' => '<span class="badge bg-success">Aprobado</span>',
                        'RECHAZADO' => '<span class="badge bg-danger">Rechazado</span>',
                        ];
                        @endphp
                        {!! $statusBadges[$budgetMovement->status] ?? '' !!}
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Año Fiscal:</strong>
                        <p class="mb-0">{{ $budgetMovement->fiscal_year }}</p>
                    </div>
                    <div class="col-md-6">
                        <strong>Fecha del Movimiento:</strong>
                        <p class="mb-0">{{ $budgetMovement->movement_date->format('d/m/Y') }}</p>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-12">
                        <strong>Monto Total:</strong>
                        <p class="mb-0 text-primary fs-4">${{ number_format($budgetMovement->total_amount, 2) }}</p>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-12">
                        <strong>Justificación:</strong>
                        <p class="mb-0">{{ $budgetMovement->justification }}</p>
                    </div>
                </div>

                <hr>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Creado por:</strong>
                        <p class="mb-0">{{ $budgetMovement->creator->name }}</p>
                        <small class="text-muted">{{ $budgetMovement->created_at->format('d/m/Y H:i') }}</small>
                    </div>
                    @if($budgetMovement->approver)
                    <div class="col-md-6">
                        <strong>{{ $budgetMovement->status === 'APROBADO' ? 'Aprobado' : 'Rechazado' }} por:</strong>
                        <p class="mb-0">{{ $budgetMovement->approver->name }}</p>
                        <small class="text-muted">{{ $budgetMovement->approved_at->format('d/m/Y H:i') }}</small>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Detalles del Movimiento -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="ti ti-list-check me-2"></i>
                    Detalles del Movimiento
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Tipo</th>
                                <th>Centro de Costo</th>
                                <th>Mes</th>
                                <th>Cuenta</th>
                                <th class="text-end">Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($budgetMovement->details as $detail)
                            <tr>
                                <td>
                                    @php
                                    $detailBadges = [
                                    'ORIGEN' => '<span class="badge bg-danger">Origen (-)</span>',
                                    'DESTINO' => '<span class="badge bg-success">Destino (+)</span>',
                                    'AJUSTE' => '<span class="badge bg-info">Ajuste</span>',
                                    ];
                                    @endphp
                                    {!! $detailBadges[$detail->detail_type] ?? '' !!}
                                </td>
                                <td>{{ $detail->costCenter->name }}</td>
                                <td>{{ $detail->month_name }}</td>
                                <td>{{ $detail->expenseCategory->name }}</td>
                                <td class="text-end">
                                    <span class="{{ $detail->amount < 0 ? 'text-danger' : 'text-success' }}">
                                        {{ $detail->amount < 0 ? '-' : '+' }}${{ number_format(abs($detail->amount), 2) }}
                                    </span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted">No hay detalles registrados</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar de Acciones -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="ti ti-settings me-2"></i>
                    Acciones
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <!-- Volver -->
                    <a href="{{ route('budget_movements.index') }}" class="btn btn-secondary">
                        <i class="tabler-file-text me-1"></i>
                        Volver al Listado
                    </a>

                    @if($budgetMovement->isPending())
                    <!-- Editar (solo si está pendiente) -->
                    <a href="{{ route('budget_movements.edit', $budgetMovement) }}" class="btn btn-warning">
                        <i class="ti ti-pencil me-1"></i>
                        Editar
                    </a>

                    <!-- Aprobar -->
                    <button type="button" class="btn btn-success btn-approve" data-id="{{ $budgetMovement->id }}">
                        <i class="ti ti-circle-check me-1"></i>
                        Aprobar
                    </button>

                    <!-- Rechazar -->
                    <button type="button" class="btn btn-danger btn-reject" data-id="{{ $budgetMovement->id }}">
                        <i class="ti ti-circle-x me-1"></i>
                        Rechazar
                    </button>

                    <hr>

                    <!-- Eliminar -->
                    <button type="button" class="btn btn-outline-danger btn-delete" data-id="{{ $budgetMovement->id }}">
                        <i class="ti ti-trash me-1"></i>
                        Eliminar
                    </button>
                    @endif
                </div>
            </div>
        </div>

        <!-- Timeline de Estado -->
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="ti ti-history me-2"></i>
                    Historial
                </h5>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-marker bg-primary"></div>
                        <div class="timeline-content">
                            <small class="text-muted">{{ $budgetMovement->created_at->format('d/m/Y H:i') }}</small>
                            <p class="mb-0"><strong>Creado</strong></p>
                            <small>por {{ $budgetMovement->creator->name }}</small>
                        </div>
                    </div>

                    @if($budgetMovement->approved_at)
                    <div class="timeline-item">
                        <div class="timeline-marker {{ $budgetMovement->status === 'APROBADO' ? 'bg-success' : 'bg-danger' }}"></div>
                        <div class="timeline-content">
                            <small class="text-muted">{{ $budgetMovement->approved_at->format('d/m/Y H:i') }}</small>
                            <p class="mb-0"><strong>{{ $budgetMovement->status === 'APROBADO' ? 'Aprobado' : 'Rechazado' }}</strong></p>
                            <small>por {{ $budgetMovement->approver->name }}</small>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .timeline {
        position: relative;
        padding-left: 30px;
    }

    .timeline-item {
        position: relative;
        padding-bottom: 20px;
    }

    .timeline-item:not(:last-child)::before {
        content: '';
        position: absolute;
        left: -21px;
        top: 20px;
        width: 2px;
        height: calc(100% - 10px);
        background-color: #dee2e6;
    }

    .timeline-marker {
        position: absolute;
        left: -25px;
        top: 0;
        width: 10px;
        height: 10px;
        border-radius: 50%;
    }

    .timeline-content {
        padding-top: 0;
    }
</style>
@endpush

@push('scripts')
<script>
    $(document).ready(function() {
        // Aprobar movimiento
        $('.btn-approve').on('click', function() {
            const movementId = $(this).data('id');

            // Crear el modal HTML
            const modalHtml = `
            <div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
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
                        }).then(() => {
                            window.location.reload();
                        });
                    },
                    error: function(xhr) {
                        modal.hide();
                        Swal.fire({
                            title: 'Error',
                            text: xhr.responseJSON?.message || 'Error al aprobar el movimiento',
                            icon: 'error',
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
        $('.btn-reject').on('click', function() {
            const movementId = $(this).data('id');

            Swal.fire({
                title: '¿Rechazar movimiento?',
                text: 'El movimiento no se aplicará al presupuesto.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, rechazar',
                cancelButtonText: 'Cancelar',
                customClass: {
                    confirmButton: 'btn btn-danger',
                    cancelButton: 'btn btn-secondary'
                },
                buttonsStyling: false
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
                                customClass: {
                                    confirmButton: 'btn btn-primary'
                                },
                                buttonsStyling: false
                            }).then(() => {
                                window.location.reload();
                            });
                        },
                        error: function(xhr) {
                            Swal.fire({
                                title: 'Error',
                                text: xhr.responseJSON?.message || 'Error al rechazar el movimiento',
                                icon: 'error',
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
        $('.btn-delete').on('click', function() {
            const movementId = $(this).data('id');

            Swal.fire({
                title: '¿Eliminar movimiento?',
                text: 'Esta acción no se puede deshacer.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                customClass: {
                    confirmButton: 'btn btn-danger',
                    cancelButton: 'btn btn-secondary'
                },
                buttonsStyling: false
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
                                customClass: {
                                    confirmButton: 'btn btn-success'
                                },
                                buttonsStyling: false
                            }).then(() => {
                                window.location.href = '{{ route("budget_movements.index") }}';
                            });
                        },
                        error: function(xhr) {
                            Swal.fire({
                                title: 'Error',
                                text: xhr.responseJSON?.message || 'Error al eliminar el movimiento',
                                icon: 'error',
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