<div>
    {{-- Indicador de Progreso (Steps) --}}
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                {{-- Paso 1: Validación --}}
                <div class="text-center flex-fill {{ $currentStep >= 1 ? 'text-primary' : 'text-muted' }}">
                    <div class="mb-2">
                        <span class="avatar avatar-sm rounded-circle {{ $currentStep >= 1 ? 'bg-primary text-white' : 'bg-light' }}">
                            @if($currentStep > 1)
                                <i class="ti ti-check"></i>
                            @else
                                1
                            @endif
                        </span>
                    </div>
                    <small class="fw-bold">Validación</small>
                </div>

                <div class="flex-fill border-top {{ $currentStep >= 2 ? 'border-primary' : '' }}" style="margin: 0 -1rem; margin-top: -2rem;"></div>

                {{-- Paso 2: Planificar --}}
                <div class="text-center flex-fill {{ $currentStep >= 2 ? 'text-primary' : 'text-muted' }}">
                    <div class="mb-2">
                        <span class="avatar avatar-sm rounded-circle {{ $currentStep >= 2 ? 'bg-primary text-white' : 'bg-light' }}">
                            @if($currentStep > 2)
                                <i class="ti ti-check"></i>
                            @else
                                2
                            @endif
                        </span>
                    </div>
                    <small class="fw-bold">Planificar</small>
                </div>

                <div class="flex-fill border-top {{ $currentStep >= 3 ? 'border-primary' : '' }}" style="margin: 0 -1rem; margin-top: -2rem;"></div>

                {{-- Paso 3: Selección Proveedores --}}
                <div class="text-center flex-fill {{ $currentStep >= 3 ? 'text-primary' : 'text-muted' }}">
                    <div class="mb-2">
                        <span class="avatar avatar-sm rounded-circle {{ $currentStep >= 3 ? 'bg-primary text-white' : 'bg-light' }}">
                            @if($currentStep > 3)
                                <i class="ti ti-check"></i>
                            @else
                                3
                            @endif
                        </span>
                    </div>
                    <small class="fw-bold">Proveedores</small>
                </div>

                <div class="flex-fill border-top {{ $currentStep >= 4 ? 'border-primary' : '' }}" style="margin: 0 -1rem; margin-top: -2rem;"></div>

                {{-- Paso 4: Enviar RFQ --}}
                <div class="text-center flex-fill {{ $currentStep >= 4 ? 'text-primary' : 'text-muted' }}">
                    <div class="mb-2">
                        <span class="avatar avatar-sm rounded-circle {{ $currentStep >= 4 ? 'bg-primary text-white' : 'bg-light' }}">
                            @if($currentStep > 4)
                                <i class="ti ti-check"></i>
                            @else
                                4
                            @endif
                        </span>
                    </div>
                    <small class="fw-bold">Enviar RFQ</small>
                </div>

                <div class="flex-fill border-top {{ $currentStep >= 5 ? 'border-primary' : '' }}" style="margin: 0 -1rem; margin-top: -2rem;"></div>

                {{-- Paso 5: Análisis --}}
                <div class="text-center flex-fill {{ $currentStep >= 5 ? 'text-primary' : 'text-muted' }}">
                    <div class="mb-2">
                        <span class="avatar avatar-sm rounded-circle {{ $currentStep >= 5 ? 'bg-primary text-white' : 'bg-light' }}">
                            5
                        </span>
                    </div>
                    <small class="fw-bold">Análisis</small>
                </div>
            </div>
        </div>
    </div>

    {{-- NUEVO: Mensajes Flash --}}
    @if (session()->has('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="ti ti-check-circle me-2"></i>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="ti ti-alert-triangle me-2"></i>
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Contenido del Paso Actual --}}
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                @if($currentStep === 1)
                    <i class="ti ti-checklist me-2"></i>Paso 1: Validación Técnica
                @elseif($currentStep === 2)
                    <i class="ti ti-calendar me-2"></i>Paso 2: Planificación de Cotización
                @elseif($currentStep === 3)
                    <i class="ti ti-users me-2"></i>Paso 3: Selección de Proveedores
                @elseif($currentStep === 4)
                    <i class="ti ti-send me-2"></i>Paso 4: Enviar RFQ
                @elseif($currentStep === 5)
                    <i class="ti ti-chart-bar me-2"></i>Paso 5: Análisis Comparativo
                @endif
                <span class="badge bg-primary ms-2">{{ $requisition->folio }}</span>
            </h5>
        </div>
        <div class="card-body">
            {{-- Contenido según el paso --}}
            @if($currentStep === 1)
                @include('rfq.wizard-steps.step-1-validation')
            @elseif($currentStep === 2)
                @include('rfq.wizard-steps.step-2-planning')
            @elseif($currentStep === 3)
                @include('rfq.wizard-steps.step-3-suppliers')
            @elseif($currentStep === 4)
                @include('rfq.wizard-steps.step-4-send-rfq')
            @elseif($currentStep === 5)
                @include('rfq.wizard-steps.step-5-analysis')
            @endif
        </div>
    </div>

    {{-- Botones de Navegación --}}
    <div class="d-flex justify-content-between mt-4">
        {{-- Botón Devolver (solo en paso 1) --}}
        @if($currentStep === 1)
            <button type="button" 
                    class="btn btn-danger" 
                    onclick="confirmReject()">
                <i class="ti ti-arrow-back-up me-1"></i> Devolver al Usuario
            </button>
        @else
            <button type="button" 
                    class="btn btn-outline-secondary" 
                    wire:click="previousStep">
                <i class="ti ti-arrow-left me-1"></i> Anterior
            </button>
        @endif

        {{-- Botones de la derecha --}}
        <div class="d-flex gap-2">
            <a href="{{ route('quotes.index') }}" class="btn btn-light">
                <i class="ti ti-x me-1"></i> Cancelar
            </a>
            
            @if($currentStep === 1)
                {{-- Botón Validar y Continuar --}}
                <button type="button" 
                        class="btn btn-primary btn-lg" 
                        wire:click="completeStep1"
                        wire:loading.attr="disabled"
                        wire:target="completeStep1"
                        @if(!($validationData['specs_clear'] ?? false) || 
                            !($validationData['time_feasible'] ?? false) || 
                            !($validationData['alternatives_evaluated'] ?? false))
                            disabled
                        @endif>
                    <span wire:loading.remove wire:target="completeStep1">
                        @if(!($validationData['specs_clear'] ?? false) || 
                            !($validationData['time_feasible'] ?? false) || 
                            !($validationData['alternatives_evaluated'] ?? false))
                            <i class="ti ti-lock me-1"></i> Complete todas las validaciones
                        @else
                            <i class="ti ti-check-double me-1"></i> Validar y Continuar con Cotización
                        @endif
                    </span>
                    <span wire:loading wire:target="completeStep1">
                        <i class="ti ti-loader rotating me-1"></i> Validando...
                    </span>
                </button>
            @elseif($currentStep < 5)
                @if($currentStep === 3)
                    <button type="button" 
                            class="btn btn-primary" 
                            onclick="validateAndProceedStep3({{ $requisition->id }})">
                        Siguiente <i class="ti ti-arrow-right ms-1"></i>
                    </button>
                @else
                    <button type="button" 
                            class="btn btn-primary" 
                            wire:click="nextStep">
                        Siguiente <i class="ti ti-arrow-right ms-1"></i>
                    </button>
                @endif
            @else
                <button type="button" 
                        class="btn btn-success">
                    <i class="ti ti-check me-1"></i> Finalizar
                </button>
            @endif
        </div>
    </div>
