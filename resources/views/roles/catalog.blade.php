@extends('layouts.zircos')

@section('title', 'Catálogo de Roles y Permisos')
@section('page.title', 'Catálogo de Roles y Permisos')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Roles y Permisos</li>
@endsection

@section('content')

@php
    /**
     * Colores Bootstrap por categoría de permiso.
     * Se usan en los badges de cada permiso.
     */
    $categoryBadgeClass = [
        'Usuarios y Sistema' => 'text-bg-primary',
        'Proveedores'        => 'text-bg-success',
        'Órdenes de Compra'  => 'text-bg-warning',
        'Facturas y Pagos'   => 'text-bg-danger',
        'Cotizaciones'       => 'text-bg-info',
        'Reportes'           => 'text-bg-secondary',
        'Catálogo'           => 'text-bg-dark',
        'Requisiciones'      => 'text-bg-primary',
        'Recepciones'        => 'text-bg-success',
        'Presupuesto'        => 'text-bg-warning',
        'Documentos'         => 'text-bg-info',
        'Personal'           => 'bg-light text-dark border',
    ];

    $totalPermissions = collect($categories)->flatten()->count();
@endphp

{{-- ===== ENCABEZADO ===== --}}
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-bottom">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div class="flex-grow-1">
                <h5 class="mb-0 fw-semibold">
                    <i class="ti ti-shield-half me-2 text-primary"></i>Catálogo de Roles y Permisos
                </h5>
                    <small class="text-muted">
                    Administra los permisos de vistas para {{ $roles->count() }} roles del sistema. Los permisos de acciones actuales se conservan.
                </small>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                @foreach($categoryBadgeClass as $cat => $cls)
                    <span class="badge {{ $cls }} fw-normal" style="font-size:11px;">{{ $cat }}</span>
                @endforeach
            </div>
        </div>
    </div>
</div>

{{-- ===== SELECTOR Y DETALLE DEL ROL ===== --}}
@php
    $role = $selectedRole;
    $meta = $roleMeta[$role->name] ?? ['label' => \Illuminate\Support\Str::headline($role->name), 'icon' => 'ti-user', 'color' => '#6b7280', 'desc' => ''];
    $rolePermNames = $role->permissions->pluck('name')->toArray();
    $permCount = count($rolePermNames);
    $roleViewNames = $role->permissions->pluck('name')->intersect($viewPermissions->flatten(1)->pluck('permission'));
    $grouped = collect($categories)->mapWithKeys(function ($catPerms, $catName) use ($rolePermNames) {
        $matched = array_values(array_intersect($catPerms, $rolePermNames));
        return $matched ? [$catName => $matched] : [];
    });
