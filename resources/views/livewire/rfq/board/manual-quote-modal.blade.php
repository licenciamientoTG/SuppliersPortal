<div x-data="{ purchaseHistoryOpen: false }"
     x-on:keydown.escape.window="if (purchaseHistoryOpen) { purchaseHistoryOpen = false; $wire.closePurchaseHistory() }">
    @if ($show && $this->group)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
            <div class="modal-dialog modal-fullscreen-xl-down modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content mq-shell">
                    <div class="modal-header py-3 mq-header">
                        <h5 class="modal-title">
                            <i class="ti ti-pencil-dollar me-2"></i>Capturar precio conocido — {{ $this->group->name }}
                        </h5>
                        <button type="button" class="btn-close" wire:click="close"></button>
                    </div>

                    <div class="modal-body mq-body">
                        <div class="mq-route-card">
                            <i class="ti ti-info-circle me-1"></i>
                            Esta ruta adjudica directamente al proveedor capturado y continúa con presupuesto y autorización. No se enviará una RFQ ni se realizará comparativo.
                        </div>
                        <div class="small text-muted mb-3">El proveedor seleccionado aporta su moneda y condiciones de pago. Puedes sustituir cualquier partida con información de los últimos pedidos emitidos.</div>
                        {{-- Proveedor --}}
                        <div class="row g-2 mb-3 mq-section">
                            <div class="col-md-6">
                                <label class="form-label fs-12 mb-1">Proveedor</label>
                                <select class="form-select form-select-sm" wire:model.live="supplierId">
                                    <option value="">— Proveedor externo nuevo —</option>
                                    @foreach ($this->selectableSuppliers as $sel)
                                        <option value="{{ $sel->id }}">{{ $sel->company_name }}{{ $sel->is_external ? ' (externo)' : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fs-12 mb-1">Fecha de cotización</label>
                                <input type="date" class="form-control form-control-sm" wire:model="quotationDate">
                                @error('quotationDate') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fs-12 mb-1">Vigencia (días)</label>
                                <input type="number" class="form-control form-control-sm" wire:model="validityDays" min="1" max="365">
                                @error('validityDays') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        </div>

                        {{-- Alta de proveedor externo --}}
                        @unless ($supplierId)
                            <div class="mq-external-card mb-3">
                                <p class="fs-12 text-muted mb-2"><i class="ti ti-user-plus me-1"></i>Datos del proveedor externo nuevo</p>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <input type="text" class="form-control form-control-sm" placeholder="Razón social *"
                                               wire:model="newSupplier.company_name">
                                        @error('newSupplier.company_name') <small class="text-danger">{{ $message }}</small> @enderror
                                    </div>
                                    <div class="col-md-3">
                                        <input type="text" class="form-control form-control-sm" placeholder="RFC *"
                                               wire:model="newSupplier.rfc">
                                        @error('newSupplier.rfc') <small class="text-danger">{{ $message }}</small> @enderror
                                    </div>
                                    <div class="col-md-2">
                                        <input type="text" class="form-control form-control-sm" placeholder="C.P. *"
                                               wire:model="newSupplier.postal_code">
                                        @error('newSupplier.postal_code') <small class="text-danger">{{ $message }}</small> @enderror
                                    </div>
                                    <div class="col-md-3">
                                        <input type="email" class="form-control form-control-sm" placeholder="Email"
                                               wire:model="newSupplier.email">
                                        @error('newSupplier.email') <small class="text-danger">{{ $message }}</small> @enderror
                                    </div>
                                    <div class="col-md-4">
                                        <input type="text" class="form-control form-control-sm" placeholder="Persona de contacto"
                                               wire:model="newSupplier.contact_person">
                                    </div>
                                    <div class="col-md-3">
                                        <input type="text" class="form-control form-control-sm" placeholder="Teléfono"
                                               wire:model="newSupplier.phone_number">
                                        @error('newSupplier.phone_number') <small class="text-danger">{{ $message }}</small> @enderror
                                    </div>
                                </div>
                            </div>
                        @endunless

                        {{-- Partidas --}}
                        <div class="table-responsive mq-table-shell">
                            <table class="table table-sm align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Partida</th>
                                        <th width="90">N/D</th>
                                        <th width="130">Precio unit. *</th>
                                        <th width="90">IVA % *</th>
                                        <th width="90">Moneda</th>
                                        <th width="110">Entrega (días) *</th>
                                        <th width="150">Pago</th>
                                        <th width="110">Historial</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($this->group->items as $item)
                                        <tr wire:key="mq-{{ $item->id }}">
                                            <td class="fs-13">
                                                <strong>{{ $item->productService?->short_name ?? $item->description ?? 'Producto' }}</strong>
                                                <br><small class="text-muted">Cant: {{ $item->quantity }} {{ $item->unit }}</small>
                                            </td>
                                            <td>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox"
                                                           wire:model.live="items.{{ $item->id }}.not_available">
                                                </div>
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                                                       wire:model="items.{{ $item->id }}.unit_price"
                                                       @disabled($items[$item->id]['not_available'] ?? false)>
                                                @error("items.{$item->id}.unit_price") <small class="text-danger">{{ $message }}</small> @enderror
                                            </td>
                                            <td>
                                                <select class="form-select form-select-sm"
                                                        wire:model="items.{{ $item->id }}.iva_rate"
                                                        @disabled($items[$item->id]['not_available'] ?? false)>
                                                    <option value="16">16</option>
                                                    <option value="8">8</option>
                                                    <option value="0">0</option>
                                                </select>
                                            </td>
                                            <td>
                                                <select class="form-select form-select-sm" wire:model="items.{{ $item->id }}.currency"
                                                        @disabled($items[$item->id]['not_available'] ?? false)>
                                                    <option value="MXN">MXN</option><option value="USD">USD</option><option value="EUR">EUR</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="number" min="0" class="form-control form-control-sm"
                                                       wire:model="items.{{ $item->id }}.delivery_days"
                                                       @disabled($items[$item->id]['not_available'] ?? false)>
                                                @error("items.{$item->id}.delivery_days") <small class="text-danger">{{ $message }}</small> @enderror
                                            </td>
                                            <td class="fs-12">
                                                @if ($ref = $priceReferences[$item->id] ?? null)
                                                    <span class="text-success">
                                                        <i class="ti ti-history me-1"></i>${{ number_format($ref['unit_price'], 2) }}
                                                        — {{ $ref['supplier_name'] }}
                                                    </span>
                                                    <br><small class="text-muted">{{ $ref['quotation_date'] }} · {{ $ref['rfq_folio'] }}</small>
                                                @else
                                                    <span class="text-muted">Sin historial</span>
                                                @endif
                                                @if (! empty($items[$item->id]['payment_terms']))
                                                    <br><small class="text-muted">Pago: {{ $items[$item->id]['payment_terms'] }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary w-100"
                                                        x-on:click="purchaseHistoryOpen = true; $wire.openPurchaseHistory({{ $item->id }})">
                                                    <i class="ti ti-history"></i> Pedidos
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Adjunto --}}
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label fs-12 mb-1">Cotización adjunta (PDF/imagen, opcional)</label>
                                <input type="file" class="form-control form-control-sm" wire:model="attachment">
                                @error('attachment') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer py-3 mq-footer">
                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="close">Cancelar</button>
                        <button type="button" class="btn btn-sm btn-primary" wire:click="save" wire:loading.attr="disabled">
                            <i class="ti ti-device-floppy me-1"></i>Guardar cotización
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

        <div x-cloak x-show="purchaseHistoryOpen" x-transition.opacity class="modal fade show d-block" tabindex="-1"
             x-on:click.self="purchaseHistoryOpen = false; $wire.closePurchaseHistory()"
             style="z-index:1070; background:rgba(17,37,57,.58);">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-light">
                        <div><h6 class="modal-title mb-0"><i class="ti ti-history me-2"></i>Últimos 10 pedidos</h6><small class="text-muted">Selecciona una referencia para importar sus datos.</small></div>
                        <button type="button" class="btn-close" x-on:click="purchaseHistoryOpen = false; $wire.closePurchaseHistory()"></button>
                    </div>
                    <div class="modal-body p-0">
                        <div wire:loading wire:target="openPurchaseHistory" class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Cargando historial…</div>
                        <div wire:loading.remove wire:target="openPurchaseHistory">
                            @if ($historyItemId)
                                @forelse ($purchaseHistory as $reference)
                                    <button type="button" class="w-100 d-flex justify-content-between align-items-center gap-3 p-3 border-0 border-bottom bg-white text-start" wire:click="applyPurchaseHistory({{ $reference['id'] }})">
                                        <span><strong class="d-block">{{ $reference['supplier_name'] }}</strong><small class="text-muted">{{ $reference['folio'] }} · {{ $reference['ordered_at'] ? \Illuminate\Support\Carbon::parse($reference['ordered_at'])->format('d/m/Y') : 'Sin fecha' }}</small></span>
                                        <span class="text-end"><strong class="d-block">${{ number_format($reference['unit_price'], 2) }} {{ $reference['currency'] }}</strong><small class="text-muted">IVA {{ number_format($reference['iva_rate'], 2) }}% · {{ $reference['delivery_days'] ?? '—' }} días</small></span>
                                        <i class="ti ti-arrow-up-right text-primary fs-5"></i>
                                    </button>
                                @empty
                                    <div class="text-center text-muted py-5">No hay pedidos emitidos para este producto.</div>
                                @endforelse
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
</div>

@push('styles')
<style>
    [x-cloak] { display: none !important; }
    .mq-shell { border: 0; border-radius: .85rem; box-shadow: 0 1.2rem 3.2rem rgba(20,54,80,.24); overflow: hidden; }
    .mq-header { align-items: center; background: #f7fbff; border-bottom: 1px solid #e2e9f0; color: #153952; }
    .mq-body { background: #f5f5f5; padding: 1.25rem; }
    .mq-route-card { background: #edf8ff; border: 1px solid #b9dbf6; border-radius: .75rem; color: #245476; font-size: .84rem; margin-bottom: 1rem; padding: .75rem 1rem; }
    .mq-section { background: #fff; border: 1px solid #e2e9f0; border-radius: .78rem; padding: 1rem; }
    .mq-external-card { background: #f7fbff; border: 1px dashed #9ecbec; border-radius: .75rem; padding: 1rem; }
    .mq-table-shell { background: #fff; border: 1px solid #e2e9f0; border-radius: .75rem; overflow: hidden; }
    .mq-table-shell .table { margin: 0; }
    .mq-table-shell thead th { background: #f7fbff; color: #526274; font-size: .7rem; letter-spacing: .02em; vertical-align: middle; white-space: nowrap; }
    .mq-table-shell tbody tr { transition: background .15s ease; }
    .mq-table-shell tbody tr:hover { background: #f7fbff; }
    .mq-footer { background: #fff; border-top: 1px solid #e2e9f0; }
    @media (prefers-reduced-motion: reduce) { .mq-table-shell tbody tr, [x-cloak] { transition: none !important; } }
    @media (max-width: 767px) { .mq-body { padding: .75rem; } .mq-section { padding: .75rem; } }
</style>
@endpush
