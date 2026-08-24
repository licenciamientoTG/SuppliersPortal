@extends('layouts.zircos')

@section('title', $taxGroup->exists ? 'Editar grupo de impuestos' : 'Nuevo grupo de impuestos')
@section('page.title', $taxGroup->exists ? 'Editar grupo de impuestos' : 'Nuevo grupo de impuestos')

@section('content')
<div class="card border-0 shadow-sm">
    <form method="POST" action="{{ $taxGroup->exists ? route('tax-groups.update', $taxGroup) : route('tax-groups.store') }}">
        @csrf
        @if ($taxGroup->exists) @method('PUT') @endif
        <div class="card-body border-bottom d-flex justify-content-between align-items-center"><h5 class="mb-0">{{ $taxGroup->exists ? 'Editar grupo' : 'Alta de grupo' }}</h5><a href="{{ $taxGroup->exists ? route('tax-groups.show', $taxGroup) : route('tax-groups.index') }}" class="btn btn-outline-secondary">Cancelar</a></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8"><label class="form-label">Nombre del grupo</label><input name="name" required value="{{ old('name', $taxGroup->name) }}" class="form-control @error('name') is-invalid @enderror">@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-4"><label class="form-label">Tipo</label><input type="number" min="0" name="one_goal_type_id" required value="{{ old('one_goal_type_id', $taxGroup->one_goal_type_id ?? 0) }}" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Objeto de impuesto SAT</label><input maxlength="2" name="sat_tax_object" value="{{ old('sat_tax_object', $taxGroup->sat_tax_object) }}" class="form-control"></div>
                <div class="col-md-8 d-flex flex-wrap gap-4 align-items-end pb-2">
                    @foreach (['is_active' => 'Grupo activo', 'is_payment_tax' => 'Impuesto de pago', 'is_border_zone' => 'Zona fronteriza', 'is_vat_tax' => 'Impuesto IVA', 'is_south_border_zone' => 'Zona sur fronteriza'] as $field => $label)
                        <div class="form-check"><input class="form-check-input" type="checkbox" value="1" name="{{ $field }}" id="{{ $field }}" @checked(old($field, $taxGroup->exists ? $taxGroup->$field : $field === 'is_active'))><label class="form-check-label" for="{{ $field }}">{{ $label }}</label></div>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="card-footer text-end"><button class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i> Guardar</button></div>
    </form>
    @if ($taxGroup->exists && $taxGroup->is_active)
        <form method="POST" action="{{ route('tax-groups.deactivate', $taxGroup) }}" class="card-footer border-top-0 pt-0 text-end">@csrf<button class="btn btn-outline-danger" onclick="return confirm('¿Desactivar este grupo? Se conservará para historial.')">Desactivar grupo</button></form>
    @endif
</div>
@endsection
