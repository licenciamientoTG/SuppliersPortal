@extends('layouts.zircos-supplier')

@section('title', 'Portal de Proveedores - Dashboard')

@section('page.title', 'Portal de Proveedores')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Dashboard Proveedor</li>
@endsection

@section('content')
<div class="container-fluid py-4">
    
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">
                <i class="ti ti-building-store me-2"></i>
                Portal de Proveedores
            </h1>
            <p class="text-muted mb-0">{{ $supplier->company_name }}</p>
        </div>
    </div>

    {{-- Estadísticas --}}
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-start border-warning border-4">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="ti ti-clock-hour-4 fs-1 text-warning"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">RFQs Pendientes</h6>
                            <h3 class="mb-0">{{ $stats['pending'] }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-start border-secondary border-4">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="ti ti-file-text fs-1 text-secondary"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Borradores</h6>
                            <h3 class="mb-0">{{ $stats['draft'] }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-start border-info border-4">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="ti ti-send fs-1 text-info"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Enviadas</h6>
                            <h3 class="mb-0">{{ $stats['submitted'] }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-start border-success border-4">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="ti ti-check fs-1 text-success"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Aprobadas</h6>
                            <h3 class="mb-0">{{ $stats['approved'] }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Lista de RFQs --}}
    <div class="card">
        <div class="card-header bg-light">
            <h5 class="mb-0">
                <i class="ti ti-file-invoice me-2"></i>
                Solicitudes de Cotización (RFQs)
            </h5>
        </div>
        <div class="card-body">
            @if($rfqs->isNotEmpty())
            <div class="table-responsive">
                <table id="rfqs-table" class="table table-hover table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Folio RFQ</th>
                            <th>Requisición</th>
                            <th>Fecha Envío</th>
                            <th>Fecha Límite</th>
                            <th>Partidas</th>
                            <th>Estado</th>
                            <th>Mi Respuesta</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rfqs as $rfq)
                        @php
                            // Obtener respuesta del proveedor para esta RFQ
                            $myResponse = $rfq->rfqResponses
                                ->where('supplier_id', $supplier->id)
                                ->first();
                            
                            // Contar partidas
                            $itemsCount = $rfq->quotation_group_id 
                                ? $rfq->quotationGroup->items->count() 
                                : 1;
                            
                            // Calcular días restantes
                            $daysRemaining = $rfq->response_deadline 
                                ? now()->diffInDays($rfq->response_deadline, false) 
                                : null;
                            $daysRemainingLabel = $daysRemaining !== null
                                ? format_days(abs($daysRemaining))
                                : null;
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $rfq->rfq_number }}</strong>
                            </td>
                            <td>
                                <a href="#" class="text-decoration-none">
                                    {{ $rfq->requisition->requisition_number ?? 'N/A' }}
                                </a>
                            </td>
                            <td>
                                <small class="text-muted">
                                    {{ $rfq->sent_at ? $rfq->sent_at->format('d/m/Y') : '-' }}
                                </small>
                            </td>
                            <td>
                                @if($rfq->response_deadline)
                                    @if($daysRemaining < 0)
                                        <span class="badge bg-danger">
                                            <i class="ti ti-alert-triangle me-1"></i>
                                            Vencida hace {{ $daysRemainingLabel }} días
                                        </span>
                                    @elseif($daysRemaining === 0)
                                        <span class="badge bg-warning">
                                            <i class="ti ti-clock me-1"></i>
                                            Vence hoy
                                        </span>
                                    @elseif($daysRemaining <= 3)
                                        <span class="badge bg-warning">
                                            <i class="ti ti-clock me-1"></i>
                                            {{ $daysRemainingLabel }} días
                                        </span>
                                    @else
                                        <span class="badge bg-info">
                                            <i class="ti ti-calendar me-1"></i>
                                            {{ $daysRemainingLabel }} días
                                        </span>
                                    @endif
                                    <br>
                                    <small class="text-muted">{{ $rfq->response_deadline->format('d/m/Y') }}</small>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-secondary">{{ $itemsCount }} partida(s)</span>
                            </td>
                            <td>
                                @switch($rfq->status)
                                    @case('DRAFT')
                                        <span class="badge bg-secondary">Borrador</span>
                                        @break
                                    @case('SENT')
                                        <span class="badge bg-warning">Enviada</span>
                                        @break
                                    @case('RECEIVED')
                                        <span class="badge bg-info">Respuestas Recibidas</span>
                                        @break
                                    @case('EVALUATED')
                                        <span class="badge bg-primary">Evaluada</span>
                                        @break
                                    @case('COMPLETED')
                                        <span class="badge bg-success">Completada</span>
                                        @break
                                    @case('CANCELLED')
                                        <span class="badge bg-danger">Cancelada</span>
                                        @break
                                    @default
                                        <span class="badge bg-light text-dark">{{ $rfq->status }}</span>
                                @endswitch
                            </td>
                            <td>
                                @if($myResponse)
                                    @switch($myResponse->status)
                                        @case('DRAFT')
                                            <span class="badge bg-secondary">
                                                <i class="ti ti-file-text me-1"></i>Borrador
                                            </span>
                                            @break
                                        @case('SUBMITTED')
                                            <span class="badge bg-info">
                                                <i class="ti ti-send me-1"></i>Enviada
                                            </span>
                                            @break
                                        @case('APPROVED')
                                            <span class="badge bg-success">
                                                <i class="ti ti-check me-1"></i>Aprobada
                                            </span>
                                            @break
                                        @case('REJECTED')
                                            <span class="badge bg-danger">
                                                <i class="ti ti-x me-1"></i>Rechazada
                                            </span>
                                            @break
                                    @endswitch
                                @else
                                    <span class="badge bg-light text-dark">
                                        <i class="ti ti-clock me-1"></i>Pendiente
                                    </span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if(in_array($rfq->status, ['SENT', 'RECEIVED']))
                                    <a href="{{ route('supplier.rfq.show', $rfq) }}" 
                                       class="btn btn-sm btn-primary" 
                                       title="Ver y cotizar">
                                        <i class="ti ti-edit me-1"></i>
                                        @if($myResponse && $myResponse->status === 'DRAFT')
                                            Continuar
                                        @elseif($myResponse && $myResponse->status === 'SUBMITTED')
                                            Ver
                                        @else
                                            Cotizar
                                        @endif
                                    </a>
                                @else
                                    <a href="{{ route('supplier.rfq.show', $rfq) }}" 
                                       class="btn btn-sm btn-outline-secondary" 
                                       title="Ver detalles">
                                        <i class="ti ti-eye"></i>
                                        Ver
                                    </a>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="text-center py-5">
                <i class="ti ti-file-off fs-1 text-muted mb-3 d-block"></i>
                <p class="text-muted mb-0">No tienes RFQs asignadas</p>
            </div>
            @endif
        </div>
    </div>

    {{-- Ayuda rápida --}}
    <div class="card mt-4 border-primary">
        <div class="card-body">
            <h6 class="text-primary mb-3">
                <i class="ti ti-info-circle me-2"></i>
                Información Importante
            </h6>
            <ul class="mb-0 small">
                <li>Las cotizaciones deben enviarse antes de la <strong>Fecha Límite</strong> indicada.</li>
                <li>Puedes guardar tus cotizaciones como <strong>Borrador</strong> y enviarlas posteriormente.</li>
                <li>Una vez <strong>enviada</strong> la cotización, no podrás modificarla.</li>
                <li>Adjunta documentación técnica en formato PDF (máx. 5MB por archivo).</li>
            </ul>
        </div>
    </div>

</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<style>
    .table thead th {
        font-weight: 600;
        font-size: 0.875rem;
    }
    
    .badge {
        font-weight: 500;
        padding: 0.35em 0.65em;
    }
    
    .card {
        box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
        border: 1px solid rgba(0,0,0,0.125);
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    const $rfqsTable = $('#rfqs-table');

    if (!$rfqsTable.length) {
        return;
    }

    $rfqsTable.DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-MX.json'
        },
        order: [[2, 'desc']], // Ordenar por fecha de envío
        pageLength: 25,
        responsive: true,
        columnDefs: [
            { orderable: false, targets: -1 } // Deshabilitar ordenamiento en columna de acciones
        ]
    });
});
</script>
@endpush
