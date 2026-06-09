@php
    $moduleAccess = app(\App\Services\ModuleAccessService::class);
    $user = auth()->user();

    
    $showPurchasingSection = collect([
        'requisitions',
        'quotations',
        'purchase_orders',
        'receptions',
        'products_services',
    ])->contains(fn ($module) => $moduleAccess->userCanAccessModule($user, $module));

    $showFinanceSection = collect([
        'budget_control',
        'payments_billing',
    ])->contains(fn ($module) => $moduleAccess->userCanAccessModule($user, $module));

    $showSuppliersSection = collect([
        'document_review',
        'communicator',
    ])->contains(fn ($module) => $moduleAccess->userCanAccessModule($user, $module));

    $openRfq = request()->routeIs('rfq.*') || request()->routeIs('quotes.*') || request()->routeIs('approvals.quotations.*');
    $openBudget = request()->routeIs('annual_budgets.*')
        || request()->routeIs('budget_monthly_distributions.*')
        || request()->routeIs('budget_movements.*')
        || request()->routeIs('cost-centers.*')
        || request()->routeIs('categories.*');
    $openPayments = request()->routeIs('invoices.*')
        || request()->routeIs('payments.*')
        || request()->routeIs('financial-provisions.*')
        || request()->routeIs('finance-reports.*');
    $openSuppliers = request()->routeIs('cat-suppliers.*')
        || request()->routeIs('sat_efos_69b.*')
        || request()->routeIs('sirocs.*')
        || request()->routeIs('documents.suppliers.*')
        || request()->routeIs('admin.review.*');
    $openConfiguration = request()->routeIs('companies.*')
        || request()->routeIs('stations.*')
        || request()->routeIs('departments.*')
        || request()->routeIs('receiving-locations.*')
        || request()->routeIs('taxes.*')
        || request()->routeIs('authorizer-roles.*')
        || request()->routeIs('approval-levels.*')
        || request()->routeIs('sat-retenciones.*')
        || request()->routeIs('roles.catalog');

    try {
        $activeRequisitionsCount = \App\Models\Requisition::whereNotIn('status', ['DRAFT', 'CANCELLED', 'COMPLETED'])->count();
    } catch (\Throwable $e) {
        $activeRequisitionsCount = 0;
    }

    try {
        $pendingApprovalsCount = auth()->check()
            ? \App\Models\QuotationSummary::where('approval_status', 'pending')
                ->where('current_approver_user_id', auth()->id())
                ->count()
            : 0;
    } catch (\Throwable $e) {
        $pendingApprovalsCount = 0;
    }

    try {
        $poCount = \App\Models\PurchaseOrder::whereIn('status', [
            'ISSUED',
            'PARTIALLY_RECEIVED',
            'DELIVERED_PENDING_RECEPTION',
        ])->count();
    } catch (\Throwable $e) {
        $poCount = 0;
    }
@endphp

{{--
    sidebar-staff.blade.php
    =======================
    Sidebar for all TotalGas internal staff roles.

    ROLES HANDLED:
      - superadmin     : full access to all sections
      - staff          : Compras + Proveedores + Órdenes de Compra
      - accounting     : Finanzas only
      - general_director : Compras + Finanzas
      - authorizer     : Compras → Aprobar cotización only
      - catalog_admin  : Compras → Productos/Servicios only
      - requester      : Compras → Requisiciones + Órdenes de Compra

    HOW TO ADD A NEW SECTION:
      1. Add a new section block wrapped with @hasanyrole('role1|role2|...')
      2. Add a plain comment above the block listing the allowed roles.
      3. Update the ROLES HANDLED list above if a new role is introduced.

    HOW TO ADD AN ITEM INSIDE AN EXISTING SECTION:
      1. Add the <li> block in the correct section.
      2. If it has different role restrictions than the parent section,
         wrap it with its own @hasanyrole directive and add an inline comment.

    SECTIONS:
      - INICIO          → all roles
      - COMPRAS         → superadmin, staff, requester, general_director, authorizer, catalog_admin
      - FINANZAS        → superadmin, accounting, general_director
      - PROVEEDORES     → superadmin, staff
      - HERRAMIENTAS    → superadmin only
      - CONFIGURACIÓN   → superadmin only
--}}

