{{--
    Formulario compartido de OCD (crear/editar).
    Si $directPurchaseOrder existe, el formulario opera en modo edición.
--}}
@php
    $ocd = $directPurchaseOrder ?? null;

    $costCenterCatalog = $costCenters->map(function ($cc) {
        return [
            'id' => $cc->id,
            'company_id' => $cc->company_id,
            'purchase_type' => $cc->purchase_type?->value ?? $cc->purchase_type,
            'label' => ($cc->code ? '[' . $cc->code . '] ' : '') . $cc->name,
        ];
    })->values();

    // Partidas normalizadas: old() tras error de validación, partidas del
    // modelo en edición, o una fila vacía en creación.
    $formItems = collect(old('items', $ocd?->items->map(fn ($item) => [
        'product_service_id' => $item->product_service_id,
        'cost_center_id' => $item->cost_center_id,
        'expense_category_id' => $item->expense_category_id,
        'description' => $item->description,
        'notes' => $item->notes,
        'quantity' => $item->quantity,
        'unit_price' => $item->unit_price,
        'iva_rate' => $item->iva_rate,
        'iva_amount' => $item->iva_amount,
        'subtotal' => $item->subtotal,
        'total' => $item->total,
    ])->all() ?? []));

    if ($formItems->isEmpty()) {
        $formItems = collect([[
            'cost_center_id' => '',
            'expense_category_id' => '',
            'description' => '',
            'notes' => '',
            'quantity' => '',
            'unit_price' => '',
            'iva_rate' => '16',
        ]]);
    }

    // Siguiente índice libre: old() conserva los índices originales (p. ej. 0, 2)
    // cuando se borraron partidas, así que no basta con contar filas.
    $nextItemIndex = ((int) $formItems->keys()->max()) + 1;

    // Tipo de cambio a MXN por moneda; null si no hay tipo sincronizado.
    $ocdLimitMxn = 250000;
    $mxnRates = collect(['MXN', 'USD', 'EUR'])->mapWithKeys(function ($code) {
        try {
            return [$code => \App\Models\ExchangeRate::rateToMxn($code)];
        } catch (\RuntimeException) {
            return [$code => null];
        }
    });

    // Errores que ya se muestran junto a su campo (o en el resumen) vs. los que no
    // tienen lugar propio (presupuesto, errores del servidor): estos van en la alerta.
    $inlineErrorPattern = '/^(supplier_id|company_id|receiving_location_id|currency|payment_terms|estimated_delivery_days|justification|quotation_file|support_documents(\..+)?|total|items(\..+)?)$/';
    $generalErrors = collect($errors->getMessages())
        ->reject(fn ($messages, $key) => preg_match($inlineErrorPattern, $key))
        ->flatten()
        ->unique()
        ->values();
    $fieldErrorCount = collect($errors->keys())
        ->filter(fn ($key) => preg_match($inlineErrorPattern, $key) && $key !== 'total')
        ->count();

    // Filas con errores de validación del servidor (items.N.campo).
    $itemErrorRows = collect($errors->keys())
        ->map(fn ($key) => preg_match('/^items\.(\d+)\./', $key, $m) ? (int) $m[1] : null)
        ->filter(fn ($i) => $i !== null)
        ->unique()
        ->values();
@endphp

