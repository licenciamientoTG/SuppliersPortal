{{-- resources/views/contracts/import.blade.php --}}
@extends('layouts.zircos')
@section('title', 'Importar Contratos')
@section('page.title', 'Carga masiva de contratos')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('contracts.index') }}">Contratos</a></li>
    <li class="breadcrumb-item active">Importar</li>
@endsection

@section('content')
<div class="container-fluid">
    <div class="card" style="max-width:600px">
        <div class="card-body">
            <p>
                <a href="{{ route('contracts.template') }}" class="btn btn-outline-secondary btn-sm"
                   title="Descarga la plantilla CSV con el formato requerido">
                    <i class="ti ti-download me-1"></i> Descargar plantilla CSV
                </a>
            </p>

            <form action="{{ route('contracts.import.preview') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="import-file">Archivo CSV o Excel (máx. 500 filas, 5 MB)</label>
                    <input type="file" name="file" id="import-file" class="form-control @error('file') is-invalid @enderror"
                        accept=".csv,.xlsx,.xls" required>
                    @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="ti ti-eye me-1"></i> Previsualizar
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
