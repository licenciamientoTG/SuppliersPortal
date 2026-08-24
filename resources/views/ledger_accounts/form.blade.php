@extends('layouts.zircos')

@section('title', $ledgerAccount->exists ? 'Editar cuenta contable' : 'Nueva cuenta contable')
@section('page.title', $ledgerAccount->exists ? 'Editar cuenta contable' : 'Nueva cuenta contable')

@section('content')
<div class="card border-0 shadow-sm">
    <form method="POST" action="{{ $ledgerAccount->exists ? route('ledger-accounts.update', $ledgerAccount) : route('ledger-accounts.store') }}">
        @csrf
        @if ($ledgerAccount->exists) @method('PUT') @endif
        <div class="card-body border-bottom d-flex justify-content-between align-items-center">
            <h5 class="mb-0">{{ $ledgerAccount->exists ? 'Editar cuenta' : 'Alta de cuenta' }}</h5>
            <a href="{{ route('ledger-accounts.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Número de cuenta</label><input name="code" value="{{ old('code', $ledgerAccount->code) }}" class="form-control @error('code') is-invalid @enderror">@error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-8"><label class="form-label">Nombre</label><input name="name" required value="{{ old('name', $ledgerAccount->name) }}" class="form-control @error('name') is-invalid @enderror">@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label">Nombre alterno</label><input name="alternate_name" value="{{ old('alternate_name', $ledgerAccount->alternate_name) }}" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Cuenta padre</label><select name="parent_id" class="form-select"><option value="">Sin cuenta padre</option>@foreach($parents as $parent)<option value="{{ $parent->id }}" @selected(old('parent_id', $ledgerAccount->parent_id) == $parent->id)>{{ $parent->display_label }}</option>@endforeach</select></div>
                <div class="col-md-4"><label class="form-label">Naturaleza</label><input type="number" min="0" max="9" name="nature" required value="{{ old('nature', $ledgerAccount->nature ?? 0) }}" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Nivel</label><input type="number" min="0" max="9" name="account_level" required value="{{ old('account_level', $ledgerAccount->account_level ?? 1) }}" class="form-control"></div>
                <div class="col-md-4 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" value="1" name="is_active" id="is-active" @checked(old('is_active', $ledgerAccount->exists ? $ledgerAccount->is_active : true))><label class="form-check-label" for="is-active">Cuenta activa</label></div></div>
            </div>
        </div>
        <div class="card-footer text-end"><button class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i> Guardar</button></div>
    </form>
    @if ($ledgerAccount->exists && $ledgerAccount->is_active)
        <form method="POST" action="{{ route('ledger-accounts.deactivate', $ledgerAccount) }}" class="card-footer border-top-0 pt-0 text-end">@csrf<button class="btn btn-outline-danger" onclick="return confirm('¿Desactivar esta cuenta? Se conservará para historial.')">Desactivar cuenta</button></form>
    @endif
</div>
@endsection
