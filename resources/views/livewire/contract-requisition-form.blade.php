<div>
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0">Requisicion por contrato</h5></div>
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label" for="company-select">Empresa <span class="text-danger">*</span></label>
                <select id="company-select" wire:model.live="company_id" class="form-select">
                    <option value="">Seleccionar...</option>
                    @foreach ($companies as $co)
                        <option value="{{ $co->id }}">{{ $co->name }}</option>
                    @endforeach
                </select>
                @error('company_id')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label class="form-label" for="required-date">Fecha requerida <span class="text-danger">*</span></label>
                <input id="required-date" type="date" wire:model="required_date" class="form-control">
                @error('required_date')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label class="form-label" for="location-select">Ubicacion de recepcion <span class="text-danger">*</span></label>
                <select id="location-select" wire:model="receiving_location_id" class="form-select">
                    <option value="">Seleccionar...</option>
                    @foreach ($locations as $loc)
                        <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                    @endforeach
                </select>
                @error('receiving_location_id')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0">Agregar partida</h6></div>
        <div class="card-body row g-3">
            <div class="col-md-5">
                <label class="form-label" for="contract-select">Contrato <span class="text-danger">*</span></label>
                <select id="contract-select" wire:model.live="newItem.contract_id" class="form-select" @if (! $company_id) disabled @endif>
                    <option value="">{{ $company_id ? 'Seleccionar contrato...' : 'Primero selecciona empresa' }}</option>
                    @foreach ($eligibleContracts as $c)
                        <option value="{{ $c->id }}">{{ $c->folio }} - {{ $c->supplier->company_name }}</option>
                    @endforeach
                </select>
                @error('newItem.contract_id')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label class="form-label" for="product-select">Producto <span class="text-danger">*</span></label>
                <select id="product-select" wire:model.live="newItem.contract_product_id" class="form-select" @if (! $newItem['contract_id']) disabled @endif>
                    <option value="">Seleccionar producto...</option>
                    @foreach ($newItemContractProducts as $cp)
                        <option value="{{ $cp->id }}">{{ $cp->product?->getDisplayName() ?? "Producto #{$cp->product_service_id}" }}</option>
                    @endforeach
                </select>
                @error('newItem.contract_product_id')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label class="form-label" for="qty-input">Cantidad <span class="text-danger">*</span></label>
                <input id="qty-input" type="number" wire:model="newItem.quantity" step="0.001" min="0.001" class="form-control">
                @error('newItem.quantity')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-5">
                <label class="form-label" for="cost-center-select">Centro de costo <span class="text-danger">*</span></label>
                <select id="cost-center-select" wire:model.live="newItem.cost_center_id" class="form-select" @if (! $company_id) disabled @endif>
                    <option value="">{{ $company_id ? 'Seleccionar centro de costo...' : 'Primero selecciona empresa' }}</option>
                    @foreach ($availableCostCenters as $costCenter)
                        <option value="{{ $costCenter->id }}">
                            {{ $costCenter->code ? "{$costCenter->code} - " : '' }}{{ $costCenter->name }}
                        </option>
                    @endforeach
                </select>
                @error('newItem.cost_center_id')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label class="form-label" for="expense-cat-select">Categoria de gasto <span class="text-danger">*</span></label>
                <select id="expense-cat-select" wire:model.live="newItem.expense_category_id" class="form-select">
                    <option value="">Seleccionar...</option>
                    @foreach ($expenseCategories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
                @error('newItem.expense_category_id')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label class="form-label" for="budget-cedula-select">Cedula presupuestal <span class="text-danger">*</span></label>
                <select id="budget-cedula-select" wire:model="newItem.budget_cedula_id" class="form-select" @if (! $newItem['cost_center_id'] || ! $newItem['expense_category_id']) disabled @endif>
                    <option value="">Seleccionar...</option>
                    @foreach ($availableBudgetCedulas as $cedula)
                        <option value="{{ $cedula->id }}">{{ $cedula->name }}</option>
                    @endforeach
                </select>
                @error('newItem.budget_cedula_id')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            @if ($newItemSnapshotPrice)
                <div class="col-12">
                    <span class="badge bg-light text-dark border">
                        Precio: <strong>${{ number_format($newItemSnapshotPrice, 4) }} {{ $newItemCurrency }}</strong>
                        (precio del contrato, se copiara al guardar)
                    </span>
                </div>
            @endif

            <div class="col-12">
                <button type="button" wire:click="addItem" class="btn btn-outline-primary btn-sm">
                    <i class="ti ti-plus me-1"></i> Agregar partida
                </button>
            </div>
        </div>
    </div>

    @if (count($items) > 0)
        <div class="card mb-3">
            <div class="card-header">Partidas ({{ count($items) }})</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Contrato</th>
                            <th>Proveedor</th>
                            <th>Producto</th>
                            <th>Cant.</th>
                            <th>Precio</th>
                            <th>Centro costo</th>
                            <th>Categoria</th>
                            <th>Cedula</th>
                            <th>Avisos</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $index => $item)
                            <tr>
                                <td>{{ $item['contract_folio'] }}</td>
                                <td>{{ $item['supplier_name'] }}</td>
                                <td>{{ $item['product_name'] }}</td>
                                <td>{{ $item['quantity'] }} {{ $item['unit_of_measure'] }}</td>
                                <td>${{ number_format($item['unit_price'], 4) }} {{ $item['currency_code'] }}</td>
                                <td>{{ $item['cost_center_name'] ?? '-' }}</td>
                                <td>{{ $item['expense_category_name'] ?? '-' }}</td>
                                <td>{{ $item['budget_cedula_name'] ?? '-' }}</td>
                                <td>
                                    @if ($item['expiry_warning'])
                                        <span class="badge bg-warning text-dark" title="Vence el {{ $item['expiry_warning'] }}">
                                            <i class="ti ti-clock-x me-1"></i> Vence {{ $item['expiry_warning'] }}
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <button type="button" wire:click="removeItem({{ $index }})" class="btn btn-sm btn-outline-danger">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <button type="button" wire:click="submit" class="btn btn-primary" wire:loading.attr="disabled">
            <span wire:loading.remove>Generar requisicion y OC</span>
            <span wire:loading>Procesando...</span>
        </button>
    @endif
</div>
