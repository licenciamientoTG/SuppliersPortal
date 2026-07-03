@extends('layouts.zircos')

@section('title', 'Cargar Factura')
@section('page.title', 'Cargar Factura')

@section('content')
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Nueva Factura</h5>
    </div>
    <div class="card-body">
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <form method="POST" action="{{ route('invoices.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Proveedor <span class="text-danger">*</span></label>
                    <select name="supplier_id" class="form-select" required>
                        <option value="">Seleccionar...</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->company_name }} ({{ $supplier->rfc }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Orden de compra <span class="text-danger">*</span></label>
                    <select name="order_key" id="order_key" class="form-select" required>
                        <option value="">Seleccionar...</option>
                        @foreach($orders as $order)
                            <option value="{{ $order['type'] }}:{{ $order['id'] }}" @selected(old('order_type') === $order['type'] && (string) old('order_id') === (string) $order['id'])>
                                {{ $order['label'] }}
                            </option>
                        @endforeach
                    </select>
                    <input type="hidden" name="order_type" id="order_type" value="{{ old('order_type') }}">
                    <input type="hidden" name="order_id" id="order_id" value="{{ old('order_id') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">XML CFDI <span class="text-danger">*</span></label>
                    <input type="file" name="xml_file" class="form-control" accept=".xml" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">PDF <span class="text-danger">*</span></label>
                    <input type="file" name="pdf_file" class="form-control" accept=".pdf" required>
                </div>
            </div>
            <div class="mt-3 d-flex justify-content-end gap-2">
                <a href="{{ route('invoices.index') }}" class="btn btn-secondary">Cancelar</a>
                <button class="btn btn-primary">Cargar Factura</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function syncInvoiceOrderFields() {
        const select = document.getElementById('order_key');
        const typeInput = document.getElementById('order_type');
        const idInput = document.getElementById('order_id');
        const [type = '', id = ''] = (select?.value || '').split(':');

        typeInput.value = type;
        idInput.value = id;
    }

    document.addEventListener('DOMContentLoaded', function() {
        const select = document.getElementById('order_key');
        const form = select?.closest('form');

        select?.addEventListener('change', syncInvoiceOrderFields);
        form?.addEventListener('submit', syncInvoiceOrderFields);
        syncInvoiceOrderFields();
    });
</script>
@endpush
