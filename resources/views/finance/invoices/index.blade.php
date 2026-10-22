@extends('layouts.zircos')

@section('title', 'Facturas')
@section('page.title', 'Facturas')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Facturas de Proveedores</h5>
        <a href="{{ route('invoices.create') }}" class="btn btn-primary btn-sm">
            <i class="ti ti-upload me-1"></i>Cargar Factura
        </a>
    </div>
    <div class="card-body">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>UUID</th>
                        <th>Proveedor</th>
                        <th>Total</th>
                        <th>Origen</th>
                        <th>Estado</th>
                        <th>Provisión</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                        <tr>
                            <td class="fw-semibold">{{ $invoice->uuid }}</td>
                            <td>{{ $invoice->supplier?->company_name }}</td>
                            <td>${{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency }}</td>
                            <td>{{ $invoice->uploaded_origin === 'supplier' ? 'Proveedor' : 'Finanzas' }}</td>
                            <td><span class="badge bg-{{ $invoice->getStatusBadgeClass() }}">{{ $invoice->getStatusLabel() }}</span></td>
                            <td>{{ $invoice->financialProvision?->reception?->folio ?? 'Pendiente' }}</td>
                            <td class="text-end">
                                <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-outline-primary btn-sm">
                                    <i class="ti ti-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted">Sin facturas registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $invoices->links() }}
    </div>
</div>
@endsection
