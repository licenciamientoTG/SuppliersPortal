<!-- Topbar Start -->
<header class="app-topbar">
    @php
        $authUser = auth('supplier')->user() ?? auth()->user();
        $isSupplierGuard = auth('supplier')->check();
        $homeRoute = $isSupplierGuard ? route('supplier.documents.index') : route('dashboard');
        $displayName = $isSupplierGuard ? ($authUser?->company_name ?? $authUser?->name) : ($authUser?->name);
    @endphp
    <div class="page-container topbar-menu">
        <div class="d-flex align-items-center gap-2">

            <!-- Brand Logo -->
            <a href="{{ $homeRoute }}" class="logo">
                <span class="logo-light">
                    <span class="logo-lg"><img src="{{ asset('images/logos/logo_TotalGas_hor_verde.png') }}" alt="logo"></span>
                    <span class="logo-sm"><img src="{{ asset('images/logos/logo_TotalGas_hor_verde.png') }}" alt="small logo"></span>
                </span>

                <span class="logo-dark">
                    <span class="logo-lg"><img src="{{ asset('images/logos/logo_TotalGas_hor_azul.png') }}" alt="dark logo"></span>
                    <span class="logo-sm"><img src="{{ asset('images/logos/logo_TotalGas_hor_azul.png') }}" alt="small logo"></span>
                </span>
            </a>

            <!-- Sidebar Menu Toggle Button -->
            <button class="sidenav-toggle-button btn-icon rounded-circle btn btn-light">
                <i class="ti ti-menu-2 fs-22"></i>
            </button>

            <!-- Horizontal Menu Toggle Button -->
            <button class="topnav-toggle-button px-2" data-bs-toggle="collapse" data-bs-target="#topnav-menu-content">
                <i class="ti ti-menu-2 fs-22"></i>
            </button>


            <!-- Mega Menu Dropdown -->
            <div class="topbar-item d-none d-md-flex">
                <div class="dropdown">
                    <a href="https://totalgas.com/" target="_blank">
                        TOTALGAS
                    </a>
                </div> <!-- .dropdown-->
            </div> <!-- end topbar-item -->

            <!-- Tipo de cambio USD/MXN -->
            @if($exchangeRate)
            <div class="topbar-item d-none d-md-flex">
                <span class="badge bg-light text-dark border fs-12 fw-semibold px-2 py-1">
                    <i class="ti ti-currency-dollar me-1 text-success"></i>USD
                    ${{ number_format($exchangeRate->rate, 2) }}
                    <span class="text-muted fw-normal">· act. {{ $exchangeRate->fetched_at->format('H:i') }}</span>
                </span>
            </div>
            @endif
        </div>

        <div class="d-flex align-items-center gap-2">

            <!-- Log Viewer (solo dev) -->
            @if(!$isSupplierGuard && Auth::id() === 1)
            <div class="topbar-item">
                <a href="{{ route('dev.log.index') }}" class="topbar-link" title="Ver Log del sistema">
                    <i class="ti ti-bug fs-22 text-danger"></i>
                </a>
            </div>
            @endif

            @php
                $notificationStyles = [
                    'new_rfq' => ['icon' => 'ti ti-file-invoice', 'color' => 'warning', 'title' => 'Nueva cotización'],
                    'new_rfq_for_supplier' => ['icon' => 'ti ti-file-invoice', 'color' => 'warning', 'title' => 'Nueva cotización'],
                    'rfq_cancelled_for_supplier' => ['icon' => 'ti ti-xbox-x', 'color' => 'danger', 'title' => 'Cotización cancelada'],
                    'rfq_cancelled_for_requester' => ['icon' => 'ti ti-xbox-x', 'color' => 'danger', 'title' => 'RFQ cancelada'],
                    'rfq_sent_to_suppliers' => ['icon' => 'ti ti-send', 'color' => 'info', 'title' => 'RFQ enviada'],
                    'requisition_submitted' => ['icon' => 'ti ti-send', 'color' => 'info', 'title' => 'Requisición enviada'],
                    'requisition_rejected' => ['icon' => 'ti ti-alert-circle', 'color' => 'danger', 'title' => 'Requisición rechazada'],
                    'requisition_in_quotation' => ['icon' => 'ti ti-shopping-cart', 'color' => 'primary', 'title' => 'Requisición en cotización'],
                    'requisition_reactivated' => ['icon' => 'ti ti-player-play', 'color' => 'success', 'title' => 'Requisición reactivada'],
                    'new_requisition_for_purchasing' => ['icon' => 'ti ti-clipboard-list', 'color' => 'primary', 'title' => 'Nueva requisición'],
                    'new_direct_purchase_order' => ['icon' => 'ti ti-file-dollar', 'color' => 'warning', 'title' => 'Nueva OC directa'],
                    'direct_purchase_order_approved' => ['icon' => 'ti ti-circle-check', 'color' => 'success', 'title' => 'OC directa aprobada'],
                    'direct_purchase_order_rejected' => ['icon' => 'ti ti-circle-x', 'color' => 'danger', 'title' => 'OC directa rechazada'],
                    'direct_purchase_order_returned' => ['icon' => 'ti ti-arrow-back-up', 'color' => 'warning', 'title' => 'OC directa devuelta'],
                    'direct_purchase_order_inactivity_warning' => ['icon' => 'ti ti-alert-triangle', 'color' => 'warning', 'title' => 'OC directa por vencer'],
                    'direct_purchase_order_closed' => ['icon' => 'ti ti-lock', 'color' => 'danger', 'title' => 'OC directa cerrada'],
                    'purchase_order_inactivity_warning' => ['icon' => 'ti ti-alert-triangle', 'color' => 'warning', 'title' => 'Orden por vencer'],
                    'purchase_order_closed' => ['icon' => 'ti ti-lock', 'color' => 'danger', 'title' => 'Orden cerrada'],
                    'quotation_approval_request' => ['icon' => 'ti ti-scale', 'color' => 'warning', 'title' => 'Aprobación requerida'],
                    'quotation_approval_approved' => ['icon' => 'ti ti-circle-check', 'color' => 'success', 'title' => 'Cotización aprobada'],
                    'quotation_approval_rejected' => ['icon' => 'ti ti-circle-x', 'color' => 'danger', 'title' => 'Cotización rechazada'],
                    'reception_completed' => ['icon' => 'ti ti-package', 'color' => 'success', 'title' => 'Recepción registrada'],
                    'supplier_invoice_uploaded' => ['icon' => 'ti ti-file-upload', 'color' => 'info', 'title' => 'Factura cargada'],
                    'financial_provision_pending_invoice' => ['icon' => 'ti ti-receipt', 'color' => 'warning', 'title' => 'Factura pendiente'],
                    'financial_provision_discrepancy' => ['icon' => 'ti ti-alert-hexagon', 'color' => 'danger', 'title' => 'Diferencia financiera'],
                    'new_supplier_registration' => ['icon' => 'ti ti-user-plus', 'color' => 'info', 'title' => 'Nuevo proveedor registrado'],
                    'staff_welcome' => ['icon' => 'ti ti-user-check', 'color' => 'success', 'title' => 'Bienvenida al portal'],
                    'new_product_requested' => ['icon' => 'ti ti-package-import', 'color' => 'primary', 'title' => 'Producto solicitado'],
                ];
            @endphp
            <div class="topbar-item">
                <div class="dropdown">
                    <button class="topbar-link dropdown-toggle drop-arrow-none" data-bs-toggle="dropdown" data-bs-offset="0,23" type="button" data-bs-auto-close="outside" aria-haspopup="false" aria-expanded="false">
                        <i class="ti ti-bell fs-24"></i>
                        @if($unreadNotificationsCount > 0)
                            <span class="noti-icon-badge badge text-bg-danger">{{ $unreadNotificationsCount > 99 ? '99+' : $unreadNotificationsCount }}</span>
                        @endif
                    </button>

                    <div class="dropdown-menu p-0 dropdown-menu-end dropdown-menu-lg fs-13">
                        <div class="py-2 px-3 border-bottom border-dashed">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h6 class="m-0 fs-16 fw-semibold">Notificaciones</h6>
                                    <small class="text-muted">{{ $unreadNotificationsCount }} sin leer</small>
                                </div>
                                <div class="col-auto">
                                    @if($unreadNotificationsCount > 0)
                                        <form method="POST" action="{{ route('notifications.read-all') }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-light">Marcar todas</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="position-relative z-2 shadow-none rounded-0" style="max-height: 340px;" data-simplebar>
                            @forelse($recentNotifications as $notification)
                                @php
                                    $type = $notification->data['type'] ?? null;
                                    $style = $notificationStyles[$type] ?? ['icon' => 'ti ti-bell', 'color' => 'secondary', 'title' => 'Notificación'];
                                    $message = $notification->data['message'] ?? 'Tienes una nueva notificación.';
                                @endphp
                                <div class="dropdown-item notification-item py-2 text-wrap {{ $notification->read_at ? '' : 'active' }}">
                                    <div class="d-flex align-items-start">
                                        <div class="avatar flex-shrink-0 me-2">
                                            <span class="avatar-title text-bg-{{ $style['color'] }} rounded-circle fs-22">
                                                <i class="{{ $style['icon'] }}"></i>
                                            </span>
                                        </div>
                                        <div class="flex-grow-1">
                                            <a href="{{ route('notifications.open', $notification->id) }}" class="text-decoration-none d-block">
                                                <span class="fw-medium text-body">{{ $style['title'] }}</span>
                                                <span class="d-block text-muted">{{ $message }}</span>
                                                <span class="fs-12 text-muted">{{ $notification->created_at->diffForHumans() }}</span>
                                            </a>
                                        </div>
                                        @if(! $notification->read_at)
                                            <div class="ms-2">
                                                <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-ghost-secondary rounded-circle btn-sm btn-icon" title="Marcar como leída">
                                                        <i class="ti ti-check fs-16"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="px-3 py-4 text-center text-muted">
                                    <i class="ti ti-bell-off fs-28 d-block mb-2"></i>
                                    No tienes notificaciones recientes.
                                </div>
                            @endforelse
                        </div>

                        <a href="{{ route('notifications.index') }}" class="dropdown-item notification-item text-center text-reset text-decoration-underline link-offset-2 fw-bold notify-item border-top border-light py-2">
                            Ver todas
                        </a>
                    </div>
                </div>
            </div>
            @if(false)
            <!-- Notification Dropdown -->
            <div class="topbar-item">
                <div class="dropdown">
                    <button class="topbar-link dropdown-toggle drop-arrow-none" data-bs-toggle="dropdown" data-bs-offset="0,23" type="button" data-bs-auto-close="outside" aria-haspopup="false" aria-expanded="false">
                        <i class="ti ti-bell-z fs-24"></i>
                        <span class="noti-icon-badge badge text-bg-danger">02</span>
                    </button>

                    <div class="dropdown-menu p-0 dropdown-menu-end dropdown-menu-lg fs-13">
                        <div class="py-2 px-3 border-bottom border-dashed">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h6 class="m-0 fs-16 fw-semibold"> Notificaciones</h6>
                                </div>
                                <div class="col-auto">
                                    <div class="dropdown">
                                        <a href="#" class="dropdown-toggle drop-arrow-none link-dark" data-bs-toggle="dropdown" data-bs-offset="0,15" aria-expanded="false">
                                            <i class="ti ti-settings fs-22 align-middle"></i>
                                        </a>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <!-- item-->
                                            <a href="javascript:void(0);" class="dropdown-item">Mark as Read</a>
                                            <!-- item-->
                                            <a href="javascript:void(0);" class="dropdown-item">Delete All</a>
                                            <!-- item-->
                                            <a href="javascript:void(0);" class="dropdown-item">Do not Disturb</a>
                                            <!-- item-->
                                            <a href="javascript:void(0);" class="dropdown-item">Other Settings</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="position-relative z-2 shadow-none rounded-0" style="max-height: 300px;" data-simplebar>
                            <!-- Document Approval Notification -->
                            <div class="dropdown-item notification-item py-2 text-wrap active" id="notification-1">
                                <span class="d-flex align-items-center">
                                    <div class="avatar flex-shrink-0 me-2">
                                        <span class="avatar-title text-bg-success rounded-circle fs-22">
                                            <i class="ti ti-file-check"></i>
                                        </span>
                                    </div>
                                    <span class="flex-grow-1 text-muted">
                                        <span class="fw-medium text-body">Documento Aprobado</span> El documento <span class="fw-medium text-body">"Informe Trimestral Q4 2023"</span> ha sido aprobado
                                        <br />
                                        <span class="fs-12">Hace 25 min</span>
                                    </span>
                                    <span class="notification-item-close">
                                        <button type="button" class="btn btn-ghost-danger rounded-circle btn-sm btn-icon" data-dismissible="#notification-1">
                                            <i class="ti ti-x fs-16"></i>
                                        </button>
                                    </span>
                                </span>
                            </div>

                            <!-- Document Review Request -->
                            <div class="dropdown-item notification-item py-2 text-wrap" id="notification-2">
                                <span class="d-flex align-items-center">
                                    <div class="avatar flex-shrink-0 me-2">
                                        <span class="avatar-title text-bg-warning rounded-circle fs-22">
                                            <i class="ti ti-edit"></i>
                                        </span>
                                    </div>
                                    <span class="flex-grow-1 text-muted">
                                        <span class="fw-medium text-body">Revisión Solicitada</span> Se te ha asignado la revisión del documento <span class="fw-medium text-body">"Contrato Proveedor 2024"</span>
                                        <br />
                                        <span class="fs-12">Hace 1h</span>
                                    </span>
                                    <span class="notification-item-close">
                                        <button type="button" class="btn btn-ghost-danger rounded-circle btn-sm btn-icon" data-dismissible="#notification-2">
                                            <i class="ti ti-x fs-16"></i>
                                        </button>
                                    </span>
                                </span>
                            </div>

                            <!-- New Document Upload -->
                            <div class="dropdown-item notification-item py-2 text-wrap" id="notification-3">
                                <span class="d-flex align-items-center">
                                    <div class="avatar flex-shrink-0 me-2">
                                        <span class="avatar-title text-bg-info rounded-circle fs-22">
                                            <i class="ti ti-upload"></i>
                                        </span>
                                    </div>
                                    <span class="flex-grow-1 text-muted">
                                        <span class="fw-medium text-body">Nuevo Documento</span> <span class="fw-medium text-body">Carlos M.</span> ha subido un nuevo archivo: <span class="fw-medium text-body">"Presupuesto_2024.xlsx"</span>
                                        <br />
                                        <span class="fs-12">Hace 2h</span>
                                    </span>
                                    <span class="notification-item-close">
                                        <button type="button" class="btn btn-ghost-danger rounded-circle btn-sm btn-icon" data-dismissible="#notification-3">
                                            <i class="ti ti-x fs-16"></i>
                                        </button>
                                    </span>
                                </span>
                            </div>

                            <!-- Document Expiration Warning -->
                            <div class="dropdown-item notification-item py-2 text-wrap" id="notification-4">
                                <span class="d-flex align-items-center">
                                    <div class="avatar flex-shrink-0 me-2">
                                        <span class="avatar-title text-bg-danger rounded-circle fs-22">
                                            <i class="ti ti-alert-triangle"></i>
                                        </span>
                                    </div>
                                    <span class="flex-grow-1 text-muted">
                                        <span class="fw-medium text-body">Documento por Vencer</span> El certificado <span class="fw-medium text-body">"ISO 9001:2015"</span> vencerá en 15 días
                                        <br />
                                        <span class="fs-12">Ayer</span>
                                    </span>
                                    <span class="notification-item-close">
                                        <button type="button" class="btn btn-ghost-danger rounded-circle btn-sm btn-icon" data-dismissible="#notification-4">
                                            <i class="ti ti-x fs-16"></i>
                                        </button>
                                    </span>
                                </span>
                            </div>
                        </div>

                        <!-- All-->
                        <a href="javascript:void(0);" class="dropdown-item notification-item text-center text-reset text-decoration-underline link-offset-2 fw-bold notify-item border-top border-light py-2">
                            View All
                        </a>
                    </div>
                </div>
            </div>

            @endif

            <!-- Button Trigger Customizer Offcanvas -->
            <div class="topbar-item d-none d-sm-flex">
                <button class="topbar-link" data-bs-toggle="offcanvas" data-bs-target="#theme-settings-offcanvas" type="button">
                    <i class="ti ti-settings fs-22"></i>
                </button>
            </div>

            <!-- Light/Dark Mode Button -->
            <div class="topbar-item d-none d-sm-flex">
                <button class="topbar-link" id="light-dark-mode" type="button">
                    <i class="ti ti-moon fs-22"></i>
                </button>
            </div>

            <!-- User Dropdown -->
            <div class="topbar-item nav-user">
                <div class="dropdown">
                    <a class="topbar-link dropdown-toggle drop-arrow-none px-2" data-bs-toggle="dropdown" data-bs-offset="0,19" type="button" aria-haspopup="false" aria-expanded="false">
                        <img src="{{ !$isSupplierGuard && Auth::user()?->avatar ? asset('storage/' . Auth::user()?->avatar) : asset('images/users/avatar-1.jpg') }}" width="32" class="rounded-circle me-lg-2 d-flex" alt="user-image">
                        <span class="d-lg-flex flex-column gap-1 d-none">
                            <h5 class="my-0 fs-13 fw-semibold">{{ $displayName }}</h5>
                        </span>
                        <i class="ti ti-chevron-down d-none d-lg-block align-middle ms-2"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end">
                        <!-- item-->
                        <div class="dropdown-header noti-title">
                            <h6 class="text-overflow m-0">¡Bienvenido, {{ $displayName }}!</h6>
                        </div>

                        <!-- item-->
                        @if($isSupplierGuard)
                            <a href="{{ route('supplier.documents.index') }}" class="dropdown-item">
                                <i class="ti ti-user-hexagon me-1 fs-17 align-middle"></i>
                                <span class="align-middle">Mi cuenta</span>
                            </a>
                        @else
                            <a href="javascript:void(0);" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#profileModal">
                                <i class="ti ti-user-hexagon me-1 fs-17 align-middle"></i>
                                <span class="align-middle">Mi cuenta</span>
                            </a>
                        @endif

                        <!-- item-->
                        <a href="javascript:void(0);" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#reportIssueModal">
                            <i class="ti ti-lifebuoy me-1 fs-17 align-middle"></i>
                            <span class="align-middle">Soporte</span>
                        </a>

                        <div class="dropdown-divider"></div>

                        <!-- item-->
                        @unless($isSupplierGuard)
                            <form id="lock-form" method="POST" action="{{ route('lockscreen.lock') }}">
                                @csrf
                                <button type="submit" class="dropdown-item"><i class="ti ti-lock-square-rounded me-1 fs-17 align-middle"></i> Bloquear pantalla</button>
                            </form>
                        @endunless

                        <!-- item-->
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="dropdown-item fw-semibold text-danger">
                                <i class="ti ti-logout me-1 fs-17 align-middle"></i>
                                <span class="align-middle">Cerrar sesión</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
