@extends('layouts.zircos')

@section('title', 'Planificador de Cotización')

@section('page.title', 'Planificador de Cotización')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('requisitions.index') }}">Requisiciones</a></li>
    <li class="breadcrumb-item active">Planificador de Cotización</li>
@endsection

@section('content')
<div class="container-fluid">
    {{-- HEADER --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1">
                        <i class="ti ti-layout-grid me-2"></i>
                        Planificador de Cotización
                    </h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{ route('requisitions.index') }}">Requisiciones</a>
                            </li>
                            <li class="breadcrumb-item">
                                <a href="{{ route('requisitions.inbox.validation') }}">Buzón de Validación</a>
                            </li>
                            <li class="breadcrumb-item active">Planificador</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="{{ route('requisitions.inbox.validation') }}" class="btn btn-outline-secondary">
                        <i class="ti ti-arrow-left"></i> Volver
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- INFORMACIÓN DE LA REQUISICIÓN --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-primary">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <h6 class="text-muted mb-1">Folio</h6>
                            <p class="mb-0 fw-bold">{{ $requisition->folio }}</p>
                        </div>
                        <div class="col-md-3">
                            <h6 class="text-muted mb-1">Solicitante</h6>
                            <p class="mb-0">{{ $requisition->requester->name ?? 'N/A' }}</p>
                        </div>
                        <div class="col-md-3">
                            <h6 class="text-muted mb-1">Centro de Costos</h6>
                            <p class="mb-0">{{ $requisition->primaryCostCenterLabel() }}</p>
                        </div>
                        <div class="col-md-3">
                            <h6 class="text-muted mb-1">Total de Partidas</h6>
                            <p class="mb-0">
                                <span class="badge bg-primary fs-6">{{ $requisition->items->count() }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="row mt-3">
                        @if ($requisition->receivingLocation)
                        <div class="col-md-3">
                            <h6 class="text-muted mb-1">Punto de Entrega</h6>
                            <p class="mb-0">
                                <i class="ti ti-map-pin me-1 text-primary"></i>
                                {{ $requisition->receivingLocation->code }} - {{ $requisition->receivingLocation->name }}
                            </p>
                        </div>
                        @endif
                        <div class="col-12 col-md-9">
                            <h6 class="text-muted mb-1">Descripción</h6>
                            <p class="mb-0">{{ $requisition->description ?? 'Sin descripción' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- INSTRUCCIONES --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-info d-flex align-items-start" role="alert">
                <i class="ti ti-info-circle fs-4 me-3 mt-1"></i>
                <div>
                    <h5 class="alert-heading mb-2">¿Cómo funciona el planificador?</h5>
                    <p class="mb-2">
                        <strong>Escenario A - Cotización integral:</strong> Agrupa partidas de categorías similares para solicitar cotización completa a proveedores especializados.
                    </p>
                    <p class="mb-0">
                        <strong>Escenario B - Cotización por partida:</strong> Deja partidas sin agrupar para cotizarlas individualmente con proveedores especializados.
                    </p>
                    <hr>
                    <p class="mb-0">
                        💡 <strong>Tip:</strong> Arrastra partidas a la zona de grupos o usa los checkboxes para seleccionar varias a la vez.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- ============================================================= --}}
        {{-- SECCIÓN A: PARTIDAS SIN AGRUPAR --}}
        {{-- ============================================================= --}}
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="ti ti-list me-2"></i>
                            Partidas de la Requisición
                        </h5>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllItems">
                                <i class="ti ti-checkbox"></i> Todas
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAllItems">
                                <i class="ti ti-square"></i> Ninguna
                            </button>
                            <button type="button" class="btn btn-sm btn-success" id="addSelectedToGroup" disabled>
                                <i class="ti ti-plus"></i>
                                <span id="selectedCountText">Agregar a grupo</span>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                    {{-- Buscador --}}
                    <div class="mb-3">
                        <input type="text" class="form-control" id="searchItems" placeholder="🔍 Buscar partidas...">
                    </div>

                    {{-- Lista de partidas sin agrupar --}}
                    <div id="unassignedItemsList">
                        @forelse($unassignedItems as $item)
                            @include('requisitions.quotation-planner.partials.item-card', ['item' => $item])
                        @empty
                            <div class="text-center py-5 text-muted" id="emptyUnassignedMessage">
                                <i class="ti ti-check-circle" style="font-size: 3rem;"></i>
                                <p class="mt-3">¡Todas las partidas están agrupadas!</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- ============================================================= --}}
        {{-- SECCIÓN B: GRUPOS DE COTIZACIÓN --}}
        {{-- ============================================================= --}}
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="ti ti-folder me-2"></i>
                            Grupos de Cotización
                        </h5>
                    </div>
                </div>
                <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                    <div id="groupsList">
                        @forelse($groups as $group)
                            @include('requisitions.quotation-planner.partials.group-card', ['group' => $group])
                        @empty
                            <div class="text-center py-5" id="emptyGroupsMessage">
                                <i class="ti ti-folder-plus text-muted" style="font-size: 3rem;"></i>
                                <p class="text-muted mt-3">Aún no has creado grupos</p>
                                <p class="text-muted small">Arrastra partidas aquí</p>
                            </div>
                        @endforelse
                    </div>

                    {{-- Zona de arrastre para crear grupo nuevo --}}
                    <div class="drop-zone mt-3 p-4 border border-2 border-dashed rounded text-center" 
                         id="newGroupDropZone"
                         style="background-color: #f8f9fa; min-height: 120px; display: flex; align-items: center; justify-content: center;">
                        <div>
                            <i class="ti ti-download text-muted" style="font-size: 2rem;"></i>
                            <p class="text-muted mt-2 mb-0">
                                📥 Arrastra partidas aquí para crear un nuevo grupo
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================================= --}}
    {{-- SECCIÓN C: RESUMEN Y ACCIONES --}}
    {{-- ============================================================= --}}
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-success">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="ti ti-chart-pie me-2"></i>
                        Resumen de Estrategia
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center">
                                <h6 class="text-muted mb-2">Grupos Creados</h6>
                                <h2 class="mb-0" id="groupsCount">{{ $groups->count() }}</h2>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h6 class="text-muted mb-2">Partidas Agrupadas</h6>
                                <h2 class="mb-0" id="assignedItemsCount">
                                    {{ $requisition->items->count() - $unassignedItems->count() }}
                                </h2>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h6 class="text-muted mb-2">Partidas Sin Agrupar</h6>
                                <h2 class="mb-0" id="unassignedItemsCount">{{ $unassignedItems->count() }}</h2>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h6 class="text-muted mb-2">Total Partidas</h6>
                                <h2 class="mb-0">{{ $requisition->items->count() }}</h2>
                            </div>
                        </div>
                    </div>

                    @if($unassignedItems->count() > 0)
                    <div class="alert alert-warning mt-3 mb-0" role="alert">
                        <i class="ti ti-alert-triangle me-2"></i>
                        <strong>{{ $unassignedItems->count() }} partidas sin agrupar</strong> 
                        serán cotizadas individualmente.
                    </div>
                    @endif

                    <hr>

                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <button type="button" class="btn btn-outline-secondary" id="saveDraftBtn">
                                <i class="ti ti-device-floppy"></i> Guardar Borrador
                            </button>
                        </div>
                        <div>
                            <a href="{{ route('requisitions.inbox.validation') }}" class="btn btn-outline-secondary me-2">
                                <i class="ti ti-arrow-left"></i> Volver al Buzón
                            </a>
                            <button type="button" class="btn btn-primary" id="continueToSuppliersBtn">
                                Continuar a Proveedores <i class="ti ti-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ============================================================= --}}
{{-- MODALES --}}
{{-- ============================================================= --}}
@include('requisitions.quotation-planner.modals.create-group')
@include('requisitions.quotation-planner.modals.confirm-strategy')