{{-- ═══════════════════════════════════════════════════
     INICIO — visible to: all staff roles
     ═══════════════════════════════════════════════════ --}}
<li class="side-nav-title">INICIO</li>
@moduleAccess('dashboard')
<li class="side-nav-item">
    <a class="side-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
        href="{{ route('dashboard') }}">
        <span class="menu-icon"><i class="ti ti-home"></i></span>
        <span class="menu-text">Dashboard</span>
    </a>
</li>
@endmoduleAccess

@if ($showPurchasingSection)
<li class="side-nav-title">COMPRAS</li>

@moduleAccess('requisitions')
<li class="side-nav-item">
    <a href="{{ route('requisitions.index') }}"
        class="side-nav-link {{ request()->routeIs('requisitions.*') || request()->routeIs('requisition-items.*') ? 'active' : '' }}">
        <span class="menu-icon"><i class="ti ti-clipboard-list"></i></span>
        <span class="menu-text">Requisiciones</span>
    </a>
</li>
@endmoduleAccess

@moduleAccess('quotations')
<li class="side-nav-item">
    <a class="side-nav-link {{ $openRfq ? '' : 'collapsed' }}" data-bs-toggle="collapse"
        href="#sidebarComprasRfq" role="button"
        aria-expanded="{{ $openRfq ? 'true' : 'false' }}" aria-controls="sidebarComprasRfq">
        <span class="menu-icon"><i class="ti ti-file-invoice"></i></span>
        <span class="menu-text">Cotizaciones (RFQ)</span>
        <span class="menu-arrow"></span>
    </a>
    <div class="{{ $openRfq ? 'show' : '' }} collapse" id="sidebarComprasRfq">
        <ul class="sub-menu">
            @if ($moduleAccess->userCanAccessModule($user, 'quotations') && $user?->hasAnyRole(['buyer', 'superadmin']))
            <li class="side-nav-item">
                <a href="{{ route('quotes.index') }}"
                    class="side-nav-link {{ request()->routeIs('quotes.index') ? 'active' : '' }}">
                    <span class="menu-text">Cotizar</span>
                    @if ($activeRequisitionsCount > 0)
                        <span class="badge bg-warning text-dark rounded-pill ms-auto">{{ $activeRequisitionsCount }}</span>
                    @endif
                </a>
            </li>
            @endif

            <li class="side-nav-item">
                <a href="{{ route('approvals.quotations.index') }}"
                    class="side-nav-link {{ request()->routeIs('approvals.quotations.*') ? 'active' : '' }}">
                    <span class="menu-text">Aprobar cotizacion</span>
                    @if ($pendingApprovalsCount > 0)
                        <span class="badge bg-danger rounded-pill ms-auto">{{ $pendingApprovalsCount }}</span>
                    @endif
                </a>
            </li>

            <li class="side-nav-item">
                <a href="{{ route('rfq.index') }}"
                    class="side-nav-link {{ request()->routeIs('rfq.index') ? 'active' : '' }}">
                    <span class="menu-text">Listado de RFQs</span>
                </a>
            </li>

            @if ($user?->hasAnyRole(['buyer', 'superadmin']))
            <li class="side-nav-item">
                <a href="{{ route('rfq.inbox.pending') }}"
                    class="side-nav-link {{ request()->routeIs('rfq.inbox.pending') ? 'active' : '' }}">
                    <span class="menu-text">Pendientes de Respuesta</span>
                </a>
            </li>
            @endif
        </ul>
    </div>
</li>
@endmoduleAccess

@moduleAccess('purchase_orders')
<li class="side-nav-item">
    <a href="{{ route('purchase-orders.index') }}"
        class="side-nav-link {{ request()->routeIs('purchase-orders.*') || request()->routeIs('direct-purchase-orders.*') ? 'active' : '' }}">
        <span class="menu-icon"><i class="ti ti-shopping-cart"></i></span>
        <span class="menu-text">Ordenes de Compra</span>
        @if ($poCount > 0)
            <span class="badge bg-info rounded-pill ms-auto">{{ $poCount }}</span>
        @endif
    </a>
