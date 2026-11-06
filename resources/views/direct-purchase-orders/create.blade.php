@extends('layouts.zircos')

@section('title', 'Nueva Orden de Compra Directa')

@section('page.title', 'Nueva OCD')

@section('hide_global_errors', '1')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('purchase-orders.index') }}">Órdenes</a></li>
    <li class="breadcrumb-item active">Nueva Directa</li>
@endsection

@section('content')

{{-- Mensajes de error y éxito --}}
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="ti ti-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="ti ti-alert-circle me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
@endif

{{-- Los errores de validación (incluido el de presupuesto) se muestran una sola vez, dentro del formulario. --}}
@include('direct-purchase-orders.partials._form')

@endsection