@endsection

{{-- ============================================================= --}}
{{-- STYLES --}}
{{-- ============================================================= --}}
@push('styles')
<style>
/* Estilos para el planificador */
.item-card {
    transition: all 0.3s ease;
    cursor: move;
    border-left: 4px solid transparent;
}

.item-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    border-left-color: #0d6efd;
}

.item-card.selected {
    background-color: #e7f3ff;
    border-left-color: #0d6efd;
}

.item-card.dragging {
    opacity: 0.5;
    transform: scale(0.95);
}

.group-card {
    transition: all 0.3s ease;
}

.group-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.drop-zone {
    transition: all 0.3s ease;
}

.drop-zone.drag-over {
    background-color: #e7f3ff !important;
    border-color: #0d6efd !important;
    transform: scale(1.02);
}

.category-badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

.group-items-list {
    max-height: 300px;
    overflow-y: auto;
}

/* Animaciones */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.item-card, .group-card {
    animation: slideIn 0.3s ease;
}

/* Scrollbar personalizado */
.card-body::-webkit-scrollbar {
    width: 8px;
}

.card-body::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.card-body::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 10px;
}

.card-body::-webkit-scrollbar-thumb:hover {
    background: #555;
}
</style>
@endpush

{{-- ============================================================= --}}
{{-- SCRIPTS --}}
{{-- ============================================================= --}}
@push('scripts')
<!-- Sortable.js para drag & drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<script>
// ============================================================================
// VARIABLES GLOBALES
// ============================================================================
const requisitionId = {{ $requisition->id }};
let groupsData = []; // Almacena los grupos en memoria
let selectedItems = new Set(); // Items seleccionados

