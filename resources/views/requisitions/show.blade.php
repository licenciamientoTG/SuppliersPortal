@extends('layouts.zircos')

@section('title', 'Requisicion ' . $requisition->folio)

@section('page.title', 'Requisicion ' . $requisition->folio)

@section('page.breadcrumbs')
<li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
<li class="breadcrumb-item"><a href="{{ route('requisitions.index') }}">Requisiciones</a></li>
<li class="breadcrumb-item active">{{ $requisition->folio }}</li>
@endsection

@section('content')

@if (session('success'))
<div class="alert alert-success alert-dismissible fade show">
    <i class="ti ti-circle-check me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if (session('warning'))
<div class="alert alert-warning alert-dismissible fade show">
    <i class="ti ti-alert-triangle me-2"></i>{{ session('warning') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if (session('error'))
<div class="alert alert-danger alert-dismissible fade show">
    <i class="ti ti-octagon me-2"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="requisition-show-page">
    <section class="requisition-show-hero">
        <div class="requisition-show-logo-wrap">
            <img src="{{ asset('images/logos/Logo.png') }}" alt="TotalGas" class="requisition-show-logo-spinner">
        </div>
        <div class="requisition-show-hero-content">
            <div class="requisition-show-eyebrow">Seguimiento de requisición</div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <h4 class="mb-0">{{ $requisition->folio }}</h4>
                <span class="badge requisition-status-badge bg-{{ $requisition->status->badgeClass() }}">
                    {{ $requisition->status->label() }}
                </span>
            </div>
            <p class="mb-0">{{ $requisition->description ?: 'Solicitud de compra sin título registrado.' }}</p>
        </div>
        <div class="requisition-show-hero-meta">
            <span><i class="ti ti-building me-1"></i>{{ $requisition->company?->name ?? 'Sin compañía' }}</span>
            <span><i class="ti ti-user me-1"></i>{{ $requisition->requester?->name ?? 'Sin solicitante' }}</span>
            <span><i class="ti ti-calendar-event me-1"></i>{{ $requisition->created_at?->format('d/m/Y') ?? '-' }}</span>
        </div>
    </section>

    <div class="requisition-show-overview row g-3 mb-4">
        <div class="col-md-4">
            <div class="requisition-overview-stat">
                <span class="requisition-overview-icon text-primary bg-primary-subtle"><i class="ti ti-package"></i></span>
                <div><small>Partidas solicitadas</small><strong>{{ $requisition->items->count() }}</strong></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="requisition-overview-stat">
                <span class="requisition-overview-icon text-success bg-success-subtle"><i class="ti ti-box"></i></span>
                <div><small>Productos</small><strong>{{ $requisition->items->filter(fn($i) => $i->productService?->product_type === 'PRODUCTO')->count() }}</strong></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="requisition-overview-stat">
                <span class="requisition-overview-icon text-info bg-info-subtle"><i class="ti ti-briefcase"></i></span>
                <div><small>Servicios</small><strong>{{ $requisition->items->filter(fn($i) => $i->productService?->product_type === 'SERVICIO')->count() }}</strong></div>
            </div>
        </div>
    </div>

@if ($requisition->isPaused())
<div class="alert alert-warning">
    <i class="ti ti-alert-triangle me-2"></i>
    <strong>Requisicion pausada:</strong> {{ $requisition->pause_reason }}
    @if ($requisition->pauser)
    <br><small>Por {{ $requisition->pauser->name }} el {{ $requisition->paused_at->format('d/m/Y H:i') }}</small>
    @endif
</div>
@endif

@if ($requisition->feedbacks->isNotEmpty())
<div class="card requisition-show-card requisition-feedback-card mt-4 border-info">
    <div class="card-header requisition-show-section-heading bg-info-subtle border-0 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 text-info">
            <i class="ti ti-mail-share me-2"></i>Retroalimentacion de Compras
        </h5>
        <span class="badge bg-info text-white">{{ $requisition->feedbacks->count() }} registro(s)</span>
    </div>
    <div class="card-body">
        @foreach ($requisition->feedbacks as $feedback)
        <div class="border rounded p-3 {{ $loop->last ? '' : 'mb-3' }}">
            <div class="d-flex justify-content-between align-items-start gap-3">
                <div>
                    <div class="fw-bold">{{ $feedback->buyer?->name ?? 'Compras' }}</div>
                    <small class="text-muted">{{ $feedback->sent_at?->format('d/m/Y H:i') }}</small>
                </div>
                @if ($loop->first)
                <span class="badge bg-info-subtle text-info border border-info-subtle">Mas reciente</span>
                @endif
            </div>
            <p class="mb-0 mt-3 text-muted">{{ $feedback->message }}</p>
        </div>
        @endforeach
    </div>
</div>
@endif

<div class="card requisition-show-card requisition-details-card border-0">
    <div class="card-header requisition-show-section-heading">
        <h5 class="mb-0">
            <i class="ti ti-info-circle me-2"></i>Informacion General
        </h5>
    </div>

    <div class="card-body requisition-details-body">
        <div class="row g-3">
            <div class="col-lg-8">
                @php
                    $costCenterLabels = $requisition->costCenterLabels();
                @endphp
                <div class="requisition-detail-grid">
                    <div class="requisition-detail-tile">
                        <span><i class="ti ti-hash"></i> Folio</span>
                        <strong>{{ $requisition->folio }}</strong>
                    </div>
                    <div class="requisition-detail-tile">
                        <span><i class="ti ti-building-bank"></i> Compañía</span>
                        <strong>{{ $requisition->company?->name ?? '-' }}</strong>
                    </div>
                    <div class="requisition-detail-tile">
                        <span><i class="ti ti-hierarchy-3"></i> Centro de costo</span>
                        @if (count($costCenterLabels) <= 1)
                            <strong>{{ $costCenterLabels[0] ?? 'N/A' }}</strong>
                        @else
                            <strong>{{ count($costCenterLabels) }} centros de costo</strong>
                            <small>{{ collect($costCenterLabels)->join(' · ') }}</small>
                        @endif
                    </div>
                    <div class="requisition-detail-tile">
                        <span><i class="ti ti-building"></i> Departamento</span>
                        <strong>{{ $requisition->department?->name ?? '-' }}</strong>
                    </div>
                    <div class="requisition-detail-tile">
                        <span><i class="ti ti-map-pin"></i> Punto de entrega</span>
                        <strong>{{ $requisition->receivingLocation ? $requisition->receivingLocation->code . ' - ' . $requisition->receivingLocation->name : '-' }}</strong>
                    </div>
                    <div class="requisition-detail-tile">
                        <span><i class="ti ti-calendar-stats"></i> Año fiscal</span>
                        <strong>{{ $requisition->created_at?->year ?? now()->year }}</strong>
                    </div>
                    @if ($requisition->description)
                    <div class="requisition-detail-tile requisition-detail-tile-wide">
                        <span><i class="ti ti-file-description"></i> Título de la requisición</span>
                        <strong>{{ $requisition->description }}</strong>
                    </div>
                    @endif
                </div>
            </div>

            <div class="col-lg-4">
                <aside class="requisition-trace-card">
                    <div class="requisition-trace-owner">
                        <span class="requisition-trace-avatar"><i class="ti ti-user"></i></span>
                        <div><small>Solicitado por</small><strong>{{ $requisition->requester?->name ?? '-' }}</strong></div>
                    </div>
                    <div class="requisition-trace-list">
                        <div><i class="ti ti-calendar-event"></i><span>Creada</span><strong>{{ $requisition->created_at?->format('d/m/Y H:i') ?? '-' }}</strong></div>
                        <div><i class="ti ti-refresh"></i><span>Última actualización</span><strong>{{ $requisition->updated_at?->format('d/m/Y H:i') ?? '-' }}</strong></div>
                        @if ($requisition->reviewer || $requisition->reviewed_at)
                        <div><i class="ti ti-user-search"></i><span>Revisión</span><strong>{{ $requisition->reviewer?->name ?? 'Pendiente' }}{{ $requisition->reviewed_at ? ' · ' . $requisition->reviewed_at->format('d/m/Y H:i') : '' }}</strong></div>
                        @endif
                        @if ($requisition->approver || $requisition->approved_at)
                        <div><i class="ti ti-circle-check"></i><span>Aprobación</span><strong>{{ $requisition->approver?->name ?? 'Pendiente' }}{{ $requisition->approved_at ? ' · ' . $requisition->approved_at->format('d/m/Y H:i') : '' }}</strong></div>
                        @endif
                    </div>
                </aside>
            </div>
        </div>
    </div>
</div>

<div class="card requisition-show-card requisition-items-card mt-4 border-0">
    <div class="card-header requisition-show-section-heading d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="ti ti-list-details me-2"></i>Partidas
            <span class="badge bg-primary ms-2">{{ $requisition->items->count() }}</span>
        </h5>
    </div>

    <div class="card-body requisition-items-body">
        @if ($requisition->items->isEmpty())
        <div class="text-center text-muted py-4">
            <i class="ti ti-inbox fs-1 d-block mb-2"></i>
            <p>No hay partidas en esta requisicion</p>
        </div>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th width="40" class="text-center">#</th>
                        <th>Producto</th>
                        <th width="80" class="text-end">Cantidad</th>
                        <th width="80" class="text-center">Unidad</th>
                        <th width="140" class="text-end">Precio Unit.</th>
                        <th width="150">Categoria Gasto</th>
                        <th width="180">Subcategoria Presupuestal</th>
                        <th width="150">Proveedor Sug.</th>
                        <th width="60" class="text-center">Notas</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($requisition->items as $item)
                    <tr>
                        <td class="text-center text-muted">{{ $item->line_number }}</td>
                        <td>
                            <strong>{{ $item->productService?->short_name ?? $item->productService?->description ?? '-' }}</strong>
                            @if ($item->productService?->code)
                            <br>
                            <small class="text-muted">{{ $item->productService->code }}</small>
                            @endif
                            @if ($item->productService?->product_type)
                            <br>
                            <span class="badge bg-{{ $item->productService->product_type === 'SERVICIO' ? 'info' : 'primary' }} badge-sm">
                                <i class="ti ti-{{ $item->productService->product_type === 'SERVICIO' ? 'briefcase' : 'box' }} me-1"></i>
                                {{ $item->productService->product_type }}
                            </span>
                            @endif
                            @if ($item->productService?->brand || $item->productService?->model)
                            <br>
                            <small class="text-muted">
                                <i class="ti ti-tag me-1"></i>
                                {{ collect([$item->productService->brand, $item->productService->model])->filter()->join(' / ') }}
                            </small>
                            @endif
                        </td>
                        <td class="text-end fw-semibold">{{ number_format($item->quantity, 3) }}</td>
                        <td class="text-center">
                            <span class="badge bg-secondary">{{ $item->unit }}</span>
                        </td>
                        <td class="text-end">
                            @if(!is_null($item->unit_price))
                                <span class="fw-semibold">
                                    {{ $item->currency_code ?? 'MXN' }} {{ number_format($item->unit_price, 4) }}
                                </span>
                                @if($item->contract_product_id)
                                    @php $currentPrice = $item->contractProduct?->unit_price; @endphp
                                    @if($currentPrice && (float)$currentPrice !== (float)$item->unit_price)
                                        <br>
                                        <span class="badge bg-warning text-dark mt-1"
                                            title="Precio actual en contrato: {{ $item->currency_code ?? 'MXN' }} {{ number_format($currentPrice, 4) }}">
                                            <i class="ti ti-refresh me-1"></i>Precio actualizado en contrato
                                        </span>
                                    @endif
                                @endif
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-info">{{ $item->expenseCategory?->name ?? '-' }}</span>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $item->budgetCedula?->name ?? '-' }}</div>
                            <small class="text-muted">Cedula presupuestal</small>
                        </td>
                        <td>
                            <small>{{ $item->suggestedVendor?->name ?? '-' }}</small>
                        </td>
                        <td class="text-center">
                            @if ($item->notes)
                            <i class="ti ti-note text-info"
                                data-bs-toggle="tooltip"
                                title="{{ $item->notes }}"></i>
                            @else
                            -
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="requisition-item-summary mt-4">
            <div class="row">
                <div class="col-md-4">
                    <i class="ti ti-package me-2 text-primary"></i>
                    <strong>Total de Partidas:</strong>
                    <span class="badge bg-primary ms-2">{{ $requisition->items->count() }}</span>
                </div>
                <div class="col-md-4">
                    <i class="ti ti-box me-2 text-primary"></i>
                    <strong>Productos:</strong>
                    <span>{{ $requisition->items->filter(fn($i) => $i->productService?->product_type === 'PRODUCTO')->count() }}</span>
                </div>
                <div class="col-md-4">
                    <i class="ti ti-briefcase me-2 text-info"></i>
                    <strong>Servicios:</strong>
                    <span>{{ $requisition->items->filter(fn($i) => $i->productService?->product_type === 'SERVICIO')->count() }}</span>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>

@if ($requisition->source_type === 'contract' && $requisition->purchaseOrders->isNotEmpty())
<div class="card requisition-show-card requisition-orders-card mt-4 border-0">
    <div class="card-header requisition-show-section-heading d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="ti ti-file-invoice me-2"></i>Ordenes de Compra Generadas
        </h5>
        <span class="badge bg-success">{{ $requisition->purchaseOrders->count() }}</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Folio OC</th>
                        <th>Proveedor</th>
                        <th>Moneda</th>
                        <th class="text-end">Total</th>
                        <th>Estado</th>
                        <th class="text-center">Accion</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($requisition->purchaseOrders as $purchaseOrder)
                    <tr>
                        <td class="fw-bold">{{ $purchaseOrder->folio }}</td>
                        <td>{{ $purchaseOrder->supplier?->company_name ?? '-' }}</td>
                        <td>{{ $purchaseOrder->currency ?? 'MXN' }}</td>
                        <td class="text-end fw-semibold">{{ number_format((float) $purchaseOrder->total, 2) }}</td>
                        <td>
                            <span class="badge bg-{{ $purchaseOrder->getStatusBadgeClass() }}">
                                {{ $purchaseOrder->getStatusLabel() }}
                            </span>
                        </td>
                        <td class="text-center">
                            <a href="{{ route('purchase-orders.show', $purchaseOrder) }}" class="btn btn-sm btn-outline-primary">
                                <i class="ti ti-eye me-1"></i>Ver OC
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

<div class="requisition-show-actions d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mt-4">
    <a href="{{ route('requisitions.index') }}" class="btn btn-outline-secondary">
        <i class="ti ti-arrow-left me-1"></i>Regresar al Listado
    </a>

    <div class="d-flex gap-2">
        @if ($requisition->isDraft() || $requisition->isPaused())
        <a href="{{ route('requisitions.edit', $requisition) }}" class="btn btn-primary">
            <i class="ti ti-edit me-1"></i>Editar Requisicion
        </a>
        @endif
    </div>
</div>
</div>
@endsection

@push('styles')
<style>
    .requisition-show-page {
        max-width: 1320px;
        margin: 0 auto;
    }

    .requisition-show-hero {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1.5rem;
        margin-bottom: 1rem;
        overflow: hidden;
        background: linear-gradient(115deg, #f7fbff 0%, #fff 62%);
        border: 1px solid #dce9f3;
        border-radius: 0.85rem;
        box-shadow: 0 0.35rem 1.25rem rgba(35, 79, 119, 0.06);
        animation: requisition-show-enter 0.45s ease both;
    }

    .requisition-overview-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        border-radius: 0.75rem;
    }

    .requisition-show-logo-wrap {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 3.25rem;
        height: 3.25rem;
        flex: 0 0 auto;
        isolation: isolate;
        animation: requisition-show-logo-arrival 1.15s cubic-bezier(0.22, 1, 0.36, 1) both;
    }

    .requisition-show-logo-wrap::before {
        position: absolute;
        top: -62vh;
        left: 50%;
        z-index: 0;
        width: 0.35rem;
        height: 62vh;
        content: '';
        pointer-events: none;
        background: linear-gradient(180deg, transparent 0%, rgba(24, 138, 226, 0.15) 42%, rgba(75, 211, 150, 0.8) 100%);
        border-radius: 999px;
        box-shadow: 0 0 0.7rem rgba(24, 138, 226, 0.65), 0 0 1.5rem rgba(75, 211, 150, 0.3);
        opacity: 0;
        transform: translateX(-50%);
        animation: requisition-show-logo-trail 1.15s ease-out both;
    }

    .requisition-show-logo-spinner {
        position: relative;
        z-index: 1;
        width: 2.4rem;
        height: 2.4rem;
        object-fit: contain;
        animation: requisition-show-logo-spin 8s linear infinite;
    }

    .requisition-show-hero-content { flex: 1; min-width: 0; }

    .requisition-show-eyebrow {
        margin-bottom: 0.3rem;
        color: #188ae2;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .requisition-show-hero h4 { color: #20354a; font-weight: 700; }
    .requisition-show-hero p { margin-top: 0.45rem; color: #718096; font-size: 0.85rem; }

    .requisition-status-badge {
        padding: 0.45rem 0.7rem;
        font-size: 0.72rem;
        font-weight: 700;
    }

    .requisition-show-hero-meta {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
        min-width: 13rem;
        padding-left: 1rem;
        color: #617287;
        font-size: 0.78rem;
        border-left: 1px solid #e2eaf1;
    }

    .requisition-show-overview { margin-inline: 0; }

    .requisition-overview-stat {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        height: 100%;
        padding: 1rem 1.15rem;
        background: #fff;
        border: 1px solid #e4ebf1;
        border-radius: 0.7rem;
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .requisition-overview-stat:hover {
        border-color: #cfe1ef;
        box-shadow: 0 0.5rem 1rem rgba(35, 79, 119, 0.09);
        transform: translateY(-3px);
    }

    .requisition-overview-icon { width: 2.35rem; height: 2.35rem; font-size: 1.1rem; }
    .requisition-overview-stat small { display: block; color: #75869a; font-size: 0.74rem; }
    .requisition-overview-stat strong { display: block; color: #29425b; font-size: 1.25rem; line-height: 1.1; }

    .requisition-show-card {
        overflow: hidden;
        border-radius: 0.8rem;
        box-shadow: 0 0.25rem 1rem rgba(35, 79, 119, 0.05);
        animation: requisition-show-enter 0.45s ease both;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .requisition-show-card:hover {
        box-shadow: 0 0.65rem 1.5rem rgba(35, 79, 119, 0.09);
        transform: translateY(-2px);
    }

    .requisition-details-card { animation-delay: 0.06s; }
    .requisition-items-card { animation-delay: 0.12s; }
    .requisition-orders-card { animation-delay: 0.18s; }

    .requisition-show-section-heading {
        min-height: 4rem;
        padding: 1rem 1.25rem;
        background: #fff;
        border-bottom: 1px solid #e8eef3 !important;
    }

    .requisition-show-section-heading h5 { color: #2c4258; font-weight: 700; }
    .requisition-details-body, .requisition-items-body { padding: 1.35rem; }

    .requisition-detail-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
    }

    .requisition-detail-tile {
        min-height: 5.4rem;
        padding: 0.9rem 1rem;
        background: #fbfcfd;
        border: 1px solid #e8eef3;
        border-radius: 0.65rem;
        transition: background-color 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
    }

    .requisition-detail-tile:hover {
        background: #f5faff;
        border-color: #cfe4f4;
        transform: translateY(-1px);
    }

    .requisition-detail-tile > span,
    .requisition-trace-owner small,
    .requisition-trace-list span {
        display: block;
        margin-bottom: 0.45rem;
        color: #78899d;
        font-size: 0.69rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        text-transform: uppercase;
    }

    .requisition-detail-tile > span i { margin-right: 0.3rem; color: #188ae2; }

    .requisition-detail-tile strong {
        display: block;
        color: #30455b;
        font-size: 0.86rem;
        font-weight: 600;
    }

    .requisition-detail-tile small { display: block; margin-top: 0.35rem; color: #8090a1; font-size: 0.73rem; }
    .requisition-detail-tile-wide { grid-column: 1 / -1; min-height: auto; }

    .requisition-trace-card {
        height: 100%;
        padding: 1rem;
        background: linear-gradient(180deg, #f7fbff, #fff);
        border: 1px solid #dceaf5;
        border-radius: 0.7rem;
    }

    .requisition-trace-owner { display: flex; align-items: center; gap: 0.75rem; padding-bottom: 1rem; border-bottom: 1px solid #dfeaf2; }
    .requisition-trace-owner small { margin-bottom: 0.2rem; }
    .requisition-trace-owner strong { color: #2c435b; font-size: 0.88rem; }

    .requisition-trace-avatar {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.4rem;
        height: 2.4rem;
        color: #188ae2;
        background: #e6f3fd;
        border-radius: 50%;
        font-size: 1.1rem;
    }

    .requisition-trace-list { margin-top: 0.5rem; }
    .requisition-trace-list > div { position: relative; padding: 0.7rem 0 0.7rem 1.65rem; border-bottom: 1px solid #e8eef3; }
    .requisition-trace-list > div:last-child { border-bottom: 0; }
    .requisition-trace-list > div > i { position: absolute; top: 0.75rem; left: 0; color: #5a9ed4; }
    .requisition-trace-list span { margin-bottom: 0.2rem; }
    .requisition-trace-list strong { display: block; color: #3c5269; font-size: 0.77rem; font-weight: 600; line-height: 1.35; }

    .requisition-feedback-card .card-body { padding: 1.25rem; }
    .requisition-feedback-card .card-body > div { border-color: #cfe8f5 !important; background: #fbfeff; }

    .requisition-items-card .table { margin-bottom: 0; }
    .requisition-items-card .table thead th {
        padding: 0.8rem 0.75rem;
        color: #52677d;
        background: #f3f6f8;
        border-bottom-width: 1px;
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.035em;
        text-transform: uppercase;
    }

    .requisition-items-card .table tbody td { padding: 0.9rem 0.75rem; color: #40546a; }
    .requisition-items-card .table tbody tr:hover { background: #f8fbfe; }

    .requisition-show-page a:not(.btn) {
        transition: color 0.2s ease;
    }

    .requisition-show-page .btn,
    .requisition-show-page .table a {
        transition: transform 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease;
    }

    .requisition-show-page .btn:hover,
    .requisition-show-page .table a:hover {
        box-shadow: 0 0.35rem 0.75rem rgba(35, 79, 119, 0.15);
        transform: translateY(-1px);
    }

    .requisition-item-summary {
        padding: 1rem 1.15rem;
        color: #536b82;
        background: #f4f9fd;
        border: 1px solid #d8eaf7;
        border-radius: 0.65rem;
    }

    .requisition-show-actions {
        padding: 1rem 1.15rem;
        background: #fff;
        border: 1px solid #e2e9ef;
        border-radius: 0.75rem;
        box-shadow: 0 0.25rem 1rem rgba(35, 79, 119, 0.04);
        animation: requisition-show-enter 0.45s 0.18s ease both;
    }

    @keyframes requisition-show-logo-spin {
        to { transform: rotate(360deg); }
    }

    @keyframes requisition-show-logo-arrival {
        0% { opacity: 0; transform: translateY(-105vh) scale(2.4); }
        72% { opacity: 1; transform: translateY(0.45rem) scale(0.9); }
        100% { opacity: 1; transform: translateY(0) scale(1); }
    }

    @keyframes requisition-show-logo-trail {
        0%, 10% { opacity: 0; }
        28% { opacity: 0.85; }
        70% { opacity: 0.35; }
        100% { opacity: 0; }
    }

    @keyframes requisition-show-enter {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @media (prefers-reduced-motion: reduce) {
        .requisition-show-hero,
        .requisition-show-logo-wrap,
        .requisition-show-logo-wrap::before,
        .requisition-show-logo-spinner,
        .requisition-show-card,
        .requisition-show-actions {
            animation: none;
        }

        .requisition-overview-stat,
        .requisition-show-card,
        .requisition-detail-tile,
        .requisition-show-page .btn,
        .requisition-show-page .table a {
            transition: none;
        }
    }

    @media (max-width: 767.98px) {
        .requisition-show-hero { align-items: flex-start; flex-wrap: wrap; padding: 1.1rem; }
        .requisition-show-hero-meta { width: 100%; padding: 0.75rem 0 0; border-top: 1px solid #e2eaf1; border-left: 0; }
        .requisition-details-body, .requisition-items-body { padding: 1rem; }
        .requisition-detail-grid { grid-template-columns: 1fr; }
        .requisition-detail-tile-wide { grid-column: auto; }
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
    });
</script>
@endpush