</li>
@endmoduleAccess

@moduleAccess('receptions')
<li class="side-nav-item">
    <a href="{{ route('receptions.overview') }}"
        class="side-nav-link {{ request()->routeIs('receptions.*') ? 'active' : '' }}">
        <span class="menu-icon"><i class="ti ti-truck-delivery"></i></span>
        <span class="menu-text">Recepciones</span>
    </a>
</li>
@endmoduleAccess

@moduleAccess('products_services')
<li class="side-nav-item">
    <a href="{{ route('products-services.index') }}"
        class="side-nav-link {{ request()->routeIs('products-services.*') ? 'active' : '' }}">
        <span class="menu-icon"><i class="ti ti-package"></i></span>
        <span class="menu-text">Productos/Servicios</span>
    </a>
</li>
@endmoduleAccess
@endif

@if ($showFinanceSection)
<li class="side-nav-title">FINANZAS</li>

@moduleAccess('budget_control')
<li class="side-nav-item">
    <a class="side-nav-link {{ $openBudget ? '' : 'collapsed' }}" data-bs-toggle="collapse"
        href="#sidebarPresupuesto" role="button"
        aria-expanded="{{ $openBudget ? 'true' : 'false' }}" aria-controls="sidebarPresupuesto">
        <span class="menu-icon"><i class="ti ti-chart-bar"></i></span>
        <span class="menu-text">Control Presupuestal</span>
        <span class="menu-arrow"></span>
    </a>
    <div class="{{ $openBudget ? 'show' : '' }} collapse" id="sidebarPresupuesto">
        <ul class="sub-menu">
            <li class="side-nav-item">
                <a href="{{ route('cost-centers.index') }}"
                    class="side-nav-link {{ request()->routeIs('cost-centers.*') ? 'active' : '' }}">
                    <span class="menu-text">Centros de Costo</span>
                </a>
            </li>
            <li class="side-nav-item">
                <a href="{{ route('annual_budgets.index') }}"
                    class="side-nav-link {{ request()->routeIs('annual_budgets.*') ? 'active' : '' }}">
                    <span class="menu-text">Presupuestos Anuales</span>
                </a>
            </li>
            <li class="side-nav-item">
                <a href="{{ route('budget_monthly_distributions.index') }}"
                    class="side-nav-link {{ request()->routeIs('budget_monthly_distributions.*') ? 'active' : '' }}">
                    <span class="menu-text">Distribuciones Mensuales</span>
                </a>
            </li>
            <li class="side-nav-item">
                <a href="{{ route('budget_movements.index') }}"
                    class="side-nav-link {{ request()->routeIs('budget_movements.*') ? 'active' : '' }}">
                    <span class="menu-text">Movimiento Presupuestal</span>
                </a>
            </li>
            <li class="side-nav-item">
                <a href="{{ route('categories.index') }}"
                    class="side-nav-link {{ request()->routeIs('categories.*') ? 'active' : '' }}">
                    <span class="menu-text">Categorias</span>
                </a>
            </li>
        </ul>
    </div>
</li>
@endmoduleAccess

@moduleAccess('payments_billing')
<li class="side-nav-item">
    <a class="side-nav-link {{ $openPayments ? '' : 'collapsed' }}" data-bs-toggle="collapse"
        href="#sidebarPagos" role="button" aria-expanded="{{ $openPayments ? 'true' : 'false' }}"
        aria-controls="sidebarPagos">
        <span class="menu-icon"><i class="ti ti-cash"></i></span>
        <span class="menu-text">Pagos y Facturacion</span>
        <span class="menu-arrow"></span>
    </a>
    <div class="{{ $openPayments ? 'show' : '' }} collapse" id="sidebarPagos">
        <ul class="sub-menu">
            <li class="side-nav-item">
                <a href="{{ route('invoices.index') }}"
                    class="side-nav-link {{ request()->routeIs('invoices.*') ? 'active' : '' }}">
                    <span class="menu-text">Facturas</span>
                </a>
            </li>
            <li class="side-nav-item">
                <a href="{{ route('financial-provisions.index') }}"
                    class="side-nav-link {{ request()->routeIs('financial-provisions.*') ? 'active' : '' }}">
                    <span class="menu-text">Provisiones</span>
                </a>
            </li>
        </ul>
    </div>
