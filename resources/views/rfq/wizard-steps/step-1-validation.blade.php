{{-- InformaciÃ³n General --}}
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
        <label class="form-label text-muted text-uppercase fs-11 fw-bold">Fecha Requerida</label>
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
            @php
                $primaryCostCenter = $requisition->primaryCostCenter();
            @endphp
            <span>{{ $primaryCostCenter?->code ?? 'N/A' }} - {{ $primaryCostCenter?->name ?? 'Sin centro de costo' }}</span>
        </div>
    </div>
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

{{-- Detalle de Partidas --}}
<h6 class="text-uppercase text-muted mb-2 fs-12 fw-bold">Detalle de Partidas</h6>
<div class="table-responsive border rounded mb-4">
    <table class="table table-sm table-nowrap table-hover mb-0 align-middle">
        <thead class="table-light">
            <tr>
                <th width="50">#</th>
                <th>Producto / Servicio</th>
                <th class="text-center" width="100">Cant.</th>
                <th class="text-center" width="100">Unidad</th>
                <th width="200">CategorÃ­a de Gasto</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($requisition->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>
                        <strong>{{ $item->productService?->short_name ?? $item->description ?? 'Producto sin nombre' }}</strong>
                        @if($item->productService?->code)
                            <br>
                            <small class="text-muted">{{ $item->productService->code }}</small>
                        @endif
                        @if($item->productService?->brand || $item->productService?->model)
                            <br>
                            <small class="text-muted">
                                {{ collect([$item->productService->brand, $item->productService->model])->filter()->join(' / ') }}
                            </small>
                        @endif
                    </td>
                    <td class="text-center">{{ $item->quantity }}</td>
                    <td class="text-center">{{ $item->unit }}</td>
                    <td>
                        <span class="badge bg-info">
                            {{ $item->expenseCategory->code }} - {{ $item->expenseCategory->name }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">
                        No hay partidas en esta requisiciÃ³n
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<hr class="my-4 border-dashed">

{{-- Formulario de ValidaciÃ³n --}}
<div class="card bg-light border-start border-4">
    <div class="card-body">
        <h5 class="card-title mb-3">
            <i class="ti ti-gavel me-2"></i> Validaciones del Departamento de Compras
        </h5>

        <p class="text-muted mb-4">
            <i class="ti ti-info-circle me-1"></i>
            Antes de proceder con la cotizaciÃ³n, verifica los siguientes aspectos:
        </p>

        <div class="row g-3">
            {{-- ValidaciÃ³n 1: Claridad de Especificaciones --}}
            <div class="col">
                <div class="card validation-card {{ ($validationData['specs_clear'] ?? false) ? 'border-success border-2 bg-success-subtle' : 'border' }}">
                    <div class="card-body">
                        <div class="form-check custom-checkbox">
                            <input type="checkbox"
                                class="form-check-input validation-checkbox"
                                wire:model.live="validationData.specs_clear"
                                id="check_specs_clear">
                            <label class="form-check-label fw-bold {{ ($validationData['specs_clear'] ?? false) ? 'text-success' : 'text-dark' }}"
                                for="check_specs_clear">
                                <i class="ti ti-file-check me-1 text-primary"></i>
                                Claridad de Especificaciones TÃ©cnicas
                            </label>
                        </div>
                        <div class="form-text mt-2 ms-4">
                            Las observaciones y especificaciones agregadas por el usuario son lo suficientemente
                            claras para que un proveedor pueda cotizar sin errores o malentendidos.
                        </div>
                    </div>
                </div>
            </div>

            {{-- ValidaciÃ³n 2: Factibilidad de Tiempos --}}
            <div class="col">
                <div class="card validation-card {{ ($validationData['time_feasible'] ?? false) ? 'border-success border-2 bg-success-subtle' : 'border' }}">
                    <div class="card-body">
                        <div class="form-check custom-checkbox">
                            <input type="checkbox"
                                class="form-check-input validation-checkbox"
                                wire:model.live="validationData.time_feasible"
                                id="check_time_feasible">
                            <label class="form-check-label fw-bold {{ ($validationData['time_feasible'] ?? false) ? 'text-success' : 'text-dark' }}"
                                for="check_time_feasible">
                                <i class="ti ti-clock-hour-4 me-1 text-warning"></i>
                                Factibilidad de Tiempos de Entrega
                            </label>
                        </div>
                        <div class="form-text mt-2 ms-4">
                            De acuerdo con las condiciones actuales del mercado, es posible cumplir con la
                            fecha requerida ({{ $requisition->required_date ? $requisition->required_date->format('d/m/Y') : 'no especificada' }}).
                        </div>
                    </div>
                </div>
            </div>

            {{-- ValidaciÃ³n 3: EvaluaciÃ³n de Alternativas --}}
            <div class="col">
                <div class="card validation-card {{ ($validationData['alternatives_evaluated'] ?? false) ? 'border-success border-2 bg-success-subtle' : 'border' }}">
                    <div class="card-body">
                        <div class="form-check custom-checkbox">
                            <input type="checkbox"
                                class="form-check-input validation-checkbox"
                                wire:model.live="validationData.alternatives_evaluated"
                                id="check_alternatives">
                            <label class="form-check-label fw-bold {{ ($validationData['alternatives_evaluated'] ?? false) ? 'text-success' : 'text-dark' }}"
                                for="check_alternatives">
                                <i class="ti ti-arrows-exchange me-1 text-success"></i>
                                EvaluaciÃ³n de Alternativas de CatÃ¡logo
                            </label>
                        </div>
                        <div class="form-text mt-2 ms-4">
                            Los productos seleccionados son la opciÃ³n mÃ¡s eficiente en cuanto a costo-beneficio.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Notas adicionales de validaciÃ³n --}}
        <div class="mt-4 pt-3 border-top">
            <label class="form-label fw-bold text-dark">
                Observaciones de Compras (opcional)
            </label>
            <textarea class="form-control"
                      wire:model="validationData.purchasing_notes"
                      rows="3"
                      placeholder="Comentarios adicionales sobre productos alternativos sugeridos..."></textarea>
        </div>
    </div>
