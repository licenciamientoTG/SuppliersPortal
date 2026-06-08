@extends('layouts.zircos-supplier')

@section('title', 'Entregas — Portal de Proveedores')
@section('page.title', 'Mis Entregas')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('supplier.dashboard') }}">Portal</a></li>
    <li class="breadcrumb-item active">Entregas</li>
@endsection

@section('content')
<div class="row g-3">

    {{-- Alertas de sesión --}}
    @if(session('success'))
        <div class="col-12">
            <div class="alert alert-success alert-dismissible fade show">
                <i class="ti ti-circle-check me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="col-12">    
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="ti ti-alert-circle me-2"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    @endif

    {{-- Listado de órdenes de compra --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="ti ti-truck-delivery me-1"></i>
                    Órdenes de Compra Pendientes de Entrega
                    <span class="badge bg-secondary ms-2">{{ $orders->count() }}</span>
                </h5>
            </div>
            <div class="card-body p-0">
                @if($orders->isEmpty())
                    <div class="text-center py-5 text-muted">
                        <i class="ti ti-package-off fs-1 d-block mb-2"></i>
                        No tienes órdenes de compra pendientes de entrega.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Folio</th>
                                    <th>Punto de Entrega</th>
                                    <th>Total</th>
                                    <th>Estatus</th>
                                    <th>Fecha Emisión</th>
                                    <th class="text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($orders as $order)
                                <tr>
                                    <td><strong>{{ $order->folio }}</strong></td>
                                    <td>
                                        @if($order->receivingLocation)
                                            <span class="badge bg-soft-info text-info">{{ $order->receivingLocation->code }}</span>
                                            {{ $order->receivingLocation->name }}
                                        @else
                                            <span class="text-muted">Sin asignar</span>
                                        @endif
                                    </td>
                                    <td>${{ number_format($order->total, 2) }} {{ $order->currency }}</td>
                                    <td>
                                        <span class="badge bg-{{ $order->getStatusBadgeClass() }}">
                                            {{ $order->getStatusLabel() }}
                                        </span>
                                    </td>
                                    <td>{{ $order->issued_at?->format('d/m/Y') ?? '—' }}</td>
                                    <td class="text-center">
                                        @if($order->canReceiveSupplierDelivery())
                                            <a href="{{ route('supplier.deliveries.create', ['type' => $order->order_type, 'id' => $order->id]) }}"
                                               class="btn btn-sm btn-primary">
                                                <i class="ti ti-truck-delivery me-1"></i> Registrar Entrega
                                            </a>
                                        @elseif($order->isDeliveredPendingReception())
                                            <span class="badge bg-warning text-dark">
                                                <i class="ti ti-clock me-1"></i>
                                                Esperando captura de estación
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
