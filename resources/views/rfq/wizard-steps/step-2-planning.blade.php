{{-- Información de la Requisición --}}
<div class="row mb-3">
    <div class="col-md-3">
        <small class="text-muted d-block">Folio</small>
        <strong>{{ $requisition->folio }}</strong>
    </div>
    <div class="col-md-3">
        <small class="text-muted d-block">Solicitante</small>
        {{ $requisition->requester->name }}
    </div>
    <div class="col-md-3">
        <small class="text-muted d-block">Centro de Costos</small>
        @php
            $primaryCostCenter = $requisition->primaryCostCenter();
        @endphp
        {{ $primaryCostCenter?->code ?? 'N/A' }} - {{ $primaryCostCenter?->name ?? 'Sin centro de costo' }}
    </div>
    <div class="col-md-3">
        <small class="text-muted d-block">Total de Partidas</small>
        <span class="badge bg-primary">{{ $requisition->items->count() }}</span>
    </div>
</div>

{{-- Instrucciones --}}
<div class="alert alert-info mb-3">
    <i class="ti ti-info-circle me-2"></i>
    <strong>Planificador:</strong> Agrupa partidas similares arrastrándolas a la zona de grupos o usa los checkboxes para seleccionar múltiples.
</div>

<div class="row">
    {{-- PARTIDAS SIN AGRUPAR --}}
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-light">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="ti ti-list me-2"></i>Partidas Sin Agrupar
                    </h6>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-primary" id="selectAllItems">
                            <i class="ti ti-checkbox"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="deselectAllItems">
                            <i class="ti ti-square"></i>
                        </button>
                        <button type="button" class="btn btn-success" id="addSelectedToGroup" disabled>
                            <i class="ti ti-plus"></i>
                            <span id="selectedCountText">Agregar</span>
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body p-2" style="max-height: 450px; overflow-y: auto;">
                <div id="unassignedItemsList">
                    @foreach($requisition->items()->whereDoesntHave('quotationGroups')->with('productService', 'expenseCategory')->get() as $item)
                        <div class="card mb-2 item-card" data-item-id="{{ $item->id }}" draggable="true">
                            <div class="card-body p-2">
                                <div class="form-check">
                                    <input class="form-check-input item-checkbox" type="checkbox" value="{{ $item->id }}" id="item-{{ $item->id }}">
                                    <label class="form-check-label small" for="item-{{ $item->id }}">
                                        <strong class="item-name">{{ $item->productService->short_name }}</strong>
                                        <br>
                                        <small class="text-muted">
                                            Cant: {{ $item->quantity }} {{ $item->unit }}
                                        </small>
                                        <br>
                                        <span class="badge bg-info category-badge">
                                            {{ $item->expenseCategory->name }}
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- GRUPOS DE COTIZACIÓN --}}
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-light">
                <h6 class="mb-0">
                    <i class="ti ti-folder me-2"></i>Grupos de Cotización
                    <span class="badge bg-primary ms-2" id="groupsCountBadge">{{ $requisition->quotationGroups->count() }}</span>
                </h6>
            </div>
            <div class="card-body p-2" style="max-height: 450px; overflow-y: auto;">
                <div id="groupsList">
                    @foreach($requisition->quotationGroups()->with('items.productService')->get() as $group)
                        <div class="card mb-2 group-card border-primary" data-group-id="{{ $group->id }}">
                            <div class="card-header bg-primary text-white p-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0 group-name-display">📦 {{ $group->name }}</h6>
                                    <div>
                                        <span class="badge bg-white text-primary me-2">
                                            {{ $group->items->count() }} partidas
                                        </span>
                                        <button type="button" class="btn btn-sm btn-danger delete-group-btn" data-group-id="{{ $group->id }}">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-2 group-items-drop-zone" data-group-id="{{ $group->id }}" style="min-height: 80px; background-color: #f8f9fa;">
                                @foreach($group->items as $item)
                                    <div class="small mb-1 group-item-mini" data-item-id="{{ $item->id }}">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-item-btn" data-item-id="{{ $item->id }}" data-group-id="{{ $group->id }}">
                                            <i class="ti ti-x"></i>
                                        </button>
                                        {{ $item->productService->short_name }}
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Zona para crear nuevo grupo --}}
                <div class="drop-zone p-3 border border-2 border-dashed rounded text-center" 
                     id="newGroupDropZone"
                     style="background-color: #f8f9fa; min-height: 100px;">
                    <i class="ti ti-download text-muted fs-3"></i>
                    <p class="text-muted mb-0 mt-2">
                        📥 Arrastra partidas aquí para crear un nuevo grupo
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Resumen --}}
<div class="card mt-3 border-success">
    <div class="card-body p-3">
        <div class="row text-center">
            <div class="col-4">
                <small class="text-muted d-block">Grupos</small>
                <h4 class="mb-0" id="groupsCount">{{ $requisition->quotationGroups->count() }}</h4>
            </div>
            <div class="col-4">
                <small class="text-muted d-block">Agrupadas</small>
                <h4 class="mb-0 text-success" id="assignedItemsCount">
                    {{ $requisition->items->count() - $requisition->items()->whereDoesntHave('quotationGroups')->count() }}
                </h4>
            </div>
            <div class="col-4">
                <small class="text-muted d-block">Sin Agrupar</small>
                <h4 class="mb-0 text-warning" id="unassignedItemsCount">
                    {{ $requisition->items()->whereDoesntHave('quotationGroups')->count() }}
                </h4>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
.item-card {
    cursor: move;
    transition: all 0.2s ease;
    border-left: 3px solid transparent;
}
.item-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-left-color: #0d6efd;
}
.item-card.selected {
    background-color: #e7f3ff;
    border-left-color: #0d6efd;
}
.item-card.dragging {
    opacity: 0.5;
}
.drop-zone.drag-over {
    background-color: #e7f3ff !important;
    border-color: #0d6efd !important;
}
.group-items-drop-zone {
    min-height: 60px;
}
</style>
@endpush
