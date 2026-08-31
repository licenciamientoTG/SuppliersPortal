@extends('layouts.zircos')

@section('title', 'Detalle de OCD: ' . ($directPurchaseOrder->folio ?? 'Borrador'))

@section('page.title', 'Detalle de OCD')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('purchase-orders.index') }}">Órdenes de Compra</a></li>
    <li class="breadcrumb-item active">{{ $directPurchaseOrder->folio ?? 'Borrador' }}</li>
@endsection

@push('styles')
<style>
    @media print {
        .d-print-none { display: none !important; }
        .card { box-shadow: none !important; border: 0 !important; }
        body { background-color: white !important; }
        .sidenav-menu, .topbar { display: none !important; }
        .page-content { padding: 0 !important; margin: 0 !important; }
        .content-page { margin: 0 !important; }
        footer { display: none !important; }
    }
    .info-label {
        font-size: 0.7rem; text-transform: uppercase;
        letter-spacing: 0.05em; color: #6c757d; font-weight: 600; margin-bottom: 2px;
    }
    .info-value { font-size: 0.875rem; color: #212529; }
    .timeline-step { display: flex; align-items: flex-start; gap: 0.75rem; }
    .timeline-icon { width: 32px; height: 32px; flex-shrink: 0; border-radius: 50%;
        display: flex; align-items: center; justify-content: center; font-size: 0.85rem; }
    .ocd-budget-impact { padding: 1rem; border: 1px solid #e2e9f0; border-radius: .75rem; background: #f7fbff; }
    .ocd-budget-heading { color: #243b53; font-weight: 700; }
    .ocd-budget-metric { height: 100%; padding: .8rem; border: 1px solid #e8eef4; border-radius: .65rem; background: #fff; }
    .ocd-budget-metric span { display: block; margin-bottom: .25rem; color: #718096; font-size: .72rem; font-weight: 600; text-transform: uppercase; }
    .ocd-budget-metric strong { color: #25364a; font-size: 1.05rem; }
    .ocd-budget-metric.committed { cursor: pointer; border-color: #f6dfad; background: #fffaf0; }
    .ocd-budget-metric.committed:focus, .ocd-budget-metric.committed:hover { outline: 2px solid #e6bd63; outline-offset: 2px; }
    .ocd-budget-metric.committed small { display: block; margin-top: .25rem; color: #947217; font-size: .7rem; }
    .ocd-budget-metric.available { border-color: #ccefe0; background: #f2fcf7; }
    .ocd-budget-metric.available strong { color: #21875d; }
    .ocd-budget-metric.request { border-color: #cfe7f8; background: #f1f8fe; }
    .ocd-budget-metric.request strong { color: #188ae2; }
    .ocd-budget-line { overflow: hidden; border: 1px solid #e2e9f0; border-radius: .65rem; background: #fff; }
    .ocd-budget-line-main { padding: .85rem; }
    .ocd-budget-line-main strong { display: block; color: #25364a; font-size: .88rem; }
    .ocd-budget-line-main small { color: #718096; }
    .ocd-commitment-toggle { width: 100%; padding: .65rem .85rem; border: 0; border-top: 1px solid #e2e9f0; background: #f7fbff; color: #176eaf; text-align: left; font-size: .8rem; font-weight: 600; }
    .ocd-commitment-detail { padding: .75rem .85rem; background: #fff; }
    .ocd-commitment-item { display: grid; grid-template-columns: auto 1fr auto; gap: .75rem; align-items: center; padding: .6rem 0; border-bottom: 1px solid #edf1f5; }
    .ocd-commitment-item:last-child { border-bottom: 0; }
    .ocd-commitment-item small { color: #718096; }
    .ocd-commitment-item.current { padding: .75rem; border: 1px solid #b9def7; border-radius: .5rem; background: #f1f8fe; }
    .ocd-commitment-item.untraced { padding: .75rem; border: 1px dashed #e6bd63; border-radius: .5rem; background: #fffaf0; }
    .ocd-commitment-pagination { display: flex; justify-content: space-between; align-items: center; gap: .5rem; margin-top: .75rem; padding-top: .75rem; border-top: 1px solid #edf1f5; }
    @media (prefers-reduced-motion: reduce) { .ocd-budget-impact *, .ocd-budget-impact *::before, .ocd-budget-impact *::after { transition: none !important; } }
</style>
@endpush

@section('content')
@if($directPurchaseOrder->isPendingApproval() && (int) $directPurchaseOrder->assigned_approver_id !== (int) auth()->id() && $directPurchaseOrder->isApproverFor(auth()->user()))
    <div class="alert alert-info border-0 shadow-sm">
        <i class="ti ti-user-share me-1"></i>
        <strong>Autorización delegada por {{ $directPurchaseOrder->assignedApprover?->name }}.</strong>
        Si actúas, quedará registrado que lo hiciste en su representación.
    </div>
@endif
@php
    $delegatedDecision = $directPurchaseOrder->approvalDecisions
        ->whereNotNull('approval_delegation_id')
        ->sortByDesc('acted_at')
        ->first();
@endphp
@if($delegatedDecision)
    @php
        $delegatedActionLabel = match($delegatedDecision->action) {
            'APPROVED' => 'Autorizado',
            'REJECTED' => 'Rechazado',
            'RETURNED' => 'Devuelto',
            default => $delegatedDecision->action,
        };
    @endphp
    <div class="alert alert-light border">
        <i class="ti ti-history me-1"></i>
        {{ $delegatedActionLabel }} por
        <strong>{{ $delegatedDecision->actor?->name }}</strong>,
        en representación de <strong>{{ $delegatedDecision->principal?->name }}</strong>
        el {{ $delegatedDecision->acted_at?->format('d/m/Y H:i') }}.
    </div>
@endif

@php
    $ocd = $directPurchaseOrder;
    $lastRejection = $ocd->approvals->where('action', 'rejected')->sortByDesc('created_at')->first();
    $lastReturn    = $ocd->approvals->where('action', 'returned')->sortByDesc('created_at')->first();
    $autoCloseIn   = $ocd->getDaysUntilAutoClose();
    $canApproveOcd = $ocd->isPendingApproval() && $ocd->isApproverFor(Auth::user());
    $canReturnIssuedOcd = $ocd->canBeReturnedToRevision()
        && (Auth::user()->hasRole('superadmin') || (int) $ocd->approved_by === (int) Auth::id());
@endphp

<div class="row">
    <div class="col-md-12">

        {{-- ─── Alertas de sesión ─── --}}
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if(session('warning'))
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                {{ session('warning') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- ─── Alerta: OCD Rechazada ─── --}}
        @if($ocd->isRejected() && $lastRejection)
            <div class="alert alert-danger border-2 d-print-none">
                <div class="d-flex gap-3">
                    <i class="ti ti-ban fs-3 flex-shrink-0"></i>
                    <div>
                        <h6 class="fw-bold mb-1">ORDEN RECHAZADA</h6>
                        <p class="mb-1 small"><strong>Motivo:</strong> {{ $lastRejection->comments }}</p>
                        <small class="text-muted">
                            Por <strong>{{ $lastRejection->approver->name }}</strong>
                            el {{ $lastRejection->created_at->format('d/m/Y H:i') }}
                        </small>
                    </div>
                </div>
            </div>
        @endif

        {{-- ─── Alerta: OCD Devuelta ─── --}}
        @if($ocd->isReturned() && $lastReturn)
            <div class="alert alert-info border-2 d-print-none">
                <div class="d-flex gap-3">
                    <i class="ti ti-arrow-back-up fs-3 flex-shrink-0"></i>
                    <div>
                        <h6 class="fw-bold mb-1">ORDEN DEVUELTA PARA CORRECCIÓN</h6>
                        <p class="mb-1 small"><strong>Instrucciones:</strong> {{ $lastReturn->comments }}</p>
                        <small class="text-muted">
                            Por <strong>{{ $lastReturn->approver->name }}</strong>
                            el {{ $lastReturn->created_at->format('d/m/Y H:i') }}
                        </small>
                    </div>
                </div>
            </div>
        @endif

        {{-- ─── Alerta: Próximo cierre automático ─── --}}
        @if($ocd->isPendingApproval() && $autoCloseIn !== null && $autoCloseIn <= 3)
            <div class="alert alert-warning border-2 d-print-none">
                <i class="ti ti-clock me-2"></i>
                @if($autoCloseIn <= 0)
                    <strong>Esta OCD será cerrada automáticamente hoy por inactividad.</strong>
                @else
                    <strong>Atención:</strong> Esta OCD se cerrará automáticamente por inactividad en
                    <strong>{{ $autoCloseIn }} día(s)</strong>
                    ({{ $ocd->getAutoCloseDeadline()->format('d/m/Y') }}).
                @endif
            </div>
        @endif

        <div class="card shadow-sm border-0 mb-4">

            {{-- ─── Barra de acciones ─── --}}
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center d-print-none">
                <div>
                    <a href="{{ route('purchase-orders.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="ti ti-arrow-left me-1"></i>Regresar
                    </a>
                    @if($ocd->canBeEdited() && $ocd->created_by === Auth::id())
                        <a href="{{ route('direct-purchase-orders.edit', $ocd->id) }}" class="btn btn-sm btn-outline-warning ms-1">
                            <i class="ti ti-edit me-1"></i>Editar
                        </a>
                    @endif
                </div>
                <div class="d-flex gap-2 align-items-center">
                    @if($ocd->status === 'DRAFT' || $ocd->status === 'RETURNED')
                        @if($ocd->created_by === Auth::id())
                            <form action="{{ route('direct-purchase-orders.submit', $ocd->id) }}" method="POST" id="form-submit">
                                @csrf
                                <button type="button" onclick="confirmAction('form-submit', '¿Estás seguro de enviar esta orden a aprobación?')" class="btn btn-sm btn-primary">
                                    <i class="ti ti-send me-1"></i>Enviar a Aprobación
                                </button>
                            </form>
                        @endif
                    @endif
                    @if($canApproveOcd)
                        <button type="button" class="btn btn-sm btn-success" onclick="confirmApproval()">
                            <i class="ti ti-check me-1"></i>Aprobar OC
                        </button>
                        <button type="button" class="btn btn-sm btn-info" onclick="confirmReturn()">
                            <i class="ti ti-arrow-back-up me-1"></i>Devolver
                        </button>
                        <button type="button" class="btn btn-sm btn-danger" onclick="confirmReject()">
                            <i class="ti ti-x me-1"></i>Rechazar OC
                        </button>
                    @endif
                    @if($canReturnIssuedOcd)
                        <button type="button" class="btn btn-sm btn-warning" onclick="confirmReturn()">
                            <i class="ti ti-arrow-back-up me-1"></i>Devolver a Revisión
                        </button>
                    @endif
                    @if(in_array($ocd->status, ['ISSUED', 'PARTIALLY_RECEIVED', 'RECEIVED', 'DELIVERED_PENDING_RECEPTION'], true))
                        <a href="{{ route('direct-purchase-orders.pdf', $ocd) }}" class="btn btn-sm btn-outline-danger" title="Descargar PDF">
                            <i class="ti ti-file-type-pdf me-1"></i>PDF
                        </a>
                        <a href="{{ route('direct-purchase-orders.word', $ocd) }}" class="btn btn-sm btn-outline-primary" title="Descargar Word editable">
                            <i class="ti ti-file-type-doc me-1"></i>Word
                        </a>
                    @endif
                    <button onclick="window.print();" class="btn btn-sm btn-outline-primary">
                        <i class="ti ti-printer me-1"></i>Imprimir
                    </button>
                </div>
            </div>

            <div class="card-body p-5" id="printable-area">

                {{-- ═══════════════════════════════════════════════
                     ENCABEZADO: Logo + Folio + Estado
                ═══════════════════════════════════════════════ --}}
                <div class="row mb-4 align-items-start">
                    <div class="col-6">
                        <img src="{{ asset('images/logos/logo_TotalGas_hor.png') }}" alt="{{ $issuingCompany?->legal_name ?? $issuingCompany?->name ?? 'Empresa emisora' }}" height="50" class="mb-3">
                        <h6 class="text-muted fw-bold mb-1">{{ $issuingCompany?->legal_name ?? $issuingCompany?->name ?? 'Empresa no identificada' }}</h6>
                        <p class="text-muted small mb-0">
                            RFC: {{ $issuingCompany?->rfc ?? 'No registrado' }}
                        </p>
                    </div>
                    <div class="col-6 text-end">
                        <h3 class="text-primary fw-bold mb-1">ORDEN DE COMPRA DIRECTA</h3>
                        <h4 class="text-dark mb-3">{{ $ocd->folio ?? 'BORRADOR' }}</h4>
                        <div class="text-muted small">
                            <strong>Fecha de Solicitud:</strong> {{ $ocd->created_at->format('d/m/Y H:i') }}<br>
                            @if($ocd->submitted_at)
                                <strong>Enviada a Aprobación:</strong> {{ $ocd->submitted_at->format('d/m/Y H:i') }}<br>
                            @endif
                            @if($ocd->approved_at)
                                <strong>Fecha de Aprobación:</strong> {{ $ocd->approved_at->format('d/m/Y H:i') }}<br>
                            @endif
                            @if($ocd->issued_at)
                                <strong>Fecha de Emisión:</strong> {{ $ocd->issued_at->format('d/m/Y H:i') }}<br>
                            @endif
                            @if($ocd->received_at)
                                <strong>Fecha de Recepción:</strong> {{ $ocd->received_at->format('d/m/Y H:i') }}<br>
                            @endif
                            <strong>Estado:</strong>
                            <span class="badge bg-{{ $ocd->getStatusBadgeClass() }}">
                                {{ $ocd->getStatusLabel() }}
                            </span>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                {{-- ═══════════════════════════════════════════════
                     SECCIÓN: Proveedor | Control | Entrega
                ═══════════════════════════════════════════════ --}}
                <div class="row mb-4">

                    {{-- Columna 1: Proveedor --}}
                    <div class="col-md-4 border-end pe-4">
                        <h6 class="text-primary text-uppercase fw-bold fs-12 mb-3">
                            <i class="ti ti-building-store me-1"></i>Datos del Proveedor
                        </h6>
                        <h5 class="fw-bold mb-2">{{ $ocd->supplier->company_name }}</h5>
                        <div class="mb-1">
                            <span class="info-label">RFC</span>
                            <div class="info-value">{{ $ocd->supplier->rfc ?? '—' }}</div>
                        </div>
                        <div class="mb-1">
                            <span class="info-label">Contacto</span>
                            <div class="info-value">{{ $ocd->supplier->contact_name ?? '—' }}</div>
                        </div>
                        <div class="mb-0">
                            <span class="info-label">Email</span>
                            <div class="info-value">{{ $ocd->supplier->email }}</div>
                        </div>
                    </div>

                    {{-- Columna 2: Control y Presupuesto --}}
                    <div class="col-md-4 border-end px-4">
                        <h6 class="text-primary text-uppercase fw-bold fs-12 mb-3">
                            <i class="ti ti-chart-pie me-1"></i>Control y Presupuesto
                        </h6>
                        <div class="mb-1">
                            <span class="info-label">Centro de Costos</span>
                            <div class="info-value fw-semibold">{{ $ocd->primaryCostCenterLabel() }}</div>
                        </div>
                        <div class="mb-1">
                            <span class="info-label">Tipo de Compra</span>
                            <div class="info-value">{{ $ocd->primaryPurchaseType() ?? '—' }}</div>
                        </div>
                        <div class="mb-1">
                            <span class="info-label">Mes de Aplicación</span>
                            <div class="info-value">{{ $ocd->application_month }}</div>
                        </div>
                        <div class="mb-1">
                            <span class="info-label">Solicitado por</span>
                            <div class="info-value">{{ $ocd->creator->name }}</div>
                        </div>
                        @if($ocd->authorizerRole)
                        <div class="mb-1">
                            <span class="info-label">Rol autorizador aplicado</span>
                            <div class="info-value fw-semibold">{{ $ocd->authorizerRole->name }}</div>
                        </div>
                        @endif
                        <div class="mb-1">
                            <span class="info-label">Límite aplicado</span>
                            <div class="info-value">
                                {{ $ocd->effective_authorization_limit !== null ? '$'.number_format((float) $ocd->effective_authorization_limit, 2).' MXN' : 'Sin límite' }}
                            </div>
                        </div>
                        @if($ocd->budget_reserved_at)
                        <div class="mb-1">
                            <span class="info-label">Reserva presupuestal</span>
                            <div class="info-value">{{ $ocd->budget_reserved_at->format('d/m/Y H:i') }}</div>
                        </div>
                        @endif
                        @if($ocd->assignedApprover)
                        <div class="mb-1">
                            <span class="info-label">{{ $ocd->isPendingApproval() ? 'Aprobador actual' : 'Aprobador asignado' }}</span>
                            <div class="info-value fw-semibold text-primary">
                                <i class="ti ti-user-check me-1"></i>{{ $ocd->assignedApprover->name }}
                            </div>
                        </div>
                        @endif
                        @if($ocd->approver)
                        <div class="mb-0">
                            <span class="info-label">Aprobado por</span>
                            <div class="info-value text-success fw-semibold">
                                <i class="ti ti-circle-check me-1"></i>{{ $ocd->approver->name }}
                            </div>
                        </div>
                        @endif
                        @if($ocd->rejector)
                        <div class="mb-0">
                            <span class="info-label">Rechazado por</span>
                            <div class="info-value text-danger fw-semibold">
                                <i class="ti ti-ban me-1"></i>{{ $ocd->rejector->name }}
                            </div>
                        </div>
                        @endif
                    </div>

                    {{-- Columna 3: Entrega y Recepción --}}
                    <div class="col-md-4 ps-4">
                        <h6 class="text-primary text-uppercase fw-bold fs-12 mb-3">
                            <i class="ti ti-truck-delivery me-1"></i>Entrega y Recepción
                        </h6>
                        @if($ocd->receivingLocation)
                        <div class="mb-2 p-2 rounded bg-warning bg-opacity-10 border border-warning border-opacity-25">
                            <span class="info-label"><i class="ti ti-map-pin me-1 text-warning"></i>Punto de Entrega</span>
                            <div class="info-value fw-semibold">
                                {{ $ocd->receivingLocation->code }} — {{ $ocd->receivingLocation->name }}
                            </div>
                        </div>
                        @else
                        <div class="mb-2">
                            <span class="info-label">Punto de Entrega</span>
                            <div class="info-value text-muted">No especificado</div>
                        </div>
                        @endif
                        <div class="mb-1">
                            <span class="info-label">Entrega Estimada</span>
                            <div class="info-value">
                                {{ $ocd->estimated_delivery_days ? $ocd->estimated_delivery_days . ' días' : '—' }}
                            </div>
                        </div>
                        <div class="mb-1">
                            <span class="info-label">Moneda</span>
                            <div class="info-value">
                                <span class="badge {{ $ocd->currency === 'USD' ? 'bg-warning text-dark' : 'bg-light text-dark border' }}">
                                    {{ $ocd->currency ?? 'MXN' }}
                                </span>
                            </div>
                        </div>
                        @if($ocd->reception_notes)
                        <div class="mb-0">
                            <span class="info-label">Notas de Recepción</span>
                            <div class="info-value small text-muted">{{ $ocd->reception_notes }}</div>
                        </div>
                        @endif
                        @if($ocd->pdf_path)
                        <div class="mt-2">
                            <a href="{{ Storage::url($ocd->pdf_path) }}" target="_blank"
                               class="btn btn-sm btn-outline-primary">
                                <i class="ti ti-file-type-pdf me-1"></i>Ver PDF adjunto
                            </a>
                        </div>
                        @endif
                    </div>

                </div>

                {{-- ═══════════════════════════════════════════════
                     IMPACTO PRESUPUESTAL
                ═══════════════════════════════════════════════ --}}
                <section class="ocd-budget-impact mb-4">
                    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
                        <span class="ocd-budget-heading">Impacto presupuestal</span>
                        <small class="text-success fw-semibold">
                            {{ $budgetSnapshot['has_budget_totals'] ? 'Disponible actual: '.$budgetSnapshot['available_total'] : 'Centro de consumo libre o sin límite' }}
                        </small>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6 col-lg-3"><div class="ocd-budget-metric"><span>Asignado</span><strong>{{ $budgetSnapshot['assigned_total'] }}</strong></div></div>
                        <div class="col-6 col-lg-3"><div class="ocd-budget-metric committed" role="button" tabindex="0" data-ocd-open-all aria-label="Abrir detalle del saldo comprometido"><span>Comprometido</span><strong>{{ $budgetSnapshot['committed_total'] }}</strong><small>Haz clic para ver su composición</small></div></div>
                        <div class="col-6 col-lg-3"><div class="ocd-budget-metric available"><span>Disponible</span><strong>{{ $budgetSnapshot['available_total'] }}</strong></div></div>
                        <div class="col-6 col-lg-3"><div class="ocd-budget-metric request"><span>Esta OCD</span><strong>{{ $budgetSnapshot['requested_total'] }}</strong></div></div>
                    </div>

                    @if($budgetSnapshot['error'])
                        <div class="alert alert-warning py-2 px-3 mb-3">{{ $budgetSnapshot['error'] }}</div>
                    @endif

                    <div class="d-grid gap-2">
                        @forelse($budgetSnapshot['lines'] as $lineIndex => $line)
                            @php
                                $components = collect($line['committed_components'] ?? []);
                                $componentId = 'ocd-commitment-components-'.$lineIndex;
                            @endphp
                            <article class="ocd-budget-line">
                                <div class="ocd-budget-line-main">
                                    <strong>{{ $line['cost_center'] ?: 'Sin centro de costo' }}</strong>
                                    <small>{{ $line['expense_category'] }} · {{ $line['budget_cedula'] }} · {{ $line['application_month'] }}</small>
                                    @if($line['message'])
                                        <small class="d-block mt-1 {{ $line['is_available'] ? 'text-success' : 'text-danger' }}">{{ $line['message'] }}</small>
                                    @endif
                                </div>
                                <button class="ocd-commitment-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $componentId }}" aria-expanded="false" aria-controls="{{ $componentId }}">
                                    {{ $components->count() === 1 ? 'Ver 1 componente del comprometido' : 'Ver '.$components->count().' componentes del comprometido' }} <i class="ti ti-chevron-down float-end"></i>
                                </button>
                                <div class="collapse" id="{{ $componentId }}">
                                    <div class="ocd-commitment-detail" data-ocd-commitment-detail>
                                        @forelse($components as $componentIndex => $component)
                                            <div class="ocd-commitment-item {{ $component['is_current'] ? 'current' : '' }} {{ $component['is_untraced'] ? 'untraced' : '' }} {{ $componentIndex >= 5 ? 'd-none' : '' }}" data-ocd-commitment-item>
                                                <span class="badge bg-soft-primary text-primary">{{ $component['type'] }}</span>
                                                <div>
                                                    <strong class="d-block small">{{ $component['folio'] }}</strong>
                                                    <small>{{ $component['supplier'] }} · Comprometido el {{ $component['committed_at'] }}</small>
                                                    @if($component['detail'])
                                                        <small class="d-block mt-1">{{ $component['detail'] }}</small>
                                                    @endif
                                                </div>
                                                <strong>{{ $component['amount'] }}</strong>
                                            </div>
                                        @empty
                                            <p class="text-muted small mb-0">No hay compromisos activos registrados para esta combinación presupuestal.</p>
                                        @endforelse
                                        @if($components->count() > 5)
                                            <div class="ocd-commitment-pagination" data-ocd-pagination data-page="1">
                                                <button type="button" class="btn btn-sm btn-outline-secondary" data-ocd-page="previous"><i class="ti ti-chevron-left"></i> Anterior</button>
                                                <small data-ocd-page-label>Página 1 de {{ (int) ceil($components->count() / 5) }}</small>
                                                <button type="button" class="btn btn-sm btn-outline-primary" data-ocd-page="next">Siguiente <i class="ti ti-chevron-right"></i></button>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </article>
                        @empty
                            <div class="text-center text-muted py-3">No se encontró desglose presupuestal para esta OCD.</div>
                        @endforelse
                    </div>
                </section>

                {{-- ═══════════════════════════════════════════════
                      JUSTIFICACIÓN
                ═══════════════════════════════════════════════ --}}
                @if($ocd->justification)
                <div class="row mb-4">
                    <div class="col-12">
                        <h6 class="text-dark fw-bold mb-2">
                            <i class="ti ti-file-description me-1 text-primary"></i>Justificación:
                        </h6>
                        <div class="p-3 bg-light rounded border-start border-primary border-3 fst-italic text-muted small">
                            {{ $ocd->justification }}
                        </div>
                    </div>
                </div>
                @endif

                {{-- ═══════════════════════════════════════════════
                     TABLA DE PARTIDAS
                ═══════════════════════════════════════════════ --}}
                <div class="table-responsive mb-5">
                    <table class="table table-bordered table-centered mb-0">
                        <thead class="table-light">
                            <tr class="text-dark fw-bold small">
                                <th style="width: 35px">#</th>
                                <th>Descripción</th>
                                <th class="text-muted" style="width: 160px">Centro de costo</th>
                                <th class="text-muted" style="width: 110px">Cuenta</th>
                                <th class="text-center" style="width: 75px">Cant.</th>
                                <th class="text-center" style="width: 55px">UM</th>
                                <th class="text-end" style="width: 130px">P. Unitario</th>
                                <th class="text-center" style="width: 65px">IVA</th>
                                <th class="text-end" style="width: 130px">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($ocd->items as $index => $item)
                                <tr>
                                    <td class="text-center small">{{ $index + 1 }}</td>
                                    <td>
                                        <span class="fw-semibold text-dark d-block">{{ $item->description }}</span>
                                        @if($item->sku)
                                            <small class="text-muted"><i class="ti ti-barcode me-1"></i>{{ $item->sku }}</small>
                                        @endif
                                        @if($item->notes)
                                            <small class="text-primary d-block mt-1"><i class="ti ti-note me-1"></i><strong>Nota para proveedor:</strong> {{ $item->notes }}</small>
                                        @endif
                                    </td>
                                    <td class="small text-muted">
                                        {{ $item->costCenter?->code ? $item->costCenter->code . ' - ' . $item->costCenter->name : ($item->costCenter?->name ?? '—') }}
                                    </td>
                                    <td class="small text-muted">
                                        {{ $item->expenseCategory->name ?? '—' }}
                                    </td>
                                    <td class="text-center">{{ number_format($item->quantity, 2) }}</td>
                                    <td class="text-center small text-muted">{{ $item->unit_of_measure ?? '—' }}</td>
                                    <td class="text-end">
                                        {{ $ocd->currency === 'USD' ? 'US$' : '$' }}{{ number_format($item->unit_price, 2) }}
                                    </td>
                                    <td class="text-center small">{{ $item->getIvaRateLabel() }}</td>
                                    <td class="text-end fw-bold text-dark">
                                        {{ $ocd->currency === 'USD' ? 'US$' : '$' }}{{ number_format($item->total, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="6" rowspan="3" class="border-0 align-top">
                                    <div class="bg-light p-3 rounded mt-2">
                                        <h6 class="fw-bold text-dark mb-2">Condiciones Comerciales:</h6>
                                        <ul class="small text-muted mb-0 ps-3">
                                            <li><strong>Forma de Pago:</strong> {{ $ocd->payment_terms ?? '—' }}</li>
                                            <li><strong>Entrega Estimada:</strong>
                                                {{ $ocd->estimated_delivery_days ? $ocd->estimated_delivery_days . ' días' : '—' }}
                                            </li>
                                            <li><strong>Moneda:</strong> {{ $ocd->currency ?? 'MXN' }}</li>
                                            @if($ocd->receivingLocation)
                                            <li><strong>Entregar en:</strong>
                                                {{ $ocd->receivingLocation->code }} — {{ $ocd->receivingLocation->name }}
                                            </li>
                                            @endif
                                        </ul>
                                    </div>
                                </td>
                                <td colspan="2" class="text-end border-0 fw-bold text-muted">Subtotal:</td>
                                <td class="text-end border-0 fw-bold">
                                    {{ $ocd->currency === 'USD' ? 'US$' : '$' }}{{ number_format($ocd->subtotal, 2) }}
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2" class="text-end border-0 text-muted small fw-bold">I.V.A:</td>
                                <td class="text-end border-0 text-muted small">
                                    {{ $ocd->currency === 'USD' ? 'US$' : '$' }}{{ number_format($ocd->iva_amount, 2) }}
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2" class="text-end border-0 bg-soft-primary">
                                    <h5 class="fw-bold text-primary mb-0">TOTAL:</h5>
                                </td>
                                <td class="text-end border-0 bg-soft-primary">
                                    <h5 class="fw-bold text-primary mb-0">
                                        {{ $ocd->currency === 'USD' ? 'US$' : '$' }}{{ number_format($ocd->total, 2) }}
                                    </h5>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                {{-- ═══════════════════════════════════════════════
                     DOCUMENTOS ADJUNTOS
                ═══════════════════════════════════════════════ --}}
                @if($ocd->documents->count() > 0)
                <div class="row mb-4 d-print-none">
                    <div class="col-12">
                        <h6 class="text-primary text-uppercase fw-bold fs-12 mb-3">
                            <i class="ti ti-paperclip me-1"></i>Documentos de Soporte
                        </h6>
                        <div class="d-flex flex-wrap gap-3">
                            @foreach($ocd->documents as $doc)
                                <div class="p-2 border rounded d-flex align-items-center bg-light">
                                    <i class="ti ti-file-text fs-2 text-primary me-2"></i>
                                    <div>
                                        <p class="mb-0 small fw-bold">{{ $doc->original_filename }}</p>
                                        <small class="text-muted text-uppercase">{{ $doc->document_type }}</small>
                                        <a href="{{ Storage::url($doc->file_path) }}" target="_blank" class="ms-2 text-info" title="Ver archivo">
                                            <i class="ti ti-external-link"></i>
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif

                {{-- ═══════════════════════════════════════════════
                     HISTORIAL DE APROBACIONES
                ═══════════════════════════════════════════════ --}}
                @if($ocd->approvals->count() > 0)
                <div class="row pt-2">
                    <div class="col-12">
                        <h6 class="text-primary text-uppercase fw-bold fs-12 mb-3">
                            <i class="ti ti-history me-1"></i>Historial de Aprobaciones
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-borderless">
                                <thead class="table-light">
                                    <tr class="small text-muted text-uppercase">
                                        <th>Fecha</th>
                                        <th>Usuario</th>
                                        <th>Acción</th>
                                        <th>Comentarios</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($ocd->approvals->sortByDesc('created_at') as $approval)
                                        <tr class="align-middle">
                                            <td class="small text-muted">{{ $approval->created_at->format('d/m/Y H:i') }}</td>
                                            <td class="small fw-bold">{{ $approval->approver->name }}</td>
                                            <td>
                                                <span class="badge bg-{{ $approval->getActionBadgeClass() }} small">
                                                    {{ $approval->getActionLabel() }}
                                                </span>
                                            </td>
                                            <td class="small fst-italic text-muted">{{ $approval->comments ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endif

            </div>{{-- /card-body --}}
        </div>
    </div>
</div>

{{-- ─── Modales de Aprobación ─── --}}
@if($canApproveOcd || $canReturnIssuedOcd)
    <form id="form-return" action="{{ route('direct-purchase-orders.return', $ocd->id) }}" method="POST" style="display:none">
        @csrf
        <input type="hidden" name="comments" id="return-comments-input">
    </form>
@endif
@if($canApproveOcd)
    <form id="form-reject" action="{{ route('direct-purchase-orders.reject', $ocd->id) }}" method="POST" style="display:none">
        @csrf
        <input type="hidden" name="comments" id="reject-comments-input">
    </form>
@endif

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function renderOcdCommitmentPage(pagination, page) {
        const detail = pagination.closest('[data-ocd-commitment-detail]');
        const items = Array.from(detail.querySelectorAll('[data-ocd-commitment-item]'));
        const pageSize = 5;
        const totalPages = Math.ceil(items.length / pageSize);
        const currentPage = Math.min(Math.max(page, 1), totalPages);

        items.forEach((item, index) => {
            item.classList.toggle('d-none', index < (currentPage - 1) * pageSize || index >= currentPage * pageSize);
        });

        pagination.dataset.page = currentPage;
        pagination.querySelector('[data-ocd-page-label]').textContent = `Página ${currentPage} de ${totalPages}`;
        pagination.querySelector('[data-ocd-page="previous"]').disabled = currentPage === 1;
        pagination.querySelector('[data-ocd-page="next"]').disabled = currentPage === totalPages;
    }

    document.querySelectorAll('[data-ocd-pagination]').forEach((pagination) => {
        renderOcdCommitmentPage(pagination, 1);
        pagination.querySelectorAll('[data-ocd-page]').forEach((button) => {
            button.addEventListener('click', () => {
                const page = Number(pagination.dataset.page || 1);
                renderOcdCommitmentPage(pagination, button.dataset.ocdPage === 'next' ? page + 1 : page - 1);
            });
        });
    });

    document.querySelectorAll('[data-ocd-open-all]').forEach((card) => {
        const toggleCommitments = () => document.querySelectorAll('.ocd-commitment-toggle').forEach((button) => button.click());
        card.addEventListener('click', toggleCommitments);
        card.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                toggleCommitments();
            }
        });
    });

    @if(session('success'))
        Swal.fire({ title: '¡Operación Exitosa!', text: "{{ session('success') }}", icon: 'success', confirmButtonColor: '#28a745' });
    @endif

    @if(session('return_data'))
    @php $rtd = session('return_data'); @endphp
    Swal.fire({
        title: '<span class="fw-bold" style="color:#0dcaf0">↩️ OC devuelta para correcciones</span>',
        icon: 'info',
        html: `<div class="text-start">
            <div class="mb-2"><strong>Folio:</strong> {{ $rtd['folio'] }}</div>
            <div class="mb-3"><strong>Nuevo estatus:</strong> <span class="badge bg-info text-dark">Corrección requerida</span></div>
            <div class="border-top pt-3">
                <p class="mb-1 small text-muted fw-bold">Instrucciones enviadas a <strong>{{ $rtd['creator_name'] }}</strong>:</p>
                <div class="p-2 bg-light rounded fst-italic text-muted small">"{{ Str::limit($rtd['comments'], 120) }}"</div>
            </div></div>`,
        confirmButtonText: 'ACEPTAR', confirmButtonColor: '#0dcaf0',
    });
    @endif

    @if(session('rejection_data'))
    @php $rd = session('rejection_data'); @endphp
    Swal.fire({
        title: '<span class="text-danger fw-bold">OC Rechazada</span>',
        icon: 'error',
        html: `<div class="text-start">
            <div class="mb-2"><strong>Folio:</strong> {{ $rd['folio'] }}</div>
            <div class="mb-3"><strong>Monto:</strong> ${{ number_format($rd['total'], 2) }} {{ $rd['currency'] }}</div>
            <div class="border-top pt-3">
                <p class="mb-1 small text-muted fw-bold">Se notificó a <strong>{{ $rd['creator_name'] }}</strong>:</p>
                <div class="p-2 bg-light rounded fst-italic text-muted small">"{{ Str::limit($rd['comments'], 120) }}"</div>
            </div></div>`,
        confirmButtonText: 'ACEPTAR', confirmButtonColor: '#dc3545',
    });
    @endif

    function confirmApproval() {
        Swal.fire({
            title: '<h4 class="fw-bold mb-0">Confirmar Aprobación</h4>',
            icon: 'question',
            html: `<div class="text-start mt-3 p-3 bg-light rounded border">
                <p class="mb-3 fw-bold text-dark">Al aprobar esta Orden de Compra:</p>
                <div class="mb-2"><i class="ti ti-check text-success me-2"></i>Se confirmará la reserva presupuestal del centro de costos</div>
                <div class="mb-2"><i class="ti ti-check text-success me-2"></i>Se generará el folio único de OC</div>
                <div class="mb-0"><i class="ti ti-check text-success me-2"></i>Se notificará al proveedor vía correo y portal</div>
            </div>
            <div class="text-start mt-3">
                <label class="form-label fw-bold small text-uppercase">Comentarios de Aprobación (Opcional)</label>
                <textarea id="swal-comments" class="form-control" rows="3" placeholder="Escriba algún comentario si lo desea..."></textarea>
            </div>`,
            showCancelButton: true,
            confirmButtonText: '<i class="ti ti-check me-1"></i>SÍ, APROBAR OC',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#28a745', cancelButtonColor: '#6c757d', reverseButtons: true,
            preConfirm: () => document.getElementById('swal-comments').value,
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = "{{ route('direct-purchase-orders.approve', $ocd->id) }}";
                const csrf = document.createElement('input');
                csrf.type = 'hidden'; csrf.name = '_token'; csrf.value = "{{ csrf_token() }}";
                const commentsInput = document.createElement('input');
                commentsInput.type = 'hidden'; commentsInput.name = 'comments'; commentsInput.value = result.value;
                form.appendChild(csrf); form.appendChild(commentsInput);
                document.body.appendChild(form);
                Swal.fire({ title: 'Procesando...', text: 'Confirmando reserva y notificando al proveedor',
                    allowOutsideClick: false, didOpen: () => { Swal.showLoading(); form.submit(); } });
            }
        });
    }

    function confirmReturn() {
        Swal.fire({
            title: '<span class="fw-bold" style="color:#0dcaf0">↩️ Devolver OC para correcciones</span>',
            icon: 'info',
            html: `<div class="text-start">
                <p class="text-muted small mb-3"><strong>{{ $ocd->folio }}</strong></p>
                <label class="form-label fw-bold small text-uppercase">Indica al solicitante qué debe corregir:</label>
                <textarea id="swal-return-instructions" class="form-control" rows="5" maxlength="500"
                    placeholder="Ejemplo: Favor de corregir el centro de costos..."></textarea>
                <div class="d-flex justify-content-end mt-1">
                    <span class="text-muted small" id="swal-return-char-count">0/500</span>
                </div></div>`,
            showCancelButton: true, confirmButtonText: 'DEVOLVER A SOLICITANTE', cancelButtonText: 'CANCELAR',
            confirmButtonColor: '#0dcaf0', cancelButtonColor: '#6c757d', reverseButtons: true,
            customClass: { confirmButton: 'text-dark' },
            didOpen: () => {
                const ta = document.getElementById('swal-return-instructions');
                const cc = document.getElementById('swal-return-char-count');
                const btn = Swal.getConfirmButton();
                btn.disabled = true;
                ta.addEventListener('input', () => { cc.textContent = `${ta.value.length}/500`; btn.disabled = ta.value.length === 0; });
            },
            preConfirm: () => {
                const v = document.getElementById('swal-return-instructions').value.trim();
                if (!v) { Swal.showValidationMessage('Las instrucciones son obligatorias'); return false; }
                return v;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('return-comments-input').value = result.value;
                Swal.fire({ title: 'Procesando...', allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); document.getElementById('form-return').submit(); } });
            }
        });
    }

    function confirmReject(previousReason = '') {
        Swal.fire({
            title: '<span class="fw-bold text-danger">Rechazar Orden de Compra</span>',
            icon: 'warning',
            html: `<div class="text-start">
                <label class="form-label fw-bold small text-uppercase text-danger">
                    Motivo del rechazo <span class="text-muted fw-normal">(mínimo 50 caracteres)</span>:
                </label>
                <textarea id="swal-reject-reason" class="form-control" rows="5" maxlength="500"
                    placeholder="Ejemplo: El proveedor no cumple con las especificaciones técnicas..."></textarea>
                <div class="d-flex justify-content-between mt-1">
                    <span id="swal-min-msg" class="text-danger small" style="display:none">Mínimo 50 caracteres requeridos</span>
                    <span class="ms-auto text-muted small" id="swal-char-count">0/500</span>
                </div></div>`,
            showCancelButton: true, confirmButtonText: 'RECHAZAR OC', cancelButtonText: 'CANCELAR',
            confirmButtonColor: '#dc3545', cancelButtonColor: '#6c757d', reverseButtons: true,
            didOpen: () => {
                const ta = document.getElementById('swal-reject-reason');
                const cc = document.getElementById('swal-char-count');
                const mm = document.getElementById('swal-min-msg');
                const btn = Swal.getConfirmButton();
                if (previousReason) ta.value = previousReason;
                const upd = () => { const l = ta.value.length; cc.textContent=`${l}/500`; const v=l>=50; btn.disabled=!v; mm.style.display=v?'none':'block'; };
                upd(); ta.addEventListener('input', upd);
            },
            preConfirm: () => {
                const r = document.getElementById('swal-reject-reason').value;
                if (r.length < 50) { Swal.showValidationMessage('Mínimo 50 caracteres requeridos'); return false; }
                return r;
            }
        }).then((result) => { if (result.isConfirmed) showRejectConfirmation(result.value); });
    }

    function showRejectConfirmation(reason) {
        const preview = reason.length > 80 ? reason.substring(0, 80) + '...' : reason;
        Swal.fire({
            title: '<span class="fw-bold">⚠️ ¿Confirmar rechazo?</span>',
            html: `<div class="text-start">
                <p class="fw-bold text-danger mb-2">Esta acción:</p>
                <ul class="text-muted small mb-3">
                    <li>Liberará el presupuesto comprometido</li>
                    <li>Notificará al solicitante</li>
                    <li>La OC quedará bloqueada permanentemente</li>
                </ul>
                <div class="p-2 bg-light rounded border">
                    <p class="mb-1 small text-muted fw-bold">Motivo registrado:</p>
                    <p class="mb-0 fst-italic small">"${preview}"</p>
                </div></div>`,
            showCancelButton: true, confirmButtonText: '<i class="ti ti-x me-1"></i>SÍ, RECHAZAR',
            cancelButtonText: 'VOLVER', confirmButtonColor: '#dc3545', cancelButtonColor: '#6c757d', reverseButtons: true,
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('reject-comments-input').value = reason;
                Swal.fire({ title: 'Procesando...', allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); document.getElementById('form-reject').submit(); } });
            } else if (result.dismiss === Swal.DismissReason.cancel) {
                confirmReject(reason);
            }
        });
    }

    function confirmAction(formId, message) {
        Swal.fire({ title: message, icon: 'question', showCancelButton: true,
            confirmButtonText: 'Sí, continuar', cancelButtonText: 'Cancelar', reverseButtons: true
        }).then((result) => { if (result.isConfirmed) document.getElementById(formId).submit(); });
    }
</script>
@endpush

{{-- ╔══════════════════════════════════════════════════════════════════╗
     ║  HISTORIAL DE RECEPCIONES  (fuera del área imprimible)         ║
     ╚══════════════════════════════════════════════════════════════════╝ --}}
<div class="row mt-3 d-print-none">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between">
                <h6 class="mb-0 text-primary">
                    <i class="ti ti-clipboard-list me-2"></i>Historial de Recepciones
                </h6>
                @php
                    $totalItems      = $directPurchaseOrder->items->count();
                    $fullyReceived   = $directPurchaseOrder->items->filter->isFullyReceived()->count();
                    $pctReceived     = $totalItems > 0 ? round(($fullyReceived / $totalItems) * 100) : 0;
                    $hasNonConforming = $directPurchaseOrder->receptions
                        ->flatMap->items
                        ->contains->isNonConforming();
                @endphp
                <div class="d-flex align-items-center gap-3">
                    @if($directPurchaseOrder->receptions->isNotEmpty())
                        <div class="text-muted small">
                            {{ $fullyReceived }}/{{ $totalItems }} partidas completas
                        </div>
                        <div style="width:120px">
                            <div class="progress" style="height:8px" title="{{ $pctReceived }}% recibido">
                                <div class="progress-bar bg-{{ $pctReceived === 100 ? 'success' : 'primary' }}"
                                     style="width:{{ $pctReceived }}%"></div>
                            </div>
                        </div>
                        @if($hasNonConforming)
                            <span class="badge bg-danger">
                                <i class="ti ti-alert-triangle me-1"></i>Contiene no conformidades
                            </span>
                        @endif
                    @endif
                    @if($directPurchaseOrder->canBeReceived())
                        <a href="{{ route('receptions.create-direct', $directPurchaseOrder) }}"
                           class="btn btn-sm btn-outline-success">
                            <i class="ti ti-package-import me-1"></i>Registrar Recepción
                        </a>
                    @endif
                </div>
            </div>

            <div class="card-body p-0">
                @if($directPurchaseOrder->receptions->isEmpty())
                    <div class="text-center text-muted py-5">
                        <i class="ti ti-package-off fs-40 d-block mb-2 opacity-25"></i>
                        <p class="mb-0">Aún no hay recepciones registradas para esta orden.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover table-centered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Folio Recepción</th>
                                    <th>Fecha</th>
                                    <th>Receptor</th>
                                    <th>Punto de Entrega</th>
                                    <th class="text-center">Partidas</th>
                                    <th class="text-center">No Conformes</th>
                                    <th class="text-center">Estado</th>
                                    <th class="text-center">Remisión</th>
                                    <th class="text-center">Recepción</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($directPurchaseOrder->receptions->sortByDesc('received_at') as $reception)
                                    @php
                                        $nonConformingCount = $reception->items->filter->isNonConforming()->count();
                                    @endphp
                                    <tr>
                                        <td class="ps-3 fw-bold text-primary">{{ $reception->folio }}</td>
                                        <td class="small text-muted">
                                            {{ $reception->received_at->format('d/m/Y H:i') }}
                                        </td>
                                        <td class="small">{{ $reception->receiver->name ?? '—' }}</td>
                                        <td class="small">{{ $reception->receivingLocation->name ?? '—' }}</td>
                                        <td class="text-center">
                                            <span class="badge bg-soft-secondary text-secondary">
                                                {{ $reception->items->count() }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            @if($nonConformingCount > 0)
                                                <span class="badge bg-danger">
                                                    <i class="ti ti-circle-x me-1"></i>{{ $nonConformingCount }}
                                                </span>
                                            @else
                                                <span class="text-muted small">—</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-{{ $reception->getStatusBadgeClass() }}">
                                                {{ $reception->getStatusLabel() }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            @if($reception->remission_path)
                                                <a href="{{ route('receptions.remission.download', $reception) }}"
                                                   class="btn btn-sm btn-outline-secondary"
                                                   title="Descargar remisión ({{ $reception->delivery_reference ?? 'sin referencia' }})">
                                                    <i class="ti ti-paperclip"></i>
                                                </a>
                                            @else
                                                <span class="text-muted small">—</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <a href="{{ route('receptions.show', $reception) }}"
                                               class="btn btn-sm btn-outline-primary"
                                               title="Ver comprobante">
                                                <i class="ti ti-file-check"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
