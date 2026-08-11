<div class="card mb-3 border-{{ ['preparing' => 'secondary', 'sent' => 'info', 'received' => 'success', 'awarded' => 'primary', 'completed' => 'dark'][$this->state] }}">
    @php
        $stateBadges = [
            'preparing' => ['class' => 'secondary', 'icon' => 'ti-hammer', 'label' => 'En preparación'],
            'sent' => ['class' => 'info', 'icon' => 'ti-send', 'label' => 'Esperando respuestas'],
            'received' => ['class' => 'success', 'icon' => 'ti-inbox', 'label' => 'Respuestas completas'],
            'awarded' => ['class' => 'primary', 'icon' => 'ti-award', 'label' => 'Adjudicada (en aprobación)'],
            'completed' => ['class' => 'dark', 'icon' => 'ti-check', 'label' => 'Completada'],
        ];
        $badge = $stateBadges[$this->state];
        $rfq = $this->activeRfq;
        $isDirectPurchase = $rfq?->source === 'external';
    @endphp

    <div class="card-header d-flex justify-content-between align-items-center py-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <strong><i class="ti ti-folder me-1"></i>{{ $this->group->name }}</strong>
            <span class="badge bg-{{ $badge['class'] }}"><i class="ti {{ $badge['icon'] }} me-1"></i>{{ $badge['label'] }}</span>
            @if ($rfq)
                <small class="text-muted">{{ $rfq->folio }}</small>
            @endif
            @if ($isDirectPurchase)
                <span class="badge bg-primary"><i class="ti ti-file-invoice me-1"></i>Compra directa</span>
            @endif
        </div>
        @if ($this->state === 'preparing')
            <button class="btn btn-sm btn-outline-danger" wire:click="$parent.cancelGroup({{ $groupId }})"
                    wire:confirm="¿Cancelar este grupo? Sus partidas volverán al panel de sin agrupar.">
                <i class="ti ti-trash"></i>
            </button>
        @endif
    </div>

    <div class="card-body py-2">
        {{-- Partidas del grupo --}}
        <div class="mb-2">
            @foreach ($this->group->items as $item)
                <div class="d-flex align-items-center gap-1 small mb-1" wire:key="gi-{{ $groupId }}-{{ $item->id }}">
                    @if ($this->state === 'preparing')
                        <button class="btn btn-xs btn-outline-danger py-0 px-1"
                                wire:click="$parent.removeItemFromGroup({{ $groupId }}, {{ $item->id }})">
                            <i class="ti ti-x fs-11"></i>
                        </button>
                    @endif
                    <span>{{ $item->productService?->short_name ?? $item->description ?? 'Producto' }}</span>
                    <span class="text-muted">× {{ $item->quantity }}</span>
                </div>
            @endforeach
        </div>

        {{-- Progreso de respuestas (Seguimiento) --}}
        @if (! $isDirectPurchase && in_array($this->state, ['sent', 'received']))
            @php $progress = $this->responseProgress; @endphp
            <div class="d-flex align-items-center gap-2 mb-2">
                <div class="progress flex-grow-1" style="height: 8px;">
                    <div class="progress-bar bg-{{ $progress['responded'] >= $progress['invited'] ? 'success' : 'info' }}"
                         style="width: {{ $progress['invited'] ? round($progress['responded'] / $progress['invited'] * 100) : 0 }}%"></div>
                </div>
                <small class="text-muted text-nowrap">{{ $progress['responded'] }}/{{ $progress['invited'] }} respuestas</small>
                @if ($rfq?->response_deadline)
                    <small class="text-muted text-nowrap"><i class="ti ti-calendar me-1"></i>{{ $rfq->response_deadline->format('d/m/Y') }}</small>
                @endif
            </div>
        @endif

        {{-- Acciones por estado --}}
        <div class="d-flex gap-2 flex-wrap">
            @if (! $isDirectPurchase && in_array($this->state, ['preparing', 'sent']))
                @if ($this->state === 'preparing')
                    <button class="btn btn-sm btn-primary" wire:click="toggleRequestForm">
                        <i class="ti ti-send me-1"></i>Solicitar cotización
                    </button>
                @endif
            @endif

            @if (! $isDirectPurchase && $this->awardableSuppliers->isNotEmpty() && ! in_array($this->state, ['awarded', 'completed']))
                <button class="btn btn-sm btn-success" wire:click="toggleAwardForm"
                        @disabled(! $this->isValidated)
                        @if(! $this->isValidated) title="Firma la validación técnica primero" @endif>
                    <i class="ti ti-award me-1"></i>Adjudicar directo
                </button>
            @endif

            @if (! $isDirectPurchase && $rfq && in_array($this->state, ['sent', 'received', 'awarded']))
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('rfq.comparison.index', $rfq->id) }}">
                    <i class="ti ti-scale me-1"></i>Comparativo
                </a>
            @endif
        </div>

        @if ($isDirectPurchase)
            <div class="alert alert-{{ $this->state === 'awarded' ? 'primary' : 'warning' }} py-2 px-3 mt-2 mb-0 small">
                <i class="ti ti-{{ $this->state === 'awarded' ? 'shield-check' : 'alert-circle' }} me-1"></i>
                @if ($this->state === 'awarded')
                    En validacion presupuestal y autorizacion. Esta compra directa no se enviara a proveedores ni pasara por comparativo.
                @else
                    Precio conocido capturado. Revisa las validaciones mostradas antes de continuar con la autorizacion.
                @endif
            </div>
        @endif

        {{-- Formulario: Solicitar cotización --}}
        @if ($showRequestForm && $this->state === 'preparing')
            <div class="border rounded p-2 mt-2 bg-light">
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label fs-12 mb-1">Proveedores a invitar</label>
                        <select multiple class="form-select form-select-sm" size="5" wire:model="supplierIds">
                            @foreach ($this->selectableSuppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->company_name }}</option>
                            @endforeach
                        </select>
                        @error('supplierIds') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fs-12 mb-1">Fecha límite de respuesta</label>
                        <input type="date" class="form-control form-control-sm" wire:model="responseDeadline">
                        @error('responseDeadline') <small class="text-danger">{{ $message }}</small> @enderror

                        <label class="form-label fs-12 mb-1 mt-2">Notas para el proveedor</label>
                        <textarea class="form-control form-control-sm" rows="2" wire:model="notes"></textarea>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-2">
                    <button class="btn btn-sm btn-outline-secondary" wire:click="saveDraft">
                        <i class="ti ti-device-floppy me-1"></i>Guardar borrador
                    </button>
                    <button class="btn btn-sm btn-primary" wire:click="sendNow"
                            @disabled(! $this->isValidated)
                            @if(! $this->isValidated) title="Firma la validación técnica primero" @endif>
                        <i class="ti ti-send me-1"></i>Enviar ahora
                    </button>
                </div>
            </div>
        @endif

        {{-- Formulario: Adjudicar directo --}}
        @if ($showAwardForm)
            <div class="border rounded p-2 mt-2 bg-success-subtle">
                <div class="row g-2">
                    <div class="col-md-5">
                        <label class="form-label fs-12 mb-1">Proveedor a adjudicar</label>
                        <select class="form-select form-select-sm" wire:model="awardSupplierId">
                            <option value="">— Selecciona —</option>
                            @foreach ($this->awardableSuppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->company_name }}</option>
                            @endforeach
                        </select>
                        @error('awardSupplierId') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                    <div class="col-md-7">
                        <label class="form-label fs-12 mb-1">Justificación (mín. 15 caracteres)</label>
                        <textarea class="form-control form-control-sm" rows="2" wire:model="awardJustification"
                                  placeholder="Ej. Precio vigente capturado de cotización reciente; único proveedor con disponibilidad."></textarea>
                        @error('awardJustification') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                </div>
                <div class="text-end mt-2">
                    <button class="btn btn-sm btn-success" wire:click="awardDirect">
                        <i class="ti ti-award me-1"></i>Confirmar adjudicación
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>
