{{-- ID único para detectar el paso --}}
<div id="suppliersSelectionStep">
    @php
        $activeQuotationGroups = $requisition->quotationGroups
            ->where('status', 'ACTIVE')
            ->values();
    @endphp
    <header class="tg-invitations-header">
        <div><h3>Define quién recibirá cada solicitud</h3></div>
        <div class="tg-invitations-summary"><div><span>Paquetes</span><strong>{{ $activeQuotationGroups->count() }}</strong></div><div><span>Partidas</span><strong>{{ $activeQuotationGroups->sum(fn($g) => $g->items->count()) }}</strong></div></div>
    </header>
    <div class="tg-invitations-notice"><i class="ti ti-info-circle"></i><span>Las solicitudes enviadas quedan protegidas. Activa <strong>Modificar</strong> únicamente cuando necesites sustituir una solicitud enviada.</span></div>

    {{-- Grupos y Selección de Proveedores --}}
    @foreach($activeQuotationGroups as $index => $group)
        @php
            // Buscamos si este grupo tiene una RFQ activa que NO sea borrador
            $activeRfq = $requisition->rfqs
                ->where('quotation_group_id', $group->id)
                ->where('status', '!=', 'CANCELLED')
                ->first();

            $isSent = $activeRfq && $activeRfq->status !== 'DRAFT';
            $isReadyForAnalysis = $activeRfq && in_array($activeRfq->status, ['RECEIVED', 'EVALUATED'], true);
            $manualSupplierIds = $activeRfq
                ? $activeRfq->rfqResponses->where('entry_source', 'buyer_manual')->pluck('supplier_id')->unique()
                : collect();
            $statusLabel = match ($activeRfq?->status) {
                'RECEIVED' => 'RFQ RECIBIDA',
                'EVALUATED' => 'RFQ EVALUADA',
                'SENT' => 'RFQ ENVIADA',
                default => 'RFQ ENVIADA',
            };
        @endphp

        <div class="card mb-3 group-supplier-card {{ $isSent ? 'border-info shadow-sm' : '' }}"
             data-group-index="{{ $index }}"
             data-rfq-status="{{ $activeRfq?->status }}"
             data-has-manual-quote="{{ $manualSupplierIds->isNotEmpty() ? '1' : '0' }}"
             data-ready-for-analysis="{{ $isReadyForAnalysis ? '1' : '0' }}">
            <div class="card-header tg-group-toggle is-collapsed {{ $isSent ? 'bg-info-subtle border-info' : 'bg-light' }}" data-group-target="#groupConfigurator{{ $index }}" role="button" tabindex="0" aria-controls="groupConfigurator{{ $index }}" aria-expanded="false">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="ti ti-box text-primary"></i> {{ $group->name }}
                        <span class="badge bg-secondary ms-2">{{ $group->items->count() }} partida(s)</span>
                        <span class="tg-header-supplier-count"><i class="ti ti-users"></i> <strong class="supplier-count" data-group-index="{{ $index }}">0</strong> proveedores</span>
                        
                        @if($isSent)
                            <span class="badge bg-info ms-2">
                                <i class="ti ti-send"></i> {{ $statusLabel }} ({{ $activeRfq->folio }})
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
                        <span class="tg-group-toggle-hint"><i class="ti ti-click"></i> Abrir</span>
                        <span class="tg-group-chevron" aria-hidden="true"><i class="ti ti-chevron-down"></i></span>
                    </div>
                </div>
            </div>

            <div class="collapse tg-group-configurator" id="groupConfigurator{{ $index }}">
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
                                    <th width="20%">Cuenta</th>
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
                        <div class="col-12">
                            @php
                                $availableSuppliers = \App\Models\Supplier::approved()->orderBy('company_name')->get();
                            @endphp
                            <label class="form-label fw-bold">Panel de proveedores <span class="text-danger">*</span></label>
                            <select class="supplier-select d-none" data-group-index="{{ $index }}" multiple required aria-hidden="true" tabindex="-1">
                                @foreach($availableSuppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->company_name }}</option>@endforeach
                            </select>
                            <div class="tg-supplier-transfer" data-group-index="{{ $index }}">
                                <section class="tg-supplier-column">
                                    <header><div><strong>Proveedores disponibles</strong><span>Busca y agrega a esta invitación.</span></div><span class="tg-supplier-total">{{ $availableSuppliers->count() }}</span></header>
                                    <div class="tg-supplier-filter-wrap"><i class="ti ti-search"></i><input type="search" class="tg-supplier-filter" data-group-index="{{ $index }}" placeholder="Buscar por nombre..." autocomplete="off"></div>
                                    <div class="tg-supplier-card-list" aria-label="Proveedores disponibles">
                                    @foreach($availableSuppliers as $supplier)
                                        <button type="button" class="tg-supplier-card" data-group-index="{{ $index }}" data-supplier-id="{{ $supplier->id }}" data-supplier-name="{{ $supplier->company_name }}"><span class="tg-supplier-avatar">{{ strtoupper(mb_substr($supplier->company_name, 0, 1)) }}</span><span class="tg-supplier-name">{{ $supplier->company_name }}</span><span class="tg-supplier-add"><i class="ti ti-plus"></i></span></button>
                                    @endforeach
                                    </div>
                                </section>
                                <div class="tg-transfer-divider" aria-hidden="true"><i class="ti ti-arrow-right"></i></div>
                                <section class="tg-supplier-column tg-selected-column">
                                    <header><div><strong>Seleccionados</strong><span>Se enviará esta solicitud a estos proveedores.</span></div><span class="supplier-count" data-group-index="{{ $index }}">0</span></header>
                                    <div class="tg-selected-supplier-list" data-group-index="{{ $index }}" aria-live="polite"></div>
                                </section>
                            </div>
                        </div>

                    </div>

                    <div class="tg-selection-summary"><i class="ti ti-users-group"></i><span><strong class="supplier-count" data-group-index="{{ $index }}">0</strong> proveedores seleccionados</span></div>

                    <div class="row mt-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold"><i class="ti ti-calendar"></i> Fecha límite <span class="text-danger">*</span></label>
                            <input type="date" class="form-control response-deadline-input" data-group-index="{{ $index }}" min="{{ now()->addDay()->format('Y-m-d') }}" value="{{ now()->addDays(7)->format('Y-m-d') }}" required>
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

                {{-- Cotización manual: fuera del fieldset para que siga disponible aunque el grupo ya tenga RFQ enviada --}}
                <div class="row mt-3">
                    <div class="col-12">
                        <button type="button"
                                class="btn btn-sm btn-outline-success"
                                wire:click="openManualQuoteModal({{ $group->id }})"
                                @disabled($activeRfq && $activeRfq->source !== 'external' && $activeRfq->status !== 'DRAFT')>
                            <i class="ti ti-pencil-plus"></i> Precio conocido / compra directa
                        </button>

                        @if($manualSupplierIds->isNotEmpty())
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                @foreach($activeRfq->suppliers->whereIn('id', $manualSupplierIds) as $manualSupplier)
                                    <span class="badge bg-success-subtle text-success border border-success">
                                        <i class="ti ti-circle-check"></i> Cotización capturada — {{ $manualSupplier->company_name }}
                                        @if($manualSupplier->is_external)
                                            <span class="badge bg-secondary ms-1">Externo</span>
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
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

