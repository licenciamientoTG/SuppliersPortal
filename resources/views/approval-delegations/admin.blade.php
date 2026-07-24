@extends('layouts.zircos')

@section('title', 'Delegaciones activas')
@section('page.title', 'Delegaciones activas')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-1">Supervisión de delegaciones</h5>
        <small class="text-muted">Consulta y desactivación de emergencia. Superadmin no puede modificar los integrantes.</small>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr><th>Titular</th><th>Delegados</th><th>Inicio</th><th>Término</th><th>Acción</th></tr>
            </thead>
            <tbody>
                @forelse($delegations as $delegation)
                    <tr>
                        <td class="fw-semibold">{{ $delegation->delegator?->name }}</td>
                        <td>
                            @foreach($delegation->activeMembers as $member)
                                <span class="badge bg-info-subtle text-info-emphasis">{{ $member->delegate?->name }}</span>
                            @endforeach
                        </td>
                        <td>{{ $delegation->starts_at?->format('d/m/Y H:i') }}</td>
                        <td>{{ $delegation->ends_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.approval-delegations.deactivate', $delegation) }}" class="d-flex gap-2">
                                @csrf
                                <input name="reason" class="form-control form-control-sm" minlength="10" maxlength="500"
                                       placeholder="Motivo obligatorio" required>
                                <button class="btn btn-sm btn-outline-danger">Desactivar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-5">No hay delegaciones activas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
