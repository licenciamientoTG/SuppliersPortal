@extends('layouts.zircos')

@section('title', 'RFQ - ' . $rfq->folio)

@section('page.title', 'Detalle RFQ')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('rfq.index') }}">RFQs</a></li>
    <li class="breadcrumb-item active">{{ $rfq->folio }}</li>
@endsection

@push('styles')
<style>
    .stat-icon {
        width: 48px; height: 48px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        border-radius: 12px; font-size: 1.4rem;
    }
    .supplier-avatar {
        width: 38px; height: 38px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        border-radius: 50%; font-weight: 700; font-size: 1rem;
    }
    .timeline-item { position: relative; padding-left: 2rem; }
    .timeline-item::before {
        content: ''; position: absolute; left: 0.5rem; top: 1.5rem;
        bottom: -1rem; width: 2px; background: #dee2e6;
    }
    .timeline-item:last-child::before { display: none; }
    .timeline-dot {
        position: absolute; left: 0; top: 0.25rem;
        width: 1rem; height: 1rem; border-radius: 50%;
        border: 2px solid; background: #fff;
    }
</style>
@endpush

@section('content')

@php
    $statusConfig = [
        'DRAFT'              => ['class' => 'secondary', 'icon' => 'ti-pencil',       'label' => 'Borrador'],
        'SENT'               => ['class' => 'info',      'icon' => 'ti-send',         'label' => 'Enviada'],
        'RECEIVED'           => ['class' => 'primary',   'icon' => 'ti-inbox',        'label' => 'Recibida'],
        'RESPONSES_RECEIVED' => ['class' => 'success',   'icon' => 'ti-check',        'label' => 'Con Respuestas'],
        'EVALUATED'          => ['class' => 'primary',   'icon' => 'ti-check-circle', 'label' => 'Evaluada'],
        'REJECTED'           => ['class' => 'warning',   'icon' => 'ti-alert-circle', 'label' => 'Rechazada'],
        'CANCELLED'          => ['class' => 'danger',    'icon' => 'ti-ban',          'label' => 'Cancelada'],
    ];
    $status = $statusConfig[$rfq->status] ?? ['class' => 'secondary', 'icon' => 'ti-help', 'label' => $rfq->status];

    $totalSuppliers  = $rfq->suppliers->count();
    $responded       = $rfq->suppliers->filter(fn($s) => $s->pivot->responded_at)->count();
    $pending         = $totalSuppliers - $responded;

    $deadline        = $rfq->response_deadline;
    $daysRemaining   = $deadline ? now()->diffInDays($deadline, false) : null;
@endphp

{{-- ================================================================
     HEADER
================================================================ --}}
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h2 class="mb-1 fw-bold">
            <i class="ti ti-file-invoice me-2"></i>{{ $rfq->folio }}
        </h2>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge bg-{{ $status['class'] }} fs-6">
                <i class="ti {{ $status['icon'] }} me-1"></i>{{ $status['label'] }}
            </span>
            @if($rfq->source === 'external')
                <span class="badge bg-warning text-dark">
                    <i class="ti ti-world me-1"></i>Cotización Externa
                </span>
            @else
                <span class="badge bg-light text-dark border">
                    <i class="ti ti-layout-grid me-1"></i>Portal
                </span>
            @endif
            @if($rfq->isExpired() && $rfq->status === 'SENT')
                <span class="badge bg-danger">
                    <i class="ti ti-clock-x me-1"></i>Plazo Vencido
                </span>
            @endif
        </div>
    </div>
    <div class="d-flex gap-2">
        @if($rfq->status === 'DRAFT')
            <button type="button" class="btn btn-success" id="sendRfqBtn">
                <i class="ti ti-send me-1"></i>Enviar a Proveedores
            </button>
        @endif
        @if(!in_array($rfq->status, ['CANCELLED', 'REJECTED'], true))
            <button type="button" class="btn btn-outline-danger" id="cancelRfqBtn">
                <i class="ti ti-ban me-1"></i>Cancelar RFQ
            </button>
        @endif
        <a href="{{ route('rfq.index') }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i>Volver
        </a>
    </div>
