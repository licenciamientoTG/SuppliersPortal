@extends('layouts.zircos')

@section('title', 'Nueva Requisición')

@section('page.title', 'Nueva Requisición')

@section('page.breadcrumbs')
<li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
<li class="breadcrumb-item"><a href="{{ route('requisitions.index') }}">Requisiciones</a></li>
<li class="breadcrumb-item active">Nueva</li>
@endsection

@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold mb-0">
            <i class="ti ti-file-plus me-2"></i>Nueva Requisición
        </h4>
    </div>

    {{-- Mensajes de sesión --}}
    @if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="ti ti-check me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    @if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="ti ti-alert-circle me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    {{-- Formulario --}}
    <form action="{{ route('requisitions.store') }}" method="POST" id="requisitionForm">
        @csrf

        {{-- Información General --}}
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="ti ti-info-circle me-2"></i>Información General
                    @if (!empty($requisition->folio))
                        <span class="ms-2 text-primary fw-bold">| Folio: {{ $requisition->folio }}</span>
                    @endif
                </h5>
            </div>
            <div class="card-body">
                {{-- Campo Hidden para el Folio (solo en edición) --}}
                @if (!empty($requisition->folio))
                    <input type="hidden" name="folio" value="{{ $requisition->folio }}">
                @endif

                <div class="row g-3">


                    {{-- Compañía --}}
                    <div class="col-md-3">
                        <label for="company_id" class="form-label">Compañía <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="ti ti-building"></i>
                            </span>
                            <select id="company_id" 
                                    name="company_id"
                                    class="form-select @error('company_id') is-invalid @enderror" 
                                    required
                                    data-url-costcenters="{{ route('requisitions.cost-centers.by-company', ['company' => '__CID__']) }}"
                                    data-selected-cc="{{ $selectedCostCenterId ?? '' }}">
                                <option value="">Seleccionar...</option>
                                @foreach ($companies as $c)
                                    <option value="{{ $c->id }}"
                                        {{ (int) old('company_id', $selectedCompanyId) === (int) $c->id ? 'selected' : '' }}>
                                        {{ $c->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @error('company_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Tipo de compra --}}
                    <div class="col-md-3">
                        <label for="purchase_type" class="form-label">Tipo de compra <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="ti ti-filter"></i>
                            </span>
                            <select id="purchase_type"
                                    name="purchase_type"
                                    class="form-select @error('purchase_type') is-invalid @enderror"
                                    required>
                                <option value="">Seleccionar...</option>
                                @foreach ($purchaseTypes as $purchaseType)
                                    <option value="{{ $purchaseType }}" {{ $selectedPurchaseType === $purchaseType ? 'selected' : '' }}>
                                        {{ $purchaseType }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @error('purchase_type')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Centro de costo --}}
                    <div class="col-md-3">
                        <label for="cost_center_id" class="form-label">Centro de costo <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="ti ti-chart-pie"></i>
                            </span>
                            <select id="cost_center_id" 
                                    name="cost_center_id"
                                    class="form-select @error('cost_center_id') is-invalid @enderror" 
                                    required
                                    disabled>
                                <option value="">Seleccionar compañía y tipo de compra primero</option>
                            </select>
                        </div>
                        @error('cost_center_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Ubicación de recepción --}}
                    <div class="col-md-3">
                        <label for="receiving_location_id" class="form-label">Ubicación de recepción <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="ti ti-map-pin"></i>
                            </span>
                            <select id="receiving_location_id"
                                    name="receiving_location_id"
                                    class="form-select @error('receiving_location_id') is-invalid @enderror"
                                    required>
                                <option value="">Seleccionar...</option>
                                @foreach ($receivingLocations as $location)
                                    <option value="{{ $location->id }}"
                                        {{ (int) old('receiving_location_id', $requisition->receiving_location_id ?? 0) === (int) $location->id ? 'selected' : '' }}>
                                        {{ $location->code ? '['.$location->code.'] ' : '' }}{{ $location->name }}{{ $location->city ? ' - '.$location->city : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @error('receiving_location_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Fecha requerida --}}
                    <div class="col-md-2">
                        <label for="required_date" class="form-label">Fecha requerida</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="ti ti-calendar"></i>
                            </span>
                            <input type="date" 
                                id="required_date" 
                                name="required_date"
                                class="form-control @error('required_date') is-invalid @enderror"
                                value="{{ old('required_date', optional($requisition->required_date)->format('Y-m-d') ?: now()->addWeeks(2)->format('Y-m-d')) }}">
                        </div>
                        @error('required_date')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Descripción --}}
                    <div class="col-md-10">
                        <label for="description" class="form-label">Descripción</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="ti ti-file-text"></i>
                            </span>
                            <input type="text" 
                                id="description" 
                                name="description"
                                class="form-control @error('description') is-invalid @enderror"
                                value="{{ old('description', $requisition->description ?? '') }}"
                                placeholder="Ej: Compra de equipo..." 
                                maxlength="500">
                        </div>
                        @error('description')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- TABLA DE PARTIDAS --}}
        <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="ti ti-list me-2"></i>Productos/Servicios
                </h5>
                <button type="button" class="btn btn-sm btn-primary" id="btnAddItem">
                    <i class="ti ti-plus me-1"></i> Agregar Partida
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover" id="itemsTable">
                        <thead class="table-light">
                            <tr>
                                <th width="40">#</th>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Unidad</th>
                                <th>Categoría de gasto</th>
                                <th>Subcategoría presupuestal</th>
                                <th>Notas</th>
                                <th width="100">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="itemsTableBody">
                            <tr id="emptyRow">
                                <td colspan="9" class="text-center text-muted py-4">
                                    <i class="ti ti-inbox fs-1 d-block mb-2"></i>
                                    No hay partidas agregadas. Haz clic en "Agregar Partida" 1
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Inputs hidden para enviar ítems al servidor --}}
        <div id="hiddenItemsContainer"></div>

        {{-- Botones --}}
        <div class="d-flex justify-content-between mt-3">
            <a href="{{ route('requisitions.index') }}" class="btn btn-outline-secondary">
                <i class="ti ti-x me-1"></i>Cancelar
            </a>
            <div class="d-flex gap-2">
                <button type="submit" name="submit_action" value="draft" class="btn btn-outline-primary">
                    <i class="ti ti-device-floppy me-1"></i>Guardar como Borrador
                </button>
                <button type="submit" name="submit_action" value="submit" class="btn btn-primary">
                    <i class="ti ti-send me-1"></i>Enviar a Compras (RN-005)
                </button>
            </div>
        </div>
    </form>
