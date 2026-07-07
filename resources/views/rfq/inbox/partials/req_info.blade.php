{{-- Header del Modal con estilo Zircos --}}
<div class="modal-header bg-info text-white border-0">
    <h5 class="modal-title">
        <i class="ti ti-clipboard-list me-2"></i>Requisición: {{ $requisition->folio }}
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body p-4">
    <div class="row g-3">
        {{-- COLUMNA IZQUIERDA: Origen y Solicitante --}}
        <div class="col-md-6 border-end">
            <h6 class="text-uppercase fs-11 fw-bold text-muted mb-3" style="letter-spacing: 0.5px;">
                <i class="ti ti-user-circle me-1"></i>Origen de la Solicitud
            </h6>
            <div class="d-flex align-items-center mb-2">
                <div class="avatar-xs me-2">
                    <span class="avatar-title rounded-circle bg-soft-info text-info">
                        <i class="ti ti-user fs-16"></i>
                    </span>
                </div>
                <div>
                    <small class="text-muted d-block">Solicitado por</small>
                    <span class="fw-semibold">{{ $requisition->requester->full_name ?? $requisition->requester->name }}</span> {{-- --}}
                </div>
            </div>
            <p class="mb-1 small">
                <strong>Departamento:</strong> {{ $requisition->department->name ?? 'N/A' }} {{-- --}}
            </p>
            <p class="mb-0 small">
                <strong>Centro de Costos:</strong> {{ $requisition->primaryCostCenter()?->name ?? 'N/A' }} {{-- --}}
            </p>
            @if($requisition->receivingLocation)
            <p class="mb-0 small mt-1">
                <strong>Punto de Entrega:</strong>
                <i class="ti ti-map-pin text-primary me-1"></i>
                {{ $requisition->receivingLocation->code }} - {{ $requisition->receivingLocation->name }}
            </p>
            @endif
        </div>

        {{-- COLUMNA DERECHA: Tiempos y Estado --}}
        <div class="col-md-6 ps-md-4">
            <h6 class="text-uppercase fs-11 fw-bold text-muted mb-3" style="letter-spacing: 0.5px;">
                <i class="ti ti-calendar-event me-1"></i>Tiempos y Estatus
            </h6>
            <p class="mb-2 small">
                <i class="ti ti-flag text-info me-1"></i>
                <strong>Estado:</strong> 
                <span class="badge bg-soft-info text-info border border-info border-opacity-25 px-2">
                    {{ $requisition->statusLabel() }} {{-- --}}
                </span>
            </p>
            <p class="mb-0 small text-muted">
                <i class="ti ti-clock me-1"></i>Registrada: {{ $requisition->created_at->diffForHumans() }}
            </p>
        </div>

        {{-- SECCIÓN: Descripción General --}}
        @if($requisition->description)
        <div class="col-12 mt-3 pt-3 border-top">
            <h6 class="text-uppercase fs-11 fw-bold text-muted mb-2">Justificación / Notas</h6>
            <div class="p-2 bg-light rounded border border-dashed">
                <p class="small mb-0 text-dark">{{ $requisition->description }}</p> {{-- --}}
            </div>
        </div>
        @endif

        {{-- SECCIÓN: Listado de Partidas --}}
        <div class="col-12 mt-4">
            <h6 class="text-uppercase fs-11 fw-bold text-muted mb-2">Partidas Solicitadas</h6>
            <div class="table-responsive rounded border">
                <table class="table table-sm table-nowrap table-hover mb-0">
                    <thead class="table-light">
                        <tr class="fs-12">
                            <th class="border-0">Descripción del Artículo / Servicio</th>
                            <th class="border-0 text-center" style="width: 80px;">Cant.</th>
                            <th class="border-0 text-center" style="width: 80px;">U.M.</th>
                        </tr>
                    </thead>
                    <tbody class="fs-13">
                        @forelse($requisition->items as $item) {{-- --}}
                        <tr>
                            <td class="text-wrap">
                                <span class="fw-medium text-dark">{{ $item->description }}</span>
                            </td>
                            <td class="text-center fw-bold text-primary">{{ number_format($item->quantity, 2) }}</td>
                            <td class="text-center text-muted">{{ $item->unit }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center py-3 text-muted italic">
                                <i class="ti ti-alert-circle me-1"></i>No hay partidas registradas en esta requisición.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal-footer bg-light border-0 py-2">
    <small class="text-muted me-auto">ID de Control: #{{ $requisition->id }}</small>
    <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">
        <i class="ti ti-x me-1"></i>Cerrar Vista
    </button>
</div>
