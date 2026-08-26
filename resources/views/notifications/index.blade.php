@extends(auth('supplier')->check() ? 'layouts.zircos-supplier' : 'layouts.zircos')

@section('title', 'Notificaciones')

@section('page.title', 'Notificaciones')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ auth('supplier')->check() ? route('supplier.documents.index') : route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Notificaciones</li>
@endsection

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
        'supplier_document_accepted' => ['icon' => 'ti ti-file-check', 'color' => 'success', 'title' => 'Documento aprobado'],
        'supplier_document_rejected' => ['icon' => 'ti ti-file-x', 'color' => 'danger', 'title' => 'Documento rechazado'],
        'supplier_document_file_completed' => ['icon' => 'ti ti-checklist', 'color' => 'success', 'title' => 'Expediente completo'],
        'supplier_document_renewal' => ['icon' => 'ti ti-calendar-exclamation', 'color' => 'warning', 'title' => 'Renovación documental'],
        'supplier_document_auto_accepted' => ['icon' => 'ti ti-file-check', 'color' => 'success', 'title' => 'Documento validado'],
        'supplier_account_approved' => ['icon' => 'ti ti-user-check', 'color' => 'success', 'title' => 'Alta aprobada'],
        'supplier_account_rejected' => ['icon' => 'ti ti-user-x', 'color' => 'danger', 'title' => 'Alta rechazada'],
        'staff_welcome' => ['icon' => 'ti ti-user-check', 'color' => 'success', 'title' => 'Bienvenida al portal'],
        'new_product_requested' => ['icon' => 'ti ti-package-import', 'color' => 'primary', 'title' => 'Producto solicitado'],
        'mail_delivery_failed' => ['icon' => 'ti ti-mail-x', 'color' => 'danger', 'title' => 'Fallo de correo'],
    ];
@endphp

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0">Historial de notificaciones</h5>
                <small class="text-muted">Aquí puedes consultar todas las notificaciones recibidas en el portal.</small>
            </div>
            @if($notifications->count() > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-primary">
                        <i class="ti ti-checks me-1"></i>Marcar todas como leídas
                    </button>
                </form>
            @endif
        </div>

        <div class="card-body p-0">
            @forelse($notifications as $notification)
                @php
                    $type = $notification->data['type'] ?? null;
                    $style = $notificationStyles[$type] ?? ['icon' => 'ti ti-bell', 'color' => 'secondary', 'title' => 'Notificación'];
                    $message = $notification->data['message'] ?? 'Tienes una nueva notificación.';
                    $targetUrl = $notification->data['url'] ?? route('notifications.index');
                @endphp
                <div class="border-bottom px-3 py-3 {{ $notification->read_at ? '' : 'bg-light-subtle' }}">
                    <div class="d-flex align-items-start gap-3">
                        <div class="avatar flex-shrink-0">
                            <span class="avatar-title text-bg-{{ $style['color'] }} rounded-circle fs-22">
                                <i class="{{ $style['icon'] }}"></i>
                            </span>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                <div>
                                    <h6 class="mb-1">
                                        {{ $style['title'] }}
                                        @if(! $notification->read_at)
                                            <span class="badge bg-danger-subtle text-danger ms-2">Nueva</span>
                                        @endif
                                    </h6>
                                    <p class="mb-1 text-muted">{{ $message }}</p>
                                    <small class="text-muted">{{ $notification->created_at->diffForHumans() }}</small>
                                </div>
                                <div class="d-flex gap-2">
                                    <a href="{{ route('notifications.open', $notification->id) }}" class="btn btn-sm btn-primary">
                                        Abrir
                                    </a>
                                    @if(! $notification->read_at)
                                        <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                Marcar leída
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>

                            @if($targetUrl !== route('notifications.index'))
                                <div class="mt-2">
                                    <small class="text-muted">Destino:</small>
                                    <a href="{{ route('notifications.open', $notification->id) }}" class="small text-decoration-none">
                                        Ir al detalle relacionado
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-5">
                    <i class="ti ti-bell-off fs-1 text-muted d-block mb-3"></i>
                    <h5 class="mb-1">Sin notificaciones</h5>
                    <p class="text-muted mb-0">Cuando ocurra algo relevante en el sistema, aparecerá aquí.</p>
                </div>
            @endforelse
        </div>

        @if($notifications->hasPages())
            <div class="card-footer">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
