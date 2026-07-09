@extends('layouts.zircos')

@section('title', 'Aprobar Presupuesto Anual')

@section('page.title', 'Aprobar Presupuesto Anual')

@section('page.breadcrumbs')
<li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
<li class="breadcrumb-item"><a href="{{ route('annual_budgets.index') }}">Presupuestos Anuales</a></li>
<li class="breadcrumb-item active">Aprobar Presupuesto</li>
@endsection

@section('content')
{{-- Header con descripción --}}
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <p class="text-muted mb-0">
            <i class="ti ti-shield-check me-2"></i>
            Revise la información y confirme la aprobación del presupuesto
        </p>
    </div>
    <div>
        <a href="{{ route('annual_budgets.index') }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i> Volver
        </a>
    </div>
</div>

<form action="{{ route('annual_budgets.approve.store', $annual_budget) }}" method="POST" id="approveForm">
    @csrf

    <div class="row">
        {{-- Columna Principal: Información del Presupuesto --}}
        <div class="col-lg-8">
            {{-- Datos Generales --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="ti ti-info-circle me-2"></i>
                        Información del Presupuesto
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small">Empresa</label>
                            <p class="mb-0">
                                <strong>{{ $annual_budget->costCenter?->company?->name ?? '—' }}</strong>
                            </p>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small">Centro de Costo</label>
                            <p class="mb-0">
                                <strong>
                                    @if($annual_budget->costCenter)
                                    [{{ $annual_budget->costCenter->code }}] {{ $annual_budget->costCenter->name }}
                                    @else
                                    —
                                    @endif
                                </strong>
                            </p>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small">Ejercicio Fiscal</label>
                            <p class="mb-0">
                                <span class="badge bg-info fs-6">{{ $annual_budget->fiscal_year }}</span>
                            </p>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small">Monto Total Anual</label>
                            <p class="mb-0">
                                <span class="badge bg-success fs-5">
                                    ${{ number_format($annual_budget->total_annual_amount, 2) }}
                                </span>
                            </p>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small">Estado Actual</label>
                            <p class="mb-0">
                                <span class="badge bg-warning">En Planificación</span>
                            </p>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small">Creado Por</label>
                            <p class="mb-0">{{ $annual_budget->createdBy?->name ?? '—' }}</p>
                        </div>

                        @if($annual_budget->notes)
                        <div class="col-12">
                            <label class="form-label text-muted small">Notas</label>
                            <div class="p-3 bg-light rounded">
                                {{ $annual_budget->notes }}
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Resumen de Distribuciones --}}
            @php
            $distributions = $annual_budget->monthlyDistributions;
            $hasDistributions = $distributions->isNotEmpty();

            if ($hasDistributions) {
            $totalAssigned = $distributions->sum('assigned_amount');
            $categoriesCount = $distributions->groupBy('expense_category_id')->count();
            $monthsWithBudget = $distributions->where('assigned_amount', '>', 0)->groupBy('month')->count();
            $difference = abs($totalAssigned - $annual_budget->total_annual_amount);
            }
            @endphp

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="ti ti-calendar-stats me-2"></i>
                        Distribuciones Mensuales
                    </h5>
                </div>
                <div class="card-body">
                    @if($hasDistributions)
                    <div class="row text-center">
                        <div class="col-md-4 mb-3">
                            <div class="p-3 bg-light rounded">
                                <div class="text-muted small mb-1">Cuentas Configuradas</div>
                                <div class="h4 mb-0 text-primary">{{ $categoriesCount }}</div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="p-3 bg-light rounded">
                                <div class="text-muted small mb-1">Meses con Presupuesto</div>
                                <div class="h4 mb-0 text-info">{{ $monthsWithBudget }}/12</div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="p-3 bg-light rounded">
                                <div class="text-muted small mb-1">Total Distribuido</div>
                                <div class="h4 mb-0 text-success">${{ number_format($totalAssigned, 2) }}</div>
                            </div>
                        </div>
                    </div>

                    {{-- Validación: Total Distribuido vs Total Anual --}}
                    @if($difference > 0.01)
                    <div class="alert alert-warning mt-3">
                        <div class="d-flex align-items-center">
                            <i class="ti ti-alert-triangle me-3 fs-2"></i>
                            <div>
                                <strong>Advertencia:</strong> El total distribuido
                                (${{ number_format($totalAssigned, 2) }})
                                no coincide con el monto total anual
                                (${{ number_format($annual_budget->total_annual_amount, 2) }}).
                                <br>
                                <strong>Diferencia:</strong> ${{ number_format($difference, 2) }}
                            </div>
                        </div>
                    </div>
                    @else
                    <div class="alert alert-success mt-3">
                        <div class="d-flex align-items-center">
                            <i class="ti ti-check me-3 fs-2"></i>
                            <div>
                                <strong>Validación correcta:</strong> El total distribuido coincide con el monto anual.
                            </div>
                        </div>
                    </div>
                    @endif

                    <div class="text-center mt-3">
                        <a href="{{ route('budget_monthly_distributions.edit', $annual_budget) }}"
                            class="btn btn-outline-primary btn-sm">
                            <i class="ti ti-calendar-stats me-1"></i>
                            Ver Distribuciones Detalladas
                        </a>
                    </div>
                    @else
                    <div class="alert alert-danger">
                        <div class="d-flex align-items-start">
                            <i class="ti ti-alert-circle me-3 fs-2"></i>
                            <div>
                                <strong>¡Atención!</strong> Este presupuesto no tiene distribuciones mensuales configuradas.
                                <br><br>
                                <strong>No se puede aprobar un presupuesto sin distribuciones.</strong>
                                <br><br>
                                <a href="{{ route('budget_monthly_distributions.create', $annual_budget) }}"
                                    class="btn btn-warning btn-sm mt-2">
                                    <i class="ti ti-calendar-plus me-1"></i>
                                    Crear Distribuciones Mensuales
                                </a>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Columna Lateral: Panel de Aprobación --}}
        <div class="col-lg-4">
            {{-- Información de Aprobación --}}
            <div class="card mb-4 border-success">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="ti ti-shield-check me-2"></i>
                        Proceso de Aprobación
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="ti ti-user me-1"></i>
                            Será Aprobado Por
                        </label>
                        <p class="mb-0">
                            <strong>{{ Auth::user()->name }}</strong>
                            <br>
                            <small class="text-muted">{{ Auth::user()->email }}</small>
                        </p>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="ti ti-calendar-event me-1"></i>
                            Fecha de Aprobación
                        </label>
                        <p class="mb-0">{{ now()->format('d/m/Y H:i') }}</p>
                    </div>

                    <hr>

                    <div class="alert alert-info mb-0">
                        <h6 class="alert-heading">
                            <i class="ti ti-info-circle me-2"></i> ¿Qué sucede al aprobar?
                        </h6>
                        <ul class="mb-0 small">
                            <li>El presupuesto cambia a estado <strong>"Aprobado"</strong></li>
                            <li>Se registra fecha y usuario de aprobación</li>
                            <li>Estará disponible para requisiciones</li>
                            <li>No podrá modificarse después</li>
                        </ul>
                        <div class="mt-2 pt-2 border-top">
                            <small class="text-muted">
                                <i class="ti ti-shield-lock me-1"></i>
                                <strong>Nota:</strong> Solo usuarios con rol de Dirección General podrán realizar modificaciones posteriores.
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Lista de Verificación --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="ti ti-checklist me-2"></i>
                        Lista de Verificación
                    </h5>
                </div>
                <div class="card-body">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="check1" {{ $hasDistributions ? 'checked disabled' : 'disabled' }}>
                        <label class="form-check-label" for="check1">
                            Tiene distribuciones mensuales
                        </label>
                    </div>

                    @if($hasDistributions)
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="check2" {{ $difference <= 0.01 ? 'checked disabled' : 'disabled' }}>
                        <label class="form-check-label" for="check2">
                            Total distribuido coincide con monto anual
                        </label>
                    </div>

                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="check3" required>
                        <label class="form-check-label" for="check3">
                            He revisado la información <span class="text-danger">*</span>
                        </label>
                    </div>

                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="check4" required>
                        <label class="form-check-label" for="check4">
                            Confirmo la aprobación <span class="text-danger">*</span>
                        </label>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Botones de Acción --}}
            <div class="card">
                <div class="card-body">
                    @if($hasDistributions && $difference <= 0.01)
                        <button type="submit" class="btn btn-success w-100 mb-2" id="btnApprove" disabled>
                        <i class="ti ti-check me-1"></i>
                        Aprobar Presupuesto
                        </button>
                        @else
                        <button type="button" class="btn btn-success w-100 mb-2" disabled>
                            <i class="ti ti-lock me-1"></i>
                            Completar Requisitos para Aprobar
                        </button>
                        @endif

                        <a href="{{ route('annual_budgets.index') }}" class="btn btn-outline-secondary w-100">
                            <i class="ti ti-x me-1"></i>
                            Cancelar
                        </a>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('approveForm');
        const btnApprove = document.getElementById('btnApprove');
        const check3 = document.getElementById('check3');
        const check4 = document.getElementById('check4');

        // Validar checkboxes antes de habilitar botón
        function validateCheckboxes() {
            if (btnApprove) {
                const allChecked = check3?.checked && check4?.checked;
                btnApprove.disabled = !allChecked;
            }
        }

        // Eventos en checkboxes
        if (check3) check3.addEventListener('change', validateCheckboxes);
        if (check4) check4.addEventListener('change', validateCheckboxes);

        // Validación inicial
        validateCheckboxes();

        // Confirmación antes de enviar
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                Swal.fire({
                    title: '¿Aprobar Presupuesto?',
                    html: `
                    <div class="text-start">
                        <p>Está a punto de aprobar el presupuesto anual para:</p>
                        <ul>
                            <li><strong>Año Fiscal:</strong> {{ $annual_budget->fiscal_year }}</li>
                            <li><strong>Centro de Costo:</strong> {{ $annual_budget->costCenter?->name }}</li>
                            <li><strong>Monto:</strong> ${{ number_format($annual_budget->total_annual_amount, 2) }}</li>
                        </ul>
                        <div class="alert alert-warning mt-3 mb-0">
                            <i class="ti ti-alert-triangle me-2"></i>
                            <strong>Esta acción no se puede deshacer.</strong>
                        </div>
                    </div>
                `,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: '<i class="ti ti-check me-1"></i>Sí, aprobar',
                    cancelButtonText: '<i class="ti ti-x me-1"></i>Cancelar',
                    customClass: {
                        confirmButton: 'btn btn-success',
                        cancelButton: 'btn btn-secondary'
                    },
                    buttonsStyling: false,
                    reverseButtons: true,
                    width: '600px'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Deshabilitar botón y mostrar loading
                        btnApprove.disabled = true;
                        btnApprove.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Aprobando...';

                        // Enviar formulario
                        form.submit();
                    }
                });
            });
        }
    });
</script>
@endpush