@extends('layouts.zircos')

@section('title', 'Usuario Staff - ' . $user->full_name)

@push('styles')
<style>
    .user-avatar {
        width: 120px;
        height: 120px;
        object-fit: cover;
        border: 4px solid #fff;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .info-card {
        transition: all 0.3s ease;
        border: none;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .info-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .stat-icon {
        width: 48px;
        height: 48px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        font-size: 1.5rem;
    }
    .badge-status {
        font-size: 0.875rem;
        padding: 0.5rem 1rem;
        border-radius: 50px;
    }
</style>
@endpush

@section('page.title', 'Perfil de Usuario')
@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('users.staff.index') }}">Usuarios</a></li>
    <li class="breadcrumb-item active">{{ $user->full_name }}</li>
@endsection

@section('content')

{{-- ================================================================
     HEADER
================================================================ --}}
<div class="card mb-4">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-auto">
                @if($user->avatar)
                    <img src="{{ asset('storage/' . $user->avatar) }}"
                         alt="{{ $user->full_name }}"
                         class="rounded-circle user-avatar">
                @else
                    <div class="rounded-circle user-avatar bg-primary d-flex align-items-center justify-content-center">
                        <i class="ti ti-user text-white" style="font-size: 3rem;"></i>
                    </div>
                @endif
            </div>
            <div class="col">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <h3 class="mb-1 fw-bold">{{ $user->full_name }}</h3>
                        @if($user->job_title)
                            <p class="text-muted mb-2 fs-5">{{ $user->job_title }}</p>
                        @endif
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <span class="badge badge-status {{ $user->is_active ? 'bg-success' : 'bg-danger' }}">
                                <i class="ti ti-{{ $user->is_active ? 'check' : 'x' }} me-1"></i>
                                {{ $user->is_active ? 'Activo' : 'Inactivo' }}
                            </span>
                            @foreach($user->roles as $role)
                                <span class="badge bg-info fs-12">
                                    <i class="ti ti-shield-check me-1"></i>{{ $role->name }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                    <div class="d-flex gap-1">
                        <a href="javascript:void(0);" class="btn btn-outline-primary" onclick="openUserModal('{{ route('users.edit', $user->id) }}')" title="Editar usuario"><i class="ti ti-pencil"></i></a>
                        @if($user->is_active)
                            <a href="#" class="btn btn-outline-warning" onclick="toggleUserStatus({{ $user->id }}, false)" title="Desactivar"><i class="ti ti-player-pause"></i></a>
                        @else
                            <a href="#" class="btn btn-outline-success" onclick="toggleUserStatus({{ $user->id }}, true)" title="Activar"><i class="ti ti-player-play"></i></a>
                        @endif
                        <a href="#" class="btn btn-outline-danger" onclick="deleteUser({{ $user->id }})" title="Eliminar"><i class="ti ti-trash"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ================================================================
     FILA PRINCIPAL: Info personal + Sidebar
================================================================ --}}
<div class="row g-4">

    {{-- Columna principal --}}
    <div class="col-lg-8">

        {{-- Información Personal --}}
        <div class="card info-card mb-4">
            <div class="card-header bg-transparent">
                <h5 class="card-title mb-0">
                    <i class="ti ti-user-circle text-primary me-2"></i>Información Personal
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="d-flex align-items-start">
                            <div class="stat-icon bg-light text-primary me-3"><i class="ti ti-user"></i></div>
                            <div>
                                <label class="form-label text-muted mb-1">Nombre completo</label>
                                <p class="mb-0 fw-semibold">{{ $user->full_name ?: $user->name }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="d-flex align-items-start">
                            <div class="stat-icon bg-light text-info me-3"><i class="ti ti-mail"></i></div>
                            <div>
                                <label class="form-label text-muted mb-1">Correo electrónico</label>
                                <p class="mb-0 fw-semibold">{{ $user->email }}</p>
                                @if($user->email_verified_at)
                                    <small class="text-success"><i class="ti ti-check me-1"></i>Verificado</small>
                                @else
                                    <small class="text-warning"><i class="ti ti-alert-circle me-1"></i>Sin verificar</small>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if($user->phone)
                    <div class="col-md-6">
                        <div class="d-flex align-items-start">
                            <div class="stat-icon bg-light text-success me-3"><i class="ti ti-phone"></i></div>
                            <div>
                                <label class="form-label text-muted mb-1">Teléfono</label>
                                <p class="mb-0 fw-semibold">{{ $user->phone }}</p>
                            </div>
                        </div>
                    </div>
                    @endif

                    @if($user->job_title)
                    <div class="col-md-6">
                        <div class="d-flex align-items-start">
                            <div class="stat-icon bg-light text-warning me-3"><i class="ti ti-briefcase"></i></div>
                            <div>
                                <label class="form-label text-muted mb-1">Puesto</label>
                                <p class="mb-0 fw-semibold">{{ $user->job_title }}</p>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Roles y Permisos --}}
        <div class="card info-card mb-4">
            <div class="card-header bg-transparent">
                <h5 class="card-title mb-0">
                    <i class="ti ti-shield-check text-warning me-2"></i>Roles y Permisos
                </h5>
            </div>
            <div class="card-body">
                @if($user->roles->isEmpty())
                    <div class="text-center text-muted py-3">
                        <i class="ti ti-shield-off fs-2 d-block mb-2"></i>
                        Sin roles asignados
                    </div>
                @else
                    <div class="row g-3">
                        @foreach($user->roles as $role)
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3" style="width:38px;height:38px;font-size:1.1rem;">
                                        <i class="ti ti-shield"></i>
                                    </div>
                                    <div>
                                        <span class="fw-bold text-dark">{{ $role->name }}</span>
                                        <br>
                                        <small class="text-muted">
                                            {{ $role->permissions->count() }} permiso(s)
                                        </small>
                                    </div>
                                </div>
                                @if($role->permissions->isNotEmpty())
                                <div class="d-flex flex-wrap gap-1 mt-2">
                                    @foreach($role->permissions->take(8) as $perm)
                                        <span class="badge bg-light text-dark border" style="font-size:0.7rem;">{{ $perm->name }}</span>
                                    @endforeach
                                    @if($role->permissions->count() > 8)
                                        <span class="badge bg-secondary" style="font-size:0.7rem;">+{{ $role->permissions->count() - 8 }} más</span>
                                    @endif
                                </div>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>

                    {{-- Permisos directos (fuera de roles) --}}
                    @php $directPerms = $user->getDirectPermissions(); @endphp
                    @if($directPerms->isNotEmpty())
                    <div class="mt-4 pt-3 border-top">
                        <h6 class="text-muted mb-2 text-uppercase fs-11 fw-bold">Permisos directos adicionales</h6>
                        <div class="d-flex flex-wrap gap-1">
                            @foreach($directPerms as $perm)
                                <span class="badge bg-info text-white">{{ $perm->name }}</span>
                            @endforeach
                        </div>
                    </div>
                    @endif
                @endif
            </div>
        </div>

        <div class="card info-card mb-4">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="ti ti-eye text-primary me-2"></i>Acceso efectivo a vistas</h5>
                <span class="badge text-bg-primary">{{ $effectivePermissionNames->count() }} permiso(s)</span>
            </div>
            <div class="card-body">
                @foreach($viewPermissions as $category => $definitions)
                    @php
                        $effectiveDefinitions = $definitions->filter(fn ($definition) => $effectivePermissionNames->contains($definition['permission']));
                    @endphp
                    @if($effectiveDefinitions->isNotEmpty())
                        <div class="mb-3">
                            <div class="text-uppercase text-muted fw-semibold mb-1" style="font-size:10px;letter-spacing:.06em;">{{ $category }}</div>
                            <div class="d-flex flex-wrap gap-1">
                                @foreach($effectiveDefinitions as $definition)
                                    <span class="badge bg-light text-dark border" title="{{ $definition['permission'] }}">
                                        {{ $definition['label'] }}
                                        @if($rolePermissionNames->contains($definition['permission']))
                                            <small class="text-muted">· rol</small>
                                        @else
                                            <small class="text-primary">· directo</small>
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        {{-- Compañías asignadas --}}
        <div class="card info-card mb-4">
            <div class="card-header bg-transparent">
                <h5 class="card-title mb-0">
                    <i class="ti ti-building-bank text-primary me-2"></i>Compañías Asignadas
                    <span class="badge bg-primary ms-2">{{ $user->companies->count() }}</span>
                </h5>
            </div>
            <div class="card-body">
                @if($user->companies->isEmpty())
                    <div class="text-center text-muted py-3">
                        <i class="ti ti-building-off fs-2 d-block mb-2"></i>
                        Sin compañías asignadas
                    </div>
                @else
                    <div class="row g-2">
                        @foreach($user->companies as $company)
                        <div class="col-md-6">
                            <div class="d-flex align-items-center border rounded p-2">
                                <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3" style="width:38px;height:38px;font-size:1.1rem;">
                                    <i class="ti ti-building"></i>
                                </div>
                                <div>
                                    <p class="mb-0 fw-semibold">{{ $company->name }}</p>
                                    @if($company->rfc ?? null)
                                        <small class="text-muted">{{ $company->rfc }}</small>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Centros de Costo --}}
        <div class="card info-card mb-4">
            <div class="card-header bg-transparent">
                <h5 class="card-title mb-0">
                    <i class="ti ti-hierarchy-3 text-success me-2"></i>Centros de Costo
                    <span class="badge bg-success ms-2">{{ $user->costCenters->count() }}</span>
                </h5>
            </div>
            <div class="card-body">
                @if($user->costCenters->isEmpty())
                    <div class="text-center text-muted py-3">
                        <i class="ti ti-hierarchy-off fs-2 d-block mb-2"></i>
                        Sin centros de costo asignados
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Código</th>
                                    <th>Nombre</th>
                                    <th class="text-center">Predeterminado</th>
                                    <th class="text-center">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($user->costCenters as $cc)
                                <tr>
                                    <td><span class="badge bg-secondary">{{ $cc->code }}</span></td>
                                    <td class="fw-semibold">{{ $cc->name }}</td>
                                    <td class="text-center">
                                        @if($cc->pivot->is_default)
                                            <span class="badge bg-warning text-dark">
                                                <i class="ti ti-star-filled me-1"></i>Predeterminado
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($cc->pivot->is_active)
                                            <span class="badge bg-success">Activo</span>
                                        @else
                                            <span class="badge bg-danger">Inactivo</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Datos de Empleado --}}
        @if($user->employee)
        <div class="card info-card mb-4">
            <div class="card-header bg-transparent">
                <h5 class="card-title mb-0">
                    <i class="ti ti-id-badge text-info me-2"></i>Datos de Empleado
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    @if($user->employee->employee_number)
                    <div class="col-md-6">
                        <div class="d-flex align-items-start">
                            <div class="stat-icon bg-light text-info me-3"><i class="ti ti-hash"></i></div>
                            <div>
                                <label class="form-label text-muted mb-1">Número de empleado</label>
                                <p class="mb-0 fw-semibold">{{ $user->employee->employee_number }}</p>
                            </div>
                        </div>
                    </div>
                    @endif

                    @if($user->employee->department)
                    <div class="col-md-6">
                        <div class="d-flex align-items-start">
                            <div class="stat-icon bg-light text-primary me-3"><i class="ti ti-building"></i></div>
                            <div>
                                <label class="form-label text-muted mb-1">Departamento</label>
                                <p class="mb-0 fw-semibold">{{ $user->employee->department }}</p>
                            </div>
                        </div>
                    </div>
                    @endif

                    @if($user->employee->team)
                    <div class="col-md-6">
                        <div class="d-flex align-items-start">
                            <div class="stat-icon bg-light text-success me-3"><i class="ti ti-users"></i></div>
                            <div>
                                <label class="form-label text-muted mb-1">Equipo</label>
                                <p class="mb-0 fw-semibold">{{ $user->employee->team }}</p>
                            </div>
                        </div>
                    </div>
                    @endif

                    @if($user->employee->hire_date)
                    <div class="col-md-6">
                        <div class="d-flex align-items-start">
                            <div class="stat-icon bg-light text-warning me-3"><i class="ti ti-calendar-event"></i></div>
                            <div>
                                <label class="form-label text-muted mb-1">Fecha de ingreso</label>
                                <p class="mb-0 fw-semibold">{{ $user->employee->hire_date->format('d/m/Y') }}</p>
                                <small class="text-muted">{{ $user->employee->hire_date->diffForHumans() }}</small>
                            </div>
                        </div>
                    </div>
                    @endif

                    @if($user->employee->seniority)
                    <div class="col-md-6">
                        <div class="d-flex align-items-start">
                            <div class="stat-icon bg-light text-secondary me-3"><i class="ti ti-award"></i></div>
                            <div>
                                <label class="form-label text-muted mb-1">Antigüedad</label>
                                <p class="mb-0 fw-semibold">{{ $user->employee->seniority }}</p>
                            </div>
                        </div>
                    </div>
                    @endif

                    @if($user->employee->company)
                    <div class="col-md-6">
                        <div class="d-flex align-items-start">
                            <div class="stat-icon bg-light text-danger me-3"><i class="ti ti-building-bank"></i></div>
                            <div>
                                <label class="form-label text-muted mb-1">Empresa (RRHH)</label>
                                <p class="mb-0 fw-semibold">{{ $user->employee->company }}</p>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endif

        {{-- Información del Sistema --}}
        <div class="card info-card">
            <div class="card-header bg-transparent">
                <h5 class="card-title mb-0">
                    <i class="ti ti-settings text-secondary me-2"></i>Información del Sistema
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="d-flex align-items-start">
                            <div class="stat-icon bg-light text-primary me-3"><i class="ti ti-calendar-plus"></i></div>
                            <div>
                                <label class="form-label text-muted mb-1">Fecha de registro</label>
                                <p class="mb-0 fw-semibold">{{ $user->created_at->format('d/m/Y H:i') }}</p>
                                <small class="text-muted">{{ $user->created_at->diffForHumans() }}</small>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="d-flex align-items-start">
                            <div class="stat-icon bg-light text-info me-3"><i class="ti ti-edit"></i></div>
                            <div>
                                <label class="form-label text-muted mb-1">Última actualización</label>
                                <p class="mb-0 fw-semibold">{{ $user->updated_at->format('d/m/Y H:i') }}</p>
                                <small class="text-muted">{{ $user->updated_at->diffForHumans() }}</small>
                            </div>
                        </div>
                    </div>

                    @if($user->last_login)
                    <div class="col-md-6">
                        <div class="d-flex align-items-start">
                            <div class="stat-icon bg-light text-success me-3"><i class="ti ti-login"></i></div>
                            <div>
                                <label class="form-label text-muted mb-1">Último acceso</label>
                                <p class="mb-0 fw-semibold">{{ $user->last_login->format('d/m/Y H:i') }}</p>
                                <small class="text-muted">{{ $user->last_login->diffForHumans() }}</small>
                            </div>
                        </div>
                    </div>
                    @endif

                    <div class="col-md-6">
                        <div class="d-flex align-items-start">
                            <div class="stat-icon bg-light text-secondary me-3"><i class="ti ti-hash"></i></div>
                            <div>
                                <label class="form-label text-muted mb-1">ID de usuario</label>
                                <p class="mb-0 fw-semibold">#{{ $user->id }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- =====================================================
         Sidebar
    ====================================================== --}}
    <div class="col-lg-4">

        {{-- Estadísticas rápidas --}}
        <div class="card info-card mb-4">
            <div class="card-header bg-transparent">
                <h5 class="card-title mb-0">
                    <i class="ti ti-chart-bar text-info me-2"></i>Resumen
                </h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <small class="text-muted">Tiempo en el sistema</small>
                        <div class="fw-bold">{{ $user->created_at->diffForHumans(null, true) }}</div>
                    </div>
                    <div class="stat-icon bg-primary text-white"><i class="ti ti-clock"></i></div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <small class="text-muted">Roles asignados</small>
                        <div class="fw-bold">{{ $user->roles->count() }}</div>
                    </div>
                    <div class="stat-icon bg-warning text-white"><i class="ti ti-shield-check"></i></div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <small class="text-muted">Compañías</small>
                        <div class="fw-bold">{{ $user->companies->count() }}</div>
                    </div>
                    <div class="stat-icon bg-primary bg-opacity-75 text-white"><i class="ti ti-building"></i></div>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted">Centros de costo</small>
                        <div class="fw-bold">{{ $user->costCenters->count() }}</div>
                    </div>
                    <div class="stat-icon bg-success text-white"><i class="ti ti-hierarchy-3"></i></div>
                </div>
            </div>
        </div>

        {{-- Estado detallado --}}
        <div class="card info-card mb-4">
            <div class="card-header bg-transparent">
                <h5 class="card-title mb-0">
                    <i class="ti ti-info-circle text-secondary me-2"></i>Estado de la Cuenta
                </h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                        <span class="text-muted"><i class="ti ti-power me-2"></i>Estado</span>
                        <span class="badge {{ $user->is_active ? 'bg-success' : 'bg-danger' }}">
                            {{ $user->is_active ? 'Activo' : 'Inactivo' }}
                        </span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                        <span class="text-muted"><i class="ti ti-mail me-2"></i>Email verificado</span>
                        <span class="badge {{ $user->email_verified_at ? 'bg-success' : 'bg-warning text-dark' }}">
                            {{ $user->email_verified_at ? 'Sí' : 'No' }}
                        </span>
                    </li>
                    @if($user->employee)
                    <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                        <span class="text-muted"><i class="ti ti-id-badge me-2"></i>Empleado activo</span>
                        <span class="badge {{ $user->employee->is_active ? 'bg-success' : 'bg-danger' }}">
                            {{ $user->employee->is_active ? 'Sí' : 'No' }}
                        </span>
                    </li>
                    @endif
                    @if($user->supplier)
                    <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                        <span class="text-muted"><i class="ti ti-truck me-2"></i>Proveedor</span>
                        <span class="badge bg-info">Vinculado</span>
                    </li>
                    @endif
                </ul>
            </div>
        </div>

        {{-- Acciones Rápidas --}}
        <div class="card info-card">
            <div class="card-header bg-transparent">
                <h5 class="card-title mb-0">
                    <i class="ti ti-bolt text-warning me-2"></i>Acciones Rápidas
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="javascript:void(0);" onclick="openUserModal('{{ route('users.edit', $user->id) }}')"
                       class="btn btn-primary">
                        <i class="ti ti-edit me-2"></i>Editar Usuario
                    </a>

                    @if($user->is_active)
                        <button type="button" class="btn btn-warning" onclick="toggleUserStatus({{ $user->id }}, false)">
                            <i class="ti ti-player-pause me-2"></i>Desactivar
                        </button>
                    @else
                        <button type="button" class="btn btn-success" onclick="toggleUserStatus({{ $user->id }}, true)">
                            <i class="ti ti-player-play me-2"></i>Activar
                        </button>
                    @endif

                    <a href="{{ route('users.staff.index') }}" class="btn btn-outline-secondary">
                        <i class="ti ti-arrow-left me-2"></i>Volver al Listado
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal genérico --}}
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content" id="userModalContent"></div>
    </div>
