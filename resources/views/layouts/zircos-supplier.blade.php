<!DOCTYPE html>
<html lang="es" data-sidenav-size="default">
<head>
    <meta charset="utf-8" />
    <title>@yield('title', config('app.name'))</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="shortcut icon" href="{{ asset('images/logos/Logo.png') }}">
    <script src="{{ asset('assets/js/config.js') }}"></script>
    <script src="{{ asset('js/sidebar-settings.js') }}"></script>
    <link href="{{ asset('assets/css/vendor.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/app.min.css') }}" rel="stylesheet" type="text/css" id="app-style" />
    <link href="{{ asset('assets/css/icons.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/vendor/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/vendor/datatables.net-bs5/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/vendor/datatables.net-responsive-bs5/css/responsive.bootstrap5.min.css') }}" rel="stylesheet" type="text/css" />
    <style>
        .sidenav-logo-img {
            display: block;
            width: 100%;
            height: auto !important;
            max-width: 100%;
            padding: 10px 16px;
            box-sizing: border-box;
            object-fit: contain;
        }
    </style>
    @stack('styles')
</head>
<body>
    <div class="wrapper">
        @include('layouts.partials.sidebar')
        @include('layouts.partials.navbar')
        <div class="page-content">
            <div class="page-container">
                <div class="page-title-head d-flex align-items-center gap-2">
                    <div class="flex-grow-1">
                        <h4 class="fs-17 mb-0">@yield('page.title', 'Portal de proveedores')</h4>
                    </div>
                    <div class="text-end">
                        <ol class="breadcrumb fs-13 m-0 py-0">
                            @hasSection('page.breadcrumbs')
                                @yield('page.breadcrumbs')
                            @else
                                <li class="breadcrumb-item"><a href="{{ route('supplier.documents.index') }}">Inicio</a></li>
                                <li class="breadcrumb-item active">@yield('page.title', 'Portal de proveedores')</li>
                            @endif
                        </ol>
                    </div>
                </div>

                @if (session('status'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('status') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
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
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    </div>
                @endif

                @yield('content')
                @include('layouts.partials.flash')
            </div>
            @include('layouts.partials.footer')
        </div>
    </div>

    <script src="{{ asset('assets/js/vendor.min.js') }}"></script>
    <script src="{{ asset('assets/js/app.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/sweetalert2/sweetalert2.all.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/datatables.net/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/datatables.net-bs5/js/dataTables.bootstrap5.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/datatables.net-responsive/js/dataTables.responsive.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/datatables.net-responsive-bs5/js/responsive.bootstrap5.min.js') }}"></script>
    @stack('scripts')
</body>
</html>
