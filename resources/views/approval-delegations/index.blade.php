@extends('layouts.zircos')

@section('title', 'Mi delegación')
@section('page.title', 'Mi delegación')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('authorizations.index') }}">Autorizaciones</a></li>
    <li class="breadcrumb-item active">Mi delegación</li>
@endsection

@section('content')
@php
    $editing = $activeDelegation ?? $draftDelegation;
    $selectedIds = collect(old('delegate_ids', $editing?->activeMembers?->pluck('delegate_user_id')->all() ?? []))->map(fn($id) => (int) $id);
@endphp

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="card border-0 shadow-sm mb-3 {{ $activeDelegation ? 'bg-warning-subtle' : 'bg-success-subtle' }}">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3 py-3">
        <div class="d-flex align-items-center gap-3">
            <div class="avatar-md rounded-circle {{ $activeDelegation ? 'bg-warning text-dark' : 'bg-success text-white' }} d-flex align-items-center justify-content-center">
                <i class="ti ti-plane-departure fs-3"></i>
            </div>
            <div>
                <h5 class="mb-1">Modo Delegar</h5>
                @if($activeDelegation)
                    <div class="text-dark">
                        Activo para {{ $activeDelegation->activeMembers->count() }} delegado(s)
                        · {{ $activeDelegation->ends_at ? 'hasta '.$activeDelegation->ends_at->format('d/m/Y H:i') : 'sin fecha de término' }}
                    </div>
                @elseif($draftDelegation && $draftDelegation->activeMembers->isNotEmpty())
                    <div class="text-muted">Configuración lista para activarse con {{ $draftDelegation->activeMembers->count() }} delegado(s).</div>
                @else
                    <div class="text-muted">Selecciona y guarda al menos un delegado para poder activarlo.</div>
                @endif
            </div>
        </div>

        @if($activeDelegation)
            <form method="POST" action="{{ route('approval-delegations.deactivate') }}" class="delegation-switch">
                @csrf
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" style="width:3.5rem;height:1.75rem" type="checkbox" role="switch" id="delegation-toggle" checked
                           onchange="if (confirm('¿Desactivar el modo Delegar?')) { this.form.submit(); } else { this.checked = true; }">
                    <label class="form-check-label fw-bold ms-2" for="delegation-toggle">Encendido</label>
                </div>
            </form>
        @elseif($draftDelegation && $draftDelegation->activeMembers->isNotEmpty())
            <form method="POST" action="{{ route('approval-delegations.activate') }}" class="delegation-switch">
                @csrf
                @foreach($draftDelegation->activeMembers as $member)
                    <input type="hidden" name="delegate_ids[]" value="{{ $member->delegate_user_id }}">
                @endforeach
                <input type="hidden" name="ends_at" value="{{ $draftDelegation->ends_at?->format('Y-m-d H:i:s') }}">
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" style="width:3.5rem;height:1.75rem" type="checkbox" role="switch" id="delegation-toggle"
                           onchange="if (confirm('¿Activar el modo Delegar ahora?')) { this.form.submit(); } else { this.checked = false; }">
                    <label class="form-check-label fw-bold ms-2" for="delegation-toggle">Apagado</label>
                </div>
            </form>
        @else
            <div class="form-check form-switch mb-0" title="Primero guarda al menos un delegado">
                <input class="form-check-input" style="width:3.5rem;height:1.75rem" type="checkbox" role="switch" id="delegation-toggle" disabled>
                <label class="form-check-label text-muted ms-2" for="delegation-toggle">Apagado</label>
            </div>
        @endif
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-1">Delegados autorizadores</h5>
                <small class="text-muted">Podrán ver y resolver tus tres tipos de autorizaciones. Tú conservarás acceso.</small>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('approval-delegations.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        @forelse($eligibleDelegates as $delegate)
                            <div class="col-md-6">
                                <label class="card h-100 border delegation-person-card cursor-pointer">
                                    <div class="card-body d-flex gap-3 align-items-center">
                                        <input class="form-check-input mt-0" type="checkbox" name="delegate_ids[]"
                                               value="{{ $delegate->id }}" @checked($selectedIds->contains($delegate->id))>
                                        <div class="flex-grow-1">
                                            <div class="fw-semibold">{{ $delegate->name }}</div>
                                            <small class="text-muted">{{ $delegate->job_title ?: $delegate->email }}</small>
                                            <div class="mt-1">
                                                <span class="badge bg-soft-primary text-primary">
                                                    {{ $delegate->authorizerAssignment?->authorizerRole?->name }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        @empty
                            <div class="col-12">
                                <div class="alert alert-info mb-0">No hay otros autorizadores activos y elegibles.</div>
                            </div>
                        @endforelse
                    </div>
                    @error('delegate_ids') <div class="text-danger small mt-2">{{ $message }}</div> @enderror

                    <hr class="my-4">
                    <div class="row align-items-end g-3">
                        <div class="col-md-7">
                            <label for="ends_at" class="form-label">Finalización automática <span class="text-muted">(opcional)</span></label>
                            <input type="datetime-local" class="form-control @error('ends_at') is-invalid @enderror"
                                   id="ends_at" name="ends_at"
                                   value="{{ old('ends_at', $editing?->ends_at?->format('Y-m-d\TH:i')) }}">
                            <small class="text-muted">Sin fecha, permanecerá activo hasta que lo desactives.</small>
                            @error('ends_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-5 text-md-end">
                            <button class="btn btn-primary px-4" @disabled($eligibleDelegates->isEmpty())>
                                {{ $activeDelegation ? 'Guardar cambios' : 'Guardar configuración' }}
                            </button>
                        </div>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h5 class="mb-0">Historial reciente</h5></div>
            <div class="card-body">
                @forelse($history as $period)
                    <div class="border-start border-3 border-secondary ps-3 mb-4">
                        <div class="fw-semibold">{{ $period->starts_at?->format('d/m/Y H:i') }}</div>
                        <small class="text-muted d-block">Finalizó {{ $period->deactivated_at?->format('d/m/Y H:i') ?? $period->ends_at?->format('d/m/Y H:i') }}</small>
                        <div class="mt-2">
                            @foreach($period->members as $member)
                                <span class="badge bg-light text-dark border mb-1">{{ $member->delegate?->name }}</span>
                            @endforeach
                        </div>
                        @if($period->deactivation_reason)
                            <small class="d-block mt-2">{{ $period->deactivation_reason }}</small>
                        @endif
                    </div>
                @empty
                    <p class="text-muted mb-0">Aún no hay periodos finalizados.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