</div>

@push('styles')
<style>
    .fs-11 { font-size: 0.688rem; }
    .fs-12 { font-size: 0.75rem; }
    .fs-13 { font-size: 0.813rem; }
    .fs-14 { font-size: 0.875rem; }
    .border-dashed { border-style: dashed !important; }
    .avatar-xs {
        width: 2rem;
        height: 2rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.875rem;
    }

    /* Estilos para animaciÃ³n de validaciÃ³n */
    .validation-card {
        transition: all 0.3s ease;
    }

    .validation-card.validated {
        border-color: #198754 !important;
        border-width: 2px !important;
        background-color: rgba(25, 135, 84, 0.08) !important;
    }

    .validation-checkbox:checked ~ label {
        color: #198754 !important;
    }

    .custom-checkbox .form-check-input:checked {
        background-color: #28a745;
        border-color: #28a745;
    }

    @keyframes checkPulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }

    .animate-check {
        animation: checkPulse 0.3s ease;
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function() {
    'use strict';
});

/**
 * Confirmar rechazo/devoluciÃ³n de requisiciÃ³n
 */
function confirmReject() {
    Swal.fire({
        title: 'Â¿Devolver requisiciÃ³n {{ $requisition->folio }}?',
        html: `
            <div class="text-start">
                <p class="mb-3"><strong>Al devolver esta requisiciÃ³n al usuario:</strong></p>
                <ul class="text-muted small">
                    <li class="mb-2">
                        <i class="ti ti-ban text-danger"></i>
                        CambiarÃ¡ a estado <span class="badge bg-danger">RECHAZADA</span>
                    </li>
                    <li class="mb-2">
                        <i class="ti ti-bell text-primary"></i>
                        El solicitante recibirÃ¡ notificaciÃ³n del rechazo
                    </li>
                    <li class="mb-2">
                        <i class="ti ti-message-2 text-info"></i>
                        PodrÃ¡ corregir y volver a enviar
                    </li>
                </ul>

                <div class="mt-3">
                    <label for="rejection_reason" class="form-label fw-bold">
                        Motivo de devoluciÃ³n <span class="text-danger">*</span>
                    </label>
                    <textarea
                        id="rejection_reason"
                        class="form-control"
                        rows="4"
                        placeholder="Explica claramente quÃ© debe corregir el usuario (especificaciones incompletas, tiempo no factible, etc.)..."
                        maxlength="1000"
                        required></textarea>
                    <small class="form-text text-muted">MÃ­nimo 20 caracteres - MÃ¡ximo 1000 caracteres</small>
                </div>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '<i class="ti ti-arrow-back-up me-1"></i> SÃ­, Devolver',
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
                Swal.showValidationMessage('Debes proporcionar un motivo de devoluciÃ³n');
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
            // Llamar al mÃ©todo Livewire para rechazar
            @this.rejectRequisition(result.value);
        }
    });
}
</script>
@endpush