</div>

@endsection

@push('scripts')
<script>
function toggleUserStatus(userId, active) {
    const action = active ? 'activar' : 'desactivar';
    Swal.fire({
        title: active ? '¿Activar usuario?' : '¿Desactivar usuario?',
        text: `¿Estás seguro de que quieres ${action} este usuario?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: active ? '#28a745' : '#ffc107',
        cancelButtonColor: '#6c757d',
        confirmButtonText: active ? 'Sí, activar' : 'Sí, desactivar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/users/${userId}/toggle-status`, {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ is_active: active })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) location.reload();
                else Swal.fire('Error', 'No se pudo actualizar el estado del usuario', 'error');
            });
        }
    });
}

function deleteUser(userId) {
    Swal.fire({
        title: '¿Eliminar usuario?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('delete-form').action = `/users/${userId}`;
            document.getElementById('delete-form').submit();
        }
    });
}

$(document).on('click', '.js-open-user-modal', function (e) {
    e.preventDefault();
    openUserModal($(this).data('url'));
});

function openUserModal(url) {
    const el = document.getElementById('userModal');
    const modal = bootstrap.Modal.getOrCreateInstance(el);
    $('#userModalContent').html('<div class="p-5 text-center">Cargando...</div>');
    modal.show();
    $.get(url)
        .done(html => $('#userModalContent').html(html))
        .fail(() => $('#userModalContent').html('<div class="p-5 text-danger">No se pudo cargar el formulario.</div>'));
}

