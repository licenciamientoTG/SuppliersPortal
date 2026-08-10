@extends('layouts.zircos')

@section('title', 'Log del sistema')
@section('page.title', 'Log del sistema')
@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item">Control y Monitoreo</li>
    <li class="breadcrumb-item active">Log del sistema</li>
@endsection

@section('content')
<div class="card shadow-sm border-0">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="ti ti-bug me-1 text-danger"></i>Log del sistema <span class="badge bg-secondary ms-2 fs-11">{{ $fileSize }}</span></h5>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-copy"><i class="ti ti-copy me-1"></i>Copiar</button>
    </div>
    <div class="card-body p-0">
        @if(empty(trim($lines ?? '')))
            <div class="text-center text-muted py-5"><i class="ti ti-file-off fs-48 d-block mb-2"></i>El archivo de log está vacío.</div>
        @else
            <pre id="log-content" style="background:#1e1e1e;color:#d4d4d4;font-size:12px;line-height:1.5;margin:0;padding:1rem;max-height:75vh;overflow-y:auto;white-space:pre-wrap;word-break:break-all;">{{ $lines }}</pre>
        @endif
    </div>
    <div class="card-footer text-muted small">Vista de solo lectura de las últimas 500 líneas de <code>storage/logs/laravel.log</code>.</div>
</div>
@endsection

@push('scripts')
<script>
    const pre = document.getElementById('log-content');
    if (pre) pre.scrollTop = pre.scrollHeight;
    document.getElementById('btn-copy')?.addEventListener('click', function () {
        navigator.clipboard.writeText(pre?.innerText || '').then(() => { this.innerHTML = '<i class="ti ti-check me-1"></i>Copiado'; });
    });
</script>
@endpush