</div>

{{-- ================================================================
     TARJETAS DE RESUMEN
================================================================ --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="ti ti-building-store"></i>
                </div>
                <div>
                    <div class="text-muted small">Proveedores</div>
                    <div class="fw-bold fs-4">{{ $totalSuppliers }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="ti ti-check-circle"></i>
                </div>
                <div>
                    <div class="text-muted small">Respondieron</div>
                    <div class="fw-bold fs-4 text-success">{{ $responded }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                    <i class="ti ti-clock-hour-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Pendientes</div>
                    <div class="fw-bold fs-4 text-warning">{{ $pending }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon {{ $daysRemaining !== null && $daysRemaining < 0 ? 'bg-danger bg-opacity-10 text-danger' : ($daysRemaining <= 3 ? 'bg-warning bg-opacity-10 text-warning' : 'bg-info bg-opacity-10 text-info') }}">
                    <i class="ti ti-calendar-due"></i>
                </div>
                <div>
                    <div class="text-muted small">Días restantes</div>
                    @if($daysRemaining !== null)
                        @if($daysRemaining < 0)
                            <div class="fw-bold fs-4 text-danger">Vencida</div>
                        @elseif($daysRemaining === 0)
                            <div class="fw-bold fs-4 text-warning">Hoy</div>
                        @else
                            <div class="fw-bold fs-4 text-info">{{ $daysRemaining }}</div>
                        @endif
                    @else
                        <div class="fw-bold fs-4 text-muted">—</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ================================================================
     FILA PRINCIPAL
================================================================ --}}
<div class="row g-4 mb-4">

    {{-- COLUMNA IZQUIERDA --}}
    <div class="col-lg-8">

        {{-- Información General de la RFQ --}}
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="ti ti-info-circle text-primary me-2"></i>Información General</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="form-label text-muted mb-1">Folio</label>
                        <p class="mb-0 fw-bold fs-5">{{ $rfq->folio }}</p>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label text-muted mb-1">Estado</label>
                        <p class="mb-0">
                            <span class="badge bg-{{ $status['class'] }} fs-6">
                                <i class="ti {{ $status['icon'] }} me-1"></i>{{ $status['label'] }}
                            </span>
                        </p>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label text-muted mb-1">Tipo de Agrupación</label>
                        <p class="mb-0">
                            @if($rfq->quotation_group_id)
                                <span class="badge bg-primary">
                                    <i class="ti ti-folder me-1"></i>Grupo: {{ $rfq->quotationGroup?->name }}
                                </span>
                            @else
                                <span class="badge bg-secondary">
                                    <i class="ti ti-file me-1"></i>Partida Individual
                                </span>
                            @endif
                        </p>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label text-muted mb-1">Origen</label>
                        <p class="mb-0">
                            @if($rfq->source === 'external')
                                <span class="badge bg-warning text-dark"><i class="ti ti-world me-1"></i>Cotización Externa</span>
                            @else
                                <span class="badge bg-light text-dark border"><i class="ti ti-layout-grid me-1"></i>Portal de Proveedores</span>
                            @endif
                        </p>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label text-muted mb-1">Fecha de Envío</label>
                        <p class="mb-0 fw-semibold">
                            @if($rfq->sent_at)
                                <i class="ti ti-send text-info me-1"></i>{{ $rfq->sent_at->format('d/m/Y H:i') }}
                                <small class="text-muted d-block">{{ $rfq->sent_at->diffForHumans() }}</small>
                            @else
                                <span class="text-muted">Aún no enviada</span>
                            @endif
                        </p>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label text-muted mb-1">Fecha Límite de Respuesta</label>
                        <p class="mb-0 fw-semibold">
                            @if($deadline)
                                <i class="ti ti-calendar-event me-1
                                    {{ $daysRemaining < 0 ? 'text-danger' : ($daysRemaining <= 3 ? 'text-warning' : 'text-success') }}"></i>
                                {{ $deadline->format('d/m/Y') }}
                                @if($daysRemaining < 0)
                                    <span class="badge bg-danger ms-1">Vencida hace {{ abs($daysRemaining) }} día(s)</span>
                                @elseif($daysRemaining === 0)
                                    <span class="badge bg-warning ms-1">Vence hoy</span>
                                @elseif($daysRemaining <= 3)
                                    <span class="badge bg-warning ms-1">{{ $daysRemaining }} día(s)</span>
                                @else
                                    <span class="badge bg-success ms-1">{{ $daysRemaining }} días</span>
                                @endif
                            @else
                                <span class="text-muted">No definida</span>
                            @endif
                        </p>
                    </div>

                    @if($rfq->message)
                    <div class="col-12">
                        <label class="form-label text-muted mb-1">Mensaje para Proveedores</label>
                        <div class="border rounded p-3 bg-light">
                            <i class="ti ti-message text-info me-1"></i>{{ $rfq->message }}
                        </div>
                    </div>
                    @endif

                    @if($rfq->requirements)
                    <div class="col-12">
                        <label class="form-label text-muted mb-1">Requisitos Especiales</label>
                        <div class="border rounded p-3 bg-light">
                            <i class="ti ti-clipboard-check text-warning me-1"></i>{{ $rfq->requirements }}
                        </div>
                    </div>
                    @endif

                    @if($rfq->notes)
                    <div class="col-12">
                        <label class="form-label text-muted mb-1">Notas Internas</label>
                        <div class="alert alert-info mb-0 py-2">
                            <i class="ti ti-notes me-1"></i>{{ $rfq->notes }}
                        </div>
                    </div>
                    @endif

                    @if($rfq->source === 'external' && $rfq->external_notes)
                    <div class="col-12">
                        <label class="form-label text-muted mb-1">Notas de Cotización Externa</label>
                        <div class="alert alert-warning mb-0 py-2">
                            <i class="ti ti-world me-1"></i>{{ $rfq->external_notes }}
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Requisición de Origen --}}
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="ti ti-file-text text-success me-2"></i>Requisición de Origen</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="form-label text-muted mb-1">Folio</label>
                        <p class="mb-0">
                            <a href="{{ route('requisitions.show', $rfq->requisition) }}" class="fw-bold text-decoration-none">
                                <i class="ti ti-external-link me-1"></i>{{ $rfq->requisition->folio }}
                            </a>
                        </p>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label text-muted mb-1">Solicitado por</label>
                        <p class="mb-0 fw-semibold">
                            <i class="ti ti-user text-primary me-1"></i>
                            {{ $rfq->requisition->requester?->name ?? '—' }}
                        </p>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label text-muted mb-1">Compañía</label>
                        <p class="mb-0 fw-semibold">
                            <i class="ti ti-building-bank text-primary me-1"></i>
                            {{ $rfq->requisition->company?->name ?? '—' }}
                        </p>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label text-muted mb-1">Centro de Costo</label>
                        <p class="mb-0 fw-semibold">
                            <i class="ti ti-hierarchy-3 text-primary me-1"></i>
                            @php
                                $primaryCostCenter = $rfq->requisition?->primaryCostCenter();
                            @endphp
                            {{ $primaryCostCenter ? $primaryCostCenter->code . ' - ' . $primaryCostCenter->name : '—' }}
                        </p>
                    </div>
                    @if($rfq->requisition->receivingLocation)
                    <div class="col-sm-6">
                        <label class="form-label text-muted mb-1">Punto de Entrega</label>
                        <p class="mb-0 fw-semibold">
                            <i class="ti ti-map-pin text-primary me-1"></i>
                            {{ $rfq->requisition->receivingLocation->code }} - {{ $rfq->requisition->receivingLocation->name }}
                        </p>
                    </div>
                    @endif
                    @if($rfq->requisition->required_date)
                    <div class="col-sm-6">
                        <label class="form-label text-muted mb-1">Fecha Requerida</label>
                        <p class="mb-0 fw-semibold">
                            <i class="ti ti-calendar-due text-warning me-1"></i>
                            {{ $rfq->requisition->required_date->format('d/m/Y') }}
                        </p>
                    </div>
                    @endif
                    @if($rfq->requisition->description)
                    <div class="col-12">
                        <label class="form-label text-muted mb-1">Descripción / Justificación</label>
                        <div class="border rounded p-2 bg-light">
                            {{ $rfq->requisition->description }}
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Partidas Incluidas --}}
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="ti ti-package text-warning me-2"></i>Partidas Incluidas</h5>
                @if($rfq->quotationGroup)
                    <span class="badge bg-primary">{{ $rfq->quotationGroup->items->count() }} partida(s)</span>
                @endif
            </div>
            <div class="card-body p-0">
                @if($rfq->quotation_group_id && $rfq->quotationGroup)
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="40" class="text-center">#</th>
                                    <th>Producto / Código</th>
                                    <th>Descripción</th>
                                    <th width="100" class="text-end">Cantidad</th>
                                    <th width="80" class="text-center">Unidad</th>
                                    <th width="160">Categoría</th>
                                    <th width="60" class="text-center">Notas</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($rfq->quotationGroup->items as $i => $item)
                                <tr>
                                    <td class="text-center text-muted">{{ $i + 1 }}</td>
                                    <td>
                                        @if($item->productService)
                                            <span class="fw-semibold">{{ $item->productService->code }}</span>
                                            @if($item->productService->product_type)
                                                <br>
                                                <span class="badge bg-{{ $item->productService->product_type === 'SERVICIO' ? 'info' : 'primary' }} badge-sm">
                                                    {{ $item->productService->product_type }}
                                                </span>
                                            @endif
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $item->description }}
                                        @if($item->productService?->brand || $item->productService?->model)
                                            <br><small class="text-muted">
                                                <i class="ti ti-tag me-1"></i>
                                                {{ collect([$item->productService->brand, $item->productService->model])->filter()->join(' / ') }}
                                            </small>
                                        @endif
                                    </td>
                                    <td class="text-end fw-semibold">{{ number_format($item->quantity, 3) }}</td>
                                    <td class="text-center"><span class="badge bg-secondary">{{ $item->unit }}</span></td>
                                    <td><span class="badge bg-info">{{ $item->expenseCategory?->name ?? '—' }}</span></td>
                                    <td class="text-center">
                                        @if($item->notes)
                                            <i class="ti ti-note text-info" data-bs-toggle="tooltip" title="{{ $item->notes }}"></i>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">Sin partidas en este grupo</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @elseif($rfq->requisition_item_id && $rfq->requisitionItem)
                    <div class="p-3">
                        <div class="alert alert-light border mb-0">
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <small class="text-muted d-block">Producto</small>
                                    <strong>{{ $rfq->requisitionItem->productService?->code ?? '—' }}</strong>
                                </div>
                                <div class="col-sm-6">
                                    <small class="text-muted d-block">Descripción</small>
                                    <span>{{ $rfq->requisitionItem->description }}</span>
                                </div>
                                <div class="col-sm-4">
                                    <small class="text-muted d-block">Cantidad</small>
                                    <strong>{{ number_format($rfq->requisitionItem->quantity, 3) }} {{ $rfq->requisitionItem->unit }}</strong>
                                </div>
                                <div class="col-sm-4">
                                    <small class="text-muted d-block">Categoría</small>
                                    <span class="badge bg-info">{{ $rfq->requisitionItem->expenseCategory?->name ?? '—' }}</span>
                                </div>
                                @if($rfq->requisitionItem->notes)
                                <div class="col-12">
                                    <small class="text-muted d-block">Notas</small>
                                    <span>{{ $rfq->requisitionItem->notes }}</span>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @else
                    <div class="p-4 text-center text-muted">
                        <i class="ti ti-alert-triangle text-warning fs-2 d-block mb-2"></i>
                        No se encontraron partidas asociadas a esta RFQ
                    </div>
                @endif
            </div>
        </div>

        {{-- Respuestas de Proveedores --}}
        @if($rfq->rfqResponses->isNotEmpty())
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="ti ti-message-check text-success me-2"></i>Respuestas de Proveedores
                </h5>
                <span class="badge bg-success">{{ $rfq->rfqResponses->count() }} respuesta(s)</span>
            </div>
            <div class="card-body p-0">
                {{-- Agrupar por proveedor --}}
                @foreach($rfq->rfqResponses->groupBy('supplier_id') as $supplierId => $responses)
                @php $firstResp = $responses->first(); @endphp
                <div class="border-bottom">
                    <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <div class="supplier-avatar bg-primary text-white">
                                {{ substr($firstResp->supplier?->company_name ?? '?', 0, 1) }}
                            </div>
                            <div>
                                <strong>{{ $firstResp->supplier?->company_name ?? 'Proveedor #'.$supplierId }}</strong>
                                @if($firstResp->supplier_quotation_number)
                                    <br><small class="text-muted">Cot. # {{ $firstResp->supplier_quotation_number }}</small>
                                @endif
                            </div>
                        </div>
                        <div class="text-end">
                            @if($firstResp->submitted_at)
                                <small class="text-muted">Recibida: {{ $firstResp->submitted_at->format('d/m/Y H:i') }}</small>
                            @endif
                            <br>
                            @php
                                $respStatus = match($firstResp->status) {
                                    'SUBMITTED' => ['bg-success','Enviada'],
                                    'APPROVED'  => ['bg-primary','Aprobada'],
                                    'REJECTED'  => ['bg-danger','Rechazada'],
                                    default     => ['bg-secondary','Borrador'],
                                };
                            @endphp
                            <span class="badge {{ $respStatus[0] }}">{{ $respStatus[1] }}</span>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Partida</th>
                                    <th class="text-center">Marca / Modelo</th>
                                    <th class="text-end">Precio Unit.</th>
                                    <th class="text-end">Desc.</th>
                                    <th class="text-end">Subtotal</th>
                                    <th class="text-end">IVA ({{ $firstResp->iva_rate ?? 16 }}%)</th>
                                    <th class="text-end fw-bold">Total</th>
                                    <th class="text-center">Entrega</th>
                                    <th class="text-center">Moneda</th>
                                    <th class="text-center">Adjunto</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($responses as $resp)
                                <tr>
                                    <td>
                                        <small>{{ $resp->requisitionItem?->description ?? '—' }}</small>
                                    </td>
                                    <td class="text-center">
                                        @if($resp->brand || $resp->model)
                                            <small>{{ collect([$resp->brand, $resp->model])->filter()->join(' / ') }}</small>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-end">${{ number_format($resp->unit_price, 2) }}</td>
                                    <td class="text-end">
                                        @if($resp->discount_percentage)
                                            <span class="badge bg-warning text-dark">{{ $resp->discount_percentage }}%</span>
                                        @elseif($resp->discount_amount)
                                            ${{ number_format($resp->discount_amount, 2) }}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-end">${{ number_format($resp->subtotal, 2) }}</td>
                                    <td class="text-end">${{ number_format($resp->iva_amount, 2) }}</td>
                                    <td class="text-end fw-bold text-success">${{ number_format($resp->total, 2) }}</td>
                                    <td class="text-center">
                                        @if($resp->delivery_days)
                                            <span class="badge bg-light text-dark border">{{ $resp->delivery_days }} días</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary">{{ $resp->currency ?? 'MXN' }}</span>
                                    </td>
                                    <td class="text-center">
                                        @if($resp->attachment_path)
                                            <a href="{{ asset('storage/' . $resp->attachment_path) }}" target="_blank"
                                               class="btn btn-sm btn-outline-primary" title="Ver cotización">
                                                <i class="ti ti-file-type-pdf"></i>
                                            </a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                                @if($resp->payment_terms || $resp->warranty_terms || $resp->specifications || $resp->notes)
                                <tr class="table-light">
                                    <td colspan="10" class="py-2 px-3">
                                        <div class="row g-2">
                                            @if($resp->payment_terms)
                                            <div class="col-sm-4">
                                                <small class="text-muted d-block">Condiciones de pago</small>
                                                <small>{{ $resp->payment_terms }}</small>
                                            </div>
                                            @endif
                                            @if($resp->warranty_terms)
                                            <div class="col-sm-4">
                                                <small class="text-muted d-block">Garantía</small>
                                                <small>{{ $resp->warranty_terms }}</small>
                                            </div>
                                            @endif
                                            @if($resp->specifications)
                                            <div class="col-sm-4">
                                                <small class="text-muted d-block">Especificaciones</small>
                                                <small>{{ $resp->specifications }}</small>
                                            </div>
                                            @endif
                                            @if($resp->notes)
                                            <div class="col-12">
                                                <small class="text-muted d-block">Notas del proveedor</small>
                                                <small>{{ $resp->notes }}</small>
                                            </div>
                                            @endif
                                            @if($resp->meets_specs !== null)
                                            <div class="col-sm-4">
                                                <small class="text-muted d-block">Cumple especificaciones</small>
                                                @if($resp->meets_specs)
                                                    <span class="badge bg-success"><i class="ti ti-check me-1"></i>Sí</span>
                                                @else
                                                    <span class="badge bg-danger"><i class="ti ti-x me-1"></i>No</span>
                                                @endif
                                            </div>
                                            @endif
                                            @if($resp->score)
                                            <div class="col-sm-4">
                                                <small class="text-muted d-block">Puntaje de evaluación</small>
                                                <span class="badge bg-primary">{{ $resp->score }} / 100</span>
                                            </div>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @endif
                                @endforeach
                            </tbody>
                            <tfoot class="table-light fw-bold">
                                <tr>
                                    <td colspan="6" class="text-end">Total de la oferta:</td>
                                    <td class="text-end text-success">
                                        ${{ number_format($responses->sum('total'), 2) }}
                                    </td>
                                    <td colspan="3"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    @if($firstResp->evaluator && $firstResp->evaluated_at)
                    <div class="p-2 px-3 bg-light border-top">
                        <small class="text-muted">
                            <i class="ti ti-user-check me-1"></i>
                            Evaluado por <strong>{{ $firstResp->evaluator->name }}</strong>
                            el {{ $firstResp->evaluated_at->format('d/m/Y H:i') }}
                            @if($firstResp->evaluation_notes)
                                — {{ $firstResp->evaluation_notes }}
                            @endif
                        </small>
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Cancelación --}}
        @if($rfq->status === 'CANCELLED')
        <div class="card border-danger mb-4">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="ti ti-ban me-2"></i>Información de Cancelación</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="form-label text-muted mb-1">Cancelada por</label>
                        <p class="mb-0 fw-semibold">{{ $rfq->canceller?->name ?? '—' }}</p>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label text-muted mb-1">Fecha de cancelación</label>
                        <p class="mb-0 fw-semibold">
                            {{ $rfq->cancelled_at?->format('d/m/Y H:i') ?? '—' }}
                        </p>
                    </div>
                    @if($rfq->cancellation_reason)
                    <div class="col-12">
                        <label class="form-label text-muted mb-1">Motivo</label>
                        <div class="alert alert-danger mb-0 py-2">
                            <i class="ti ti-message-2 me-1"></i>{{ $rfq->cancellation_reason }}
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endif

    </div>

    {{-- COLUMNA DERECHA --}}
    <div class="col-lg-4">

        {{-- Proveedores Invitados --}}
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="ti ti-building-store text-primary me-2"></i>Proveedores</h5>
                <span class="badge bg-primary">{{ $totalSuppliers }}</span>
            </div>
            <div class="card-body p-0">
                @forelse($rfq->suppliers as $supplier)
                @php $hasResponse = $supplier->pivot->responded_at !== null; @endphp
                <div class="d-flex align-items-start gap-3 p-3 border-bottom">
                    <div class="supplier-avatar {{ $hasResponse ? 'bg-success' : 'bg-warning' }} text-white">
                        {{ substr($supplier->company_name, 0, 1) }}
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold text-truncate">{{ $supplier->company_name }}</div>
                        <small class="text-muted d-block text-truncate">
                            <i class="ti ti-mail me-1"></i>{{ $supplier->email ?? '—' }}
                        </small>
                        @if($supplier->phone)
                            <small class="text-muted d-block">
                                <i class="ti ti-phone me-1"></i>{{ $supplier->phone }}
                            </small>
                        @endif
                        <div class="mt-1 d-flex flex-wrap gap-1">
                            @if($hasResponse)
                                <span class="badge bg-success">
                                    <i class="ti ti-check me-1"></i>Respondió
                                </span>
                                <small class="text-muted d-block w-100">
                                    {{ \Carbon\Carbon::parse($supplier->pivot->responded_at)->format('d/m/Y H:i') }}
                                </small>
                            @else
                                <span class="badge bg-warning text-dark">
                                    <i class="ti ti-clock me-1"></i>Pendiente
                                </span>
                            @endif
                            @if($supplier->pivot->quotation_pdf_path)
                                <a href="{{ asset('storage/' . $supplier->pivot->quotation_pdf_path) }}"
                                   target="_blank" class="badge bg-light text-dark border text-decoration-none">
                                    <i class="ti ti-file-type-pdf me-1 text-danger"></i>PDF
                                </a>
                            @endif
                        </div>
                        @if($supplier->pivot->invited_at)
                        <small class="text-muted d-block mt-1">
                            <i class="ti ti-calendar me-1"></i>Invitado: {{ \Carbon\Carbon::parse($supplier->pivot->invited_at)->format('d/m/Y') }}
                        </small>
                        @endif
                    </div>
                </div>
                @empty
                <div class="text-center text-muted py-4">
                    <i class="ti ti-user-off fs-2 d-block mb-2"></i>
                    Sin proveedores invitados
                </div>
                @endforelse
            </div>
        </div>

        {{-- Auditoría --}}
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="ti ti-history text-secondary me-2"></i>Auditoría</h5>
            </div>
            <div class="card-body">
                <div class="timeline-item mb-3">
                    <div class="timeline-dot" style="border-color:#0d6efd;background:#e7f1ff;"></div>
                    <div class="ps-1">
                        <small class="text-muted d-block">Creada</small>
                        <span class="fw-semibold">{{ $rfq->creator?->name ?? '—' }}</span>
                        <small class="text-muted d-block">{{ $rfq->created_at->format('d/m/Y H:i') }}</small>
                        <small class="text-muted">{{ $rfq->created_at->diffForHumans() }}</small>
                    </div>
                </div>

                @if($rfq->sent_at)
                <div class="timeline-item mb-3">
                    <div class="timeline-dot" style="border-color:#0dcaf0;background:#e7f9fc;"></div>
                    <div class="ps-1">
                        <small class="text-muted d-block">Enviada a proveedores</small>
                        <small class="text-muted d-block">{{ $rfq->sent_at->format('d/m/Y H:i') }}</small>
                    </div>
                </div>
                @endif

                @if($rfq->cancelled_at)
                <div class="timeline-item mb-3">
                    <div class="timeline-dot" style="border-color:#dc3545;background:#fde8ea;"></div>
                    <div class="ps-1">
                        <small class="text-muted d-block">Cancelada</small>
                        <span class="fw-semibold text-danger">{{ $rfq->canceller?->name ?? '—' }}</span>
                        <small class="text-muted d-block">{{ $rfq->cancelled_at->format('d/m/Y H:i') }}</small>
                    </div>
                </div>
                @endif

                @if($rfq->updated_at->gt($rfq->created_at))
                <div class="timeline-item">
                    <div class="timeline-dot" style="border-color:#6c757d;background:#f8f9fa;"></div>
                    <div class="ps-1">
                        <small class="text-muted d-block">Última actualización</small>
                        <span class="fw-semibold">{{ $rfq->updater?->name ?? '—' }}</span>
                        <small class="text-muted d-block">{{ $rfq->updated_at->format('d/m/Y H:i') }}</small>
                        <small class="text-muted">{{ $rfq->updated_at->diffForHumans() }}</small>
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- Acciones --}}
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="ti ti-bolt text-warning me-2"></i>Acciones</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    @if($rfq->status === 'DRAFT')
                        <button type="button" class="btn btn-success" id="sendRfqBtn2">
                            <i class="ti ti-send me-1"></i>Enviar a Proveedores
                        </button>
                    @endif
                    <button type="button"
                        class="btn btn-outline-primary js-open-requisition-modal"
                        data-url="{{ route('rfq.requisition-modal', $rfq) }}">
                        <i class="ti ti-file-text me-1"></i>Ver Requisición
                    </button>
                    @if($rfq->status !== 'CANCELLED')
                        <button type="button" class="btn btn-outline-danger" id="cancelRfqBtn2">
                            <i class="ti ti-ban me-1"></i>Cancelar RFQ
                        </button>
                    @endif
                    <a href="{{ route('rfq.index') }}" class="btn btn-outline-secondary">
                        <i class="ti ti-arrow-left me-1"></i>Volver al Listado
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>