{{--
    Nota: getManualQuoteGroupProperty()/getManualQuoteSelectableSuppliersProperty() son
    "computed properties" estilo Livewire 2 (magic getXxxProperty()). Livewire 3 ya NO las
    expone automáticamente como variables Blade en vistas @include'das (solo expone
    propiedades públicas reales, ver Utils::getPublicPropertiesDefinedOnSubclass). Se
    resuelven aquí vía $__livewire (la instancia del componente, compartida globalmente por
    Livewire en cada request) para no tener que tocar QuotationWizard::render().
--}}
@php
    $manualQuoteGroup = $__livewire->manualQuoteGroup;
    $manualQuoteSelectableSuppliers = $__livewire->manualQuoteSelectableSuppliers;
@endphp

<div class="modal {{ $showManualQuoteModal ? 'show d-block' : '' }}"
     tabindex="-1"
     style="{{ $showManualQuoteModal ? 'background: rgba(0,0,0,.5);' : '' }}">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="ti ti-pencil-plus"></i> Cotización manual
                    @if($manualQuoteGroup)
                        — {{ $manualQuoteGroup->name }}
                    @endif
                </h5>
                <button type="button" class="btn-close" wire:click="closeManualQuoteModal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Proveedor</label>
                        <select class="form-select" wire:model="manualQuoteSupplierId">
                            <option value="">-- Nuevo proveedor externo --</option>
                            @foreach($manualQuoteSelectableSuppliers as $sel)
                                <option value="{{ $sel->id }}">{{ $sel->company_name }}{{ $sel->is_external ? ' (externo)' : '' }}</option>
                            @endforeach
                        </select>
                        @error('manualQuoteSupplierId') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Fecha de cotización</label>
                        <input type="date" class="form-control" wire:model="manualQuoteQuotationDate">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Vigencia (días)</label>
                        <input type="number" class="form-control" wire:model="manualQuoteValidityDays" min="1" max="365">
                    </div>
                </div>

                @if(! $manualQuoteSupplierId)
                    <div class="card border-info mb-3">
                        <div class="card-body">
                            <h6 class="text-info"><i class="ti ti-building-store"></i> Nuevo proveedor externo</h6>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label small">Razón social *</label>
                                    <input type="text" class="form-control form-control-sm" wire:model="manualQuoteNewSupplier.company_name">
                                    @error('manualQuoteNewSupplier.company_name') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small">RFC *</label>
                                    <input type="text" class="form-control form-control-sm" wire:model="manualQuoteNewSupplier.rfc">
                                    @error('manualQuoteNewSupplier.rfc') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small">Código postal *</label>
                                    <input type="text" class="form-control form-control-sm" wire:model="manualQuoteNewSupplier.postal_code">
                                    @error('manualQuoteNewSupplier.postal_code') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small">Contacto</label>
                                    <input type="text" class="form-control form-control-sm" wire:model="manualQuoteNewSupplier.contact_person">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small">Email</label>
                                    <input type="email" class="form-control form-control-sm" wire:model="manualQuoteNewSupplier.email">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small">Teléfono</label>
                                    <input type="text" class="form-control form-control-sm" wire:model="manualQuoteNewSupplier.phone_number">
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if($manualQuoteGroup)
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Partida</th>
                                    <th width="10%">No disp.</th>
                                    <th width="12%">Precio unit.</th>
                                    <th width="8%">IVA</th>
                                    <th width="8%">Moneda</th>
                                    <th width="10%">Entrega (días)</th>
                                    <th width="15%">Cond. pago</th>
                                    <th width="15%">Garantía</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($manualQuoteGroup->items as $item)
                                    <tr>
                                        <td>{{ $item->productService->short_name ?? $item->description }}</td>
                                        <td class="text-center">
                                            <input type="checkbox" wire:model="manualQuoteItems.{{ $item->id }}.not_available">
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" class="form-control form-control-sm" wire:model="manualQuoteItems.{{ $item->id }}.unit_price">
                                        </td>
                                        <td>
                                            <select class="form-select form-select-sm" wire:model="manualQuoteItems.{{ $item->id }}.iva_rate">
                                                <option value="16">16%</option>
                                                <option value="8">8%</option>
                                                <option value="0">0%</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select class="form-select form-select-sm" wire:model="manualQuoteItems.{{ $item->id }}.currency">
                                                <option value="MXN">MXN</option>
                                                <option value="USD">USD</option>
                                                <option value="EUR">EUR</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" class="form-control form-control-sm" wire:model="manualQuoteItems.{{ $item->id }}.delivery_days">
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm" wire:model="manualQuoteItems.{{ $item->id }}.payment_terms">
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm" wire:model="manualQuoteItems.{{ $item->id }}.warranty_terms">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <div class="row mt-3">
                    <div class="col-md-8">
                        <label class="form-label fw-bold">Adjunto (opcional)</label>
                        <input type="file" class="form-control" wire:model="manualQuoteAttachment" accept=".pdf,.jpg,.jpeg,.png">
                        @error('manualQuoteAttachment') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" wire:click="closeManualQuoteModal">Cancelar</button>
                <button type="button"
                        class="btn btn-success"
                        wire:click="saveManualQuote"
                        wire:loading.attr="disabled"
                        wire:target="saveManualQuote">
                    <span wire:loading.remove wire:target="saveManualQuote"><i class="ti ti-device-floppy"></i> Guardar cotización</span>
                    <span wire:loading wire:target="saveManualQuote"><i class="ti ti-loader rotating"></i> Guardando...</span>
                </button>
            </div>
        </div>
    </div>
