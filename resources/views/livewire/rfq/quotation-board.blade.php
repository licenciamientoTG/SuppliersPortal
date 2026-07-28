<div>
    <div class="container-fluid" x-data="{ dragging: null }">

        {{-- Encabezado --}}
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h4 class="fw-bold mb-0">
                    <i class="ti ti-layout-kanban me-2"></i>Tablero de Cotización
                    <span class="badge bg-warning-subtle text-warning border ms-1">Beta</span>
                </h4>
                <div class="text-muted fs-13 mt-1">
                    <strong>{{ $requisition->folio }}</strong>
                    · {{ $requisition->requester?->name ?? 'Sin solicitante' }}
                    · <span class="badge bg-{{ $requisition->status->badgeClass() }}">{{ $requisition->status->label() }}</span>
                </div>
            </div>
            <a href="{{ route('rfq.wizard.steps', $requisition->id) }}" class="btn btn-sm btn-outline-secondary">
                <i class="ti ti-route me-1"></i>Abrir en wizard clásico
            </a>
        </div>

        {{-- Mensajes flash --}}
        @if (session()->has('success'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if (session()->has('error'))
            <div class="alert alert-danger alert-dismissible fade show">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- Banner de validación técnica --}}
        @if ($this->isValidated() && ! $showValidationDetail)
            <div class="alert alert-success d-flex justify-content-between align-items-center py-2 mb-3">
                <div>
                    <i class="ti ti-shield-check me-1"></i>
                    Validación técnica firmada el {{ \Illuminate\Support\Carbon::parse($requisition->validated_at)->format('d/m/Y H:i') }}.
                </div>
                <button class="btn btn-sm btn-link text-success" wire:click="$toggle('showValidationDetail')">Ver detalle</button>
            </div>
        @else
            <div class="card border-{{ $this->isValidated() ? 'success' : 'warning' }} mb-3">
                <div class="card-header bg-{{ $this->isValidated() ? 'success' : 'warning' }}-subtle d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="ti ti-gavel me-2"></i>Validación técnica de Compras
                        @unless($this->isValidated())
                            <span class="badge bg-warning text-dark ms-2">Pendiente — bloquea envíos y adjudicación</span>
                        @endunless
                    </h6>
                    @if ($this->isValidated())
                        <button class="btn btn-sm btn-link" wire:click="$toggle('showValidationDetail')">Ocultar</button>
                    @endif
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="board_specs_clear"
                                       wire:model.live="validationData.specs_clear" @disabled($this->isValidated())>
                                <label class="form-check-label" for="board_specs_clear">
                                    Especificaciones claras para cotizar
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="board_time_feasible"
                                       wire:model.live="validationData.time_feasible" @disabled($this->isValidated())>
                                <label class="form-check-label" for="board_time_feasible">
                                    Tiempos de entrega factibles
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="board_alternatives"
                                       wire:model.live="validationData.alternatives_evaluated" @disabled($this->isValidated())>
                                <label class="form-check-label" for="board_alternatives">
                                    Alternativas evaluadas
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            <textarea class="form-control form-control-sm" rows="2"
                                      placeholder="Notas de validación (opcional)"
                                      wire:model="validationData.purchasing_notes" @disabled($this->isValidated())></textarea>
                        </div>
                    </div>
                    @unless($this->isValidated())
                        <div class="text-end mt-2">
                            <button class="btn btn-primary btn-sm" wire:click="signValidation"
                                    @disabled(! (($validationData['specs_clear'] ?? false) && ($validationData['time_feasible'] ?? false) && ($validationData['alternatives_evaluated'] ?? false)))>
                                <i class="ti ti-signature me-1"></i>Firmar validación
                            </button>
                        </div>
                    @endunless
                </div>
            </div>
        @endif

        <div class="row g-3">
            {{-- Panel de partidas sin agrupar --}}
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="ti ti-list me-2"></i>Partidas sin agrupar
                            <span class="badge bg-secondary ms-1">{{ $this->unassignedItems->count() }}</span>
                        </h6>
                    </div>
                    <div class="card-body p-2" style="max-height: 60vh; overflow-y: auto;">
                        @forelse ($this->unassignedItems as $item)
                            <div class="card mb-2 board-item-card"
                                 draggable="true"
                                 @dragstart="dragging = {{ $item->id }}"
                                 @dragend="dragging = null"
                                 wire:key="item-{{ $item->id }}">
                                <div class="card-body p-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox"
                                               id="board-item-{{ $item->id }}"
                                               wire:model.live="selectedItemIds.{{ $item->id }}">
                                        <label class="form-check-label small" for="board-item-{{ $item->id }}">
                                            <strong>{{ $item->productService?->short_name ?? $item->description ?? 'Producto' }}</strong><br>
                                            <small class="text-muted">Cant: {{ $item->quantity }} {{ $item->unit }}</small><br>
                                            <span class="badge bg-info">{{ $item->expenseCategory?->name }}</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="text-muted text-center py-3 mb-0">
                                <i class="ti ti-circle-check fs-3 d-block mb-1"></i>
                                Todas las partidas están agrupadas
                            </p>
                        @endforelse
                    </div>
                    @if ($this->unassignedItems->isNotEmpty())
                        <div class="card-footer p-2">
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control" placeholder="Nombre del grupo (opcional)"
                                       wire:model="newGroupName">
                                <button class="btn btn-primary" wire:click="createGroupWithSelection">
                                    <i class="ti ti-plus"></i> Crear grupo
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Tarjetas de grupos --}}
            <div class="col-lg-8">
                @php
                    $followIds = $this->followUpGroupIds;
                    $prepGroups = $this->groups->filter(fn ($g) => ! in_array($g->id, $followIds));
                    $followGroups = $this->groups->filter(fn ($g) => in_array($g->id, $followIds));
                @endphp

                <h6 class="text-uppercase text-muted fs-12 fw-bold mb-2">
                    <i class="ti ti-hammer me-1"></i>Preparación
                </h6>

                @foreach ($prepGroups as $group)
                    <div wire:key="group-wrap-{{ $group->id }}"
                         @dragover.prevent
                         @drop.prevent="if (dragging) { $wire.addItemsToGroup({{ $group->id }}, [dragging]); dragging = null }"
                         :class="dragging ? 'board-drop-ready' : ''">
                        <livewire:rfq.board.group-card
                            :requisition="$requisition"
                            :group-id="$group->id"
                            :key="'card-'.$group->id" />
                    </div>
                @endforeach

                {{-- Zona para crear grupo con drag & drop --}}
                <div class="p-3 border border-2 border-dashed rounded text-center text-muted mb-3"
                     @dragover.prevent
                     @drop.prevent="if (dragging) { $wire.createGroup([dragging]); dragging = null }"
                     :class="dragging ? 'board-drop-ready' : ''">
                    <i class="ti ti-download fs-3"></i>
                    <p class="mb-0 mt-1">Arrastra una partida aquí para crear un grupo nuevo</p>
                </div>

                @if ($followGroups->isNotEmpty())
                    <h6 class="text-uppercase text-muted fs-12 fw-bold mb-2 mt-4">
                        <i class="ti ti-radar-2 me-1"></i>Seguimiento
                    </h6>

                    @foreach ($followGroups as $group)
                        <div wire:key="group-wrap-{{ $group->id }}">
                            <livewire:rfq.board.group-card
                                :requisition="$requisition"
                                :group-id="$group->id"
                                :key="'card-'.$group->id" />
                        </div>
                    @endforeach
                @endif

                @if ($this->groups->isEmpty())
                    <div class="text-center text-muted py-5">
                        <i class="ti ti-folder-plus fs-1 d-block mb-2"></i>
                        Crea tu primer grupo arrastrando partidas o seleccionándolas con los checkboxes.
                    </div>
                @endif
            </div>
        </div>

        {{-- Modal de captura de precio conocido --}}
        <livewire:rfq.board.manual-quote-modal :requisition="$requisition" />
    </div>
</div>

@push('styles')
<style>
    .board-item-card { cursor: grab; border-left: 3px solid transparent; transition: all .15s ease; }
    .board-item-card:hover { border-left-color: #0d6efd; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
    .board-drop-ready { outline: 2px dashed #0d6efd; outline-offset: 2px; border-radius: .375rem; background-color: #f0f7ff; }
</style>
@endpush