</div>

@push('styles')
<style>
    .avatar-sm {
        width: 2.5rem;
        height: 2.5rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
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
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
/**
 * ==========================================================
 * CONSTANTES GLOBALES
 * ==========================================================
 */
const REQUISITION_ID = {{ $requisition->id }};

/**
 * Recarga la página forzando el paso 2, para que Livewire no salte al paso 3
 * por detectar grupos creados en determineCurrentStep().
 */
function reloadStep2() {
    const url = new URL(window.location.href);
    url.searchParams.set('step', '2');
    window.location.href = url.toString();
}

/**
 * ==========================================================
 * MÓDULO: PASO 2 - PLANIFICADOR (AGRUPACIÓN)
 * ==========================================================
 */
window.Step2Planner = (function() {
    'use strict';
    
    let selectedItems = new Set();
    let sortableInstances = [];
    let initialized = false;
    let isInitializing = false;

    function init(requisitionId) {
        if (isInitializing) return;
        if (initialized && sortableInstances.length > 0) return;
        if (!document.getElementById('unassignedItemsList')) return;
        
        isInitializing = true;
        console.log('🏗️ Inicializando Paso 2...');
        
        sortableInstances.forEach(instance => instance.destroy());
        sortableInstances = [];
        selectedItems.clear();
        
        initializeDragAndDrop(requisitionId);
        
        if (!initialized) {
            initializeEventListeners(requisitionId);
            initialized = true;
        }
        
        updateAddSelectedButton();
        isInitializing = false;
    }
    
    function reset() {
        if (!initialized) return;
        sortableInstances.forEach(instance => instance.destroy());
        sortableInstances = [];
        selectedItems.clear();
        initialized = false;
        isInitializing = false;
        console.log('🧹 Limpieza Paso 2 completada');
    }

    function initializeDragAndDrop(requisitionId) {
        const unassignedList = document.getElementById('unassignedItemsList');
        if (unassignedList) {
            sortableInstances.push(new Sortable(unassignedList, {
                group: { name: 'items', pull: 'clone', put: false },
                animation: 150,
                sort: false,
                onStart: (evt) => evt.item.classList.add('dragging'),
                onEnd: (evt) => evt.item.classList.remove('dragging')
            }));
        }

        const newGroupDropZone = document.getElementById('newGroupDropZone');
        if (newGroupDropZone) {
            sortableInstances.push(new Sortable(newGroupDropZone, {
                group: { name: 'items', put: true },
                animation: 150,
                onAdd: function(evt) {
                    const itemId = evt.item.dataset.itemId;
                    createGroupWithItem(requisitionId, itemId);
                    evt.item.remove();
                }
            }));
        }

        document.querySelectorAll('.group-items-drop-zone').forEach(el => {
            sortableInstances.push(new Sortable(el, {
                group: { name: 'items', put: true },
                animation: 150,
                onAdd: function(evt) {
                    const itemId = evt.item.dataset.itemId;
                    const groupId = evt.to.dataset.groupId;
                    addItemToGroup(requisitionId, groupId, itemId);
                    evt.item.remove();
                }
            }));
        });
    }

    function initializeEventListeners(requisitionId) {
        $(document).off('change', '.item-checkbox').on('change', '.item-checkbox', function() {
            const itemId = parseInt($(this).val());
            if (this.checked) {
                selectedItems.add(itemId);
                $(this).closest('.item-card').addClass('selected');
            } else {
                selectedItems.delete(itemId);
                $(this).closest('.item-card').removeClass('selected');
            }
            updateAddSelectedButton();
        });

        $(document).off('click', '#selectAllItems').on('click', '#selectAllItems', function(e) {
            e.preventDefault();
            $('.item-checkbox').prop('checked', true).trigger('change');
        });
        
        $(document).off('click', '#deselectAllItems').on('click', '#deselectAllItems', function(e) {
            e.preventDefault();
            $('.item-checkbox').prop('checked', false).trigger('change');
        });
        
        $(document).off('click', '#addSelectedToGroup').on('click', '#addSelectedToGroup', function(e) {
            e.preventDefault();
            addSelectedItemsToGroup(requisitionId);
        });

        $(document).off('click', '.delete-group-btn').on('click', '.delete-group-btn', function() {
            deleteGroup(requisitionId, $(this).data('group-id'));
        });

        $(document).off('click', '.remove-item-btn').on('click', '.remove-item-btn', function() {
            removeItemFromGroup(requisitionId, $(this).data('group-id'), $(this).data('item-id'));
        });
    }

    function updateAddSelectedButton() {
        const count = selectedItems.size;
        $('#addSelectedToGroup').prop('disabled', count === 0);
        $('#selectedCountText').text(count > 0 ? `Agregar ${count}` : 'Agregar');
    }

    function createGroupWithItem(requisitionId, itemId) {
        Swal.fire({
            title: 'Nombre del Grupo',
            input: 'text',
            inputPlaceholder: 'Ej: Equipo de Oficina',
            showCancelButton: true,
            confirmButtonText: 'Crear',
            inputValidator: (value) => !value && 'Debes ingresar un nombre'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post(`/requisitions/${requisitionId}/quotation-planner/groups`, {
                    _token: '{{ csrf_token() }}',
                    name: result.value,
                    item_ids: [itemId]
                }).done(() => reloadStep2())
                  .fail(() => Swal.fire('Error', 'No se pudo crear el grupo', 'error'));
            }
        });
    }

    function addItemToGroup(requisitionId, groupId, itemId) {
        $.post(`/requisitions/${requisitionId}/quotation-planner/groups/${groupId}/items`, {
            _token: '{{ csrf_token() }}',
            item_ids: [itemId]
        }).done(() => setTimeout(() => reloadStep2(), 500))
          .fail(() => Swal.fire('Error', 'No se pudo agregar', 'error'));
    }

    function removeItemFromGroup(requisitionId, groupId, itemId) {
        $.ajax({
            url: `/requisitions/${requisitionId}/quotation-planner/groups/${groupId}/items`,
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                _method: 'DELETE',
                item_ids: [itemId]
            }
        }).done(() => setTimeout(() => reloadStep2(), 500))
          .fail(() => Swal.fire('Error', 'No se pudo remover', 'error'));
    }

    function deleteGroup(requisitionId, groupId) {
        Swal.fire({
            title: '¿Eliminar grupo?',
            text: 'Las partidas volverán a estar sin agrupar',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `/requisitions/${requisitionId}/quotation-planner/groups/${groupId}`,
                    method: 'POST',
                    data: { _token: '{{ csrf_token() }}', _method: 'DELETE' }
                }).done(() => reloadStep2());
            }
        });
    }

    function addSelectedItemsToGroup(requisitionId) {
        if (selectedItems.size === 0) return;
        const groups = [];
        $('.group-card').each(function() {
            groups.push({ id: $(this).data('group-id'), name: $(this).find('.group-name-display').text().trim() });
        });
        
        if (groups.length === 0) {
            Swal.fire({
                title: 'Crear Nuevo Grupo',
                input: 'text',
                confirmButtonText: 'Crear Grupo',
                showCancelButton: true
            }).then(r => { if(r.isConfirmed) createGroupWithMultipleItems(requisitionId, r.value, Array.from(selectedItems)); });
            return;
        }

        const options = groups.map(g => `<option value="${g.id}">${g.name}</option>`).join('');
        Swal.fire({
            title: `Agregar ${selectedItems.size} partidas`,
            html: `<select id="targetGroup" class="form-select"><option value="">-- Selecciona --</option>${options}<option value="_new">➕ Nuevo grupo</option></select>`,
            confirmButtonText: 'Agregar',
            showCancelButton: true,
            preConfirm: () => document.getElementById('targetGroup').value || Swal.showValidationMessage('Selecciona un grupo')
        }).then(result => {
            if (result.isConfirmed) {
                if (result.value === '_new') {
                    Swal.fire({ title: 'Nombre', input: 'text', showCancelButton: true }).then(r => {
                        if (r.isConfirmed) createGroupWithMultipleItems(requisitionId, r.value, Array.from(selectedItems));
                    });
                } else {
                    addMultipleItemsToGroup(requisitionId, result.value, Array.from(selectedItems));
                }
            }
        });
    }

    function createGroupWithMultipleItems(requisitionId, groupName, itemIds) {
        $.post(`/requisitions/${requisitionId}/quotation-planner/groups`, {
            _token: '{{ csrf_token() }}',
            name: groupName,
            item_ids: itemIds
        }).done(() => reloadStep2());
    }

    function addMultipleItemsToGroup(requisitionId, groupId, itemIds) {
        $.post(`/requisitions/${requisitionId}/quotation-planner/groups/${groupId}/items`, {
            _token: '{{ csrf_token() }}',
            item_ids: itemIds
        }).done(() => reloadStep2());
    }

    return { init, reset };
})();

