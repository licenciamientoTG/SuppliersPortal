@extends('layouts.zircos')

@section('title', 'Validar Requisición - ' . $requisition->folio)

@section('page.title', 'Validar Requisición')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('requisitions.inbox.validation') }}">Bandeja de Validación</a></li>
    <li class="breadcrumb-item active">Validar {{ $requisition->folio }}</li>
@endsection

@section('content')
    <div class="container-fluid">
        {{-- Header --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold mb-0">
                <i class="ti ti-checklist me-2"></i>Validación Técnica de Requisición
                <span class="badge bg-{{ $requisition->status->badgeClass() }} ms-2">
                    {{ $requisition->folio }}
                </span>
            </h4>
        </div>

        {{-- Información General --}}
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label text-muted text-uppercase fs-11 fw-bold">Solicitante</label>
                <div class="d-flex align-items-center bg-light p-2 rounded border">
                    <span class="avatar avatar-xs rounded-circle bg-primary-subtle text-primary me-2">
                        <i class="ti ti-user"></i>
                    </span>
                    <span class="fw-medium text-dark">{{ $requisition->requester->name }}</span>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label text-muted text-uppercase fs-11 fw-bold">Fecha Requerida - @if ($requisition->required_date)
                        @php
                            $today = \Carbon\Carbon::today();
                            $requiredDate = $requisition->required_date->startOfDay();
                            $daysRemaining = $today->diffInDays($requiredDate, false);
                            
                            // Determinar clase y texto según los días
                            if ($daysRemaining < 0) {
                                $badgeClass = 'text-danger';
                                $daysText = 'Vencida hace ' . abs($daysRemaining) . ' día(s)';
                                $icon = 'ti-alert-circle';
                            } elseif ($daysRemaining == 0) {
                                $badgeClass = 'text-warning';
                                $daysText = 'Vence hoy';
                                $icon = 'ti-clock-hour-4';
                            } elseif ($daysRemaining <= 3) {
                                $badgeClass = 'text-warning';
                                $daysText = 'Faltan ' . $daysRemaining . ' día(s)';
                                $icon = 'ti-clock-hour-4';
                            } elseif ($daysRemaining <= 7) {
                                $badgeClass = 'text-info';
                                $daysText = 'Faltan ' . $daysRemaining . ' días';
                                $icon = 'ti-calendar-check';
                            } else {
                                $badgeClass = 'text-success';
                                $daysText = 'Faltan ' . $daysRemaining . ' días';
                                $icon = 'ti-calendar-check';
                            }
                        @endphp
                        <b class="{{ $badgeClass }} mt-1">
                            <i class="ti {{ $icon }}"></i> {{ $daysText }}
                        </b>
                    @endif</label>
                <div class="d-flex align-items-center bg-light p-2 rounded border">
                    <i class="ti ti-calendar-event me-2 text-warning"></i>
                    <div class="flex-grow-1">
                        <span class="fw-medium text-dark d-block">
                            {{ $requisition->required_date ? $requisition->required_date->format('d/m/Y') : 'No especificada' }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label text-muted text-uppercase fs-11 fw-bold">Centro de Costos</label>
                <div class="bg-light p-2 rounded border text-truncate">
                    <span>{{ $requisition->primaryCostCenterLabel() }}</span>
                </div>
            </div>
            @if ($requisition->receivingLocation)
            <div class="col-md-4">
                <label class="form-label text-muted text-uppercase fs-11 fw-bold">Punto de Entrega</label>
                <div class="d-flex align-items-center bg-light p-2 rounded border">
                    <i class="ti ti-map-pin me-2 text-primary"></i>
                    <span class="fw-medium text-dark text-truncate">
                        {{ $requisition->receivingLocation->code }} - {{ $requisition->receivingLocation->name }}
                    </span>
                </div>
            </div>
            @endif
        </div>

        {{-- Notas del Requisitor --}}
        <div class="alert alert-info border-0 bg-info-subtle text-info mb-4" role="alert">
            <div class="d-flex">
                <div class="me-3">
                    <i class="ti ti-message-2 fs-3"></i>
                </div>
                <div>
                    <h5 class="alert-heading fs-14 fw-bold">Notas del Requisitor:</h5>
                    <p class="mb-0 fs-13">{{ $requisition->description ?: 'Sin observaciones adicionales' }}</p>
                </div>
            </div>
        </div>

        <div class="card border-info mb-4">
            <div class="card-header bg-info-subtle border-0">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-info">
                        <i class="ti ti-mail-share me-2"></i>Historial de Retroalimentacion de Compras
                    </h5>
                    @if ($requisition->hasFeedback())
                        <span class="badge bg-info text-white">{{ $requisition->feedbacks->count() }} envio(s)</span>
                    @endif
                </div>
            </div>
            <div class="card-body">
                @forelse ($requisition->feedbacks as $feedback)
                    <div class="border rounded p-3 {{ $loop->last ? '' : 'mb-3' }}">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <div class="fw-bold text-dark">{{ $feedback->buyer?->name ?? 'Compras' }}</div>
                                <div class="small text-muted">{{ $feedback->sent_at?->format('d/m/Y H:i') }}</div>
                            </div>
                            @if ($loop->first)
                                <span class="badge bg-info-subtle text-info border border-info-subtle">Mas reciente</span>
                            @endif
                        </div>
                        <p class="mb-0 mt-3 text-muted">{{ $feedback->message }}</p>
                    </div>
                @empty
                    <div class="text-muted">
                        Aun no se ha enviado retroalimentacion para esta requisicion.
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Detalle de Partidas --}}
        <h6 class="text-uppercase text-muted mb-2 fs-12 fw-bold">Detalle de Partidas</h6>
        <div class="table-responsive border rounded mb-4">
            <table class="table table-sm table-nowrap table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th width="50">#</th>
                        <th>Producto / Servicio</th>
                        <th>Descripción</th>
                        <th class="text-center" width="100">Cant.</th>
                        <th class="text-center" width="100">Unidad</th>
                        <th width="200">Categoría de Gasto</th>
                        <th width="80">Notas</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requisition->items as $index => $item)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>
                                <strong>{{ $item->productService->code }}</strong><br>
                                <small class="text-muted">{{ $item->productService->short_name }}</small>
                            </td>
                            <td>
                                {{ Str::limit($item->description, 80) }}
                                @if (strlen($item->description) > 80)
                                    <a href="#" class="text-primary" data-bs-toggle="tooltip" 
                                    title="{{ $item->description }}">
                                        <i class="ti ti-info-circle"></i>
                                    </a>
                                @endif
                            </td>
                            <td class="text-center">{{ $item->quantity }}</td>
                            <td class="text-center">{{ $item->unit }}</td>
                            <td>
                                <span class="badge bg-info">
                                    {{ $item->expenseCategory->code }} - {{ $item->expenseCategory->name }}
                                </span>
                            </td>
                            <td class="text-center">
                                @if ($item->notes)
                                    <button type="button" class="btn btn-link btn-sm text-primary p-0" 
                                            onclick="toggleNotes({{ $index }})">
                                        <i class="ti ti-note"></i>
                                    </button>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                        @if ($item->notes)
                            <tr id="notes-row-{{ $index }}" class="notes-row d-none">
                                <td colspan="7" class="bg-light">
                                    <div class="d-flex align-items-start gap-2 py-2">
                                        <div class="flex-grow-1">
                                            <small class="text-muted d-block mb-1">
                                                <i class="ti ti-note"></i> Notas:
                                            </small>
                                            <div class="notes-content">{{ $item->notes }}</div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                onclick="copyNotes({{ $index }})">
                                            <i class="ti ti-copy"></i> Copiar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                No hay partidas en esta requisición
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <hr class="my-4 border-dashed">

        {{-- Formulario de Validación --}}
        <form id="formValidateRequisition" method="POST" action="{{ route('requisitions.validate', $requisition->id) }}">
            @csrf
            
            <div class="card bg-light border-start border-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">
                        <i class="ti ti-gavel me-2"></i> Validaciones del Departamento de Compras
                    </h5>
                    
                    <p class="text-muted mb-4">
                        <i class="ti ti-info-circle me-1"></i>
                        Antes de proceder con la cotización, verifica los siguientes aspectos:
                    </p>

                    <div class="row g-3">
                        {{-- Validación 1: Claridad de Especificaciones --}}
                        <div class="col">
                            <div class="card border">
                                <div class="card-body">
                                    <div class="form-check custom-checkbox">
                                        <input type="checkbox" class="form-check-input validation-checkbox" 
                                            id="check_specs_clear" name="specs_clear" required>
                                        <label class="form-check-label fw-bold text-dark" for="check_specs_clear">
                                            <i class="ti ti-file-check me-1 text-primary"></i>
                                            Claridad de Especificaciones Técnicas
                                        </label>
                                    </div>
                                    <div class="form-text mt-2 ms-4">
                                        Las observaciones y especificaciones agregadas por el usuario son lo suficientemente 
                                        claras para que un proveedor pueda cotizar sin errores o malentendidos.
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Validación 2: Factibilidad de Tiempos --}}
                        <div class="col">
                            <div class="card border">
                                <div class="card-body">
                                    <div class="form-check custom-checkbox">
                                        <input type="checkbox" class="form-check-input validation-checkbox" 
                                            id="check_time_feasible" name="time_feasible" required>
                                        <label class="form-check-label fw-bold text-dark" for="check_time_feasible">
                                            <i class="ti ti-clock-hour-4 me-1 text-warning"></i>
                                            Factibilidad de Tiempos de Entrega
                                        </label>
                                    </div>
                                    <div class="form-text mt-2 ms-4">
                                        De acuerdo con las condiciones actuales del mercado, es posible cumplir con la 
                                        fecha requerida ({{ $requisition->required_date ? $requisition->required_date->format('d/m/Y') : 'no especificada' }}). 
                                        Si el plazo es muy corto, se ha coordinado con el requisitor.
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Validación 3: Evaluación de Alternativas --}}
                        <div class="col">
                            <div class="card border">
                                <div class="card-body">
                                    <div class="form-check custom-checkbox">
                                        <input type="checkbox" class="form-check-input validation-checkbox" 
                                            id="check_alternatives" name="alternatives_evaluated" required>
                                        <label class="form-check-label fw-bold text-dark" for="check_alternatives">
                                            <i class="ti ti-arrows-exchange me-1 text-success"></i>
                                            Evaluación de Alternativas de Catálogo
                                        </label>
                                    </div>
                                    <div class="form-text mt-2 ms-4">
                                        Los productos seleccionados son la opción más eficiente en cuanto a costo-beneficio, o se han sugerido alternativas al requisitor cuando aplica.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Notas adicionales de validación --}}
                    <div class="mt-4 pt-3 border-top">
                        <label class="form-label fw-bold text-dark">
                            Observaciones de Compras (opcional)
                        </label>
                        <textarea class="form-control" name="purchasing_notes" rows="3" 
                                placeholder="Comentarios adicionales sobre productos alternativos sugeridos, ajustes de tiempo acordados con el requisitor, o cualquier observación relevante..."></textarea>
                        <div class="form-text">
                            <i class="ti ti-info-circle me-1"></i>
                            Estas notas quedarán registradas en el historial de la requisición.
                        </div>
                    </div>
                </div>
            </div>

            {{-- Botones de Acción --}}
            <div class="d-flex justify-content-between mt-4 mb-5">
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-info" onclick="confirmFeedback()">
                        <i class="ti ti-mail-share me-1"></i> Enviar Retroalimentacion
                    </button>
                    <button type="button" class="btn btn-danger" onclick="confirmReject()">
                        <i class="ti ti-arrow-back-up me-1"></i> Devolver al Usuario
                    </button>
                    <button type="button" class="btn btn-outline-warning" onclick="confirmCancelRequisition()">
                        <i class="ti ti-ban me-1"></i> Cancelar Requisición
                    </button>
                </div>

                <div class="d-flex gap-2">
                    <a href="{{ route('requisitions.inbox.validation') }}" class="btn btn-light">
                        <i class="ti ti-x me-1"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg" id="btnValidateRFQ" disabled>
                        <i class="ti ti-check-double me-1"></i> Validar y Continuar con Cotización
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('styles')
<style>
    .custom-checkbox .form-check-input:checked {
        background-color: #28a745;
        border-color: #28a745;
    }
    
    .border-dashed {
        border-style: dashed !important;
    }
    
    .avatar-xs {
        width: 2rem;
        height: 2rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.875rem;
    }
    
    .fs-11 {
        font-size: 0.688rem;
    }
    
    .fs-12 {
        font-size: 0.75rem;
    }
    
    .fs-13 {
        font-size: 0.813rem;
    }
    
    .fs-14 {
        font-size: 0.875rem;
    }

    /* Transiciones suaves */
    #btnValidateRFQ {
        transition: all 0.3s ease;
    }

    #btnValidateRFQ:disabled {
        cursor: not-allowed;
        opacity: 0.6;
    }

    .card {
        transition: all 0.3s ease;
    }

    .bg-success-subtle {
        background-color: rgba(40, 167, 69, 0.08) !important;
    }

    .border-2 {
        border-width: 2px !important;
    }

    /* Animación al marcar checkbox */
    @keyframes checkPulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }

    .animate-check {
        animation: checkPulse 0.3s ease;
    }

    /* Mejorar apariencia de las cards de validación */
    .validation-checkbox:checked ~ label {
        color: #28a745 !important;
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function() {
    'use strict';
    
    // Inicializar tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();

    // =====================================================
    // HABILITAR/DESHABILITAR BOTÓN SEGÚN VALIDACIONES
    // =====================================================
    function checkValidationRequirements() {
        const specsChecked = $('#check_specs_clear').is(':checked');
        const timeChecked = $('#check_time_feasible').is(':checked');
        const alternativesChecked = $('#check_alternatives').is(':checked');
        
        // El botón solo se habilita si TODAS las validaciones están OK
        const allValid = specsChecked && timeChecked && alternativesChecked;
        
        $('#btnValidateRFQ').prop('disabled', !allValid);
        
        // Cambiar apariencia del botón
        if (allValid) {
            $('#btnValidateRFQ')
                .removeClass('btn-secondary')
                .addClass('btn-primary')
                .html('<i class="ti ti-check-double me-1"></i> Validar y Continuar con Cotización');
        } else {
            $('#btnValidateRFQ')
                .removeClass('btn-primary')
                .addClass('btn-secondary')
                .html('<i class="ti ti-lock me-1"></i> Complete todas las validaciones');
        }

        // Actualizar contador de validaciones
        const checkedCount = $('.validation-checkbox:checked').length;
        const totalCount = $('.validation-checkbox').length;
        
        if (checkedCount > 0) {
            $('#btnValidateRFQ').attr('title', `${checkedCount} de ${totalCount} validaciones completadas`);
        }
    }

    // Escuchar cambios en los checkboxes
    $('.validation-checkbox').on('change', function() {
        const $card = $(this).closest('.card');
        
        checkValidationRequirements();
        
        // Efecto visual al marcar/desmarcar
        if ($(this).is(':checked')) {
            $card.addClass('border-success border-2 bg-success-subtle')
                 .removeClass('border');
            
            // Animar el check
            $(this).closest('.form-check').addClass('animate-check');
            setTimeout(() => {
                $(this).closest('.form-check').removeClass('animate-check');
            }, 300);
        } else {
            $card.removeClass('border-success border-2 bg-success-subtle')
                 .addClass('border');
        }
    });

    // Verificar estado inicial
    checkValidationRequirements();
});

