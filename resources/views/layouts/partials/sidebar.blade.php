{{--
    sidebar.blade.php
    =================
    Entry point for all sidebar rendering. Acts as a role-based dispatcher.

    DO NOT add menu items here. Edit the appropriate partial instead:
      - Staff menu     → layouts/partials/sidebar-staff.blade.php
      - Supplier menu  → layouts/partials/sidebar-supplier.blade.php


      
    ROUTING LOGIC:
      - Users with role 'supplier' → sidebar-supplier.blade.php
      - All other authenticated users (staff roles) → sidebar-staff.blade.php
--}}
<div class="sidenav-menu">
    @php
        $isSupplierGuard = auth('supplier')->check();
    @endphp
    <a href="{{ $isSupplierGuard ? route('supplier.documents.index') : route('dashboard') }}" class="logo">
        <span class="logo-light">
            <span class="logo-lg"><img src="{{ asset('images/logos/logo_TotalGas_hor_azul.png') }}" alt="TotalGas" class="sidenav-logo-img"></span>
            <span class="logo-sm"><img src="{{ asset('images/logos/logo_TotalGas_hor_azul.png') }}" alt="TotalGas" class="sidenav-logo-img"></span>
        </span>
        <span class="logo-dark">
            <span class="logo-lg"><img src="{{ asset('images/logos/logo_TotalGas_hor_azul.png') }}" alt="TotalGas" class="sidenav-logo-img"></span>
            <span class="logo-sm"><img src="{{ asset('images/logos/logo_TotalGas_hor_azul.png') }}" alt="TotalGas" class="sidenav-logo-img"></span>
        </span>
    </a>

    <button class="button-sm-hover" aria-label="Toggle mini sidebar">
        <i class="ti ti-circle align-middle" aria-hidden="true"></i>
    </button>

    <button class="button-close-fullsidebar" aria-label="Close sidebar">
        <i class="ti ti-x align-middle" aria-hidden="true"></i>
    </button>

    <div data-simplebar>
        <ul class="side-nav">
            @if($isSupplierGuard)
                @include('layouts.partials.sidebar-supplier')
            @else
                @include('layouts.partials.sidebar-staff')
            @endif
        </ul>

        <!-- Help Box -->
        <div class="help-box text-center">
            <h5 class="fw-semibold fs-16">¿Necesitas ayuda?</h5>
            <p class="text-muted mb-3">Si el sistema no funciona como esperabas, por favor contáctanos.</p>
            <a href="javascript: void(0);" data-bs-toggle="modal" data-bs-target="#reportIssueModal"
                class="btn btn-danger btn-sm">Contáctanos</a>
        </div>

        <div class="clearfix"></div>
    </div>
</div>
