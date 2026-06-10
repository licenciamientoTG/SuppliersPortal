@extends('layouts.zircos')
@section('title', 'Contrato ' . $contract->folio)
@section('page.title', 'Contrato ' . $contract->folio)

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('contracts.index') }}">Contratos</a></li>
    <li class="breadcrumb-item active">{{ $contract->folio }}</li>
@endsection

@section('content')
<div class="container-fluid">

    {{-- Encabezado con estado y acciones --}}
    <div class="card mb-3">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="mb-1">{{ $contract->folio }}</h4>
                <span class="badge bg-{{ $contract->effective_status_badge }} fs-6">
                    @php
                        $icon = match($contract->effective_status) {
                            'active'  => 'file-check',
                            'expired' => 'clock-x',
                            default   => 'file-x',
                        };
                    @endphp
                    <i class="ti ti-{{ $icon }} me-1"></i>
                    {{ $contract->effective_status_label }}
                </span>
            </div>
            <div class="d-flex gap-2">
                @if($contract->effective_status === 'active')
                    <a href="{{ route('contracts.edit', $contract) }}" class="btn btn-outline-secondary">
                        <i class="ti ti-edit me-1"></i> Editar
                    </a>
                    <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal">
                        <i class="ti ti-ban me-1"></i> Cancelar contrato
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Datos generales --}}
    <div class="card mb-3">
        <div class="card-body row g-3">
            <div class="col-md-4"><strong>Proveedor:</strong><br>{{ $contract->supplier->company_name }}</div>
            <div class="col-md-4"><strong>Empresa:</strong><br>{{ $contract->company->name }}</div>
            <div class="col-md-4"><strong>Monto contrato:</strong><br>${{ number_format($contract->contract_amount, 2) }}</div>
            <div class="col-md-4"><strong>Vigencia:</strong><br>{{ $contract->start_date->format('d/m/Y') }} — {{ $contract->end_date->format('d/m/Y') }}</div>
            <div class="col-md-4"><strong>Creado por:</strong><br>{{ $contract->creator->name }}</div>
            @if($contract->effective_status === 'cancelled')
                <div class="col-md-4">
                    <strong>Cancelado por:</strong><br>{{ $contract->cancelledByUser?->name ?? '—' }}
                    @if($contract->cancelled_at)<br><small class="text-muted">{{ $contract->cancelled_at->format('d/m/Y H:i') }}</small>@endif
                </div>
                <div class="col-12">
                    <strong>Motivo de cancelación:</strong><br>
                    <span class="text-muted">{{ $contract->cancellation_reason }}</span>
                </div>
            @endif
        </div>
    </div>

    {{-- Tabs --}}
    <ul class="nav nav-tabs mb-3" id="contractTabs">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-products">Productos</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-history">Historial</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-purchases">Compras realizadas</a></li>
    </ul>

    <div class="tab-content">
        {{-- Tab: Productos --}}
        <div class="tab-pane fade show active" id="tab-products">
            <div class="card">
                <div class="card-body">
                    <table class="table table-sm">
                        <thead>
                            <tr><th>Producto</th><th>Precio unitario</th><th>Moneda</th><th>U/M</th><th>Notas</th></tr>
                        </thead>
                        <tbody>
                            @foreach($contract->products as $cp)
                            <tr>
                                <td>{{ $cp->product->short_name ?? $cp->product->code ?? $cp->product_service_id }}</td>
                                <td>${{ number_format($cp->unit_price, 4) }}</td>
                                <td>{{ $cp->currency_code }}</td>
                                <td>{{ $cp->unit_of_measure }}</td>
                                <td>{{ $cp->notes ?? '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Tab: Historial --}}
        <div class="tab-pane fade" id="tab-history">
            <div class="card">
                <div class="card-body">
                    @forelse($history as $log)
                    <div class="d-flex mb-2">
                        <div class="text-muted small me-3" style="min-width:140px">{{ $log->created_at->format('d/m/Y H:i') }}</div>
                        <div>
                            <strong>{{ $log->causer->name ?? 'Sistema' }}</strong>
                            <span class="text-muted ms-1">{{ $log->description }}</span>
                        </div>
                    </div>
                    @empty
                    <p class="text-muted">Sin historial.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Tab: Compras realizadas --}}
        <div class="tab-pane fade" id="tab-purchases">
            <div class="card">
                <div class="card-body">
                    <table class="table table-sm">
                        <thead>
                            <tr><th>Requisición</th><th>Producto</th><th>Cantidad</th><th>Precio snapshot</th><th>Fecha</th></tr>
                        </thead>
                        <tbody>
                            @forelse($purchases as $item)
                            <tr>
                                <td>
                                    <a href="{{ route('requisitions.show', $item->requisition_id) }}">
                                        {{ $item->requisition->folio ?? $item->requisition_id }}
                                    </a>
                                </td>
                                <td>{{ $item->description }}</td>
                                <td>{{ $item->quantity }}</td>
                                <td>${{ number_format($item->unit_price, 4) }} {{ $item->currency_code }}</td>
                                <td>{{ $item->created_at->format('d/m/Y') }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-muted">Sin compras registradas.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    {{ $purchases->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal cancelar --}}
@if($contract->effective_status === 'active')
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('contracts.cancel', $contract) }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cancelar contrato {{ $contract->folio }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Motivo de cancelación <span class="text-danger">*</span></label>
                    <textarea name="cancellation_reason" class="form-control" rows="3" minlength="10" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-danger">Confirmar cancelación</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
