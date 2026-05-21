<div>
    {{-- Mensajes de sesión --}}
    @if (session()->has('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="ti ti-check me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    @if (session()->has('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="ti ti-alert-circle me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    {{-- Formulario --}}
    <form wire:submit.prevent="submit">

        {{-- Información General --}}
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="ti ti-info-circle me-2"></i>
                    {{ $isEditMode ? 'Editar Requisición' : 'Nueva Requisición' }}
                    @if (!empty($folio))
                        <span class="ms-2 text-primary fw-bold">| Folio: {{ $folio }}</span>
                    @endif
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3">

                    {{-- Compañía --}}
                    <div class="col-md-2">
                        <label for="company_id" class="form-label">Compañía <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="ti ti-building"></i>
                            </span>
                            <select wire:model.live="company_id" id="company_id" class="form-select @error('company_id') is-invalid @enderror" 
                                    required>
                                <option value="">Seleccionar...</option>
                                @foreach ($companies as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @error('company_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Tipo de compra --}}
                    <div class="col-md-2">
                        <label for="purchase_type" class="form-label">Tipo de compra <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="ti ti-filter"></i>
                            </span>
                            <select wire:model.live="purchase_type"
                                    id="purchase_type"
                                    class="form-select @error('purchase_type') is-invalid @enderror"
                                    required>
                                <option value="">Seleccionar...</option>
                                @foreach ($purchaseTypes as $purchaseTypeOption)
                                    <option value="{{ $purchaseTypeOption }}">{{ $purchaseTypeOption }}</option>
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
                            <select wire:model.live="cost_center_id" 
                                    id="cost_center_id"
                                    class="form-select @error('cost_center_id') is-invalid @enderror" 
                                    required
                                    {{ empty($company_id) || empty($purchase_type) ? 'disabled' : '' }}>
                                <option value="">
                                    {{ empty($company_id) || empty($purchase_type) ? 'Seleccionar compañía y tipo de compra primero' : 'Seleccionar centro de costo...' }}
                                </option>
                                @foreach ($costCenters as $cc)
                                    <option value="{{ $cc->id }}">
                                        {{ $cc->code ? "[{$cc->code}] {$cc->name}" : $cc->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @error('cost_center_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        
                        {{-- Loading indicator --}}
                        <div wire:loading wire:target="company_id,purchase_type" class="mt-1">
                            <small class="text-muted">
                                <i class="ti ti-loader rotating"></i> Cargando centros de costo...
                            </small>
                        </div>
                    </div>

                    {{-- Ubicación de recepción --}}
                    <div class="col-md-3">
                        <label for="receiving_location_id" class="form-label">Ubicación recepción <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="ti ti-map-pin"></i>
                            </span>
                            <select wire:model.live="receiving_location_id"
                                    id="receiving_location_id"
                                    class="form-select @error('receiving_location_id') is-invalid @enderror"
                                    required>
                                <option value="">Seleccionar...</option>
                                @foreach ($receivingLocations as $loc)
                                    <option value="{{ $loc->id }}">
                                        {{ $loc->name }}{{ $loc->city ? ' — ' . $loc->city : '' }}
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
                                   wire:model.live="required_date"
                                   id="required_date" 
                                   class="form-control @error('required_date') is-invalid @enderror">
                        </div>
                        @error('required_date')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Descripción con contador de caracteres --}}
                    <div class="col-md-3">
                        <label for="description" class="form-label">
                            Descripción
                            <span class="ms-2 badge {{ $descriptionRemainingChars < 50 ? 'bg-danger' : 'bg-secondary' }}">
                                {{ $descriptionRemainingChars }} / {{ $descriptionMaxLength }}
                            </span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="ti ti-file-text"></i>
                            </span>
                            <input type="text" 
                                   wire:model.live="description"
                                   id="description" 
                                   class="form-control @error('description') is-invalid @enderror"
                                   placeholder="Ej: Compra de equipo..." 
                                   maxlength="{{ $descriptionMaxLength }}">
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
                    @if(count($items) > 0)
                        <span class="badge bg-primary ms-2">{{ count($items) }} partida(s)</span>
                    @endif
                </h5>
                <button type="button" class="btn btn-sm btn-primary" id="btnAddItem">
                    <i class="ti ti-plus me-1"></i> Agregar Partida
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th width="40">#</th>
                                <th>Producto</th>
                                <th>Descripción</th>
                                <th>Cantidad</th>
                                <th>Unidad</th>
                                <th>Categoría de gasto</th>
                                <th>Notas</th>
                                <th width="100">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($items as $index => $item)
                                <tr wire:key="item-{{ $index }}">
                                    <td>{{ $index + 1 }}</td>
                                    <td><strong>{{ $item['product_name'] }}</strong></td>
                                    <td data-bs-toggle="tooltip" title="{{ $item['description'] }}">
                                        {{ Str::limit($item['description'], 50) }}
                                    </td>
                                    <td>{{ $item['quantity'] }}</td>
                                    <td>{{ $item['unit'] }}</td>
                                    <td>
                                        <span class="badge bg-info">{{ $item['expense_category_name'] }}</span>
                                    </td>
                                    <td>
                                        @if(!empty($item['notes']))
                                            <span class="text-primary cursor-help" 
                                                  data-bs-toggle="tooltip" 
                                                  title="{{ $item['notes'] }}">
                                                <i class="ti ti-note"></i>
                                                {{ Str::limit($item['notes'], 30) }}
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-nowrap">
                                        <button type="button" 
                                                class="btn btn-sm btn-warning btn-edit-item" 
                                                data-index="{{ $index }}"
                                                title="Editar">
                                            <i class="ti ti-edit"></i>
                                        </button>
                                        <button type="button" 
                                                class="btn btn-sm btn-danger" 
                                                onclick="confirmDeleteItem({{ $index }})"
                                                title="Eliminar">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="ti ti-inbox fs-1 d-block mb-2"></i>
                                        No hay partidas agregadas. Haz clic en "Agregar Partida"
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Botones --}}
        <div class="d-flex justify-content-between mt-3">
            <a href="{{ route('requisitions.index') }}" class="btn btn-outline-secondary">
                <i class="ti ti-x me-1"></i>Cancelar
            </a>
            <div class="d-flex gap-2">
                <button type="button" 
                        onclick="confirmSaveDraft()"
                        class="btn btn-outline-primary"
                        wire:loading.attr="disabled"
                        wire:target="saveDraft">
                    <span wire:loading.remove wire:target="saveDraft">
                        <i class="ti ti-device-floppy me-1"></i>
                        {{ $isEditMode ? 'Actualizar Borrador' : 'Guardar como Borrador' }}
                    </span>
                    <span wire:loading wire:target="saveDraft">
                        <i class="ti ti-loader rotating me-1"></i>
                        {{ $isEditMode ? 'Actualizando...' : 'Guardando...' }}
                    </span>
                </button>
                
                <button type="button" 
                        onclick="confirmSubmit()"
                        class="btn btn-primary"
                        wire:loading.attr="disabled"
                        wire:target="submit">
                    <span wire:loading.remove wire:target="submit">
                        <i class="ti ti-send-2 me-1"></i>
                        {{ $isEditMode ? 'Actualizar y Enviar a Compras' : 'Enviar a Compras' }}
                    </span>
                    <span wire:loading wire:target="submit">
                        <i class="ti ti-loader rotating me-1"></i>Enviando...
                    </span>
                </button>
            </div>
        </div>
    </form>

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

                        {{-- Categoría de gasto --}}
                        <div class="mb-3">
                            <label for="modal_expense_category" class="form-label fw-bold text-muted">
                                <i class="ti ti-subtask me-1"></i> Categoría de Gasto <span class="text-danger">*</span>
                            </label>
                            <select id="modal_expense_category" class="form-select select2-simple" required>
                                <option value="">Seleccione primero un Centro de Costo...</option>
                            </select>
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

</div>

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

    #modal_description {
        font-size: 0.95rem;
        line-height: 1.4;
    }
    
    .rotating {
        display: inline-block;
        animation: rotate 1s linear infinite;
    }
    
    @keyframes rotate {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>

// =====================================================
// FUNCIÓN PARA CONFIRMAR ELIMINACIÓN DE PARTIDA
// =====================================================
function confirmDeleteItem(index) {
    Swal.fire({
        title: '¿Eliminar partida?',
        text: "Esta partida será eliminada de la requisición",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            @this.removeItem(index);
        }
    });
}

/**
 * Confirmar guardar como borrador
 */
function confirmSaveDraft() {
    const isEditMode = {{ $isEditMode ? 'true' : 'false' }};
    const title = isEditMode ? '¿Actualizar Borrador?' : '¿Guardar como Borrador?';
    const confirmText = isEditMode 
        ? '<i class="ti ti-device-floppy me-1"></i> Sí, Actualizar' 
        : '<i class="ti ti-device-floppy me-1"></i> Sí, Guardar Borrador';
    
    Swal.fire({
        title: title,
        html: `
            <div class="text-start">
                <p class="mb-3"><strong>Al guardar como borrador:</strong></p>
                <ul class="text-muted small">
                    <li class="mb-2">
                        <i class="ti ti-edit text-info"></i> 
                        Podrás <strong>editar, agregar o eliminar</strong> partidas después
                    </li>
                    <li class="mb-2">
                        <i class="ti ti-send text-success"></i> 
                        Podrás enviarlo a Compras cuando esté listo
                    </li>
                    <li class="mb-2">
                        <i class="ti ti-trash text-danger"></i> 
                        Podrás eliminarlo si ya no es necesario
                    </li>
                </ul>
                <div class="alert alert-info mt-3 mb-0">
                    <i class="ti ti-info-circle me-2"></i>
                    <small>Compras <strong>NO</strong> recibirá notificación hasta que lo envíes.</small>
                </div>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: '<i class="ti ti-x me-1"></i> Cancelar',
        confirmButtonColor: '#0d6efd',
        cancelButtonColor: '#6c757d',
        width: '600px',
        customClass: {
            confirmButton: 'btn btn-primary',
            cancelButton: 'btn btn-outline-secondary'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            @this.call('saveDraft');
        }
    });
}

/**
 * Confirmar enviar a compras
 */
function confirmSubmit() {
    const isEditMode = {{ $isEditMode ? 'true' : 'false' }};
    const title = isEditMode ? '¿Actualizar y Enviar a Compras?' : '¿Enviar a Compras?';
    
    Swal.fire({
        title: title,
        html: `
            <div class="text-start">
                <p class="mb-3"><strong>Al enviar a Compras:</strong></p>
                <ul class="text-muted small">
                    <li class="mb-2">
                        <i class="ti ti-lock text-danger"></i> 
                        <strong>Ya NO podrás editar</strong> la requisición, solo <strong class="text-danger">Cancelarla</strong>
                    </li>
                    <li class="mb-2">
                        <i class="ti ti-bell text-primary"></i> 
                        <strong>Compras recibirá notificación</strong> para iniciar cotización
                    </li>
                    <li class="mb-2">
                        <i class="ti ti-eye text-info"></i> 
                        Podrás consultar el estatus pero <strong>no modificarla</strong>
                    </li>
                </ul>
                <div class="alert alert-warning mt-3 mb-0">
                    <i class="ti ti-alert-triangle me-2"></i>
                    <small><strong>¡Importante!</strong> Verifica que toda la información sea correcta antes de enviar.</small>
                </div>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '<i class="ti ti-send me-1"></i> Sí, Enviar a Compras',
        cancelButtonText: '<i class="ti ti-arrow-left me-1"></i> Revisar de nuevo',
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d',
        width: '600px',
        customClass: {
            confirmButton: 'btn btn-success',
            cancelButton: 'btn btn-outline-secondary'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Validar que haya partidas antes de enviar
            const itemsCount = @this.items.length;
            
            if (itemsCount === 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Requisición vacía',
                    text: 'Debes agregar al menos una partida antes de enviar a Compras (RN-003).',
                    confirmButtonText: 'Entendido',
                    confirmButtonColor: '#dc3545'
                });
                return;
            }
            
            @this.call('submit');
        }
    });
}