</li>
@endmoduleAccess
@endif

@if ($showSuppliersSection)
<li class="side-nav-title">PROVEEDORES</li>

@moduleAccess('document_review')
<li class="side-nav-item">
    <a class="side-nav-link {{ $openSuppliers ? '' : 'collapsed' }}" data-bs-toggle="collapse"
        href="#sidebarGestionProveedores" role="button"
        aria-expanded="{{ $openSuppliers ? 'true' : 'false' }}"
        aria-controls="sidebarGestionProveedores">
        <span class="menu-icon"><i class="ti ti-building-store"></i></span>
        <span class="menu-text">Gestion de Proveedores</span>
        <span class="menu-arrow"></span>
    </a>
    <div class="{{ $openSuppliers ? 'show' : '' }} collapse" id="sidebarGestionProveedores">
        <ul class="sub-menu">
            <li class="side-nav-item">
                <a href="{{ route('admin.suppliers.index') }}"
                    class="side-nav-link {{ request()->routeIs('admin.suppliers.*') ? 'active' : '' }}">
                    <span class="menu-text">Lista de Proveedores</span>
                </a>
            </li>
            <li class="side-nav-item">
                <a href="{{ route('sat_efos_69b.index') }}"
                    class="side-nav-link {{ request()->routeIs('sat_efos_69b.*') ? 'active' : '' }}">
                    <span class="menu-text">EFOS (SAT)</span>
                </a>
            </li>
            <li class="side-nav-item">
                <a href="{{ route('sirocs.index') }}"
                    class="side-nav-link {{ request()->routeIs('sirocs.*') ? 'active' : '' }}">
                    <span class="menu-text">SIROC (IMSS)</span>
                </a>
            </li>
            <li class="side-nav-item">
                <a href="{{ route('admin.review.index') }}"
                    class="side-nav-link {{ request()->routeIs('admin.review.*') ? 'active' : '' }}">
                    <span class="menu-text">Rev. de documentos</span>
                    @if (!empty($pendingReviewCount) && $pendingReviewCount > 0)
                        <span class="badge bg-danger rounded-pill ms-auto">{{ $pendingReviewCount }}</span>
                    @endif
                </a>
            </li>
        </ul>
    </div>
</li>
@endmoduleAccess

@moduleAccess('communicator')
<li class="side-nav-item">
    <a href="{{ route('admin.announcements.index') }}"
        class="side-nav-link {{ request()->routeIs('admin.announcements.*') ? 'active' : '' }}">
        <span class="menu-icon"><i class="ti ti-speakerphone"></i></span>
        <span class="menu-text">Comunicados</span>
    </a>
</li>
@endmoduleAccess
@endif

{{-- ═══════════════════════════════════════════════════
     HERRAMIENTAS — visible to: superadmin only
     ═══════════════════════════════════════════════════ --}}
@hasrole('superadmin')
<li class="side-nav-title">HERRAMIENTAS</li>

<li class="side-nav-item">
    <a href="{{ route('tools.cfdi.form') }}"
        class="side-nav-link {{ request()->routeIs('tools.*') ? 'active' : '' }}">
        <span class="menu-icon"><i class="ti ti-file-code"></i></span>
        <span class="menu-text">Generador CFDI</span>
    </a>
</li>
@endhasrole

{{-- ═══════════════════════════════════════════════════
     CONFIGURACIÓN — visible to: superadmin only
     ═══════════════════════════════════════════════════ --}}
@hasrole('superadmin')
<li class="side-nav-title">CONFIGURACIÓN</li>

@moduleAccess('staff_users')
<li class="side-nav-item">
    <a class="side-nav-link {{ request()->routeIs('users.staff.index') ? 'active' : '' }}"
        href="{{ route('users.staff.index') }}">
        <span class="menu-icon"><i class="ti ti-users"></i></span>
        <span class="menu-text">Usuarios Staff</span>
    </a>
