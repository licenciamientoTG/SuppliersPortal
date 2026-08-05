@extends('layouts.zircos')

@section('title', 'Nuevo Producto/Servicio')
@section('page.title', 'Nuevo Producto/Servicio')
@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('products-services.index') }}">Catálogo</a></li>
    <li class="breadcrumb-item active">Nuevo</li>
@endsection

@section('content')
    <form action="{{ route('products-services.store') }}" method="POST">
    @csrf

    <div class="row">
        {{-- Columna Principal --}}
        <div class="col-lg-9">
            {{-- Información Principal --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="ti ti-info-circle me-2"></i>Información Principal
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        {{-- Tipo de Producto --}}
                        <div class="col-md-6 mb-3">
                            <label for="product_type" class="form-label">Tipo <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="ti ti-category"></i>
                                </span>
                                <select class="form-select @error('product_type') is-invalid @enderror" 
                                        id="product_type"
                                        name="product_type" 
                                        required>
                                    <option value="PRODUCTO" {{ old('product_type', 'PRODUCTO') == 'PRODUCTO' ? 'selected' : '' }}>
                                        Producto Físico
                                    </option>
                                    <option value="SERVICIO" {{ old('product_type') == 'SERVICIO' ? 'selected' : '' }}>
                                        Servicio
                                    </option>
                                </select>
                            </div>
                            @error('product_type')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                        @include('products_services.partials.budget-cedula-selector', [
                                'selectorId' => 'budget-cedula-selector-create',
                                'budgetCedulas' => $budgetCedulas,
                                'selectedIds' => collect(old('budget_cedula_ids', [])),
                                'collapseGroups' => true,
                                'departments' => $departments,
                                'departmentAssignments' => old('department_subaccount_assignments', $departmentAssignments),
                        ])
                        @error('budget_cedula_ids')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        @error('department_subaccount_assignments')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="is_inventoriable" name="is_inventoriable" value="1"
                               {{ old('is_inventoriable', $productService->product_type === 'PRODUCTO') ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_inventoriable">Inventariable</label>
                        <div class="form-text">Marca si este producto se controla por inventario. Se sugiere automáticamente según el tipo, pero puedes ajustarlo.</div>
                    </div>

                    {{-- Descripción Técnica --}}
                    <div class="mb-3">
                        <label for="technical_description" class="form-label">Descripción Técnica</label>
                        <textarea class="form-control @error('technical_description') is-invalid @enderror" 
                                    id="technical_description"
                                    name="technical_description" 
                                    rows="3" 
                                    maxlength="5000"
                                    placeholder="Descripción detallada y completa del producto o servicio...">{{ old('technical_description') }}</textarea>
                        <div class="form-text">Opcional. Puedes dejarla vacía si el nombre corto describe correctamente el producto o servicio.</div>
                        @error('technical_description')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Nombre Corto --}}
                    <div class="mb-3">
                        <label for="short_name" class="form-label">Nombre Corto (Opcional)</label>
                        <input type="text" 
                                class="form-control @error('short_name') is-invalid @enderror"
                                id="short_name" 
                                name="short_name" 
                                value="{{ old('short_name') }}" 
                                maxlength="100"
                                placeholder="Ej: Laptop Dell Optiplex 5150">
                        <div class="form-text">Nombre resumido para listados (máx. 100 caracteres)</div>
                        @error('short_name')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- Especificaciones Técnicas --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="ti ti-file-settings me-2"></i>Especificaciones Técnicas
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        {{-- Marca --}}
                        <div class="col-md-4 mb-3">
                            <label for="brand" class="form-label">Marca</label>
                            <input type="text" 
                                    class="form-control @error('brand') is-invalid @enderror"
                                    id="brand" 
                                    name="brand" 
                                    value="{{ old('brand') }}" 
                                    maxlength="100"
                                    placeholder="Ej: Dell, HP, Microsoft">
                            @error('brand')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Modelo --}}
                        <div class="col-md-4 mb-3">
                            <label for="model" class="form-label">Modelo</label>
                            <input type="text" 
                                    class="form-control @error('model') is-invalid @enderror"
                                    id="model" 
                                    name="model" 
                                    value="{{ old('model') }}" 
                                    maxlength="100"
                                    placeholder="Ej: Optiplex 5150">
                            @error('model')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Unidad de Medida --}}
                        <div class="col-md-4 mb-3">
                            <label for="unit_of_measure" class="form-label">Unidad de Medida <span class="text-danger">*</span></label>
                            <select class="form-select @error('unit_of_measure') is-invalid @enderror" 
                                    id="unit_of_measure"
                                    name="unit_of_measure" 
                                    required>
                                @foreach ($unitsOfMeasure as $value => $label)
                                    <option value="{{ $value }}"
                                        {{ old('unit_of_measure', 'PIEZA') == $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('unit_of_measure')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Especificaciones JSON (Opcional) --}}
                    <div class="mb-3">
                        <label for="specifications" class="form-label">Especificaciones Adicionales (JSON)</label>
                        <textarea class="form-control font-monospace @error('specifications') is-invalid @enderror" 
                                    id="specifications"
                                    name="specifications" 
                                    rows="4"
                                    placeholder='{"procesador": "Intel Core i7", "ram": "16GB", "almacenamiento": "512GB SSD"}'>{{ old('specifications') }}</textarea>
                        <div class="form-text">Formato JSON para especificaciones técnicas estructuradas</div>
                        @error('specifications')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- Información Comercial --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="ti ti-shopping-cart me-2"></i>Información Comercial
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        {{-- Proveedor Sugerido --}}
                        <div class="col-md-6 mb-3">
                            <label for="default_vendor_id" class="form-label">Proveedor Sugerido</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="ti ti-truck"></i>
                                </span>
                                <select class="form-select @error('default_vendor_id') is-invalid @enderror" 
                                        id="default_vendor_id"
                                        name="default_vendor_id">
                                    <option value="">Sin proveedor sugerido</option>
                                    
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}"
                                            {{ old('default_vendor_id') == $supplier->id ? 'selected' : '' }}>
                                            {{ $supplier->company_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-text">Sugerencia para Compras (no vinculante)</div>
                            @error('default_vendor_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Tiempo de Entrega --}}
                        <div class="col-md-6 mb-3">
                            <label for="lead_time_days" class="form-label">Tiempo de Entrega (días)</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="ti ti-clock"></i>
                                </span>
                                <input type="number" 
                                        class="form-control @error('lead_time_days') is-invalid @enderror"
                                        id="lead_time_days" 
                                        name="lead_time_days" 
                                        value="{{ old('lead_time_days') }}" 
                                        min="1"
                                        max="365"
                                        placeholder="Ej: 15">
                                <span class="input-group-text">días</span>
                            </div>
                            <div class="form-text">Días estimados de entrega del proveedor</div>
                            @error('lead_time_days')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row">
                        {{-- Cantidad Mínima --}}
                        <div class="col-md-6 mb-3">
                            <label for="minimum_quantity" class="form-label">Cantidad Mínima</label>
                            <input type="number" 
                                    class="form-control @error('minimum_quantity') is-invalid @enderror"
                                    id="minimum_quantity" 
                                    name="minimum_quantity" 
                                    value="{{ old('minimum_quantity') }}" 
                                    step="0.001"
                                    min="0.001"
                                    placeholder="Ej: 1.000">
                            <div class="form-text">Cantidad mínima de compra</div>
                            @error('minimum_quantity')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Cantidad Máxima --}}
                        <div class="col-md-6 mb-3">
                            <label for="maximum_quantity" class="form-label">Cantidad Máxima</label>
                            <input type="number" 
                                    class="form-control @error('maximum_quantity') is-invalid @enderror"
                                    id="maximum_quantity" 
                                    name="maximum_quantity" 
                                    value="{{ old('maximum_quantity') }}" 
                                    step="0.001"
                                    min="0.001"
                                    placeholder="Ej: 100.000">
                            <div class="form-text">Cantidad máxima permitida por requisición</div>
                            @error('maximum_quantity')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Observaciones --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="ti ti-notes me-2"></i>Observaciones
                    </h5>
                </div>
                <div class="card-body">
                    {{-- Observaciones (visibles para requisitores) --}}
                    <div class="mb-3">
                        <label for="observations" class="form-label">Observaciones Generales</label>
                        <textarea class="form-control @error('observations') is-invalid @enderror" 
                                    id="observations"
                                    name="observations" 
                                    rows="3"
                                    maxlength="2000"
                                    placeholder="Información visible para requisitores...">{{ old('observations') }}</textarea>
                        <div class="form-text">Visible para todos los usuarios que crean requisiciones</div>
                        @error('observations')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Notas Internas (solo para Compras) --}}
                    <div class="mb-3">
                        <label for="internal_notes" class="form-label">Notas Internas (Solo para Compras)</label>
                        <textarea class="form-control @error('internal_notes') is-invalid @enderror" 
                                    id="internal_notes"
                                    name="internal_notes" 
                                    rows="3"
                                    maxlength="2000"
                                    placeholder="Notas privadas solo visibles para el área de Compras...">{{ old('internal_notes') }}</textarea>
                        <div class="form-text">Solo visible para personal autorizado de Compras</div>
                        @error('internal_notes')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Columna Lateral --}}
        <div class="col-lg-3">
            {{-- Información Financiera y Contable --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="ti ti-calculator me-2"></i>Financiero y Contable
                    </h5>
                </div>
                <div class="card-body">
                    {{-- Información Financiera --}}
                    <div class="mb-4">
                        <h6 class="border-bottom pb-2 mb-3">
                            <i class="ti ti-cash me-1"></i>Financiero
                        </h6>
                        
                        <div class="mb-3">
                            <label for="estimated_price" class="form-label">Precio Estimado <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">
                                    <i class="ti ti-currency-dollar"></i>
                                </span>
                                <input type="number" 
                                        class="form-control @error('estimated_price') is-invalid @enderror"
                                        id="estimated_price" 
                                        name="estimated_price"
                                        value="{{ old('estimated_price', 0) }}" 
                                        step="0.01"
                                        min="0" 
                                        required 
                                        placeholder="0.00">
                            </div>
                            <div class="form-text small">Precio referencial</div>
                            @error('estimated_price')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="currency_code" class="form-label">Moneda</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">
                                    <i class="ti ti-coin"></i>
                                </span>
                                <select class="form-select @error('currency_code') is-invalid @enderror" 
                                        id="currency_code"
                                        name="currency_code">
                                    <option value="MXN" {{ old('currency_code', 'MXN') == 'MXN' ? 'selected' : '' }}>
                                        MXN
                                    </option>
                                    <option value="USD" {{ old('currency_code') == 'USD' ? 'selected' : '' }}>
                                        USD
                                    </option>
                                    <option value="EUR" {{ old('currency_code') == 'EUR' ? 'selected' : '' }}>
                                        EUR
                                    </option>
                                </select>
                            </div>
                            @error('currency_code')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                </div>
            </div>

            {{-- Botones de Acción --}}
            <div class="card">
                <div class="card-body p-3">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="ti ti-device-floppy me-1"></i>Guardar
                        </button>
                        <a href="{{ route('products-services.index') }}" class="btn btn-secondary btn-sm">
                            <i class="ti ti-x me-1"></i>Cancelar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

@endsection

@push('scripts')
    <script>
        $(function() {
            initializeBudgetCedulaSelector('#budget-cedula-selector-create');

            // Sugerir Inventariable según el tipo de producto
            $('#product_type').on('change', function () {
                $('#is_inventoriable').prop('checked', $(this).val() === 'PRODUCTO');
            });

            // Validar JSON de especificaciones al enviar
            $('form').on('submit', function(e) {
                const specs = $('#specifications').val().trim();
                
                if (specs) {
                    try {
                        JSON.parse(specs);
                    } catch (error) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'JSON Inválido',
                            text: 'Las especificaciones deben ser un JSON válido o dejar el campo vacío.',
                        });
                        return false;
                    }
                }
            });
        });
    </script>
@endpush