/**
 * ==========================================================
 * MÓDULO: PASO 3 - SELECCIÓN DE PROVEEDORES
 * ==========================================================
 */
window.Step3Suppliers = (function() {
    'use strict';
    
    let initialized = false;
    let isInitializing = false;
    let select2Instances = [];
    let suppliersData = {};

    function init(requisitionId, existingData = []) {
        if (isInitializing || (initialized && select2Instances.length > 0)) return;
        if (!document.getElementById('suppliersSelectionStep')) return;
        
        isInitializing = true;
        console.log('🏗️ Inicializando Paso 3...');
        cleanup();
        initializeSelect2(existingData);
        
        if (!initialized) {
            initializeEventListeners(requisitionId);
            initialized = true;
        }
        
        isInitializing = false;
    }

    function reset() {
        if (!initialized) return;
        cleanup();
        initialized = false;
        isInitializing = false;
        suppliersData = {};
        console.log('🧹 Limpieza Paso 3 completada');
    }

    function cleanup() {
        select2Instances.forEach(instance => {
            if (instance && instance.data('select2')) instance.select2('destroy');
        });
        select2Instances = [];
    }

    function initializeSelect2(existingData) {
        $('.supplier-select').each(function() {
            const $select = $(this);
            const groupIndex = $select.data('group-index');
            const groupId = $(`.group-supplier-card[data-group-index="${groupIndex}"] .group-id-input`).val();
            
            const existingGroup = existingData.find(item => item.group_id == groupId);
            
            if (existingGroup) {
                if (existingGroup.supplier_ids) $select.val(existingGroup.supplier_ids);
                if (existingGroup.response_deadline) $(`.response-deadline-input[data-group-index="${groupIndex}"]`).val(existingGroup.response_deadline);
                if (existingGroup.notes) $(`.group-notes-input[data-group-index="${groupIndex}"]`).val(existingGroup.notes);
            }
            
            $select.select2({
                theme: 'bootstrap-5',
                placeholder: 'Selecciona proveedores...',
                width: '100%'
            }).on('change', function() {
                updateSupplierCount(groupIndex);
                saveSupplierSelection(groupIndex);
            });
            
            select2Instances.push($select);
            updateSupplierCount(groupIndex);
            if (existingGroup) saveSupplierSelection(groupIndex);
        });
    }

    function initializeEventListeners(requisitionId) {
        $(document).off('change', '.response-deadline-input').on('change', '.response-deadline-input', function() {
            saveSupplierSelection($(this).data('group-index'));
        });
        
        $(document).off('blur', '.group-notes-input').on('blur', '.group-notes-input', function() {
            saveSupplierSelection($(this).data('group-index'));
        });
    }

    function updateSupplierCount(groupIndex) {
        const count = $(`.supplier-select[data-group-index="${groupIndex}"]`).val()?.length || 0;
        const $badge = $(`.supplier-count[data-group-index="${groupIndex}"]`);
        $badge.text(count);
        $badge.removeClass('bg-primary bg-success bg-secondary')
              .addClass(count === 0 ? 'bg-secondary' : (count >= 3 ? 'bg-success' : 'bg-primary'));
    }

    function saveSupplierSelection(groupIndex) {
        const supplierIds = $(`.supplier-select[data-group-index="${groupIndex}"]`).val() || [];
        const deadline = $(`.response-deadline-input[data-group-index="${groupIndex}"]`).val();
        const notes = $(`.group-notes-input[data-group-index="${groupIndex}"]`).val();
        const groupId = $(`.group-supplier-card[data-group-index="${groupIndex}"] .group-id-input`).val();
        
        suppliersData[groupIndex] = {
            group_id: parseInt(groupId),
            supplier_ids: supplierIds.map(id => parseInt(id)),
            response_deadline: deadline,
            notes: notes
        };
    }

    function validateStep() {
        let emptyGroups = [];
        $('.supplier-select').each(function() {
            const $card = $(this).closest('.group-supplier-card');
            const isReadyForAnalysis = $card.data('ready-for-analysis') == 1;
            const hasManualQuote = $card.data('has-manual-quote') == 1;

            if (isReadyForAnalysis || hasManualQuote) {
                return;
            }

            if (($(this).val()?.length || 0) === 0) {
                emptyGroups.push($(this).closest('.card').find('h6').first().text().trim());
            }
        });
        
        if (emptyGroups.length > 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Proveedores faltantes',
                html: `<p>Asigna al menos 1 proveedor a:</p><ul>${emptyGroups.map(g => `<li>${g}</li>`).join('')}</ul>`
            });
            return false;
        }
        return true;
    }

    function getSuppliersData() {
        return Object.values(suppliersData);
    }

    return { init, reset, validateStep, getSuppliersData };
})();

