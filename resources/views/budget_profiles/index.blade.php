@extends('layouts.zircos')

@section('title', 'Perfiles presupuestales')
@section('page.title', 'Perfiles presupuestales')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Perfiles presupuestales</li>
@endsection

@push('styles')
    <style>
        .budget-profile-page {
            --bp-border: #d9e2ec;
            --bp-muted: #64748b;
            --bp-surface: #ffffff;
            --bp-soft: #f7fafc;
            --bp-accent: #2563eb;
            --bp-success: #14845c;
            --bp-warning: #b45309;
        }

        .bp-toolbar {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 1rem;
            align-items: center;
            padding: 1rem 0 1.25rem;
        }

        .bp-title {
            display: flex;
            align-items: center;
            gap: .65rem;
            margin: 0;
            font-size: 1.35rem;
            font-weight: 700;
        }

        .bp-title-icon {
            display: inline-flex;
            width: 2.4rem;
            height: 2.4rem;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            background: linear-gradient(135deg, #2563eb, #16a34a);
            border-radius: 8px;
            flex: 0 0 auto;
        }

        .bp-summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(130px, 1fr));
            gap: .75rem;
            margin-bottom: 1rem;
        }

        .bp-metric,
        .bp-panel,
        .bp-profile-card,
        .bp-department-row {
            border: 1px solid var(--bp-border);
            background: var(--bp-surface);
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
        }

        .bp-metric {
            padding: .9rem 1rem;
        }

        .bp-metric-label {
            color: var(--bp-muted);
            font-size: .76rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .bp-metric-value {
            margin-top: .2rem;
            font-size: 1.5rem;
            font-weight: 800;
            line-height: 1;
        }

        .bp-panel {
            margin-bottom: 1rem;
            overflow: hidden;
        }

        .bp-panel-header {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: center;
            padding: 1rem 1.1rem;
            border-bottom: 1px solid var(--bp-border);
            background: linear-gradient(180deg, #ffffff, #f8fafc);
        }

        .bp-panel-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 750;
        }

        .bp-panel-body {
            padding: 1.1rem;
        }

        .bp-tabs {
            display: inline-flex;
            gap: .35rem;
            padding: .25rem;
            border: 1px solid var(--bp-border);
            border-radius: 8px;
            background: #eef2f7;
        }

        .bp-tabs .nav-link {
            border: 0;
            border-radius: 6px;
            color: #334155;
            font-weight: 700;
            padding: .48rem .8rem;
        }

        .bp-tabs .nav-link.active {
            color: #0f172a;
            background: #ffffff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .08);
        }

        .bp-search {
            max-width: 420px;
        }

        .bp-list-controls {
            display: grid;
            grid-template-columns: minmax(240px, 1fr) minmax(220px, .55fr) auto;
            gap: .75rem;
            align-items: center;
            width: min(100%, 920px);
        }

        .bp-profile-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .9rem;
        }

        .bp-pagination {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            align-items: center;
            justify-content: space-between;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--bp-border);
        }

        .bp-page-actions {
            display: inline-flex;
            gap: .4rem;
            align-items: center;
        }

        .bp-page-status {
            color: var(--bp-muted);
            font-size: .84rem;
            font-weight: 700;
        }

        .bp-profile-card {
            display: grid;
            grid-template-rows: auto 1fr auto;
            min-height: 100%;
            overflow: hidden;
        }

        .bp-profile-head {
            display: flex;
            justify-content: space-between;
            gap: .85rem;
            padding: 1rem;
            border-bottom: 1px solid var(--bp-border);
        }

        .bp-profile-name {
            margin: 0;
            font-size: 1rem;
            font-weight: 800;
        }

        .bp-profile-department {
            color: var(--bp-muted);
            font-size: .85rem;
        }

        .bp-profile-body {
            padding: 1rem;
        }

        .bp-profile-description {
            min-height: 2.3rem;
            color: #475569;
        }

        .bp-chip-row {
            display: flex;
            flex-wrap: wrap;
            gap: .4rem;
        }

        .bp-chip {
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            padding: .28rem .5rem;
            border: 1px solid var(--bp-border);
            border-radius: 999px;
            background: var(--bp-soft);
            color: #334155;
            font-size: .78rem;
            font-weight: 700;
        }

        .bp-chip-success {
            border-color: rgba(20, 132, 92, .25);
            background: rgba(20, 132, 92, .09);
            color: var(--bp-success);
        }

        .bp-chip-muted {
            color: var(--bp-muted);
        }

        .bp-profile-actions {
            display: flex;
            justify-content: flex-end;
            gap: .5rem;
            padding: .85rem 1rem;
            border-top: 1px solid var(--bp-border);
            background: #fbfdff;
        }

        .bp-edit-area {
            padding: 1rem;
            border-top: 1px solid var(--bp-border);
            background: #f8fafc;
        }

        .bp-department-list {
            display: grid;
            gap: .75rem;
        }

        .bp-department-row {
            display: grid;
            grid-template-columns: minmax(220px, .35fr) minmax(0, 1fr);
            gap: 1rem;
            padding: 1rem;
        }

        .bp-department-name {
            font-weight: 800;
        }

        .bp-empty {
            padding: 2.5rem 1rem;
            text-align: center;
            color: var(--bp-muted);
            border: 1px dashed var(--bp-border);
            border-radius: 8px;
            background: #fbfdff;
        }

        .bp-subaccount-picker {
            border: 1px solid var(--bp-border);
            border-radius: 8px;
            background: #ffffff;
            overflow: hidden;
        }

        .bp-picker-toolbar {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: .75rem;
            align-items: center;
            padding: .75rem;
            border-bottom: 1px solid var(--bp-border);
            background: #f8fafc;
        }

        .bp-picker-count {
            color: var(--bp-muted);
            font-size: .78rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .bp-picker-list {
            max-height: 360px;
            overflow: auto;
        }

        .bp-picker-group + .bp-picker-group {
            border-top: 1px solid var(--bp-border);
        }

        .bp-picker-group-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            padding: .55rem .75rem;
            background: #ffffff;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .bp-picker-group-title {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            min-width: 0;
            border: 0;
            background: transparent;
            padding: 0;
            color: #0f172a;
            font-size: .86rem;
            font-weight: 800;
            text-align: left;
        }

        .bp-picker-group-title i {
            transition: transform .15s ease;
        }

        .bp-picker-group-title.collapsed i {
            transform: rotate(-90deg);
        }

        .bp-picker-actions {
            display: inline-flex;
            gap: .25rem;
            flex: 0 0 auto;
        }

        .bp-picker-actions .btn {
            padding: 0 .25rem;
            font-weight: 700;
            text-decoration: none;
        }

        .bp-picker-options {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .35rem;
            padding: 0 .75rem .75rem;
        }

        .bp-picker-option {
            display: flex;
            align-items: flex-start;
            gap: .5rem;
            min-height: 42px;
            padding: .48rem .55rem;
            border: 1px solid #e6edf5;
            border-radius: 7px;
            background: #fbfdff;
            color: #334155;
            cursor: pointer;
            line-height: 1.25;
        }

        .bp-picker-option:hover {
            border-color: #b9c7d8;
            background: #f4f8fc;
        }

        .bp-picker-option input {
            margin-top: .1rem;
            flex: 0 0 auto;
        }

        .bp-picker-option:has(input:checked) {
            border-color: rgba(37, 99, 235, .45);
            background: rgba(37, 99, 235, .08);
            color: #1e3a8a;
        }

        .bp-picker-option strong {
            margin-right: .2rem;
        }

        .bp-picker-empty {
            display: none;
            padding: 1rem;
            color: var(--bp-muted);
            text-align: center;
        }

        .bp-subaccount-picker.is-empty .bp-picker-empty {
            display: block;
        }

        @media (max-width: 1199.98px) {
            .bp-summary,
            .bp-profile-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .bp-picker-options {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .bp-toolbar,
            .bp-panel-header,
            .bp-department-row {
                grid-template-columns: 1fr;
                display: grid;
            }

            .bp-summary,
            .bp-profile-grid {
                grid-template-columns: 1fr;
            }

            .bp-search {
                max-width: none;
            }

            .bp-list-controls {
                grid-template-columns: 1fr;
                width: 100%;
            }

            .bp-picker-toolbar,
            .bp-picker-options {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $totalProfiles = $profiles->count();
        $activeProfiles = $profiles->where('is_active', true)->count();
        $totalUsers = $profiles->sum('users_count');
        $assignedSubaccounts = $profiles->sum('subaccounts_count');
        $totalDepartments = $departments->count();
        $activeDepartments = $departments->where('is_active', true)->count();
        $inactiveDepartments = $totalDepartments - $activeDepartments;
    @endphp

    <div class="budget-profile-page">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="ti ti-check me-1"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="ti ti-alert-triangle me-1"></i>Revisa los datos capturados.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bp-toolbar">
            <div>
                <h4 class="bp-title">
                    <span class="bp-title-icon"><i class="ti ti-wallet"></i></span>
                    Perfiles presupuestales
                </h4>
            </div>

            <ul class="nav bp-tabs" id="budgetProfileTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#profilesTab" type="button" role="tab">
                        <i class="ti ti-layout-list me-1"></i>Perfiles
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#departmentsTab" type="button" role="tab">
                        <i class="ti ti-building me-1"></i>Departamentos
                    </button>
                </li>
            </ul>
        </div>

        <div class="bp-summary">
            <div class="bp-metric">
                <div class="bp-metric-label">Perfiles</div>
                <div class="bp-metric-value">{{ $totalProfiles }}</div>
            </div>
            <div class="bp-metric">
                <div class="bp-metric-label">Activos</div>
                <div class="bp-metric-value">{{ $activeProfiles }}</div>
            </div>
            <div class="bp-metric">
                <div class="bp-metric-label">Usuarios asignados</div>
                <div class="bp-metric-value">{{ $totalUsers }}</div>
            </div>
            <div class="bp-metric">
                <div class="bp-metric-label">Subcuentas ligadas</div>
                <div class="bp-metric-value">{{ $assignedSubaccounts }}</div>
            </div>
        </div>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="profilesTab" role="tabpanel">
                <div class="bp-panel">
                    <div class="bp-panel-header">
                        <div>
                            <h5 class="bp-panel-title">Nuevo perfil</h5>
                        </div>
                        <span class="bp-chip bp-chip-success">
                            <i class="ti ti-circle-check"></i>Activo al crear
                        </span>
                    </div>
                    <div class="bp-panel-body">
                        <form method="POST" action="{{ route('budget-profiles.store') }}" class="row g-3 align-items-end">
                            @csrf
                            <input type="hidden" name="is_active" value="1">

                            <div class="col-lg-3 col-md-6">
                                <label class="form-label fw-semibold">Departamento</label>
                                <select name="department_id" class="form-select" required>
                                    <option value="">Selecciona un departamento</option>
                                    @foreach ($departments as $department)
                                        <option value="{{ $department->id }}" @selected((int) old('department_id') === $department->id)>
                                            {{ $department->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <label class="form-label fw-semibold">Nombre del perfil</label>
                                <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="Ej. Gerente de estacion" required>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <label class="form-label fw-semibold">Descripcion</label>
                                <input type="text" name="description" class="form-control" value="{{ old('description') }}" placeholder="Ej. Compra operativa mensual">
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Subcuentas</label>
                                @include('budget_profiles.partials.subaccount-picker', [
                                    'pickerId' => 'createSubaccountPicker',
                                    'subaccounts' => $subaccounts,
                                    'selectedSubaccountIds' => old('subaccount_ids', []),
                                ])
                            </div>

                            <div class="col-lg-12 d-grid d-lg-flex justify-content-lg-end">
                                <button type="submit" class="btn btn-primary" title="Crear perfil">
                                    <i class="ti ti-plus me-1"></i>Crear
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="bp-panel">
                    <div class="bp-panel-header">
                        <div>
                            <h5 class="bp-panel-title">Perfiles configurados</h5>
                        </div>
                        <div class="bp-list-controls">
                            <div class="input-group bp-search">
                                <span class="input-group-text"><i class="ti ti-search"></i></span>
                                <input type="search" class="form-control" id="profileSearch" placeholder="Buscar por perfil o subcuenta">
                            </div>

                            <select class="form-select" id="profileDepartmentFilter" aria-label="Filtrar por departamento">
                                <option value="">Todos los departamentos</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                                @endforeach
                            </select>

                            <select class="form-select" id="profilePageSize" aria-label="Perfiles por pagina">
                                <option value="8">8 por pagina</option>
                                <option value="12" selected>12 por pagina</option>
                                <option value="24">24 por pagina</option>
                                <option value="9999">Todos</option>
                            </select>
                        </div>
                    </div>
                    <div class="bp-panel-body">
                        @if ($profiles->isEmpty())
                            <div class="bp-empty">
                                <i class="ti ti-wallet fs-2 d-block mb-2"></i>
                                Sin perfiles presupuestales.
                            </div>
                        @else
                            <div class="bp-profile-grid" id="profileGrid">
                                @foreach ($profiles as $profile)
                                    @php
                                        $profileSearch = collect([
                                            $profile->name,
                                            $profile->description,
                                            $profile->department?->name,
                                            $profile->is_active ? 'activo' : 'inactivo',
                                        ])
                                            ->merge($profile->subaccounts->map(fn ($subaccount) => trim(($subaccount->account?->name ?? '').' '.$subaccount->code.' '.$subaccount->name)))
                                            ->filter()
                                            ->implode(' ');
                                    @endphp

                                    <article class="bp-profile-card js-profile-card" data-search="{{ Str::lower($profileSearch) }}" data-department-id="{{ $profile->department_id }}">
                                        <div class="bp-profile-head">
                                            <div>
                                                <h5 class="bp-profile-name">{{ $profile->name }}</h5>
                                                <div class="bp-profile-department">
                                                    <i class="ti ti-building me-1"></i>{{ $profile->department?->name ?? 'Sin departamento' }}
                                                </div>
                                            </div>
                                            <span class="bp-chip {{ $profile->is_active ? 'bp-chip-success' : 'bp-chip-muted' }}">
                                                <i class="ti {{ $profile->is_active ? 'ti-circle-check' : 'ti-circle-off' }}"></i>
                                                {{ $profile->is_active ? 'Activo' : 'Inactivo' }}
                                            </span>
                                        </div>

                                        <div class="bp-profile-body">
                                            <p class="bp-profile-description mb-3">
                                                {{ $profile->description ?: 'Sin descripcion.' }}
                                            </p>

                                            <div class="bp-chip-row mb-3">
                                                <span class="bp-chip"><i class="ti ti-users"></i>{{ $profile->users_count }} usuarios</span>
                                                <span class="bp-chip"><i class="ti ti-list-details"></i>{{ $profile->subaccounts_count }} subcuentas</span>
                                            </div>

                                            <div class="bp-chip-row">
                                                @forelse ($profile->subaccounts->take(4) as $subaccount)
                                                    <span class="bp-chip" title="{{ $subaccount->account?->name }}">
                                                        {{ $subaccount->account?->code }} / {{ $subaccount->code }} {{ Str::limit($subaccount->name, 28) }}
                                                    </span>
                                                @empty
                                                    <span class="bp-chip bp-chip-muted">Sin subcuentas</span>
                                                @endforelse

                                                @if ($profile->subaccounts_count > 4)
                                                    <span class="bp-chip bp-chip-muted">+{{ $profile->subaccounts_count - 4 }}</span>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="bp-profile-actions">
                                            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#editProfile{{ $profile->id }}" aria-expanded="false" aria-controls="editProfile{{ $profile->id }}">
                                                <i class="ti ti-edit me-1"></i>Editar
                                            </button>
                                        </div>

                                        <div class="collapse" id="editProfile{{ $profile->id }}">
                                            <div class="bp-edit-area">
                                                <form method="POST" action="{{ route('budget-profiles.update', $profile) }}" class="row g-3">
                                                    @csrf
                                                    @method('PUT')

                                                    <div class="col-md-6">
                                                        <label class="form-label fw-semibold">Departamento</label>
                                                        <select name="department_id" class="form-select form-select-sm" required>
                                                            @foreach ($departments as $department)
                                                                <option value="{{ $department->id }}" @selected($profile->department_id === $department->id)>
                                                                    {{ $department->name }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <label class="form-label fw-semibold">Estatus</label>
                                                        <select name="is_active" class="form-select form-select-sm">
                                                            <option value="1" @selected($profile->is_active)>Activo</option>
                                                            <option value="0" @selected(! $profile->is_active)>Inactivo</option>
                                                        </select>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <label class="form-label fw-semibold">Nombre</label>
                                                        <input type="text" name="name" class="form-control form-control-sm" value="{{ $profile->name }}" required>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <label class="form-label fw-semibold">Descripcion</label>
                                                        <input type="text" name="description" class="form-control form-control-sm" value="{{ $profile->description }}">
                                                    </div>

                                                    <div class="col-12">
                                                        <label class="form-label fw-semibold">Subcuentas</label>
                                                        @include('budget_profiles.partials.subaccount-picker', [
                                                            'pickerId' => 'editSubaccountPicker'.$profile->id,
                                                            'subaccounts' => $subaccounts,
                                                            'selectedSubaccountIds' => $profile->subaccounts->pluck('id'),
                                                        ])
                                                    </div>

                                                    <div class="col-12 text-end">
                                                        <button type="submit" class="btn btn-primary btn-sm">
                                                            <i class="ti ti-device-floppy me-1"></i>Guardar cambios
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>

                            <div class="bp-empty d-none" id="profileEmptySearch">
                                <i class="ti ti-search fs-2 d-block mb-2"></i>
                                Sin resultados.
                            </div>

                            <div class="bp-pagination" id="profilePagination">
                                <div class="bp-page-status" id="profilePageStatus"></div>
                                <div class="bp-page-actions">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="profilePrevPage">
                                        <i class="ti ti-chevron-left me-1"></i>Anterior
                                    </button>
                                    <span class="bp-page-status" id="profilePageNumber"></span>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="profileNextPage">
                                        Siguiente<i class="ti ti-chevron-right ms-1"></i>
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="departmentsTab" role="tabpanel">
                <div class="bp-panel">
                    <div class="bp-panel-header">
                        <div>
                            <h5 class="bp-panel-title">Nuevo departamento</h5>
                        </div>
                        <span class="bp-chip bp-chip-success">
                            <i class="ti ti-circle-check"></i>Activo al crear
                        </span>
                    </div>
                    <div class="bp-panel-body">
                        <form method="POST" action="{{ route('budget-profiles.departments.store') }}" class="row g-3 align-items-end">
                            @csrf
                            <input type="hidden" name="is_active" value="1">

                            <div class="col-lg-4 col-md-6">
                                <label class="form-label fw-semibold">Nombre</label>
                                <input type="text" name="name" class="form-control" value="{{ old('name') }}" maxlength="100" placeholder="Ej. Operaciones" required>
                            </div>

                            <div class="col-lg-2 col-md-6">
                                <label class="form-label fw-semibold">Abreviacion</label>
                                <input type="text" name="abbreviated" class="form-control text-uppercase" value="{{ old('abbreviated') }}" maxlength="10" placeholder="OPE" required>
                            </div>

                            <div class="col-lg-5 col-md-12">
                                <label class="form-label fw-semibold">Notas</label>
                                <input type="text" name="notes" class="form-control" value="{{ old('notes') }}" maxlength="255" placeholder="Contexto interno del departamento">
                            </div>

                            <div class="col-lg-1 d-grid">
                                <button type="submit" class="btn btn-primary" title="Crear departamento">
                                    <i class="ti ti-plus me-1"></i>Crear
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="bp-summary">
                    <div class="bp-metric">
                        <div class="bp-metric-label">Departamentos</div>
                        <div class="bp-metric-value">{{ $totalDepartments }}</div>
                    </div>
                    <div class="bp-metric">
                        <div class="bp-metric-label">Activos</div>
                        <div class="bp-metric-value">{{ $activeDepartments }}</div>
                    </div>
                    <div class="bp-metric">
                        <div class="bp-metric-label">Inactivos</div>
                        <div class="bp-metric-value">{{ $inactiveDepartments }}</div>
                    </div>
                    <div class="bp-metric">
                        <div class="bp-metric-label">Con perfiles</div>
                        <div class="bp-metric-value">{{ $departments->filter(fn ($department) => $department->budgetProfiles->isNotEmpty())->count() }}</div>
                    </div>
                </div>

                <div class="bp-panel">
                    <div class="bp-panel-header">
                        <div>
                            <h5 class="bp-panel-title">Departamentos configurados</h5>
                        </div>
                        <div class="bp-list-controls">
                            <div class="input-group bp-search">
                                <span class="input-group-text"><i class="ti ti-search"></i></span>
                                <input type="search" class="form-control" id="departmentSearch" placeholder="Buscar departamento, abreviacion o nota">
                            </div>

                            <select class="form-select" id="departmentStatusFilter" aria-label="Filtrar por estatus">
                                <option value="">Todos los estatus</option>
                                <option value="1">Activos</option>
                                <option value="0">Inactivos</option>
                            </select>

                            <select class="form-select" id="departmentPageSize" aria-label="Departamentos por pagina">
                                <option value="8">8 por pagina</option>
                                <option value="12" selected>12 por pagina</option>
                                <option value="24">24 por pagina</option>
                                <option value="9999">Todos</option>
                            </select>
                        </div>
                    </div>
                    <div class="bp-panel-body">
                        @if ($departments->isEmpty())
                            <div class="bp-empty">
                                <i class="ti ti-building fs-2 d-block mb-2"></i>
                                Sin departamentos.
                            </div>
                        @else
                            <div class="bp-profile-grid" id="departmentGrid">
                                @foreach ($departments as $department)
                                    @php
                                        $departmentSearch = collect([
                                            $department->name,
                                            $department->abbreviated,
                                            $department->notes,
                                            $department->is_active ? 'activo' : 'inactivo',
                                        ])->filter()->implode(' ');
                                    @endphp

                                    <article class="bp-profile-card js-department-card" data-search="{{ Str::lower($departmentSearch) }}" data-status="{{ $department->is_active ? '1' : '0' }}">
                                        <div class="bp-profile-head">
                                            <div>
                                                <h5 class="bp-profile-name">{{ $department->name }}</h5>
                                                <div class="bp-profile-department">
                                                    <i class="ti ti-tag me-1"></i>{{ $department->abbreviated }}
                                                </div>
                                            </div>
                                            <span class="bp-chip {{ $department->is_active ? 'bp-chip-success' : 'bp-chip-muted' }}">
                                                <i class="ti {{ $department->is_active ? 'ti-circle-check' : 'ti-circle-off' }}"></i>
                                                {{ $department->is_active ? 'Activo' : 'Inactivo' }}
                                            </span>
                                        </div>

                                        <div class="bp-profile-body">
                                            <p class="bp-profile-description mb-3">
                                                {{ $department->notes ?: 'Sin notas.' }}
                                            </p>

                                            <div class="bp-chip-row mb-3">
                                                <span class="bp-chip"><i class="ti ti-wallet"></i>{{ $department->budgetProfiles->count() }} perfiles</span>
                                                <span class="bp-chip"><i class="ti ti-list-details"></i>{{ $department->budgetProfiles->sum('subaccounts_count') }} subcuentas</span>
                                                <span class="bp-chip"><i class="ti ti-users"></i>{{ $department->budgetProfiles->sum('users_count') }} usuarios</span>
                                            </div>

                                            <div class="bp-chip-row">
                                                @forelse ($department->budgetProfiles->take(4) as $profile)
                                                    <span class="bp-chip">
                                                        <i class="ti ti-wallet"></i>
                                                        {{ $profile->name }}
                                                    </span>
                                                @empty
                                                    <span class="bp-chip bp-chip-muted">Sin perfiles</span>
                                                @endforelse

                                                @if ($department->budgetProfiles->count() > 4)
                                                    <span class="bp-chip bp-chip-muted">+{{ $department->budgetProfiles->count() - 4 }}</span>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="bp-profile-actions">
                                            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#editDepartment{{ $department->id }}" aria-expanded="false" aria-controls="editDepartment{{ $department->id }}">
                                                <i class="ti ti-edit me-1"></i>Editar
                                            </button>
                                        </div>

                                        <div class="collapse" id="editDepartment{{ $department->id }}">
                                            <div class="bp-edit-area">
                                                <form method="POST" action="{{ route('budget-profiles.departments.update', $department) }}" class="row g-3">
                                                    @csrf
                                                    @method('PUT')

                                                    <div class="col-md-6">
                                                        <label class="form-label fw-semibold">Nombre</label>
                                                        <input type="text" name="name" class="form-control form-control-sm" value="{{ $department->name }}" maxlength="100" required>
                                                    </div>

                                                    <div class="col-md-3">
                                                        <label class="form-label fw-semibold">Abreviacion</label>
                                                        <input type="text" name="abbreviated" class="form-control form-control-sm text-uppercase" value="{{ $department->abbreviated }}" maxlength="10" required>
                                                    </div>

                                                    <div class="col-md-3">
                                                        <label class="form-label fw-semibold">Estatus</label>
                                                        <select name="is_active" class="form-select form-select-sm">
                                                            <option value="1" @selected($department->is_active)>Activo</option>
                                                            <option value="0" @selected(! $department->is_active)>Inactivo</option>
                                                        </select>
                                                    </div>

                                                    <div class="col-12">
                                                        <label class="form-label fw-semibold">Notas</label>
                                                        <input type="text" name="notes" class="form-control form-control-sm" value="{{ $department->notes }}" maxlength="255">
                                                    </div>

                                                    <div class="col-12 text-end">
                                                        <button type="submit" class="btn btn-primary btn-sm">
                                                            <i class="ti ti-device-floppy me-1"></i>Guardar cambios
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>

                            <div class="bp-empty d-none" id="departmentEmptySearch">
                                <i class="ti ti-search fs-2 d-block mb-2"></i>
                                Sin resultados.
                            </div>

                            <div class="bp-pagination" id="departmentPagination">
                                <div class="bp-page-status" id="departmentPageStatus"></div>
                                <div class="bp-page-actions">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="departmentPrevPage">
                                        <i class="ti ti-chevron-left me-1"></i>Anterior
                                    </button>
                                    <span class="bp-page-status" id="departmentPageNumber"></span>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="departmentNextPage">
                                        Siguiente<i class="ti ti-chevron-right ms-1"></i>
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            const normalize = function (value) {
                return (value || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            };

            const $profileCards = $('.js-profile-card');
            const $profilePagination = $('#profilePagination');
            let profilePage = 1;

            const applyProfileFilters = function () {
                const term = normalize($('#profileSearch').val());
                const departmentId = $('#profileDepartmentFilter').val();
                const pageSize = parseInt($('#profilePageSize').val(), 10);
                const matchedCards = [];

                $profileCards.each(function () {
                    const $card = $(this);
                    const matchesSearch = normalize($card.data('search')).includes(term);
                    const matchesDepartment = ! departmentId || String($card.data('department-id')) === departmentId;
                    const matches = matchesSearch && matchesDepartment;

                    $card.toggleClass('d-none', ! matches);

                    if (matches) {
                        matchedCards.push($card);
                    }
                });

                const totalMatches = matchedCards.length;
                const totalPages = Math.max(1, Math.ceil(totalMatches / pageSize));
                profilePage = Math.min(profilePage, totalPages);

                matchedCards.forEach(function ($card, index) {
                    const isCurrentPage = index >= (profilePage - 1) * pageSize && index < profilePage * pageSize;
                    $card.toggleClass('d-none', ! isCurrentPage);
                });

                $('#profileEmptySearch').toggleClass('d-none', totalMatches !== 0);
                $profilePagination.toggleClass('d-none', totalMatches === 0);
                $('#profilePageStatus').text(totalMatches === 0 ? '' : totalMatches + ' perfiles encontrados');
                $('#profilePageNumber').text('Pagina ' + profilePage + ' de ' + totalPages);
                $('#profilePrevPage').prop('disabled', profilePage <= 1);
                $('#profileNextPage').prop('disabled', profilePage >= totalPages);
            };

            $('#profileSearch, #profileDepartmentFilter, #profilePageSize').on('input change', function () {
                profilePage = 1;
                applyProfileFilters();
            });

            $('#profilePrevPage').on('click', function () {
                if (profilePage > 1) {
                    profilePage--;
                    applyProfileFilters();
                }
            });

            $('#profileNextPage').on('click', function () {
                profilePage++;
                applyProfileFilters();
            });

            applyProfileFilters();

            const $departmentCards = $('.js-department-card');
            const $departmentPagination = $('#departmentPagination');
            let departmentPage = 1;

            const applyDepartmentFilters = function () {
                const term = normalize($('#departmentSearch').val());
                const status = $('#departmentStatusFilter').val();
                const pageSize = parseInt($('#departmentPageSize').val(), 10);
                const matchedCards = [];

                $departmentCards.each(function () {
                    const $card = $(this);
                    const matchesSearch = normalize($card.data('search')).includes(term);
                    const matchesStatus = ! status || String($card.data('status')) === status;
                    const matches = matchesSearch && matchesStatus;

                    $card.toggleClass('d-none', ! matches);

                    if (matches) {
                        matchedCards.push($card);
                    }
                });

                const totalMatches = matchedCards.length;
                const totalPages = Math.max(1, Math.ceil(totalMatches / pageSize));
                departmentPage = Math.min(departmentPage, totalPages);

                matchedCards.forEach(function ($card, index) {
                    const isCurrentPage = index >= (departmentPage - 1) * pageSize && index < departmentPage * pageSize;
                    $card.toggleClass('d-none', ! isCurrentPage);
                });

                $('#departmentEmptySearch').toggleClass('d-none', totalMatches !== 0);
                $departmentPagination.toggleClass('d-none', totalMatches === 0);
                $('#departmentPageStatus').text(totalMatches === 0 ? '' : totalMatches + ' departamentos encontrados');
                $('#departmentPageNumber').text('Pagina ' + departmentPage + ' de ' + totalPages);
                $('#departmentPrevPage').prop('disabled', departmentPage <= 1);
                $('#departmentNextPage').prop('disabled', departmentPage >= totalPages);
            };

            $('#departmentSearch, #departmentStatusFilter, #departmentPageSize').on('input change', function () {
                departmentPage = 1;
                applyDepartmentFilters();
            });

            $('#departmentPrevPage').on('click', function () {
                if (departmentPage > 1) {
                    departmentPage--;
                    applyDepartmentFilters();
                }
            });

            $('#departmentNextPage').on('click', function () {
                departmentPage++;
                applyDepartmentFilters();
            });

            applyDepartmentFilters();

            $('.js-subaccount-picker').each(function () {
                const $picker = $(this);
                const $count = $picker.find('.js-subaccount-count');

                const updateCount = function () {
                    $count.text($picker.find('input[type="checkbox"]:checked').length);
                };

                const updateSearch = function () {
                    const term = normalize($picker.find('.js-subaccount-search').val());
                    let visibleGroups = 0;

                    $picker.find('.js-subaccount-group').each(function () {
                        const $group = $(this);
                        const groupMatches = normalize($group.data('search')).includes(term);
                        let visibleOptions = 0;

                        $group.find('.js-subaccount-option').each(function () {
                            const $option = $(this);
                            const matches = groupMatches || normalize($option.data('search')).includes(term);
                            $option.toggle(matches);

                            if (matches) {
                                visibleOptions++;
                            }
                        });

                        $group.toggle(visibleOptions > 0);

                        if (visibleOptions > 0) {
                            visibleGroups++;
                        }
                    });

                    $picker.toggleClass('is-empty', visibleGroups === 0);
                };

                $picker.on('change', 'input[type="checkbox"]', updateCount);

                $picker.on('input', '.js-subaccount-search', updateSearch);

                $picker.on('click', '.js-select-group', function () {
                    $(this)
                        .closest('.js-subaccount-group')
                        .find('.js-subaccount-option:visible input[type="checkbox"]')
                        .prop('checked', true);

                    updateCount();
                });

                $picker.on('click', '.js-clear-group', function () {
                    $(this)
                        .closest('.js-subaccount-group')
                        .find('.js-subaccount-option:visible input[type="checkbox"]')
                        .prop('checked', false);

                    updateCount();
                });

                updateCount();
            });
        });
    </script>
@endpush