/**
 * Confirmar rechazo/devolución de requisición
 */
function confirmCancelRequisition() {
    Swal.fire({
        title: '¿Cancelar requisición {{ $requisition->folio }}?',
        html: `
            <div class="text-start">
                <p class="mb-3"><strong>La requisición se conservará como expediente.</strong></p>
                <ul class="text-muted small">
                    <li class="mb-2"><i class="ti ti-ban text-warning"></i> Cambiará a estado <span class="badge bg-dark">CANCELADA</span></li>
                    <li class="mb-2"><i class="ti ti-archive text-info"></i> No se borrarán registros, grupos ni RFQs ligadas</li>
                </ul>
                <div class="mt-3">
                    <label for="cancel_reason" class="form-label fw-bold">Motivo de cancelación <span class="text-danger">*</span></label>
                    <textarea id="cancel_reason" class="form-control" rows="4" placeholder="Explica por qué Compras cancela la requisición completa..." maxlength="1000" required></textarea>
                </div>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '<i class="ti ti-ban me-1"></i> Sí, cancelar',
        cancelButtonText: '<i class="ti ti-x me-1"></i> Volver',
        confirmButtonColor: '#f59e0b',
        cancelButtonColor: '#6c757d',
        reverseButtons: true,
        customClass: {
            confirmButton: 'btn btn-warning',
            cancelButton: 'btn btn-outline-secondary'
        },
        buttonsStyling: false,
        preConfirm: () => {
            const reason = document.getElementById('cancel_reason').value.trim();

            if (!reason) {
                Swal.showValidationMessage('Debes proporcionar un motivo de cancelación');
                return false;
            }

            if (reason.length < 10) {
                Swal.showValidationMessage('El motivo debe tener al menos 10 caracteres');
                return false;
            }

            return reason;
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            const form = $('<form>', {
                method: 'POST',
                action: '{{ route('requisitions.workflow.cancel', $requisition->id) }}'
            });

            form.append($('<input>', {
                type: 'hidden',
                name: '_token',
                value: '{{ csrf_token() }}'
            }));

            form.append($('<input>', {
                type: 'hidden',
                name: 'reason',
                value: result.value
            }));

            $('body').append(form);
            form.submit();
        }
    });
}

function confirmFeedback() {
    Swal.fire({
        title: 'Enviar retroalimentacion a {{ $requisition->folio }}',
        html: `
            <div class="text-start">
                <p class="mb-3">
                    El correo se enviara al requisitor y llevara copia al comprador que registra el mensaje.
                </p>
                <div>
                    <label for="feedback_message" class="form-label fw-bold">
                        Mensaje de retroalimentacion <span class="text-danger">*</span>
                    </label>
                    <textarea
                        id="feedback_message"
                        class="form-control"
                        rows="5"
                        maxlength="2000"
                        placeholder="Describe los ajustes, aclaraciones o informacion adicional que necesita el requisitor..."
                        required></textarea>
                    <small class="form-text text-muted">Minimo 10 caracteres - Maximo 2000 caracteres</small>
                </div>
            </div>
        `,
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: '<i class="ti ti-send me-1"></i> Enviar',
        cancelButtonText: '<i class="ti ti-x me-1"></i> Cancelar',
        confirmButtonColor: '#0ea5e9',
        cancelButtonColor: '#6c757d',
        width: '680px',
        reverseButtons: true,
        customClass: {
            confirmButton: 'btn btn-info',
            cancelButton: 'btn btn-outline-secondary'
        },
        buttonsStyling: false,
        preConfirm: () => {
            const message = document.getElementById('feedback_message').value.trim();

            if (!message) {
                Swal.showValidationMessage('Debes capturar un mensaje de retroalimentacion');
                return false;
            }

            if (message.length < 10) {
                Swal.showValidationMessage('El mensaje debe tener al menos 10 caracteres');
                return false;
            }

            return message;
        }
    }).then((result) => {
        if (!result.isConfirmed || !result.value) {
            return;
        }

        $.ajax({
            url: '{{ route('requisitions.feedback', $requisition->id) }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                message: result.value
            }
        }).done(function (response) {
            Swal.fire({
                icon: 'success',
                title: 'Retroalimentacion enviada',
                text: response.message,
                timer: 1800,
                showConfirmButton: false
            }).then(() => window.location.reload());
        }).fail(function (xhr) {
            const message = xhr.responseJSON?.message || 'No se pudo enviar la retroalimentacion.';

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: message
            });
        });
    });
}

function confirmReject() {
    Swal.fire({
        title: '¿Devolver requisición {{ $requisition->folio }}?',
        html: `
            <div class="text-start">
                <p class="mb-3"><strong>Al devolver esta requisición al usuario:</strong></p>
                <ul class="text-muted small">
                    <li class="mb-2">
                        <i class="ti ti-ban text-danger"></i> 
                        Cambiará a estado <span class="badge bg-danger">RECHAZADA</span>
                    </li>
                    <li class="mb-2">
                        <i class="ti ti-bell text-primary"></i> 
                        El solicitante recibirá notificación del rechazo
                    </li>
                    <li class="mb-2">
                        <i class="ti ti-message-2 text-info"></i> 
                        Podrá corregir y volver a enviar
                    </li>
                </ul>
                
                <div class="mt-3">
                    <label for="rejection_reason" class="form-label fw-bold">
                        Motivo de devolución <span class="text-danger">*</span>
                    </label>
                    <textarea 
                        id="rejection_reason" 
                        class="form-control" 
                        rows="4" 
                        placeholder="Explica claramente qué debe corregir el usuario (especificaciones incompletas, tiempo no factible, etc.)..."
                        maxlength="1000"
                        required></textarea>
                    <small class="form-text text-muted">Mínimo 20 caracteres - Máximo 1000 caracteres</small>
                </div>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '<i class="ti ti-arrow-back-up me-1"></i> Sí, Devolver',
        cancelButtonText: '<i class="ti ti-x me-1"></i> Cancelar',
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        width: '650px',
        reverseButtons: true,
        customClass: {
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-outline-secondary'
        },
        buttonsStyling: false,
        preConfirm: () => {
            const reason = document.getElementById('rejection_reason').value.trim();
            
            if (!reason) {
                Swal.showValidationMessage('Debes proporcionar un motivo de devolución');
                return false;
            }
            
            if (reason.length < 20) {
                Swal.showValidationMessage('El motivo debe tener al menos 20 caracteres para ser claro');
                return false;
            }
            
            return reason;
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            // Crear y enviar formulario
            const form = $('<form>', {
                method: 'POST',
                action: '{{ route('requisitions.workflow.reject', $requisition->id) }}'
            });
            
            form.append($('<input>', {
                type: 'hidden',
                name: '_token',
                value: '{{ csrf_token() }}'
            }));
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'rejection_reason',
                value: result.value
            }));
            
            $('body').append(form);
            form.submit();
        }
    });
}

