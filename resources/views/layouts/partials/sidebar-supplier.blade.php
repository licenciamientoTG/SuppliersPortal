@php
    $u = auth('supplier')->user();
    $openSupplierRfq = request()->routeIs('supplier.rfq.*') || request()->routeIs('supplier.quotations.*');
    $openFacturacion = request()->routeIs('supplier.invoices.*');
@endphp



<li class="side-nav-title">PORTAL DE PROVEEDORES</li>

@if ($u->hasRestrictedPortalAccess())
    <li class="side-nav-item">
        <a href="{{ route('supplier.documents.index') }}"
            class="side-nav-link {{ request()->routeIs('supplier.documents.*') ? 'active' : '' }}">
            <span class="menu-icon"><i class="ti ti-checklist"></i></span>
            <span class="menu-text">Documentacion</span>
            <span class="badge bg-danger rounded-pill">!</span>
        </a>
    </li>
@else
    <li class="side-nav-item">
        <a href="{{ route('supplier.dashboard') }}"
            class="side-nav-link {{ request()->routeIs('supplier.dashboard') ? 'active' : '' }}">
            <span class="menu-icon"><i class="ti ti-home"></i></span>
            <span class="menu-text">Dashboard</span>
        </a>
    </li>

    <li class="side-nav-item">
        <a class="side-nav-link {{ $openSupplierRfq ? '' : 'collapsed' }}"
            data-bs-toggle="collapse"
            href="#sidebarSupplierRfq"
            role="button"
            aria-expanded="{{ $openSupplierRfq ? 'true' : 'false' }}"
            aria-controls="sidebarSupplierRfq">
            <span class="menu-icon"><i class="ti ti-file-invoice"></i></span>
            <span class="menu-text">Cotizaciones</span>
            <span class="menu-arrow"></span>
        </a>
        <div class="{{ $openSupplierRfq ? 'show' : '' }} collapse" id="sidebarSupplierRfq">
            <ul class="sub-menu">
                <li class="side-nav-item">
                    <a href="{{ route('supplier.dashboard') }}"
                        class="side-nav-link {{ request()->routeIs('supplier.dashboard') || request()->routeIs('supplier.rfq.show') ? 'active' : '' }}">
                        <span class="menu-text">Mis RFQs</span>
                    </a>
                </li>
                <li class="side-nav-item">
                    <a href="{{ route('supplier.quotations.history') }}"
                        class="side-nav-link {{ request()->routeIs('supplier.quotations.history') ? 'active' : '' }}">
                        <span class="menu-text">Historial</span>
                    </a>
                </li>
            </ul>
        </div>
    </li>

    <li class="side-nav-item">
        <a href="{{ route('supplier.documents.index') }}"
            class="side-nav-link {{ request()->routeIs('supplier.documents.*') ? 'active' : '' }}">
            <span class="menu-icon"><i class="ti ti-checklist"></i></span>
            <span class="menu-text">Documentacion</span>
        </a>
    </li>

    <li class="side-nav-item">
        <a href="{{ route('supplier.announcements.inbox') }}"
            class="side-nav-link {{ request()->routeIs('supplier.announcements.*') ? 'active' : '' }}">
            <span class="menu-icon"><i class="ti ti-speakerphone"></i></span>
            <span class="menu-text">Comunicados</span>
        </a>
    </li>

    <li class="side-nav-item">
        <a href="{{ route('supplier.deliveries.index') }}"
            class="side-nav-link {{ request()->routeIs('supplier.deliveries.*') ? 'active' : '' }}">
            <span class="menu-icon"><i class="ti ti-truck-delivery"></i></span>
            <span class="menu-text">Mis Entregas</span>
        </a>
    </li>

    <li class="side-nav-item">
        <a class="side-nav-link {{ $openFacturacion ? '' : 'collapsed' }}"
            data-bs-toggle="collapse"
            href="#sidebarSupplierFacturacion"
            role="button"
            aria-expanded="{{ $openFacturacion ? 'true' : 'false' }}"
            aria-controls="sidebarSupplierFacturacion">
            <span class="menu-icon"><i class="ti ti-file-invoice"></i></span>
            <span class="menu-text">Facturacion</span>
            <span class="menu-arrow"></span>
        </a>
        <div class="{{ $openFacturacion ? 'show' : '' }} collapse" id="sidebarSupplierFacturacion">
            <ul class="sub-menu">
                <li class="side-nav-item">
                    <a href="{{ route('supplier.invoices.create') }}" class="side-nav-link">
                        <span class="menu-text">Cargar Factura</span>
                    </a>
                </li>
                <li class="side-nav-item">
                    <a href="{{ route('supplier.invoices.index') }}" class="side-nav-link">
                        <span class="menu-text">Historial de Facturas</span>
                    </a>
                </li>
            </ul>
        </div>
    </li>
@endif
