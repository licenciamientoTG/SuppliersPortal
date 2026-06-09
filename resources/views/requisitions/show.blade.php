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

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0">
        <i class="ti ti-file-text me-2"></i>Requisicion {{ $requisition->folio }}
    </h4>
    <div class="d-flex gap-2 align-items-center">
        <span class="badge bg-{{ $requisition->status->badgeClass() }}">
            {{ $requisition->status->label() }}
        </span>
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
<div class="card mt-3 border-info">
    <div class="card-header bg-info-subtle border-0 d-flex justify-content-between align-items-center">
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

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="ti ti-info-circle me-2"></i>Informacion General
        </h5>
    </div>

    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <dl class="row mb-0">
                    <dt class="col-sm-5 text-muted">
                        <i class="ti ti-hash me-1"></i> Folio
                    </dt>
                    <dd class="col-sm-7">
                        <span class="fw-bold">{{ $requisition->folio }}</span>
                    </dd>

                    <dt class="col-sm-5 text-muted">
                        <i class="ti ti-building-bank me-1"></i> Compania
                    </dt>
                    <dd class="col-sm-7 fw-semibold">
                        {{ $requisition->company?->name ?? '-' }}
                    </dd>

                    <dt class="col-sm-5 text-muted">
                        <i class="ti ti-hierarchy-3 me-1"></i> Centro de costo
                    </dt>
                    <dd class="col-sm-7 fw-semibold">
                        {{ $requisition->primaryCostCenterLabel() }}
                    </dd>

                    <dt class="col-sm-5 text-muted">
                        <i class="ti ti-building me-1"></i> Departamento
                    </dt>
                    <dd class="col-sm-7 fw-semibold">
                        {{ $requisition->department?->name ?? '-' }}
                    </dd>

                    <dt class="col-sm-5 text-muted">
                        <i class="ti ti-map-pin me-1"></i> Punto de entrega
                    </dt>
                    <dd class="col-sm-7 fw-semibold">
                        {{ $requisition->receivingLocation ? $requisition->receivingLocation->code . ' - ' . $requisition->receivingLocation->name : '-' }}
                    </dd>

                    <dt class="col-sm-5 text-muted">
                        <i class="ti ti-calendar-stats me-1"></i> Ano fiscal
                    </dt>
                    <dd class="col-sm-7">
                        <span class="fw-semibold">{{ $requisition->fiscal_year }}</span>
                    </dd>

                    @if ($requisition->required_date)
                    <dt class="col-sm-5 text-muted">
                        <i class="ti ti-calendar-due me-1"></i> Fecha requerida
                    </dt>
                    <dd class="col-sm-7">
                        <span class="fw-semibold">{{ $requisition->required_date->format('d/m/Y') }}</span>
                    </dd>
                    @endif

                    @if ($requisition->description)
                    <dt class="col-sm-5 text-muted">
                        <i class="ti ti-file-description me-1"></i> Descripcion
                    </dt>
                    <dd class="col-sm-7">
                        {{ $requisition->description }}
                    </dd>
                    @endif
                </dl>
            </div>

            <div class="col-12 col-md-6">
                <dl class="row mb-0">
                    <dt class="col-sm-5 text-muted">
                        <i class="ti ti-user-check me-1"></i> Solicitado por
                    </dt>
                    <dd class="col-sm-7 fw-semibold">
                        {{ $requisition->requester?->name ?? '-' }}
                    </dd>

                    <dt class="col-sm-5 text-muted">
                        <i class="ti ti-calendar-event me-1"></i> Fecha creacion
                    </dt>
                    <dd class="col-sm-7">
                        <span class="fw-semibold">{{ $requisition->created_at?->format('d/m/Y H:i') ?? '-' }}</span>
                    </dd>

                    <dt class="col-sm-5 text-muted">
                        <i class="ti ti-calendar-time me-1"></i> Ultima actualizacion
                    </dt>
                    <dd class="col-sm-7">
                        <span class="fw-semibold">{{ $requisition->updated_at?->format('d/m/Y H:i') ?? '-' }}</span>
                    </dd>

                    @if ($requisition->reviewer)
                    <dt class="col-sm-5 text-muted">
                        <i class="ti ti-user-search me-1"></i> Revisado por
                    </dt>
                    <dd class="col-sm-7 fw-semibold">
                        {{ $requisition->reviewer->name }}
                    </dd>
                    @endif

                    @if ($requisition->reviewed_at)
                    <dt class="col-sm-5 text-muted">
                        <i class="ti ti-calendar-check me-1"></i> Fecha revision
                    </dt>
                    <dd class="col-sm-7">
                        <span class="fw-semibold">{{ $requisition->reviewed_at->format('d/m/Y H:i') }}</span>
                    </dd>
                    @endif

                    @if ($requisition->approver)
                    <dt class="col-sm-5 text-muted">
                        <i class="ti ti-user-check me-1"></i> Aprobado por
                    </dt>
                    <dd class="col-sm-7 fw-semibold">
                        {{ $requisition->approver->name }}
                    </dd>
                    @endif

                    @if ($requisition->approved_at)
                    <dt class="col-sm-5 text-muted">
                        <i class="ti ti-calendar-check me-1"></i> Fecha aprobacion
                    </dt>
                    <dd class="col-sm-7">
                        <span class="fw-semibold">{{ $requisition->approved_at->format('d/m/Y H:i') }}</span>
                    </dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="ti ti-list-details me-2"></i>Partidas
            <span class="badge bg-primary ms-2">{{ $requisition->items->count() }}</span>
        </h5>
    </div>

    <div class="card-body">
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
                        <th>Producto/Codigo</th>
                        <th>Descripcion</th>
                        <th width="80" class="text-end">Cantidad</th>
                        <th width="80" class="text-center">Unidad</th>
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
                            <strong>{{ $item->productService?->code ?? '-' }}</strong>
                            @if ($item->productService?->product_type)
                            <br>
                            <span class="badge bg-{{ $item->productService->product_type === 'SERVICIO' ? 'info' : 'primary' }} badge-sm">
                                <i class="ti ti-{{ $item->productService->product_type === 'SERVICIO' ? 'briefcase' : 'box' }} me-1"></i>
                                {{ $item->productService->product_type }}
                            </span>
                            @endif
                        </td>
                        <td>
                            {{ $item->description }}
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

        <div class="mt-3 p-3 bg-light rounded">
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

<div class="d-flex justify-content-between mt-3">
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
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
    });
</script>
@endpush
