<form id="supplierForm" action="{{ route('admin.suppliers.update', $supplier) }}" method="POST">
    @csrf
    @method('PUT')

    <div class="modal-header">
        <h5 class="modal-title">Editar proveedor</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
    </div>

    <div class="modal-body">
        <div id="formErrors" class="d-none"></div>

        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Nombre</label>
                <input type="text" name="first_name" class="form-control" value="{{ old('first_name', $supplier->first_name) }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Apellidos</label>
                <input type="text" name="last_name" class="form-control" value="{{ old('last_name', $supplier->last_name) }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Correo de acceso</label>
                <input type="email" name="email" class="form-control" value="{{ old('email', $supplier->email) }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Empresa</label>
                <input type="text" name="company_name" class="form-control" value="{{ old('company_name', $supplier->company_name) }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">RFC</label>
                <input type="text" name="rfc" class="form-control" value="{{ old('rfc', $supplier->rfc) }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Contacto</label>
                <input type="text" name="contact_person" class="form-control" value="{{ old('contact_person', $supplier->contact_person) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Teléfono</label>
                <input type="text" name="contact_phone" class="form-control" value="{{ old('contact_phone', $supplier->contact_phone) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Teléfono empresa</label>
                <input type="text" name="phone_number" class="form-control" value="{{ old('phone_number', $supplier->phone_number) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Tipo</label>
                <select name="supplier_type" class="form-select">
                    @foreach (['product' => 'Productos', 'service' => 'Servicios', 'product_service' => 'Productos y servicios', 'both' => 'Ambos'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('supplier_type', $supplier->supplier_type) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Moneda</label>
                <select name="currency" class="form-select">
                    @foreach($currencies as $k => $label)
                        <option value="{{ $k }}" @selected(old('currency', $supplier->currency) === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @php
                $acceptedCurrencies = old('accepted_currencies', $supplier->accepted_currencies ?? []);
                $acceptedCurrencies = is_array($acceptedCurrencies) ? $acceptedCurrencies : [];
            @endphp
            <div class="col-md-3">
                <label class="form-label">Moneda(s) que maneja</label>
                <div class="d-flex gap-3 pt-1">
                    @foreach (['MXN' => 'Pesos (MXN)', 'USD' => 'Dólares (USD)'] as $value => $label)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="acceptedCurrency{{ $value }}" name="accepted_currencies[]" value="{{ $value }}" @checked(in_array($value, $acceptedCurrencies, true))>
                            <label class="form-check-label" for="acceptedCurrency{{ $value }}">{{ $label }}</label>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="supplierActive" name="is_active" value="1" {{ old('is_active', $supplier->is_active) ? 'checked' : '' }}>
                    <label class="form-check-label" for="supplierActive">Proveedor activo</label>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label">Dirección</label>
                <textarea name="address" class="form-control" rows="2">{{ old('address', $supplier->address) }}</textarea>
            </div>
            <div class="col-md-3">
                <label class="form-label">Código postal</label>
                <input type="text" name="postal_code" class="form-control" value="{{ old('postal_code', $supplier->postal_code) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Banco</label>
                <input type="text" name="bank_name" class="form-control" value="{{ old('bank_name', $supplier->bank_name) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Cuenta</label>
                <input type="text" name="account_number" class="form-control" value="{{ old('account_number', $supplier->account_number) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">CLABE</label>
                <input type="text" name="clabe" class="form-control" value="{{ old('clabe', $supplier->clabe) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Estado de aprobación</label>
                <input type="text" class="form-control" value="{{ $supplier->approval_status }}" disabled>
            </div>
            <div class="col-md-6">
                <label class="form-label">Estado documental</label>
                <input type="text" class="form-control" value="{{ $supplier->document_status }}" disabled>
            </div>
            <div class="col-12">
                <label class="form-label">Notas de aprobación</label>
                <textarea class="form-control" rows="2" disabled>{{ $supplier->approval_notes }}</textarea>
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar cambios</button>
    </div>
</form>