<!-- Topbar End -->

<!-- Theme Settings Offcanvas -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="theme-settings-offcanvas" aria-labelledby="themeSettingsLabel">
    <div class="offcanvas-header border-bottom py-3">
        <h5 class="offcanvas-title" id="themeSettingsLabel">
            <i class="ti ti-settings me-2 text-primary"></i>Configuración
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>

    <div class="offcanvas-body p-0" data-simplebar>

        {{-- ── Modo Claro / Oscuro ─────────────────────────────── --}}
        <div class="p-3 border-bottom">
            <p class="fw-semibold text-uppercase text-muted fs-11 mb-2">Modo</p>
            <div class="btn-group w-100" role="group" aria-label="Modo de color">
                <input type="radio" class="btn-check" name="data-bs-theme"
                       id="layout-mode-light" value="light" autocomplete="off">
                <label class="btn btn-outline-secondary" for="layout-mode-light">
                    <i class="ti ti-sun me-1"></i>Claro
                </label>

                <input type="radio" class="btn-check" name="data-bs-theme"
                       id="layout-mode-dark" value="dark" autocomplete="off">
                <label class="btn btn-outline-secondary" for="layout-mode-dark">
                    <i class="ti ti-moon me-1"></i>Oscuro
                </label>
            </div>
        </div>

        {{-- ── Tamaño del Sidebar ───────────────────────────────── --}}
        <div class="p-3 border-bottom">
            <p class="fw-semibold text-uppercase text-muted fs-11 mb-2">Tamaño del Sidebar</p>
            <div class="btn-group w-100" role="group" aria-label="Tamaño del sidebar">
                <input type="radio" class="btn-check" name="data-sidenav-size"
                       id="sidenav-size-default" value="default" autocomplete="off">
                <label class="btn btn-outline-secondary" for="sidenav-size-default">
                    <i class="ti ti-layout-sidebar me-1"></i>Normal
                </label>

                <input type="radio" class="btn-check" name="data-sidenav-size"
                       id="sidenav-size-compact" value="compact" autocomplete="off">
                <label class="btn btn-outline-secondary" for="sidenav-size-compact">
                    <i class="ti ti-layout-sidebar-right-collapse me-1"></i>Compacto
                </label>
            </div>
        </div>

        {{-- ── Colores (solo modo claro) ──────────────────────────── --}}
        <div class="light-only-section">

            {{-- Aviso --}}
            <div class="px-3 pt-3 pb-0">
                <p class="text-muted mb-0" style="font-size:10px;line-height:1.4;">
                    <i class="ti ti-info-circle me-1"></i>
                    Las opciones de color solo aplican en modo <strong>claro</strong>.
                </p>
            </div>

            {{-- Color del Topbar --}}
            <div class="p-3 border-bottom">
                <p class="fw-semibold text-uppercase text-muted fs-11 mb-2">Color del Topbar</p>
                <div class="btn-group w-100" role="group" aria-label="Color del topbar">
                    <input type="radio" class="btn-check" name="data-topbar-color"
                           id="topbar-color-light" value="light" autocomplete="off">
                    <label class="btn btn-outline-secondary" for="topbar-color-light">
                        <i class="ti ti-sun me-1"></i>Claro
                    </label>

                    <input type="radio" class="btn-check" name="data-topbar-color"
                           id="topbar-color-dark" value="dark" autocomplete="off">
                    <label class="btn btn-outline-secondary" for="topbar-color-dark">
                        <i class="ti ti-moon me-1"></i>Oscuro
                    </label>
                </div>
            </div>

            {{-- Color del Sidebar --}}
            <div class="p-3 border-bottom">
                <p class="fw-semibold text-uppercase text-muted fs-11 mb-2">Color del Sidebar</p>
                <div class="btn-group w-100" role="group" aria-label="Color del sidebar">
                    <input type="radio" class="btn-check" name="data-menu-color"
                           id="menu-color-light" value="light" autocomplete="off">
                    <label class="btn btn-outline-secondary" for="menu-color-light">
                        <i class="ti ti-sun me-1"></i>Claro
                    </label>

                    <input type="radio" class="btn-check" name="data-menu-color"
                           id="menu-color-dark" value="dark" autocomplete="off">
                    <label class="btn btn-outline-secondary" for="menu-color-dark">
                        <i class="ti ti-moon me-1"></i>Oscuro
                    </label>
                </div>
            </div>

        </div>{{-- /light-only-section --}}

        {{-- ── Zoom / Accesibilidad ─────────────────────────────── --}}
        <div class="p-3 border-bottom">
            <p class="fw-semibold text-uppercase text-muted fs-11 mb-2">
                <i class="ti ti-accessible me-1"></i>Zoom de Página
            </p>
            <div class="d-flex align-items-center gap-2">
                <button id="zoom-out" type="button"
                        class="btn btn-outline-secondary btn-sm flex-shrink-0"
                        style="width:36px;height:36px;padding:0;" title="Reducir zoom">
                    <i class="ti ti-minus fs-16"></i>
                </button>

                <div class="flex-grow-1 text-center">
                    <span id="zoom-display"
                          class="fw-bold fs-16 text-body">100%</span>
                    <div class="text-muted" style="font-size:10px;line-height:1.2;">70% – 130%</div>
                </div>

                <button id="zoom-reset" type="button"
                        class="btn btn-outline-secondary btn-sm flex-shrink-0 px-2"
                        title="Restablecer zoom al 100%">
                    <i class="ti ti-refresh fs-14 me-1"></i><span style="font-size:11px;">100%</span>
                </button>

                <button id="zoom-in" type="button"
                        class="btn btn-outline-secondary btn-sm flex-shrink-0"
                        style="width:36px;height:36px;padding:0;" title="Aumentar zoom">
                    <i class="ti ti-plus fs-16"></i>
                </button>
            </div>
        </div>

        {{-- ── Botón restablecer todo ───────────────────────────── --}}
        <div class="p-3">
            <button type="button" class="btn btn-outline-danger btn-sm w-100" id="reset-theme-settings">
                <i class="ti ti-restore me-1"></i>Restablecer todo
            </button>
        </div>

    </div>{{-- /offcanvas-body --}}
</div>
<!-- End Theme Settings Offcanvas -->
