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

@if($activeDelegation)
    <div class="alert alert-warning border-0 shadow-sm d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <div class="fw-bold"><i class="ti ti-plane-departure me-1"></i>Modo Delegar activo</div>
            <div>
                {{ $activeDelegation->activeMembers->count() }} delegado(s)
                · {{ $activeDelegation->ends_at ? 'hasta '.$activeDelegation->ends_at->format('d/m/Y H:i') : 'sin fecha de término' }}
            </div>
        </div>
        <form method="POST" action="{{ route('approval-delegations.deactivate') }}">
            @csrf
            <button class="btn btn-danger" onclick="return confirm('¿Desactivar el modo Delegar?')">
                Desactivar ahora
            </button>
        </form>
    </div>
@endif

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

                @if($draftDelegation && !$activeDelegation)
                    <form method="POST" action="{{ route('approval-delegations.activate') }}" class="mt-3 text-end">
                        @csrf
                        @foreach($selectedIds as $id)<input type="hidden" name="delegate_ids[]" value="{{ $id }}">@endforeach
                        <input type="hidden" name="ends_at" value="{{ $draftDelegation->ends_at?->format('Y-m-d H:i:s') }}">
                        <button class="btn btn-success"><i class="ti ti-plane-departure me-1"></i>Activar configuración guardada</button>
                    </form>
                @endif
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