/**
 * Validar y confirmar antes de continuar
 */
$('#formValidateRequisition').on('submit', function(e) {
    e.preventDefault();
    
    // Confirmar validación
    Swal.fire({
        title: '¿Continuar con cotización?',
        html: `
            <div class="text-start">
                <p class="mb-3">
                    <i class="ti ti-check-circle text-success fs-3"></i>
                    Has validado correctamente la requisición <strong>{{ $requisition->folio }}</strong>
                </p>
                <p class="mb-3"><strong>Al continuar:</strong></p>
                <ul class="text-muted small">
                    <li class="mb-2">
                        <i class="ti ti-file-dollar text-primary"></i> 
                        Se creará el proceso de cotización (RFQ)
                    </li>
                    <li class="mb-2">
                        <i class="ti ti-users text-info"></i> 
                        Podrás seleccionar proveedores para cotizar
                    </li>
                    <li class="mb-2">
                        <i class="ti ti-clock text-warning"></i> 
                        Iniciará el flujo formal de solicitud de cotizaciones
                    </li>
                </ul>
            </div>
        `,
        icon: 'success',
        showCancelButton: true,
        confirmButtonText: '<i class="ti ti-arrow-right me-1"></i> Sí, Continuar',
        cancelButtonText: '<i class="ti ti-x me-1"></i> Cancelar',
        confirmButtonColor: '#0d6efd',
        cancelButtonColor: '#6c757d',
        reverseButtons: true,
        customClass: {
            confirmButton: 'btn btn-primary',
            cancelButton: 'btn btn-outline-secondary'
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            this.submit();
        }
    });
});