</li>
@endmoduleAccess

@moduleAccess('employees')
<li class="side-nav-item">
    <a class="side-nav-link {{ request()->routeIs('employees.index') ? 'active' : '' }}"
        href="{{ route('employees.index') }}">
        <span class="menu-icon"><i class="ti ti-id-badge"></i></span>
        <span class="menu-text">Empleados</span>
    </a>
</li>
@endmoduleAccess

@moduleAccess('catalogs_config')
<li class="side-nav-item">
    <a class="side-nav-link {{ $openConfiguration ? '' : 'collapsed' }}" data-bs-toggle="collapse"
        href="#sidebarConfigurations" role="button"
        aria-expanded="{{ $openConfiguration ? 'true' : 'false' }}"
        aria-controls="sidebarConfigurations">
        <span class="menu-icon"><i class="ti ti-settings"></i></span>
        <span class="menu-text">Catalogos</span>
        <span class="menu-arrow"></span>
    </a>
    <div class="{{ $openConfiguration ? 'show' : '' }} collapse" id="sidebarConfigurations">
        <ul class="sub-menu">
            <li class="side-nav-item">
                <a href="{{ route('companies.index') }}"
                    class="side-nav-link {{ request()->routeIs('companies.*') ? 'active' : '' }}">
                    <span class="menu-text">Empresas</span>
                </a>
            </li>
            <li class="side-nav-item">
                <a href="{{ route('stations.index') }}"
                    class="side-nav-link {{ request()->routeIs('stations.*') ? 'active' : '' }}">
                    <span class="menu-text">Estaciones</span>
                </a>
            </li>
            <li class="side-nav-item">
                <a href="{{ route('departments.index') }}"
                    class="side-nav-link {{ request()->routeIs('departments.*') ? 'active' : '' }}">
                    <span class="menu-text">Departamentos</span>
                </a>
            </li>
            <li class="side-nav-item">
                <a href="{{ route('receiving-locations.index') }}"
                    class="side-nav-link {{ request()->routeIs('receiving-locations.*') ? 'active' : '' }}">
                    <span class="menu-text">Ubicaciones de Recepcion</span>
                </a>
            </li>
            <li class="side-nav-item">
                <a href="{{ route('taxes.index') }}"
                    class="side-nav-link {{ request()->routeIs('taxes.*') ? 'active' : '' }}">
                    <span class="menu-text">IVA</span>
                </a>
            </li>
            <li class="side-nav-item">
                <a href="{{ route('authorizer-roles.index') }}"
                    class="side-nav-link {{ request()->routeIs('authorizer-roles.*') ? 'active' : '' }}">
                    <span class="menu-text">Roles Autorizadores</span>
                </a>
            </li>
            <li class="side-nav-item">
                <a href="{{ route('roles.catalog') }}"
                    class="side-nav-link {{ request()->routeIs('roles.catalog') ? 'active' : '' }}">
                    <span class="menu-text">Roles y Permisos</span>
                </a>
            </li>
            <li class="side-nav-item">
                <a href="{{ route('approval-levels.index') }}"
                    class="side-nav-link {{ request()->routeIs('approval-levels.*') ? 'active' : '' }}">
                    <span class="menu-text">Niveles de Autorizacion</span>
                </a>
            </li>
            <li class="side-nav-item">
                <a href="{{ route('sat-retenciones.index') }}"
                    class="side-nav-link {{ request()->routeIs('sat-retenciones.*') ? 'active' : '' }}">
                    <span class="menu-text">Retenciones SAT</span>
                </a>
            </li>
        </ul>
    </div>
</li>
@endmoduleAccess

@moduleAccess('reported_incidents')
<li class="side-nav-item text-danger">
    <a href="{{ route('incidents.index') }}"
        class="side-nav-link text-danger {{ request()->routeIs('incidents.*') ? 'active' : '' }}">
        <span class="menu-icon"><i class="ti ti-bug"></i></span>
        <span class="menu-text">Incidentes Reportados</span>
    </a>
</li>
@endmoduleAccess
@endhasrole {{-- end CONFIGURACIÓN --}}
