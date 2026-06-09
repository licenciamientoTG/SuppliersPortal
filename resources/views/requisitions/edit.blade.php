@extends('layouts.zircos')

@section('title', 'Editar Requisición')

@section('page.title', 'Editar Requisición')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('requisitions.index') }}">Requisiciones</a></li>
    <li class="breadcrumb-item"><a href="{{ route('requisitions.show', $requisition) }}">{{ $requisition->folio }}</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
    <div class="container-fluid">   
        {{-- Header --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold mb-0">
                <i class="ti ti-edit me-2"></i>Editar Requisición — {{ $requisition->folio }}
            </h4>
            <div>
                <span class="badge bg-{{ $requisition->status->badgeClass() }}">
                    {{ $requisition->status->label() }}
                </span>
            </div>
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

        {{-- Alerta si está pausada --}}
        @if ($requisition->isPaused())
            <div class="alert alert-warning">
                <i class="ti ti-alert-triangle me-2"></i>
                <strong>Requisición pausada:</strong> {{ $requisition->pause_reason }}
                @if ($requisition->pauser)
                    <br><small>Por {{ $requisition->pauser->name }} el
                        {{ $requisition->paused_at->format('d/m/Y H:i') }}</small>
                @endif
            </div>
        @endif

        {{-- Formulario --}}
        <form action="{{ route('requisitions.update', $requisition) }}" method="POST" id="requisitionForm">
            @csrf
            @method('PUT')

            {{-- Información General --}}
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="ti ti-info-circle me-2"></i>Información General
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        {{-- Compañía (solo lectura en edición) --}}
                        <div class="col-md-2">
                            <label class="form-label">Compañía</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="ti ti-building"></i>
                                </span>
                                <input type="text" class="form-control bg-light" value="{{ $requisition->company->name }}" readonly>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Tipo de compra</label>
                            <input type="hidden" name="purchase_type" value="{{ old('purchase_type', $selectedPurchaseType) }}">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="ti ti-filter"></i>
                                </span>
                                <input type="text" class="form-control bg-light" value="{{ old('purchase_type', $selectedPurchaseType) }}" readonly>
                            </div>
                            @error('purchase_type')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Centro de costo --}}
                        <div class="col-md-3">
                            <label for="cost_center_id" class="form-label">
                                Centro de costo
                                @if ($requisition->isDraft())
                                    <span class="text-danger">*</span>
                                @endif
                            </label>
                            @if ($requisition->isDraft())
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="ti ti-chart-pie"></i>
                                    </span>
                                    <select id="cost_center_id" name="cost_center_id"
                                        class="@error('cost_center_id') is-invalid @enderror form-select" required>
                                        <option value="">Seleccionar...</option>
                                        @foreach ($costCenters as $cc)
                                            <option value="{{ $cc->id }}"
                                                {{ (int) old('cost_center_id', $requisition->cost_center_id) === (int) $cc->id ? 'selected' : '' }}>
                                                {{ $cc->code ? "[{$cc->code}] {$cc->name}" : $cc->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                @error('cost_center_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            @else
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="ti ti-chart-pie"></i>
                                    </span>
                                    <input type="text" class="form-control bg-light"
                                        value="{{ $requisition->costCenter->code ? "[{$requisition->costCenter->code}] {$requisition->costCenter->name}" : $requisition->costCenter->name }}" readonly>
                                </div>
                                <small class="form-text text-muted">No se puede cambiar</small>
                            @endif
                        </div>

                        {{-- Departamento --}}
                        <div class="col-md-2">
                            <label for="department_id" class="form-label">
                                Departamento
                                @if ($requisition->isDraft())
                                    <span class="text-danger">*</span>
                                @endif
                            </label>
                            @if ($requisition->isDraft())
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="ti ti-users"></i>
                                    </span>
                                    <select id="department_id" name="department_id"
                                        class="@error('department_id') is-invalid @enderror form-select" required>
                                        <option value="">Seleccionar...</option>
                                        @foreach ($departments as $dep)
                                            <option value="{{ $dep->id }}"
                                                {{ (int) old('department_id', $requisition->department_id) === (int) $dep->id ? 'selected' : '' }}>
                                                {{ $dep->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                @error('department_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            @else
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="ti ti-users"></i>
                                    </span>
                                    <input type="text" class="form-control bg-light"
                                        value="{{ $requisition->department->name }}" readonly>
                                </div>
                                <small class="form-text text-muted">No se puede cambiar</small>
                            @endif
                        </div>

                        {{-- Fecha requerida --}}
                        <div class="col-md-2">
                            <label for="required_date" class="form-label">
                                Fecha requerida
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="ti ti-calendar"></i>
                                </span>
                                <input type="date" id="required_date" name="required_date"
                                    class="form-control @error('required_date') is-invalid @enderror"
                                    value="{{ old('required_date', optional($requisition->required_date)->format('Y-m-d')) }}">
                            </div>
                            @error('required_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Descripción --}}
                        <div class="col-md-3">
                            <label for="description" class="form-label">
                                Descripción
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="ti ti-file-text"></i>
                                </span>
                                <input type="text" id="description" name="description"
                                    class="form-control @error('description') is-invalid @enderror"
                                    value="{{ old('description', $requisition->description) }}"
                                    placeholder="Ej: Compra de equipo..." maxlength="500">
                            </div>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
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
                        @if(count($requisition->items) > 0)
                            <span class="badge bg-primary ms-2">{{ count($requisition->items) }} partida(s)</span>
                        @endif
                    </h5>
                    <button type="button" class="btn btn-sm btn-primary" id="btnAddItem">
                        <i class="ti ti-plus me-1"></i> Agregar Partida
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table-sm table-hover table" id="itemsTable">
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
                            <tbody id="itemsTableBody">
                                <tr id="emptyRow" style="{{ count($requisition->items) > 0 ? 'display:none;' : '' }}">
                                    <td colspan="8" class="text-muted py-4 text-center">
                                        <i class="ti ti-inbox fs-1 d-block mb-2"></i>
                                        No hay partidas agregadas. Haz clic en "Agregar Partida"
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
                <a href="{{ route('requisitions.show', $requisition) }}" class="btn btn-outline-secondary">
                    <i class="ti ti-arrow-left me-1"></i>Regresar
                </a>
                <div class="d-flex gap-2">
                    <button type="button" 
                            onclick="confirmSaveChanges()" 
                            class="btn btn-outline-primary">
                        <i class="ti ti-device-floppy me-1"></i>Guardar Cambios
                    </button>
                    <button type="button" 
                            onclick="confirmSubmitEdit()" 
                            class="btn btn-primary">
                        <i class="ti ti-send me-1"></i>Enviar a Compras
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
                        <input type="hidden" id="item_id">

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
                                <textarea id="modal_description" class="form-control bg-light" rows="2" style="resize: none;" readonly></textarea>
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
                                    <input type="number" id="modal_quantity" class="form-control" min="0.001"
                                        step="0.001" value="1" required>
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

@endsection

@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css"
        rel="stylesheet" />
    <style>
        .input-group-text {
            background-color: #f8f9fa;
            border-right: 0;
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
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // =====================================================
        // VARIABLES GLOBALES (fuera del jQuery ready)
        // =====================================================
        let itemsArray = [];
        let editingIndex = null;

        /**
         * Confirmar guardar cambios (mantener como borrador)
         */
        function confirmSaveChanges() {
            // Validar que haya partidas
            if (itemsArray.length === 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Requisición vacía',
                    text: 'Debes agregar al menos una partida antes de guardar (RN-003).',
                    confirmButtonText: 'Entendido',
                    confirmButtonColor: '#dc3545'
                });
                return;
            }

            Swal.fire({
                title: '¿Guardar Cambios?',
                html: `
                    <div class="text-start">
                        <p class="mb-3"><strong>Al guardar los cambios:</strong></p>
                        <ul class="text-muted small">
                            <li class="mb-2">
                                <i class="ti ti-clock text-warning"></i> 
                                Mantendrá el estatus <span class="badge bg-{{ $requisition->status->badgeClass() }}">{{ $requisition->status->label() }}</span>
                            </li>
                            <li class="mb-2">
                                <i class="ti ti-pencil text-info"></i> 
                                Podrás seguir <strong>editando</strong> después
                            </li>
                            <li class="mb-2">
                                <i class="ti ti-send text-success"></i> 
                                Podrás enviarlo a Compras cuando esté listo
                            </li>
                        </ul>
                        <div class="alert alert-info mt-3 mb-0">
                            <i class="ti ti-info-circle me-2"></i>
                            <small>Los cambios se guardarán pero Compras <strong>NO</strong> recibirá notificación.</small>
                        </div>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="ti ti-device-floppy me-1"></i> Sí, Guardar Cambios',
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
                    // CRÍTICO: Actualizar inputs hidden antes de enviar
                    updateHiddenInputs();
                    
                    // Establecer el action como "save" y enviar el formulario
                    const form = document.getElementById('requisitionForm');
                    
                    // Crear input hidden para la acción
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'save';
                    form.appendChild(actionInput);
                    
                    console.log('📤 Enviando formulario con', itemsArray.length, 'partidas (GUARDAR)');
                    form.submit();
                }
            });
        }

        /**
         * Confirmar enviar a compras (desde edición)
         */
        function confirmSubmitEdit() {
            // Validar que haya partidas
            if (itemsArray.length === 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Requisición vacía',
                    text: 'Debes agregar al menos una partida antes de enviar a Compras (RN-003).',
                    confirmButtonText: 'Entendido',
                    confirmButtonColor: '#dc3545'
                });
                return;
            }

            Swal.fire({
                title: '¿Enviar a Compras?',
                html: `
                    <div class="text-start">
                        <p class="mb-3"><strong>Al enviar la requisición {{ $requisition->folio }} a Compras:</strong></p>
                        <ul class="text-muted small">
                            <li class="mb-2">
                                <i class="ti ti-clock-hour-4 text-warning"></i> 
                                Cambiará a estatus <span class="badge bg-warning">PENDIENTE</span>
                            </li>
                            <li class="mb-2">
                                <i class="ti ti-lock text-danger"></i> 
                                <strong>Ya NO podrás editar</strong> la requisición
                            </li>
                            <li class="mb-2">
                                <i class="ti ti-bell text-primary"></i> 
                                <strong>Compras recibirá notificación</strong> para iniciar cotización
                            </li>
                            <li class="mb-2">
                                <i class="ti ti-eye text-info"></i> 
                                Solo podrás consultar el estatus, <strong>no modificarla</strong>
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
                    // CRÍTICO: Actualizar inputs hidden antes de enviar
                    updateHiddenInputs();
                    
                    // Establecer el action como "submit" y enviar el formulario
                    const form = document.getElementById('requisitionForm');
                    
                    // Crear input hidden para la acción
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'submit';
                    form.appendChild(actionInput);
                    
                    console.log('📤 Enviando formulario con', itemsArray.length, 'partidas (ENVIAR A COMPRAS)');
                    form.submit();
                }
            });
        }

        /**
         * Actualizar inputs hidden para envío
         */
        function updateHiddenInputs() {
            const container = $('#hiddenItemsContainer');
            container.empty();

            itemsArray.forEach((item, index) => {
                const idInput = item.id ? `<input type="hidden" name="items[${index}][id]" value="${item.id}">` : '';
                
                container.append(`
                    ${idInput}
                    <input type="hidden" name="items[${index}][product_service_id]" value="${escapeHtml(item.product_id)}">
                    <input type="hidden" name="items[${index}][description]" value="${escapeHtml(item.description)}">
                    <input type="hidden" name="items[${index}][quantity]" value="${escapeHtml(item.quantity)}">
                    <input type="hidden" name="items[${index}][unit]" value="${escapeHtml(item.unit)}">
                    <input type="hidden" name="items[${index}][expense_category_id]" value="${escapeHtml(item.expense_category_id)}">
                    <input type="hidden" name="items[${index}][budget_cedula_id]" value="${escapeHtml(item.budget_cedula_id)}">
                    <input type="hidden" name="items[${index}][cost_center_id]" value="${escapeHtml(item.cost_center_id)}">
                    <input type="hidden" name="items[${index}][notes]" value="${escapeHtml(item.notes)}">
                `);
            });

            console.log('✅', itemsArray.length, 'partidas preparadas para envío');
        }

        /**
         * Escapar HTML para prevenir XSS
         */
        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.toString().replace(/[&<>"']/g, m => map[m]);
        }

        // =====================================================
        // JQUERY READY
        // =====================================================
        $(function() {
            'use strict';

            // =====================================================
            // INICIALIZAR DATOS
            // =====================================================
            @php
                $itemsData = old(
                    'items',
                    $requisition->items
                        ->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'product_id' => $item->product_service_id,
                                'product_name' => $item->productService->code . ' - ' . $item->productService->description,
                                'description' => $item->description,
                                'quantity' => $item->quantity,
                                'unit' => $item->unit,
                                'expense_category_id' => $item->expense_category_id,
                                'expense_category_name' => $item->expenseCategory?->name ?? '—',
                                'budget_cedula_id' => $item->budget_cedula_id,
                                'budget_cedula_name' => $item->budgetCedula?->name ?? '—',
                                'cost_center_id' => $item->cost_center_id,
                                'cost_center_name' => $item->costCenter?->name ?? '—',
                                'purchase_type' => $item->costCenter?->purchase_type?->value ?? $item->costCenter?->purchase_type ?? '',
                                'notes' => $item->notes ?? ''
                            ];
                        })
                        ->toArray()
                );
            @endphp

            // Inicializar itemsArray con los datos existentes
            itemsArray = @json($itemsData);
            console.log('📦 Partidas cargadas:', itemsArray.length);

            // Cargar partidas existentes en la tabla
            refreshTable();

            // =====================================================
            // ABRIR MODAL PARA AGREGAR
            // =====================================================
            $('#btnAddItem').on('click', function() {
                openItemModal();
            });

            // =====================================================
            // ABRIR MODAL (AGREGAR O EDITAR)
            // =====================================================
            function openItemModal(index = null) {
                editingIndex = index;

                $('#itemModalTitle').text(index !== null ? 'Editar Partida' : 'Agregar Partida');

                document.getElementById('itemForm').reset();
                $('#item_index').val(index !== null ? index : '');
                $('#item_id').val('');
                $('#product_info').hide();

                loadProductsForCostCenter();
                loadExpenseCategories();

                if (index !== null && itemsArray[index]) {
                    const item = itemsArray[index];

                    setTimeout(() => {
                        $('#item_id').val(item.id || '');
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
            // CARGAR PRODUCTOS
            // =====================================================
            function loadProductsForCostCenter() {
                const companyId = @json($requisition->company_id);
                const costCenterId = @json($requisition->cost_center_id);

                $('#modal_product_id').prop('disabled', true).empty().append(
                    '<option value="">Cargando...</option>');

                $.ajax({
                    url: '{{ route('products-services.api.active-for-requisitions') }}',
                    method: 'GET',
                data: {
                    company_id: companyId,
                    cost_center_id: costCenterId,
                    all: true
                },
                    success: function(response) {
                        $('#modal_product_id').empty().append(
                            '<option value="">Buscar producto...</option>');

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
                        Swal.fire('Error', xhr.responseJSON?.message || 'No se pudieron cargar los productos.', 'error');
                    },
                    complete: function() {
                        $('#modal_product_id').prop('disabled', false);
                    }
                });
            }

            // =====================================================
            // INICIALIZAR SELECT2
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

                    $('#modal_quantity').siblings('.form-text').html(
                        `<i class="ti ti-info-circle me-1"></i>${helpText}`);

                    // Mostrar info del producto
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
            // CARGAR CATEGORÍAS DE GASTO
            // =====================================================
            function loadExpenseCategories() {
                const $select = $('#modal_expense_category');

                // Mostrar estado de carga
                $select.prop('disabled', true)
                    .empty()
                    .append('<option value="">⏳ Cargando categorías...</option>');

                $.ajax({
                    url: '{{ route("expense-categories.by-cost-center") }}',
                    type: 'GET',
                    dataType: 'json',
                    success: function(data) {
                        $select.empty().append('<option value="">Seleccionar categoría...</option>');

                        if (data.categories && data.categories.length > 0) {
                            data.categories.forEach(cat => {
                                // Mostrar código y nombre si existe código
                                const optionText = cat.code ? `${cat.code} - ${cat.name}` : cat.name;
                                
                                $select.append($('<option>', {
                                    value: cat.id,
                                    text: optionText
                                }));
                            });

                            $select.prop('disabled', false);
                        } else {
                            $select.append('<option value="">⚠️ Sin categorías disponibles</option>');
                        }
                    },
                    error: function(xhr) {
                        console.error('Error al cargar categorías:', xhr);
                        
                        $select.empty().append('<option value="">❌ Error al cargar</option>');
                        
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'No se pudieron cargar las categorías de gasto.'
                        });
                    }
                });
            }

            // =====================================================
            // GUARDAR PARTIDA
            // =====================================================
            $('#btnSaveItem').on('click', function() {
                const productId = $('#modal_product_id').val();
                const quantity = parseFloat($('#modal_quantity').val());
                const categoryId = $('#modal_expense_category').val();
                const budgetCedulaId = $('#modal_budget_cedula').val();
                const costCenterId = $('#cost_center_id').val();
                const costCenterName = $('#cost_center_id option:selected').text();
                const purchaseType = $('input[name="purchase_type"]').val() || $('#purchase_type').val();

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

                if (!budgetCedulaId) {
                    Swal.fire('Error', 'Selecciona una subcategoría presupuestal.', 'error');
                    return;
                }

                if (!costCenterId) {
                    Swal.fire('Error', 'Selecciona un centro de costo antes de agregar la partida.', 'error');
                    return;
                }

                // Validar cantidad mínima y máxima
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

                const item = {
                    id: $('#item_id').val() || null,
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

                if (editingIndex !== null) {
                    itemsArray[editingIndex] = item;
                    console.log('✏️ Partida actualizada en índice', editingIndex);
                } else {
                    itemsArray.push(item);
                    console.log('➕ Nueva partida agregada. Total:', itemsArray.length);
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
            // EDITAR PARTIDA
            // =====================================================
            $(document).on('click', '.btn-edit-item', function() {
                const index = parseInt($(this).data('index'));
                openItemModal(index);
            });

            // =====================================================
            // ELIMINAR PARTIDA
            // =====================================================
            $(document).on('click', '.btn-delete-item', function() {
                const index = parseInt($(this).data('index'));

                Swal.fire({
                    title: '¿Eliminar partida?',
                    text: 'Esta acción no se puede deshacer.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then(result => {
                    if (result.isConfirmed) {
                        itemsArray.splice(index, 1);
                        console.log('🗑️ Partida eliminada. Total:', itemsArray.length);
                        
                        refreshTable();

                        Swal.fire({
                            icon: 'success',
                            title: 'Partida eliminada',
                            timer: 1500,
                            showConfirmButton: false
                        });
                    }
                });
            });

            // =====================================================
            // REFRESCAR TABLA
            // =====================================================
            function refreshTable() {
                const tbody = $('#itemsTableBody');
                const emptyRow = $('#emptyRow');

                if (itemsArray.length === 0) {
                    emptyRow.show();
                } else {
                    emptyRow.hide();
                    tbody.find('tr:not(#emptyRow)').remove();

                    itemsArray.forEach((item, index) => {
                        const notesIcon = item.notes ? 
                            `<i class="ti ti-note text-primary" data-bs-toggle="tooltip" title="${escapeHtml(item.notes)}"></i>` : 
                            '<span class="text-muted">—</span>';
                        
                        const row = `
                        <tr>
                            <td>${index + 1}</td>
                            <td><strong>${escapeHtml(item.product_name)}</strong></td>
                            <td>${escapeHtml(item.description.substring(0, 50))}${item.description.length > 50 ? '...' : ''}</td>
                            <td>${item.quantity}</td>
                            <td>${escapeHtml(item.unit)}</td>
                            <td><span class="badge bg-info">${escapeHtml(item.expense_category_name)}</span></td>
                            <td>${notesIcon}</td>
                            <td class="text-nowrap">
                                <button type="button" class="btn btn-sm btn-warning btn-edit-item" data-index="${index}" title="Editar">
                                    <i class="ti ti-edit"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-danger btn-delete-item" data-index="${index}" title="Eliminar">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                        tbody.append(row);
                    });

                    // Inicializar tooltips
                    $('[data-bs-toggle="tooltip"]').tooltip();
                }

                updateHiddenInputs();
            }

            // =====================================================
            // VALIDACIÓN ANTES DE SUBMIT
            // =====================================================
            $('#requisitionForm').on('submit', function(e) {
                if (itemsArray.length === 0) {
                    e.preventDefault();
                    Swal.fire('Requisición vacía', 'Debes agregar al menos una partida (RN-003).', 'error');
                    return false;
                }
            });
        });
    </script>
@endpush
