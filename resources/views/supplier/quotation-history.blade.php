@extends('layouts.zircos-supplier')

@section('title', 'Historial de Cotizaciones')

@section('page.title', 'Historial de Cotizaciones')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('supplier.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Historial de Cotizaciones</li>
@endsection

@section('content')
<div class="container-fluid py-4">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="ti ti-history me-2"></i>
                Historial de Cotizaciones
            </h1>
            <p class="text-muted mb-0">Todas tus cotizaciones enviadas</p>
        </div>
        <a href="{{ route('supplier.dashboard') }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-2"></i>Volver
        </a>
    </div>

    {{-- Estadísticas Rápidas --}}
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-start border-secondary border-4">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="ti ti-file-text fs-1 text-secondary"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Borradores</h6>
                            <h3 class="mb-0">{{ $responses->where('status', 'DRAFT')->count() }}</h3>
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
                            <h3 class="mb-0">{{ $responses->where('status', 'SUBMITTED')->count() }}</h3>
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
                            <h3 class="mb-0">{{ $responses->where('status', 'APPROVED')->count() }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-start border-danger border-4">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="ti ti-x fs-1 text-danger"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Rechazadas</h6>
                            <h3 class="mb-0">{{ $responses->where('status', 'REJECTED')->count() }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabla de Historial --}}
    <div class="card">
        <div class="card-header bg-light">
            <h5 class="mb-0">
                <i class="ti ti-list me-2"></i>
                Todas las Cotizaciones
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="quotations-table" class="table table-hover table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha</th>
                            <th>RFQ</th>
                            <th>Requisición</th>
                            <th>Partida</th>
                            <th>Precio Unit.</th>
                            <th>Cantidad</th>
                            <th>Subtotal</th>
                            <th>Estado</th>
                            <th>Adjunto</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($responses as $response)
                        <tr>
                            <td>
                                <small class="text-muted">
                                    @if($response->submitted_at)
                                        {{ $response->submitted_at->format('d/m/Y H:i') }}
                                        <br>
                                        <span class="badge bg-light text-dark">
                                            {{ $response->submitted_at->diffForHumans() }}
                                        </span>
                                    @elseif($response->created_at)
                                        {{ $response->created_at->format('d/m/Y H:i') }}
                                    @else
                                        -
                                    @endif
                                </small>
                            </td>
                            <td>
                                <strong>{{ $response->rfq->rfq_number }}</strong>
                            </td>
                            <td>
                                {{ $response->rfq->requisition->requisition_number ?? 'N/A' }}
                            </td>
                            <td>
                                <div style="max-width: 250px;">
                                    <strong>{{ $response->requisitionItem->product_service }}</strong>
                                    @if($response->brand || $response->model)
                                    <br>
                                    <small class="text-muted">
                                        @if($response->brand){{ $response->brand }}@endif
                                        @if($response->brand && $response->model) - @endif
                                        @if($response->model){{ $response->model }}@endif
                                    </small>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span class="text-nowrap">
                                    ${{ number_format($response->unit_price, 2) }}
                                </span>
                            </td>
                            <td>
                                {{ $response->quantity }}
                            </td>
                            <td>
                                <strong class="text-primary text-nowrap">
                                    ${{ number_format($response->subtotal, 2) }}
                                </strong>
                            </td>
                            <td>
                                @switch($response->status)
                                    @case('DRAFT')
                                        <span class="badge bg-secondary">
                                            <i class="ti ti-file-text me-1"></i>
                                            Borrador
                                        </span>
                                        @break
                                    @case('SUBMITTED')
                                        <span class="badge bg-info">
                                            <i class="ti ti-send me-1"></i>
                                            Enviada
                                        </span>
                                        @break
                                    @case('APPROVED')
                                        <span class="badge bg-success">
                                            <i class="ti ti-check me-1"></i>
                                            Aprobada
                                        </span>
                                        @break
                                    @case('REJECTED')
                                        <span class="badge bg-danger">
                                            <i class="ti ti-x me-1"></i>
                                            Rechazada
                                        </span>
                                        @break
                                    @default
                                        <span class="badge bg-light text-dark">
                                            {{ $response->status }}
                                        </span>
                                @endswitch
                            </td>
                            <td class="text-center">
                                @if($response->attachment_path)
                                    <a href="{{ route('supplier.quotation.download', $response) }}" 
                                       class="btn btn-sm btn-outline-primary"
                                       title="Descargar adjunto"
                                       target="_blank">
                                        <i class="ti ti-download"></i>
                                    </a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($response->status === 'DRAFT')
                                    {{-- Editar borrador --}}
                                    <a href="{{ route('supplier.rfq.show', $response->rfq) }}" 
                                       class="btn btn-sm btn-primary me-1"
                                       title="Continuar editando">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                    {{-- Eliminar borrador --}}
                                    <form action="{{ route('supplier.quotation.draft.delete', $response) }}" 
                                          method="POST" 
                                          class="d-inline delete-draft-form">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-danger delete-draft-btn"
                                                title="Eliminar borrador">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </form>
                                @else
                                    {{-- Ver detalle --}}
                                    <a href="{{ route('supplier.rfq.show', $response->rfq) }}" 
                                       class="btn btn-sm btn-outline-secondary"
                                       title="Ver detalles">
                                        <i class="ti ti-eye"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center py-5">
                                <i class="ti ti-file-off fs-1 text-muted mb-3 d-block"></i>
                                <p class="text-muted mb-0">No tienes cotizaciones registradas</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
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
        white-space: nowrap;
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    
    // =========================================================================
    // DataTable
    // =========================================================================
    $('#quotations-table').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-MX.json'
        },
        order: [[0, 'desc']], // Ordenar por fecha (más reciente primero)
        pageLength: 25,
        responsive: true,
        columnDefs: [
            { orderable: false, targets: [-1, -2] } // Deshabilitar orden en adjunto y acciones
        ]
    });
    
    // =========================================================================
    // Eliminar borrador con confirmación
    // =========================================================================
    $('.delete-draft-btn').on('click', function(e) {
        e.preventDefault();
        const form = $(this).closest('.delete-draft-form');
        
        Swal.fire({
            icon: 'warning',
            title: '¿Eliminar borrador?',
            text: 'Esta acción no se puede deshacer',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
    
});
</script>
@endpush
