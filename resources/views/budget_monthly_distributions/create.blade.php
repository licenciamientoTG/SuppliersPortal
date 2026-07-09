@extends('layouts.zircos')

@section('title', 'Crear Distribuciones Mensuales')

@section('page.title', 'Crear Distribuciones Mensuales')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('annual_budgets.index') }}">Presupuestos Anuales</a></li>
    <li class="breadcrumb-item"><a href="{{ route('annual_budgets.show', $annualBudget->id) }}">Presupuesto {{ $annualBudget->fiscal_year }}</a></li>
    <li class="breadcrumb-item active">Crear Distribuciones</li>
@endsection

@section('content')

{{-- Header --}}
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <p class="text-muted mb-0">
            Configure cómo se distribuirá el presupuesto anual a lo largo de los 12 meses para cada categoría de gasto.
        </p>
    </div>
    <div>
        <a href="{{ route('annual_budgets.show', $annualBudget->id) }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i> Cancelar
        </a>
    </div>
</div>

{{-- Alertas --}}
@if (session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="ti ti-check-circle me-2"></i>
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
</div>
@endif

@if (session('error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="ti ti-alert-circle me-2"></i>
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
</div>
@endif

@if ($errors->any())
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="ti ti-alert-circle me-2"></i>
    <strong>Error de validación:</strong>
    <ul class="mb-0 mt-2">
        @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- Formulario --}}
<form action="{{ route('budget_monthly_distributions.store') }}" method="POST" id="formDistributions" novalidate>
    @csrf

    <input type="hidden" name="annual_budget_id" value="{{ $annualBudget->id }}">

    {{-- Incluir el formulario parcial --}}
    @include('budget_monthly_distributions.partials.form', ['isEdit' => false,])

    {{-- Botones de Acción --}}
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div class="text-muted">
                    <i class="ti ti-info-circle me-1"></i>
                    Las distribuciones se guardarán para el presupuesto anual {{ $annualBudget->fiscal_year }}.
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('annual_budgets.show', $annualBudget->id) }}" class="btn btn-light">
                        <i class="ti ti-x me-1"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary" id="submitButton">
                        <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true" id="submitSpinner"></span>
                        <i class="ti ti-device-floppy me-1" id="submitIcon"></i>
                        <span id="submitText">Guardar Distribuciones</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

{{-- ========================================
     MODAL FUERA DEL FORMULARIO PRINCIPAL
     ======================================== --}}
<!-- Modal para agregar nueva categoría -->
<div class="modal fade" id="newCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nueva Cuenta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="newCategoryForm">
                @csrf
                <div class="modal-body">
                    <div id="categoryFormErrors" class="alert alert-danger d-none"></div>

                    <div class="mb-3">
                        <label for="newCategoryCode" class="form-label">Código <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="newCategoryCode" name="code" required
                            maxlength="3" placeholder="Ej: MAT, SER, VIA" style="text-transform:uppercase">
                        <small class="text-muted">Máximo 3 letras mayúsculas</small>
                    </div>

                    <div class="mb-3">
                        <label for="newCategoryName" class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="newCategoryName" name="name" required>
                    </div>

                    <div class="mb-3">
                        <label for="newCategoryDescription" class="form-label">Descripción</label>
                        <textarea class="form-control" id="newCategoryDescription" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy me-1"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('formDistributions');
        const submitButton = document.getElementById('submitButton');
        const submitSpinner = document.getElementById('submitSpinner');
        const submitIcon = document.getElementById('submitIcon');
        const submitText = document.getElementById('submitText');
        let formChanged = false;

        // Detectar cambios en los campos del formulario
        document.querySelectorAll('.distribution-input, input, select, textarea').forEach(input => {
            input.addEventListener('input', function() {
                formChanged = true;
            });

            // Para checkboxes y radios
            input.addEventListener('change', function() {
                formChanged = true;
            });
        });

        // Confirmar antes de abandonar la página con cambios sin guardar
        window.addEventListener('beforeunload', function(e) {
            if (formChanged) {
                e.preventDefault();
                // Para la mayoría de los navegadores
                e.returnValue = 'Tienes cambios sin guardar. ¿Estás seguro de que deseas salir?';
                // Para navegadores más antiguos
                return 'Tienes cambios sin guardar. ¿Estás seguro de que deseas salir?';
            }
        });

        // Manejar el envío del formulario
        form.addEventListener('submit', function(e) {
            // Deshabilitar el botón de envío
            submitButton.disabled = true;
            submitSpinner.classList.remove('d-none');
            submitIcon.classList.add('d-none');
            submitText.textContent = 'Guardando...';

            // Marcar el formulario como no cambiado para evitar la alerta de navegación
            formChanged = false;

            // Validación del lado del cliente
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();

                // Mostrar mensajes de validación
                form.classList.add('was-validated');

                // Restaurar el botón
                submitButton.disabled = false;
                submitSpinner.classList.add('d-none');
                submitIcon.classList.remove('d-none');
                submitText.textContent = 'Guardar Distribuciones';

                // Desplazarse al primer campo inválido
                const firstInvalid = form.querySelector(':invalid');
                if (firstInvalid) {
                    firstInvalid.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                    firstInvalid.focus();
                }
            }
        });

        // Validar campos al perder el foco
        form.querySelectorAll('input, select, textarea').forEach(input => {
            input.addEventListener('blur', function() {
                this.reportValidity();
            });
        });
    });
</script>
@endpush