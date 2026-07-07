<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Empresa <span class="text-danger">*</span></label>
        <select name="company_id" class="form-select @error('company_id') is-invalid @enderror" required>
            <option value="">Seleccionar...</option>
            @foreach($companies as $company)
                <option value="{{ $company->id }}" {{ (int) old('company_id', $location->company_id ?? 0) === (int) $company->id ? 'selected' : '' }}>
                    {{ $company->code }} - {{ $company->name }}
                </option>
            @endforeach
        </select>
        @error('company_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Código <span class="text-danger">*</span></label>
        <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
            value="{{ old('code', $location->code ?? '') }}" maxlength="20" required>
        @error('code')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">Nombre <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name', $location->name ?? '') }}" maxlength="100" required>
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Tipo <span class="text-danger">*</span></label>
        <select name="type" class="form-select @error('type') is-invalid @enderror" required>
            @foreach([
                'service_station' => 'Estación de Servicio',
                'corporate'       => 'Corporativo',
                'warehouse'       => 'Almacén',
                'other'           => 'Otro',
            ] as $value => $label)
                <option value="{{ $value }}" {{ old('type', $location->type ?? 'service_station') === $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('type')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-9">
        <label class="form-label">Dirección</label>
        <input type="text" name="address" class="form-control @error('address') is-invalid @enderror"
            value="{{ old('address', $location->address ?? '') }}" maxlength="255">
        @error('address')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Código Postal</label>
        <input type="text" name="postal_code" class="form-control @error('postal_code') is-invalid @enderror"
            value="{{ old('postal_code', $location->postal_code ?? '') }}" maxlength="10">
        @error('postal_code')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Ciudad</label>
        <input type="text" name="city" class="form-control @error('city') is-invalid @enderror"
            value="{{ old('city', $location->city ?? '') }}" maxlength="100">
        @error('city')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Estado</label>
        <input type="text" name="state" class="form-control @error('state') is-invalid @enderror"
            value="{{ old('state', $location->state ?? '') }}" maxlength="50">
        @error('state')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">País</label>
        <input type="text" name="country" class="form-control @error('country') is-invalid @enderror"
            value="{{ old('country', $location->country ?? 'México') }}" maxlength="50">
        @error('country')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Responsable</label>
        <input type="text" name="manager_name" class="form-control @error('manager_name') is-invalid @enderror"
            value="{{ old('manager_name', $location->manager_name ?? '') }}" maxlength="100">
        @error('manager_name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Teléfono</label>
        <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror"
            value="{{ old('phone', $location->phone ?? '') }}" maxlength="20">
        @error('phone')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Correo Electrónico</label>
        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
            value="{{ old('email', $location->email ?? '') }}" maxlength="100">
        @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <label class="form-label">Notas</label>
        <textarea name="notes" rows="2" class="form-control @error('notes') is-invalid @enderror" maxlength="255">{{ old('notes', $location->notes ?? '') }}</textarea>
        @error('notes')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 d-flex gap-4">
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                {{ old('is_active', $location->is_active ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_active">Activa</label>
        </div>
    </div>

    <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="ti ti-device-floppy"></i> Guardar
        </button>
        <a href="{{ route('receiving-locations.index') }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left"></i> Volver
        </a>
    </div>
</div>
