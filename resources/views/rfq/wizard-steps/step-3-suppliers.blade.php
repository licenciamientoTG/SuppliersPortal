{{-- ID único para detectar el paso --}}
<div id="suppliersSelectionStep">
    {{-- Información de la Requisición --}}
    <div class="alert alert-primary mb-3">
        <div class="row">
            <div class="col-md-4">
                <small class="text-muted d-block">Folio</small>
                <strong>{{ $requisition->folio }}</strong>
            </div>
            <div class="col-md-4">
                <small class="text-muted d-block">Grupos Creados</small>
                <span class="badge bg-primary">{{ $requisition->quotationGroups->count() }}</span>
            </div>
            <div class="col-md-4">
                <small class="text-muted d-block">Total de Partidas</small>
                <span class="badge bg-info">{{ $requisition->quotationGroups->sum(fn($g) => $g->items->count()) }}</span>
            </div>
        </div>
    </div>

    {{-- Instrucciones --}}
    <div class="alert alert-info mb-3">
        <div class="d-flex">
            <i class="ti ti-info-circle me-2 mt-1"></i>
            <div>
                <strong>Instrucciones:</strong> Selecciona proveedores para cada grupo. 
                <br><small class="text-dark">Nota: Los grupos con solicitudes ya enviadas aparecen bloqueados. Activa "Modificar" si necesitas generar una nueva versión (la anterior se cancelará).</small>
            </div>
        </div>
    </div>

    {{-- Grupos y Selección de Proveedores --}}
    @foreach($requisition->quotationGroups()->with('items.productService', 'items.expenseCategory')->get() as $index => $group)
        @php
            // Buscamos si este grupo tiene una RFQ activa que NO sea borrador
            $activeRfq = $requisition->rfqs
                ->where('quotation_group_id', $group->id)
                ->where('status', '!=', 'CANCELLED')
                ->first();
            
            $isSent = $activeRfq && $activeRfq->status !== 'DRAFT';
        @endphp

        <div class="card mb-3 group-supplier-card {{ $isSent ? 'border-info shadow-sm' : '' }}" data-group-index="{{ $index }}">
            <div class="card-header {{ $isSent ? 'bg-info-subtle border-info' : 'bg-light' }}">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="ti ti-box text-primary"></i> {{ $group->name }}
                        <span class="badge bg-secondary ms-2">{{ $group->items->count() }} partida(s)</span>
                        
                        @if($isSent)
                            <span class="badge bg-info ms-2">
                                <i class="ti ti-send"></i> RFQ ENVIADA ({{ $activeRfq->folio }})
                            </span>
                        @endif
                    </h6>
                    
                    <div class="d-flex align-items-center gap-3">
                        @if($isSent)
                            {{-- Switch de desbloqueo --}}
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input unlock-group-switch" type="checkbox" id="unlock{{ $group->id }}">
                                <label class="form-check-label text-danger fw-bold small" for="unlock{{ $group->id }}">
                                    MODIFICAR
                                </label>
                            </div>
                        @endif

                        <button type="button" 
                                class="btn btn-sm btn-outline-primary toggle-items-btn" 
                                data-bs-toggle="collapse" 
                                data-bs-target="#groupItems{{ $index }}">
                            <i class="ti ti-eye"></i> Ver Partidas
                        </button>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <input type="hidden" class="group-id-input" value="{{ $group->id }}">

                {{-- Partidas del grupo (siempre accesibles) --}}
                <div class="collapse mb-3" id="groupItems{{ $index }}">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered bg-white">
                            <thead class="table-light">
                                <tr>
                                    <th width="5%">#</th>
                                    <th>Descripción</th>
                                    <th width="15%">Cantidad</th>
                                    <th width="20%">Categoría</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($group->items as $itemIndex => $item)
                                <tr>
                                    <td>{{ $itemIndex + 1 }}</td>
                                    <td>
                                        <strong>{{ $item->productService->short_name }}</strong>
                                        @if($item->description)
                                            <br><small class="text-muted">{{ $item->description }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $item->quantity }} {{ $item->unit }}</td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            {{ $item->expenseCategory->name ?? 'N/A' }}
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Contenedor de Inputs con Bloqueo --}}
                <fieldset class="group-fieldset" {{ $isSent ? 'disabled' : '' }}>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">
                                <i class="ti ti-building-store"></i>
                                Proveedores a Invitar <span class="text-danger">*</span>
                            </label>
                            <select class="form-select supplier-select" 
                                    data-group-index="{{ $index }}"
                                    multiple
                                    required>
                                @foreach(\App\Models\Supplier::approved()->orderBy('company_name')->get() as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->company_name }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted d-block mt-1">Mantén <kbd>Ctrl</kbd> para varios</small>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-bold">
                                <i class="ti ti-calendar"></i>
                                Fecha Límite <span class="text-danger">*</span>
                            </label>
                            <input type="date" 
                                   class="form-control response-deadline-input" 
                                   data-group-index="{{ $index }}"
                                   min="{{ now()->addDay()->format('Y-m-d') }}"
                                   value="{{ now()->addDays(7)->format('Y-m-d') }}"
                                   required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-bold">
                                <i class="ti ti-users"></i>
                                Seleccionados
                            </label>
                            <div class="form-control bg-light d-flex align-items-center justify-content-center" style="height: 38px;">
                                <span class="badge bg-secondary supplier-count" data-group-index="{{ $index }}">0</span>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-12">
                            <label class="form-label fw-bold">
                                <i class="ti ti-notes"></i>
                                Notas / Instrucciones Especiales
                            </label>
                            <textarea class="form-control group-notes-input" 
                                      data-group-index="{{ $index }}"
                                      rows="2"
                                      placeholder="Ej: Solicitar muestras, incluir garantía, plazo de entrega especial, etc.">{{ $group->notes }}</textarea>
                        </div>
                    </div>
                </fieldset>
            </div>
        </div>
    @endforeach

    {{-- Resumen Final --}}
    <div class="card border-success mt-3 shadow-sm">
        <div class="card-body">
            <h6 class="mb-2 text-success">
                <i class="ti ti-send me-2"></i>Resumen de Solicitudes
            </h6>
            <p class="mb-0 text-muted">
                Se procesarán <strong class="text-primary">{{ $requisition->quotationGroups->count() }}</strong> 
                grupos de cotización vinculados a 
                <strong class="text-primary">{{ $requisition->quotationGroups->sum(fn($g) => $g->items->count()) }}</strong> 
                partidas en total.
            </p>
        </div>
    </div>
</div>

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
    .supplier-select { font-size: 0.95rem; }
    .group-supplier-card { transition: all 0.3s ease; }
    .bg-info-subtle { background-color: #e7f6f8 !important; }
    .bg-warning-subtle { background-color: #fffce3 !important; }
    
    /* Animación para el badge de enviado */
    @keyframes pulse-subtle {
        0% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.05); opacity: 0.8; }
        100% { transform: scale(1); opacity: 1; }
    }
    .animated.pulse { animation: pulse-subtle 2s infinite; }

    kbd {
        background-color: #e9ecef;
        border: 1px solid #adb5bd;
        border-radius: 3px;
        padding: 2px 6px;
        font-family: monospace;
        font-size: 0.85em;
        color: #333;
    }
</style>
@endpush