$(document).on('submit', '#userForm', function (e) {
    e.preventDefault();
    const $form  = $(this);
    $form.find('button[type="submit"]').prop('disabled', true);
    $('#formErrors').addClass('d-none').empty();

    $.ajax({ url: $form.attr('action'), type: 'POST', data: $form.serialize() })
        .done(() => {
            const modal = bootstrap.Modal.getInstance(document.getElementById('userModal'));
            if (modal) modal.hide();
            $('#userModal').one('hidden.bs.modal', () => {
                $('#userModalContent').empty();
                if (typeof table !== 'undefined') table.ajax.reload(null, false);
                if (typeof toastOk === 'function') toastOk('Guardado correctamente');
            });
        })
        .fail(xhr => {
            $form.find('button[type="submit"]').prop('disabled', false);
            if (xhr.status === 422) {
                let html = '<div class="alert alert-danger"><ul class="mb-0">';
                Object.values(xhr.responseJSON.errors || {}).forEach(arr => arr.forEach(msg => html += `<li>${msg}</li>`));
                html += '</ul></div>';
                $('#formErrors').html(html).removeClass('d-none');
            } else {
                $('#formErrors').html('<div class="alert alert-danger">Error inesperado.</div>').removeClass('d-none');
            }
        });
});
</script>

<form id="delete-form" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>
@endpush
