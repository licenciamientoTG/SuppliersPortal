@extends('layouts.zircos')

@section('title', 'Editar Distribuciones Mensuales')

@section('page.title', 'Editar Distribuciones Mensuales')

@section('page.breadcrumbs')
<li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
<li class="breadcrumb-item"><a href="{{ route('annual_budgets.index') }}">Presupuestos Anuales</a></li>
<li class="breadcrumb-item"><a href="{{ route('annual_budgets.show', $annualBudget->id) }}">Presupuesto {{ $annualBudget->fiscal_year }}</a></li>
<li class="breadcrumb-item active">Editar Distribuciones</li>
@endsection

@section('content')

{{-- Header --}}
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <p class="text-muted mb-0">
            Modifique la distribución del presupuesto anual a lo largo de los 12 meses para cada categoría de gasto.
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
    <i class="ti ti-check me-2"></i>
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

@if (session('error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="ti ti-alert-circle me-2"></i>
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
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

{{-- Alerta Informativa sobre Meses Bloqueados --}}
<div class="alert alert-info alert-dismissible fade show" role="alert">
    <i class="ti ti-info-circle me-2"></i>
    <strong>Nota:</strong> Los meses que ya tienen consumo o compromisos registrados tienen un monto mínimo que no puede reducirse.
    Estos aparecerán marcados con <i class="ti ti-lock"></i> y mostrarán el monto mínimo requerido.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>

{{-- Formulario --}}
<form action="{{ route('budget_monthly_distributions.update', $annualBudget->id) }}" method="POST" id="formDistributions">
    @csrf
    @method('PUT')

    <input type="hidden" name="annual_budget_id" value="{{ $annualBudget->id }}">

    {{-- Incluir el formulario parcial --}}
    @include('budget_monthly_distributions.partials.form', [
    'isEdit' => true,
    ])

    {{-- Botones de Acción --}}
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div class="text-muted">
                    <i class="ti ti-info-circle me-1"></i>
                    Los cambios afectarán el presupuesto disponible para nuevas requisiciones.
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('annual_budgets.show', $annualBudget->id) }}" class="btn btn-light">
                        <i class="ti ti-x me-1"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy me-1"></i> Actualizar Distribuciones
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
    // Confirmación antes de abandonar la página con cambios sin guardar
    let formChanged = false;

    document.querySelectorAll('.distribution-input').forEach(input => {
        const originalValue = input.value;

        input.addEventListener('change', function() {
            if (input.value !== originalValue) {
                formChanged = true;
            }
        });
    });

    window.addEventListener('beforeunload', function(e) {
        if (formChanged) {
            e.preventDefault();
            e.returnValue = '';
            return '';
        }
    });

    // No mostrar alerta al enviar el formulario
    document.getElementById('formDistributions').addEventListener('submit', function() {
        formChanged = false;
    });

    // Validación adicional para inputs bloqueados
    document.querySelectorAll('.distribution-input[min]').forEach(input => {
        const minValue = parseFloat(input.min);

        if (minValue > 0) {
            input.addEventListener('blur', function() {
                const currentValue = parseFloat(input.value) || 0;

                if (currentValue < minValue) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Monto mínimo requerido',
                        text: `Este mes tiene compromisos o consumos. El monto mínimo es: $${minValue.toFixed(2)}`,
                        confirmButtonText: 'Entendido'
                    });
                    input.value = minValue.toFixed(2);
                    input.focus();
                }
            });
        }
    });
</script>
@endpush

@push('scripts')
<script>
    (function() {
        function parseFormattedMoney(value) {
            const normalized = String(value || '')
                .replace(/[^\d.,-]/g, '')
                .replace(/,/g, '');

            const parsed = Number.parseFloat(normalized);
            return Number.isFinite(parsed) ? parsed : 0;
        }

        document.addEventListener('input', function(e) {
            if (!e.target.classList.contains('distribution-input')) {
                return;
            }

            const initialValue = Number.parseFloat(e.target.dataset.initialValue || '0');
            const currentValue = parseFormattedMoney(e.target.value);

            if (Math.abs(currentValue - initialValue) > 0.009) {
                formChanged = true;
            }
        });

        document.addEventListener('blur', function(e) {
            if (!e.target.classList.contains('distribution-input')) {
                return;
            }

            const minValue = parseFormattedMoney(e.target.dataset.minAmount || '0');
            const currentValue = parseFormattedMoney(e.target.value);

            if (minValue > 0 && currentValue < minValue) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Monto mínimo requerido',
                    text: `Este mes tiene compromisos o consumos. El monto mínimo es: $${minValue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`,
                    confirmButtonText: 'Entendido'
                });
                e.target.value = minValue.toFixed(2);
                e.target.focus();
            }
        }, true);
    })();
</script>
@endpush