@endphp

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-bottom">
        <div class="row align-items-end g-3">
            <div class="col-12 col-md-7">
                <label class="form-label small text-uppercase text-muted fw-semibold mb-1" for="roleSelector">Selecciona un rol</label>
                <select id="roleSelector" class="form-select" onchange="if (this.value) window.location='{{ route('roles.catalog') }}?role='+encodeURIComponent(this.value)">
                    @foreach($roles as $availableRole)
                        @php $availableMeta = $roleMeta[$availableRole->name] ?? ['label' => \Illuminate\Support\Str::headline($availableRole->name)]; @endphp
                        <option value="{{ $availableRole->name }}" @selected($availableRole->id === $role->id)>
                            {{ $availableMeta['label'] }} · {{ $availableRole->users_count }} usuario(s)
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-5 text-md-end">
                <small class="text-muted">Consulta y modifica sólo los permisos del rol seleccionado.</small>
            </div>
        </div>
    </div>

    <div style="height:5px; background:{{ $meta['color'] }}; border-radius:0 0 .375rem .375rem;"></div>
    <div class="card-body">
        <div class="d-flex align-items-center gap-3 mb-4">
            <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width:46px;height:46px;border-radius:50%;background:{{ $meta['color'] }}1a;border:2px solid {{ $meta['color'] }}33;">
                <i class="ti {{ $meta['icon'] }} fs-5" style="color:{{ $meta['color'] }};"></i>
            </div>
            <div class="flex-grow-1">
                <h5 class="mb-1">{{ $meta['label'] }} <code class="text-muted" style="font-size:11px;">{{ $role->name }}</code></h5>
                <p class="text-muted mb-0">{{ $meta['desc'] }}</p>
            </div>
            <div class="d-flex flex-column align-items-end gap-1">
                <span class="badge rounded-pill text-bg-light border"><i class="ti ti-users me-1"></i>{{ $role->users_count }} usuarios</span>
                <span class="badge rounded-pill text-bg-light border">{{ $permCount }} permisos actuales</span>
            </div>
        </div>

        <div class="rounded border p-3 mb-4" style="background:#f7fbff;border-color:#dbeaf7 !important;">
            <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                <div><div class="fw-semibold text-primary"><i class="ti ti-eye me-1"></i>Permisos de vistas</div><small class="text-muted">Controlan qué módulos puede abrir el rol.</small></div>
                @if($role->name === 'superadmin')
                    <span class="badge text-bg-primary">Acceso total</span>
                @else
                    <span class="badge text-bg-light border">{{ $roleViewNames->count() }} asignados</span>
                @endif
            </div>
            @if($role->name === 'superadmin')
                <small class="text-muted">El superadmin conserva acceso global y no requiere selección manual.</small>
            @else
                <form method="POST" action="{{ route('roles.catalog.view-permissions.update', $role) }}">
                    @csrf
                    @method('PATCH')
                    <div class="row g-2">
                        @foreach($viewPermissions as $category => $definitions)
                            <div class="col-12 col-lg-6">
                                <div class="small text-uppercase text-muted fw-semibold mb-1">{{ $category }}</div>
                                @foreach($definitions as $definition)
                                    <label class="d-flex align-items-start gap-2 small mb-2"><input class="form-check-input mt-1" type="checkbox" name="permissions[]" value="{{ $definition['permission'] }}" @checked($roleViewNames->contains($definition['permission']))><span><strong>{{ $definition['label'] }}</strong><br><span class="text-muted">{{ $definition['description'] }}</span></span></label>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                    <button class="btn btn-sm btn-primary mt-2" type="submit"><i class="ti ti-device-floppy me-1"></i>Guardar vistas</button>
                </form>
            @endif
        </div>

        <div>
            <div class="small text-uppercase text-muted fw-semibold mb-2">Permisos operativos actuales</div>
            @if($role->name === 'superadmin')
                <div class="d-flex align-items-center gap-2"><i class="ti ti-infinity text-primary fs-5"></i><span class="text-primary fw-semibold">Todos los permisos del sistema</span></div>
            @elseif($grouped->isEmpty())
                <span class="text-muted">Sin permisos operativos asignados.</span>
            @else
                <div class="row g-2">
                    @foreach($grouped as $catName => $catPerms)
                        <div class="col-12 col-lg-6"><div class="text-uppercase fw-semibold mb-1" style="font-size:10px;letter-spacing:.06em;color:#9ca3af;">{{ $catName }}</div><div class="d-flex flex-wrap gap-1">@foreach($catPerms as $perm)<span class="badge {{ $categoryBadgeClass[$catName] ?? 'text-bg-secondary' }} fw-normal" style="font-size:11px;" title="{{ $perm }}">{{ $permLabels[$perm] ?? $perm }}</span>@endforeach</div></div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>

{{-- ===== TABLA RESUMEN (matriz rol × categoría) ===== --}}
<div class="card shadow-sm border-0 mt-4">
    <div class="card-header bg-white border-bottom">
        <h6 class="mb-0 fw-semibold">
            <i class="ti ti-table me-2 text-secondary"></i>Resumen — Permisos por categoría
        </h6>
        <small class="text-muted">Número de permisos que cada rol tiene en cada categoría.</small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0 text-center" style="font-size:13px;">
                <thead class="table-light">
                    <tr>
                        <th class="text-start ps-3" style="min-width:170px;">Rol</th>
                        @foreach(array_keys($categories) as $catName)
                            <th style="min-width:90px; font-size:11px; white-space:nowrap;">
                                {{ $catName }}
                            </th>
                        @endforeach
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($roles as $role)
                        @php
                            $meta     = $roleMeta[$role->name] ?? ['label' => \Illuminate\Support\Str::headline($role->name), 'color' => '#6b7280', 'icon' => 'ti-user'];
                            $rPerms   = $role->permissions->pluck('name')->toArray();
                            $isSuper  = $role->name === 'superadmin';
                        @endphp
                        <tr>
                            <td class="text-start ps-3">
                                <span class="d-flex align-items-center gap-2">
                                    <span class="d-inline-flex align-items-center justify-content-center flex-shrink-0"
                                          style="width:22px;height:22px;border-radius:50%;background:{{ $meta['color'] }}1a;">
                                        <i class="ti {{ $meta['icon'] }}" style="color:{{ $meta['color'] }};font-size:12px;"></i>
                                    </span>
                                    <span class="fw-medium">{{ $meta['label'] }}</span>
                                </span>
                            </td>

                            @foreach($categories as $catName => $catPerms)
                                @php
                                    $count = $isSuper
                                        ? count($catPerms)
                                        : count(array_intersect($catPerms, $rPerms));
                                    $max = count($catPerms);
                                @endphp
                                <td>
                                    @if($count === 0)
                                        <span class="text-muted">—</span>
                                    @elseif($count === $max)
                                        <span class="badge text-bg-success fw-normal" title="{{ $count }}/{{ $max }}">{{ $count }}/{{ $max }}</span>
                                    @else
                                        <span class="badge text-bg-warning fw-normal text-dark" title="{{ $count }}/{{ $max }}">{{ $count }}/{{ $max }}</span>
                                    @endif
                                </td>
                            @endforeach

                            <td>
                                @php $total = $isSuper ? $totalPermissions : count($rPerms); @endphp
                                <strong>{{ $total }}</strong>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
