@extends('layouts.zircos')

@section('title', $title)
@section('page.title', $title)
@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('receiving-locations.index') }}">Ubicaciones de Recepción</a></li>
    <li class="breadcrumb-item active">{{ $location->name }}</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="ti ti-map-pin me-1"></i> {{ $location->name }}</h5>
        <div class="d-flex gap-2">
            @can('update', $location)
                <a href="{{ route('receiving-locations.edit', $location) }}" class="btn btn-sm btn-outline-primary">
                    <i class="ti ti-edit me-1"></i> Editar
                </a>
            @endcan
            <a href="{{ route('receiving-locations.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="ti ti-arrow-left me-1"></i> Volver
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label text-muted">Código</label>
                <p class="fw-semibold">{{ $location->code }}</p>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted">Empresa</label>
                <p class="fw-semibold">{{ $location->company ? $location->company->code.' - '.$location->company->name : '-' }}</p>
            </div>
            <div class="col-md-6">
                <label class="form-label text-muted">Nombre</label>
                <p class="fw-semibold">{{ $location->name }}</p>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted">Tipo</label>
                <p>{!! $location->type_badge ?? $location->type_name !!}</p>
            </div>
            <div class="col-md-9">
                <label class="form-label text-muted">Dirección</label>
                <p>{{ $location->address ?: '-' }}</p>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted">Código Postal</label>
                <p>{{ $location->postal_code ?: '-' }}</p>
            </div>
            <div class="col-md-4">
                <label class="form-label text-muted">Ciudad</label>
                <p>{{ $location->city ?: '-' }}</p>
            </div>
            <div class="col-md-4">
                <label class="form-label text-muted">Estado</label>
                <p>{{ $location->state ?: '-' }}</p>
            </div>
            <div class="col-md-4">
                <label class="form-label text-muted">País</label>
                <p>{{ $location->country ?: '-' }}</p>
            </div>
            <div class="col-md-4">
                <label class="form-label text-muted">Responsable</label>
                <p>{{ $location->manager_name ?: '-' }}</p>
            </div>
            <div class="col-md-4">
                <label class="form-label text-muted">Teléfono</label>
                <p>{{ $location->phone ?: '-' }}</p>
            </div>
            <div class="col-md-4">
                <label class="form-label text-muted">Correo Electrónico</label>
                <p>{{ $location->email ?: '-' }}</p>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted">Estado</label>
                <p>{!! $location->status_badge !!}</p>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted">Portal</label>
                <p>{!! $location->portal_badge !!}</p>
            </div>
            @if($location->notes)
            <div class="col-12">
                <label class="form-label text-muted">Notas</label>
                <p>{{ $location->notes }}</p>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
