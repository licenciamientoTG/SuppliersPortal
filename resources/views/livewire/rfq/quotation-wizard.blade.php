<div class="tg-wizard" wire:loading.class="is-loading">
    @php
        $stepMeta = [
            1 => ['label' => 'Revisar', 'title' => 'Confirma la solicitud', 'icon' => 'ti-checklist', 'hint' => 'Valida que la compra tenga información suficiente para pedir precios.'],
            2 => ['label' => 'Preparar', 'title' => 'Prepara paquetes de cotización', 'icon' => 'ti-layout-grid', 'hint' => 'Organiza las partidas que deben cotizarse juntas.'],
            3 => ['label' => 'Invitar', 'title' => 'Configura las invitaciones', 'icon' => 'ti-users', 'hint' => 'Elige proveedores, fechas límite e instrucciones por paquete.'],
            4 => ['label' => 'Lanzar', 'title' => 'Lanza las solicitudes', 'icon' => 'ti-send', 'hint' => 'Revisa los borradores y envíalos a los proveedores.'],
            5 => ['label' => 'Decidir', 'title' => 'Da seguimiento y decide', 'icon' => 'ti-chart-bar', 'hint' => 'Consulta respuestas y compara las cotizaciones recibidas.'],
        ];
        $activeStep = $stepMeta[$currentStep];
    @endphp
    <header class="tg-rfq-page-intro" aria-labelledby="tg-wizard-title">
        <img src="{{ asset('images/logos/Logo.png') }}" alt="TotalGas" class="tg-rfq-logo">
        <div class="tg-rfq-intro-copy">
            <span class="tg-rfq-kicker">Gestión de cotizaciones</span>
            <h1 id="tg-wizard-title">Expediente de cotización</h1>
            <p>{{ $activeStep['hint'] }}</p>
        </div>
        <div class="tg-rfq-meta" aria-label="Contexto de la requisición">
            <div><span>Requisición</span><strong>{{ $requisition->folio }}</strong></div>
            <div><span>Solicitante</span><strong>{{ $requisition->requester?->name ?? 'Sin solicitante' }}</strong></div>
            <div><span>Partidas</span><strong>{{ $requisition->items->count() }}</strong></div>
        </div>
    </header>
    <nav class="tg-workbench-nav" aria-label="Módulos del expediente RFQ">
        <div class="tg-workbench-label"><i class="ti ti-briefcase"></i><span>Expediente RFQ</span></div>
        <div class="tg-workbench-modules">
            @foreach($stepMeta as $number => $meta)
                @if($number === $currentStep)
                    <span class="tg-module is-current" aria-current="page"><i class="ti {{ $meta['icon'] }}"></i><span>{{ $meta['label'] }}</span></span>
                @elseif($isReadOnlyAfterSend)
                    <a href="{{ route('rfq.wizard.steps', $requisition) }}?step={{ $number }}" class="tg-module is-reviewable" aria-label="Consultar {{ $meta['label'] }}"><i class="ti ti-eye"></i><span>{{ $meta['label'] }}</span></a>
                @elseif($number < $currentStep)
                    <a href="{{ route('rfq.wizard.steps', $requisition) }}?step={{ $number }}" class="tg-module is-done" aria-label="Volver a {{ $meta['label'] }}"><i class="ti ti-circle-check"></i><span>{{ $meta['label'] }}</span></a>
                @else
                    <span class="tg-module is-locked" aria-disabled="true"><i class="ti ti-lock"></i><span>{{ $meta['label'] }}</span></span>
                @endif
            @endforeach
        </div>
    </nav>
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
    <main class="card tg-content-card">
        <div class="card-header">
            <div class="d-flex align-items-center gap-2"><span class="tg-content-icon"><i class="ti {{ $activeStep['icon'] }}"></i></span><div><span class="tg-content-kicker">Etapa {{ $currentStep }} de 5</span><h2 class="mb-0 tg-content-title">{{ $activeStep['title'] }}</h2></div></div>
        </div>
        <div class="card-body tg-content-body">
            @if($isReadOnlyAfterSend && $currentStep < 4)
                <div class="tg-readonly-notice" role="status">
                    <span class="tg-readonly-notice-icon"><i class="ti ti-lock-check"></i></span>
                    <div><strong>Consulta protegida</strong><span>Esta requisición ya tiene solicitudes enviadas. Puedes revisar cada etapa, pero ya no se pueden cambiar paquetes, proveedores ni condiciones.</span></div>
                    <a href="{{ route('rfq.wizard.steps', $requisition) }}?step=4" class="btn btn-sm btn-outline-primary">Ver envíos</a>
                </div>
            @endif
            <div @if($isReadOnlyAfterSend && $currentStep < 4) inert aria-label="Contenido disponible solo para consulta" @endif>
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
    </main>

    {{-- Botones de Navegación --}}
    <div class="d-flex justify-content-between mt-4 tg-action-bar">
        {{-- Botón Devolver (solo en paso 1) --}}
        @if($isReadOnlyAfterSend && $currentStep < 4)
            <a href="{{ route('rfq.wizard.steps', $requisition) }}?step=4" class="btn btn-light tg-secondary-action">
                <i class="ti ti-send me-1"></i> Volver a lanzamiento
            </a>
        @elseif($currentStep === 1)
            <button type="button" 
                    class="btn btn-outline-danger"
                    onclick="confirmReject()">
                <i class="ti ti-arrow-back-up me-1"></i> Devolver al Usuario
            </button>
        @else
            <button type="button" 
                    class="btn btn-light tg-secondary-action"
                    wire:click="previousStep">
                <i class="ti ti-arrow-left me-1"></i> Volver a {{ $stepMeta[$currentStep - 1]['label'] }}
            </button>
        @endif

        {{-- Botones de la derecha --}}
        <div class="d-flex gap-2">
            <a href="{{ route('quotes.index') }}" class="btn btn-light">
                <i class="ti ti-x me-1"></i> Cancelar
            </a>
            
            @if($isReadOnlyAfterSend && $currentStep < 4)
                <span class="tg-readonly-action"><i class="ti ti-lock-check"></i> Modo consulta</span>
            @elseif($currentStep === 1)
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
                            <i class="ti ti-check-double me-1"></i> Guardar revisión y preparar paquetes
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
                        Crear borradores <i class="ti ti-arrow-right ms-1"></i>
                    </button>
                @elseif($currentStep === 2 && ! $requisition->quotationGroups()->active()->exists())
                    <button type="button" class="btn btn-primary" disabled title="Crea al menos un grupo antes de continuar">
                        <i class="ti ti-folder-plus me-1"></i> Crea un grupo para continuar
                    </button>
                @else
                    <button type="button" 
                            class="btn btn-primary" 
                            wire:click="nextStep">
                        Abrir {{ $stepMeta[$currentStep + 1]['label'] }} <i class="ti ti-arrow-right ms-1"></i>
                    </button>
                @endif
            @else
                <button type="button" 
                        class="btn btn-success">
                    <i class="ti ti-circle-check me-1"></i> Proceso en seguimiento
                </button>
            @endif
        </div>
    </div>
    <div class="tg-livewire-progress" wire:loading wire:target="completeStep1,nextStep,previousStep,goToStep,completeStep3,saveManualQuote" role="status" aria-live="polite"><span class="tg-spinner" aria-hidden="true"></span><span>Guardando cambios...</span></div>
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

    .tg-wizard { --tg-blue:#188ae2; --tg-ink:#1b2a41; --tg-muted:#66758a; --tg-border:#e2e9f0; color:var(--tg-ink); }
    .tg-rfq-page-intro { display:flex; align-items:center; gap:1rem; margin:.5rem 0 1.35rem; padding:.2rem .1rem; }.tg-rfq-logo { width:2.75rem; height:2.75rem; flex:0 0 auto; object-fit:contain; animation:tg-rfq-logo-spin 8s linear infinite; }.tg-rfq-intro-copy { min-width:0; }.tg-rfq-kicker,.tg-content-kicker { display:block; color:#718096; font-size:.68rem; font-weight:700; letter-spacing:.07em; text-transform:uppercase; }.tg-rfq-intro-copy h1 { margin:.15rem 0; color:#188ae2; font-size:1.45rem; font-weight:700; }.tg-rfq-intro-copy p { margin:0; color:#738196; font-size:.84rem; }.tg-rfq-meta { display:flex; align-items:stretch; margin-left:auto; border:1px solid #e2e9f0; border-radius:.65rem; background:#fff; }.tg-rfq-meta > div { min-width:116px; padding:.55rem .8rem; border-right:1px solid #e2e9f0; }.tg-rfq-meta > div:last-child { border-right:0; }.tg-rfq-meta span,.tg-rfq-meta strong { display:block; }.tg-rfq-meta span { color:#718096; font-size:.67rem; }.tg-rfq-meta strong { overflow:hidden; max-width:180px; margin-top:.15rem; color:#34465a; font-size:.78rem; text-overflow:ellipsis; white-space:nowrap; }.tg-rfq-meta > div:last-child strong { color:#1269ac; font-size:1rem; }
    @keyframes tg-rfq-logo-spin { from { transform:rotate(0deg); } to { transform:rotate(360deg); } }
    .tg-workbench-nav { display:flex; align-items:center; gap:1rem; margin:0 0 1rem; padding:.65rem .85rem; border:1px solid var(--tg-border); border-radius:.75rem; background:#fff; }.tg-workbench-label { display:flex; align-items:center; gap:.45rem; min-width:max-content; color:#34465a; font-size:.8rem; font-weight:700; }.tg-workbench-label i { color:var(--tg-blue); font-size:1rem; }.tg-workbench-modules { display:flex; flex:1; gap:.35rem; overflow-x:auto; }.tg-module { display:flex; align-items:center; gap:.35rem; flex:1 0 auto; justify-content:center; min-width:108px; padding:.55rem .65rem; border:1px solid transparent; border-radius:.55rem; color:#7b8899; background:transparent; font-size:.74rem; font-weight:700; text-decoration:none; }.tg-module.is-done { color:#218b64; cursor:pointer; }.tg-module.is-done:hover { border-color:#a9e7c8; color:#16724f; background:#f4fcf8; }.tg-module.is-reviewable { color:#527b9d; border-color:#e0ebf4; background:#fbfdff; cursor:pointer; }.tg-module.is-reviewable:hover { border-color:#b9dcf6; color:#1269ac; background:#f3faff; }.tg-module.is-current { border-color:#b9dcf6; color:#1269ac; background:#eaf6ff; }.tg-module.is-done i { color:#4bd396; }.tg-module.is-reviewable i { color:#188ae2; }.tg-module.is-locked { opacity:.55; cursor:not-allowed; }
    .tg-content-card { overflow:hidden; border:1px solid var(--tg-border); border-radius:.85rem; background:#fff; box-shadow:0 8px 24px rgba(28,80,120,.05); }.tg-content-card .card-header { border-bottom-color:var(--tg-border); background:#fbfdff; }.tg-content-icon { display:inline-flex; align-items:center; justify-content:center; width:2.15rem; height:2.15rem; border-radius:.6rem; color:#188ae2; background:#eaf6ff; }.tg-content-title { font-size:1rem; }.tg-content-body { padding:1.35rem; }.tg-livewire-progress { display:flex; align-items:center; gap:.5rem; margin-top:.75rem; color:var(--tg-muted); font-size:.78rem; }.tg-spinner { width:1rem; height:1rem; border:2px solid #cfe7f8; border-top-color:var(--tg-blue); border-radius:50%; animation:rotate 1s linear infinite; }
    .tg-wizard .card { border-color:var(--tg-border); border-radius:.7rem; box-shadow:0 3px 12px rgba(28,80,120,.04); }.tg-wizard .form-control,.tg-wizard .form-select { border-color:#d7e0e9; border-radius:.55rem; }.tg-wizard .form-control:focus,.tg-wizard .form-select:focus { border-color:var(--tg-blue); box-shadow:0 0 0 .2rem rgba(24,138,226,.12); }.tg-wizard .btn { border-radius:.55rem; transition:transform .18s ease,box-shadow .18s ease; }.tg-wizard .btn:hover:not(:disabled) { transform:translateY(-2px); box-shadow:0 5px 12px rgba(28,80,120,.12); }.tg-wizard .table thead th { color:#53647a; font-size:.72rem; text-transform:uppercase; letter-spacing:.04em; }.tg-wizard .table tbody tr { transition:background-color .18s ease; }.tg-wizard .table tbody tr:hover { background:#f7fbff; }
    .tg-readonly-notice { display:flex; align-items:center; gap:.7rem; padding:.75rem .9rem; margin:0 0 1rem; border:1px solid #b9dcf6; border-radius:.7rem; color:#36516e; background:#f7fbff; }.tg-readonly-notice-icon { display:inline-flex; align-items:center; justify-content:center; width:2rem; height:2rem; flex:0 0 auto; border-radius:50%; color:#1269ac; background:#eaf6ff; }.tg-readonly-notice strong,.tg-readonly-notice span { display:block; }.tg-readonly-notice strong { font-size:.8rem; }.tg-readonly-notice div span { margin-top:.1rem; color:#66758a; font-size:.76rem; }.tg-readonly-notice .btn { margin-left:auto; flex:0 0 auto; }.tg-readonly-action { display:inline-flex; align-items:center; gap:.35rem; padding:.55rem .7rem; border:1px solid #dcecf8; border-radius:.55rem; color:#526f8d; background:#f7fbff; font-size:.78rem; font-weight:700; }
    @media (max-width:767.98px) { .tg-rfq-page-intro { align-items:flex-start; flex-wrap:wrap; }.tg-rfq-meta { order:3; width:100%; margin-left:0; }.tg-rfq-meta > div { flex:1; min-width:0; }.tg-workbench-nav { align-items:flex-start; flex-direction:column; }.tg-workbench-modules { width:100%; }.tg-module { min-width:44px; }.tg-module span { display:none; }.tg-content-body { padding:.9rem; }.tg-action-bar { align-items:stretch; flex-direction:column; }.tg-action-bar > *, .tg-action-bar .d-flex { width:100%; }.tg-action-bar .btn { flex:1; }.tg-action-bar .d-flex { display:flex; }.tg-readonly-notice { align-items:flex-start; flex-wrap:wrap; }.tg-readonly-notice .btn { width:100%; margin-left:0; } }
    @media (prefers-reduced-motion:reduce) { .tg-wizard *, .tg-wizard *::before, .tg-wizard *::after { animation-duration:.01ms !important; animation-iteration-count:1 !important; scroll-behavior:auto !important; transition-duration:.01ms !important; } }
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
                  .fail(xhr => Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo crear el grupo', 'error'));
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
                }).done(() => reloadStep2())
                  .fail(xhr => Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo eliminar el grupo', 'error'));
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
        }).done(() => reloadStep2())
          .fail(xhr => Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo crear el grupo', 'error'));
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
        if (isInitializing || initialized) return;
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

            $select.off('.tgSupplierSelection').on('change.tgSupplierSelection', function() {
                syncSupplierCards(groupIndex);
                updateSupplierCount(groupIndex);
                saveSupplierSelection(groupIndex);
            });

            syncSupplierCards(groupIndex);
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

        $(document).off('click.tgSupplierCards', '.tg-supplier-card').on('click.tgSupplierCards', '.tg-supplier-card', function() {
            const groupIndex = $(this).data('group-index');
            const supplierId = String($(this).data('supplier-id'));
            const $select = $(`.supplier-select[data-group-index="${groupIndex}"]`);
            const selected = ($select.val() || []).map(String);
            if (!selected.includes(supplierId)) {
                animateSupplierTransfer($(this), $(`.tg-selected-supplier-list[data-group-index="${groupIndex}"]`), 'to-selected');
                $select.val([...selected, supplierId]).trigger('change');
            }
        });

        $(document).off('input.tgSupplierFilter', '.tg-supplier-filter').on('input.tgSupplierFilter', '.tg-supplier-filter', function() {
            const query = $(this).val().trim().toLocaleLowerCase();
            const groupIndex = $(this).data('group-index');
            $(`.tg-supplier-card[data-group-index="${groupIndex}"]`).each(function() {
                const name = String($(this).data('supplier-name')).toLocaleLowerCase();
                $(this).toggleClass('is-filtered-out', query.length > 0 && !name.includes(query));
            });
        });

        $(document).off('click.tgSelectedSuppliers', '.remove-selected-supplier').on('click.tgSelectedSuppliers', '.remove-selected-supplier', function() {
            const groupIndex = $(this).data('group-index');
            const supplierId = String($(this).data('supplier-id'));
            const $select = $(`.supplier-select[data-group-index="${groupIndex}"]`);
            const next = ($select.val() || []).map(String).filter(id => id !== supplierId);
            animateSupplierTransfer($(this), $(`.tg-supplier-card-list`).filter(function() {
                return $(this).closest('.tg-supplier-transfer').data('group-index') === groupIndex;
            }), 'to-available');
            $select.val(next).trigger('change');
        });

        $(document).off('click.tgGroupToggle', '.tg-group-toggle').on('click.tgGroupToggle', '.tg-group-toggle', function(event) {
            if ($(event.target).closest('button, input, label, a, .form-check').length) return;
            const target = document.querySelector($(this).data('group-target'));
            if (target && window.bootstrap?.Collapse) bootstrap.Collapse.getOrCreateInstance(target, { toggle: false }).toggle();
        });

        $(document).off('keydown.tgGroupToggle', '.tg-group-toggle').on('keydown.tgGroupToggle', '.tg-group-toggle', function(event) {
            if (event.key !== 'Enter' && event.key !== ' ') return;
            event.preventDefault();
            $(this).trigger('click');
        });

        $(document).off('shown.bs.collapse.tgGroupToggle hidden.bs.collapse.tgGroupToggle', '.tg-group-configurator')
            .on('shown.bs.collapse.tgGroupToggle hidden.bs.collapse.tgGroupToggle', '.tg-group-configurator', function(event) {
                const expanded = event.type === 'shown';
                const $header = $(this).prev('.tg-group-toggle');
                $header.attr('aria-expanded', expanded ? 'true' : 'false').toggleClass('is-collapsed', !expanded);
                $header.find('.tg-group-chevron i').attr('class', expanded ? 'ti ti-chevron-up' : 'ti ti-chevron-down');
                $header.find('.tg-group-toggle-hint').html(expanded ? '<i class="ti ti-chevron-up"></i> Cerrar' : '<i class="ti ti-click"></i> Abrir');
                if (expanded) {
                    const $card = $(this).closest('.group-supplier-card').addClass('is-opening');
                    setTimeout(() => $card.removeClass('is-opening'), 1150);
                }
            });
    }

    function syncSupplierCards(groupIndex) {
        const selected = ($(`.supplier-select[data-group-index="${groupIndex}"]`).val() || []).map(String);
        $(`.tg-supplier-card[data-group-index="${groupIndex}"]`).each(function() {
            const checked = selected.includes(String($(this).data('supplier-id')));
            $(this).toggleClass('is-selected', checked);
        });

        const $selectedList = $(`.tg-selected-supplier-list[data-group-index="${groupIndex}"]`);
        $selectedList.empty();
        if (selected.length === 0) {
            $selectedList.append($('<div>', { class: 'tg-selected-empty' }).append($('<i>', { class: 'ti ti-users-minus' })).append($('<span>').text('Aún no hay proveedores seleccionados.')));
            return;
        }

        selected.forEach(supplierId => {
            const $source = $(`.tg-supplier-card[data-group-index="${groupIndex}"][data-supplier-id="${supplierId}"]`);
            const name = String($source.data('supplier-name') || 'Proveedor');
            const initials = name.trim().charAt(0).toUpperCase();
            const $row = $('<button>', { type: 'button', class: 'tg-selected-supplier remove-selected-supplier', 'data-group-index': groupIndex, 'data-supplier-id': supplierId, 'aria-label': `Quitar ${name}` });
            $row.append($('<span>', { class: 'tg-supplier-avatar', text: initials }));
            $row.append($('<strong>').text(name));
            $row.append($('<span>', { class: 'tg-supplier-remove' }).append($('<i>', { class: 'ti ti-x' })));
            $selectedList.append($row);
        });
    }

    function animateSupplierTransfer($source, $target, direction) {
        const sourceRect = $source[0]?.getBoundingClientRect();
        const targetRect = $target[0]?.getBoundingClientRect();
        if (!sourceRect || !targetRect || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        const targetX = targetRect.left + Math.min(42, targetRect.width / 2);
        const targetY = targetRect.top + Math.min(42, targetRect.height / 2);
        const $ghost = $source.clone(false, false)
            .removeClass('is-selected is-filtered-out remove-selected-supplier')
            .addClass(`tg-supplier-transfer-ghost ${direction}`)
            .css({ left: sourceRect.left, top: sourceRect.top, width: sourceRect.width, height: sourceRect.height });

        $('body').append($ghost);
        requestAnimationFrame(() => {
            $ghost.css({ transform: `translate(${targetX - sourceRect.left}px, ${targetY - sourceRect.top}px) scale(.82)`, opacity: .18 });
        });
        setTimeout(() => $ghost.remove(), 720);
    }

    function updateSupplierCount(groupIndex) {
        const count = $(`.supplier-select[data-group-index="${groupIndex}"]`).val()?.length || 0;
        const $badge = $(`.supplier-count[data-group-index="${groupIndex}"]`);
        $badge.text(count);
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