<div class="modal fade" id="requisitionInfoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" id="requisition-modal-content">
            <div class="modal-body p-5 text-center text-muted">
                <div class="spinner-border text-primary mb-3" role="status" aria-hidden="true"></div>
                <p class="mb-0">Cargando requisición...</p>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(document).ready(function() {
    $('[data-bs-toggle="tooltip"]').tooltip();

    function confirmSend() {
        Swal.fire({
            icon: 'question',
            title: '¿Enviar RFQ?',
            html: '¿Confirmas el envío a los <strong>{{ $totalSuppliers }}</strong> proveedores invitados?<br>' +
                  '<small class="text-muted">Se enviarán notificaciones por correo electrónico.</small>',
            showCancelButton: true,
            confirmButtonText: '<i class="ti ti-send me-1"></i>Sí, enviar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#198754'
        }).then(r => { if (r.isConfirmed) sendRFQ(); });
    }

    function confirmCancel() {
        Swal.fire({
            icon: 'warning',
            title: '¿Cancelar RFQ?',
            html: '<small class="text-muted">Esta acción no se puede deshacer.</small>',
            input: 'textarea',
            inputPlaceholder: 'Motivo de cancelación (opcional)',
            showCancelButton: true,
            confirmButtonText: 'Sí, cancelar',
            cancelButtonText: 'No',
            confirmButtonColor: '#dc3545'
        }).then(r => { if (r.isConfirmed) cancelRFQ(r.value); });
    }

    $('#sendRfqBtn, #sendRfqBtn2').on('click', confirmSend);
    $('#cancelRfqBtn, #cancelRfqBtn2').on('click', confirmCancel);
    $('.js-open-requisition-modal').on('click', function() {
        const url = $(this).data('url');
        const $modal = $('#requisitionInfoModal');
        const $content = $('#requisition-modal-content');

        $content.html(`
            <div class="modal-body p-5 text-center text-muted">
                <div class="spinner-border text-primary mb-3" role="status" aria-hidden="true"></div>
                <p class="mb-0">Cargando requisición...</p>
            </div>
        `);

        $modal.modal('show');
        $content.load(url, function(response, status) {
            if (status === 'error') {
                $content.html(`
                    <div class="modal-header border-0">
                        <h5 class="modal-title text-danger">
                            <i class="ti ti-alert-circle me-2"></i>Error al cargar la requisición
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger mb-0">
                            No fue posible cargar la requisición de origen. Intenta de nuevo.
                        </div>
                    </div>
                `);
            }
        });
    });

    function sendRFQ() {
        Swal.fire({ title: 'Enviando...', html: 'Por favor espera', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        $.ajax({
            url: '{{ route("rfq.send", $rfq) }}',
            method: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: r => {
                if (r.success) {
                    Swal.fire({ icon: 'success', title: '¡Enviada!', text: r.message, timer: 2000, showConfirmButton: false })
                        .then(() => location.reload());
                }
            },
            error: xhr => Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.message || 'No se pudo enviar' })
        });
    }

    function cancelRFQ(reason) {
        $.ajax({
            url: '{{ route("rfq.cancel", $rfq) }}',
            method: 'POST',
            data: { _token: '{{ csrf_token() }}', reason: reason },
            success: r => {
                if (r.success) {
                    Swal.fire({ icon: 'success', title: 'Cancelada', text: r.message, timer: 2000, showConfirmButton: false })
                        .then(() => location.reload());
                }
            },
            error: xhr => Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.message || 'No se pudo cancelar' })
        });
    }
});
</script>
@endpush
