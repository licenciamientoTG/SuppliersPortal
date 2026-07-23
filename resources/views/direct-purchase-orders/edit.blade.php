@extends('layouts.zircos')

@section('title', 'Editar Orden de Compra Directa')

@section('page.title', 'Editar OCD')

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

@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="ti ti-alert-circle me-2"></i>
        <strong>Errores al guardar:</strong>
        <ul class="mb-0 mt-1">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
@endif

@include('direct-purchase-orders.partials._form')

@endsection
