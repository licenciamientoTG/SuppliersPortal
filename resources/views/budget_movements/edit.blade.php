@extends('layouts.zircos')

@section('title', 'Editar Movimiento Presupuestal')

@section('page.title', 'Editar Movimiento Presupuestal')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('budget_movements.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('budget_movements.index') }}">Movimientos Presupuestales</a></li>
    <li class="breadcrumb-item active">Editar Movimiento #{{ $budgetMovement->id }}</li>
@endsection

@section('content')
<form action="{{ route('budget_movements.update', $budgetMovement) }}" method="POST" id="movementForm" novalidate>
    @csrf
    @method('PUT')

    <div class="alert alert-danger d-none" id="form-errors">
        <ul class="mb-0" id="error-list"></ul>
    </div>

    <div class="row">
        <!-- Formulario Principal -->
        <div class="col-lg-8">
            <!-- Información General -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="ti ti-info-circle me-2"></i>
                        Información General
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="movement_type" class="form-label">
                                    Tipo de Movimiento <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="ti ti-arrows-exchange"></i>
                                    </span>
                                    <select class="form-select @error('movement_type') is-invalid @enderror"
                                        id="movement_type"
                                        name="movement_type"
                                        required>
                                        <option value="">Seleccione un tipo</option>
                                        <option value="TRANSFERENCIA" {{ old('movement_type', $budgetMovement->movement_type) == 'TRANSFERENCIA' ? 'selected' : '' }}>
                                            Transferencia (entre centros de costo)
                                        </option>
                                        <option value="AMPLIACION" {{ old('movement_type', $budgetMovement->movement_type) == 'AMPLIACION' ? 'selected' : '' }}>
                                            Ampliación (aumentar presupuesto)
                                        </option>
                                        <option value="REDUCCION" {{ old('movement_type', $budgetMovement->movement_type) == 'REDUCCION' ? 'selected' : '' }}>
                                            Reducción (disminuir presupuesto)
                                        </option>
                                    </select>
                                </div>
                                @error('movement_type')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <small class="form-text text-muted" id="movement_type_help"></small>
                            </div>

                            <div class="mb-3">
                                <label for="fiscal_year" class="form-label">
                                    Año Fiscal <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="ti ti-calendar-event"></i>
                                    </span>
                                    <select class="form-select @error('fiscal_year') is-invalid @enderror"
                                        id="fiscal_year"
                                        name="fiscal_year"
                                        required>
                                        <option value="">Seleccione un año</option>
                                        @for($year = date('Y') - 1; $year <= date('Y') + 2; $year++)
                                            <option value="{{ $year }}" {{ old('fiscal_year', $budgetMovement->fiscal_year) == $year ? 'selected' : '' }}>
                                            {{ $year }}
                                            </option>
                                            @endfor
                                    </select>
                                </div>
                                @error('fiscal_year')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="movement_date" class="form-label">
                                    Fecha del Movimiento <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="ti ti-calendar"></i>
                                    </span>
                                    <input type="date"
                                        class="form-control @error('movement_date') is-invalid @enderror"
                                        id="movement_date"
                                        name="movement_date"
                                        value="{{ old('movement_date', $budgetMovement->movement_date->format('Y-m-d')) }}"
                                        required>
                                </div>
                                @error('movement_date')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="total_amount" class="form-label">
                                    Monto Total <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="ti ti-cash"></i>
                                    </span>
                                    <input type="number"
                                        class="form-control @error('total_amount') is-invalid @enderror"
                                        id="total_amount"
                                        name="total_amount"
                                        value="{{ old('total_amount', $budgetMovement->total_amount) }}"
                                        step="0.01"
                                        min="0.01"
                                        placeholder="0.00"
                                        required>
                                    <span class="input-group-text">MXN</span>
                                </div>
                                @error('total_amount')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="mb-3">
                                <label for="justification" class="form-label">
                                    Justificación <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="ti ti-file-description"></i>
                                    </span>
                                    <textarea class="form-control @error('justification') is-invalid @enderror"
                                        id="justification"
                                        name="justification"
                                        rows="3"
                                        placeholder="Ingrese la justificación..."
                                        required>{{ old('justification', $budgetMovement->justification) }}</textarea>
                                </div>
                                @error('justification')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <small class="form-text text-muted">Mínimo 10 caracteres, máximo 1000.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @php
            // Obtener detalles existentes
            $originDetail = $budgetMovement->details->where('detail_type', 'ORIGEN')->first();
            $destinationDetail = $budgetMovement->details->where('detail_type', 'DESTINO')->first();
            $adjustmentDetail = $budgetMovement->details->where('detail_type', 'AJUSTE')->first();
            @endphp

            <!-- Secciones ORIGEN y DESTINO (Horizontal para Transferencias) -->
            <div class="row" id="transfer_sections" style="display: none;">
                <!-- Sección ORIGEN -->
                <div class="col-md-6">
                    <div class="card mb-4" id="origin_section">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0">
                                <i class="ti ti-arrow-up-circle me-2"></i>
                                <span id="origin_title">Origen</span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <!-- Centro de Costo Origen -->
                            <div class="mb-3">
                                <label for="origin_cost_center_id" class="form-label">
                                    Centro de Costo <span class="text-danger">*</span>
                                </label>
                                <select class="form-select select2 @error('origin_cost_center_id') is-invalid @enderror"
                                    id="origin_cost_center_id"
                                    name="origin_cost_center_id">
                                    <option value="">Seleccione un centro de costo</option>
                                    @foreach($costCenters as $cc)
                                    <option value="{{ $cc->id }}" {{ old('origin_cost_center_id', $originDetail?->cost_center_id) == $cc->id ? 'selected' : '' }}>
                                        {{ $cc->name }} ({{ $cc->code }})
                                    </option>
                                    @endforeach
                                </select>
                                @error('origin_cost_center_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Mes Origen -->
                            <div class="mb-3">
                                <label for="origin_month" class="form-label">
                                    Mes <span class="text-danger">*</span>
                                </label>
                                <x-select-month name="origin_month" :value="old('origin_month', $originDetail?->month)" required />
                                @error('origin_month')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Categoría Origen -->
                            <div class="mb-3">
                                <label for="origin_expense_category_id" class="form-label">
                                    Cuenta <span class="text-danger">*</span>
                                </label>
                                <select class="form-select select2 @error('origin_expense_category_id') is-invalid @enderror"
                                    id="origin_expense_category_id"
                                    name="origin_expense_category_id">
                                    <option value="">Seleccione una cuenta</option>
                                    @foreach($expenseCategories as $cat)
                                    <option value="{{ $cat->id }}" {{ old('origin_expense_category_id', $originDetail?->expense_category_id) == $cat->id ? 'selected' : '' }}>
                                        {{ $cat->name }}
                                    </option>
                                    @endforeach
                                </select>
                                @error('origin_expense_category_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Alert de Presupuesto Disponible ORIGEN -->
                            <div id="origin_budget_alert" class="alert" style="display: none;">
                                <div class="d-flex align-items-center">
                                    <div class="spinner-border spinner-border-sm me-2" role="status" id="origin_budget_spinner" style="display: none;">
                                        <span class="visually-hidden">Cargando...</span>
                                    </div>
                                    <div id="origin_budget_info" style="width: 100%;">
                                        <strong>Presupuesto Disponible:</strong> <span id="origin_available_amount" class="fw-bold">-</span><br>
                                        <small class="text-muted">
                                            Asignado: $<span id="origin_assigned_amount">-</span> |
                                            Consumido: $<span id="origin_consumed_amount">-</span> |
                                            Comprometido: $<span id="origin_committed_amount">-</span>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sección DESTINO -->
                <div class="col-md-6">
                    <div class="card mb-4" id="destination_section">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">
                                <i class="ti ti-arrow-down-circle me-2"></i>
                                <span id="destination_title">Destino</span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <!-- Centro de Costo Destino -->
                            <div class="mb-3">
                                <label for="destination_cost_center_id" class="form-label">
                                    Centro de Costo <span class="text-danger">*</span>
                                </label>
                                <select class="form-select select2 @error('destination_cost_center_id') is-invalid @enderror"
                                    id="destination_cost_center_id"
                                    name="destination_cost_center_id">
                                    <option value="">Seleccione un centro de costo</option>
                                    @foreach($costCenters as $cc)
                                    <option value="{{ $cc->id }}" {{ old('destination_cost_center_id', $destinationDetail?->cost_center_id) == $cc->id ? 'selected' : '' }}>
                                        {{ $cc->name }} ({{ $cc->code }})
                                    </option>
                                    @endforeach
                                </select>
                                @error('destination_cost_center_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Mes Destino -->
                            <div class="mb-3">
                                <label for="destination_month" class="form-label">
                                    Mes <span class="text-danger">*</span>
                                </label>
                                <x-select-month name="destination_month" :value="old('destination_month', $destinationDetail?->month)" required />
                                @error('destination_month')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Categoría Destino -->
                            <div class="mb-3">
                                <label for="destination_expense_category_id" class="form-label">
                                    Cuenta <span class="text-danger">*</span>
                                </label>
                                <select class="form-select select2 @error('destination_expense_category_id') is-invalid @enderror"
                                    id="destination_expense_category_id"
                                    name="destination_expense_category_id">
                                    <option value="">Seleccione una cuenta</option>
                                    @foreach($expenseCategories as $cat)
                                    <option value="{{ $cat->id }}" {{ old('destination_expense_category_id', $destinationDetail?->expense_category_id) == $cat->id ? 'selected' : '' }}>
                                        {{ $cat->name }}
                                    </option>
                                    @endforeach
                                </select>
                                @error('destination_expense_category_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección ÚNICA (Para Ampliaciones y Reducciones cuando NO es transferencia) -->
            <div class="card mb-4" id="single_section" style="display: none;">
                <div class="card-header" id="single_section_header">
                    <h5 class="mb-0">
                        <i class="ti ti-target-arrow me-2"></i>
                        <span id="single_title">Detalles</span>
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Centro de Costo -->
                    <div class="row mb-3">
                        <label for="cost_center_id" class="col-sm-3 col-form-label">
                            Centro de Costo <span class="text-danger">*</span>
                        </label>
                        <div class="col-sm-9">
                            <select class="form-select select2 @error('cost_center_id') is-invalid @enderror"
                                id="cost_center_id"
                                name="cost_center_id">
                                <option value="">Seleccione un centro de costo</option>
                                @foreach($costCenters as $cc)
                                <option value="{{ $cc->id }}" {{ old('cost_center_id', $adjustmentDetail?->cost_center_id) == $cc->id ? 'selected' : '' }}>
                                    {{ $cc->name }} ({{ $cc->code }})
                                </option>
                                @endforeach
                            </select>
                            @error('cost_center_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Mes -->
                    <div class="row mb-3">
                        <label for="month" class="col-sm-3 col-form-label">
                            Mes <span class="text-danger">*</span>
                        </label>
                        <div class="col-sm-9">
                            <x-select-month name="month" :value="old('month', $adjustmentDetail?->month)" required />
                            @error('month')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Categoría -->
                    <div class="row mb-3">
                        <label for="expense_category_id" class="col-sm-3 col-form-label">
                                Cuenta <span class="text-danger">*</span>
                            </label>
                            <div class="col-sm-9">
                                <select class="form-select select2 @error('expense_category_id') is-invalid @enderror"
                                    id="expense_category_id"
                                    name="expense_category_id">
                                    <option value="">Seleccione una cuenta</option>
                                @foreach($expenseCategories as $cat)
                                <option value="{{ $cat->id }}" {{ old('expense_category_id', $adjustmentDetail?->expense_category_id) == $cat->id ? 'selected' : '' }}>
                                    {{ $cat->name }}
                                </option>
                                @endforeach
                            </select>
                            @error('expense_category_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Alert de Presupuesto Disponible SINGLE -->
                    <div id="single_budget_alert" class="alert" style="display: none;">
                        <div class="d-flex align-items-center">
                            <div class="spinner-border spinner-border-sm me-2" role="status" id="single_budget_spinner" style="display: none;">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                            <div id="single_budget_info" style="width: 100%;">
                                <strong>Presupuesto Disponible:</strong> <span id="single_available_amount" class="fw-bold">-</span><br>
                                <small class="text-muted">
                                    Asignado: $<span id="single_assigned_amount">-</span> |
                                    Consumido: $<span id="single_consumed_amount">-</span> |
                                    Comprometido: $<span id="single_committed_amount">-</span>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar de Acciones e Información -->
        <div class="col-lg-4">
            <!-- Botones de Acción -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="ti ti-check-circle me-2"></i>
                        Acciones
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-device-floppy me-1"></i>
                            Actualizar Movimiento
                        </button>
                        <a href="{{ route('budget_movements.index') }}" class="btn btn-secondary">
                            <i class="ti ti-x me-1"></i>
                            Cancelar
                        </a>
                    </div>
                </div>
            </div>

            <!-- Info del Movimiento -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="ti ti-info-circle me-2"></i>
                        Información
                    </h5>
                </div>
                <div class="card-body">
                    <p class="mb-2">
                        <strong>ID:</strong> #{{ $budgetMovement->id }}
                    </p>
                    <p class="mb-2">
                        <strong>Estado:</strong>
                        <span class="badge bg-warning">{{ $budgetMovement->status }}</span>
                    </p>
                    <p class="mb-2">
                        <strong>Creado por:</strong><br>
                        {{ $budgetMovement->creator->name }}
                    </p>
                    <p class="mb-0">
                        <strong>Fecha de creación:</strong><br>
                        {{ $budgetMovement->created_at->format('d/m/Y H:i') }}
                    </p>
                </div>
            </div>

            <!-- Ayuda -->
            <div class="card" id="help_card" style="display: none;">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="ti ti-help me-2"></i>
                        Ayuda
                    </h5>
                </div>
                <div class="card-body">
                    <div id="help_content">
                        <!-- Se llenará dinámicamente según el tipo seleccionado -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        // Inicializar Select2
        $('.select2').select2({
            theme: 'bootstrap-5',
            width: '100%'
        });

        // Textos de ayuda según tipo de movimiento
        const helpTexts = {
            'TRANSFERENCIA': `
            <h6>Transferencia entre Centros</h6>
            <p>Mueve presupuesto entre centros de costo, meses o categorías.</p>
            <ul>
                <li>Define el <strong>origen</strong> (de dónde sale el dinero)</li>
                <li>Define el <strong>destino</strong> (a dónde va el dinero)</li>
                    <li>Al menos uno debe ser diferente: centro, mes o cuenta</li>
                </ul>
            `,
                'AMPLIACION': `
                <h6>Ampliación de Presupuesto</h6>
                <p>Aumenta el presupuesto de un centro de costo.</p>
                <ul>
                    <li>El monto se <strong>suma</strong> al presupuesto actual</li>
                    <li>Define el centro, mes y cuenta a ampliar</li>
                </ul>
            `,
                'REDUCCION': `
                <h6>Reducción de Presupuesto</h6>
                <p>Disminuye el presupuesto de un centro de costo.</p>
                <ul>
                    <li>El monto se <strong>resta</strong> del presupuesto actual</li>
                    <li>Define el centro, mes y cuenta a reducir</li>
            </ul>
        `
        };

        // Función para actualizar visibilidad de secciones
        function updateSections() {
            const movementType = $('#movement_type').val();

            // Ocultar todas las secciones primero
            $('#transfer_sections').hide();
            $('#single_section').hide();
            $('#help_card').hide();

            // Limpiar campos de las secciones ocultas
            $('#origin_section select, #origin_section input').prop('required', false);
            $('#destination_section select, #destination_section input').prop('required', false);
            $('#single_section select, #single_section input').prop('required', false);

            // Mostrar secciones según el tipo
            if (movementType === 'TRANSFERENCIA') {
                $('#transfer_sections').show();
                $('#origin_title').text('Origen (De dónde sale el dinero)');
                $('#destination_title').text('Destino (A dónde va el dinero)');

                // Hacer campos requeridos
                $('#origin_cost_center_id, #origin_month, #origin_expense_category_id').prop('required', true);
                $('#destination_cost_center_id, #destination_month, #destination_expense_category_id').prop('required', true);

                $('#movement_type_help').text('Transferir presupuesto entre centros de costo');

            } else if (movementType === 'AMPLIACION') {
                $('#single_section').show();
                $('#single_section_header').removeClass('bg-danger').addClass('bg-primary');
                $('#single_title').text('Centro de Costo a Ampliar');

                // Hacer campos requeridos
                $('#cost_center_id, #month, #expense_category_id').prop('required', true);

                $('#movement_type_help').text('Aumentar el presupuesto de un centro de costo');

            } else if (movementType === 'REDUCCION') {
                $('#single_section').show();
                $('#single_section_header').removeClass('bg-primary').addClass('bg-danger');
                $('#single_title').text('Centro de Costo a Reducir');

                // Hacer campos requeridos
                $('#cost_center_id, #month, #expense_category_id').prop('required', true);

                $('#movement_type_help').text('Disminuir el presupuesto de un centro de costo');
            }

            // Mostrar ayuda si hay tipo seleccionado
            if (movementType) {
                $('#help_content').html(helpTexts[movementType]);
                $('#help_card').show();
            }
        }

        // Evento al cambiar tipo de movimiento
        $('#movement_type').on('change', updateSections);

        // Ejecutar al cargar para mostrar las secciones según el tipo existente
        updateSections();

        // Validación antes de enviar
        $('#movementForm').on('submit', function(e) {
            e.preventDefault();

            const movementType = $('#movement_type').val();

            if (!movementType) {
                Swal.fire({
                    title: 'Error',
                    text: 'Debe seleccionar un tipo de movimiento',
                    icon: 'error',
                    customClass: {
                        confirmButton: 'btn btn-danger'
                    },
                    buttonsStyling: false
                });
                return false;
            }

            // Validación para transferencias: al menos un campo debe ser diferente
            if (movementType === 'TRANSFERENCIA') {
                const originCC = $('#origin_cost_center_id').val();
                const destCC = $('#destination_cost_center_id').val();
                const originMonth = $('#origin_month').val();
                const destMonth = $('#destination_month').val();
                const originCat = $('#origin_expense_category_id').val();
                const destCat = $('#destination_expense_category_id').val();

                // Validar que al menos UNO sea diferente
                if (originCC === destCC && originMonth === destMonth && originCat === destCat) {
                    Swal.fire({
                        title: 'Error',
                        text: 'En una transferencia, al menos uno de estos campos debe ser diferente: centro de costo, mes o cuenta.',
                        icon: 'error',
                        customClass: {
                            confirmButton: 'btn btn-danger'
                        },
                        buttonsStyling: false
                    });
                    return false;
                }
            }

            // Submit via AJAX
            const form = $(this);
            const formData = form.serialize();
            const submitButton = form.find('button[type="submit"]');
            const originalButtonText = submitButton.html();

            // Show loading state
            submitButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Procesando...');

            // Clear previous errors
            $('.is-invalid').removeClass('is-invalid');
            $('.invalid-feedback').remove();

            $.ajax({
                url: form.attr('action'),
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.redirect) {
                        window.location.href = response.redirect;
                    } else {
                        window.location.href = "{{ route('budget_movements.index') }}";
                    }
                },
                error: function(xhr) {
                    if (xhr.status === 422) {
                        const errors = xhr.responseJSON.errors;
                        for (const field in errors) {
                            const input = $(`[name="${field}"]`);
                            const errorMessage = errors[field][0];

                            input.addClass('is-invalid');
                            const errorDiv = $('<div class="invalid-feedback">').text(errorMessage);
                            input.after(errorDiv);

                            if (input.hasClass('select2-hidden-accessible')) {
                                input.parent().find('.select2-selection').addClass('is-invalid');
                            }
                        }

                        $('html, body').animate({
                            scrollTop: $('.is-invalid').first().offset().top - 100
                        }, 500);

                        Swal.fire({
                            title: 'Error de validación',
                            text: 'Por favor, corrija los errores en el formulario.',
                            icon: 'error',
                            customClass: {
                                confirmButton: 'btn btn-danger'
                            },
                            buttonsStyling: false
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: 'Ocurrió un error al actualizar el movimiento.',
                            icon: 'error',
                            customClass: {
                                confirmButton: 'btn btn-danger'
                            },
                            buttonsStyling: false
                        });
                    }
                },
                complete: function() {
                    submitButton.prop('disabled', false).html(originalButtonText);
                }
            });
        });

        // ===== VERIFICACIÓN DE PRESUPUESTO DISPONIBLE =====

        // Función para verificar presupuesto disponible
        function checkOriginBudget() {
            const fiscalYear = $('#fiscal_year').val();
            const costCenterId = $('#origin_cost_center_id').val();
            const month = $('#origin_month').val();
            const expenseCategoryId = $('#origin_expense_category_id').val();

            // Si falta algún campo, ocultar el alert
            if (!fiscalYear || !costCenterId || !month || !expenseCategoryId) {
                $('#origin_budget_alert').hide();
                return;
            }

            // Mostrar spinner
            $('#origin_budget_alert').show().removeClass('alert-success alert-warning alert-danger alert-info').addClass('alert-info');
            $('#origin_budget_spinner').show();
            $('#origin_budget_info').css('opacity', '0.5');

            // Hacer petición AJAX
            $.ajax({
                url: '{{ route("budget_movements.check_budget") }}',
                method: 'GET',
                data: {
                    fiscal_year: fiscalYear,
                    cost_center_id: costCenterId,
                    month: month,
                    expense_category_id: expenseCategoryId
                },
                success: function(response) {
                    $('#origin_budget_spinner').hide();
                    $('#origin_budget_info').css('opacity', '1');

                    if (response.success && response.has_budget) {
                        // Tiene presupuesto disponible
                        const available = parseFloat(response.available_amount);
                        const assigned = parseFloat(response.assigned_amount);
                        const consumed = parseFloat(response.consumed_amount);
                        const committed = parseFloat(response.committed_amount);

                        $('#origin_available_amount').text('$' + available.toLocaleString('es-MX', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }));
                        $('#origin_assigned_amount').text(assigned.toLocaleString('es-MX', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }));
                        $('#origin_consumed_amount').text(consumed.toLocaleString('es-MX', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }));
                        $('#origin_committed_amount').text(committed.toLocaleString('es-MX', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }));

                        // Cambiar color según el estado
                        $('#origin_budget_alert').removeClass('alert-info alert-warning alert-danger').addClass('alert-success');

                        if (response.status === 'ALERTA') {
                            $('#origin_budget_alert').removeClass('alert-success').addClass('alert-warning');
                        } else if (response.status === 'CRÍTICO') {
                            $('#origin_budget_alert').removeClass('alert-success').addClass('alert-danger');
                        }
                    } else {
                        // No tiene presupuesto disponible
                        $('#origin_budget_alert').removeClass('alert-info alert-warning alert-success').addClass('alert-danger');
                        $('#origin_available_amount').text('$0.00');
                        $('#origin_assigned_amount').text('0.00');
                        $('#origin_consumed_amount').text('0.00');
                        $('#origin_committed_amount').text('0.00');

                        // Cambiar el texto del alert
                        $('#origin_budget_info').html('<strong class="text-danger"><i class="ti ti-alert-circle me-1"></i>' + response.message + '</strong>');
                    }
                },
                error: function(xhr) {
                    $('#origin_budget_spinner').hide();
                    $('#origin_budget_info').css('opacity', '1');
                    $('#origin_budget_alert').removeClass('alert-info alert-warning alert-success').addClass('alert-danger');
                    $('#origin_budget_info').html('<strong class="text-danger"><i class="ti ti-alert-circle me-1"></i>Error al verificar presupuesto</strong>');
                }
            });
        }

        // Eventos para verificar presupuesto cuando cambian los campos
        $('#fiscal_year, #origin_cost_center_id, #origin_month, #origin_expense_category_id').on('change', function() {
            const movementType = $('#movement_type').val();
            // Solo verificar si es TRANSFERENCIA o REDUCCIÓN (que usan campos origen)
            if (movementType === 'TRANSFERENCIA' || movementType === 'REDUCCION') {
                checkOriginBudget();
            }
        });

        // También verificar cuando cambia el tipo de movimiento
        $('#movement_type').on('change', function() {
            const movementType = $(this).val();
            if (movementType === 'TRANSFERENCIA' || movementType === 'REDUCCION') {
                // Dar un pequeño delay para que se muestren los campos primero
                setTimeout(checkOriginBudget, 300);
            } else {
                $('#origin_budget_alert').hide();
            }
        });

        // Ejecutar al cargar si el tipo es TRANSFERENCIA o REDUCCIÓN
        const initialType = $('#movement_type').val();
        if (initialType === 'TRANSFERENCIA' || initialType === 'REDUCCION') {
            setTimeout(checkOriginBudget, 500);
        }

        // ===== VERIFICACIÓN DE PRESUPUESTO PARA AMPLIACIÓN Y REDUCCIÓN (single section) =====

        function checkSingleBudget() {
            const fiscalYear = $('#fiscal_year').val();
            const costCenterId = $('#cost_center_id').val();
            const month = $('#month').val();
            const expenseCategoryId = $('#expense_category_id').val();

            // Si falta algún campo, ocultar el alert
            if (!fiscalYear || !costCenterId || !month || !expenseCategoryId) {
                $('#single_budget_alert').hide();
                return;
            }

            // Mostrar spinner
            $('#single_budget_alert').show().removeClass('alert-success alert-warning alert-danger alert-info').addClass('alert-info');
            $('#single_budget_spinner').show();
            $('#single_budget_info').css('opacity', '0.5');

            // Hacer petición AJAX
            $.ajax({
                url: '{{ route("budget_movements.check_budget") }}',
                method: 'GET',
                data: {
                    fiscal_year: fiscalYear,
                    cost_center_id: costCenterId,
                    month: month,
                    expense_category_id: expenseCategoryId
                },
                success: function(response) {
                    $('#single_budget_spinner').hide();
                    $('#single_budget_info').css('opacity', '1');

                    if (response.success && response.has_budget) {
                        // Tiene presupuesto disponible
                        const available = parseFloat(response.available_amount);
                        const assigned = parseFloat(response.assigned_amount);
                        const consumed = parseFloat(response.consumed_amount);
                        const committed = parseFloat(response.committed_amount);

                        $('#single_available_amount').text('$' + available.toLocaleString('es-MX', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }));
                        $('#single_assigned_amount').text(assigned.toLocaleString('es-MX', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }));
                        $('#single_consumed_amount').text(consumed.toLocaleString('es-MX', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }));
                        $('#single_committed_amount').text(committed.toLocaleString('es-MX', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }));

                        // Cambiar color según el estado
                        $('#single_budget_alert').removeClass('alert-info alert-warning alert-danger').addClass('alert-success');

                        if (response.status === 'ALERTA') {
                            $('#single_budget_alert').removeClass('alert-success').addClass('alert-warning');
                        } else if (response.status === 'CRÍTICO') {
                            $('#single_budget_alert').removeClass('alert-success').addClass('alert-danger');
                        }
                    } else {
                        // No tiene presupuesto disponible
                        $('#single_budget_alert').removeClass('alert-info alert-warning alert-success').addClass('alert-danger');
                        $('#single_available_amount').text('$0.00');
                        $('#single_assigned_amount').text('0.00');
                        $('#single_consumed_amount').text('0.00');
                        $('#single_committed_amount').text('0.00');

                        // Cambiar el texto del alert
                        $('#single_budget_info').html('<strong class="text-danger"><i class="ti ti-alert-circle me-1"></i>' + response.message + '</strong>');
                    }
                },
                error: function(xhr) {
                    $('#single_budget_spinner').hide();
                    $('#single_budget_info').css('opacity', '1');
                    $('#single_budget_alert').removeClass('alert-info alert-warning alert-success').addClass('alert-danger');
                    $('#single_budget_info').html('<strong class="text-danger"><i class="ti ti-alert-circle me-1"></i>Error al verificar presupuesto</strong>');
                }
            });
        }

        // Eventos para verificar presupuesto en single section
        $('#fiscal_year, #cost_center_id, #month, #expense_category_id').on('change', function() {
            const movementType = $('#movement_type').val();
            // Solo verificar si es AMPLIACIÓN o REDUCCIÓN
            if (movementType === 'AMPLIACION' || movementType === 'REDUCCION') {
                checkSingleBudget();
            }
        });

        // También verificar cuando cambia el tipo de movimiento para single section
        $('#movement_type').on('change', function() {
            const movementType = $(this).val();
            if (movementType === 'AMPLIACION' || movementType === 'REDUCCION') {
                // Dar un pequeño delay para que se muestren los campos primero
                setTimeout(checkSingleBudget, 300);
            } else {
                $('#single_budget_alert').hide();
            }
        });

        // Ejecutar al cargar si el tipo es AMPLIACIÓN o REDUCCIÓN
        if (initialType === 'AMPLIACION' || initialType === 'REDUCCION') {
            setTimeout(checkSingleBudget, 500);
        }
    });
</script>
@endpush