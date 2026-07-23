{{-- resources/views/contracts/import-preview.blade.php --}}
@extends('layouts.zircos')
@section('title', 'Previsualizar importación')
@section('page.title', 'Previsualizar importación')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('contracts.index') }}">Contratos</a></li>
    <li class="breadcrumb-item"><a href="{{ route('contracts.import') }}">Importar</a></li>
    <li class="breadcrumb-item active">Previsualizar</li>
@endsection

@section('content')
<div class="container-fluid">

    @if(isset($result['error']))
        <div class="alert alert-danger">{{ $result['error'] }}</div>
    @else

    <div class="alert alert-info">
        Se crearán <strong>{{ collect($result['valid'])->pluck('contract_key')->unique()->count() }}</strong> contratos.
        <strong>{{ count($result['errors']) }}</strong> filas con errores serán omitidas.
    </div>

    {{-- Filas válidas --}}
    @if(count($result['valid']) > 0)
    <div class="card mb-3">
        <div class="card-header text-success"><i class="ti ti-check me-1"></i> Filas válidas ({{ count($result['valid']) }})</div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead><tr><th>Línea</th><th>Empresa</th><th>Proveedor RFC</th><th>Vigencia</th><th>Producto</th><th>Precio</th><th>U/M</th></tr></thead>
                <tbody>
                    @foreach($result['valid'] as $row)
                    <tr>
                        <td>{{ $row['line'] }}</td>
                        <td>{{ $row['empresa_code'] }}</td>
                        <td>{{ $row['supplier_rfc'] }}</td>
                        <td>{{ $row['start_date'] }} / {{ $row['end_date'] }}</td>
                        <td>{{ $row['product_code'] }}</td>
                        <td>{{ $row['unit_price'] }} {{ $row['currency_code'] }}</td>
                        <td>{{ $row['unit_of_measure'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Filas con error --}}
    @if(count($result['errors']) > 0)
    <div class="card mb-3">
        <div class="card-header text-danger"><i class="ti ti-x me-1"></i> Filas con errores ({{ count($result['errors']) }})</div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead><tr><th>Línea</th><th>Empresa</th><th>Proveedor RFC</th><th>Producto</th><th>Errores</th></tr></thead>
                <tbody>
                    @foreach($result['errors'] as $row)
                    <tr class="table-danger">
                        <td>{{ $row['line'] }}</td>
                        <td>{{ $row['empresa_code'] }}</td>
                        <td>{{ $row['supplier_rfc'] }}</td>
                        <td>{{ $row['product_code'] }}</td>
                        <td>{{ implode('; ', $row['errors']) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @if(count($result['valid']) > 0)
    <form action="{{ route('contracts.import.confirm') }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-success">
            <i class="ti ti-check me-1"></i> Confirmar importación
        </button>
        <a href="{{ route('contracts.import') }}" class="btn btn-outline-secondary ms-2">Cancelar</a>
    </form>
    @endif

    @endif
</div>
@endsection
