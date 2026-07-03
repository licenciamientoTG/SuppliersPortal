<div class="modal-header bg-primary text-white border-0">
    <h5 class="modal-title">
        <i class="ti ti-file-text me-2"></i>Detalle de RFQ: {{ $rfq->folio }}
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body p-4">
    <div class="row">
        {{-- 1. Encabezado Táctico --}}
        <div class="col-12 mb-4">
            <div class="d-flex align-items-center">
                <span class="badge {{ $rfq->isGroupRfq() ? 'bg-soft-primary text-primary' : 'bg-soft-info text-info' }} fs-12 px-3 py-2">
                    <i class="ti {{ $rfq->isGroupRfq() ? 'ti-package' : 'ti-box' }} me-1"></i>
                    {{ $rfq->isGroupRfq() ? 'Cotización por Grupo' : 'Cotización Individual' }}
                </span>
                <span class="ms-auto text-muted small">
                    <i class="ti ti-calendar me-1"></i>Vence: {{ $rfq->response_deadline ? $rfq->response_deadline->format('d/m/Y H:i') : 'S/N' }}
                </span>
            </div>
            <h4 class="mt-3 mb-1 fw-bold text-dark">
                {{ $rfq->quotationGroup->name ?? ($rfq->requisitionItem->description ?? 'Sin descripción') }}
            </h4>
        </div>

        {{-- 2. Tabla de Partidas --}}
        <div class="col-12 mb-4">
            <h6 class="text-uppercase fs-11 fw-bold text-muted mb-2" style="letter-spacing: 1px;">
                <i class="ti ti-list-check me-1"></i>Partidas a Cotizar
            </h6>
            <div class="table-responsive rounded border shadow-sm">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr class="fs-12">
                            <th class="border-0 py-2">Descripción del Ítem</th>
                            <th class="border-0 py-2 text-center">Cantidad</th>
                            <th class="border-0 py-2 text-center">U.M.</th>
                        </tr>
                    </thead>
                    <tbody class="fs-13">
                        @forelse($rfq->getItemsToQuote() ?? [] as $item)
                        <tr>
                            <td class="py-2">
                                <span class="fw-medium text-dark">{{ $item->productService->short_name ?? $item->productService->name ?? $item->description }}</span>
                                @if(!empty($item->description) && ($item->productService->short_name ?? $item->productService->name ?? null) !== $item->description)
                                    <br>
                                    <small class="text-muted">{{ $item->description }}</small>
                                @endif
                                @if(!empty($item->notes))
                                    <br>
                                    <small class="text-info">
                                        <i class="ti ti-message-2 me-1"></i>Observación del solicitante: {{ $item->notes }}
                                    </small>
                                @endif
                            </td>
                            <td class="text-center fw-bold py-2">{{ number_format($item->quantity, 2) }}</td>
                            <td class="text-center text-muted py-2">{{ $item->unit }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center py-4 text-muted">No hay partidas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- 3. ESTADO DE PROVEEDORES (LO NUEVO) --}}
        <div class="col-12">
            <h6 class="text-uppercase fs-11 fw-bold text-muted mb-2" style="letter-spacing: 1px;">
                <i class="ti ti-users me-1"></i>Situación de Proveedores Invitados
            </h6>
            <div class="list-group shadow-sm">
                @forelse($rfq->suppliers as $supplier)
                    @php
                        // Accedemos a los datos de la tabla pivote rfq_suppliers
                        $hasResponded = !is_null($supplier->pivot->responded_at);
                    @endphp
                    <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar-xs me-3">
                                <span class="avatar-title rounded-circle {{ $hasResponded ? 'bg-soft-success text-success' : 'bg-soft-warning text-warning' }}">
                                    <i class="ti {{ $hasResponded ? 'ti-check' : 'ti-clock' }} fs-16"></i>
                                </span>
                            </div>
                            <div>
                                <h6 class="mb-0 fs-14">{{ $supplier->company_name }}</h6>
                                <small class="text-muted">Invitado: {{ $supplier->pivot->invited_at ? \Illuminate\Support\Carbon::parse($supplier->pivot->invited_at)->format('d/m/Y') : 'N/A' }}</small>
                            </div>
                        </div>
                        
                        @if($hasResponded)
                            <div class="text-end">
                                <span class="badge bg-success px-2 py-1">Contestó</span>
                                <small class="d-block text-muted mt-1" style="font-size: 10px;">
                                    {{ \Illuminate\Support\Carbon::parse($supplier->pivot->responded_at)->diffForHumans() }}
                                </small>
                            </div>
                        @else
                            <span class="badge bg-warning text-dark px-2 py-1">Pendiente</span>
                        @endif
                    </div>
                @empty
                    <div class="list-group-item text-center py-3 text-muted">
                        <i class="ti ti-user-x me-1"></i> No se han asignado proveedores a esta RFQ.
                    </div>
                @endforelse
            </div>
            {{-- Insertar después de la lista de proveedores --}}
            <div class="row mt-4">
                {{-- Documentos Adjuntos por Compras --}}
                <div class="col-md-6">
                    <h6 class="text-uppercase fs-11 fw-bold text-muted mb-2">
                        <i class="ti ti-paperclip me-1"></i>Documentación Adjunta
                    </h6>
                    @if($rfq->attachments && $rfq->attachments->count() > 0)
                        <div class="d-flex flex-column gap-2">
                            @foreach($rfq->attachments as $doc)
                                <a href="{{ storage_path($doc->path) }}" class="btn btn-xs btn-outline-secondary text-start">
                                    <i class="ti ti-file-download me-1"></i> {{ $doc->name }}
                                </a>
                            @endforeach
                        </div>
                    @else
                        <p class="small text-muted italic">Sin documentos adjuntos.</p>
                    @endif
                </div>

                {{-- Log de Actividad (Versión Blindada) --}}
                <div class="col-md-6">
                    <h6 class="text-uppercase fs-11 fw-bold text-muted mb-2">
                        <i class="ti ti-history me-1"></i>Últimos Movimientos
                    </h6>
                    <div class="bg-light p-2 rounded border border-dashed" style="max-height: 100px; overflow-y: auto;">
                        {{-- Usamos optional() y verificamos que no sea null antes del take --}}
                        @php
                            $recentActivities = optional($rfq->activities)->take(3) ?? collect([]);
                        @endphp

                        @forelse($recentActivities as $activity)
                            <div class="mb-1" style="font-size: 10px;">
                                <span class="fw-bold text-primary">{{ $activity->causer->name ?? 'Sistema' }}:</span> 
                                {{ $activity->description }} 
                                <span class="text-muted">({{ $activity->created_at->diffForHumans() }})</span>
                            </div>
                        @empty
                            <p class="mb-0 small text-muted italic">Sin registros de actividad para esta solicitud.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- 4. Notas del Comprador --}}
        @if($rfq->message)
        <div class="col-12 mt-4">
            <div class="alert bg-light border-0 mb-0">
                <h6 class="alert-heading fs-12 fw-bold text-uppercase text-muted">
                    <i class="ti ti-message-2 me-1"></i>Mensaje para Proveedores:
                </h6>
                <p class="mb-0 small text-dark">{{ $rfq->message }}</p>
            </div>
        </div>
        @endif
    </div>
</div>

<div class="modal-footer bg-light border-0">
    {{-- Reemplaza tu modal-footer actual --}}
    <div class="modal-footer bg-light border-0">
        <div class="w-100 d-flex justify-content-between align-items-center">
            <div class="d-flex gap-2">
                @if($rfq->status === 'RECEIVED')
                    <a href="{{ route('rfq.comparison.index', $rfq) }}" class="btn btn-success btn-sm px-3">
                        <i class="ti ti-scale me-1"></i>Evaluar Comparativa
                    </a>
                @endif
                <a href="{{ route('rfq.show', $rfq) }}" class="btn btn-outline-primary btn-sm px-3">
                    <i class="ti ti-settings me-1"></i>Gestionar RFQ
                </a>
            </div>
            <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">Cerrar</button>
        </div>
    </div>
</div>
