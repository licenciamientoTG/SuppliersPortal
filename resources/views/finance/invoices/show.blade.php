@extends('layouts.zircos')

@section('title', 'Factura')
@section('page.title', 'Factura ' . $invoice->uuid)

@section('content')
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Detalle de Factura</h5>
            </div>
            <div class="card-body">
                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                <dl class="row mb-0">
                    <dt class="col-sm-3">UUID</dt><dd class="col-sm-9">{{ $invoice->uuid }}</dd>
                    <dt class="col-sm-3">Proveedor</dt><dd class="col-sm-9">{{ $invoice->supplier?->company_name }} ({{ $invoice->issuer_rfc }})</dd>
                    <dt class="col-sm-3">Receptor RFC</dt><dd class="col-sm-9">{{ $invoice->receiver_rfc ?: '-' }}</dd>
                    <dt class="col-sm-3">Subtotal</dt><dd class="col-sm-9">${{ number_format((float) $invoice->subtotal, 2) }}</dd>
                    <dt class="col-sm-3">IVA</dt><dd class="col-sm-9">${{ number_format((float) $invoice->iva_amount, 2) }}</dd>
                    <dt class="col-sm-3">Total</dt><dd class="col-sm-9">${{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency }}</dd>
                    <dt class="col-sm-3">Estado</dt><dd class="col-sm-9"><span class="badge bg-{{ $invoice->getStatusBadgeClass() }}">{{ $invoice->getStatusLabel() }}</span></dd>
                    @if($invoice->status === \App\Models\SupplierInvoice::STATUS_REJECTED)
                        <dt class="col-sm-3">Motivo de rechazo</dt><dd class="col-sm-9">{{ $invoice->rejection_reason }}</dd>
                    @endif
                    <dt class="col-sm-3">Archivos</dt>
                    <dd class="col-sm-9">
                        <a href="{{ asset('storage/' . $invoice->xml_path) }}" target="_blank" class="btn btn-outline-secondary btn-sm">XML</a>
                        <a href="{{ asset('storage/' . $invoice->pdf_path) }}" target="_blank" class="btn btn-outline-danger btn-sm">PDF</a>
                    </dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Control</h5>
            </div>
            <div class="card-body">
                @if($invoice->financialProvision)
                    <p>Vinculada a provisión de recepción <strong>{{ $invoice->financialProvision->reception?->folio }}</strong>.</p>
                    <a href="{{ route('financial-provisions.show', $invoice->financialProvision) }}" class="btn btn-primary btn-sm">Ver Provisión</a>
                @else
                    <p class="text-muted">La factura aún no está vinculada a una provisión.</p>
                @endif

                @if($invoice->status === \App\Models\SupplierInvoice::STATUS_UPLOADED)
                    <hr>
                    <form method="POST" action="{{ route('invoices.reject', $invoice) }}">
                        @csrf
                        <label class="form-label">Motivo de rechazo</label>
                        <textarea name="rejection_reason" class="form-control" rows="3" required></textarea>
                        <button class="btn btn-danger btn-sm mt-2">Rechazar Factura</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