/**
 * ==========================================================
 * MÓDULO: PASO 4 - ENVIAR RFQs
 * ==========================================================
 */
window.Step4RFQs = (function() {
    'use strict';
    
    let initialized = false;
    let isInitializing = false;
    let dataTable = null;

    function init(requisitionId) {
        if (isInitializing || initialized) return;
        if (!document.getElementById('sendRfqStep')) return;
        
        console.log('🏗️ Inicializando Paso 4 - Enviar RFQs');
        isInitializing = true;
        
        cleanup();
        initializeDataTable(requisitionId);
        
        if (!initialized) {
            initializeEventListeners(requisitionId);
            initialized = true;
        }
        
        isInitializing = false;
        console.log('✅ Paso 4 inicializado');
    }

    function reset() {
        if (!initialized) return;
        console.log('🧹 Reseteando Paso 4');
        cleanup();
        initialized = false;
        isInitializing = false;
    }

    function cleanup() {
        if (dataTable) {
            dataTable.destroy();
            dataTable = null;
        }
    }

    function initializeDataTable(requisitionId) {
        dataTable = $('#rfqsWizardTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: `/rfq/wizard/${requisitionId}/datatable`,
                error: function(xhr) {
                    console.error('Error DataTable:', xhr);
                }
            },
            columns: [
                { data: 'folio', name: 'folio', render: data => `<strong>${data}</strong>` },
                { data: 'group_or_item', name: 'quotation_group_id', orderable: false, searchable: false },
                { data: 'suppliers_list', name: 'suppliers_list', orderable: false, searchable: false },
                { data: 'status_badge', name: 'status', className: 'text-center' },
                { data: 'response_deadline', name: 'response_deadline' },
                { data: 'days_remaining', name: 'days_remaining', className: 'text-center', orderable: false },
                { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-center' }
            ],
            order: [[0, 'desc']],
            pageLength: 10,
            language: { url: "{{ asset('assets/vendor/datatables.net/es-MX.json') }}" },
            responsive: true,
            drawCallback: function() {
                $('[data-bs-toggle="tooltip"]').tooltip();
                updateBadges();
            }
        });
    }

    function initializeEventListeners(requisitionId) {
        $(document).off('click', '.btn-send-single-rfq').on('click', '.btn-send-single-rfq', function() {
            confirmAndSendSingle($(this).data('rfq-id'), $(this).data('folio'), $(this).data('emails'));
        });
        
        $(document).off('click', '.btn-view-rfq-details').on('click', '.btn-view-rfq-details', function() {
            window.open(`/rfq/${$(this).data('rfq-id')}`, '_blank');
        });
        
        $(document).off('click', '#sendAllDraftsBtn').on('click', '#sendAllDraftsBtn', function() {
            sendAllDrafts(requisitionId);
        });
        
        $(document).off('click', '.btn-cancel-rfq').on('click', '.btn-cancel-rfq', function() {
            const rfqId = $(this).data('rfq-id');
            const folio = $(this).data('folio');
            confirmAndCancelRfq(rfqId, folio);
        });
    }

    function updateBadges() {
        $.get(`/rfq/wizard/${REQUISITION_ID}/summary`, function(data) {
            if (data.success) {
                $('#draftCountBadge').text(data.drafts);
                $('#sentCountBadge').text(data.sent);
                $('#sendAllDraftsBtn').prop('disabled', data.drafts === 0);
            }
        });
    }

    function confirmAndSendSingle(rfqId, folio, emails) {
        let emailsHtml = emails ? `<div class="mt-3 text-start"><p class="mb-1 text-muted small">Se notificará a:</p><ul class="list-group">${emails.split(', ').map(e => `<li class="list-group-item py-1 small"><i class="ti ti-mail me-2"></i>${e}</li>`).join('')}</ul></div>` : '';

        Swal.fire({
            icon: 'question',
            title: '¿Confirmar envío?',
            html: `¿Enviar la solicitud <strong>${folio}</strong>?${emailsHtml}`,
            showCancelButton: true,
            confirmButtonText: 'Sí, enviar',
            confirmButtonColor: '#198754'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'Enviando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                $.post(`/rfq/${rfqId}/send-single`, { _token: '{{ csrf_token() }}' })
                    .done(response => {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: '¡Enviado!', text: response.message, timer: 2000 });
                            dataTable.draw();
                        }
                    })
                    .fail(xhr => Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.message || 'No se pudo enviar' }));
            }
        });
    }

    function sendAllDrafts(requisitionId) {
        Swal.fire({
            icon: 'warning',
            title: '¿Enviar todas?',
            text: 'Se enviarán notificaciones a todos los proveedores seleccionados.',
            showCancelButton: true,
            confirmButtonText: 'Sí, enviar todas',
            confirmButtonColor: '#198754'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'Enviando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                $.post(`/rfq/wizard/${requisitionId}/send-all`, { _token: '{{ csrf_token() }}' })
                    .done(response => {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: '¡Enviadas!', text: response.message, timer: 2000 });
                            dataTable.draw();
                        }
                    })
                    .fail(xhr => Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.message || 'Error al enviar' }));
            }
        });
    }

    // Agrega esta función privada al final del módulo (antes del return)
    function confirmAndCancelRfq(rfqId, folio) {
        Swal.fire({
            title: `¿Cancelar RFQ ${folio}?`,
            text: "Esta acción no se puede deshacer y notificará a los involucrados.",
            icon: 'warning',
            input: 'textarea',
            inputPlaceholder: 'Escribe el motivo de la cancelación aquí...',
            inputAttributes: { 'aria-label': 'Motivo de la cancelación' },
            showCancelButton: true,
            confirmButtonText: 'Sí, cancelar RFQ',
            confirmButtonColor: '#d33',
            cancelButtonText: 'No, mantener',
            inputValidator: (value) => {
                if (!value || value.length < 10) {
                    return 'El motivo debe tener al menos 10 caracteres.'
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'Cancelando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

                $.post(`/rfq/${rfqId}/cancel`, {
                    _token: '{{ csrf_token() }}',
                    reason: result.value
                })
                .done(response => {
                    if (response.success) {
                        Swal.fire({ 
                            icon: 'success', 
                            title: '¡Eliminado!', 
                            text: response.message, 
                            timer: 2000,
                            showConfirmButton: false // Para que el timer actúe solo
                        }).then(() => {
                            // ✨ AQUÍ VA EL REFRESH:
                            // Al recargar, Livewire detectará que el RFQ ya no existe
                            // y habilitará el grupo en el Paso 3 automáticamente.
                            location.reload(); 
                        });
                    }
                })
                .fail(xhr => {
                    Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.message || 'No se pudo cancelar' });
                });
            }
        });
    }

    return { init, reset };
})();