$(function() {
    'use strict';

    // =====================================================
    // VARIABLE GLOBAL
    // =====================================================
    let editingIndex = null;
    const livewireComponent = @this;

    function initializeSearchableSelect($element, placeholder, options = {}) {
        if (!$element.length) {
            return;
        }

        if ($element.data('select2')) {
            $element.off('.requisitionSelect2');
            $element.select2('destroy');
        }

        $element.select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: placeholder,
            allowClear: true,
            language: {
                noResults: function() { return 'No se encontraron resultados'; },
                searching: function() { return 'Buscando...'; }
            },
            ...options
        });
    }

    function initializeHeaderSelects() {
        const headerSelects = [
            { selector: '#company_id', property: 'company_id', placeholder: 'Buscar compañía...' },
            { selector: '#cost_center_id', property: 'cost_center_id', placeholder: 'Buscar centro de costo...' },
            { selector: '#purchase_type', property: 'purchase_type', placeholder: 'Buscar tipo de compra...' },
        ];

        headerSelects.forEach(config => {
            const $select = $(config.selector);

            if (!$select.length) {
                return;
            }

            initializeSearchableSelect($select, config.placeholder);
            $select.on('change.requisitionSelect2', function() {
                livewireComponent.set(config.property, $(this).val() || '');
            });
        });
    }

    function initializeExpenseCategorySelect() {
        initializeSearchableSelect($('#modal_expense_category'), 'Buscar categoría de gasto...', {
            dropdownParent: $('#itemModal')
        });
    }

    function initializeRequisitionSelects() {
        initializeHeaderSelects();
        initializeExpenseCategorySelect();
    }

    initializeRequisitionSelects();

    document.addEventListener('livewire:init', () => {
        Livewire.hook('morph.updated', ({ el }) => {
            if (el.querySelector?.('#company_id') || el.id === 'company_id' || el.id === 'purchase_type' || el.id === 'cost_center_id') {
                setTimeout(() => initializeRequisitionSelects(), 0);
            }
        });
    });

    // =====================================================
    // LISTENER: Cambio de Centro de Costo
    // =====================================================
    $('#cost_center_id').on('change', function() {
        const costCenterId = $(this).val();
        $('#modal_expense_category').val(null).trigger('change');
        
        if (costCenterId) {
            loadExpenseCategories();
        } else {
            $('#modal_expense_category')
                .empty()
                .append('<option value="">Seleccione primero un Centro de Costo...</option>')
                .prop('disabled', true);
            initializeExpenseCategorySelect();
        }
    });

    // =====================================================
    // 1. ABRIR MODAL PARA AGREGAR
    // =====================================================
    $('#btnAddItem').on('click', function() {
        const companyId = $('#company_id').val();
        const costCenterId = $('#cost_center_id').val();

        if (!companyId || !costCenterId) {
            Swal.fire('Datos incompletos', 'Primero selecciona compañía y centro de costo.', 'warning');
            return;
        }

        // ✅ PASO 1: Verificar si hay productos activos para este centro de costo
        checkProductsAvailability(companyId, costCenterId).then(hasProducts => {
            if (!hasProducts) {
                return;
            }

            // ✅ PASO 2: Validar categorías ANTES de abrir el modal
            loadExpenseCategories().then(hasCategories => {
                if (hasCategories) {
                    openItemModal();
                }
            });
        });
    });

    /**
     * Verificar si hay productos/servicios activos disponibles para el centro de costo.
     */
    function checkProductsAvailability(companyId, costCenterId) {
        return new Promise((resolve, reject) => {
            $.ajax({
                url: '{{ route("products-services.api.active-for-requisitions") }}',
                type: 'GET',
                data: {
                    company_id: companyId,
                    cost_center_id: costCenterId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.products && response.products.length > 0) {
                        console.log(`✅ ${response.products.length} producto(s) disponible(s)`);
                        resolve(true);
                    } else {
                        Swal.fire({
                            title: '⚠️ Sin Productos en el Catálogo',
                            html: `
                                <div class="text-start">
                                    <div class="alert alert-warning mb-3">
                                        <i class="ti ti-alert-triangle me-2"></i>
                                        <strong>No se puede agregar partida</strong>
                                    </div>
                                    
                                    <p class="mb-3">
                                        No hay productos o servicios <strong>activos</strong> registrados en el 
                                        catálogo para este centro de costo.
                                    </p>
                                    
                                    <div class="card bg-light border-0 mb-3">
                                        <div class="card-body">
                                            <h6 class="card-title text-primary mb-2">
                                                <i class="ti ti-info-circle me-1"></i>¿Qué significa esto?
                                            </h6>
                                            <p class="small mb-0">
                                                Solo puedes requisar productos que estén previamente registrados 
                                                y aprobados en el <strong>catálogo de productos y servicios</strong>.
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div class="card border-primary mb-0">
                                        <div class="card-body">
                                            <h6 class="card-title text-primary mb-2">
                                                <i class="ti ti-checklist me-1"></i>¿Qué debo hacer?
                                            </h6>
                                            <ol class="small mb-0 ps-3">
                                                <li class="mb-2">
                                                    Accede al módulo de <strong>Catálogo de Productos/Servicios</strong>
                                                </li>
                                                <li class="mb-2">
                                                    Crea un nuevo producto/servicio para tu centro de costo
                                                </li>
                                                <li class="mb-2">
                                                    Espera a que sea <strong>aprobado</strong> por el administrador del catálogo
                                                </li>
                                                <li>
                                                    Una vez aprobado, podrás agregarlo a tus requisiciones
                                                </li>
                                            </ol>
                                        </div>
                                    </div>
                                </div>
                            `,
                            icon: 'warning',
                            confirmButtonText: 'Entendido',
                            confirmButtonColor: '#0d6efd',
                            width: '600px',
                            customClass: {
                                popup: 'text-start'
                            }
                        });
                        resolve(false);
                    }
                },
                error: function(xhr) {
                    console.error('Error al verificar productos:', xhr);
                    Swal.fire({
                        title: '¡Error!',
                        text: xhr.responseJSON?.message || 'Error al verificar productos disponibles en el catálogo.',
                        icon: 'error',
                        confirmButtonText: 'Entendido'
                    });
                    resolve(false);
                }
            });
        });
    }

    /**
     * Abrir modal en modo AGREGAR (limpio).
     */
    function openItemModal() {
        editingIndex = null;

        $('#itemModalTitle').text('Agregar Partida');
        document.getElementById('itemForm').reset();
        $('#item_index').val('');
        $('#budgetAlert').hide();
        $('#product_info').hide();

        loadProductsForCostCenter();
        loadExpenseCategories();

        $('#itemModal').modal('show');
    }

    // =====================================================
    // 2. EDITAR PARTIDA
    // =====================================================
    $(document).on('click', '.btn-edit-item', function() {
        const index = parseInt($(this).data('index'));
        
        const item = @this.items[index];
        
        if (!item) {
            Swal.fire('Error', 'No se pudo cargar la partida para editar.', 'error');
            return;
        }
        
        const companyId = $('#company_id').val();
        const costCenterId = $('#cost_center_id').val();
        
        checkProductsAvailability(companyId, costCenterId).then(hasProducts => {
            if (!hasProducts) {
                return;
            }
            
            loadExpenseCategories().then(hasCategories => {
                if (hasCategories) {
                    openItemModalForEdit(index, item);
                }
            });
        });
    });

    /**
     * Abrir modal en modo EDITAR con datos pre-cargados.
     */
    function openItemModalForEdit(index, item) {
        editingIndex = index;
        
        $('#itemModalTitle').text('Editar Partida');
        $('#item_index').val(index);
        $('#budgetAlert').hide();
        
        loadProductsForCostCenter();
        loadExpenseCategories();
        
        setTimeout(() => {
            $('#modal_product_id').val(item.product_id).trigger('change');
            $('#modal_description').val(item.description);
            $('#modal_quantity').val(item.quantity);
            $('#modal_unit').val(item.unit);
            $('#modal_expense_category').val(item.expense_category_id).trigger('change');
            $('#modal_notes').val(item.notes || '');
        }, 500);
        
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
                cost_center_id: costCenterId
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

            $('#modal_description').val($option.data('description') || '');
            $('#modal_unit').val($option.data('unit') || 'PZA');
            $('#modal_suggested_vendor').val($option.data('suggested-vendor') || 'Sin proveedor sugerido');

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

            const type = $option.data('type');
            const code = $option.data('code');
            const brand = $option.data('brand');
            const model = $option.data('model');

            const typeBadge = type === 'SERVICIO' ?
                '<span class="badge bg-info"><i class="ti ti-briefcase me-1"></i>Servicio</span>' :
                '<span class="badge bg-primary"><i class="ti ti-box me-1"></i>Producto</span>';
            $('#product_type_badge').html(typeBadge);

            $('#product_code_display').html(`<strong>Código:</strong> <code>${code}</code>`);

            if (brand || model) {
                const brandModel = [brand, model].filter(Boolean).join(' / ');
                $('#product_brand_model').html(`<strong>Marca/Modelo:</strong> ${brandModel}`).show();
            } else {
                $('#product_brand_model').hide();
            }

            $('#product_info').show();
        });
    }

    // =====================================================
    // 5. CARGAR CATEGORÍAS DE GASTO
    // =====================================================
    function loadExpenseCategories() {
        return new Promise((resolve, reject) => {
            const $select = $('#modal_expense_category');
            const costCenterId = $('#cost_center_id').val();

            if (!costCenterId) {
                $select.empty()
                    .append('<option value="">Seleccione primero un Centro de Costo...</option>')
                    .prop('disabled', true);
                initializeExpenseCategorySelect();
                resolve(false);
                return;
            }

            $select.prop('disabled', true)
                .empty()
                .append('<option value="">⏳ Cargando categorías...</option>');
            initializeExpenseCategorySelect();

            $.ajax({
                url: '{{ route("expense-categories.by-cost-center") }}',
                type: 'GET',
                data: {
                    cost_center_id: costCenterId
                },
                dataType: 'json',
                success: function(response) {
                    $select.empty().append('<option value="">Seleccionar categoría...</option>');

                    if (response.success && response.categories && response.categories.length > 0) {
                        response.categories.forEach(cat => {
                            const optionText = `${cat.code} - ${cat.name}`;
                            
                            $select.append($('<option>', {
                                value: cat.id,
                                text: optionText,
                                'data-name': cat.name
                            }));
                        });

                        $select.prop('disabled', false);
                        initializeExpenseCategorySelect();

                        if (response.budget_type === 'FREE_CONSUMPTION') {
                            const Toast = Swal.mixin({
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 4000,
                                timerProgressBar: true,
                                didOpen: (toast) => {
                                    toast.addEventListener('mouseenter', Swal.stopTimer)
                                    toast.addEventListener('mouseleave', Swal.resumeTimer)
                                }
                            });
                            
                            Toast.fire({
                                icon: 'info',
                                title: 'Centro de consumo libre',
                                html: '<small>Todas las categorías disponibles</small>'
                            });
                        }

                        resolve(true);
                    } else {
                        $select.append('<option value="">⚠️ Sin categorías disponibles</option>');
                        initializeExpenseCategorySelect();
                        showBudgetError(response);
                        resolve(false);
                    }
                },
                error: function(xhr) {
                    $select.empty().append('<option value="">❌ Error al cargar</option>');
                    initializeExpenseCategorySelect();
                    
                    if (xhr.status === 404 && xhr.responseJSON) {
                        showBudgetError(xhr.responseJSON);
                    } else {
                        Swal.fire({
                            title: '¡Error!',
                            text: 'Error al cargar las categorías de gasto.',
                            icon: 'error',
                            confirmButtonText: 'Entendido'
                        });
                    }
                    
                    resolve(false);
                }
            });
        });
    }

    /**
    * Mostrar alerta específica según el tipo de error de presupuesto
    */
    function showBudgetError(response) {
        const errorType = response.error_type;
        const currentYear = new Date().getFullYear();
        
        let title, html, icon;
        
        if (errorType === 'NO_BUDGET') {
            title = '⚠️ Presupuesto No Configurado';
            html = `
                <div class="text-start">
                    <div class="alert alert-warning mb-3">
                        <i class="ti ti-alert-triangle me-2"></i>
                        <strong>No se puede crear la requisición</strong>
                    </div>
                    
                    <p class="mb-3">${response.message}</p>
                    
                    <div class="card bg-light border-0 mb-3">
                        <div class="card-body">
                            <h6 class="card-title text-primary mb-2">
                                <i class="ti ti-info-circle me-1"></i>¿Qué significa esto?
                            </h6>
                            <p class="small mb-0">
                                Todos los gastos deben estar dentro del <strong>plan financiero anual</strong>. 
                                Sin un presupuesto existente, no es posible crear requisiciones.
                            </p>
                        </div>
                    </div>
                    
                    <div class="card border-primary mb-0">
                        <div class="card-body">
                            <h6 class="card-title text-primary mb-2">
                                <i class="ti ti-checklist me-1"></i>¿Qué debo hacer?
                            </h6>
                            <ol class="small mb-0 ps-3">
                                <li class="mb-2">Contacta al <strong>responsable del centro de costo</strong></li>
                                <li class="mb-2">Solicita que configure el <strong>presupuesto anual ${currentYear}</strong></li>
                                <li>Una vez existente, podrás crear requisiciones</li>
                            </ol>
                        </div>
                    </div>
                </div>
            `;
            icon = 'warning';
        } else if (errorType === 'NO_CATEGORIES') {
            title = '⚠️ Distribución Presupuestal Incompleta';
            html = `
                <div class="text-start">
                    <div class="alert alert-info mb-3">
                        <i class="ti ti-info-circle me-2"></i>
                        <strong>El presupuesto existe pero está incompleto</strong>
                    </div>
                    
                    <p class="mb-3">${response.message}</p>
                    
                    <div class="card bg-light border-0 mb-3">
                        <div class="card-body">
                            <h6 class="card-title text-primary mb-2">
                                <i class="ti ti-info-circle me-1"></i>¿Qué significa esto?
                            </h6>
                            <p class="small mb-0">
                                El presupuesto anual existe, pero no tiene <strong>distribuciones mensuales</strong> 
                                asignadas a categorías de gasto. Sin esto, no se pueden crear requisiciones.
                            </p>
                        </div>
                    </div>
                    
                    <div class="card border-primary mb-0">
                        <div class="card-body">
                            <h6 class="card-title text-primary mb-2">
                                <i class="ti ti-checklist me-1"></i>¿Qué debo hacer?
                            </h6>
                            <ol class="small mb-0 ps-3">
                                <li class="mb-2">Contacta al <strong>responsable del centro de costo</strong></li>
                                <li class="mb-2">Solicita que configure las <strong>distribuciones mensuales</strong> del presupuesto</li>
                                <li>Debe asignar montos a las categorías de gasto por mes</li>
                            </ol>
                        </div>
                    </div>
                </div>
            `;
            icon = 'info';
        } else {
            title = 'Sin Categorías Disponibles';
            html = `
                <p>${response.message || 'No hay categorías de gasto disponibles para este centro de costo.'}</p>
                <p class="text-muted small">${response.instructions || 'Contacta al administrador del sistema.'}</p>
            `;
            icon = 'warning';
        }
        
        Swal.fire({
            title: title,
            html: html,
            icon: icon,
            confirmButtonText: 'Entendido',
            confirmButtonColor: '#0d6efd',
            width: '600px',
            customClass: {
                popup: 'text-start'
            }
        });
    }

    // =====================================================
    // 6. GUARDAR PARTIDA → Llamar a Livewire
    // =====================================================
    $('#btnSaveItem').on('click', function() {
        const productId = $('#modal_product_id').val();
        const quantity = parseFloat($('#modal_quantity').val());
        const categoryId = $('#modal_expense_category').val();

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

        const $selectedProduct = $('#modal_product_id option:selected');
        const minQty = parseFloat($selectedProduct.data('min-qty'));
        const maxQty = parseFloat($selectedProduct.data('max-qty'));
        const unit = $selectedProduct.data('unit') || 'PZA';

        if (minQty && quantity < minQty) {
            Swal.fire({
                icon: 'error',
                title: 'Cantidad insuficiente',
                text: `La cantidad mínima para este producto es ${minQty} ${unit}`
            });
            return;
        }

        if (maxQty && quantity > maxQty) {
            Swal.fire({
                icon: 'error',
                title: 'Cantidad excedida',
                text: `La cantidad máxima permitida es ${maxQty} ${unit}`
            });
            return;
        }

        const itemData = {
            product_id: productId,
            product_name: $('#modal_product_id option:selected').text(),
            description: $('#modal_description').val(),
            quantity: quantity,
            unit: $('#modal_unit').val(),
            expense_category_id: categoryId,
            expense_category_name: $('#modal_expense_category option:selected').text(),
            notes: $('#modal_notes').val() || ''
        };

        const editIndex = $('#item_index').val();
        
        if (editIndex !== '' && editIndex !== null) {
            @this.updateItem(parseInt(editIndex), itemData);
        } else {
            @this.addItem(itemData);
        }

        $('#itemModal').modal('hide');
    });

    // =====================================================
    // 7. LISTENERS DE EVENTOS DE LIVEWIRE
    // =====================================================
    Livewire.on('item-added', (event) => {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });
        
        Toast.fire({
            icon: 'success',
            title: 'Partida agregada'
        });
    });

    Livewire.on('item-updated', (event) => {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });
        
        Toast.fire({
            icon: 'success',
            title: 'Partida actualizada'
        });
    });

    Livewire.on('item-removed', (event) => {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });
        
        Toast.fire({
            icon: 'success',
            title: 'Partida eliminada'
        });
    });

    Livewire.on('item-error', (event) => {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 4000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });
        
        Toast.fire({
            icon: 'error',
            title: event.message || 'Ocurrió un error al procesar la partida'
        });
    });

    // =====================================================
    // 8. LISTENERS ADICIONALES DE VALIDACIÓN Y GUARDADO
    // =====================================================
    Livewire.on('validation-error', (event) => {
        Swal.fire({
            icon: 'error',
            title: 'Validación',
            text: event.message || 'Por favor, completa todos los campos requeridos'
        });
    });

    Livewire.on('save-error', (event) => {
        Swal.fire({
            icon: 'error',
            title: 'Error al guardar',
            text: event.message || 'Ocurrió un error al guardar la requisición'
        });
    });
});
</script>
@endpush
