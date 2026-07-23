@extends('layouts.zircos')
@section('title', 'Editar Contrato ' . $contract->folio)
@section('page.title', 'Editar Contrato ' . $contract->folio)

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('contracts.index') }}">Contratos</a></li>
    <li class="breadcrumb-item"><a href="{{ route('contracts.show', $contract) }}">{{ $contract->folio }}</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@push('styles')
<style>
    select.is-invalid + .select2-container .select2-selection--single {
        border-color: #dc3545 !important;
    }
</style>
@endpush

@section('content')
<div class="container-fluid" x-data="contractForm()">
    <form action="{{ route('contracts.update', $contract) }}" method="POST">
        @csrf
        @method('PUT')

        {{-- Datos generales --}}
        <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0">Datos del contrato</h5></div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label">Empresa <span class="text-danger">*</span></label>
                    <select name="company_id" id="company_id" class="form-select @error('company_id') is-invalid @enderror" required>
                        <option value="">Seleccionar...</option>
                        @foreach($companies as $co)
                            <option value="{{ $co->id }}" {{ old('company_id', $contract->company_id) == $co->id ? 'selected' : '' }}>
                                {{ $co->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('company_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">Proveedor <span class="text-danger">*</span></label>
                    <select name="supplier_id" id="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror" required>
                        <option value="">Seleccionar...</option>
                        @foreach($suppliers as $s)
                            <option value="{{ $s->id }}" {{ old('supplier_id', $contract->supplier_id) == $s->id ? 'selected' : '' }}>
                                {{ $s->company_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label class="form-label">Tipo de contrato</label>
                    <div class="border rounded p-3 bg-light">
                        <strong>{{ $contract->contract_type->label() }}</strong>
                        <div class="form-text mt-1">{{ $contract->contract_type->description() }}</div>
                        <div class="form-text text-muted mt-1"><i class="ti ti-lock me-1"></i>El tipo de contrato no puede modificarse después de creado.</div>
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Fecha inicio <span class="text-danger">*</span></label>
                    <input type="date" name="start_date" class="form-control @error('start_date') is-invalid @enderror"
                        value="{{ old('start_date', $contract->start_date->format('Y-m-d')) }}" x-model="startDate" required>
                    @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label">Fecha fin <span class="text-danger">*</span></label>
                    <input type="date" name="end_date" class="form-control @error('end_date') is-invalid @enderror"
                        value="{{ old('end_date', $contract->end_date->format('Y-m-d')) }}" x-model="endDate" required>
                    <template x-if="endInPast">
                        <div class="alert alert-warning py-1 mt-1 small">
                            La fecha de fin está en el pasado. El contrato quedará vencido al guardar.
                        </div>
                    </template>
                    @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label">Monto contrato</label>
                    <input type="number" name="contract_amount" step="0.01" min="0"
                        class="form-control @error('contract_amount') is-invalid @enderror"
                        value="{{ old('contract_amount', $contract->contract_amount) }}">
                    @error('contract_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        {{-- Productos --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Productos</h5>
                <button type="button" class="btn btn-sm btn-outline-primary" @click="addRow()">
                    <i class="ti ti-plus me-1"></i> Agregar producto
                </button>
            </div>
            <div class="card-body">
                @error('products')<div class="alert alert-danger">{{ $message }}</div>@enderror
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th style="min-width:220px">Producto/Servicio</th>
                                <th>Precio unitario</th>
                                <th>Moneda</th>
                                <th>U/M</th>
                                <th>Notas</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(row, index) in rows" :key="index">
                                <tr>
                                    <td>
                                        <select
                                            :name="`products[${index}][product_service_id]`"
                                            x-model="row.product_service_id"
                                            class="form-select form-select-sm"
                                            required
                                            x-init="$nextTick(() => { let $sel = $($el); $sel.select2({ theme: 'bootstrap-5', width: '100%', placeholder: 'Seleccionar...' }); if (row.product_service_id) $sel.val(row.product_service_id).trigger('change'); })">
                                            <option value="">Seleccionar...</option>
                                            @foreach($productServices as $prod)
                                                <option value="{{ $prod->id }}">{{ $prod->short_name ?? $prod->code }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" :name="`products[${index}][unit_price]`"
                                            step="0.0001" min="0.0001" class="form-control form-control-sm"
                                            x-model="row.unit_price" required>
                                    </td>
                                    <td>
                                        <select :name="`products[${index}][currency_code]`" class="form-select form-select-sm" x-model="row.currency_code">
                                            <option value="MXN">MXN</option>
                                            <option value="USD">USD</option>
                                        </select>
                                    </td>
                                    <td>
                                        <select :name="`products[${index}][unit_of_measure]`"
                                            class="form-select form-select-sm" x-model="row.unit_of_measure" required>
                                            <option value="">U/M</option>
                                            @foreach($unitsOfMeasure as $group => $units)
                                            <optgroup label="{{ $group }}">
                                                @foreach($units as $code => $label)
                                                <option value="{{ $code }}">{{ $label }}</option>
                                                @endforeach
                                            </optgroup>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" :name="`products[${index}][notes]`"
                                            class="form-control form-control-sm" x-model="row.notes">
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                            @click="removeRow(index)" :disabled="rows.length === 1">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Guardar contrato</button>
            <a href="{{ route('contracts.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function () {
    $('#company_id, #supplier_id').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Seleccionar...',
    });
});

@php
$contractRows = $contract->products->map(function ($p) {
    return [
        'product_service_id' => $p->product_service_id,
        'unit_price'         => $p->unit_price,
        'currency_code'      => $p->currency_code,
        'unit_of_measure'    => $p->unit_of_measure,
        'notes'              => $p->notes,
    ];
})->values()->toArray();
@endphp
function contractForm() {
    return {
        rows: @json($contractRows),
        startDate: @json($contract->start_date->format('Y-m-d')),
        endDate: @json($contract->end_date->format('Y-m-d')),
        get endInPast() {
            return this.endDate && this.endDate < new Date().toISOString().slice(0, 10);
        },
        addRow() { this.rows.push({ product_service_id: '', unit_price: '', currency_code: 'MXN', unit_of_measure: '', notes: '' }); },
        removeRow(index) { if (this.rows.length > 1) this.rows.splice(index, 1); },
    };
}
</script>
@endpush
