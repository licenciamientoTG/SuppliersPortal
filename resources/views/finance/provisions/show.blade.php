@extends('layouts.zircos')

@section('title', 'Provisión')
@section('page.title', 'Provisión ' . ($provision->reception?->folio ?? $provision->id))

@section('content')
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Detalle de Provisión</h5>
            </div>
            <div class="card-body">
                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                <dl class="row">
                    <dt class="col-sm-3">Recepción</dt><dd class="col-sm-9">{{ $provision->reception?->folio }}</dd>
                    <dt class="col-sm-3">Proveedor</dt><dd class="col-sm-9">{{ $provision->supplier?->company_name }}</dd>
                    <dt class="col-sm-3">Monto provisionado</dt><dd class="col-sm-9">${{ number_format((float) $provision->provision_amount, 2) }} {{ $provision->currency }}</dd>
                    <dt class="col-sm-3">Monto factura</dt><dd class="col-sm-9">{{ $provision->invoice_amount !== null ? '$' . number_format((float) $provision->invoice_amount, 2) : '-' }}</dd>
                    <dt class="col-sm-3">Diferencia</dt><dd class="col-sm-9">${{ number_format((float) $provision->difference_amount, 2) }}</dd>
                    <dt class="col-sm-3">Estado</dt><dd class="col-sm-9"><span class="badge bg-{{ $provision->getStatusBadgeClass() }}">{{ $provision->getStatusLabel() }}</span></dd>
                </dl>

                <h6>Partidas recibidas</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>Partida</th><th>Cantidad</th><th>Conformidad</th></tr></thead>
                        <tbody>
                            @foreach($provision->reception->items as $item)
                                <tr>
                                    <td>{{ $item->receivableItem?->description ?? 'Partida' }}</td>
                                    <td>{{ number_format((float) $item->quantity_received, 3) }}</td>
                                    <td>{{ $item->conformity }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Factura y Ajustes</h5>
            </div>
            <div class="card-body">
                @if($provision->invoice)
                    <p><strong>UUID:</strong> {{ $provision->invoice->uuid }}</p>
                    <p><strong>Total:</strong> ${{ number_format((float) $provision->invoice->total, 2) }}</p>
                    <a href="{{ route('invoices.show', $provision->invoice) }}" class="btn btn-outline-primary btn-sm">Ver Factura</a>
                @else
                    <p class="text-muted">Pendiente de factura.</p>
                    @if(($compatibleInvoices ?? collect())->isNotEmpty())
                        <form method="POST" action="{{ route('financial-provisions.link-invoice', $provision) }}">
                            @csrf
                            <label class="form-label">Facturas compatibles</label>
                            <select name="supplier_invoice_id" class="form-select form-select-sm" required>
                                @foreach($compatibleInvoices as $invoice)
                                    <option value="{{ $invoice->id }}">{{ $invoice->uuid }} - ${{ number_format((float) $invoice->total, 2) }}</option>
                                @endforeach
                            </select>
                            <button class="btn btn-primary btn-sm mt-2">Vincular y Conciliar</button>
                        </form>
                    @endif
                @endif

                @if($provision->adjustments->isNotEmpty())
                    <hr>
                    <h6>Ajustes autorizados</h6>
                    @foreach($provision->adjustments as $adjustment)
                        <div class="border rounded p-2 mb-2">
                            <div class="fw-semibold">${{ number_format((float) $adjustment->amount, 2) }} - {{ $adjustment->reason }}</div>
                            <div class="small text-muted">{{ $adjustment->authorizer?->name }} | {{ $adjustment->authorized_at?->format('d/m/Y H:i') }}</div>
                            @if($adjustment->notes)
                                <div class="small">{{ $adjustment->notes }}</div>
                            @endif
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
