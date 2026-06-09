@extends('layouts.zircos')

@section('title', 'Nueva Orden de Compra Directa')

@section('page.title', 'Nueva OCD')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('purchase-orders.index') }}">Órdenes</a></li>
    <li class="breadcrumb-item active">Nueva Directa</li>
@endsection

@section('content')

{{-- Mensajes de error y éxito --}}
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="ti ti-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="ti ti-alert-circle me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
@endif

{{-- Error de presupuesto específico --}}
@error('budget')
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="ti ti-alert-circle me-2"></i>
        <strong>Error de Presupuesto: </strong> {{ $message }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
@enderror

@php
    $costCenterCatalog = $costCenters->map(function ($cc) {
        return [
            'id' => $cc->id,
            'company_id' => $cc->company_id,
            'purchase_type' => $cc->purchase_type?->value ?? $cc->purchase_type,
            'label' => ($cc->code ? '[' . $cc->code . '] ' : '') . $cc->name,
        ];
    })->values();
@endphp

<form action="{{ route('direct-purchase-orders.store') }}" method="POST" enctype="multipart/form-data" id="ocd-form">
    @csrf

    <div class="row">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-body">

                    {{-- FILA 1: PROVEEDOR Y CONDICIONES --}}
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Proveedor <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm input-group-select2">
                                <span class="input-group-text"><i class="ti ti-building"></i></span>
                                <select name="supplier_id" id="supplier_id" class="form-select form-select-sm @error('supplier_id') is-invalid @enderror" required>
                                    <option value="">Seleccione...</option>
                                    @foreach($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}"
                                                data-payment-terms="{{ $supplier->default_payment_terms }}"
                                                data-delivery-days="{{ $supplier->avg_delivery_time }}"
                                                {{ old('supplier_id') == $supplier->id ? 'selected' : '' }}>
                                            {{ $supplier->company_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @error('supplier_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Condiciones de Pago</label>
                            <div class="input-group input-group-sm input-group-select2">
                                <span class="input-group-text"><i class="ti ti-receipt"></i></span>
                                <select name="payment_terms" id="payment_terms" class="form-select form-select-sm">
                                    <option value="">Seleccione...</option>
                                    @foreach(\App\Enum\PaymentTerm::options() as $value => $label)
                                        <option value="{{ $value }}" {{ old('payment_terms') == $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Días Entrega</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="ti ti-clock"></i></span>
                                <input type="number" name="estimated_delivery_days" id="estimated_delivery_days" class="form-control form-control-sm" value="{{ old('estimated_delivery_days') }}">
                            </div>
                        </div>
                    </div>

                    {{-- FILA 2: EMPRESA Y UBICACIÓN DE RECEPCIÓN --}}
                    <div class="row g-2 mb-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Tipo de Compra <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm input-group-select2">
                                <span class="input-group-text"><i class="ti ti-filter"></i></span>
                                <select name="purchase_type" id="purchase_type" class="form-select form-select-sm @error('purchase_type') is-invalid @enderror" required>
                                    <option value="">Seleccione...</option>
                                    @foreach($purchaseTypes as $purchaseType)
                                        <option value="{{ $purchaseType }}" {{ old('purchase_type') === $purchaseType ? 'selected' : '' }}>
                                            {{ $purchaseType }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @error('purchase_type') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Empresa <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm input-group-select2">
                                <span class="input-group-text"><i class="ti ti-building-store"></i></span>
                                <select name="company_id" id="company_id" class="form-select form-select-sm @error('company_id') is-invalid @enderror" required>
                                    <option value="">Seleccione...</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}" {{ old('company_id') == $company->id ? 'selected' : '' }}>
                                            {{ $company->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @error('company_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-bold">Ubicación de Recepción <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm input-group-select2">
                                <span class="input-group-text"><i class="ti ti-map-pin"></i></span>
                                <select name="receiving_location_id" id="receiving_location_id" class="form-select form-select-sm @error('receiving_location_id') is-invalid @enderror" required>
                                    <option value="">Seleccione...</option>
                                    @foreach($receivingLocations as $loc)
                                        <option value="{{ $loc->id }}" {{ old('receiving_location_id') == $loc->id ? 'selected' : '' }}>
                                            {{ $loc->name }}{{ $loc->city ? ' — '.$loc->city : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @error('receiving_location_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <hr class="my-3">

                    {{-- JUSTIFICACIÓN COMPACTA --}}
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Justificación de Compra Directa <span class="text-danger">*</span></label>
                        <textarea name="justification" id="justification" rows="3" class="form-control form-control-sm @error('justification') is-invalid @enderror" placeholder="Mínimo 100 caracteres..." required minlength="100" maxlength="2000">{{ old('justification') }}</textarea>
                        <div class="text-end small text-muted"><span id="char-count">0</span> / 2000</div>
                        @error('justification') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>

                    {{-- TABLA DE PARTIDAS --}}
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="m-0 fw-bold text-uppercase small text-primary">Partidas</h6>
                        <button type="button" class="btn btn-xs btn-outline-primary" id="add-item-btn"><i class="ti ti-plus"></i> Agregar</button>
                    </div>
                    <div class="table-responsive border rounded">
                        <table class="table table-sm table-hover mb-0" id="items-table">
                            <thead class="table-light small">
                                <tr>
                                    <th width="22%" class="ps-2">Descripción</th>
                                    <th width="18%">Centro de costo</th>
                                    <th width="18%">Categoría de Gasto</th>
                                    <th width="6%">Cant.</th>
                                    <th width="9%">P. Unit.</th>
                                    <th width="7%">IVA</th>
                                    <th width="9%">Subtotal</th>
                                    <th width="9%">IVA $</th>
                                    <th width="9%">Total</th>
                                    <th width="3%"></th>
                                </tr>
                            </thead>
                            <tbody id="items-tbody">
                                @if(old('items'))
                                    @foreach(old('items') as $index => $item)
                                        <tr class="item-row">
                                            <td class="ps-2">
                                                <input type="text"
                                                       name="items[{{ $index }}][description]"
                                                       class="form-control form-control-sm border-0 item-description"
                                                       placeholder="Descripción"
                                                       value="{{ $item['description'] ?? '' }}"
                                                       required
                                                       maxlength="500">
                                            </td>
                                            <td>
                                                <select name="items[{{ $index }}][cost_center_id]"
                                                        class="form-select form-select-sm border-0 item-cost-center"
                                                        data-selected="{{ $item['cost_center_id'] ?? '' }}"
                                                        required disabled>
                                                    <option value="">Seleccione empresa y tipo...</option>
                                                </select>
                                            </td>
                                            <td>
                                                <select name="items[{{ $index }}][expense_category_id]"
                                                        class="form-select form-select-sm border-0 item-expense-category"
                                                        data-selected="{{ $item['expense_category_id'] ?? '' }}"
                                                        required disabled>
                                                    <option value="">Cargando...</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="number"
                                                       name="items[{{ $index }}][quantity]"
                                                       class="form-control form-control-sm border-0 item-quantity"
                                                       step="0.01"
                                                       min="0.01"
                                                       placeholder="0"
                                                       value="{{ $item['quantity'] ?? '' }}"
                                                       required>
                                            </td>
                                            <td>
                                                <input type="number"
                                                       name="items[{{ $index }}][unit_price]"
                                                       class="form-control form-control-sm border-0 item-unit-price"
                                                       step="0.01"
                                                       min="0.01"
                                                       placeholder="0.00"
                                                       value="{{ $item['unit_price'] ?? '' }}"
                                                       required>
                                            </td>
                                            <td>
                                                <select name="items[{{ $index }}][iva_rate]"
                                                        class="form-select form-select-sm border-0 item-iva-rate"
                                                        required>
                                                    <option value="16" {{ ($item['iva_rate'] ?? '16') == '16' ? 'selected' : '' }}>16%</option>
                                                    <option value="8" {{ ($item['iva_rate'] ?? '') == '8' ? 'selected' : '' }}>8%</option>
                                                    <option value="0" {{ ($item['iva_rate'] ?? '') == '0' ? 'selected' : '' }}>0%</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="text"
                                                       class="form-control form-control-sm border-0 bg-transparent item-subtotal text-end"
                                                       value="$0.00" readonly tabindex="-1">
                                            </td>
                                            <td>
                                                <input type="text"
                                                       class="form-control form-control-sm border-0 bg-transparent item-iva text-end"
                                                       value="$0.00" readonly tabindex="-1">
                                            </td>
                                            <td>
                                                <input type="text"
                                                       class="form-control form-control-sm border-0 bg-transparent item-total text-end fw-bold"
                                                       value="$0.00" readonly tabindex="-1">
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-link text-danger p-0 remove-item-btn" title="Eliminar">
                                                    <i class="ti ti-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                @else
                                    {{-- Fila por defecto --}}
                                    <tr class="item-row">
                                        <td class="ps-2">
                                            <input type="text"
                                                   name="items[0][description]"
                                                   class="form-control form-control-sm border-0 item-description"
                                                   placeholder="Descripción"
                                                   required
                                                   maxlength="500">
                                        </td>
                                        <td>
                                            <select name="items[0][cost_center_id]"
                                                    class="form-select form-select-sm border-0 item-cost-center"
                                                    required disabled>
                                                <option value="">Seleccione empresa y tipo...</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="items[0][expense_category_id]"
                                                    class="form-select form-select-sm border-0 item-expense-category"
                                                    required disabled>
                                                <option value="">Seleccione CC primero...</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number"
                                                   name="items[0][quantity]"
                                                   class="form-control form-control-sm border-0 item-quantity"
                                                   step="0.01" min="0.01" placeholder="0" required>
                                        </td>
                                        <td>
                                            <input type="number"
                                                   name="items[0][unit_price]"
                                                   class="form-control form-control-sm border-0 item-unit-price"
                                                   step="0.01" min="0.01" placeholder="0.00" required>
                                        </td>
                                        <td>
                                            <select name="items[0][iva_rate]"
                                                    class="form-select form-select-sm border-0 item-iva-rate"
                                                    required>
                                                <option value="16">16%</option>
                                                <option value="8">8%</option>
                                                <option value="0">0%</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text"
                                                   class="form-control form-control-sm border-0 bg-transparent item-subtotal text-end"
                                                   value="$0.00" readonly tabindex="-1">
                                        </td>
                                        <td>
                                            <input type="text"
                                                   class="form-control form-control-sm border-0 bg-transparent item-iva text-end"
                                                   value="$0.00" readonly tabindex="-1">
                                        </td>
                                        <td>
                                            <input type="text"
                                                   class="form-control form-control-sm border-0 bg-transparent item-total text-end fw-bold"
                                                   value="$0.00" readonly tabindex="-1">
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-link text-danger p-0 remove-item-btn" title="Eliminar">
                                                <i class="ti ti-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                    @error('items') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    @error('items.*') <div class="text-danger small mt-1">{{ $message }}</div> @enderror

                    {{-- ADJUNTOS COMPACTOS --}}
                    <div class="row g-2 mt-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Cotización <span class="text-danger">*</span></label>
                            <input type="file" name="quotation_file" id="quotation_file" class="form-control form-control-sm @error('quotation_file') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png" required>
                            <small class="text-muted">PDF, JPG o PNG (máx. 5MB)</small>
                            @error('quotation_file') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Otros Anexos</label>
                            <input type="file" name="support_documents[]" id="support_documents" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" multiple>
                            <small class="text-muted">Máximo 5 archivos</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- RESUMEN LATERAL --}}
        <div class="col-lg-3">
            <div class="card shadow-none border">
                <div class="card-header bg-light py-2">
                    <h6 class="card-title mb-0 small fw-bold">Resumen</h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0 small">
                        <tbody>
                            <tr>
                                <td class="ps-3 border-0">Subtotal</td>
                                <td class="text-end pe-3 border-0" id="summary-subtotal">$0.00</td>
                            </tr>
                            <tr>
                                <td class="ps-3">IVA</td>
                                <td class="text-end pe-3" id="summary-iva">$0.00</td>
                            </tr>
                            <tr class="fw-bold">
                                <td class="ps-3">TOTAL</td>
                                <td class="text-end pe-3 text-primary fs-6" id="summary-total">$0.00</td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="px-3 py-2 bg-light border-top">
                        <small class="text-muted">
                            <i class="ti ti-alert-circle me-1"></i>Límite: <strong>$250,000</strong>
                        </small>
                    </div>
                </div>
                <div class="card-footer p-2 bg-transparent border-top-0">
                    <button type="submit" class="btn btn-primary btn-sm w-100 mb-1">
                        <i class="ti ti-device-floppy me-1"></i>Guardar y enviar a aprobación
                    </button>
                    <a href="{{ route('purchase-orders.index') }}" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="ti ti-x me-1"></i>Cancelar
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('styles')
<style>
    /* ==================================================
       SELECT2 CON INPUT-GROUP - CORRECCIÓN DE LAYOUT
       ================================================== */

    .input-group-select2 {
        position: relative;
        display: flex;
        flex-wrap: nowrap;
        align-items: stretch;
        width: 100%;
    }

    .input-group-select2 .input-group-text {
        display: flex;
        align-items: center;
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
        font-weight: 400;
        line-height: 1.5;
        color: #495057;
        text-align: center;
        white-space: nowrap;
        background-color: #e9ecef;
        border: 1px solid #ced4da;
        border-radius: 0.2rem 0 0 0.2rem;
    }

    .input-group-select2 .form-select {
        display: none;
    }

    .input-group-select2 .select2-container {
        flex: 1 1 auto;
        width: 1% !important;
    }

    .input-group-select2 .select2-container .select2-selection--single {
        height: calc(1.5em + 0.5rem + 2px) !important;
        border-radius: 0 0.2rem 0.2rem 0 !important;
        border-left: 0 !important;
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }

    .input-group-select2 .select2-container .select2-selection__rendered {
        line-height: calc(1.5em + 0.5rem) !important;
        padding-left: 0.5rem !important;
        padding-right: 1.5rem !important;
    }

    .input-group-select2 .select2-container .select2-selection__arrow {
        height: calc(1.5em + 0.5rem) !important;
        top: 1px !important;
        right: 1px !important;
    }

    .input-group-select2 .select2-container .select2-selection__placeholder {
        color: #6c757d;
        font-size: 0.875rem;
    }

    .input-group-select2 .select2-container--focus .select2-selection--single,
    .input-group-select2 .select2-container--open .select2-selection--single {
        border-color: #80bdff !important;
        outline: 0;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    }

    .input-group-select2 .select2-container.is-invalid .select2-selection--single {
        border-color: #dc3545 !important;
    }

    .select2-container--bootstrap-5 .select2-dropdown {
        border-color: #ced4da;
        font-size: 0.875rem;
    }

    .select2-container--bootstrap-5 .select2-results__option {
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
    }

    /* Select2 dentro de tabla (IVA y Categoría) */
    .table .select2-container .select2-selection--single {
        border: 0 !important;
        background: transparent !important;
        height: auto !important;
        padding: 0.25rem 0.5rem;
    }

    .table .select2-container .select2-selection__rendered {
        padding-left: 0.25rem !important;
        padding-right: 1.25rem !important;
        line-height: 1.5 !important;
    }

    .table .select2-container .select2-selection__arrow {
        height: 100% !important;
        top: 0 !important;
    }
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function() {
    const costCenterCatalog = @json($costCenterCatalog);

    $('#supplier_id, #company_id, #purchase_type, #receiving_location_id').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Seleccione...',
        allowClear: true,
        language: {
            noResults: function() { return "No se encontraron resultados"; },
            searching: function() { return "Buscando..."; }
        }
    });

    $('#payment_terms').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Seleccione...',
        allowClear: true,
        minimumResultsForSearch: Infinity,
        language: {
            noResults: function() { return "No se encontraron resultados"; }
        }
    });

    // Select2 solo para IVA en la primera fila (categorías inician deshabilitadas)
    initializeIvaSelect($('.item-iva-rate').first());

    function initializeIvaSelect(element) {
        element.select2({
            theme: 'bootstrap-5',
            width: '100%',
            minimumResultsForSearch: Infinity,
            dropdownParent: element.closest('td')
        });
    }

    function initializeCategorySelect(element) {
        element.select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: 'Categoría...',
            minimumResultsForSearch: 5,
            dropdownParent: element.closest('td'),
            language: {
                noResults: function() { return "Sin resultados"; },
                searching: function() { return "Buscando..."; }
            }
        });
    }

    function initializeCostCenterSelect(element) {
        element.select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: 'Centro de costo...',
            minimumResultsForSearch: 5,
            dropdownParent: element.closest('td'),
            language: {
                noResults: function() { return "Sin resultados"; },
                searching: function() { return "Buscando..."; }
            }
        });
    }

    function getFilteredCostCenters() {
        const companyId = $('#company_id').val();
        const purchaseType = $('#purchase_type').val();

        if (!companyId || !purchaseType) {
            return [];
        }

        return costCenterCatalog.filter(cc =>
            String(cc.company_id) === String(companyId)
            && String(cc.purchase_type) === String(purchaseType)
        );
    }

    function buildCostCenterOptions(selectedValue = '') {
        const options = getFilteredCostCenters();

        if (!options.length) {
            return {
                html: '<option value="">Sin centros de costo disponibles</option>',
                disabled: true
            };
        }

        let html = '<option value="">Seleccione...</option>';
        options.forEach(function(option) {
            const selected = String(selectedValue) === String(option.id) ? ' selected' : '';
            html += `<option value="${option.id}"${selected}>${option.label}</option>`;
        });

        return { html, disabled: false };
    }

    function resetCategorySelect(row, message = 'Seleccione CC primero...') {
        const select = row.find('.item-expense-category');
        if (select.hasClass('select2-hidden-accessible')) {
            select.select2('destroy');
        }
        select.html(`<option value="">${message}</option>`).prop('disabled', true);
    }

    function loadCategoriesForRow(row, costCenterId, selectedValue = null, silent = false) {
        const select = row.find('.item-expense-category');

        if (!costCenterId) {
            resetCategorySelect(row);
            return;
        }

        if (select.hasClass('select2-hidden-accessible')) {
            select.select2('destroy');
        }
        select.prop('disabled', true).html('<option value="">Cargando...</option>');

        $.ajax({
            url: "{{ route('direct-purchase-orders.categories') }}",
            method: 'GET',
            data: { cost_center_id: costCenterId },
            success: function(response) {
                if (response.success && response.categories.length > 0) {
                    let options = '<option value="">Seleccione...</option>';
                    response.categories.forEach(function(cat) {
                        options += `<option value="${cat.id}">${cat.name}</option>`;
                    });
                    select.html(options).prop('disabled', false);
                    if (selectedValue) {
                        select.val(String(selectedValue));
                    }
                    initializeCategorySelect(select);
                } else {
                    resetCategorySelect(row, 'Sin categorías disponibles');
                    if (!silent) {
                        Swal.fire({
                        icon: 'warning',
                        title: 'Sin categorías de gasto',
                        text: response.message || 'El centro de costo seleccionado no tiene categorías de gasto configuradas para el año actual.',
                        confirmButtonText: 'Entendido'
                        });
                    }
                }
            },
            error: function() {
                resetCategorySelect(row, 'Error al cargar');
                if (!silent) {
                    Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Ocurrió un error al consultar las categorías de gasto.',
                    confirmButtonText: 'Cerrar'
                    });
                }
            }
        });
    }

    function refreshItemCostCenters() {
        const options = buildCostCenterOptions();
        const hasFilters = !!($('#company_id').val() && $('#purchase_type').val());

        $('.item-row').each(function() {
            const row = $(this);
            const select = row.find('.item-cost-center');
            const selectedValue = select.val() || select.data('selected') || '';

            if (select.hasClass('select2-hidden-accessible')) {
                select.select2('destroy');
            }

            select.html(options.html).prop('disabled', options.disabled || !hasFilters);

            if (selectedValue && select.find(`option[value="${selectedValue}"]`).length) {
                select.val(String(selectedValue));
            } else {
                select.val('');
            }

            initializeCostCenterSelect(select);

            const effectiveCostCenterId = select.val();
            const categorySelected = row.find('.item-expense-category').data('selected') || row.find('.item-expense-category').val();
            loadCategoriesForRow(row, effectiveCostCenterId, categorySelected, true);

            select.removeData('selected');
            row.find('.item-expense-category').removeData('selected');
        });
    }

    $('#company_id, #purchase_type').on('change', refreshItemCostCenters);

    $(document).on('change', '.item-cost-center', function() {
        const row = $(this).closest('tr');
        loadCategoriesForRow(row, $(this).val(), null, false);
    });

    // ============================================
    // CONTADOR DE CARACTERES
    // ============================================
    $('#justification').on('input', function() {
        const count = $(this).val().length;
        $('#char-count').text(count).toggleClass('text-danger', count < 100).toggleClass('text-success', count >= 100);
    }).trigger('input');

    // ============================================
    // AUTOCOMPLETAR CONDICIONES DEL PROVEEDOR
    // ============================================
    $('#supplier_id').on('change', function() {
        const opt = $(this).find('option:selected');
        const paymentTerms = opt.data('payment-terms');
        const deliveryDays = opt.data('delivery-days');

        if (!$('#payment_terms').val() && paymentTerms) {
            $('#payment_terms').val(paymentTerms).trigger('change');
        }
        if (!$('#estimated_delivery_days').val() && deliveryDays) {
            $('#estimated_delivery_days').val(deliveryDays);
        }
    });

    // ============================================
    // CÁLCULOS DE MONTOS
    // ============================================

    $(document).on('input change', '.item-quantity, .item-unit-price, .item-iva-rate', function() {
        calculateItemRow($(this).closest('tr'));
        calculateTotals();
    });

    function calculateItemRow(row) {
        const qty = parseFloat(row.find('.item-quantity').val()) || 0;
        const price = parseFloat(row.find('.item-unit-price').val()) || 0;
        const ivaRate = parseFloat(row.find('.item-iva-rate').val()) || 0;

        const subtotal = qty * price;
        const iva = subtotal * (ivaRate / 100);
        const total = subtotal + iva;

        row.find('.item-subtotal').val('$' + subtotal.toFixed(2));
        row.find('.item-iva').val('$' + iva.toFixed(2));
        row.find('.item-total').val('$' + total.toFixed(2));
    }

    function calculateTotals() {
        let subtotal = 0;
        let ivaTotal = 0;

        $('.item-row').each(function() {
            const qty = parseFloat($(this).find('.item-quantity').val()) || 0;
            const price = parseFloat($(this).find('.item-unit-price').val()) || 0;
            const ivaRate = parseFloat($(this).find('.item-iva-rate').val()) || 0;

            const itemSubtotal = qty * price;
            const itemIva = itemSubtotal * (ivaRate / 100);

            subtotal += itemSubtotal;
            ivaTotal += itemIva;
        });

        const total = subtotal + ivaTotal;

        $('#summary-subtotal').text('$' + subtotal.toFixed(2));
        $('#summary-iva').text('$' + ivaTotal.toFixed(2));
        $('#summary-total').text('$' + total.toFixed(2)).toggleClass('text-danger', total > 250000).toggleClass('text-primary', total <= 250000);
    }

    // ============================================
    // AGREGAR/ELIMINAR PARTIDAS
    // ============================================

    let itemIndex = {{ old('items') ? count(old('items')) : 1 }};

    $('#add-item-btn').on('click', function() {
        const costCenterOptions = buildCostCenterOptions();
        const newRow = `
            <tr class="item-row">
                <td class="ps-2"><input type="text" name="items[${itemIndex}][description]" class="form-control form-control-sm border-0 item-description" placeholder="Descripción" required maxlength="500"></td>
                <td>
                    <select name="items[${itemIndex}][cost_center_id]" class="form-select form-select-sm border-0 item-cost-center" required ${costCenterOptions.disabled ? 'disabled' : ''}>
                        ${costCenterOptions.html}
                    </select>
                </td>
                <td>
                    <select name="items[${itemIndex}][expense_category_id]" class="form-select form-select-sm border-0 item-expense-category" required disabled>
                        <option value="">Seleccione CC primero...</option>
                    </select>
                </td>
                <td><input type="number" name="items[${itemIndex}][quantity]" class="form-control form-control-sm border-0 item-quantity" step="0.01" min="0.01" required></td>
                <td><input type="number" name="items[${itemIndex}][unit_price]" class="form-control form-control-sm border-0 item-unit-price" step="0.01" min="0.01" required></td>
                <td>
                    <select name="items[${itemIndex}][iva_rate]" class="form-select form-select-sm border-0 item-iva-rate" required>
                        <option value="16">16%</option>
                        <option value="8">8%</option>
                        <option value="0">0%</option>
                    </select>
                </td>
                <td><input type="text" class="form-control form-control-sm border-0 bg-transparent item-subtotal text-end" value="$0.00" readonly tabindex="-1"></td>
                <td><input type="text" class="form-control form-control-sm border-0 bg-transparent item-iva text-end" value="$0.00" readonly tabindex="-1"></td>
                <td><input type="text" class="form-control form-control-sm border-0 bg-transparent item-total text-end fw-bold" value="$0.00" readonly tabindex="-1"></td>
                <td class="text-center"><button type="button" class="btn btn-link text-danger p-0 remove-item-btn"><i class="ti ti-trash"></i></button></td>
            </tr>
        `;
        $('#items-tbody').append(newRow);

        const lastRow = $('#items-tbody tr:last-child');
        initializeCostCenterSelect(lastRow.find('.item-cost-center'));
        initializeIvaSelect(lastRow.find('.item-iva-rate'));

        itemIndex++;
    });

    @if(old('items'))
        setTimeout(function() {
            $('.item-iva-rate').each(function() {
                if (!$(this).hasClass('select2-hidden-accessible')) {
                    initializeIvaSelect($(this));
                }
            });
            refreshItemCostCenters();
            $('.item-row').each(function() {
                calculateItemRow($(this));
            });
            calculateTotals();
        }, 100);
    @else
        refreshItemCostCenters();
    @endif

    $(document).on('click', '.remove-item-btn', function() {
        if ($('#items-tbody tr').length > 1) {
            const row = $(this).closest('tr');
            if (row.find('.item-cost-center').hasClass('select2-hidden-accessible')) {
                row.find('.item-cost-center').select2('destroy');
            }
            row.find('.item-iva-rate').select2('destroy');
            if (row.find('.item-expense-category').hasClass('select2-hidden-accessible')) {
                row.find('.item-expense-category').select2('destroy');
            }
            row.remove();
            calculateTotals();
        } else {
            Swal.fire({
                icon: 'warning',
                title: 'Advertencia',
                text: 'Debe haber al menos una partida.',
                confirmButtonText: 'Entendido'
            });
        }
    });

    // ============================================
    // VALIDACIÓN ANTES DE ENVIAR
    // ============================================
    $('#ocd-form').on('submit', function(e) {
        const total = parseFloat($('#summary-total').text().replace(/[$,]/g, ''));

        if (total > 250000) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Total excedido',
                text: 'El total de $' + total.toFixed(2) + ' excede el límite de $250,000 MXN para OCD.',
                confirmButtonText: 'Entendido'
            });
            return false;
        }

        if ($('#justification').val().length < 100) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Justificación incompleta',
                text: 'La justificación debe tener al menos 100 caracteres.',
                confirmButtonText: 'Entendido'
            });
            return false;
        }
    });

    calculateTotals();
});
</script>
@endpush
