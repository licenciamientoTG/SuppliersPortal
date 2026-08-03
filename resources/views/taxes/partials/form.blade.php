@php
    $editing = isset($tax) && $tax->exists;
@endphp
@csrf

<div id="formErrors" class="d-none"></div>

<div class="mb-3">
    <label for="name" class="form-label">Nombre</label>
    <input type="text"
           id="name"
           name="name"
           class="form-control"
           required
           maxlength="100"
           value="{{ old('name', $tax->name ?? '') }}">
</div>

<div class="mb-3">
    <label for="rate_percent" class="form-label">Tasa (%)</label>
    <input type="number"
           id="rate_percent"
           name="rate_percent"
           class="form-control"
           step="0.01"
           min="0"
           max="100"
           required
           value="{{ old('rate_percent', isset($tax) ? number_format($tax->rate_percent, 2, '.', '') : '') }}">
</div>

<div class="form-check form-switch mb-3">
    <input class="form-check-input"
           type="checkbox"
           role="switch"
           id="is_active"
           name="is_active"
           value="1"
           {{ old('is_active', $tax->is_active ?? true) ? 'checked' : '' }}>
    <label class="form-check-label" for="is_active">Activo</label>
</div>

<div class="d-flex gap-2">
    <button class="btn btn-primary">
        {{ $editing ? 'Actualizar' : 'Crear' }}
    </button>
    <a href="{{ route('taxes.index') }}" class="btn btn-outline-secondary">Cancelar</a>
</div>
