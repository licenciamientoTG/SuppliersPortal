@extends('layouts.zircos')

@section('title', 'Cuentas y Subcuentas')
@section('page.title', 'Cuentas y Subcuentas')

@section('page.breadcrumbs')
    <li class="breadcrumb-item active">Cuentas y Subcuentas</li>
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
            <strong>Revisa la captura:</strong>
            <ul class="mb-0 mt-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <div>
                <h5 class="mb-0"><i class="ti ti-abacus me-1"></i>Catálogo de cuentas y subcuentas</h5>
                <small class="text-muted">Los números de cuenta y subcuenta son la clasificación presupuestal ligada a productos y partidas.</small>
            </div>
            <form method="POST" action="{{ route('accounts.sync') }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-primary">
                    <i class="ti ti-refresh me-1"></i>Sincronizar legacy
                </button>
            </form>
        </div>
        <div class="card-body">
            <div class="accordion" id="accountsAccordion">
                @forelse ($accounts as $account)
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="account-heading-{{ $account->id }}">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#account-collapse-{{ $account->id }}" aria-expanded="false"
                                    aria-controls="account-collapse-{{ $account->id }}">
                                <span class="fw-semibold me-2">{{ $account->code }}</span>
                                <span>{{ $account->name }}</span>
                                <span class="badge bg-light text-dark border ms-3">{{ $account->subaccounts_count }} subcuentas</span>
                                <span class="badge bg-light text-dark border ms-1">{{ $account->product_services_count }} productos</span>
                                @unless ($account->is_active)
                                    <span class="badge bg-secondary ms-1">Inactiva</span>
                                @endunless
                                @if ($account->is_fixed_asset)
                                    <span class="badge bg-info ms-1">Activo fijo</span>
                                @endif
                            </button>
                        </h2>
                        <div id="account-collapse-{{ $account->id }}" class="accordion-collapse collapse"
                             aria-labelledby="account-heading-{{ $account->id }}" data-bs-parent="#accountsAccordion">
                            <div class="accordion-body">
                                <form method="POST" action="{{ route('accounts.update', $account) }}" class="row g-2 mb-3">
                                    @csrf
                                    @method('PUT')
                                    <div class="col-md-2">
                                        <label class="form-label small">Número de cuenta</label>
                                        <input type="text" name="code" class="form-control form-control-sm" value="{{ old('code', $account->code) }}" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small">Cuenta</label>
                                        <input type="text" name="name" class="form-control form-control-sm" value="{{ old('name', $account->name) }}" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small">Categoría</label>
                                        <input type="text" name="account_category" class="form-control form-control-sm" value="{{ old('account_category', $account->account_category) }}">
                                    </div>
                                    <div class="col-md-3 d-flex align-items-end gap-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_fixed_asset" value="1" id="account-fixed-{{ $account->id }}" @checked(old('is_fixed_asset', $account->is_fixed_asset))>
                                            <label class="form-check-label small" for="account-fixed-{{ $account->id }}">Activo fijo</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="account-active-{{ $account->id }}" @checked(old('is_active', $account->is_active))>
                                            <label class="form-check-label small" for="account-active-{{ $account->id }}">Activa</label>
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-primary ms-auto">
                                            <i class="ti ti-device-floppy"></i>
                                        </button>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small">Descripción</label>
                                        <textarea name="description" rows="2" class="form-control form-control-sm">{{ old('description', $account->description) }}</textarea>
                                    </div>
                                </form>

                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Número</th>
                                                <th>Subcuenta</th>
                                                <th>Categoría</th>
                                                <th class="text-center">Productos</th>
                                                <th class="text-center">Perfiles</th>
                                                <th class="text-center">Deptos.</th>
                                                <th class="text-center">Usuarios</th>
                                                <th class="text-center">Fijo</th>
                                                <th class="text-center">Activa</th>
                                                <th class="text-end"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($account->subaccounts as $subaccount)
                                                <tr>
                                                    <form method="POST" action="{{ route('subaccounts.update', $subaccount) }}">
                                                        @csrf
                                                        @method('PUT')
                                                        <td style="min-width: 130px;">
                                                            <input type="text" name="code" class="form-control form-control-sm" value="{{ $subaccount->code }}" required>
                                                        </td>
                                                        <td style="min-width: 260px;">
                                                            <input type="text" name="name" class="form-control form-control-sm" value="{{ $subaccount->name }}" required>
                                                            @if ($subaccount->legacy_budget_cedula_id)
                                                                <small class="text-muted">Legacy cédula #{{ $subaccount->legacy_budget_cedula_id }}</small>
                                                            @endif
                                                        </td>
                                                        <td style="min-width: 180px;">
                                                            <input type="text" name="subaccount_category" class="form-control form-control-sm" value="{{ $subaccount->subaccount_category }}">
                                                        </td>
                                                        <td class="text-center">{{ $subaccount->product_services_count }}</td>
                                                        <td class="text-center">{{ $subaccount->budget_profiles_count }}</td>
                                                        <td class="text-center">{{ $subaccount->departments_count }}</td>
                                                        <td class="text-center">{{ $subaccount->users_count }}</td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="checkbox" name="is_fixed_asset" value="1" @checked($subaccount->is_fixed_asset)>
                                                        </td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked($subaccount->is_active)>
                                                        </td>
                                                        <td class="text-end">
                                                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                                                <i class="ti ti-device-floppy"></i>
                                                            </button>
                                                        </td>
                                                    </form>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-5">
                        <i class="ti ti-abacus fs-1 d-block mb-2"></i>
                        No hay cuentas registradas.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