/**
 * ==========================================================
 * FUNCIONES GLOBALES DE NAVEGACIÓN
 * ==========================================================
 */
function validateAndProceedStep3() {
    if (!Step3Suppliers.validateStep()) return;
    const data = Step3Suppliers.getSuppliersData();

    Swal.fire({ title: 'Guardando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    @this.completeStep3(data)
        .then(() => Swal.close())
        .catch(error => {
            Swal.fire('Error', 'No se pudo procesar la solicitud', 'error');
            console.error(error);
        });
}

/**
 * ==========================================================
 * AUTO-INICIALIZACIÓN CON DEBOUNCER
 * ==========================================================
 */
let wizardDebounceTimer;

const wizardObserver = new MutationObserver(function() {
    clearTimeout(wizardDebounceTimer);

    wizardDebounceTimer = setTimeout(() => {
        const requisitionId = REQUISITION_ID;
        const s2 = document.getElementById('unassignedItemsList');
        const s3 = document.getElementById('suppliersSelectionStep');
        const s4 = document.getElementById('sendRfqStep');
        const s5 = document.getElementById('analysisStep');

        // Manejo Inteligente de Pasos
        s2 ? Step2Planner.init(requisitionId) : Step2Planner.reset();
        s3 ? Step3Suppliers.init(requisitionId, @json($suppliersData ?? [])) : Step3Suppliers.reset();
        s4 ? Step4RFQs.init(requisitionId) : Step4RFQs.reset();
        s5 ? Step5Analysis.init(requisitionId) : Step5Analysis.reset();
    }, 200); 
});

$(document).ready(function() {
    const requisitionId = REQUISITION_ID;
    if (document.getElementById('unassignedItemsList')) Step2Planner.init(requisitionId);
    if (document.getElementById('suppliersSelectionStep')) Step3Suppliers.init(requisitionId, @json($suppliersData ?? []));
    if (document.getElementById('sendRfqStep')) Step4RFQs.init(requisitionId);
});

wizardObserver.observe(document.body, { childList: true, subtree: true });

/**
 * ==========================================================
 * MÓDULO: PASO 5 - ANÁLISIS COMPARATIVO
 * ==========================================================
 */
window.Step5Analysis = (function() {
    'use strict';
    
    let initialized = false;
    let isInitializing = false;
    let dataTable = null;

    function init(requisitionId) {
        if (isInitializing || initialized) return;
        if (!document.getElementById('analysisStep')) return;
        
        console.log('🏗️ Inicializando Paso 5 - Análisis');
        isInitializing = true;
        cleanup();
        initializeDataTable(requisitionId);
        
        initialized = true;
        isInitializing = false;
    }

    function reset() {
        if (!initialized) return;
        console.log('🧹 Reseteando Paso 5');
        cleanup();
        initialized = false;
    }

    function cleanup() {
        if (dataTable) {
            dataTable.destroy();
            dataTable = null;
        }
    }

    function initializeDataTable(requisitionId) {
        dataTable = $('#rfq-analysis-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: `/rfq/wizard/${requisitionId}/analysis-data`, // Nueva ruta filtrada
            columns: [
                { 
                    data: 'folio', 
                    className: 'fw-bold',
                    render: function(data, type, row) {
                        return `
                            <div class="d-flex flex-column">
                                <a href="javascript:void(0)" onclick="Step5Analysis.loadRfqModal(${row.id})" class="fw-bold text-primary">
                                    <i class="ti ti-external-link me-1"></i>${data}
                                </a>
                                <a href="javascript:void(0)" onclick="Step5Analysis.loadReqModal(${row.requisition.id})" class="text-muted small mt-1">
                                    <i class="ti ti-clipboard-list me-1"></i>${row.requisition.folio}
                                </a>
                            </div>`;
                    }
                },
                { 
                    data: 'quotation_group.name',
                    render: (data) => `<span class="fw-semibold">${data || 'Partida Individual'}</span>`
                },
                { 
                    data: 'progress',
                    render: (data) => {
                        let colorClass = data.percent === 100 ? 'bg-success' : (data.percent >= 50 ? 'bg-warning' : 'bg-danger');
                        return `
                            <div class="d-flex align-items-center gap-2" data-bs-toggle="tooltip" title="${data.tooltip}">
                                <div class="progress flex-grow-1" style="height: 8px; background-color: #f1f1f1; border-radius: 10px; overflow: hidden;">
                                    <div class="progress-bar ${colorClass} progress-bar-striped progress-bar-animated" 
                                         role="progressbar" style="width: ${data.percent}%;"></div>
                                </div>
                                <span class="small fw-bold text-dark">${data.label}</span>
                            </div>`;
                    }
                },
                { 
                    data: 'response_deadline',
                    render: (data) => `
                        <div class="d-flex flex-column">
                            <span class="small">${data.display}</span>
                            <small class="${data.is_past ? 'text-danger' : 'text-warning'} fw-bold">${data.human}</small>
                        </div>`
                },
                { 
                    data: 'status',
                    render: (data) => `
                        <span class="badge bg-${data.color} bg-opacity-10 text-${data.color} border border-${data.color} border-opacity-25 px-2 py-1">
                            <i class="ti ${data.icon} me-1"></i>${data.label}
                        </span>`
                },
                {
                    data: null,
                    className: 'text-center',
                    render: (data, type, row) => `
                        <div class="btn-group">
                            <button onclick="Step5Analysis.loadRfqModal(${row.id})" class="btn btn-sm btn-outline-primary" title="Ver Detalles">
                                <i class="ti ti-eye"></i>
                            </button>
                            <a href="/rfq/${row.id}/comparison" class="btn btn-sm btn-outline-success" title="Cuadro Comparativo">
                                <i class="ti ti-scale"></i>
                            </a>
                        </div>`
                }
            ],
            language: { url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json' },
            drawCallback: function() {
                // Inicializar tooltips
                $('[data-bs-toggle="tooltip"]').tooltip();
                // Actualizar contadores superiores
                updateStep5Summary(requisitionId);
            }
        });
    }

    function updateStep5Summary(requisitionId) {
        $.get(`/rfq/wizard/${requisitionId}/summary`, function(data) {
            if (data.success) {
                $('#totalRfqsStep5').text(data.drafts + data.sent);
                $('#completedRfqsStep5').text(data.sent); // O la lógica que prefieras
            }
        });
    }

    function loadRfqModal(id) {
        $('#infoAjaxModal').modal('show');
        $('#modal-loader-content').load(`/rfq/inbox/modal-rfq/${id}`);
    }

    function loadReqModal(id) {
        $('#infoAjaxModal').modal('show');
        $('#modal-loader-content').load(`/rfq/inbox/modal-req/${id}`);
    }

    return { init, reset, loadRfqModal, loadReqModal };
})();

$(document).on('change', '.unlock-group-switch', function() {
    const isChecked = $(this).is(':checked');
    const $card = $(this).closest('.group-supplier-card');
    const $fieldset = $card.find('.group-fieldset');
    const groupName = $card.find('h6').first().text().trim();
    
    if(isChecked) {
        Swal.fire({
            title: '¿Habilitar edición?',
            html: `Al modificar el grupo <strong>${groupName}</strong>, la solicitud que ya enviaste se marcará como <strong>CANCELADA</strong> y se generará una nueva al continuar.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, modificar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#d33'
        }).then((result) => {
            if (result.isConfirmed) {
                // Desbloqueamos visualmente
                $fieldset.prop('disabled', false);
                $card.removeClass('border-info');
                $card.find('.card-header').removeClass('bg-info-subtle').addClass('bg-warning-subtle');
            } else {
                // Si cancela, regresamos el switch a apagado
                $(this).prop('checked', false);
            }
        });
    } else {
        // Bloquear de nuevo si el usuario apaga el switch manualmente
        $fieldset.prop('disabled', true);
        $card.addClass('border-info');
        $card.find('.card-header').addClass('bg-info-subtle').removeClass('bg-warning-subtle');
    }
});
</script>
@endpush
