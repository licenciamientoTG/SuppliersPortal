@extends('layouts.zircos')

@section('title', 'Cuentas y Subcuentas')
@section('page.title', 'Cuentas y Subcuentas')

@section('page.breadcrumbs')
    <li class="breadcrumb-item active">Cuentas y Subcuentas</li>
@endsection

@section('content')
    @php
        $productsPayload = function ($products): string {
            return $products->map(fn ($product) => [
                'code' => $product->code,
                'name' => $product->short_name ?: Str::limit($product->technical_description, 120),
                'type' => $product->product_type,
                'status' => $product->status,
                'active' => (bool) $product->is_active,
            ])->values()->toJson(JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        };
    @endphp

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
        <div class="card-header">
            <div>
                <h5 class="mb-0"><i class="ti ti-abacus me-1"></i>Catálogo de cuentas y subcuentas</h5>
                <small class="text-muted">Los números de cuenta y subcuenta son la clasificación presupuestal ligada a productos y partidas.</small>
            </div>
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
                                <span class="badge bg-light text-dark border ms-1 js-products-modal"
                                      role="button"
                                      data-title="Productos de {{ $account->code }} - {{ $account->name }}"
                                      data-products='{{ $productsPayload($account->productServices) }}'>
                                    {{ $account->product_services_count }} productos
                                </span>
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
                                    <div class="col-md-5">
                                        <label class="form-label small">Cuenta</label>
                                        <input type="text" name="name" class="form-control form-control-sm" value="{{ old('name', $account->name) }}" required>
                                    </div>
                                    <div class="col-md-5 d-flex align-items-end gap-3">
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
                                                        <td class="text-center">
                                                            <button type="button"
                                                                    class="btn btn-sm btn-link p-0 js-products-modal"
                                                                    data-title="Productos de {{ $subaccount->code }} - {{ $subaccount->name }}"
                                                                    data-products='{{ $productsPayload($subaccount->productServices) }}'>
                                                                {{ $subaccount->product_services_count }}
                                                            </button>
                                                        </td>
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

    <div class="modal fade" id="accountProductsModal" tabindex="-1" aria-labelledby="accountProductsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="accountProductsModalLabel">Productos asociados</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="input-group input-group-sm mb-3">
                        <span class="input-group-text"><i class="ti ti-search"></i></span>
                        <input type="search" class="form-control" id="accountProductsFilter" placeholder="Filtrar productos...">
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Código</th>
                                    <th>Producto</th>
                                    <th>Tipo</th>
                                    <th>Estatus</th>
                                </tr>
                            </thead>
                            <tbody id="accountProductsTable"></tbody>
                        </table>
                    </div>
                    <div class="text-center text-muted py-4 d-none" id="accountProductsEmpty">
                        No hay productos para mostrar.
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function() {
            let currentProducts = [];
            const modal = new bootstrap.Modal(document.getElementById('accountProductsModal'));

            function renderProducts() {
                const term = $('#accountProductsFilter').val().toLowerCase().trim();
                const rows = currentProducts.filter(function(product) {
                    const haystack = `${product.code} ${product.name} ${product.type} ${product.status}`.toLowerCase();
                    return !term || haystack.includes(term);
                });

                $('#accountProductsTable').empty();
                $('#accountProductsEmpty').toggleClass('d-none', rows.length > 0);

                rows.forEach(function(product) {
                    const badge = product.active
                        ? '<span class="badge bg-success">Activo</span>'
                        : '<span class="badge bg-secondary">Inactivo</span>';

                    $('#accountProductsTable').append(`
                        <tr>
                            <td><code>${product.code || ''}</code></td>
                            <td>${product.name || ''}</td>
                            <td>${product.type || ''}</td>
                            <td>${badge}</td>
                        </tr>
                    `);
                });
            }

            $(document).on('click', '.js-products-modal', function(event) {
                event.preventDefault();
                event.stopPropagation();

                currentProducts = $(this).data('products') || [];
                $('#accountProductsModalLabel').text($(this).data('title') || 'Productos asociados');
                $('#accountProductsFilter').val('');
                renderProducts();
                modal.show();
            });

            $('#accountProductsFilter').on('input', renderProducts);
        });
    </script>
@endpush
