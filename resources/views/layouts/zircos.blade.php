<!DOCTYPE html>
<html lang="es" data-sidenav-size="default">

    <head>
        <meta charset="utf-8" />
        <title>@yield('title', config('app.name'))</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <!-- App favicon -->
        <link rel="shortcut icon" href="{{ asset('images/logos/Logo.png') }}">

        <!-- Sweet Alert css-->
        <link href="{{ asset('assets/vendor/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />

        <!-- Theme Config Js -->
        <script src="{{ asset('assets/js/config.js') }}"></script>

        <!-- Preferencias del tema (sidebar size, modo claro/oscuro) -->
        <script src="{{ asset('js/sidebar-settings.js') }}"></script>

        <!-- Vendor css -->
        <link href="{{ asset('assets/css/vendor.min.css') }}" rel="stylesheet" type="text/css" />

        <!-- App css -->
        <link href="{{ asset('assets/css/app.min.css') }}" rel="stylesheet" type="text/css" id="app-style" />

        <!-- Icons css -->
        <link href="{{ asset('assets/css/icons.min.css') }}" rel="stylesheet" type="text/css" />

        <!-- Datatables css -->
        <link href="{{ asset('assets/vendor/datatables.net-bs5/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet"
            type="text/css" />
        <link href="{{ asset('assets/vendor/datatables.net-responsive-bs5/css/responsive.bootstrap5.min.css') }}"
            rel="stylesheet" type="text/css" />
        <link href="{{ asset('assets/vendor/datatables.net-fixedcolumns-bs5/css/fixedColumns.bootstrap5.min.css') }}"
            rel="stylesheet" type="text/css" />
        <link href="{{ asset('assets/vendor/datatables.net-fixedheader-bs5/css/fixedHeader.bootstrap5.min.css') }}"
            rel="stylesheet" type="text/css" />
        <link href="{{ asset('assets/vendor/datatables.net-buttons-bs5/css/buttons.bootstrap5.min.css') }}"
            rel="stylesheet" type="text/css" />
        <link href="{{ asset('assets/vendor/datatables.net-select-bs5/css/select.bootstrap5.min.css') }}"
            rel="stylesheet" type="text/css" />

        {{-- Logo del sidebar ─────────────────────────────────────────────── --}}
        <style>
            /*
             * Anula la restricción del template (--ct-logo-lg-height: 24px) y
             * hace que el logo se ajuste automáticamente al ancho del sidebar,
             * tanto en modo Normal (235px) como en Compacto (160px).
             */
            .sidenav-logo-img {
                display: block;
                width: 100%;
                height: auto !important;        /* anula height: var(--ct-logo-lg-height) */
                max-width: 100%;
                padding: 10px 16px;
                box-sizing: border-box;
                object-fit: contain;
            }

            /* En modo compacto el padding lateral es menor */
            html[data-sidenav-size=compact] .sidenav-menu .logo {
                padding: 0 4px;
            }
            html[data-sidenav-size=compact] .sidenav-logo-img {
                padding: 8px 10px;
            }

            /*
             * Opciones de color: solo aplican en modo claro.
             * En modo oscuro se deshabilitan visualmente.
             */
            html[data-bs-theme=dark] .light-only-section {
                opacity: 0.38;
                pointer-events: none;
                user-select: none;
            }
        </style>

        {{-- CSS Personalizado --}}
        <style>
            .bg-gradient-primary {
                background: linear-gradient(135deg, #007bff 0%, #0056b3 100%) !important;
            }

            .info-icon,
            .security-icon {
                width: 36px;
                height: 36px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 8px;
                font-size: 1.1rem;
            }

            .info-item,
            .security-item {
                transition: all 0.2s ease;
            }

            .info-item:hover,
            .security-item:hover {
                background-color: #f8f9fa !important;
                border-radius: 8px;
                padding: 0.5rem;
                margin: -0.5rem;
            }

            .modal-content {
                border-radius: 15px;
            }

            /* overflow:hidden solo en modales NO scrollables (para que border-radius recorte bien los hijos) */
            .modal-dialog:not(.modal-dialog-scrollable) .modal-content {
                overflow: hidden;
            }

            /* Ancho por defecto solo cuando no hay clase de tamaño explícita de Bootstrap */
            .modal-dialog:not(.modal-sm):not(.modal-lg):not(.modal-xl):not(.modal-fullscreen) {
                max-width: 600px;
            }
        </style>

        {{-- Estilos adicionales para SweetAlert --}}
        <style>
            .swal-wide {
                width: 500px !important;
            }

            .swal2-html-container {
                overflow: visible !important;
            }

            .input-group-text {
                border-color: #ced4da;
            }

            .form-control:focus {
                border-color: #007bff;
                box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
            }

            .swal2-input {
                margin: 0 !important;
            }

            .swal2-confirm {
                margin: .4rem;
            }

            .alert-info {
                background-color: #d1ecf1;
                border-color: #b7daed;
                color: #0c5460;
                padding: 0.75rem;
                border-radius: 0.375rem;
                border: 1px solid transparent;
            }
        </style>
        
        <style>
            div.dataTables_processing {
                position: fixed !important;
                top: 50% !important;
                left: 50% !important;
                transform: translate(-50%, -50%) !important;
                width: auto !important;
                margin: 0 !important;
                padding: 20px !important;
                background: white !important;
                border: 1px solid #ddd !important;
                border-radius: 8px !important;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1) !important;
            }

            div.dataTables_processing::before {
                content: '';
                display: inline-block;
                width: 2rem;
                height: 2rem;
                border: 0.25rem solid #0d6efd;
                border-right-color: transparent;
                border-radius: 50%;
                animation: spinner-border 0.75s linear infinite;
                margin-right: 10px;
                vertical-align: middle;
            }

            @keyframes spinner-border {
                to { transform: rotate(360deg); }
            }
        </style>

        
        @stack('styles')
        @livewireStyles
    </head>

    <body>
        <!-- Aquí va tu navbar/sidebar -->
        <div class="wrapper">

            @include('layouts.partials.sidebar')
            @include('layouts.partials.navbar')
            <div class="page-content">
                <div class="page-container">
                    <div class="page-title-head d-flex align-items-center gap-2">
                        <div class="flex-grow-1">
                            <h4 class="fs-17 mb-0">@yield('page.title', 'Dashboard')</h4>
                        </div>

                        <div class="text-end">
                            <ol class="breadcrumb fs-13 m-0 py-0">
                                {{-- Permite sobreescribir las migas desde la vista hija --}}
                                @hasSection('page.breadcrumbs')
                                    @yield('page.breadcrumbs')
                                @else
                                    <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
                                    <li class="breadcrumb-item active">@yield('page.title', 'Dashboard')</li>
                                @endif
                            </ol>
                        </div>
                    </div>
                    @if (session('status'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('status') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"
                                aria-label="Cerrar"></button>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <strong>Ocurrieron errores:</strong>
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"
                                aria-label="Cerrar"></button>
                        </div>
                    @endif
                    @yield('content')
                    @include('layouts.partials.flash')
                </div>
                @include('layouts.partials.footer')
            </div>
        </div>

        {{-- Modal de Perfil de Usuario --}}
        <div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true"
            data-bs-focus="false">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    {{-- Header del Modal --}}
                    <div class="modal-header bg-gradient-primary position-relative overflow-hidden border-0 text-white">
                        <div class="position-absolute end-0 top-0 opacity-25">
                            <i class="ti ti-user-circle" style="font-size: 6rem;"></i>
                        </div>
                        <div class="d-flex align-items-center position-relative">
                            <div class="me-3">
                                @if (auth()->user()->avatar)
                                    <img src="{{ asset('storage/' . auth()->user()->avatar) }}"
                                        alt="{{ auth()->user()->full_name }}"
                                        class="rounded-circle border-3 border border-white"
                                        style="width: 60px; height: 60px; object-fit: cover;">
                                @else
                                    <div class="rounded-circle d-flex align-items-center justify-content-center border-3 border border-white bg-white bg-opacity-25"
                                        style="width: 60px; height: 60px;">
                                        <i class="ti ti-user text-white" style="font-size: 1.5rem;"></i>
                                    </div>
                                @endif
                            </div>
                            <div>
                                <h5 class="modal-title mb-0" id="profileModalLabel">Mi Perfil</h5>
                                <p class="mb-0 opacity-75">{{ auth()->user()->full_name }}</p>
                            </div>
                        </div>
                        <button type="button" class="btn-close btn-close-white position-relative"
                            data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    {{-- Contenido del Modal --}}
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            {{-- Información Personal --}}
                            <div class="col-12">
                                <h6 class="text-muted text-uppercase fw-semibold mb-3"
                                    style="font-size: 0.75rem; letter-spacing: 0.5px;">
                                    <i class="ti ti-info-circle me-1"></i>Información Personal
                                </h6>
                            </div>

                            <div class="col-md-6">
                                <div class="info-item">
                                    <div class="d-flex align-items-start">
                                        <div class="info-icon bg-primary text-primary me-3 bg-opacity-10">
                                            <i class="ti ti-user"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <label class="form-label text-muted mb-1">Nombre completo</label>
                                            <p class="fw-semibold mb-0">
                                                {{ auth()->user()->full_name ?: auth()->user()->name }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="info-item">
                                    <div class="d-flex align-items-start">
                                        <div class="info-icon bg-info text-info me-3 bg-opacity-10">
                                            <i class="ti ti-mail"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <label class="form-label text-muted mb-1">Correo electrónico</label>
                                            <p class="fw-semibold mb-0">{{ auth()->user()->email }}</p>
                                            <div class="mt-1">
                                                @if (auth()->user()->email_verified_at)
                                                    <span
                                                        class="badge bg-success text-success border-success text-light border border-opacity-25 bg-opacity-15">
                                                        <i class="ti ti-check me-1"></i>Verificado
                                                    </span>
                                                @else
                                                    <span
                                                        class="badge bg-warning text-warning border-warning text-light border border-opacity-25 bg-opacity-15">
                                                        <i class="ti ti-alert-circle me-1"></i>Sin verificar
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @if (auth()->user()->phone)
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <div class="d-flex align-items-start">
                                            <div class="info-icon bg-success text-success me-3 bg-opacity-10">
                                                <i class="ti ti-phone"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <label class="form-label text-muted mb-1">Teléfono</label>
                                                <p class="fw-semibold mb-0">{{ auth()->user()->phone }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if (auth()->user()->job_title)
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <div class="d-flex align-items-start">
                                            <div class="info-icon bg-warning text-warning me-3 bg-opacity-10">
                                                <i class="ti ti-briefcase"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <label class="form-label text-muted mb-1">Puesto</label>
                                                <p class="fw-semibold mb-0">{{ auth()->user()->job_title }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            {{-- Información de Seguridad --}}
                            <div class="col-12 mt-4">
                                <h6 class="text-muted text-uppercase fw-semibold mb-3"
                                    style="font-size: 0.75rem; letter-spacing: 0.5px;">
                                    <i class="ti ti-shield-lock me-1"></i>Información de Cuenta
                                </h6>
                            </div>

                            {{-- Estado de la cuenta --}}
                            <div class="col-12">
                                <div class="security-item rounded-3 border p-3">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center">
                                            <div
                                                class="security-icon bg-{{ auth()->user()->is_active ? 'success' : 'danger' }} text-{{ auth()->user()->is_active ? 'success' : 'danger' }} me-3 bg-opacity-10">
                                                <i class="ti ti-{{ auth()->user()->is_active ? 'check' : 'x' }}"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0">Estado de la cuenta</h6>
                                            </div>
                                        </div>
                                        <span
                                            class="badge bg-{{ auth()->user()->is_active ? 'success' : 'danger' }} text-{{ auth()->user()->is_active ? 'success' : 'danger' }} border-{{ auth()->user()->is_active ? 'success' : 'danger' }} text-light border border-opacity-25 bg-opacity-15">
                                            {{ auth()->user()->is_active ? 'Activa' : 'Inactiva' }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {{-- Información de fechas importantes --}}
                            <div class="col-md-6">
                                <div class="security-item rounded-3 border p-3">
                                    <div class="d-flex align-items-start">
                                        <div class="security-icon bg-primary text-primary me-3 bg-opacity-10">
                                            <i class="ti ti-calendar-plus"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">Cuenta creada</h6>
                                            <p class="fw-semibold mb-0">
                                                {{ auth()->user()->created_at->format('d/m/Y') }}</p>
                                            <small
                                                class="text-muted">{{ auth()->user()->created_at->diffForHumans() }}</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @if (auth()->user()->last_login)
                                <div class="col-md-6">
                                    <div class="security-item rounded-3 border p-3">
                                        <div class="d-flex align-items-start">
                                            <div class="security-icon bg-success text-success me-3 bg-opacity-10">
                                                <i class="ti ti-login"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-1">Último acceso</h6>
                                                <p class="fw-semibold mb-0">
                                                    {{ auth()->user()->last_login->format('d/m/Y') }}</p>
                                                <small
                                                    class="text-muted">{{ auth()->user()->last_login->diffForHumans() }}</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            {{-- Roles del usuario --}}
                            @if (auth()->user()->hasAnyRole())
                                <div class="col-12 mt-3">
                                    <h6 class="text-muted text-uppercase fw-semibold mb-3"
                                        style="font-size: 0.75rem; letter-spacing: 0.5px;">
                                        <i class="ti ti-shield-check me-1"></i>Roles Asignados
                                    </h6>
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach (auth()->user()->roles as $role)
                                            <span
                                                class="badge bg-primary text-primary border-primary border border-opacity-25 bg-opacity-15 px-3 py-2">
                                                <i class="ti ti-shield me-1"></i>{{ $role->name }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            @hasanyrole('supplier')
                                @php
                                    $supplier = optional(auth()->user()->supplier); // asumiendo User->hasOne(Supplier)
                                    $yesNo = fn($v) => $v ? 'Sí' : 'No';
                                    $repseTags = collect($supplier?->specialized_services_types ?? [])
                                        ->map(function ($t) {
                                            return '<span class="badge bg-secondary text-secondary border-secondary text-light me-1 border border-opacity-25 bg-opacity-15 px-2 py-1">' .
                                                e(ucfirst($t)) .
                                                '</span>';
                                        })
                                        ->implode(' ');
                                @endphp

                                <div class="col-12 mt-4">
                                    <h6 class="text-muted text-uppercase fw-semibold mb-3"
                                        style="font-size: 0.75rem; letter-spacing: 0.5px;">
                                        <i class="ti ti-building me-1"></i>Datos del Proveedor
                                    </h6>

                                    <div class="rounded-3 border p-3">
                                        <div class="row g-3">

                                            {{-- Identificación fiscal y razón social --}}
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-start">
                                                    <div class="security-icon bg-primary text-primary me-3 bg-opacity-10">
                                                        <i class="ti ti-id-badge-2"></i>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-1">Razón social</h6>
                                                        <p class="fw-semibold mb-0">{{ $supplier?->company_name ?? '—' }}
                                                        </p>
                                                        <small class="text-muted">RFC:
                                                            <code>{{ $supplier?->rfc ?? '—' }}</code></small>
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Régimen y Actividad económica --}}
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-start">
                                                    <div class="security-icon bg-info text-info me-3 bg-opacity-10">
                                                        <i class="ti ti-list-details"></i>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-1">Tipo, régimen y actividad</h6>
                                                        <p class="fw-semibold mb-0">
                                                            {{ $supplier?->person_type_label ?? '�' }}
                                                            <span class="text-muted">·</span>
                                                            {{ $supplier?->tax_regimes_label ?? '�' }}
                                                        </p>
                                                        <small class="text-muted">Actividades:
                                                            {{ $supplier?->economic_activities_label ?? '�' }}</small>
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Contacto --}}
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-start">
                                                    <div class="security-icon bg-success text-success me-3 bg-opacity-10">
                                                        <i class="ti ti-user-circle"></i>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-1">Contacto</h6>
                                                        <p class="fw-semibold mb-0">
                                                            {{ $supplier?->contact_person ?? '—' }}</p>
                                                        <small class="text-muted">
                                                            <i
                                                                class="ti ti-phone me-1"></i>{{ $supplier?->contact_phone ?? '—' }}
                                                            <span class="text-muted">·</span>
                                                            <i
                                                                class="ti ti-building-warehouse me-1"></i>{{ $supplier?->phone_number ?? '—' }}
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Servicios especializados (REPSE) --}}
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-start">
                                                    <div class="security-icon bg-warning text-warning me-3 bg-opacity-10">
                                                        <i class="ti ti-shield-check"></i>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-1">Servicios especializados</h6>
                                                        <p class="fw-semibold mb-0">
                                                            {{ $yesNo($supplier?->provides_specialized_services) }}</p>

                                                        @if ($supplier?->provides_specialized_services)
                                                            <small class="text-muted d-block">
                                                                N° REPSE: <span
                                                                    class="fw-semibold">{{ $supplier?->repse_registration_number ?? '—' }}</span>
                                                                @if ($supplier?->repse_expiry_date)
                                                                    <span class="text-muted"> · Vence:
                                                                        {{ \Illuminate\Support\Carbon::parse($supplier->repse_expiry_date)->format('d/m/Y') }}</span>
                                                                @endif
                                                            </small>

                                                            @if ($repseTags)
                                                                <div class="text-light mt-2">{!! $repseTags !!}</div>
                                                            @endif
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Bancarios (compacto) --}}
                                            <div class="col-md-12">
                                                <div class="d-flex align-items-start">
                                                    <div
                                                        class="security-icon bg-secondary text-secondary me-3 bg-opacity-10">
                                                        <i class="ti ti-credit-card"></i>
                                                    </div>
                                                    <div class="w-100">
                                                        <h6 class="mb-1">Datos bancarios</h6>
                                                        <div class="row">
                                                            <div class="col-md-4">
                                                                <small class="text-muted d-block">Banco</small>
                                                                <p class="fw-semibold mb-0">
                                                                    {{ $supplier?->bank_name ?? '—' }}</p>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <small class="text-muted d-block">CLABE / Cuenta</small>
                                                                <p class="fw-semibold mb-0">
                                                                    {{ $supplier?->clabe ?? '—' }}
                                                                    @if ($supplier?->account_number)
                                                                        <span class="text-muted"> · </span>
                                                                        <span>{{ $supplier->account_number }}</span>
                                                                    @endif
                                                                </p>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <small class="text-muted d-block">Moneda</small>
                                                                <p class="fw-semibold mb-0">
                                                                    {{ $supplier?->currency ?? 'MXN' }}</p>
                                                            </div>
                                                        </div>

                                                        @if ($supplier?->swift_bic || $supplier?->iban || $supplier?->aba_routing || $supplier?->bank_address)
                                                            <div class="row mt-2">
                                                                @if ($supplier?->swift_bic)
                                                                    <div class="col-md-3">
                                                                        <small class="text-muted d-block">SWIFT/BIC</small>
                                                                        <p class="fw-semibold mb-0">
                                                                            {{ $supplier->swift_bic }}</p>
                                                                    </div>
                                                                @endif
                                                                @if ($supplier?->iban)
                                                                    <div class="col-md-5">
                                                                        <small class="text-muted d-block">IBAN</small>
                                                                        <p class="fw-semibold mb-0">{{ $supplier->iban }}
                                                                        </p>
                                                                    </div>
                                                                @endif
                                                                @if ($supplier?->aba_routing)
                                                                    <div class="col-md-2">
                                                                        <small class="text-muted d-block">ABA</small>
                                                                        <p class="fw-semibold mb-0">
                                                                            {{ $supplier->aba_routing }}</p>
                                                                    </div>
                                                                @endif
                                                                @if ($supplier?->bank_address)
                                                                    <div class="col-md-12 mt-2">
                                                                        <small class="text-muted d-block">Dirección del
                                                                            banco</small>
                                                                        <p class="mb-0">{{ $supplier->bank_address }}
                                                                        </p>
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Dirección fiscal (compacto) --}}
                                            <div class="col-12">
                                                <div class="d-flex align-items-start">
                                                    <div class="security-icon bg-light text-dark me-3 bg-opacity-10">
                                                        <i class="ti ti-map-pin"></i>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-1">Dirección fiscal</h6>
                                                        <p class="mb-0">{{ $supplier?->address ?? '—' }}</p>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            @endhasanyrole

                        </div>
                    </div>

                    {{-- Footer del Modal --}}
                    <div class="modal-footer bg-light border-0">
                        <div class="w-100 d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="ti ti-shield-check me-1"></i>
                                Cuenta protegida y segura
                            </small>
                            <div>
                                <button type="button" class="btn btn-primary" onclick="changePassword()">
                                    <i class="ti ti-key me-1"></i>Cambiar contraseña
                                </button>
                                <button type="button" class="btn btn-outline-secondary me-1"
                                    data-bs-dismiss="modal">
                                    <i class="ti ti-x me-1"></i>Cerrar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: Reportar problema -->
        <div class="modal fade" id="reportIssueModal" tabindex="-1" aria-labelledby="reportIssueModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header border-0">
                        <div>
                            <h1 class="modal-title fs-5 mb-0" id="reportIssueModalLabel">Reportar mal funcionamiento
                            </h1>
                            <small class="text-muted">Cuéntanos qué salió mal. Mientras más claro, mejor.</small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="Cerrar"></button>
                    </div>

                    <form method="POST" action="{{ route('incidents.store') }}" enctype="multipart/form-data"
                        id="issueForm" novalidate>
                        @csrf
                        <div class="modal-body">
                            <div class="row g-2">
                                {{-- Nombre y Email --}}
                                <div class="col-md-6">
                                    <input type="text" name="reporter_name" class="form-control form-control-sm"
                                        placeholder="Nombre"
                                        value="{{ old('reporter_name', auth()->user()->name ?? '') }}" required>
                                </div>
                                <div class="col-md-6">
                                    <input type="email" name="reporter_email" class="form-control form-control-sm"
                                        placeholder="Correo"
                                        value="{{ old('reporter_email', auth()->user()->email ?? '') }}" required>
                                </div>

                                {{-- Módulo y Severidad --}}
                                <div class="col-md-6">
                                    <input type="text" name="module" class="form-control form-control-sm"
                                        placeholder="Módulo/Pantalla" value="{{ old('module') }}" required>
                                </div>
                                <div class="col-md-6">
                                    <select name="severity" class="form-select-sm form-select" required>
                                        <option value="">Severidad...</option>
                                        <option value="bloqueante">Bloqueante</option>
                                        <option value="alta">Alta</option>
                                        <option value="media">Media</option>
                                        <option value="baja">Baja</option>
                                    </select>
                                </div>

                                {{-- Título corto --}}
                                <div class="col-12">
                                    <input type="text" name="title" class="form-control form-control-sm"
                                        placeholder="Título breve del problema" maxlength="120"
                                        value="{{ old('title') }}" required>
                                </div>

                                {{-- Pasos + Resultado esperado/real --}}
                                <div class="col-12">
                                    <textarea name="steps" class="form-control form-control-sm" rows="2" placeholder="Pasos para reproducir..."
                                        required>{{ old('steps') }}</textarea>
                                </div>
                                <div class="col-md-6">
                                    <textarea name="expected" class="form-control form-control-sm" rows="2" placeholder="Esperado..." required>{{ old('expected') }}</textarea>
                                </div>
                                <div class="col-md-6">
                                    <textarea name="actual" class="form-control form-control-sm" rows="2" placeholder="Ocurrió..." required>{{ old('actual') }}</textarea>
                                </div>

                                {{-- Reproducibilidad e Impacto --}}
                                <div class="col-md-6">
                                    <select name="reproducibility" class="form-select-sm form-select" required>
                                        <option value="">Reproducibilidad...</option>
                                        <option value="siempre">Siempre</option>
                                        <option value="frecuente">Frecuente</option>
                                        <option value="intermitente">Intermitente</option>
                                        <option value="raro">Raro</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <select name="impact" class="form-select-sm form-select" required>
                                        <option value="">Impacto...</option>
                                        <option value="todos">A todos</option>
                                        <option value="equipo">A mi equipo</option>
                                        <option value="usuario">Solo a mí</option>
                                    </select>
                                </div>

                                {{-- Adjuntos opcionales en collapse --}}
                                <div class="col-12">
                                    <a class="text-decoration-none small" data-bs-toggle="collapse"
                                        href="#attachCollapse" role="button">
                                        Adjuntar archivos (opcional)
                                    </a>
                                    <div class="collapse" id="attachCollapse">
                                        <input type="file" name="image" class="form-control form-control-sm"
                                            accept=".png,.jpg,.jpeg,.webp,.gif,.mp4,.mov,.mkv,.pdf">
                                    </div>
                                </div>

                                {{-- Consentimiento --}}
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="can_contact"
                                            value="1" required>
                                        <label class="form-check-label small">
                                            Pueden contactarme para más información.
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer py-2">
                            <button type="button" class="btn btn-light btn-sm"
                                data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary btn-sm">Enviar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Zircos JS -->
        <!-- Vendor js -->
        <script src="{{ asset('assets/js/vendor.min.js') }}"></script>

        <!-- App js -->
        <script src="{{ asset('assets/js/app.js') }}"></script>

        <!-- Sweet Alerts js -->
        <script src="{{ asset('assets/vendor/sweetalert2/sweetalert2.min.js') }}"></script>

        <!-- Sortable -->
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

        <!-- Toastr para notificaciones (opcional) - NUEVO -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
        <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

        <!-- Select2 CSS -->
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css"
            rel="stylesheet" />

        <!-- Select2 JS -->
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

        <!-- Datatables js -->
        <script src="{{ asset('assets/vendor/datatables.net/js/jquery.dataTables.min.js') }}"></script>
        <script src="{{ asset('assets/vendor/datatables.net-bs5/js/dataTables.bootstrap5.min.js') }}"></script>
        <script src="{{ asset('assets/vendor/datatables.net-responsive/js/dataTables.responsive.min.js') }}"></script>
        <script src="{{ asset('assets/vendor/datatables.net-responsive-bs5/js/responsive.bootstrap5.min.js') }}"></script>
        <script src="{{ asset('assets/vendor/datatables.net-fixedcolumns-bs5/js/fixedColumns.bootstrap5.min.js') }}"></script>
        <script src="{{ asset('assets/vendor/datatables.net-fixedheader/js/dataTables.fixedHeader.min.js') }}"></script>
        <script src="{{ asset('assets/vendor/datatables.net-buttons/js/dataTables.buttons.min.js') }}"></script>
        <script src="{{ asset('assets/vendor/datatables.net-buttons-bs5/js/buttons.bootstrap5.min.js') }}"></script>

        <!-- Dependencias para Excel/PDF -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

        <script src="{{ asset('assets/vendor/datatables.net-buttons/js/buttons.html5.min.js') }}"></script>
        <script src="{{ asset('assets/vendor/datatables.net-buttons/js/buttons.flash.min.js') }}"></script>
        <script src="{{ asset('assets/vendor/datatables.net-buttons/js/buttons.print.min.js') }}"></script>
        <script src="{{ asset('assets/vendor/datatables.net-keytable/js/dataTables.keyTable.min.js') }}"></script>
        <script src="{{ asset('assets/vendor/datatables.net-select/js/dataTables.select.min.js') }}"></script>

        @livewireScripts

        @stack('scripts')

        <script>
            // Prefill de datos técnicos cuando se abre el modal
            document.getElementById('reportIssueModal').addEventListener('shown.bs.modal', function() {
                const ua = navigator.userAgent;
                const url = window.location.href;
                document.getElementById('user_agent').value = ua;
                document.getElementById('ua_readonly').value = ua;
                document.getElementById('current_url').value = url;
                // Prefill fecha si no hay valor
                const happened = document.getElementById('happened_at');
                if (!happened.value) {
                    const now = new Date();
                    const pad = n => String(n).padStart(2, '0');
                    happened.value =
                        `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
                }
            });

            // Conteo de título
            const titleInput = document.getElementById('title');
            const titleCount = document.getElementById('titleCount');
            if (titleInput && titleCount) {
                const updateCount = () => titleCount.textContent = titleInput.value.length;
                titleInput.addEventListener('input', updateCount);
                updateCount();
            }

            // Previsualización simple de imágenes adjuntas
            const inputFiles = document.getElementById('attachments');
            const preview = document.getElementById('attachmentPreview');
            if (inputFiles && preview) {
                inputFiles.addEventListener('change', () => {
                    preview.innerHTML = '';
                    [...inputFiles.files].slice(0, 10).forEach(file => {
                        if (file.type.startsWith('image/')) {
                            const img = document.createElement('img');
                            img.className = 'rounded border';
                            img.style.maxHeight = '72px';
                            img.style.maxWidth = '72px';
                            img.alt = file.name;
                            img.src = URL.createObjectURL(file);
                            preview.appendChild(img);
                        } else {
                            const badge = document.createElement('span');
                            badge.className = 'badge text-bg-secondary';
                            badge.textContent = file.name;
                            preview.appendChild(badge);
                        }
                    });
                });
            }

            // Estado de carga en submit + validación nativa Bootstrap
            const form = document.getElementById('issueForm');
            const submitBtn = document.getElementById('submitBtn');
            form.addEventListener('submit', function(e) {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                } else {
                    submitBtn.disabled = true;
                    submitBtn.querySelector('.spinner-border').classList.remove('d-none');
                }
                form.classList.add('was-validated');
            });
        </script>

        {{-- JavaScript para funcionalidad --}}
        <script>
            async function changePassword() {
                const {
                    value: formValues
                } = await Swal.fire({
                    title: '<i class="ti ti-key text-primary me-1"></i>Cambiar Contraseña',
                    html: `
                <div class="text-start">
                    <div class="mb-3">
                        <label for="current_password" class="form-label text-muted">Contraseña actual</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="ti ti-lock"></i></span>
                            <input id="current_password" class="form-control" type="password" placeholder="Ingresa tu contraseña actual">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('current_password', this)">
                                <i class="ti ti-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label text-muted">Nueva contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="ti ti-key"></i></span>
                            <input id="new_password" class="form-control" type="password" placeholder="Ingresa tu nueva contraseña">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('new_password', this)">
                                <i class="ti ti-eye"></i>
                            </button>
                        </div>
                        <small class="text-muted">Mínimo 8 caracteres</small>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label text-muted">Confirmar nueva contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="ti ti-check"></i></span>
                            <input id="confirm_password" class="form-control" type="password" placeholder="Confirma tu nueva contraseña">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('confirm_password', this)">
                                <i class="ti ti-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="alert alert-info d-flex align-items-center" role="alert">
                        <i class="ti ti-info-circle me-2"></i>
                        <div>Al cambiar tu contraseña, se cerrará tu sesión automáticamente para mayor seguridad.</div>
                    </div>
                </div>
            `,
                    focusConfirm: false,
                    showCancelButton: true,
                    confirmButtonText: '<i class="ti ti-device-floppy me-1"></i>Cambiar contraseña',
                    cancelButtonText: '<i class="ti ti-x me-1"></i>Cerrar',
                    customClass: {
                        popup: 'swal-wide',
                        confirmButton: 'btn btn-primary',
                        cancelButton: 'btn btn-outline-secondary me-1'
                    },
                    buttonsStyling: false,
                    preConfirm: () => {
                        const currentPassword = document.getElementById('current_password').value;
                        const newPassword = document.getElementById('new_password').value;
                        const confirmPassword = document.getElementById('confirm_password').value;

                        // Validaciones
                        if (!currentPassword) {
                            Swal.showValidationMessage('La contraseña actual es requerida');
                            return false;
                        }

                        if (!newPassword) {
                            Swal.showValidationMessage('La nueva contraseña es requerida');
                            return false;
                        }

                        if (newPassword.length < 8) {
                            Swal.showValidationMessage('La nueva contraseña debe tener al menos 8 caracteres');
                            return false;
                        }

                        if (newPassword !== confirmPassword) {
                            Swal.showValidationMessage('Las contraseñas no coinciden');
                            return false;
                        }

                        if (currentPassword === newPassword) {
                            Swal.showValidationMessage('La nueva contraseña debe ser diferente a la actual');
                            return false;
                        }

                        return {
                            current_password: currentPassword,
                            new_password: newPassword,
                            new_password_confirmation: confirmPassword
                        };
                    }
                });

                if (formValues) {
                    // Mostrar loading mientras se procesa
                    Swal.fire({
                        title: 'Cambiando contraseña...',
                        text: 'Por favor espera mientras procesamos tu solicitud',
                        icon: 'info',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    try {
                        // Realizar petición AJAX
                        const response = await fetch('/profile/change-password', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify(formValues)
                        });

                        const data = await response.json();

                        if (response.ok && data.success) {
                            // Éxito - mostrar mensaje y cerrar sesión
                            await Swal.fire({
                                title: '¡Contraseña cambiada!',
                                text: 'Tu contraseña se ha actualizado correctamente. Serás redirigido al login.',
                                icon: 'success',
                                timer: 3000,
                                timerProgressBar: true,
                                allowOutsideClick: false,
                                confirmButtonText: 'Entendido',
                                customClass: {
                                    confirmButton: 'btn btn-success'
                                },
                                buttonsStyling: false
                            });

                            // Cerrar modal de perfil
                            const profileModal = bootstrap.Modal.getInstance(document.getElementById('profileModal'));
                            if (profileModal) {
                                profileModal.hide();
                            }

                            // Logout automático
                            logout();

                        } else {
                            // Error del servidor
                            Swal.fire({
                                title: 'Error al cambiar contraseña',
                                text: data.message ||
                                    'Ha ocurrido un error inesperado. Por favor intenta nuevamente.',
                                icon: 'error',
                                confirmButtonText: 'Intentar de nuevo',
                                customClass: {
                                    confirmButton: 'btn btn-danger'
                                },
                                buttonsStyling: false
                            });
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        Swal.fire({
                            title: 'Error de conexión',
                            text: 'No se pudo conectar con el servidor. Verifica tu conexión a internet.',
                            icon: 'error',
                            confirmButtonText: 'Entendido',
                            customClass: {
                                confirmButton: 'btn btn-danger'
                            },
                            buttonsStyling: false
                        });
                    }
                }
            }

            function togglePasswordVisibility(inputId, button) {
                const input = document.getElementById(inputId);
                const icon = button.querySelector('i');

                if (input.type === 'password') {
                    input.type = 'text';
                    icon.className = 'ti ti-eye-off';
                } else {
                    input.type = 'password';
                    icon.className = 'ti ti-eye';
                }
            }

            async function logout() {
                try {
                    const response = await fetch('/logout', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        }
                    });

                    if (response.ok) {
                        window.location.href = '/login';
                    } else {
                        // Si falla el logout automático, redirigir manualmente
                        window.location.href = '/login';
                    }
                } catch (error) {
                    // En caso de error, redirigir manualmente
                    window.location.href = '/login';
                }
            }
        </script>
    </body>

</html>
