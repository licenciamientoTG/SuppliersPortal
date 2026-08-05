@extends('layouts.zircos')

@section('title', 'Monitoreo de sesiones')
@section('page.title', 'Monitoreo de sesiones')
@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item">Configuración</li>
    <li class="breadcrumb-item active">Sesiones de usuarios</li>
@endsection

@push('styles')
<style>
    .session-summary { border: 1px solid #e2e9f0; border-radius: .8rem; background: #fff; box-shadow: 0 3px 12px rgba(20, 45, 75, .05); }
    .session-summary .label { color: #6c7a89; font-size: .76rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
    .session-summary .value { color: #20354b; font-size: 1.65rem; font-weight: 700; }
    .session-table-card { border: 1px solid #e2e9f0; border-radius: .8rem; overflow: hidden; }
    .status-dot { width: .6rem; height: .6rem; display: inline-block; border-radius: 50%; margin-right: .45rem; }
    .status-active { background: #4bd396; box-shadow: 0 0 0 4px rgba(75, 211, 150, .14); }
    .status-closed { background: #adb5bd; }
    .session-row { transition: background-color .18s ease, transform .18s ease; }
    .session-row:hover { background: #f7fbff; transform: translateY(-1px); }
    @media (prefers-reduced-motion: reduce) { .session-row { transition: none; } .session-row:hover { transform: none; } }
</style>
@endpush

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h4 class="mb-1">Actividad de acceso</h4>
        <p class="text-muted mb-0">Consulta quién ingresó recientemente, sus sesiones activas y los cierres registrados.</p>
    </div>
    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">Actualizado al cargar la página</span>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="session-summary p-3"><div class="label">Usuarios con acceso</div><div class="value">{{ $users->total() }}</div><small class="text-muted">Con al menos un inicio de sesión</small></div></div>
    <div class="col-md-4"><div class="session-summary p-3"><div class="label">Sesiones activas</div><div class="value text-success">{{ $activeCount }}</div><small class="text-muted">Con actividad dentro del periodo vigente</small></div></div>
    <div class="col-md-4"><div class="session-summary p-3"><div class="label">Sesiones cerradas</div><div class="value">{{ max($closedCount, 0) }}</div><small class="text-muted">Entre los usuarios del listado actual</small></div></div>
</div>

<div class="row g-4">
    <div class="col-xl-9">
        <div class="card session-table-card mb-0">
            <div class="card-header bg-white border-bottom"><h5 class="mb-1">Últimos accesos por usuario</h5><small class="text-muted">La sesión activa se valida con el tiempo de actividad configurado en el portal.</small></div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light"><tr><th>Usuario</th><th>Roles</th><th>Estado</th><th>Último inicio</th><th>Último cierre</th></tr></thead>
                    <tbody>
                    @forelse($users as $user)
                        <tr class="session-row">
                            <td><div class="fw-semibold">{{ $user->full_name ?: $user->name }}</div><small class="text-muted">{{ $user->email }}</small></td>
                            <td><small>{{ $user->roles->pluck('name')->join(', ') ?: 'Sin rol' }}</small></td>
                            <td>@if($user->session_status === 'active')<span class="status-dot status-active"></span><span class="text-success fw-semibold">Activa</span>@else<span class="status-dot status-closed"></span><span class="text-muted fw-semibold">Cerrada</span>@endif</td>
                            <td>{{ optional($user->last_session_started_at)->format('d/m/Y H:i') }}<br><small class="text-muted">{{ optional($user->last_session_started_at)->diffForHumans() }}</small></td>
                            <td>@if($user->last_session_ended_at){{ $user->last_session_ended_at->format('d/m/Y H:i') }}@else<span class="text-muted">Sin cierre registrado</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-5">Aún no hay inicios de sesión de usuarios internos.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($users->hasPages())<div class="card-footer bg-white">{{ $users->links() }}</div>@endif
        </div>
    </div>
    <div class="col-xl-3">
        <div class="card session-table-card mb-0"><div class="card-header bg-white"><h6 class="mb-0">Inicios recientes</h6></div><div class="list-group list-group-flush">
            @forelse($recentSessions as $session)
                <div class="list-group-item"><div class="fw-semibold text-truncate">{{ $session->user?->full_name ?: $session->user?->name ?? 'Usuario eliminado' }}</div><small class="text-muted d-block">{{ $session->started_at->diffForHumans() }}</small><small class="{{ $session->ended_at ? 'text-muted' : 'text-success' }}">{{ $session->ended_at ? 'Cerrada' : 'Sin cierre registrado' }}</small></div>
            @empty <div class="list-group-item text-muted">El historial comenzará a registrarse con los nuevos accesos.</div>
            @endforelse
        </div></div>
    </div>
</div>
@endsection
