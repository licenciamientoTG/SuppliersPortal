@extends('layouts.zircos')

@section('title', 'Editar Rol Autorizador')
@section('page.title', 'Editar Rol Autorizador')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('authorizer-roles.index') }}">Roles autorizadores</a></li>
    <li class="breadcrumb-item active">{{ $authorizerRole->name }}</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-xl-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0">Editar facultad</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('authorizer-roles.update', $authorizerRole) }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $authorizerRole->name) }}" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Límite con IVA</label>
                        <input type="number" step="0.01" min="0" name="approval_limit" class="form-control"
                            value="{{ old('approval_limit', $authorizerRole->approval_limit) }}">
                        <small class="text-muted">Solo Dirección General puede dejar este campo vacío para operar sin límite.</small>
                    </div>

                    <div class="form-check form-switch mb-4">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                            {{ old('is_active', $authorizerRole->is_active) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_active">Rol activo</label>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('authorizer-roles.index') }}" class="btn btn-light">Cancelar</a>
                        <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
