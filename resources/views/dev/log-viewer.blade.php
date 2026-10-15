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
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-copy"><i class="ti ti-copy me-1"></i>Copiar</button>
            <form method="POST" action="{{ route('dev.log.clear') }}" id="form-clear-log">
                @csrf
                <button type="button" class="btn btn-sm btn-outline-danger" id="btn-clear"><i class="ti ti-trash me-1"></i>Limpiar log</button>
            </form>
        </div>
    </div>
    <div class="card-body p-0">
        @if(empty(trim($lines ?? '')))
            <div class="text-center text-muted py-5"><i class="ti ti-file-off fs-48 d-block mb-2"></i>El archivo de log está vacío.</div>
        @else
            <pre id="log-content" style="background:#1e1e1e;color:#d4d4d4;font-size:12px;line-height:1.5;margin:0;padding:1rem;max-height:75vh;overflow-y:auto;white-space:pre-wrap;word-break:break-all;">{{ $lines }}</pre>
        @endif
    </div>
    <div class="card-footer text-muted small">Muestra las últimas 500 líneas de <code>storage/logs/laravel.log</code>.</div>
</div>
@endsection

@push('scripts')
<script>
    const pre = document.getElementById('log-content');
    if (pre) pre.scrollTop = pre.scrollHeight;
    document.getElementById('btn-clear')?.addEventListener('click', function () {
        Swal.fire({
            icon: 'warning',
            title: '¿Limpiar el log?',
            text: 'Se borrará todo el contenido de laravel.log. Esta acción no se puede deshacer.',
            showCancelButton: true,
            confirmButtonText: 'Sí, limpiar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
        }).then(r => { if (r.isConfirmed) document.getElementById('form-clear-log').submit(); });
    });
    document.getElementById('btn-copy')?.addEventListener('click', function () {
        navigator.clipboard.writeText(pre?.innerText || '').then(() => { this.innerHTML = '<i class="ti ti-check me-1"></i>Copiado'; });
    });
</script>
@endpush