// ============================================================================
// INICIALIZACIÓN
// ============================================================================
$(document).ready(function() {
    console.log('🎨 Inicializando Planificador de Cotización');
    
    initializeDragAndDrop();
    initializeEventListeners();
    loadGroupsFromDOM();
    updateSummary();
});

// ============================================================================
// DRAG & DROP CON SORTABLE.JS
// ============================================================================
function initializeDragAndDrop() {
    // Configurar drag en lista de partidas sin agrupar
    const unassignedList = document.getElementById('unassignedItemsList');
    
    if (unassignedList) {
        new Sortable(unassignedList, {
            group: {
                name: 'items',
                pull: 'clone',
                put: false
            },
            animation: 150,
            sort: false,
            onStart: function(evt) {
                evt.item.classList.add('dragging');
            },
            onEnd: function(evt) {
                evt.item.classList.remove('dragging');
            }
        });
    }

    // Configurar drop zone para nuevo grupo
    const newGroupDropZone = document.getElementById('newGroupDropZone');
    
    if (newGroupDropZone) {
        new Sortable(newGroupDropZone, {
            group: {
                name: 'items',
                put: true
            },
            animation: 150,
            onAdd: function(evt) {
                const itemId = evt.item.dataset.itemId;
                createGroupWithItem(itemId);
                evt.item.remove(); // Remover el elemento clonado
            }
        });

        // Eventos visuales para drag over
        newGroupDropZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('drag-over');
        });

        newGroupDropZone.addEventListener('dragleave', function() {
            this.classList.remove('drag-over');
        });

        newGroupDropZone.addEventListener('drop', function() {
            this.classList.remove('drag-over');
        });
    }

    // Configurar sortable en grupos existentes
    initializeGroupsSortable();
}

function initializeGroupsSortable() {
    document.querySelectorAll('.group-items-drop-zone').forEach(function(element) {
        new Sortable(element, {
            group: {
                name: 'items',
                put: true
            },
            animation: 150,
            onAdd: function(evt) {
                const itemId = evt.item.dataset.itemId;
                const groupId = evt.to.dataset.groupId;
                
                console.log('🎯 Sortable onAdd disparado:', {itemId, groupId});
                
                addItemToGroup(groupId, itemId);
                evt.item.remove(); // Remover el elemento clonado
            }
        });
        
        // Agregar eventos visuales
        element.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.style.backgroundColor = '#e7f3ff';
            this.style.borderColor = '#0d6efd';
        });
        
        element.addEventListener('dragleave', function() {
            this.style.backgroundColor = '#f8f9fa';
            this.style.borderColor = '';
        });
        
        element.addEventListener('drop', function() {
            this.style.backgroundColor = '#f8f9fa';
            this.style.borderColor = '';
        });
    });
}