<form action="{{ $ocd ? route('direct-purchase-orders.update', $ocd->id) : route('direct-purchase-orders.store') }}" method="POST" enctype="multipart/form-data" id="ocd-form">
    @csrf
    @if($ocd)
        @method('PUT')
    @endif
    <input type="hidden" id="products-api-url" value="{{ route('products-services.api.active-for-requisitions') }}">
    <input type="hidden" id="budget-preview-url" value="{{ route('direct-purchase-orders.budget-preview') }}">

    <div class="row">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-body">

                    @if($errors->any())
                        <div class="alert alert-danger py-2 px-3 small mb-3" role="alert" id="form-error-summary" tabindex="-1">
                            <strong><i class="ti ti-alert-circle me-1" aria-hidden="true"></i>No se pudo guardar la OCD.</strong>
                            @foreach($generalErrors as $message)
                                <div class="mt-1">{{ $message }}</div>
                            @endforeach
                            @if($fieldErrorCount > 0)
                                <div class="mt-1">
                                    Revisa {{ $fieldErrorCount === 1 ? 'el campo marcado' : "los {$fieldErrorCount} campos marcados" }} en rojo.
                                    <a href="#" class="alert-link" id="go-to-first-error">Ir al primero</a>
                                </div>
                            @elseif($errors->has('total'))
                                <div class="mt-1">El total excede el límite: revisa el resumen.</div>
                            @endif
                        </div>
                    @endif

                    {{-- FILA 1: EMPRESA Y UBICACIÓN DE RECEPCIÓN (definen centros de costo y destino) --}}
                    <div class="row g-2 mb-3">
                        <div class="col-md-5">
                            <label for="company_id" class="form-label small fw-bold">Empresa <span class="text-danger" aria-hidden="true">*</span></label>
                            <div class="input-group input-group-sm input-group-select2">
                                <span class="input-group-text" aria-hidden="true"><i class="ti ti-building-store"></i></span>
                                <select name="company_id" id="company_id" class="form-select form-select-sm @error('company_id') is-invalid @enderror" data-url-receiving-locations="{{ route('requisitions.receiving-locations.by-company', ['company' => '__CID__']) }}" required>
                                    <option value="">Seleccione...</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}"
                                            {{ old('company_id', $ocd?->primaryCompanyId()) == $company->id ? 'selected' : '' }}>
                                            {{ $company->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @error('company_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-7">
                            <label for="receiving_location_id" class="form-label small fw-bold">Ubicación de recepción <span class="text-danger" aria-hidden="true">*</span></label>
                            <div class="input-group input-group-sm input-group-select2">
                                <span class="input-group-text" aria-hidden="true"><i class="ti ti-map-pin"></i></span>
                                <select name="receiving_location_id" id="receiving_location_id" class="form-select form-select-sm @error('receiving_location_id') is-invalid @enderror" required>
                                    <option value="">Seleccione...</option>
                                    @foreach($receivingLocations as $loc)
                                        <option value="{{ $loc->id }}" {{ old('receiving_location_id', $ocd?->receiving_location_id) == $loc->id ? 'selected' : '' }}>
                                            {{ $loc->code ? '['.$loc->code.'] ' : '' }}{{ $loc->name }}{{ $loc->city ? ' — '.$loc->city : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @error('receiving_location_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    {{-- FILA 2: PROVEEDOR Y CONDICIONES --}}
                    <div class="row g-2 mb-3">
                        <div class="col-md-5">
                            <label for="supplier_id" class="form-label small fw-bold">Proveedor <span class="text-danger" aria-hidden="true">*</span></label>
                            <div class="input-group input-group-sm input-group-select2">
                                <span class="input-group-text" aria-hidden="true"><i class="ti ti-building"></i></span>
                                <select name="supplier_id" id="supplier_id" class="form-select form-select-sm @error('supplier_id') is-invalid @enderror" required>
                                    <option value="">Seleccione...</option>
                                    @foreach($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}"
                                                data-payment-terms="{{ $supplier->default_payment_terms }}"
                                                data-delivery-days="{{ $supplier->avg_delivery_time }}"
                                                {{ old('supplier_id', $ocd?->supplier_id) == $supplier->id ? 'selected' : '' }}>
                                            {{ $supplier->company_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @error('supplier_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-2">
                            <label for="currency" class="form-label small fw-bold">Moneda <span class="text-danger" aria-hidden="true">*</span></label>
                            <div class="input-group input-group-sm input-group-select2">
                                <span class="input-group-text" aria-hidden="true"><i class="ti ti-currency-dollar"></i></span>
                                <select name="currency" id="currency" class="form-select form-select-sm @error('currency') is-invalid @enderror" required>
                                    @foreach(['MXN' => 'MXN', 'USD' => 'USD', 'EUR' => 'EUR'] as $value => $label)
                                        <option value="{{ $value }}" {{ old('currency', $ocd?->currency ?? 'MXN') == $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @error('currency') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-3">
                            <label for="payment_terms" class="form-label small fw-bold">Condiciones de pago</label>
                            <div class="input-group input-group-sm input-group-select2">
                                <span class="input-group-text" aria-hidden="true"><i class="ti ti-receipt"></i></span>
                                <select name="payment_terms" id="payment_terms" class="form-select form-select-sm">
                                    <option value="">Seleccione...</option>
                                    @foreach(\App\Enum\PaymentTerm::options() as $value => $label)
                                        <option value="{{ $value }}" {{ old('payment_terms', $ocd?->payment_terms) == $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @error('payment_terms') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-2">
                            <label for="estimated_delivery_days" class="form-label small fw-bold">Días de entrega</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text" aria-hidden="true"><i class="ti ti-clock"></i></span>
                                <input type="number" name="estimated_delivery_days" id="estimated_delivery_days" class="form-control form-control-sm @error('estimated_delivery_days') is-invalid @enderror" min="1" max="365" step="1" inputmode="numeric" aria-describedby="estimated_delivery_days_help" value="{{ old('estimated_delivery_days', $ocd?->estimated_delivery_days) }}">
                            </div>
                            <div class="form-text mt-1" id="estimated_delivery_days_help">Define el mes del presupuesto.</div>
                            @error('estimated_delivery_days') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <hr class="my-3">

                    {{-- JUSTIFICACIÓN --}}
                    <div class="mb-3">
                        <label for="justification" class="form-label small fw-bold mb-0">Justificación de la compra directa <span class="text-danger" aria-hidden="true">*</span></label>
                        <div class="form-text mt-0 mb-1" id="justification-help">
                            Explica por qué esta compra no puede seguir el proceso de requisición (urgencia, proveedor único, etc.). Mínimo 100 caracteres.
                        </div>
                        <textarea name="justification" id="justification" rows="3" class="form-control form-control-sm @error('justification') is-invalid @enderror" aria-describedby="justification-help justification-count" required minlength="100" maxlength="2000">{{ old('justification', $ocd?->justification) }}</textarea>
                        <div class="d-flex justify-content-between small mt-1">
                            <span id="justification-remaining" class="text-muted"></span>
                            <span class="text-muted" id="justification-count"><span id="char-count">0</span> / 2000</span>
                        </div>
                        <div class="invalid-feedback d-block" id="justification-client-error" role="alert"></div>
                        @error('justification') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>

                    {{-- TABLA DE PARTIDAS --}}
                    <div class="d-flex justify-content-between align-items-end mb-2 gap-2">
                        <div>
                            <h6 class="m-0 fw-bold text-uppercase small text-primary">Partidas</h6>
                            <div class="form-text m-0" id="items-help">
                                Elige primero la empresa. Luego llena cada partida en orden:
                                <strong>centro de costo → producto → cantidad y precio</strong>.
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary text-nowrap" id="add-item-btn"><i class="ti ti-plus" aria-hidden="true"></i> Agregar partida</button>
                    </div>
                    @if($itemErrorRows->isNotEmpty())
                        <div class="alert alert-danger py-2 px-3 mb-2 small" role="alert" id="items-error-summary">
                            <i class="ti ti-alert-circle me-1"></i>
                            {{ $itemErrorRows->count() === 1 ? 'Hay 1 partida con errores' : 'Hay '.$itemErrorRows->count().' partidas con errores' }}:
                            revisa los campos marcados en rojo en la tabla.
                        </div>
                    @endif
                    <div class="border rounded items-table-wrap">
                        <table class="table table-sm table-hover mb-0" id="items-table" aria-describedby="items-help">
                            <caption class="visually-hidden">Partidas de la orden de compra directa</caption>
                            <thead class="table-light small">
                                <tr>
                                    <th scope="col" style="width:23%">Centro de costo</th>
                                    <th scope="col" style="width:31%">Producto y nota para proveedor</th>
                                    <th scope="col" style="width:8%">Cantidad</th>
                                    <th scope="col" style="width:10%">Precio unit.</th>
                                    <th scope="col" style="width:9%">IVA</th>
                                    <th scope="col" class="text-end" style="width:9%">Subtotal</th>
                                    <th scope="col" class="text-end" style="width:10%">Total</th>
                                    <th scope="col"><span class="visually-hidden">Acciones</span></th>
                                </tr>
                            </thead>
                            <tbody id="items-tbody">
                                @foreach($formItems as $index => $item)
                                    @php
                                        $fieldError = fn (string ...$fields) => collect($fields)
                                            ->map(fn ($field) => $errors->first("items.{$index}.{$field}"))
                                            ->first(fn ($message) => filled($message));
                                        $costCenterError = $fieldError('cost_center_id');
                                        $productError = $fieldError('product_service_id', 'description', 'expense_category_id', 'budget_cedula_id', 'notes');
                                        $quantityError = $fieldError('quantity');
                                        $priceError = $fieldError('unit_price');
                                        $ivaError = $fieldError('iva_rate');
                                    @endphp
                                    <tr class="item-row{{ $itemErrorRows->contains($index) ? ' item-row-invalid' : '' }}"
                                        data-selected-product="{{ $item['product_service_id'] ?? '' }}">
                                        <td data-label="Centro de costo" class="{{ $costCenterError ? 'cell-invalid' : '' }}">
                                            <select name="items[{{ $index }}][cost_center_id]"
                                                    class="form-select form-select-sm border-0 item-cost-center"
                                                    data-selected="{{ $item['cost_center_id'] ?? '' }}"
                                                    aria-label="Centro de costo"
                                                    required>
                                                <option value="">Seleccione empresa...</option>
                                            </select>
                                            <select name="items[{{ $index }}][expense_category_id]"
                                                    class="form-select form-select-sm border-0 item-expense-category"
                                                    data-selected="{{ $item['expense_category_id'] ?? '' }}"
                                                    required disabled style="display:none">
                                                <option value="">Seleccione...</option>
                                            </select>
                                            @if($costCenterError) <div class="item-error">{{ $costCenterError }}</div> @endif
                                            <div class="item-budget" aria-live="polite"></div>
                                        </td>
                                        <td data-label="Producto y nota" class="{{ $productError ? 'cell-invalid' : '' }}">
                                            <input type="text"
                                                   name="items[{{ $index }}][description]"
                                                   class="form-control form-control-sm border-0 item-description"
                                                   placeholder="Descripción"
                                                   value="{{ $item['description'] ?? '' }}"
                                                   required
                                                   maxlength="500">
                                            <textarea name="items[{{ $index }}][notes]"
                                                      class="form-control form-control-sm border-0 mt-1 item-notes"
                                                      aria-label="Nota para el proveedor"
                                                      rows="2"
                                                      maxlength="1000"
                                                      placeholder="Nota para el proveedor (opcional)">{{ $item['notes'] ?? '' }}</textarea>
                                            @if($productError) <div class="item-error">{{ $productError }}</div> @endif
                                        </td>
                                        <td data-label="Cantidad">
                                            <input type="number"
                                                   name="items[{{ $index }}][quantity]"
                                                   class="form-control form-control-sm border-0 item-quantity text-end {{ $quantityError ? 'is-invalid' : '' }}"
                                                   aria-label="Cantidad"
                                                   inputmode="decimal"
                                                   step="0.01"
                                                   min="0.01"
                                                   placeholder="0"
                                                   value="{{ $item['quantity'] ?? '' }}"
                                                   required>
                                            @if($quantityError) <div class="item-error">{{ $quantityError }}</div> @endif
                                        </td>
                                        <td data-label="Precio unit.">
                                            <input type="number"
                                                   name="items[{{ $index }}][unit_price]"
                                                   class="form-control form-control-sm border-0 item-unit-price text-end {{ $priceError ? 'is-invalid' : '' }}"
                                                   aria-label="Precio unitario"
                                                   inputmode="decimal"
                                                   step="0.01"
                                                   min="0.01"
                                                   placeholder="0.00"
                                                   value="{{ $item['unit_price'] ?? '' }}"
                                                   required>
                                            @if($priceError) <div class="item-error">{{ $priceError }}</div> @endif
                                        </td>
                                        <td data-label="IVA" class="{{ $ivaError ? 'cell-invalid' : '' }}">
                                            <select name="items[{{ $index }}][iva_rate]"
                                                    class="form-select form-select-sm border-0 item-iva-rate"
                                                    aria-label="Tasa de IVA"
                                                    required>
                                                <option value="16" {{ ($item['iva_rate'] ?? '16') == 16 ? 'selected' : '' }}>16%</option>
                                                <option value="8"  {{ ($item['iva_rate'] ?? '') == 8  ? 'selected' : '' }}>8%</option>
                                                <option value="0"  {{ ($item['iva_rate'] ?? '') == 0  ? 'selected' : '' }}>0%</option>
                                            </select>
                                            <input type="text"
                                                   class="form-control form-control-sm border-0 bg-transparent item-iva text-end small mt-1"
                                                   value="${{ number_format($item['iva_amount'] ?? 0, 2) }}"
                                                   aria-label="IVA de la partida"
                                                   readonly tabindex="-1">
                                            @if($ivaError) <div class="item-error">{{ $ivaError }}</div> @endif
                                        </td>
                                        <td data-label="Subtotal">
                                            <input type="text"
                                                   class="form-control form-control-sm border-0 bg-transparent item-subtotal text-end"
                                                   value="${{ number_format($item['subtotal'] ?? 0, 2) }}"
                                                   aria-label="Subtotal de la partida"
                                                   readonly tabindex="-1">
                                        </td>
                                        <td data-label="Total">
                                            <input type="text"
                                                   class="form-control form-control-sm border-0 bg-transparent item-total text-end fw-bold"
                                                   value="${{ number_format($item['total'] ?? 0, 2) }}"
                                                   aria-label="Total de la partida"
                                                   readonly tabindex="-1">
                                        </td>
                                        <td class="text-center item-actions">
                                            <button type="button" class="btn btn-link p-0 duplicate-item-btn" title="Duplicar partida" aria-label="Duplicar partida">
                                                <i class="ti ti-copy" aria-hidden="true"></i><span class="item-action-label">Duplicar</span>
                                            </button>
                                            <button type="button" class="btn btn-link text-danger p-0 remove-item-btn" title="Eliminar partida" aria-label="Eliminar partida">
                                                <i class="ti ti-trash" aria-hidden="true"></i><span class="item-action-label">Eliminar</span>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @error('items') <div class="text-danger small mt-1">{{ $message }}</div> @enderror

                    {{-- ADJUNTOS --}}
                    <div class="row g-2 mt-3">
                        @php
                            $currentQuotation = $ocd?->documents->firstWhere('document_type', 'quotation');
                        @endphp
                        @if($ocd && $ocd->documents->count() > 0)
                            <div class="col-md-12 mb-1">
                                <div class="border rounded px-3 py-2 bg-light small">
                                    <strong><i class="ti ti-paperclip me-1" aria-hidden="true"></i>Documentos actuales</strong>
                                    <ul class="list-inline mb-0 mt-1">
                                        @foreach($ocd->documents as $doc)
                                            <li class="list-inline-item me-3">
                                                <a href="{{ route('direct-purchase-orders.documents.show', [$ocd->id, $doc->id]) }}" target="_blank" rel="noopener">
                                                    {{ $doc->original_filename }}
                                                </a>
                                                @if($doc->document_type === 'quotation')
                                                    <span class="text-muted">(cotización)</span>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @endif
                        <div class="col-md-6">
                            <label for="quotation_file" class="form-label small fw-bold">
                                {{ $ocd ? 'Nueva cotización' : 'Cotización' }}
                                <span class="text-muted fw-normal">
                                    @if($currentQuotation)
                                        (opcional — reemplaza a «{{ $currentQuotation->original_filename }}»)
                                    @else
                                        (opcional)
                                    @endif
                                </span>
                            </label>
                            <input type="file" name="quotation_file" id="quotation_file"
                                   class="form-control form-control-sm @error('quotation_file') is-invalid @enderror"
                                   accept=".pdf,.jpg,.jpeg,.png"
                                   data-max-files="1" data-max-mb="5"
                                   aria-describedby="quotation_file_help quotation_file_client_error">
                            <small class="text-muted" id="quotation_file_help">PDF, JPG o PNG (máx. 5MB)</small>
                            <div class="invalid-feedback d-block file-client-error" id="quotation_file_client_error" role="alert"></div>
                            @error('quotation_file') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="support_documents" class="form-label small fw-bold">
                                {{ $ocd ? 'Nuevos anexos' : 'Otros anexos' }}
                                @if($ocd)
                                    <span class="text-muted fw-normal">(opcional — se agregan a los existentes)</span>
                                @endif
                            </label>
                            <input type="file" name="support_documents[]" id="support_documents"
                                   class="form-control form-control-sm @if($errors->has('support_documents') || $errors->has('support_documents.*')) is-invalid @endif"
                                   accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" multiple
                                   data-max-files="5" data-max-mb="5"
                                   aria-describedby="support_documents_help support_documents_client_error">
                            <small class="text-muted" id="support_documents_help">PDF, imagen, Word o Excel. Máximo 5 archivos de 5MB cada uno</small>
                            <div class="invalid-feedback d-block file-client-error" id="support_documents_client_error" role="alert"></div>
                            @error('support_documents') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            @error('support_documents.*') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                    </div>

                </div>
            </div>
        </div>

        {{-- RESUMEN LATERAL --}}
        <div class="col-lg-3">
            <div class="card shadow-none border ocd-summary">
                <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                    <h6 class="card-title mb-0 small fw-bold">Resumen</h6>
                    <span class="small text-muted" id="summary-item-count" aria-live="polite"></span>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0 small">
                        <tbody>
                            <tr>
                                <td class="ps-3 border-0">Subtotal</td>
                                <td class="text-end pe-3 border-0" id="summary-subtotal">{{ $ocd ? '$'.number_format($ocd->subtotal, 2) : '$0.00' }}</td>
                            </tr>
                            <tr>
                                <td class="ps-3">IVA</td>
                                <td class="text-end pe-3" id="summary-iva">{{ $ocd ? '$'.number_format($ocd->iva_amount, 2) : '$0.00' }}</td>
                            </tr>
                            <tr class="fw-bold">
                                <td class="ps-3">TOTAL <span class="fw-normal text-muted" id="summary-currency">{{ old('currency', $ocd?->currency ?? 'MXN') }}</span></td>
                                <td class="text-end pe-3 text-primary fs-6" id="summary-total">{{ $ocd ? '$'.number_format($ocd->total, 2) : '$0.00' }}</td>
                            </tr>
                            <tr id="summary-mxn-row" class="d-none">
                                <td class="ps-3">
                                    Equivalente MXN
                                    <div class="text-muted" id="summary-rate"></div>
                                </td>
                                <td class="text-end pe-3 fw-semibold" id="summary-total-mxn">$0.00</td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="px-3 py-2 bg-light border-top small" id="summary-limit" tabindex="-1">
                        <div class="d-flex justify-content-between text-muted">
                            <span>Límite OCD</span>
                            <strong>${{ number_format($ocdLimitMxn) }} MXN</strong>
                        </div>
                        <div class="progress mt-1 limit-meter" role="progressbar" aria-label="Consumo del límite de OCD"
                             aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="summary-limit-meter">
                            <div class="progress-bar" id="summary-limit-bar" style="width:0%"></div>
                        </div>
                        <div id="summary-limit-status" class="mt-1" aria-live="polite"></div>
                    </div>
                    <div class="px-3 py-2 border-top small" id="summary-budget">
                        <div class="d-flex justify-content-between text-muted">
                            <span>Presupuesto</span>
                            <span id="summary-budget-month"></span>
                        </div>
                        <div id="summary-budget-status" class="mt-1 text-muted" aria-live="polite">
                            Elige centro de costo y producto para ver el saldo.
                        </div>
                        <div class="text-muted mt-1 budget-month-hint">
                            El mes se calcula con la entrega estimada (hoy + días de entrega).
                        </div>
                    </div>
                    @if($errors->has('total') || $errors->has('currency'))
                        <div class="px-3 py-2 border-top">
                            @foreach(array_merge($errors->get('total'), $errors->get('currency')) as $message)
                                <div class="text-danger small" role="alert"><i class="ti ti-alert-triangle me-1"></i>{{ $message }}</div>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="card-footer p-2 bg-transparent border-top-0">
                    <button type="submit" class="btn btn-primary btn-sm w-100 mb-1 ocd-submit" id="ocd-submit-btn">
                        <i class="ti ti-device-floppy me-1" aria-hidden="true"></i>{{ $ocd ? ($ocd->isReturned() ? 'Guardar y reenviar a aprobación' : 'Guardar cambios') : 'Guardar y enviar a aprobación' }}
                    </button>
                    <a href="{{ $ocd ? route('direct-purchase-orders.show', $ocd->id) : route('purchase-orders.index') }}" class="btn btn-outline-secondary btn-sm w-100" id="ocd-cancel-btn">
                        <i class="ti ti-x me-1" aria-hidden="true"></i>Cancelar
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Barra fija en pantallas angostas: total, estado y guardar siempre a la vista --}}
    <div class="ocd-mobile-bar d-lg-none" id="ocd-mobile-bar">
        <div class="ocd-mobile-bar__info">
            <div class="ocd-mobile-bar__label">Total <span id="mobile-currency">{{ old('currency', $ocd?->currency ?? 'MXN') }}</span></div>
            <div class="ocd-mobile-bar__total" id="mobile-total">{{ $ocd ? '$'.number_format($ocd->total, 2) : '$0.00' }}</div>
            <div class="ocd-mobile-bar__status" id="mobile-status" aria-live="polite"></div>
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="mobile-summary-btn">Resumen</button>
        <button type="submit" class="btn btn-primary btn-sm ocd-submit" id="mobile-submit-btn">
            <i class="ti ti-device-floppy me-1" aria-hidden="true"></i>Guardar
        </button>
    </div>
</form>

@push('styles')
<style>
    #ocd-form {
        --ocd-focus-border: #86b7fe;
        --ocd-focus-ring: 0 0 0 0.2rem rgba(13, 110, 253, 0.2);
        --ocd-danger: #b42318;
        --ocd-warning: #b54708;
    }

    /* Resumen visible mientras se capturan partidas largas */
    @media (min-width: 992px) {
        .ocd-summary {
            position: sticky;
            top: 90px;
        }
    }

    .limit-meter {
        height: 6px;
        background-color: #e2e8f0;
    }

    .limit-meter .progress-bar {
        transition: width 0.25s ease-out;
    }

    @media (prefers-reduced-motion: reduce) {
        .limit-meter .progress-bar { transition: none; }
    }

    #items-table .remove-item-btn:disabled {
        color: #94a3b8 !important;
        opacity: 1;
        cursor: not-allowed;
    }

    #items-table .item-total,
    #items-table .item-subtotal,
    #items-table .item-iva,
    .ocd-summary td.text-end {
        font-variant-numeric: tabular-nums;
    }

    /* ==================================================
       SELECT2 CON INPUT-GROUP - CORRECCIÓN DE LAYOUT
       ================================================== */

    .input-group-select2 {
        position: relative;
        display: flex;
        flex-wrap: nowrap;
        align-items: stretch;
        width: 100%;
    }

    .input-group-select2 .input-group-text {
        display: flex;
        align-items: center;
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
        font-weight: 400;
        line-height: 1.5;
        color: #495057;
        text-align: center;
        white-space: nowrap;
        background-color: #e9ecef;
        border: 1px solid #ced4da;
        border-radius: 0.2rem 0 0 0.2rem;
    }

    .input-group-select2 .form-select {
        display: none;
    }

    .input-group-select2 .select2-container {
        flex: 1 1 auto;
        width: 1% !important;
    }

    .input-group-select2 .select2-container .select2-selection--single {
        height: calc(1.5em + 0.5rem + 2px) !important;
        border-radius: 0 0.2rem 0.2rem 0 !important;
        border-left: 0 !important;
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }

    .input-group-select2 .select2-container .select2-selection__rendered {
        line-height: calc(1.5em + 0.5rem) !important;
        padding-left: 0.5rem !important;
        padding-right: 1.5rem !important;
    }

    .input-group-select2 .select2-container .select2-selection__arrow {
        height: calc(1.5em + 0.5rem) !important;
        top: 1px !important;
        right: 1px !important;
    }

    .input-group-select2 .select2-container .select2-selection__placeholder {
        color: #6c757d;
        font-size: 0.875rem;
    }

    .input-group-select2 .select2-container--focus .select2-selection--single,
    .input-group-select2 .select2-container--open .select2-selection--single {
        border-color: var(--ocd-focus-border) !important;
        outline: 0;
        box-shadow: var(--ocd-focus-ring);
    }

    .input-group-select2 .select2-container.is-invalid .select2-selection--single {
        border-color: #dc3545 !important;
    }

    .select2-container--bootstrap-5 .select2-dropdown {
        border-color: #ced4da;
        font-size: 0.875rem;
    }

    .select2-container--bootstrap-5 .select2-results__option {
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
    }

    /* Select2 dentro de tabla (IVA y Categoría) */
    .table .select2-container .select2-selection--single {
        border: 0 !important;
        background: transparent !important;
        height: auto !important;
        padding: 0.25rem 0.5rem;
    }

    .table .select2-container .select2-selection__rendered {
        padding-left: 0.25rem !important;
        padding-right: 1.25rem !important;
        line-height: 1.5 !important;
    }

    .table .select2-container .select2-selection__arrow {
        height: 100% !important;
        top: 0 !important;
    }

    #items-table {
        table-layout: fixed;
        width: 100%;
        min-width: 960px;
    }

    .items-table-wrap {
        overflow-x: auto;
        overflow-y: visible;
    }

    #items-table th {
        font-weight: 600;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: #475569;
        padding: 0.4rem 0.35rem;
        vertical-align: middle;
    }

    #items-table td {
        padding: 0.35rem;
        vertical-align: middle;
    }

    #items-table th:last-child,
    #items-table td:last-child {
        min-width: 64px;
        width: 64px;
    }

    #items-table .item-actions {
        white-space: nowrap;
    }

    #items-table .item-actions .btn + .btn {
        margin-left: 0.6rem;
    }

    #items-table .duplicate-item-btn {
        color: #475569;
    }

    #items-table .duplicate-item-btn:hover,
    #items-table .duplicate-item-btn:focus-visible {
        color: #0d6efd;
    }

    #items-table .item-action-label {
        display: none;
    }

    /* Pantallas angostas: cada partida se vuelve una tarjeta, sin scroll horizontal */
    @media (max-width: 1199.98px) {
        .items-table-wrap {
            overflow-x: visible;
            border: 0 !important;
        }

        #items-table,
        #items-table tbody {
            display: block;
            width: 100%;
            min-width: 0;
        }

        #items-table thead {
            display: none;
        }

        #items-table tr.item-row {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.6rem 0.75rem;
            padding: 0.75rem;
            margin-bottom: 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #fff;
        }

        #items-table tr.item-row > td {
            display: block;
            width: auto !important;
            min-width: 0 !important;
            padding: 0;
            border: 0;
            background: transparent !important;
        }

        #items-table tr.item-row > td::before {
            content: attr(data-label);
            display: block;
            margin-bottom: 0.2rem;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            color: #475569;
        }

        #items-table tr.item-row > td:nth-child(1),
        #items-table tr.item-row > td:nth-child(2) {
            grid-column: 1 / -1;
        }

        #items-table tr.item-row > td.item-actions {
            grid-column: 1 / -1;
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
            padding-top: 0.5rem;
            border-top: 1px solid #f1f5f9;
        }

        #items-table tr.item-row > td.item-actions::before {
            content: none;
        }

        #items-table .item-actions .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            min-height: 44px;
            padding: 0 0.6rem !important;
            text-decoration: none;
        }

        #items-table .item-actions .btn + .btn {
            margin-left: 0;
        }

        #items-table .item-action-label {
            display: inline;
            font-size: 0.8125rem;
        }

        #items-table tr.item-row-invalid {
            border-color: #f1aeb5;
            background: #fff5f5;
        }
    }

    #items-table .form-control-sm,
    #items-table .form-select-sm {
        padding: 0.25rem 0.4rem;
        font-size: 0.8125rem;
        border: 1px solid #cbd5e1;
        background: #fff;
        border-radius: 4px;
        height: auto;
    }

    #items-table .form-control-sm:focus,
    #items-table .form-select-sm:focus {
        border-color: var(--ocd-focus-border);
        box-shadow: var(--ocd-focus-ring);
    }

    #items-table .form-control-sm.bg-transparent,
    #items-table .form-control-sm[readonly] {
        background: #f8fafc !important;
        border-color: transparent;
    }

    #items-table .select2-container .select2-selection--single {
        border: 1px solid #cbd5e1 !important;
        border-radius: 4px !important;
        height: auto !important;
        padding: 0.15rem 0.25rem;
        font-size: 0.8125rem;
    }

    #items-table .select2-container--focus .select2-selection--single,
    #items-table .select2-container--open .select2-selection--single {
        border-color: var(--ocd-focus-border) !important;
        box-shadow: var(--ocd-focus-ring);
    }

    #items-table .select2-container .select2-selection__rendered {
        line-height: 1.5 !important;
        padding-left: 0.3rem !important;
        padding-right: 1.2rem !important;
    }

    #items-table .select2-container .select2-selection__arrow {
        height: 100% !important;
        top: 0 !important;
    }

    /* Errores de validación por partida */
    #items-table .item-error {
        margin-top: 0.2rem;
        font-size: 0.75rem;
        line-height: 1.3;
        color: var(--ocd-danger);
    }

    #items-table .form-control-sm.is-invalid,
    #items-table .cell-invalid .select2-container .select2-selection--single {
        border-color: #dc3545 !important;
        background-image: none;
    }

    #items-table tr.item-row-invalid > td {
        background-color: #fff5f5;
    }

    /* Saldo presupuestal por partida */
    #items-table .item-budget {
        margin-top: 0.25rem;
        font-size: 0.75rem;
        line-height: 1.35;
        color: #475569;
        font-variant-numeric: tabular-nums;
    }

    #items-table .item-budget:empty {
        display: none;
    }

    #items-table .item-budget .budget-insufficient,
    #items-table .item-budget .budget-error {
        color: var(--ocd-danger);
        font-weight: 600;
    }

    #items-table .item-budget .budget-target {
        color: #64748b;
    }

    .budget-month-hint {
        font-size: 0.72rem;
    }

    /* Barra fija inferior (solo < 992px, donde el resumen queda al final) */
    .ocd-mobile-bar {
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 1030;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.6rem 1rem calc(0.6rem + env(safe-area-inset-bottom));
        background: #fff;
        border-top: 1px solid #e2e8f0;
        box-shadow: 0 -4px 12px rgba(15, 23, 42, 0.08);
    }

    .ocd-mobile-bar__info {
        flex: 1 1 auto;
        min-width: 0;
        line-height: 1.2;
    }

    .ocd-mobile-bar__label {
        font-size: 0.7rem;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .ocd-mobile-bar__total {
        font-size: 1rem;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        color: #0f172a;
    }

    .ocd-mobile-bar__total.is-over {
        color: var(--ocd-danger);
    }

    .ocd-mobile-bar__status {
        overflow: hidden;
        font-size: 0.75rem;
        white-space: nowrap;
        text-overflow: ellipsis;
    }

    .ocd-mobile-bar .btn {
        min-height: 44px;
        flex: 0 0 auto;
    }

    @media (max-width: 991.98px) {
        /* Deja espacio para que la barra no tape los últimos campos */
        #ocd-form {
            padding-bottom: 88px;
        }
    }

    .file-client-error:empty,
    #justification-client-error:empty {
        display: none !important;
    }
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function() {
    const costCenterCatalog = @json($costCenterCatalog);
    const productsApiUrl = $('#products-api-url').val();
    const OCD_LIMIT_MXN = {{ $ocdLimitMxn }};
    const mxnRates = @json($mxnRates);

    $('#supplier_id, #company_id, #receiving_location_id').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Seleccione...',
        allowClear: true,
        language: {
            noResults: function() { return "No se encontraron resultados"; },
            searching: function() { return "Buscando..."; }
        }
    });

    // Moneda vive en un input-group-select2, cuyo CSS oculta el <select> nativo:
    // sin Select2 el campo quedaba invisible y no se podía elegir.
    $('#currency').select2({
        theme: 'bootstrap-5',
        width: '100%',
        minimumResultsForSearch: Infinity
    });

    $('#payment_terms').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Seleccione...',
        allowClear: true,
        minimumResultsForSearch: Infinity,
        language: {
            noResults: function() { return "No se encontraron resultados"; }
        }
    });

    $('.item-iva-rate').each(function() {
        initializeIvaSelect($(this));
    });

    function initializeIvaSelect(element) {
        element.select2({
            theme: 'bootstrap-5',
            width: '100%',
            minimumResultsForSearch: Infinity,
            dropdownParent: element.closest('td')
        });
    }

    function initializeCostCenterSelect(element) {
        element.select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: $('#company_id').val() ? 'Selecciona el centro de costo' : 'Elige primero la empresa',
            minimumResultsForSearch: 5,
            dropdownParent: element.closest('td'),
            language: {
                noResults: function() { return "Sin resultados"; },
                searching: function() { return "Buscando..."; }
            }
        });
    }

    function rowIndex(row) {
        const name = row.find('.item-description').attr('name') || '';
        const match = name.match(/items\[(\d+)\]/);

        return match ? Number(match[1]) : 0;
    }

    function initializeProductSelect(element) {
        const hasCostCenter = !!element.closest('tr').find('.item-cost-center').val();

        element.select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: hasCostCenter ? 'Selecciona el producto' : 'Elige primero el centro de costo',
            dropdownParent: element.closest('td'),
            language: {
                noResults: function() { return "Sin productos"; },
                searching: function() { return "Buscando..."; }
            }
        });
    }

    function loadProductsForRow(row) {
        const companyId = $('#company_id').val();
        const costCenterId = row.find('.item-cost-center').val();
        const $productSelect = row.find('.item-product');

        if (!$productSelect.length || !companyId || !costCenterId) {
            return;
        }

        if ($productSelect.hasClass('select2-hidden-accessible')) {
            $productSelect.select2('destroy');
        }

        $productSelect.html('<option value="">Cargando productos...</option>').prop('disabled', true);

        $.ajax({
            url: productsApiUrl,
            method: 'GET',
            data: {
                company_id: companyId,
                cost_center_id: costCenterId,
                all: true
            },
            success: function(response) {
                $productSelect.empty().append('<option value="">Seleccione producto...</option>');

                if (response.products && response.products.length > 0) {
                    response.products.forEach(function(product) {
                        const label = `[${product.code}] ${product.short_name || product.description || product.technical_description}`;
                        const $option = $('<option>', {
                            value: product.id,
                            text: label,
                            'data-code': product.code,
                            'data-description': product.description || '',
                            'data-unit': product.unit_of_measure || 'PZA',
                            'data-estimated-price': product.estimated_price || 0
                        });
                        $option.data('budget-classification', product.budget_classification || null);
                        $productSelect.append($option);
                    });
                }

                $productSelect.prop('disabled', false);
                initializeProductSelect($productSelect);
                row.find('.products-load-error').remove();

                const preselected = row.find('.item-product').data('selected');
                if (preselected && $productSelect.find(`option[value="${preselected}"]`).length) {
                    $productSelect.val(String(preselected)).trigger('change', ['preselect']);
                    row.find('.item-product').removeData('selected');
                }
            },
            error: function() {
                // Se conserva el producto que se quería preseleccionar para el reintento.
                $productSelect.html('<option value=""></option>').prop('disabled', true);
                initializeProductSelect($productSelect);

                row.find('.products-load-error').remove();
                const $notice = $(`
                    <div class="item-error products-load-error" role="alert">
                        No se pudieron cargar los productos.
                        <button type="button" class="btn btn-link btn-sm p-0 align-baseline retry-products-btn">Reintentar</button>
                    </div>
                `);
                $notice.insertAfter($productSelect.next('.select2-container').length ? $productSelect.next('.select2-container') : $productSelect);
            }
        });
    }

    $(document).on('click', '.retry-products-btn', function() {
        const row = $(this).closest('tr');
        $(this).closest('.products-load-error').remove();
        loadProductsForRow(row);
    });

    function prepareProductRow(row) {
        const index = rowIndex(row);
        const description = row.find('.item-description');
        const categoryCell = row.find('.item-expense-category').closest('td');

        description.attr('type', 'hidden');

        if (!row.find('.item-product').length) {
            const $product = $(`
                <select name="items[${index}][product_service_id]" class="form-select form-select-sm border-0 item-product" aria-label="Producto" required>
                    <option value=""></option>
                </select>
            `);
            // Producto guardado (edición) o enviado antes de un error de validación.
            const preselectedProduct = row.attr('data-selected-product');
            if (preselectedProduct) {
                $product.data('selected', preselectedProduct);
            }
            description.before($product);
        }

        if (!row.find('.item-unit').length) {
            description.after(`<input type="hidden" name="items[${index}][unit_of_measure]" class="item-unit">`);
        }

        if (!row.find('.item-sku').length) {
            description.after(`<input type="hidden" name="items[${index}][sku]" class="item-sku">`);
        }

        row.find('.item-expense-category')
            .removeClass('form-select form-select-sm border-0')
            .addClass('d-none')
            .prop('disabled', false);

        if (!row.find('.item-budget-cedula').length) {
            categoryCell.append(`<input type="hidden" name="items[${index}][budget_cedula_id]" class="item-budget-cedula">`);
        }

        const costCenterId = row.find('.item-cost-center').val();
        if (costCenterId) {
            loadProductsForRow(row);
        } else {
            // Sin centro de costo no hay catálogo: el producto queda bloqueado con la instrucción.
            const $product = row.find('.item-product');
            if ($product.hasClass('select2-hidden-accessible')) {
                $product.select2('destroy');
            }
            $product.html('<option value=""></option>').prop('disabled', true);
            initializeProductSelect($product);
        }
    }

    function applySelectedProductToRow(row) {
        const $option = row.find('.item-product option:selected');
        const classification = $option.data('budget-classification');
        const categorySelect = row.find('.item-expense-category');

        if (!classification) {
            categorySelect.html('<option value=""></option>').val('');
            row.find('.item-budget-cedula').val('');
            row.find('.item-description').val('');
            row.find('.item-unit').val('');
            row.find('.item-sku').val('');
            return false;
        }

        categorySelect.html(`<option value="${classification.expense_category_id}">${classification.expense_category_name}</option>`)
            .val(String(classification.expense_category_id));
        row.find('.item-budget-cedula').val(classification.budget_cedula_id);
        row.find('.item-description').val($option.data('description') || '');
        row.find('.item-unit').val($option.data('unit') || 'PZA');
        row.find('.item-sku').val($option.data('code') || '');

        const estPrice = parseFloat($option.data('estimated-price'));
        if (!row.find('.item-unit-price').val() && estPrice > 0) {
            row.find('.item-unit-price').val(estPrice);
        }

        calculateItemRow(row);
        calculateTotals();

        return true;
    }

    function refreshReceivingLocations(selectedValue = '') {
        const $company = $('#company_id');
        const $location = $('#receiving_location_id');
        const companyId = $company.val();

        if (!companyId) {
            $location.empty().append('<option value="">Seleccione empresa primero</option>').val('');
            $location.trigger('change.select2');
            return;
        }

        $location.prop('disabled', true).empty().append('<option value="">Cargando...</option>');

        const url = String($company.attr('data-url-receiving-locations')).replace('__CID__', companyId);

        $.getJSON(url)
            .done(function(data) {
                const list = Array.isArray(data) ? data : [];
                $location.empty().append('<option value="">Seleccione...</option>');

                list.forEach(function(row) {
                    $location.append($('<option>', {
                        value: row.id,
                        text: row.label
                    }));
                });

                if (selectedValue && list.some(row => String(row.id) === String(selectedValue))) {
                    $location.val(String(selectedValue));
                } else {
                    $location.val('');
                }
            })
            .always(function() {
                $location.prop('disabled', false).trigger('change.select2');
            });
    }

    function getFilteredCostCenters() {
        const companyId = $('#company_id').val();

        if (!companyId) {
            return costCenterCatalog;
        }

        return costCenterCatalog.filter(cc => String(cc.company_id) === String(companyId));
    }

    function findCostCenterById(costCenterId) {
        return costCenterCatalog.find(cc => String(cc.id) === String(costCenterId)) || null;
    }

    function buildCostCenterOptions(selectedValue = '') {
        const companyId = $('#company_id').val();
        const options = getFilteredCostCenters();

        if (!companyId) {
            return {
                html: '<option value=""></option>',
                disabled: true
            };
        }

        if (!options.length) {
            return {
                html: '<option value="">Sin centros de costo disponibles</option>',
                disabled: false
            };
        }

        let html = '<option value=""></option>';
        options.forEach(function(option) {
            const selected = String(selectedValue) === String(option.id) ? ' selected' : '';
            const purchaseType = option.purchase_type ? ` · ${option.purchase_type}` : '';
            html += `<option value="${option.id}" data-company-id="${option.company_id}" data-purchase-type="${option.purchase_type}"${selected}>${option.label}${purchaseType}</option>`;
        });

        return { html, disabled: false };
    }

    function loadCategoriesForRow(row, costCenterId, selectedValue = null, silent = false) {
        prepareProductRow(row);
    }

    function refreshItemCostCenters() {
        const options = buildCostCenterOptions();

        $('.item-row').each(function() {
            const row = $(this);
            const select = row.find('.item-cost-center');
            const selectedValue = select.val() || select.data('selected') || '';

            if (select.hasClass('select2-hidden-accessible')) {
                select.select2('destroy');
            }

            select.html(options.html).prop('disabled', options.disabled);

            if (selectedValue && select.find(`option[value="${selectedValue}"]`).length) {
                select.val(String(selectedValue));
            } else {
                select.val('');
            }

            initializeCostCenterSelect(select);

            const effectiveCostCenterId = select.val();
            const categorySelected = row.find('.item-expense-category').data('selected') || row.find('.item-expense-category').val();
            loadCategoriesForRow(row, effectiveCostCenterId, categorySelected, true);

            select.removeData('selected');
            row.find('.item-expense-category').removeData('selected');
        });
    }

    // ============================================
    // CAMBIO DE EMPRESA: confirma antes de limpiar partidas capturadas
    // ============================================

    let currentCompanyId = $('#company_id').val() || '';

    function capturedRowsCount() {
        return $('.item-row').filter(function() {
            return $(this).find('.item-cost-center').val() || $(this).find('.item-product').val();
        }).length;
    }

    function applyCompanyChange() {
        currentCompanyId = $('#company_id').val() || '';
        refreshItemCostCenters();
        refreshReceivingLocations();
        scheduleBudgetPreview();
    }

    $('#company_id').on('change', function(e, source) {
        const nextCompanyId = $(this).val() || '';
        if (nextCompanyId === currentCompanyId) {
            return;
        }

        const captured = capturedRowsCount();
        // Elegir el centro de costo cuando aún no hay empresa no pierde nada.
        if (source === 'cost-center' || !currentCompanyId || captured === 0) {
            applyCompanyChange();
            return;
        }

        // Revierte visualmente hasta que el usuario confirme.
        $(this).val(currentCompanyId).trigger('change.select2');

        Swal.fire({
            icon: 'warning',
            title: '¿Cambiar de empresa?',
            text: `Se limpiarán la ubicación de recepción y el centro de costo y producto de ${captured === 1 ? '1 partida' : captured + ' partidas'}. Cantidades y precios se conservan.`,
            showCancelButton: true,
            confirmButtonText: 'Sí, cambiar empresa',
            cancelButtonText: 'Conservar la actual',
            reverseButtons: true,
            focusCancel: true
        }).then(function(result) {
            if (result.isConfirmed) {
                $('#company_id').val(nextCompanyId).trigger('change.select2');
                applyCompanyChange();
            }
        });
    });
    refreshReceivingLocations('{{ old('receiving_location_id', $ocd?->receiving_location_id) }}');

    $(document).on('change', '.item-cost-center', function() {
        const row = $(this).closest('tr');
        const selectedCostCenter = findCostCenterById($(this).val());

        if (selectedCostCenter && !$('#company_id').val()) {
            $('#company_id').val(String(selectedCostCenter.company_id)).trigger('change', ['cost-center']);
        }

        loadCategoriesForRow(row, $(this).val(), null, false);
    });

    $(document).on('change', '.item-product', function() {
        applySelectedProductToRow($(this).closest('tr'));
    });

    // ============================================
    // AUTOCOMPLETAR CONDICIONES DEL PROVEEDOR
    // ============================================

    $('#supplier_id').on('change', function() {
        const opt = $(this).find('option:selected');
        const paymentTerms = opt.data('payment-terms');
        const deliveryDays = opt.data('delivery-days');

        if (!$('#payment_terms').val() && paymentTerms) {
            $('#payment_terms').val(paymentTerms).trigger('change');
        }
        if (!$('#estimated_delivery_days').val() && deliveryDays) {
            $('#estimated_delivery_days').val(deliveryDays);
        }
    });

    // ============================================
    // CONTADOR DE CARACTERES
    // ============================================

    const JUSTIFICATION_MIN = 100;

    $('#justification').on('input', function() {
        const count = $(this).val().length;
        const missing = JUSTIFICATION_MIN - count;

        $('#char-count').text(count);
        $('#justification-remaining')
            .text(missing > 0
                ? `Faltan ${missing} ${missing === 1 ? 'carácter' : 'caracteres'} para el mínimo`
                : 'Mínimo de 100 caracteres cumplido')
            .toggleClass('text-muted', missing > 0)
            .toggleClass('text-success', missing <= 0);

        if (missing <= 0) {
            $(this).removeClass('is-invalid');
            $('#justification-client-error').text('');
        }
    }).trigger('input');

    // ============================================
    // CÁLCULOS DE MONTOS
    // ============================================

    $(document).on('input change', '.item-quantity, .item-unit-price, .item-iva-rate', function() {
        calculateItemRow($(this).closest('tr'));
        calculateTotals();
    });

    function calculateItemRow(row) {
        const qty      = parseFloat(row.find('.item-quantity').val())   || 0;
        const price    = parseFloat(row.find('.item-unit-price').val())  || 0;
        const ivaRate  = parseFloat(row.find('.item-iva-rate').val())    || 0;

        const subtotal = qty * price;
        const iva      = subtotal * (ivaRate / 100);
        const total    = subtotal + iva;

        row.find('.item-subtotal').val(formatMoney(subtotal));
        row.find('.item-iva').val(formatMoney(iva));
        row.find('.item-total').val(formatMoney(total));
    }

    function formatMoney(amount, currency = null) {
        const code = currency || $('#currency').val() || 'MXN';
        try {
            return new Intl.NumberFormat('es-MX', { style: 'currency', currency: code, currencyDisplay: 'narrowSymbol' }).format(amount);
        } catch (error) {
            return '$' + amount.toFixed(2);
        }
    }

    function calculateTotals() {
        let subtotal = 0;
        let ivaTotal = 0;

        $('.item-row').each(function() {
            const qty     = parseFloat($(this).find('.item-quantity').val())  || 0;
            const price   = parseFloat($(this).find('.item-unit-price').val()) || 0;
            const ivaRate = parseFloat($(this).find('.item-iva-rate').val())   || 0;

            const itemSubtotal = qty * price;
            const itemIva      = itemSubtotal * (ivaRate / 100);

            subtotal += itemSubtotal;
            ivaTotal += itemIva;
        });

        const total = subtotal + ivaTotal;
        const limit = evaluateLimit(total);

        const rowCount = $('.item-row').length;

        $('#summary-subtotal').text(formatMoney(subtotal));
        $('#summary-iva').text(formatMoney(ivaTotal));
        $('#summary-total').text(formatMoney(total))
            .toggleClass('text-danger', limit.exceeded)
            .toggleClass('text-primary', !limit.exceeded);
        $('#summary-item-count').text(rowCount === 1 ? '1 partida' : `${rowCount} partidas`);

        renderLimitStatus(limit);

        mobileState.total = total;
        mobileState.limit = limit;
        renderMobileBar();

        return { total, limit };
    }

    function formatMxn(amount) {
        return formatMoney(amount, 'MXN');
    }

    // El límite de OCD es en MXN: convierte el total con el tipo de cambio vigente.
    function evaluateLimit(total) {
        const currency = $('#currency').val() || 'MXN';
        const rate = mxnRates[currency];

        if (rate === null || rate === undefined) {
            return { currency, rate: null, totalMxn: null, exceeded: false, missingRate: true };
        }

        const totalMxn = Math.round(total * rate * 100) / 100;

        return { currency, rate, totalMxn, exceeded: totalMxn > OCD_LIMIT_MXN, missingRate: false };
    }

    function renderLimitStatus(limit) {
        const $status = $('#summary-limit-status');
        const isForeign = limit.currency !== 'MXN';

        $('#summary-currency').text(limit.currency);
        $('#summary-mxn-row').toggleClass('d-none', !isForeign || limit.missingRate);

        if (limit.missingRate) {
            renderLimitMeter(0, 'bg-secondary');
            $status.attr('class', 'mt-1 text-danger fw-semibold')
                .text(`No hay tipo de cambio ${limit.currency} → MXN sincronizado; no se puede validar el límite. Cambia a MXN o pide a Finanzas sincronizar el tipo de cambio.`);
            return;
        }

        const usedPct = Math.min(100, (limit.totalMxn / OCD_LIMIT_MXN) * 100);
        renderLimitMeter(usedPct, limit.exceeded ? 'bg-danger' : (usedPct >= 80 ? 'bg-warning' : 'bg-primary'));

        if (isForeign) {
            $('#summary-total-mxn').text(formatMxn(limit.totalMxn))
                .toggleClass('text-danger', limit.exceeded);
            $('#summary-rate').text(`TC ${Number(limit.rate).toFixed(4)}`);
        }

        const remaining = OCD_LIMIT_MXN - limit.totalMxn;

        if (limit.exceeded) {
            $status.attr('class', 'mt-1 text-danger fw-semibold')
                .text(`Excede el límite por ${formatMxn(Math.abs(remaining))} MXN. Usa una requisición regular.`);
        } else if (limit.totalMxn >= OCD_LIMIT_MXN * 0.8) {
            $status.attr('class', 'mt-1 text-warning-emphasis fw-semibold')
                .text(`Quedan ${formatMxn(remaining)} MXN antes del límite.`);
        } else {
            $status.attr('class', 'mt-1 text-muted').text(`Disponible: ${formatMxn(remaining)} MXN`);
        }
    }

    function renderLimitMeter(pct, colorClass) {
        $('#summary-limit-meter').attr('aria-valuenow', Math.round(pct));
        $('#summary-limit-bar')
            .css('width', pct + '%')
            .attr('class', 'progress-bar ' + colorClass);
    }

    $('#currency').on('change', function() {
        $('.item-row').each(function() {
            calculateItemRow($(this));
        });
        calculateTotals();
    });

    // ============================================
    // AGREGAR / ELIMINAR PARTIDAS
    // ============================================

    let itemIndex = {{ $nextItemIndex }};

    /**
     * Agrega una partida. Con `prefill` copia centro de costo, producto, cantidad,
     * precio, IVA y nota (duplicar); con `$after` la inserta debajo de esa fila.
     */
    function addItemRow(prefill = null, $after = null) {
        const costCenterOptions = buildCostCenterOptions();
        const newRow = `
            <tr class="item-row">
                <td data-label="Centro de costo">
                    <select name="items[${itemIndex}][cost_center_id]" class="form-select form-select-sm border-0 item-cost-center" aria-label="Centro de costo" required>
                        ${costCenterOptions.html}
                    </select>
                    <select name="items[${itemIndex}][expense_category_id]" class="form-select form-select-sm border-0 item-expense-category" required disabled style="display:none">
                        <option value="">Seleccione...</option>
                    </select>
                    <div class="item-budget" aria-live="polite"></div>
                </td>
                <td data-label="Producto y nota">
                    <input type="text" name="items[${itemIndex}][description]" class="form-control form-control-sm border-0 item-description" placeholder="Descripción" required maxlength="500">
                    <textarea name="items[${itemIndex}][notes]" class="form-control form-control-sm border-0 mt-1 item-notes" aria-label="Nota para el proveedor" rows="2" maxlength="1000" placeholder="Nota para el proveedor (opcional)"></textarea>
                </td>
                <td data-label="Cantidad"><input type="number" name="items[${itemIndex}][quantity]" class="form-control form-control-sm border-0 item-quantity text-end" aria-label="Cantidad" inputmode="decimal" step="0.01" min="0.01" placeholder="0" required></td>
                <td data-label="Precio unit."><input type="number" name="items[${itemIndex}][unit_price]" class="form-control form-control-sm border-0 item-unit-price text-end" aria-label="Precio unitario" inputmode="decimal" step="0.01" min="0.01" placeholder="0.00" required></td>
                <td data-label="IVA">
                    <select name="items[${itemIndex}][iva_rate]" class="form-select form-select-sm border-0 item-iva-rate" aria-label="Tasa de IVA" required>
                        <option value="16">16%</option>
                        <option value="8">8%</option>
                        <option value="0">0%</option>
                    </select>
                    <input type="text" class="form-control form-control-sm border-0 bg-transparent item-iva text-end small mt-1" value="${formatMoney(0)}" aria-label="IVA de la partida" readonly tabindex="-1">
                </td>
                <td data-label="Subtotal"><input type="text" class="form-control form-control-sm border-0 bg-transparent item-subtotal text-end" value="${formatMoney(0)}" aria-label="Subtotal de la partida" readonly tabindex="-1"></td>
                <td data-label="Total"><input type="text" class="form-control form-control-sm border-0 bg-transparent item-total text-end fw-bold" value="${formatMoney(0)}" aria-label="Total de la partida" readonly tabindex="-1"></td>
                <td class="text-center item-actions">
                    <button type="button" class="btn btn-link p-0 duplicate-item-btn" title="Duplicar partida" aria-label="Duplicar partida"><i class="ti ti-copy" aria-hidden="true"></i><span class="item-action-label">Duplicar</span></button>
                    <button type="button" class="btn btn-link text-danger p-0 remove-item-btn" title="Eliminar partida" aria-label="Eliminar partida"><i class="ti ti-trash" aria-hidden="true"></i><span class="item-action-label">Eliminar</span></button>
                </td>
            </tr>
        `;
        const $row = $(newRow);
        if ($after && $after.length) {
            $after.after($row);
        } else {
            $('#items-tbody').append($row);
        }

        if (prefill) {
            // Se asignan con .val() (no en el HTML) para no interpretar el texto de la nota.
            $row.find('.item-quantity').val(prefill.quantity);
            $row.find('.item-unit-price').val(prefill.unit_price);
            $row.find('.item-iva-rate').val(prefill.iva_rate);
            $row.find('.item-notes').val(prefill.notes);
            if (prefill.cost_center_id && $row.find(`.item-cost-center option[value="${prefill.cost_center_id}"]`).length) {
                $row.find('.item-cost-center').val(String(prefill.cost_center_id));
            }
            if (prefill.product_service_id) {
                $row.attr('data-selected-product', prefill.product_service_id);
            }
        }

        initializeCostCenterSelect($row.find('.item-cost-center'));
        initializeIvaSelect($row.find('.item-iva-rate'));
        prepareProductRow($row);

        itemIndex++;
        formDirty = true;
        calculateItemRow($row);
        calculateTotals();
        updateRemoveButtons();
        scheduleBudgetPreview();

        return $row;
    }

    $('#add-item-btn').on('click', function() {
        const $row = addItemRow();

        // Lleva el foco al primer campo de la nueva partida (si ya hay empresa).
        if ($('#company_id').val()) {
            $row.find('.item-cost-center').select2('open');
        }
    });

    // Duplicar: copia la partida debajo de la original y deja el foco en la cantidad.
    $(document).on('click', '.duplicate-item-btn', function() {
        const $source = $(this).closest('tr');
        const $copy = addItemRow({
            cost_center_id: $source.find('.item-cost-center').val(),
            product_service_id: $source.find('.item-product').val() || $source.attr('data-selected-product') || '',
            quantity: $source.find('.item-quantity').val(),
            unit_price: $source.find('.item-unit-price').val(),
            iva_rate: $source.find('.item-iva-rate').val(),
            notes: $source.find('.item-notes').val()
        }, $source);

        $copy.find('.item-quantity').trigger('focus').trigger('select');
    });

    // Con una sola partida no se puede borrar: el botón se deshabilita en lugar de avisar después.
    function updateRemoveButtons() {
        const onlyOne = $('#items-tbody tr').length <= 1;
        $('.remove-item-btn')
            .prop('disabled', onlyOne)
            .attr('title', onlyOne ? 'La OCD necesita al menos una partida' : 'Eliminar partida');
    }

    function rowHasData(row) {
        return !!(row.find('.item-product').val() || row.find('.item-quantity').val()
            || row.find('.item-unit-price').val() || row.find('.item-notes').val());
    }

    function removeRow(row) {
        row.find('.select2-hidden-accessible').each(function() {
            $(this).select2('destroy');
        });
        row.remove();
        formDirty = true;
        calculateTotals();
        updateRemoveButtons();
        scheduleBudgetPreview();
    }

    $(document).on('click', '.remove-item-btn', function() {
        if ($('#items-tbody tr').length <= 1) {
            return;
        }

        const row = $(this).closest('tr');
        if (!rowHasData(row)) {
            removeRow(row);
            return;
        }

        const productLabel = row.find('.item-product option:selected').text().trim();
        Swal.fire({
            icon: 'warning',
            title: '¿Eliminar esta partida?',
            text: productLabel ? `Se quitará «${productLabel}» de la orden.` : 'Se perderán los datos capturados en esta partida.',
            showCancelButton: true,
            confirmButtonText: 'Eliminar partida',
            cancelButtonText: 'Conservar',
            reverseButtons: true,
            focusCancel: true
        }).then(function(result) {
            if (result.isConfirmed) {
                removeRow(row);
            }
        });
    });

    // ============================================
    // CAMBIOS SIN GUARDAR
    // ============================================

    let formDirty = false;

    $('#ocd-form').on('input', ':input', function() {
        formDirty = true;
    });
    $('#ocd-form').on('select2:select select2:unselect', 'select', function() {
        formDirty = true;
    });
    $('#ocd-form').on('change', 'input[type="file"]', function() {
        formDirty = true;
    });

    $('#ocd-cancel-btn').on('click', function(e) {
        if (!formDirty) {
            return;
        }

        e.preventDefault();
        const href = this.href;
        Swal.fire({
            icon: 'warning',
            title: '¿Descartar los cambios?',
            text: 'Lo que capturaste en esta OCD no se guardará.',
            showCancelButton: true,
            confirmButtonText: 'Descartar y salir',
            cancelButtonText: 'Seguir editando',
            reverseButtons: true,
            focusCancel: true
        }).then(function(result) {
            if (result.isConfirmed) {
                window.location.href = href;
            }
        });
    });

    // ============================================
    // ERRORES DEL SERVIDOR: se limpian al corregir el campo
    // ============================================

    $(document).on('input change', '#items-table .is-invalid, #items-table .cell-invalid :input', function(e, source) {
        if (source === 'preselect') {
            return;
        }

        const cell = $(this).closest('td');
        cell.removeClass('cell-invalid').find('.is-invalid').removeClass('is-invalid');
        $(this).removeClass('is-invalid');
        cell.find('.item-error').remove();

        const row = cell.closest('tr');
        if (!row.find('.item-error').length) {
            row.removeClass('item-row-invalid');
        }
        if (!$('#items-table .item-error').length) {
            $('#items-error-summary').remove();
        }
    });

    // ============================================
    // VALIDACIÓN DE ADJUNTOS EN EL NAVEGADOR
    // ============================================

    function validateFileInput(input) {
        const $input = $(input);
        const maxFiles = Number($input.data('max-files')) || 1;
        const maxBytes = (Number($input.data('max-mb')) || 5) * 1024 * 1024;
        const files = Array.from(input.files || []);
        const $error = $('#' + input.id + '_client_error');
        let message = '';

        if (files.length > maxFiles) {
            message = `Seleccionaste ${files.length} archivos; el máximo es ${maxFiles}.`;
        } else {
            const tooBig = files.filter(f => f.size > maxBytes).map(f => f.name);
            if (tooBig.length) {
                message = `Supera ${$input.data('max-mb')}MB: ${tooBig.join(', ')}. Elige un archivo más ligero.`;
            }
        }

        $error.text(message);
        $input.toggleClass('is-invalid', message !== '');
        if (message) {
            input.value = '';
        }

        return message === '';
    }

    $('#quotation_file, #support_documents').on('change', function() {
        validateFileInput(this);
    });

    // ============================================
    // VALIDACIÓN ANTES DE ENVIAR: mensajes junto al campo, sin modales
    // ============================================

    $('#ocd-form').on('submit', function(e) {
        const $submit = $('.ocd-submit');

        // Evita doble envío (doble clic o Enter repetido).
        if ($submit.prop('disabled')) {
            e.preventDefault();
            return false;
        }

        const { limit } = calculateTotals();
        let $firstProblem = null;

        const justificationLength = $('#justification').val().length;
        if (justificationLength < JUSTIFICATION_MIN) {
            const missing = JUSTIFICATION_MIN - justificationLength;
            $('#justification').addClass('is-invalid');
            $('#justification-client-error').text(`La justificación debe tener al menos ${JUSTIFICATION_MIN} caracteres; faltan ${missing}.`);
            $firstProblem = $firstProblem || $('#justification');
        }

        if (limit.exceeded || limit.missingRate) {
            // El estado del límite ya explica el problema en el resumen; se lleva el foco ahí.
            $firstProblem = $firstProblem || $('#summary-limit');
        }

        if ($firstProblem) {
            e.preventDefault();
            $firstProblem[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            $firstProblem.trigger('focus');
            return false;
        }
        $submit.prop('disabled', true).attr('aria-busy', 'true')
            .html('<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Guardando...');
    });

    // Enlace "Ir al primero" de la alerta de errores del servidor.
    $('#go-to-first-error').on('click', function(e) {
        e.preventDefault();
        const $first = $('#ocd-form')
            .find('.is-invalid, .cell-invalid, .select2-container.is-invalid')
            .filter(':visible')
            .first();
        if (!$first.length) {
            return;
        }
        $first[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        const $focusable = $first.is(':input') ? $first : $first.find('.select2-selection, :input:visible').first();
        $focusable.trigger('focus');
    });

    // ============================================
    // CAMPOS OBLIGATORIOS CON SELECT2
    // El select real está oculto, así que el navegador no puede mostrar su aviso:
    // se validan aquí, antes de la validación nativa del resto de campos.
    // ============================================

    const requiredHeaderSelects = [
        ['#company_id', 'Elige la empresa.'],
        ['#receiving_location_id', 'Elige la ubicación de recepción.'],
        ['#supplier_id', 'Elige el proveedor.'],
        ['#currency', 'Elige la moneda.']
    ];

    function markHeaderSelectInvalid($select, message) {
        $select.next('.select2-container').addClass('is-invalid');
        const $group = $select.closest('.input-group');
        let $message = $group.siblings('.select-client-error');
        if (!$message.length) {
            $message = $('<div class="invalid-feedback d-block select-client-error" role="alert"></div>');
            $group.after($message);
        }
        $message.text(message);
    }

    function clearHeaderSelectInvalid($select) {
        $select.next('.select2-container').removeClass('is-invalid');
        $select.closest('.input-group').siblings('.select-client-error').remove();
    }

    function markRowSelectInvalid($select, message) {
        const $cell = $select.closest('td');
        $cell.addClass('cell-invalid').find('.item-error.item-error-client').remove();
        $('<div class="item-error item-error-client" role="alert"></div>')
            .text(message)
            .insertAfter($select.next('.select2-container').length ? $select.next('.select2-container') : $select);
        $cell.closest('tr').addClass('item-row-invalid');
    }

    function validateRequiredSelects() {
        let $first = null;

        requiredHeaderSelects.forEach(function([selector, message]) {
            const $select = $(selector);
            if (!$select.val()) {
                markHeaderSelectInvalid($select, message);
                $first = $first || $select;
            } else {
                clearHeaderSelectInvalid($select);
            }
        });

        // Sin empresa los centros de costo están bloqueados: basta con señalar la empresa.
        if ($('#company_id').val()) {
            $('.item-row').each(function() {
                const row = $(this);
                [['.item-cost-center', 'Elige el centro de costo.'], ['.item-product', 'Elige el producto.']].forEach(function([selector, message]) {
                    const $select = row.find(selector);
                    if ($select.length && !$select.val() && !$select.prop('disabled')) {
                        markRowSelectInvalid($select, message);
                        $first = $first || $select;
                    }
                });
            });
        }

        return $first;
    }

    function focusSelect2($select) {
        const $selection = $select.next('.select2-container').find('.select2-selection');
        const target = ($selection.length ? $selection : $select)[0];
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        $(target).trigger('focus');
    }

    $('.ocd-submit').on('click', function(e) {
        const $first = validateRequiredSelects();
        if ($first) {
            e.preventDefault();
            focusSelect2($first);
        }
    });

    requiredHeaderSelects.forEach(function([selector]) {
        $(selector).on('change', function() {
            if ($(this).val()) {
                clearHeaderSelectInvalid($(this));
            }
        });
    });

    // ============================================
    // PRESUPUESTO DISPONIBLE (vista previa, no aparta presupuesto)
    // ============================================

    const budgetPreviewUrl = $('#budget-preview-url').val();
    const editingOcdId = @json($ocd?->id);
    let budgetTimer = null;
    let budgetXhr = null;

    function scheduleBudgetPreview() {
        clearTimeout(budgetTimer);
        budgetTimer = setTimeout(loadBudgetPreview, 450);
    }

    function collectBudgetItems() {
        const items = {};
        $('.item-row').each(function() {
            const row = $(this);
            items[rowIndex(row)] = {
                cost_center_id: row.find('.item-cost-center').val() || '',
                product_service_id: row.find('.item-product').val() || '',
                quantity: row.find('.item-quantity').val() || 0,
                unit_price: row.find('.item-unit-price').val() || 0
            };
        });
        return items;
    }

    function setBudgetSummary(text, tone) {
        $('#summary-budget-status').attr('class', 'mt-1 ' + (tone || 'text-muted')).text(text);
    }

    function loadBudgetPreview() {
        if (budgetXhr) {
            budgetXhr.abort();
        }

        const items = collectBudgetItems();
        const hasReadyRow = Object.values(items).some(i => i.cost_center_id && i.product_service_id);

        if (!hasReadyRow) {
            $('.item-budget').empty();
            $('#summary-budget-month').text('');
            mobileState.budgetProblems = 0;
            mobileState.budgetChecked = 0;
            renderMobileBar();
            setBudgetSummary('Elige centro de costo y producto para ver el saldo.');
            return;
        }

        setBudgetSummary('Consultando presupuesto…');

        budgetXhr = $.getJSON(budgetPreviewUrl, {
            currency: $('#currency').val() || 'MXN',
            supplier_id: $('#supplier_id').val() || '',
            estimated_delivery_days: $('#estimated_delivery_days').val() || '',
            direct_purchase_order_id: editingOcdId || '',
            items: items
        })
            .done(renderBudgetPreview)
            .fail(function(xhr, status) {
                if (status === 'abort') {
                    return;
                }
                setBudgetSummary('No se pudo consultar el presupuesto. Se validará al guardar.', 'text-warning-emphasis');
            })
            .always(function() {
                budgetXhr = null;
            });
    }

    function budgetLine(allocation, showTarget) {
        const $line = $('<div>');
        if (showTarget) {
            $line.append($('<span class="budget-target">').text(allocation.cost_center + ': '));
        }

        if (allocation.status === 'free') {
            return $line.append(document.createTextNode('Consumo libre, sin tope de presupuesto'));
        }
        if (allocation.status === 'error') {
            return $line.append($('<span class="budget-error">').text(allocation.message));
        }
        if (allocation.status === 'insufficient') {
            return $line.append($('<span class="budget-insufficient">')
                .text(`Saldo insuficiente: disponible ${formatMxn(allocation.available)}, requiere ${formatMxn(allocation.required)}`));
        }

        return $line.append(document.createTextNode(
            `Disponible ${formatMxn(allocation.available)} · usa ${formatMxn(allocation.required)}`
        ));
    }

    function renderBudgetPreview(data) {
        $('#summary-budget-month').text(data.month_label || '');

        if (data.error) {
            $('.item-budget').empty();
            setBudgetSummary(data.error, 'text-danger fw-semibold');
            return;
        }

        const rows = data.rows || {};
        let problems = 0;
        let checked = 0;

        $('.item-row').each(function() {
            const row = $(this);
            const result = rows[rowIndex(row)];
            const $target = row.find('.item-budget').empty();

            if (!result || result.status === 'incomplete') {
                return;
            }

            checked++;
            if (result.status === 'insufficient' || result.status === 'error') {
                problems++;
            }

            if (result.message && !result.allocations) {
                $target.append($('<div>').append($('<span class="budget-error">').text(result.message)));
                return;
            }

            const allocations = result.allocations || [];
            allocations.forEach(function(allocation) {
                $target.append(budgetLine(allocation, allocations.length > 1));
            });
        });

        mobileState.budgetProblems = problems;
        mobileState.budgetChecked = checked;
        renderMobileBar();

        if (problems > 0) {
            setBudgetSummary(
                `${problems === 1 ? '1 partida' : problems + ' partidas'} sin saldo suficiente. La OCD podría rechazarse al enviarla.`,
                'text-danger fw-semibold'
            );
        } else if (checked > 0) {
            setBudgetSummary(
                checked === 1 ? 'La partida tiene saldo disponible.' : `Las ${checked} partidas tienen saldo disponible.`,
                'text-success'
            );
        } else {
            setBudgetSummary('Elige centro de costo y producto para ver el saldo.');
        }
    }

    $(document).on('change', '.item-cost-center, .item-product', scheduleBudgetPreview);
    $(document).on('input change', '.item-quantity, .item-unit-price', scheduleBudgetPreview);
    $('#currency, #supplier_id').on('change', scheduleBudgetPreview);
    $('#estimated_delivery_days').on('input', scheduleBudgetPreview);

    // Si el usuario regresa con "Atrás", el navegador puede restaurar el botón deshabilitado.
    $('.ocd-submit').each(function() {
        $(this).data('original-html', $(this).html());
    });
    $(window).on('pageshow', function() {
        $('.ocd-submit').each(function() {
            $(this).prop('disabled', false).removeAttr('aria-busy').html($(this).data('original-html'));
        });
    });

    // ============================================
    // BARRA INFERIOR EN PANTALLAS ANGOSTAS
    // ============================================

    const mobileState = { total: 0, limit: null, budgetProblems: 0, budgetChecked: 0 };

    function renderMobileBar() {
        const limit = mobileState.limit;
        if (!limit) {
            return;
        }

        $('#mobile-currency').text(limit.currency);
        $('#mobile-total').text(formatMoney(mobileState.total)).toggleClass('is-over', limit.exceeded);

        let text = 'Dentro del límite de OCD';
        let tone = 'text-muted';

        if (limit.missingRate) {
            text = `Sin tipo de cambio ${limit.currency} → MXN`;
            tone = 'text-danger fw-semibold';
        } else if (limit.exceeded) {
            text = 'Excede el límite de OCD';
            tone = 'text-danger fw-semibold';
        } else if (mobileState.budgetProblems > 0) {
            text = mobileState.budgetProblems === 1 ? '1 partida sin saldo' : `${mobileState.budgetProblems} partidas sin saldo`;
            tone = 'text-danger fw-semibold';
        } else if (mobileState.budgetChecked > 0) {
            text = 'Con saldo y dentro del límite';
            tone = 'text-success';
        }

        $('#mobile-status').attr('class', 'ocd-mobile-bar__status ' + tone).text(text);
    }

    $('#mobile-summary-btn').on('click', function() {
        const summary = document.querySelector('.ocd-summary');
        summary.scrollIntoView({ behavior: 'smooth', block: 'start' });
        $('#summary-limit').trigger('focus');
    });

    // Estado inicial: totales y centros de costo de cada partida
    $('.item-row').each(function() {
        calculateItemRow($(this));
    });
    refreshItemCostCenters();
    calculateTotals();
    updateRemoveButtons();
});
</script>
@endpush
