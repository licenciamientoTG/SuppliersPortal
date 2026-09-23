@extends('layouts.zircos')

@section('title', 'Respaldos de base de datos')
@section('page.title', 'Respaldos de base de datos')
@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Respaldos de BD</li>
@endsection

@section('content')
<div class="card shadow-sm border-0">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="mb-0"><i class="ti ti-database-export me-1 text-primary"></i>Respaldos de base de datos</h5>
            <small class="text-muted">Se conservan las {{ $keep }} copias más recientes; al generar una nueva se elimina la más antigua.</small>
        </div>
        <form method="POST" action="{{ route('db-backups.store') }}" id="backup-form">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm" id="btn-backup">
                <i class="ti ti-database-plus me-1"></i>Generar respaldo
            </button>
        </form>
    </div>
    <div class="card-body p-0">
        @if($backups->isEmpty())
            <div class="text-center text-muted py-5"><i class="ti ti-database-off fs-48 d-block mb-2"></i>Aún no hay respaldos.</div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Archivo</th>
                            <th>Base de datos</th>
                            <th class="text-end">Peso</th>
                            <th>Fecha de creación</th>
                            <th>Generado por</th>
                            <th class="text-end">Duración</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($backups as $backup)
                            <tr>
                                <td><code>{{ $backup->filename }}</code></td>
                                <td>{{ $backup->database_name }}</td>
                                <td class="text-end">{{ $backup->human_size }}</td>
                                <td>{{ $backup->started_at?->format('d/m/Y H:i:s') ?? '—' }}</td>
                                <td>{{ $backup->creator?->name ?? '—' }}</td>
                                <td class="text-end">{{ $backup->duration_seconds !== null ? $backup->duration_seconds.' s' : '—' }}</td>
                                <td>
                                    @switch($backup->status)
                                        @case(\App\Models\DatabaseBackup::STATUS_COMPLETED)
                                            <span class="badge bg-success-subtle text-success">Completado</span>
                                            @break
                                        @case(\App\Models\DatabaseBackup::STATUS_FAILED)
                                            <span class="badge bg-danger-subtle text-danger" title="{{ $backup->error_message }}">Fallido</span>
                                            <div class="small text-danger text-wrap" style="max-width: 320px;">{{ \Illuminate\Support\Str::limit($backup->error_message, 160) }}</div>
                                            @break
                                        @default
                                            <span class="badge bg-warning-subtle text-warning">En curso</span>
                                    @endswitch
                                </td>
                                <td class="text-end">
                                    @if($backup->status === \App\Models\DatabaseBackup::STATUS_COMPLETED)
                                        <a href="{{ route('db-backups.download', $backup) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="ti ti-download me-1"></i>Descargar
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
    <div class="card-footer text-muted small">
        Respaldo nativo de SQL Server (<code>COPY_ONLY</code>): no altera la cadena de respaldos programados del servidor.
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('backup-form')?.addEventListener('submit', function (event) {
        if (!confirm('¿Generar un nuevo respaldo? Si ya hay {{ $keep }}, se eliminará el más antiguo.')) {
            event.preventDefault();
            return;
        }
        const button = document.getElementById('btn-backup');
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generando…';
    });
</script>
@endpush
