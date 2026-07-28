<div>
    @if ($show && $this->group)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header py-2">
                        <h5 class="modal-title">
                            <i class="ti ti-pencil-dollar me-2"></i>Capturar precio conocido — {{ $this->group->name }}
                        </h5>
                        <button type="button" class="btn-close" wire:click="close"></button>
                    </div>

                    <div class="modal-body">
                        {{-- Proveedor --}}
                        <div class="row g-2 mb-3">
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
                            <div class="border rounded p-2 mb-3 bg-light">
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
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Partida</th>
                                        <th width="90">N/D</th>
                                        <th width="130">Precio unit. *</th>
                                        <th width="90">IVA % *</th>
                                        <th width="110">Entrega (días) *</th>
                                        <th>Referencia reciente</th>
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

                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="close">Cancelar</button>
                        <button type="button" class="btn btn-sm btn-primary" wire:click="save" wire:loading.attr="disabled">
                            <i class="ti ti-device-floppy me-1"></i>Guardar cotización
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
