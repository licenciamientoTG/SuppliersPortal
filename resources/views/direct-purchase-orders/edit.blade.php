@extends('layouts.zircos')

@section('title', 'Editar Orden de Compra Directa')

@section('page.title', 'Editar OCD')

@section('hide_global_errors', '1')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('purchase-orders.index') }}">Órdenes</a></li>
    <li class="breadcrumb-item"><a href="{{ route('direct-purchase-orders.show', $directPurchaseOrder->id) }}">{{ $directPurchaseOrder->folio ?? 'BORRADOR' }}</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')

{{-- Alerta de estado --}}
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    <i class="ti ti-alert-triangle me-1"></i>
    <strong>Editando OCD:</strong> {{ $directPurchaseOrder->folio ?? 'BORRADOR' }}
    — Estado: <strong>{{ $directPurchaseOrder->getStatusLabel() }}</strong>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>

{{-- Los errores de validación se muestran una sola vez, dentro del formulario. --}}
@include('direct-purchase-orders.partials._form')

@endsection
