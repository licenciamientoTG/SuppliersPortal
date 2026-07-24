@extends('layouts.zircos')

@section('title', 'Mis autorizaciones')
@section('page.title', 'Mis autorizaciones')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Autorizaciones</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
    <div>
        <h4 class="mb-1">Bandeja de autorizaciones</h4>
        <p class="text-muted mb-0">Compras regulares, directas y por convenio en un solo lugar.</p>
    </div>
    @if(auth()->user()->authorizerAssignment()->exists())
        <a href="{{ route('approval-delegations.index') }}" class="btn btn-outline-primary">
            <i class="ti ti-plane-departure me-1"></i>Configurar Delegar
        </a>
    @endif
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-lg-4">
                <label class="form-label">Responsabilidad</label>
                <div class="btn-group w-100" role="group">
                    @foreach(['all' => 'Todas', 'own' => 'Propias', 'delegated' => 'Delegadas'] as $value => $label)
                        <a href="{{ route('authorizations.index', array_filter(['scope' => $value, 'type' => $type, 'days' => $days])) }}"
                           class="btn {{ $scope === $value ? 'btn-primary' : 'btn-outline-primary' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>
            <div class="col-md-4 col-lg-3">
                <label for="type" class="form-label">Tipo</label>
                <select name="type" id="type" class="form-select">
                    <option value="all" @selected($type === 'all')>Todos los tipos</option>
                    <option value="quotation" @selected($type === 'quotation')>Compra regular</option>
                    <option value="direct" @selected($type === 'direct')>OC Directa</option>
                    <option value="contract" @selected($type === 'contract')>OC por convenio</option>
                </select>
            </div>
            <div class="col-md-4 col-lg-3">
                <label for="days" class="form-label">Antigüedad</label>
                <select name="days" id="days" class="form-select">
                    <option value="">Cualquier antigüedad</option>
                    <option value="2" @selected($days === 2)>Más de 2 días</option>
                    <option value="5" @selected($days === 5)>Más de 5 días</option>
                    <option value="10" @selected($days === 10)>Más de 10 días</option>
                </select>
            </div>
            <div class="col-md-4 col-lg-2">
                <input type="hidden" name="scope" value="{{ $scope }}">
                <button class="btn btn-dark w-100"><i class="ti ti-filter me-1"></i>Filtrar</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3">
    @forelse($items as $item)
        <div class="col-12">
            <div class="card border-0 shadow-sm {{ $item['is_delegated'] ? 'border-start border-4 border-info' : '' }}">
                <div class="card-body">
                    <div class="row align-items-center g-3">
                        <div class="col-lg-2">
                            <span class="badge bg-soft-primary text-primary mb-2">{{ $item['type_label'] }}</span>
                            <div class="fw-bold fs-5">{{ $item['folio'] }}</div>
                            @if($item['reference'])
                                <small class="text-muted">{{ $item['reference'] }}</small>
                            @endif
                        </div>
                        <div class="col-lg-3">
                            <small class="text-muted d-block">Proveedor</small>
                            <span class="fw-semibold">{{ $item['supplier'] ?: 'Sin proveedor' }}</span>
                        </div>
                        <div class="col-lg-2">
                            <small class="text-muted d-block">Total</small>
                            <span class="fw-bold text-primary">{{ format_money($item['total'], $item['currency']) }}</span>
                        </div>
                        <div class="col-lg-3">
                            @if($item['is_delegated'])
                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">
                                    <i class="ti ti-user-share me-1"></i>Delegada por {{ $item['principal']?->name }}
                                </span>
                                <small class="text-muted d-block mt-2">Actuarás en representación del titular.</small>
                            @else
                                <span class="badge bg-light text-dark border">Asignada a ti</span>
                            @endif
                        </div>
                        <div class="col-lg-2 text-lg-end">
                            <a href="{{ $item['url'] }}" class="btn btn-primary">
                                Revisar <i class="ti ti-arrow-right ms-1"></i>
                            </a>
                            <small class="text-muted d-block mt-2">{{ $item['created_at']->diffForHumans() }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <div class="avatar-lg bg-success-subtle text-success rounded-circle mx-auto d-flex align-items-center justify-content-center mb-3">
                        <i class="ti ti-circle-check fs-1"></i>
                    </div>
                    <h5>No hay pendientes con estos filtros</h5>
                    <p class="text-muted mb-0">Tu bandeja está al día.</p>
                </div>
            </div>
        </div>
    @endforelse
</div>
@endsection
