@extends('layouts.zircos')

@section('title', 'Nueva Orden de Compra Directa')

@section('page.title', 'Nueva Orden de Compra Directa')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('purchase-orders.index') }}">Órdenes de Compra</a></li>
    <li class="breadcrumb-item active">Nueva</li>
@endsection

@section('content')
<form action="{{ route('direct-purchase-orders.store') }}" method="POST" enctype="multipart/form-data" id="ocd-form">
    @csrf

    <div class="row">
        {{-- Columna Principal: Formulario --}}
        <div class="col-lg-8">
            
            {{-- SECCIÓN 1: DATOS DEL PROVEEDOR --}}
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="ti ti-building me-1"></i>Información del Proveedor</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="supplier_id" class="form-label">
                                Proveedor <span class="text-danger">*</span>
                            </label>
                            <select name="supplier_id" id="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror" required>
                                <option value="">-- Seleccione un proveedor --</option>
                                @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" 
                                            data-payment-terms="{{ $supplier->default_payment_terms ?? 'Contado' }}"
                                            data-delivery-days="{{ $supplier->avg_delivery_time ?? 30 }}"
                                            {{ old('supplier_id') == $supplier->id ? 'selected' : '' }}>
                                        {{ $supplier->company_name }} - {{ $supplier->rfc }}
                                    </option>
                                @endforeach
                            </select>
                            @error('supplier_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="payment_terms" class="form-label">Condiciones de Pago</label>
                            <input type="text" 
                                   name="payment_terms" 
                                   id="payment_terms" 
                                   class="form-control @error('payment_terms') is-invalid @enderror"
                                   placeholder="Ej: 30 días neto"
                                   value="{{ old('payment_terms') }}">
                            <small class="text-muted">Se llenarán automáticamente del proveedor si no se especifican</small>
                            @error('payment_terms')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="estimated_delivery_days" class="form-label">Días Estimados de Entrega</label>
                            <input type="number" 
                                   name="estimated_delivery_days" 
                                   id="estimated_delivery_days" 
                                   class="form-control @error('estimated_delivery_days') is-invalid @enderror"
                                   placeholder="Ej: 30"
                                   min="1"
                                   max="365"
                                   value="{{ old('estimated_delivery_days') }}">
                            <small class="text-muted">Se llenarán automáticamente del proveedor si no se especifican</small>
                            @error('estimated_delivery_days')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- SECCIÓN 2: DATOS PRESUPUESTALES --}}
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="ti ti-wallet me-1"></i>Información Presupuestal</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="cost_center_id" class="form-label">
                                Centro de Costo <span class="text-danger">*</span>
                            </label>
                            <select name="cost_center_id" id="cost_center_id" class="form-select @error('cost_center_id') is-invalid @enderror" required>
                                <option value="">-- Seleccione --</option>
                                @foreach($costCenters as $cc)
                                    <option value="{{ $cc->id }}" {{ old('cost_center_id') == $cc->id ? 'selected' : '' }}>
                                        {{ $cc->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('cost_center_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="expense_category_id" class="form-label">
                                Cuenta <span class="text-danger">*</span>
                            </label>
                            <select name="expense_category_id" id="expense_category_id" class="form-select @error('expense_category_id') is-invalid @enderror" required>
                                <option value="">-- Seleccione --</option>
                                @foreach($expenseCategories as $category)
                                    <option value="{{ $category->id }}" {{ old('expense_category_id') == $category->id ? 'selected' : '' }}>
                                        {{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('expense_category_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="application_month" class="form-label">
                                Mes de Aplicación <span class="text-danger">*</span>
                            </label>
                            <input type="month" 
                                   name="application_month" 
                                   id="application_month" 
                                   class="form-control @error('application_month') is-invalid @enderror"
                                   value="{{ old('application_month', $currentMonth) }}"
                                   min="{{ $currentMonth }}"
                                   required>
                            @error('application_month')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Alerta de Presupuesto (se llenará con AJAX en Paso 4) --}}
                    <div id="budget-alert" class="alert alert-info d-none">
                        <i class="ti ti-info-circle me-1"></i>
                        <span id="budget-message">Seleccione CC, Cuenta y Mes para verificar presupuesto disponible.</span>
                    </div>
                    @error('budget')
                        <div class="alert alert-danger">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            {{-- SECCIÓN 3: JUSTIFICACIÓN --}}
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="ti ti-message-circle me-1"></i>Justificación de la Compra Directa</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="justification" class="form-label">
                            Justificación <span class="text-danger">*</span>
                        </label>
                        <textarea name="justification" 
                                  id="justification" 
                                  rows="5" 
                                  class="form-control @error('justification') is-invalid @enderror"
                                  placeholder="Explique detalladamente por qué esta compra debe realizarse directamente con este proveedor (mínimo 100 caracteres)..."
                                  required
                                  minlength="100"
                                  maxlength="2000">{{ old('justification') }}</textarea>
                        <div class="d-flex justify-content-between mt-1">
                            <small class="text-muted">Mínimo 100 caracteres, máximo 2000</small>
                            <small class="text-muted">
                                <span id="char-count">0</span> / 2000
                            </small>
                        </div>
                        @error('justification')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- SECCIÓN 4: PARTIDAS --}}
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="ti ti-list me-1"></i>Partidas / Items</h5>
                    <button type="button" class="btn btn-sm btn-light" id="add-item-btn">
                        <i class="ti ti-plus me-1"></i>Agregar Partida
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered" id="items-table">
                            <thead class="table-light">
                                <tr>
                                    <th width="40%">Descripción <span class="text-danger">*</span></th>
                                    <th width="15%">Cantidad <span class="text-danger">*</span></th>
                                    <th width="20%">Precio Unit. <span class="text-danger">*</span></th>
                                    <th width="20%">Subtotal</th>
                                    <th width="5%"></th>
                                </tr>
                            </thead>
                            <tbody id="items-tbody">
                                {{-- Las partidas se agregarán dinámicamente con JavaScript (Paso 5) --}}
                                {{-- Por ahora, una fila de ejemplo --}}
                                <tr class="item-row">
                                    <td>
                                        <input type="text" 
                                               name="items[0][description]" 
                                               class="form-control form-control-sm item-description" 
                                               placeholder="Descripción del producto o servicio"
                                               required
                                               maxlength="500">
                                    </td>
                                    <td>
                                        <input type="number" 
                                               name="items[0][quantity]" 
                                               class="form-control form-control-sm item-quantity" 
                                               placeholder="0.00"
                                               step="0.01"
                                               min="0.01"
                                               required>
                                    </td>
                                    <td>
                                        <input type="number" 
                                               name="items[0][unit_price]" 
                                               class="form-control form-control-sm item-unit-price" 
                                               placeholder="0.00"
                                               step="0.01"
                                               min="0.01"
                                               required>
                                    </td>
                                    <td>
                                        <input type="text" 
                                               class="form-control form-control-sm item-subtotal" 
                                               value="$0.00" 
                                               readonly>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-danger remove-item-btn" title="Eliminar">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    @error('items')
                        <div class="text-danger small">{{ $message }}</div>
                    @enderror
                    @error('items.*')
                        <div class="text-danger small">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            {{-- SECCIÓN 5: DOCUMENTOS --}}
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="ti ti-paperclip me-1"></i>Documentos Adjuntos</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="quotation_file" class="form-label">
                            Cotización del Proveedor <span class="text-danger">*</span>
                        </label>
                        <input type="file" 
                               name="quotation_file" 
                               id="quotation_file" 
                               class="form-control @error('quotation_file') is-invalid @enderror"
                               accept=".pdf,.jpg,.jpeg,.png"
                               required>
                        <small class="text-muted">Formatos permitidos: PDF, JPG, PNG (máximo 5MB)</small>
                        @error('quotation_file')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="support_documents" class="form-label">
                            Documentos de Soporte (Opcional)
                        </label>
                        <input type="file" 
                               name="support_documents[]" 
                               id="support_documents" 
                               class="form-control @error('support_documents') is-invalid @enderror"
                               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                               multiple>
                        <small class="text-muted">Máximo 5 archivos (PDF, imágenes, documentos Office)</small>
                        @error('support_documents')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

        </div>

        {{-- Columna Lateral: Resumen y Acciones --}}
        <div class="col-lg-4">
            
            {{-- RESUMEN DE TOTALES --}}
            <div class="card shadow-sm mb-3 sticky-top" style="top: 20px;">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="ti ti-calculator me-1"></i>Resumen de Totales</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <td>Subtotal:</td>
                            <td class="text-end fw-bold" id="summary-subtotal">$0.00</td>
                        </tr>
                        <tr>
                            <td>IVA (16%):</td>
                            <td class="text-end fw-bold" id="summary-iva">$0.00</td>
                        </tr>
                        <tr class="table-primary">
                            <td class="fw-bold">TOTAL:</td>
                            <td class="text-end fw-bold fs-5" id="summary-total">$0.00</td>
                        </tr>
                    </table>

                    <div class="alert alert-warning mt-3 mb-0">
                        <small>
                            <i class="ti ti-alert-triangle me-1"></i>
                            <strong>Límite de OCD:</strong> $250,000.00 MXN
                        </small>
                    </div>
                </div>
            </div>

            {{-- BOTONES DE ACCIÓN --}}
            <div class="card shadow-sm">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100 mb-2">
                        <i class="ti ti-device-floppy me-1"></i>Guardar Borrador
                    </button>
                    <a href="{{ route('purchase-orders.index') }}" class="btn btn-outline-secondary w-100">
                        <i class="ti ti-x me-1"></i>Cancelar
                    </a>

                    <hr>

                    <div class="alert alert-info mb-0">
                        <small>
                            <strong>Nota:</strong> La OCD se guardará como <em>Borrador</em>. 
                            Podrá enviarla a aprobación posteriormente desde el listado.
                        </small>
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
    
    // ==========================================
    // CONTADOR DE CARACTERES (Justificación)
    // ==========================================
    $('#justification').on('input', function() {
        const count = $(this).val().length;
        $('#char-count').text(count);
        
        if (count < 100) {
            $('#char-count').addClass('text-danger').removeClass('text-success');
        } else {
            $('#char-count').addClass('text-success').removeClass('text-danger');
        }
    });

    // Inicializar contador
    $('#justification').trigger('input');

    // ==========================================
    // AUTOCOMPLETAR CONDICIONES DEL PROVEEDOR
    // ==========================================
    $('#supplier_id').on('change', function() {
        const selectedOption = $(this).find('option:selected');
        const paymentTerms = selectedOption.data('payment-terms');
        const deliveryDays = selectedOption.data('delivery-days');
        
        // Solo llenar si están vacíos
        if (!$('#payment_terms').val()) {
            $('#payment_terms').val(paymentTerms);
        }
        
        if (!$('#estimated_delivery_days').val()) {
            $('#estimated_delivery_days').val(deliveryDays);
        }
    });

    // ==========================================
    // GESTIÓN DE PARTIDAS (Paso 5 - Por Implementar)
    // ==========================================
    let itemIndex = 1;

    // Agregar nueva partida
    $('#add-item-btn').on('click', function() {
        // TODO: Implementar en Paso 5
        Swal.fire({ icon: 'info', title: 'En desarrollo', text: 'Funcionalidad de agregar partidas se implementará en el Paso 5.' });
    });

    // Eliminar partida
    $(document).on('click', '.remove-item-btn', function() {
        // TODO: Implementar en Paso 5
        Swal.fire({ icon: 'info', title: 'En desarrollo', text: 'Funcionalidad de eliminar partidas se implementará en el Paso 5.' });
    });

    // Calcular subtotal de cada partida
    $(document).on('input', '.item-quantity, .item-unit-price', function() {
        // TODO: Implementar en Paso 5
        calculateItemSubtotal($(this).closest('tr'));
        calculateTotals();
    });

    // ==========================================
    // CALCULAR TOTALES (Básico)
    // ==========================================
    function calculateItemSubtotal(row) {
        const quantity = parseFloat(row.find('.item-quantity').val()) || 0;
        const unitPrice = parseFloat(row.find('.item-unit-price').val()) || 0;
        const subtotal = quantity * unitPrice;
        
        row.find('.item-subtotal').val('$' + subtotal.toFixed(2));
    }

    function calculateTotals() {
        let subtotal = 0;
        
        $('.item-row').each(function() {
            const quantity = parseFloat($(this).find('.item-quantity').val()) || 0;
            const unitPrice = parseFloat($(this).find('.item-unit-price').val()) || 0;
            subtotal += (quantity * unitPrice);
        });
        
        const iva = subtotal * 0.16;
        const total = subtotal + iva;
        
        $('#summary-subtotal').text('$' + subtotal.toFixed(2));
        $('#summary-iva').text('$' + iva.toFixed(2));
        $('#summary-total').text('$' + total.toFixed(2));
        
        // Validar límite de $250K
        if (total > 250000) {
            $('#summary-total').addClass('text-danger');
        } else {
            $('#summary-total').removeClass('text-danger');
        }
    }

    // Calcular totales al cargar
    calculateTotals();

});
</script>
@endpush