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
                    Vista de solo lectura &mdash; {{ $roles->count() }} roles del sistema &middot; {{ $totalPermissions }} permisos definidos.
                    Los permisos se asignan a los roles en <code>RolePermissionSeeder</code>.
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

{{-- ===== GRILLA DE ROLES ===== --}}
<div class="row g-4">
    @foreach($roles as $role)
        @php
            $meta = $roleMeta[$role->name] ?? [
                'label' => \Illuminate\Support\Str::headline($role->name),
                'icon'  => 'ti-user',
                'color' => '#6b7280',
                'desc'  => '',
            ];

            // Permisos del rol como colección de nombres
            $rolePermNames = $role->permissions->pluck('name')->toArray();

            // Total de permisos asignados a este rol
            $permCount = count($rolePermNames);

            // Agrupar los permisos del rol por categoría
            $grouped = [];
            foreach ($categories as $catName => $catPerms) {
                $matched = array_values(array_intersect($catPerms, $rolePermNames));
                if (!empty($matched)) {
                    $grouped[$catName] = $matched;
                }
            }
        @endphp

        <div class="col-12 col-xl-6">
            <div class="card h-100 shadow-sm border-0 role-card" data-role="{{ $role->name }}">

                {{-- Barra de color superior --}}
                <div style="height:5px; background:{{ $meta['color'] }}; border-radius: .375rem .375rem 0 0;"></div>

                {{-- Encabezado de la tarjeta --}}
                <div class="card-header bg-white border-bottom py-3">
                    <div class="d-flex align-items-center gap-3">
                        {{-- Icono circular con color del rol --}}
                        <div class="role-icon-wrap d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width:44px;height:44px;border-radius:50%;background:{{ $meta['color'] }}1a;border:2px solid {{ $meta['color'] }}33;">
                            <i class="ti {{ $meta['icon'] }} fs-5" style="color:{{ $meta['color'] }};"></i>
                        </div>

                        <div class="flex-grow-1 min-width-0">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="fw-semibold fs-6">{{ $meta['label'] }}</span>
                                <code class="text-muted" style="font-size:11px;">{{ $role->name }}</code>
                            </div>
                            <small class="text-muted d-block">{{ $meta['desc'] }}</small>
                        </div>

                        <div class="d-flex flex-column align-items-end gap-1 flex-shrink-0">
                            <span class="badge rounded-pill text-bg-light border fw-normal"
                                  title="{{ $role->users_count }} usuario(s) con este rol">
                                <i class="ti ti-users me-1"></i>{{ $role->users_count }}
                            </span>
                            <span class="badge rounded-pill fw-normal"
                                  style="background:{{ $meta['color'] }}1a;color:{{ $meta['color'] }};border:1px solid {{ $meta['color'] }}33;"
                                  title="{{ $permCount }} permiso(s) asignado(s)">
                                {{ $permCount }} permisos
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Cuerpo: permisos agrupados --}}
                <div class="card-body py-3">
                    @if($permCount === 0)
                        <p class="text-muted text-center mb-0 py-2">
                            <i class="ti ti-lock-off me-1"></i>Sin permisos asignados
                        </p>

                    @elseif($role->name === 'superadmin')
                        {{-- Superadmin tiene todos los permisos --}}
                        <div class="d-flex align-items-center gap-2 py-1">
                            <i class="ti ti-infinity text-primary fs-5"></i>
                            <span class="text-primary fw-semibold">Todos los permisos del sistema</span>
                        </div>
                        <small class="text-muted">
                            Acceso completo a los {{ $totalPermissions }} permisos definidos, incluyendo cualquier permiso nuevo que se agregue.
                        </small>

                    @else
                        @foreach($grouped as $catName => $catPerms)
                            <div class="mb-2">
                                <div class="text-uppercase fw-semibold mb-1"
                                     style="font-size:10px;letter-spacing:.06em;color:#9ca3af;">
                                    {{ $catName }}
                                </div>
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach($catPerms as $perm)
                                        <span class="badge {{ $categoryBadgeClass[$catName] ?? 'text-bg-secondary' }} fw-normal"
                                              style="font-size:11px;"
                                              title="{{ $perm }}">
                                            {{ $permLabels[$perm] ?? $perm }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>

            </div>{{-- .card --}}
        </div>{{-- .col --}}

    @endforeach
</div>{{-- .row --}}

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
