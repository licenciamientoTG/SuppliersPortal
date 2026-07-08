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
                    <h5 class="mb-0"><i class="ti ti-wallet me-1"></i>Perfiles presupuestales</h5>
                    <small class="text-muted">Los departamentos contienen perfiles; cada perfil define las subcuentas disponibles.</small>
                </div>
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
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#departmentsTab" type="button">
                        Departamentos
                    </button>
                </li>
            </ul>

            <div class="tab-content pt-3">
                <div class="tab-pane fade show active" id="profilesTab" role="tabpanel">
                    <form method="POST" action="{{ route('budget-profiles.store') }}" class="row g-2 align-items-end mb-3">
                        @csrf
                        <input type="hidden" name="is_active" value="1">
                        <div class="col-md-2">
                            <label class="form-label">Clave</label>
                            <input type="text" name="key" class="form-control" placeholder="compras_jefe" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Descripcion</label>
                            <input type="text" name="description" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Subcuentas</label>
                            <select name="subaccount_ids[]" class="form-select js-budget-select" multiple>
                                @foreach ($subaccounts as $subaccount)
                                    <option value="{{ $subaccount->id }}">
                                        {{ $subaccount->account?->code }} - {{ $subaccount->account?->name }} / {{ $subaccount->code }} {{ $subaccount->name }}
                                    </option>
                                @endforeach
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
                                        <td style="min-width: 420px;">
                                            <select form="profileForm{{ $profile->id }}" name="subaccount_ids[]" class="form-select form-select-sm js-budget-select" multiple>
                                                @foreach ($subaccounts as $subaccount)
                                                    <option value="{{ $subaccount->id }}" @selected($profile->subaccounts->contains('id', $subaccount->id))>
                                                        {{ $subaccount->account?->code }} - {{ $subaccount->account?->name }} / {{ $subaccount->code }} {{ $subaccount->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">{{ $profile->departments_count }} departamentos</span>
                                            <span class="badge bg-secondary">{{ $profile->subaccounts_count }} subcuentas</span>
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

                <div class="tab-pane fade" id="departmentsTab" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered align-middle js-data-table">
                            <thead class="table-light">
                                <tr>
                                    <th>Departamento</th>
                                    <th>Perfiles</th>
                                    <th>Subcuentas efectivas</th>
                                    <th class="text-end">Guardar</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($departments as $department)
                                    @php
                                        $effectiveSubaccounts = $department->budgetProfiles
                                            ->where('is_active', true)
                                            ->flatMap(fn ($profile) => $profile->subaccounts)
                                            ->unique('id')
                                            ->sortBy('name');
                                    @endphp
                                    <tr>
                                        <td style="min-width: 220px;">
                                            <div class="fw-semibold">{{ $department->name }}</div>
                                            <span class="badge {{ $department->is_active ? 'bg-success' : 'bg-secondary' }}">
                                                {{ $department->is_active ? 'Activo' : 'Inactivo' }}
                                            </span>
                                        </td>
                                        <td style="min-width: 360px;">
                                            <form id="departmentForm{{ $department->id }}" method="POST" action="{{ route('budget-profiles.departments.update', $department) }}">
                                                @csrf
                                                @method('PATCH')
                                                <select name="budget_profile_ids[]" class="form-select form-select-sm js-profile-select" multiple>
                                                    @foreach ($profiles as $profile)
                                                        <option value="{{ $profile->id }}" @selected($department->budgetProfiles->contains('id', $profile->id))>
                                                            {{ $profile->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </form>
                                        </td>
                                        <td style="min-width: 420px;">
                                            @forelse ($effectiveSubaccounts->take(10) as $subaccount)
                                                <span class="badge bg-light text-dark border mb-1">
                                                    {{ $subaccount->account?->code }} / {{ $subaccount->code }} {{ $subaccount->name }}
                                                </span>
                                            @empty
                                                <span class="text-muted">Sin subcuentas asignadas</span>
                                            @endforelse

                                            @if ($effectiveSubaccounts->count() > 10)
                                                <span class="badge bg-secondary mb-1">+{{ $effectiveSubaccounts->count() - 10 }}</span>
                                            @endif
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

            $('.js-profile-select').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Selecciona perfiles',
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
