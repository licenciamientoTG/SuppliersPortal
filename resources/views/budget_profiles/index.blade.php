@extends('layouts.zircos')

@section('title', 'Perfiles presupuestales')
@section('page.title', 'Perfiles presupuestales')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Perfiles presupuestales</li>
@endsection

@section('content')
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
        </div>
    @endif

    <div class="card">
        <div class="card-header bg-white">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0"><i class="ti ti-wallet me-1"></i>Alcance presupuestal</h5>
                    <small class="text-muted">Administra perfiles, homologaciones y accesos por subcuenta.</small>
                </div>
                <form method="POST" action="{{ route('budget-profiles.sync-positions') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary btn-sm">
                        <i class="ti ti-refresh me-1"></i>Sincronizar puestos
                    </button>
                </form>
            </div>
        </div>

        <div class="card-body">
            <ul class="nav nav-tabs" id="budgetProfileTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#profilesTab" type="button">
                        Perfiles
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#positionsTab" type="button">
                        Puestos
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#departmentsTab" type="button">
                        Departamentos
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#usersTab" type="button">
                        Usuarios
                    </button>
                </li>
            </ul>

            <div class="tab-content pt-3">
                <div class="tab-pane fade show active" id="profilesTab" role="tabpanel">
                    <form method="POST" action="{{ route('budget-profiles.store') }}" class="row g-2 align-items-end mb-3">
                        @csrf
                        <div class="col-md-2">
                            <label class="form-label">Clave</label>
                            <input type="text" name="key" class="form-control" placeholder="finance_ops" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Descripcion</label>
                            <input type="text" name="description" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Estatus</label>
                            <select name="is_active" class="form-select">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                        <div class="col-md-1 d-grid">
                            <button type="submit" class="btn btn-primary" title="Crear perfil">
                                <i class="ti ti-plus"></i>
                            </button>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-hover table-bordered align-middle js-data-table">
                            <thead class="table-light">
                                <tr>
                                    <th>Perfil</th>
                                    <th>Clave</th>
                                    <th>Subcuentas</th>
                                    <th>Uso</th>
                                    <th>Estatus</th>
                                    <th class="text-end">Guardar</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($profiles as $profile)
                                    <tr>
                                        <td style="min-width: 260px;">
                                            <form id="profileForm{{ $profile->id }}" method="POST" action="{{ route('budget-profiles.update', $profile) }}">
                                                @csrf
                                                @method('PUT')
                                                <input type="text" name="name" class="form-control form-control-sm mb-1" value="{{ $profile->name }}" required>
                                                <textarea name="description" class="form-control form-control-sm" rows="2">{{ $profile->description }}</textarea>
                                            </form>
                                        </td>
                                        <td style="min-width: 170px;">
                                            <input form="profileForm{{ $profile->id }}" type="text" name="key" class="form-control form-control-sm" value="{{ $profile->key }}" required>
                                        </td>
                                        <td style="min-width: 360px;">
                                            <select form="profileForm{{ $profile->id }}" name="subaccount_ids[]" class="form-select form-select-sm js-budget-select" multiple>
                                                @foreach ($subaccounts as $subaccount)
                                                    <option value="{{ $subaccount->id }}" @selected($profile->subaccounts->contains('id', $subaccount->id))>
                                                        {{ $subaccount->account?->code }} - {{ $subaccount->account?->name }} / {{ $subaccount->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">{{ $profile->users_count }} usuarios</span>
                                            <span class="badge bg-secondary">{{ $profile->employee_positions_count }} puestos</span>
                                        </td>
                                        <td>
                                            <select form="profileForm{{ $profile->id }}" name="is_active" class="form-select form-select-sm">
                                                <option value="1" @selected($profile->is_active)>Activo</option>
                                                <option value="0" @selected(! $profile->is_active)>Inactivo</option>
                                            </select>
                                        </td>
                                        <td class="text-end">
                                            <button form="profileForm{{ $profile->id }}" type="submit" class="btn btn-sm btn-primary" title="Guardar perfil">
                                                <i class="ti ti-device-floppy"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade" id="positionsTab" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered align-middle js-data-table">
                            <thead class="table-light">
                                <tr>
                                    <th>Puesto detectado</th>
                                    <th>Normalizado</th>
                                    <th>Empleados</th>
                                    <th>Perfil</th>
                                    <th>Excluido</th>
                                    <th class="text-end">Guardar</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($positions as $position)
                                    <tr>
                                        <td>{{ $position->raw_job_title }}</td>
                                        <td><code>{{ $position->normalized_job_title }}</code></td>
                                        <td>{{ $position->employees_count }}</td>
                                        <td style="min-width: 260px;">
                                            <form id="positionForm{{ $position->id }}" method="POST" action="{{ route('budget-profiles.positions.update', $position) }}">
                                                @csrf
                                                @method('PATCH')
                                                <select name="budget_profile_id" class="form-select form-select-sm">
                                                    <option value="">Sin perfil</option>
                                                    @foreach ($profiles as $profile)
                                                        <option value="{{ $profile->id }}" @selected($position->budget_profile_id === $profile->id)>
                                                            {{ $profile->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </form>
                                        </td>
                                        <td class="text-center">
                                            <input form="positionForm{{ $position->id }}" type="hidden" name="is_excluded" value="0">
                                            <input form="positionForm{{ $position->id }}" type="checkbox" name="is_excluded" value="1" class="form-check-input" @checked($position->is_excluded)>
                                        </td>
                                        <td class="text-end">
                                            <button form="positionForm{{ $position->id }}" type="submit" class="btn btn-sm btn-primary" title="Guardar homologacion">
                                                <i class="ti ti-device-floppy"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade" id="departmentsTab" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered align-middle js-data-table">
                            <thead class="table-light">
                                <tr>
                                    <th>Departamento</th>
                                    <th>Activo</th>
                                    <th>Subcuentas permitidas</th>
                                    <th class="text-end">Guardar</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($departments as $department)
                                    <tr>
                                        <td>{{ $department->name }}</td>
                                        <td>
                                            <span class="badge {{ $department->is_active ? 'bg-success' : 'bg-secondary' }}">
                                                {{ $department->is_active ? 'Activo' : 'Inactivo' }}
                                            </span>
                                        </td>
                                        <td style="min-width: 420px;">
                                            <form id="departmentForm{{ $department->id }}" method="POST" action="{{ route('budget-profiles.departments.update', $department) }}">
                                                @csrf
                                                @method('PATCH')
                                                <select name="subaccount_ids[]" class="form-select form-select-sm js-budget-select" multiple>
                                                    @foreach ($subaccounts as $subaccount)
                                                        <option value="{{ $subaccount->id }}" @selected($department->subaccounts->contains('id', $subaccount->id))>
                                                            {{ $subaccount->account?->code }} - {{ $subaccount->account?->name }} / {{ $subaccount->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </form>
                                        </td>
                                        <td class="text-end">
                                            <button form="departmentForm{{ $department->id }}" type="submit" class="btn btn-sm btn-primary" title="Guardar departamento">
                                                <i class="ti ti-device-floppy"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade" id="usersTab" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered align-middle js-data-table">
                            <thead class="table-light">
                                <tr>
                                    <th>Usuario</th>
                                    <th>Puesto</th>
                                    <th>Departamento</th>
                                    <th>Perfil</th>
                                    <th>Subcuentas directas</th>
                                    <th class="text-end">Guardar</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($users as $user)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $user->name }}</div>
                                            <small class="text-muted">{{ $user->email }}</small>
                                        </td>
                                        <td>{{ $user->employee?->job_title ?? $user->job_title ?? 'Sin puesto' }}</td>
                                        <td style="min-width: 220px;">
                                            <form id="userForm{{ $user->id }}" method="POST" action="{{ route('budget-profiles.users.update', $user) }}">
                                                @csrf
                                                @method('PATCH')
                                                <select name="department_id" class="form-select form-select-sm">
                                                    <option value="">Sin departamento</option>
                                                    @foreach ($departments as $department)
                                                        <option value="{{ $department->id }}" @selected($user->department_id === $department->id)>
                                                            {{ $department->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </form>
                                        </td>
                                        <td style="min-width: 240px;">
                                            <select form="userForm{{ $user->id }}" name="budget_profile_id" class="form-select form-select-sm">
                                                <option value="">Sin perfil</option>
                                                @foreach ($profiles as $profile)
                                                    <option value="{{ $profile->id }}" @selected($user->budget_profile_id === $profile->id)>
                                                        {{ $profile->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td style="min-width: 380px;">
                                            <select form="userForm{{ $user->id }}" name="subaccount_ids[]" class="form-select form-select-sm js-budget-select" multiple>
                                                @foreach ($subaccounts as $subaccount)
                                                    <option value="{{ $subaccount->id }}" @selected($user->subaccounts->contains('id', $subaccount->id))>
                                                        {{ $subaccount->account?->code }} - {{ $subaccount->account?->name }} / {{ $subaccount->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="text-end">
                                            <button form="userForm{{ $user->id }}" type="submit" class="btn btn-sm btn-primary" title="Guardar usuario">
                                                <i class="ti ti-device-floppy"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            $('.js-budget-select').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Selecciona subcuentas',
                closeOnSelect: false
            });

            $('.js-data-table').DataTable({
                pageLength: 25,
                order: [],
                language: {
                    url: "{{ asset('assets/vendor/datatables.net/es-MX.json') }}"
                }
            });
        });
    </script>
@endpush