// ============================================================================
// EVENT LISTENERS
// ============================================================================
function initializeEventListeners() {
    // Selección de items
    $(document).on('change', '.item-checkbox', function() {
        const itemId = $(this).closest('.item-card').data('item-id');
        
        if (this.checked) {
            selectedItems.add(itemId);
            $(this).closest('.item-card').addClass('selected');
        } else {
            selectedItems.delete(itemId);
            $(this).closest('.item-card').removeClass('selected');
        }
        
        updateAddSelectedButton();
    });

    // ============================================================================
// SELECCIÓN MÚLTIPLE
// ============================================================================
function updateAddSelectedButton() {
    const count = selectedItems.size;
    const btn = $('#addSelectedToGroup');
    const textSpan = $('#selectedCountText');
    
    if (count > 0) {
        btn.prop('disabled', false);
        textSpan.text(`Agregar ${count} partida${count > 1 ? 's' : ''}`);
    } else {
        btn.prop('disabled', true);
        textSpan.text('Agregar a grupo');
    }
}

function addSelectedItemsToGroup() {
    if (selectedItems.size === 0) {
        Swal.fire('Aviso', 'No hay partidas seleccionadas', 'info');
        return;
    }
    
    // Obtener grupos existentes
    const groups = [];
    $('.group-card').each(function() {
        const groupId = $(this).data('group-id');
        const groupName = $(this).find('.group-name-display').text().trim()
            .replace(/📦/g, '')
            .replace(/\s+/g, ' ')
            .trim();
        
        if (groupName) {
            groups.push({
                id: groupId,
                name: groupName
            });
        }
    });
    
    if (groups.length === 0) {
        // No hay grupos, preguntar si crear uno nuevo
        Swal.fire({
            title: 'No hay grupos creados',
            text: '¿Deseas crear un nuevo grupo para estas partidas?',
            icon: 'question',
            input: 'text',
            inputPlaceholder: 'Nombre del grupo',
            showCancelButton: true,
            confirmButtonText: 'Crear Grupo',
            cancelButtonText: 'Cancelar',
            inputValidator: (value) => {
                if (!value) {
                    return 'Debes ingresar un nombre';
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                createGroupWithMultipleItems(result.value, Array.from(selectedItems));
            }
        });
        return;
    }
    
    // Mostrar selector de grupo
    const groupOptions = groups.map(g => 
        `<option value="${g.id}">${g.name}</option>`
    ).join('');
    
    Swal.fire({
        title: `Agregar ${selectedItems.size} partida${selectedItems.size > 1 ? 's' : ''}`,
        html: `
            <select id="targetGroup" class="form-select">
                <option value="">Selecciona un grupo...</option>
                ${groupOptions}
                <option value="_new">➕ Crear nuevo grupo</option>
            </select>
        `,
        showCancelButton: true,
        confirmButtonText: 'Agregar',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            const groupId = document.getElementById('targetGroup').value;
            if (!groupId) {
                Swal.showValidationMessage('Debes seleccionar un grupo');
                return false;
            }
            return groupId;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            if (result.value === '_new') {
                // Crear nuevo grupo
                Swal.fire({
                    title: 'Nombre del nuevo grupo',
                    input: 'text',
                    inputPlaceholder: 'Ej: Equipo de Oficina',
                    showCancelButton: true,
                    confirmButtonText: 'Crear',
                    cancelButtonText: 'Cancelar',
                    inputValidator: (value) => {
                        if (!value) {
                            return 'Debes ingresar un nombre';
                        }
                    }
                }).then((nameResult) => {
                    if (nameResult.isConfirmed) {
                        createGroupWithMultipleItems(nameResult.value, Array.from(selectedItems));
                    }
                });
            } else {
                // Agregar a grupo existente
                addMultipleItemsToGroup(result.value, Array.from(selectedItems));
            }
        }
    });
}

function createGroupWithMultipleItems(groupName, itemIds) {
    Swal.fire({
        title: 'Creando grupo...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Actualizar groupsData
    const newGroup = {
        name: groupName,
        notes: '',
        item_ids: itemIds
    };
    
    groupsData.push(newGroup);
    
    $.ajax({
        url: `{{ route('requisitions.quotation-planner.save', $requisition) }}`,
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            groups: groupsData
        },
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Grupo creado!',
                    text: `${itemIds.length} partida${itemIds.length > 1 ? 's agregadas' : ' agregada'}`,
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            }
        },
        error: function(xhr) {
            console.error('❌ Error:', xhr);
            Swal.fire('Error', 'No se pudo crear el grupo', 'error');
        }
    });
}