</div>

{{-- Modal para agregar/editar partidas --}}
<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="itemModalTitle">Agregar Partida</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="itemForm" class="needs-validation" novalidate>
                    <input type="hidden" id="item_index">

                    {{-- Categoría de gasto --}}
                    <div class="mb-3">
                        <label for="modal_expense_category" class="form-label fw-semibold">
                            Categoría de Gasto <span class="text-danger">*</span>
                        </label>
                        <select id="modal_expense_category" class="form-select" required>
                            <option value="">Seleccionar categoría...</option>
                        </select>
                        <div class="form-text text-danger">
                            <i class="ti ti-alert-circle me-1"></i>RN-010A: Campo obligatorio
                        </div>
                    </div>

                    {{-- Subcategoría presupuestal --}}
                    <div class="mb-3">
                        <label for="modal_budget_cedula" class="form-label fw-semibold">
                            Subcategoría Presupuestal <span class="text-danger">*</span>
                        </label>
                        <select id="modal_budget_cedula" class="form-select" required disabled>
                            <option value="">Selecciona primero una categoría de gasto...</option>
                        </select>
                        <div class="form-text">
                            La cédula disponible depende del centro de costo, la categoría y el ejercicio fiscal.
                        </div>
                    </div>

                    {{-- Producto del catálogo --}}
                    <div class="mb-4">
                        <div class="mb-3">
                            <label for="modal_product_id" class="form-label fw-semibold">
                                Producto del catálogo <span class="text-danger">*</span>
                            </label>
                            <select id="modal_product_id" class="form-select" required>
                                <option value="">Buscar producto del catálogo...</option>
                            </select>
                            <div class="form-text">
                                <i class="ti ti-info-circle me-1"></i>RN-001: Solo productos del catálogo
                            </div>
                            {{-- Info del producto seleccionado --}}
                            <div id="product_info" class="alert alert-light border mt-2" style="display:none;">
                                <div class="d-flex gap-3 align-items-center flex-wrap">
                                    <span id="product_type_badge"></span>
                                    <span id="product_code_display"></span>
                                    <span id="product_brand_model" style="display:none;"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Descripción completa --}}
                    <div class="mb-3">
                        <label for="modal_description" class="form-label fw-semibold">Descripción</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">
                                <i class="ti ti-align-left"></i>
                            </span>
                            <textarea id="modal_description" class="form-control bg-light"
                                rows="2" style="resize: none;" readonly></textarea>
                        </div>
                    </div>

                    {{-- Cantidad y Unidad --}}
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="modal_quantity" class="form-label fw-semibold">
                                Cantidad <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="ti ti-numbers"></i>
                                </span>
                                <input type="number" id="modal_quantity" class="form-control"
                                    min="0.001" step="0.001" value="1" required>
                            </div>
                            <div class="form-text">Mínimo: 0.001</div>
                        </div>

                        <div class="col-md-6">
                            <label for="modal_unit" class="form-label fw-semibold">Unidad de Medida</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="ti ti-ruler-measure"></i>
                                </span>
                                <input type="text" id="modal_unit" class="form-control bg-light" readonly>
                            </div>
                        </div>
                    </div>

                    {{-- Observaciones --}}
                    <div class="mb-3">
                        <label for="modal_notes" class="form-label fw-semibold">Observaciones</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="ti ti-notes"></i>
                            </span>
                            <textarea id="modal_notes" class="form-control" rows="3"
                                placeholder="Especificaciones adicionales, requisitos especiales, información de contacto, etc."></textarea>
                        </div>
                    </div>

                    {{-- Alerta de presupuesto --}}
                    <div class="alert alert-info d-flex align-items-center" id="budgetAlert" style="display:none;">
                        <i class="ti ti-info-circle fs-4 me-3"></i>
                        <div>
                            <strong class="d-block mb-1">Información de Presupuesto</strong>
                            <span id="budgetMessage" class="small"></span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="ti ti-x me-1"></i>Cancelar
                </button>
                <button type="button" class="btn btn-primary" id="btnSaveItem">
                    <i class="ti ti-check me-1"></i>Guardar Partida
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
    .input-group-text {
        background-color: #f8f9fa;
        border-right: 0;
    }

    .input-group > .select2-container {
        flex: 1 1 auto;
        width: 1% !important;
    }

    .input-group > .select2-container .select2-selection--single {
        height: calc(1.5em + 0.75rem + 2px) !important;
        border-top-left-radius: 0 !important;
        border-bottom-left-radius: 0 !important;
        border-left: 0 !important;
    }

    .input-group > .select2-container .select2-selection__rendered {
        line-height: calc(1.5em + 0.75rem) !important;
        padding-left: 0.75rem !important;
    }

    .input-group > .select2-container .select2-selection__arrow {
        height: calc(1.5em + 0.75rem) !important;
    }

    .form-control:focus+.input-group-text,
    .form-select:focus~.input-group-text {
        border-color: #86b7fe;
        background-color: #fff;
    }

    .modal-footer {
        border-top: 1px solid #dee2e6;
    }

    /* Mejorar visualización del textarea de descripción */
    #modal_description {
        font-size: 0.95rem;
        line-height: 1.4;
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    $(function() {
        'use strict';

        // =====================================================
        // VARIABLES GLOBALES
        // =====================================================
        let itemsArray = @json($initialItems ?? []); // Array principal de ítems
        let editingIndex = null; // Índice del ítem en edición

        // =====================================================
        // 1. GESTIÓN DE COMPAÑÍA Y CENTROS DE COSTO
        // =====================================================
        const $company = $('#company_id');
        const $purchaseType = $('#purchase_type');
        const $cc = $('#cost_center_id');
        const $expenseCategory = $('#modal_expense_category');
        const $budgetCedula = $('#modal_budget_cedula');

        function initSearchableSelect($element, placeholder, options = {}) {
            if ($element.data('select2')) {
                $element.select2('destroy');
            }

            $element.select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder,
                allowClear: true,
                language: {
                    noResults: function() { return 'No se encontraron resultados'; },
                    searching: function() { return 'Buscando...'; }
                },
                ...options
            });
        }

        initSearchableSelect($company, 'Buscar compañía...');
        initSearchableSelect($purchaseType, 'Buscar tipo de compra...');
        initSearchableSelect($cc, 'Buscar centro de costo...');
        initSearchableSelect($('#receiving_location_id'), 'Buscar ubicación de recepción...');
        initSearchableSelect($expenseCategory, 'Buscar categoría de gasto...', {
            dropdownParent: $('#itemModal')
        });

        function loadCostCenters(companyId, purchaseType) {
            if (!companyId || !purchaseType) {
                $cc.prop('disabled', true)
                    .empty()
                    .append('<option value="">Seleccionar compañía y tipo de compra primero</option>');
                initSearchableSelect($cc, 'Buscar centro de costo...');
                return;
            }

            $cc.prop('disabled', true).empty().append('<option value="">Cargando...</option>');
            initSearchableSelect($cc, 'Buscar centro de costo...');

            const url = $company.data('url-costcenters').replace('__CID__', companyId);

            $.getJSON(url, { purchase_type: purchaseType })
                .done(function(data) {
                    $cc.empty().append('<option value="">Seleccionar centro de costo</option>');

                    data.forEach(row => {
                        $cc.append($('<option>', {
                            value: row.id,
                            text: row.code ? `[${row.code}] ${row.name}` : row.name
                        }));
                    });

                    const wanted = $company.data('selected-cc');
                    if (wanted) $cc.val(wanted);
                })
                .fail(function() {
                    Swal.fire('Error', 'No se pudieron cargar los centros de costo.', 'error');
                })
                .always(() => {
                    $cc.prop('disabled', false);
                    initSearchableSelect($cc, 'Buscar centro de costo...');

                    if ($cc.val()) {
                        $cc.trigger('change');
                    }
                });
        }

        $company.on('change', function() {
            $company.data('selected-cc', '');
            loadCostCenters($(this).val(), $purchaseType.val());
        });

        $purchaseType.on('change', function() {
            $company.data('selected-cc', '');
            loadCostCenters($company.val(), $(this).val());
        });

        // Cargar centros de costo iniciales
        const initialCompany = $company.val();
        const initialPurchaseType = $purchaseType.val();
        if (initialCompany && initialPurchaseType) {
            loadCostCenters(initialCompany, initialPurchaseType);
        }

        // =====================================================
        // 2. ABRIR MODAL PARA AGREGAR/EDITAR
        // =====================================================
        $('#btnAddItem').on('click', function() {
            const companyId = $('#company_id').val();
            const costCenterId = $('#cost_center_id').val();

            if (!companyId || !costCenterId) {
                Swal.fire('Datos incompletos', 'Primero selecciona compañía y centro de costo.', 'warning');
                return;
            }

            openItemModal();
        });

        function openItemModal(index = null) {
            editingIndex = index;

            // Actualizar título
            $('#itemModalTitle').text(index !== null ? 'Editar Partida' : 'Agregar Partida');

            // Resetear formulario
            document.getElementById('itemForm').reset();
            $('#item_index').val(index !== null ? index : '');
            $('#budgetAlert').hide();
            resetBudgetCedulaSelect();

            // Cargar productos y categorías
            loadProductsForCostCenter();
            loadExpenseCategories();

            // Si es edición, pre-cargar datos
            if (index !== null && itemsArray[index]) {
                const item = itemsArray[index];

                setTimeout(() => {
                    $('#modal_product_id').val(item.product_id).trigger('change');
                    $('#modal_quantity').val(item.quantity);
                    $budgetCedula.data('pending-value', item.budget_cedula_id || null);
                    $('#modal_expense_category').val(item.expense_category_id).trigger('change');
                    $('#modal_notes').val(item.notes || '');
                }, 500);
            }

            $('#itemModal').modal('show');
        }

        // =====================================================
        // 3. CARGAR PRODUCTOS DEL CATÁLOGO
        // =====================================================
        function loadProductsForCostCenter() {
            const companyId = $('#company_id').val();
            const costCenterId = $('#cost_center_id').val();

            $('#modal_product_id').prop('disabled', true).empty().append('<option value="">Cargando...</option>');

            $.ajax({
                url: '{{ route("products-services.api.active-for-requisitions") }}',
                method: 'GET',
                data: {
                    company_id: companyId,
                    cost_center_id: costCenterId,
                    all: true
                },
                success: function(response) {
                    $('#modal_product_id').empty().append('<option value="">Buscar producto...</option>');

                    if (response.products && response.products.length > 0) {
                        response.products.forEach(function(product) {
                            const $option = $('<option>', {
                                value: product.id,
                                text: `[${product.code}] ${product.short_name || product.description.substring(0, 50)}`,
                                'data-code': product.code,
                                'data-description': product.description,
                                'data-unit': product.unit_of_measure || 'PZA',
                                'data-suggested-vendor': product.default_vendor_name || 'Sin proveedor',
                                'data-min-qty': product.minimum_quantity || '',
                                'data-max-qty': product.maximum_quantity || '',
                                'data-brand': product.brand || '',
                                'data-model': product.model || '',
                                'data-type': product.product_type || 'PRODUCTO'
                            });
                            $('#modal_product_id').append($option);
                        });

                        initializeProductSelect2();
                    }
                },
                error: function(xhr) {
                    Swal.fire('Error', xhr.responseJSON?.message || 'No se pudieron cargar los productos del catálogo.', 'error');
                },
                complete: function() {
                    $('#modal_product_id').prop('disabled', false);
                }
            });
        }

        // =====================================================
        // 4. INICIALIZAR SELECT2
        // =====================================================
        function initializeProductSelect2() {
            if ($('#modal_product_id').data('select2')) {
                $('#modal_product_id').select2('destroy');
            }

            $('#modal_product_id').select2({
                dropdownParent: $('#itemModal'),
                placeholder: 'Buscar producto...',
                allowClear: true,
                width: '100%'
            });

            $('#modal_product_id').on('select2:select', function(e) {
                const $option = $(e.params.data.element);

                // Auto-llenar campos heredados del catálogo
                $('#modal_description').val($option.data('description') || '');
                $('#modal_unit').val($option.data('unit') || 'PZA');
                $('#modal_suggested_vendor').val($option.data('suggested-vendor') || 'Sin proveedor sugerido');

                // Actualizar texto de ayuda con min/max
                const minQty = $option.data('min-qty');
                const maxQty = $option.data('max-qty');
                const unit = $option.data('unit') || 'PZA';

                let helpText = 'Mínimo: 0.001';
                if (minQty) {
                    helpText = `Mínimo: ${minQty} ${unit}`;
                }
                if (maxQty) {
                    helpText += ` | Máximo: ${maxQty} ${unit}`;
                }

                $('#modal_quantity').siblings('.form-text').html(`<i class="ti ti-info-circle me-1"></i>${helpText}`);

                // Mostrar info del producto
                const type = $option.data('type');
                const code = $option.data('code');
                const brand = $option.data('brand');
                const model = $option.data('model');

                // Badge de tipo
                const typeBadge = type === 'SERVICIO' ?
                    '<span class="badge bg-info"><i class="ti ti-briefcase me-1"></i>Servicio</span>' :
                    '<span class="badge bg-primary"><i class="ti ti-box me-1"></i>Producto</span>';
                $('#product_type_badge').html(typeBadge);

                // Código
                $('#product_code_display').html(`<strong>Código:</strong> <code>${code}</code>`);

                // Marca/Modelo (si existe)
                if (brand || model) {
                    const brandModel = [brand, model].filter(Boolean).join(' / ');
                    $('#product_brand_model').html(`<strong>Marca/Modelo:</strong> ${brandModel}`).show();
                } else {
                    $('#product_brand_model').hide();
                }

                // Mostrar el alert
                $('#product_info').show();
            });
        }

        // =====================================================
        // 5. CARGAR CATEGORÍAS DE GASTO
        // =====================================================
        function loadExpenseCategories() {
            const costCenterId = $('#cost_center_id').val();

            if (!costCenterId) {
                $expenseCategory
                    .empty()
                    .append('<option value="">Selecciona primero un centro de costo...</option>');
                initSearchableSelect($expenseCategory, 'Buscar categoría de gasto...', {
                    dropdownParent: $('#itemModal')
                });
                return;
            }

            $expenseCategory.empty().append('<option value="">Cargando...</option>');
        initSearchableSelect($expenseCategory, 'Buscar categorÃ­a de gasto...', {
            dropdownParent: $('#itemModal')
        });
        initSearchableSelect($budgetCedula, 'Buscar subcategoría presupuestal...', {
            dropdownParent: $('#itemModal')
        });

            $.getJSON('{{ route("expense-categories.by-budget") }}', {
                    cost_center_id: costCenterId,
                    fiscal_year: {{ now()->year }}
                })
                .done(function(data) {
                    $('#modal_expense_category').empty().append('<option value="">Seleccionar categoría...</option>');

                    if (data.categories && data.categories.length > 0) {
                        data.categories.forEach(cat => {
                            $expenseCategory.append($('<option>', {
                                value: cat.id,
                                text: cat.name,
                                'data-name': cat.name
                            }));
                        });
                    }
                });
        }

        loadExpenseCategories = function() {
            const costCenterId = $('#cost_center_id').val();

            if (!costCenterId) {
                $expenseCategory
                    .empty()
                    .append('<option value="">Selecciona primero un centro de costo...</option>');
                initSearchableSelect($expenseCategory, 'Buscar categoría de gasto...', {
                    dropdownParent: $('#itemModal')
                });
                return;
            }

            $expenseCategory.empty().append('<option value="">Cargando...</option>');
            initSearchableSelect($expenseCategory, 'Buscar categoría de gasto...', {
                dropdownParent: $('#itemModal')
            });

            $.getJSON('{{ route("expense-categories.by-budget") }}', {
                    cost_center_id: costCenterId,
                    fiscal_year: {{ now()->year }}
                })
                .done(function(data) {
                    $expenseCategory.empty().append('<option value="">Seleccionar categoría...</option>');

                    if (data.categories && data.categories.length > 0) {
                        data.categories.forEach(cat => {
                            $expenseCategory.append($('<option>', {
                                value: cat.id,
                                text: cat.name,
                                'data-name': cat.name
                            }));
                        });
                    }
                })
                .fail(function(xhr) {
                    const message = xhr.responseJSON?.message || 'No se pudieron cargar las categorías de gasto.';
                    $expenseCategory.empty().append(`<option value="">${message}</option>`);
                    Swal.fire('Error', message, 'error');
                })
                .always(function() {
                    initSearchableSelect($expenseCategory, 'Buscar categoría de gasto...', {
                        dropdownParent: $('#itemModal')
                    });
                });
        }

        function resetBudgetCedulaSelect(message = 'Selecciona primero una categoría de gasto...') {
            $budgetCedula
                .val(null)
                .data('pending-value', null)
                .empty()
                .append(`<option value="">${message}</option>`)
                .prop('disabled', true);

            initSearchableSelect($budgetCedula, 'Buscar subcategoría presupuestal...', {
                dropdownParent: $('#itemModal')
            });
        }

        function loadBudgetCedulas() {
            const costCenterId = $('#cost_center_id').val();
            const categoryId = $('#modal_expense_category').val();

            if (!costCenterId || !categoryId) {
                resetBudgetCedulaSelect();
                return;
            }

            $budgetCedula
                .prop('disabled', true)
                .empty()
                .append('<option value="">Cargando subcategorías...</option>');

            initSearchableSelect($budgetCedula, 'Buscar subcategoría presupuestal...', {
                dropdownParent: $('#itemModal')
            });

            $.getJSON('{{ route("expense-categories.cedulas-by-cost-center") }}', {
                cost_center_id: costCenterId,
                expense_category_id: categoryId,
                fiscal_year: {{ now()->year }}
            }).done(function(response) {
                $budgetCedula.empty().append('<option value="">Seleccionar subcategoría...</option>');

                if (response.success && response.cedulas && response.cedulas.length > 0) {
                    response.cedulas.forEach(cedula => {
                        $budgetCedula.append($('<option>', {
                            value: cedula.id,
                            text: cedula.name
                        }));
                    });

                    $budgetCedula.prop('disabled', false);
                    initSearchableSelect($budgetCedula, 'Buscar subcategoría presupuestal...', {
                        dropdownParent: $('#itemModal')
                    });

                    const pendingValue = $budgetCedula.data('pending-value');
                    if (pendingValue) {
                        $budgetCedula.val(String(pendingValue)).trigger('change');
                        $budgetCedula.data('pending-value', null);
                    }
                    return;
                }

                resetBudgetCedulaSelect('No hay subcategorías configuradas para esta categoría.');
            }).fail(function() {
                resetBudgetCedulaSelect('No se pudieron cargar las subcategorías.');
            });
        }

        $expenseCategory.on('change select2:select', function() {
            loadBudgetCedulas();
        });

        $cc.on('change', function() {
            resetBudgetCedulaSelect();
        });

        // =====================================================
        // 6. GUARDAR PARTIDA
        // =====================================================
        $('#btnSaveItem').on('click', function() {
            const productId = $('#modal_product_id').val();
            const quantity = parseFloat($('#modal_quantity').val());
            const categoryId = $('#modal_expense_category').val();
            const budgetCedulaId = $('#modal_budget_cedula').val();
            const costCenterId = $('#cost_center_id').val();
            const costCenterName = $('#cost_center_id option:selected').text();
            const purchaseType = $('#purchase_type').val();

            if (!budgetCedulaId) {
                Swal.fire('Error', 'Selecciona una subcategoría presupuestal.', 'error');
                return;
            }

            if (!costCenterId) {
                Swal.fire('Error', 'Selecciona un centro de costo antes de agregar la partida.', 'error');
                return;
            }

            // Validaciones
            if (!productId) {
                Swal.fire('Error', 'Selecciona un producto del catálogo (RN-001).', 'error');
                return;
            }

            if (!quantity || quantity <= 0) {
                Swal.fire('Error', 'La cantidad debe ser mayor a cero.', 'error');
                return;
            }

            if (!categoryId) {
                Swal.fire('Error', 'Selecciona una categoría de gasto (RN-010A).', 'error');
                return;
            }

            // Validar cantidad mínima y máxima
            const $selectedProduct = $('#modal_product_id option:selected');
            const minQty = parseFloat($selectedProduct.data('min-qty'));
            const maxQty = parseFloat($selectedProduct.data('max-qty'));
            const unit = $selectedProduct.data('unit') || 'PZA';

            // Validar mínimo
            if (minQty && quantity < minQty) {
                Swal.fire({
                    icon: 'error',
                    title: 'Cantidad insuficiente',
                    text: `La cantidad mínima para este producto es ${minQty} ${unit}`
                });
                return;
            }

            // Validar máximo
            if (maxQty && quantity > maxQty) {
                Swal.fire({
                    icon: 'error',
                    title: 'Cantidad excedida',
                    text: `La cantidad máxima permitida es ${maxQty} ${unit}`
                });
                return;
            }

            // Construir objeto de partida (SIN PRECIOS)
            const item = {
                product_id: productId,
                product_name: $('#modal_product_id option:selected').text(),
                description: $('#modal_description').val(),
                quantity: quantity,
                unit: $('#modal_unit').val(),
                expense_category_id: categoryId,
                expense_category_name: $('#modal_expense_category option:selected').text(),
                budget_cedula_id: budgetCedulaId,
                budget_cedula_name: $('#modal_budget_cedula option:selected').text(),
                cost_center_id: costCenterId,
                cost_center_name: costCenterName,
                purchase_type: purchaseType,
                notes: $('#modal_notes').val() || ''
            };

            // Agregar o actualizar en el array
            if (editingIndex !== null) {
                itemsArray[editingIndex] = item;
            } else {
                itemsArray.push(item);
            }

            refreshTable();
            $('#itemModal').modal('hide');

            Swal.fire({
                icon: 'success',
                title: editingIndex !== null ? 'Partida actualizada' : 'Partida agregada',
                timer: 1500,
                showConfirmButton: false
            });
        });

        // =====================================================
        // 7. EDITAR PARTIDA
        // =====================================================
        $(document).on('click', '.btn-edit-item', function() {
            const index = parseInt($(this).data('index'));
            openItemModal(index);
        });

        // =====================================================
        // 8. ELIMINAR PARTIDA
        // =====================================================
        $(document).on('click', '.btn-delete-item', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const index = parseInt($(this).data('index'));
            
            console.log('Intentando eliminar índice:', index);
            
            // Verificar que SweetAlert2 esté disponible
            if (typeof Swal === 'undefined') {
                itemsArray.splice(index, 1);
                refreshTable();
                return;
            }
            
            Swal.fire({
                title: '¿Eliminar partida?',
                text: 'Esta acción no se puede deshacer.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                buttonsStyling: true
            }).then((result) => {
                if (result.isConfirmed) {
                    console.log('Eliminando partida en índice:', index);
                    
                    // Eliminar del array
                    itemsArray.splice(index, 1);
                    
                    // Refrescar tabla
                    refreshTable();
                    
                    // Mostrar mensaje de éxito
                    Swal.fire({
                        icon: 'success',
                        title: 'Partida eliminada',
                        text: 'La partida ha sido eliminada correctamente',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            }).catch((error) => {
                console.error('Error en SweetAlert:', error);
                Swal.fire({ icon: 'error', title: 'Error', text: 'Error al eliminar. Por favor, intenta nuevamente.' });
            });
        });

        // =====================================================
        // 9. REFRESCAR TABLA
        // =====================================================
        function refreshTable() {
            const tbody = $('#itemsTableBody');
            const emptyRow = $('#emptyRow');

            // Eliminar todas las filas (excepto emptyRow)
            tbody.find('tr:not(#emptyRow)').remove();

            if (itemsArray.length === 0) {
                emptyRow.show();
            } else {
                emptyRow.hide();

                itemsArray.forEach((item, index) => {
                    const row = `
                        <tr>
                            <td>${index + 1}</td>
                            <td><strong>${item.product_name}</strong></td>
                            <td>${item.quantity}</td>
                            <td>${item.unit}</td>
                            <td><span class="badge bg-info">${item.expense_category_name}</span></td>
                            <td>${item.budget_cedula_name || '-'}</td>
                            <td>${item.notes ? '<i class="ti ti-note"></i>' : '—'}</td>
                            <td class="text-nowrap">
                                <button type="button" class="btn btn-sm btn-warning btn-edit-item" data-index="${index}">
                                    <i class="ti ti-edit"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-danger btn-delete-item" data-index="${index}">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                    tbody.append(row);
                });
            }

            updateHiddenInputs();
            
            console.log('Tabla refrescada. Items en array:', itemsArray.length);
        }

        // =====================================================
        // 10. INPUTS HIDDEN PARA ENVIAR AL SERVIDOR
        // =====================================================
        function escapeAttribute(value) {
            return $('<div>').text(value ?? '').html();
        }

        function updateHiddenInputs() {
            const container = $('#hiddenItemsContainer');
            container.empty();

            itemsArray.forEach((item, index) => {
                const idInput = item.id ? `<input type="hidden" name="items[${index}][id]" value="${escapeAttribute(item.id)}">` : '';

                container.append(`
                    ${idInput}
                    <input type="hidden" name="items[${index}][product_service_id]" value="${escapeAttribute(item.product_id)}">
                    <input type="hidden" name="items[${index}][description]" value="${escapeAttribute(item.description)}">
                    <input type="hidden" name="items[${index}][unit]" value="${escapeAttribute(item.unit)}">
                    <input type="hidden" name="items[${index}][expense_category_id]" value="${escapeAttribute(item.expense_category_id)}">
                    <input type="hidden" name="items[${index}][budget_cedula_id]" value="${escapeAttribute(item.budget_cedula_id)}">
                    <input type="hidden" name="items[${index}][cost_center_id]" value="${escapeAttribute(item.cost_center_id)}">
                    <input type="hidden" name="items[${index}][quantity]" value="${escapeAttribute(item.quantity)}">
                    <input type="hidden" name="items[${index}][notes]" value="${escapeAttribute(item.notes)}">
                `);
            });
        }

        // =====================================================
        // 11. VALIDACIÓN ANTES DE SUBMIT
        // =====================================================
        let formIsSubmitting = false;
        let clickedAction = null;

        // Recordar qué botón se presionó (los botones deshabilitados no envían su value)
        $('#requisitionForm button[type="submit"]').on('click', function() {
            clickedAction = $(this).val();
        });

        $('#requisitionForm').on('submit', function(e) {
            if (itemsArray.length === 0) {
                e.preventDefault();
                Swal.fire('Requisición vacía', 'Debes agregar al menos una partida (RN-003).', 'error');
                return false;
            }

            // Prevenir doble envío / doble click (requisiciones duplicadas)
            if (formIsSubmitting) {
                e.preventDefault();
                return false;
            }
            formIsSubmitting = true;

            const $buttons = $('#requisitionForm button[type="submit"]');

            // Conservar la acción del botón presionado como input oculto,
            // ya que al deshabilitar los botones no se envía su value.
            if (clickedAction) {
                $('<input>')
                    .attr({ type: 'hidden', name: 'submit_action', value: clickedAction })
                    .appendTo(this);
            }

            // Deshabilitar ambos botones y mostrar spinner en el presionado
            $buttons.prop('disabled', true);
            $buttons.filter(`[value="${clickedAction}"]`)
                .html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Procesando...');
        });

        refreshTable();
    });
</script>
@endpush
