@extends('layouts.zircos-supplier')

@section('title', 'Mis Facturas')
@section('page.title', 'Mis Facturas')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Historial de Facturas</h5>
        <a href="{{ route('supplier.invoices.create') }}" class="btn btn-primary btn-sm">
            <i class="ti ti-upload me-1"></i>Cargar Factura
        </a>
    </div>
    <div class="card-body">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>UUID</th>
                        <th>Orden</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th>Provisión</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                        <tr>
                            <td class="fw-semibold">{{ $invoice->uuid }}</td>
                            <td>{{ class_basename($invoice->receivable_type) }} #{{ $invoice->receivable_id }}</td>
                            <td>${{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency }}</td>
                            <td><span class="badge bg-{{ $invoice->getStatusBadgeClass() }}">{{ $invoice->getStatusLabel() }}</span></td>
                            <td>
                                @if($invoice->financialProvision)
                                    {{ $invoice->financialProvision->reception?->folio }}
                                @else
                                    <span class="text-muted">Pendiente</span>
                                @endif
                            </td>
                            <td>{{ $invoice->created_at->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">Sin facturas registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $invoices->links() }}
    </div>
</div>
@endsection