function addMultipleItemsToGroup(groupId, itemIds) {
    Swal.fire({
        title: 'Agregando partidas...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Buscar el grupo en memoria
    let group = groupsData.find(g => g.id == groupId);
    
    if (!group) {
        const $groupCard = $(`.group-card[data-group-id="${groupId}"]`);
        const groupName = $groupCard.find('.group-name-display').text().trim()
            .replace(/📦/g, '')
            .replace(/\s+/g, ' ')
            .trim();
        
        group = {
            id: parseInt(groupId),
            name: groupName,
            notes: '',
            item_ids: []
        };
        groupsData.push(group);
    }
    
    // Agregar items que no existan
    itemIds.forEach(itemId => {
        const itemIdInt = parseInt(itemId);
        if (!group.item_ids.includes(itemIdInt)) {
            group.item_ids.push(itemIdInt);
        }
    });
    
    $.ajax({
        url: `{{ route('requisitions.quotation-planner.save', $requisition) }}`,
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            groups: groupsData
        },
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Partidas agregadas!',
                    text: `${itemIds.length} partida${itemIds.length > 1 ? 's agregadas' : ' agregada'} al grupo`,
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            }
        },
        error: function(xhr) {
            console.error('❌ Error:', xhr);
            Swal.fire('Error', 'No se pudo agregar las partidas', 'error');
        }
    });
}

    // Seleccionar todas
    $('#selectAllItems').click(function() {
        $('.item-checkbox').prop('checked', true).trigger('change');
    });

    // Deseleccionar todas
    $('#deselectAllItems').click(function() {
        $('.item-checkbox').prop('checked', false).trigger('change');
    });

    // NUEVO: Agregar seleccionadas a grupo
    $('#addSelectedToGroup').click(function() {
        addSelectedItemsToGroup();
    });

    // Buscador de partidas
    $('#searchItems').on('keyup', function() {
        const searchTerm = $(this).val().toLowerCase();
        
        $('#unassignedItemsList .item-card').each(function() {
            const itemName = $(this).find('.item-name').text().toLowerCase();
            const itemCategory = $(this).find('.category-badge').text().toLowerCase();
            
            if (itemName.includes(searchTerm) || itemCategory.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    // Guardar borrador
    $('#saveDraftBtn').click(function() {
        saveStrategy(false);
    });

    // Continuar a proveedores
    $('#continueToSuppliersBtn').click(function() {
        $('#confirmStrategyModal').modal('show');
    });

    // Confirmar estrategia
    $('#confirmStrategyBtn').click(function() {
        saveStrategy(true);
    });
}

// ============================================================================
// GESTIÓN DE GRUPOS
// ============================================================================
function loadGroupsFromDOM() {
    groupsData = [];
    
    $('.group-card').each(function() {
        const groupId = $(this).data('group-id');
        const groupName = $(this).find('.group-name-display').text().trim()
            .replace(/📦/g, '')
            .replace(/\s+/g, ' ')
            .trim();
        
        const items = [];
        
        $(this).find('.group-item-mini').each(function() {
            const itemId = parseInt($(this).data('item-id'));
            if (itemId && !isNaN(itemId)) {
                items.push(itemId);
            }
        });
        
        // Solo agregar si tiene nombre válido
        if (groupName && groupName.length > 0) {
            const groupObj = {
                name: groupName,
                item_ids: items
            };
            
            // ⚠️ IMPORTANTE: Solo incluir ID si existe Y es válido
            if (groupId && !isNaN(parseInt(groupId)) && parseInt(groupId) > 0) {
                groupObj.id = parseInt(groupId);
                console.log(`✅ Grupo con ID: ${groupObj.id} - ${groupName}`);
            } else {
                console.log(`⚠️ Grupo SIN ID (será creado): ${groupName}`);
            }
            
            groupsData.push(groupObj);
        }
    });
    
    console.log('📦 Grupos cargados desde DOM:', groupsData);
}

function createGroupWithItem(itemId) {
    Swal.fire({
        title: 'Nombre del Grupo',
        input: 'text',
        inputPlaceholder: 'Ej: Equipo de Oficina',
        showCancelButton: true,
        confirmButtonText: 'Crear Grupo',
        cancelButtonText: 'Cancelar',
        inputValidator: (value) => {
            if (!value) {
                return 'Debes ingresar un nombre';
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const groupName = result.value;
            createGroup(groupName, [itemId]);
        }
    });
}

function createGroup(name, itemIds = []) {
    $.ajax({
        url: `{{ route('requisitions.quotation-planner.groups.create', $requisition) }}`,
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            name: name,
            item_ids: itemIds
        },
        success: function(response) {
            if (response.success) {
                location.reload(); // Recargar para mostrar el nuevo grupo
            }
        },
        error: function(xhr) {
            Swal.fire('Error', 'No se pudo crear el grupo', 'error');
        }
    });
}

function addItemToGroup(groupId, itemId) {
    console.log(`📥 Agregando item ${itemId} al grupo ${groupId}`);
    
    // Buscar el grupo en memoria
    let group = groupsData.find(g => g.id == groupId);
    
    if (!group) {
        const $groupCard = $(`.group-card[data-group-id="${groupId}"]`);
        const groupName = $groupCard.find('.group-name-display').text().trim().replace(/📦\s*/g, '').replace(/\s+/g, ' ');
        
        group = {
            id: parseInt(groupId),
            name: groupName,
            item_ids: []
        };
        groupsData.push(group);
    }
    
    const itemIdInt = parseInt(itemId);
    if (!group.item_ids.includes(itemIdInt)) {
        group.item_ids.push(itemIdInt);
    }
    
    console.log('📦 Grupos actualizados:', groupsData);
    
    Swal.fire({
        title: 'Agregando partida...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    $.ajax({
        url: `{{ route('requisitions.quotation-planner.save', $requisition) }}`,
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            groups: groupsData
        },
        success: function(response) {
            console.log('✅ Guardado exitoso:', response);
            
            Swal.fire({
                icon: 'success',
                title: 'Partida agregada',
                text: 'Recargando...',
                timer: 1000,
                showConfirmButton: false
            }).then(() => {
                location.reload(); // ← ESTO DEBE RECARGAR LA PÁGINA
            });
        },
        error: function(xhr) {
            console.error('❌ Error al guardar:', xhr);
            
            let errorMsg = 'No se pudo agregar la partida';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg += '\n\n' + xhr.responseJSON.message;
            }
            
            Swal.fire('Error', errorMsg, 'error');
        }
    });
}

// ============================================================================
// GUARDAR ESTRATEGIA
// ============================================================================
function saveStrategy(redirect = false) {
    const saveBtn = redirect ? $('#confirmStrategyBtn') : $('#saveDraftBtn');
    const originalText = saveBtn.html();
    
    saveBtn.prop('disabled', true).html('<i class="ti ti-loader rotating"></i> Guardando...');
    
    loadGroupsFromDOM(); // Actualizar desde DOM
    
    $.ajax({
        url: `{{ route('requisitions.quotation-planner.save', $requisition) }}`,
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            groups: groupsData
        },
        success: function(response) {
            console.log('✅ Respuesta exitosa:', response);
            
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Guardado!',
                    text: response.message,
                    timer: 2000,
                    showConfirmButton: false
                });
                
                updateSummary();
                
                if (redirect) {
                    setTimeout(() => {
                        window.location.href = `{{ route('rfq.select-suppliers', $requisition) }}`;
                    }, 2000);
                }
            }
        },
        error: function(xhr) {
            console.error('❌ Error:', xhr);
            console.error('❌ Response:', xhr.responseJSON);
            
            let errorMsg = 'No se pudo guardar la estrategia';
            
            if (xhr.responseJSON && xhr.responseJSON.errors) {
                errorMsg += '\n\n' + Object.values(xhr.responseJSON.errors).flat().join('\n');
            }
            
            Swal.fire('Error', errorMsg, 'error');
        },
        complete: function() {
            saveBtn.prop('disabled', false).html(originalText);
        }
    });
}

// ============================================================================
// ACTUALIZAR RESUMEN
// ============================================================================
function updateSummary() {
    const totalItems = {{ $requisition->items->count() }};
    const groupsCount = groupsData.length;
    
    let assignedItems = 0;
    groupsData.forEach(group => {
        assignedItems += group.item_ids.length;
    });
    
    const unassignedItems = totalItems - assignedItems;
    
    $('#groupsCount').text(groupsCount);
    $('#assignedItemsCount').text(assignedItems);
    $('#unassignedItemsCount').text(unassignedItems);
}
</script>
@endpush