function toggleNotes(index) {
    const notesRow = document.getElementById(`notes-row-${index}`);
    notesRow.classList.toggle('d-none');
}

function copyNotes(index) {
    const notesRow = document.getElementById(`notes-row-${index}`);
    const notesText = notesRow.querySelector('.notes-content').textContent;
    
    // Intentar con la API moderna
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(notesText).then(() => {
            showCopyFeedback(event.currentTarget);
        }).catch(err => {
            // Si falla, usar el método alternativo
            fallbackCopy(notesText, event.currentTarget);
        });
    } else {
        // Método alternativo para navegadores antiguos o HTTP
        fallbackCopy(notesText, event.currentTarget);
    }
}

function fallbackCopy(text, button) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    
    try {
        document.execCommand('copy');
        showCopyFeedback(button);
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Error al copiar', text: 'No se pudo copiar. Por favor, copia manualmente.' });
    }
    
    document.body.removeChild(textarea);
}

function showCopyFeedback(btn) {
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="ti ti-check"></i> Copiado';
    btn.classList.remove('btn-outline-primary');
    btn.classList.add('btn-success');
    
    setTimeout(() => {
        btn.innerHTML = originalHTML;
        btn.classList.remove('btn-success');
        btn.classList.add('btn-outline-primary');
    }, 2000);
}
</script>
@endpush