</div>

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

    .tg-invitations-header { display:flex; justify-content:space-between; gap:1rem; align-items:flex-start; margin-bottom:1rem; }.tg-invitations-header h3 { margin:.2rem 0; font-size:1.1rem; }.tg-invitations-header p { max-width:650px; margin:0; color:#718096; font-size:.85rem; }.tg-invitations-summary { display:flex; border:1px solid #e2e9f0; border-radius:.7rem; background:#fff; }.tg-invitations-summary > div { min-width:82px; padding:.6rem .8rem; text-align:center; border-right:1px solid #e2e9f0; }.tg-invitations-summary > div:last-child { border:0; }.tg-invitations-summary span,.tg-invitations-summary strong { display:block; }.tg-invitations-summary span { color:#718096; font-size:.68rem; }.tg-invitations-summary strong { font-size:1.05rem; }.tg-invitations-notice { display:flex; align-items:flex-start; gap:.55rem; margin-bottom:1rem; padding:.75rem .9rem; border:1px solid #dcecf8; border-radius:.65rem; background:#f7fbff; color:#526274; font-size:.78rem; }.tg-invitations-notice i { color:#188ae2; font-size:1rem; }
    #suppliersSelectionStep .group-supplier-card { overflow:hidden; border:1px solid #e2e9f0; border-radius:.75rem; box-shadow:none; }.group-supplier-card .card-header { position:relative; padding:.9rem 1rem; border-bottom:1px solid #e2e9f0; background:#f7fbff !important; }.tg-group-toggle { cursor:pointer; user-select:none; }.tg-group-toggle::before { position:absolute; top:0; bottom:0; left:0; width:4px; background:#188ae2; content:""; }.tg-group-toggle:hover { background:#eff8ff !important; }.tg-group-toggle:focus-visible { outline:3px solid rgba(24,138,226,.28); outline-offset:-3px; }.tg-group-toggle.is-collapsed { border-bottom-color:transparent; }.tg-group-toggle.is-collapsed::before { background:#4bd396; }.tg-header-supplier-count { display:inline-flex; align-items:center; gap:.25rem; margin-left:.45rem; padding:.18rem .42rem; border-radius:999px; color:#1269ac; background:#eaf6ff; font-size:.7rem; font-weight:600; }.tg-header-supplier-count strong { font-size:.75rem; }.tg-group-toggle-hint { display:inline-flex; align-items:center; gap:.25rem; color:#1269ac; font-size:.7rem; font-weight:700; }.tg-group-toggle.is-collapsed .tg-group-toggle-hint { color:#218b64; }.tg-group-chevron { color:#718096; transition:transform .25s ease; }.tg-group-toggle.is-collapsed .tg-group-chevron { transform:translateY(1px); }.group-supplier-card.is-opening { animation:tg-group-open .65s ease; }.group-supplier-card.is-opening .card-header::before { animation:tg-logo-colors .65s ease; }.group-supplier-card .card-body { padding:1rem; }.group-supplier-card .form-label { color:#34465a; font-size:.8rem; }.group-supplier-card .group-fieldset { border:0; }.group-supplier-card fieldset:disabled { opacity:.72; }.select2-container--bootstrap-5 .select2-selection { min-height:38px; border-color:#d7e0e9; border-radius:.55rem; }.select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__rendered { padding-top:2px; }.select2-container--open { z-index:1065; }
    @keyframes tg-group-open { 0% { box-shadow:0 0 0 rgba(24,138,226,0); } 35% { box-shadow:0 .3rem 1rem rgba(24,138,226,.16), 0 0 0 3px rgba(75,211,150,.16); } 70% { box-shadow:0 .3rem 1rem rgba(244,190,58,.14), 0 0 0 3px rgba(24,138,226,.1); } 100% { box-shadow:none; } }
    @keyframes tg-logo-colors { 0% { background:#4bd396; } 50% { background:#f4be3a; } 100% { background:#188ae2; } }
    .tg-group-toggle > * { position:relative; z-index:1; }.group-supplier-card.is-opening .tg-group-toggle::after { position:absolute; z-index:0; top:0; right:0; bottom:0; width:52%; background:linear-gradient(90deg,rgba(24,138,226,0),rgba(24,138,226,.34),rgba(75,211,150,.38),rgba(244,190,58,.32),rgba(24,138,226,0)); content:""; filter:blur(1px); pointer-events:none; transform:translateX(-225%); animation:tg-logo-sweep 1.05s cubic-bezier(.2,.7,.25,1) both; }.group-supplier-card.is-opening .tg-group-toggle { overflow:hidden; }.group-supplier-card.is-opening .tg-group-toggle::before { z-index:2; }
    @keyframes tg-logo-sweep { 0% { opacity:0; transform:translateX(-225%); } 12% { opacity:1; } 82% { opacity:1; } 100% { opacity:0; transform:translateX(225%); } }
    .tg-supplier-transfer-ghost { position:fixed !important; z-index:1080; display:grid !important; overflow:hidden; border-color:#9ed5f5 !important; color:#34465a !important; background:#fff !important; box-shadow:0 .5rem 1.4rem rgba(24,138,226,.26),0 0 0 3px rgba(75,211,150,.14); pointer-events:none; transition:transform .68s cubic-bezier(.18,.78,.22,1),opacity .68s ease; }.tg-supplier-transfer-ghost::after { position:absolute; top:0; bottom:0; left:0; width:62%; background:linear-gradient(90deg,rgba(24,138,226,0),rgba(24,138,226,.42),rgba(75,211,150,.48),rgba(244,190,58,.4),rgba(24,138,226,0)); content:""; transform:translateX(-170%); animation:tg-supplier-logo-sweep .68s ease-out both; }.tg-supplier-transfer-ghost > * { position:relative; z-index:1; }
    @keyframes tg-supplier-logo-sweep { 0% { transform:translateX(-170%); } 100% { transform:translateX(245%); } }
    .tg-supplier-transfer { display:grid; grid-template-columns:minmax(0,1fr) 32px minmax(0,1fr); gap:.65rem; align-items:stretch; }.tg-supplier-column { overflow:hidden; min-width:0; border:1px solid #e2e9f0; border-radius:.65rem; background:#fbfdff; }.tg-supplier-column > header { display:flex; align-items:center; justify-content:space-between; gap:.5rem; padding:.7rem .75rem; border-bottom:1px solid #e2e9f0; background:#fff; }.tg-supplier-column header strong,.tg-supplier-column header span { display:block; }.tg-supplier-column header strong { font-size:.8rem; }.tg-supplier-column header div > span { margin-top:.12rem; color:#718096; font-size:.69rem; }.tg-supplier-total,.tg-selected-column .supplier-count { min-width:1.65rem; padding:.18rem .45rem; border-radius:999px; color:#1269ac; background:#eaf6ff; font-size:.72rem; font-weight:700; text-align:center; }.tg-supplier-filter-wrap { position:relative; padding:.6rem; border-bottom:1px solid #e2e9f0; }.tg-supplier-filter-wrap i { position:absolute; top:50%; left:1rem; color:#718096; transform:translateY(-50%); }.tg-supplier-filter { width:100%; padding:.5rem .65rem .5rem 2rem; border:1px solid #d7e0e9; border-radius:.5rem; font-size:.78rem; outline:0; }.tg-supplier-filter:focus { border-color:#188ae2; box-shadow:0 0 0 .15rem rgba(24,138,226,.12); }.tg-supplier-card-list,.tg-selected-supplier-list { max-height:290px; overflow:auto; padding:.45rem; }.tg-supplier-card { display:grid; width:100%; grid-template-columns:auto 1fr auto; gap:.45rem; align-items:center; min-height:42px; margin-bottom:.3rem; padding:.45rem .55rem; border:1px solid transparent; border-radius:.5rem; color:#34465a; background:#fff; cursor:pointer; text-align:left; }.tg-supplier-card:hover { border-color:#9ed5f5; background:#f7fbff; }.tg-supplier-card.is-selected,.tg-supplier-card.is-filtered-out { display:none; }.tg-supplier-avatar { display:inline-flex; align-items:center; justify-content:center; width:1.55rem; height:1.55rem; border-radius:50%; color:#1269ac; background:#eaf6ff; font-size:.64rem; font-weight:700; }.tg-supplier-name { overflow:hidden; font-size:.75rem; font-weight:600; text-overflow:ellipsis; white-space:nowrap; }.tg-supplier-add { color:#188ae2; font-size:.9rem; }.tg-transfer-divider { display:flex; align-items:center; justify-content:center; color:#188ae2; }.tg-selected-column { border-color:#b9dcf6; }.tg-selected-supplier { display:grid; width:100%; grid-template-columns:auto 1fr auto; gap:.45rem; align-items:center; min-height:42px; margin-bottom:.3rem; padding:.45rem .55rem; border:1px solid #dcecf8; border-radius:.5rem; color:#34465a; background:#fff; cursor:pointer; text-align:left; }.tg-selected-supplier:hover { border-color:#e7b8b8; background:#fffafa; }.tg-selected-supplier strong { overflow:hidden; font-size:.75rem; text-overflow:ellipsis; white-space:nowrap; }.tg-supplier-remove { display:inline-flex; align-items:center; justify-content:center; width:1.65rem; height:1.65rem; border-radius:.4rem; color:#d55757; }.tg-selected-supplier:hover .tg-supplier-remove { background:#fff1f1; }.tg-selected-empty { display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:180px; gap:.45rem; color:#8a97a8; font-size:.76rem; text-align:center; }.tg-selected-empty i { color:#b9c7d6; font-size:1.5rem; }.tg-selection-summary { display:flex; align-items:center; gap:.45rem; margin-top:.65rem; color:#526274; font-size:.76rem; }.tg-selection-summary i { color:#188ae2; }.tg-selection-summary .supplier-count { color:#1269ac; font-size:.95rem; }
    @media (max-width:767.98px) { .tg-invitations-header { flex-direction:column; }.tg-invitations-summary { width:100%; }.tg-invitations-summary > div { flex:1; } }
    @media (max-width:767.98px) { .tg-supplier-transfer { grid-template-columns:1fr; }.tg-transfer-divider { min-height:24px; transform:rotate(90deg); }.tg-supplier-card-list,.tg-selected-supplier-list { max-height:230px; } }
</style>
