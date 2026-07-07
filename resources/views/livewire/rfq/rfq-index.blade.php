<div>
    <div class="container-fluid">
        {{-- Header --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold mb-0">
                <i class="ti ti-file-dollar me-2"></i>Gestión de Cotizaciones
            </h4>
        </div>

        {{-- Filtros --}}
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    {{-- Búsqueda --}}
                    <div class="col-md-6">
                        <label class="form-label">Buscar</label>
                        <input type="text" 
                               class="form-control" 
                               wire:model.live.debounce.300ms="search"
                               placeholder="Buscar por folio, solicitante o centro de costos...">
                    </div>

                    {{-- Filtro de Status --}}
                    <div class="col-md-6">
                        <label class="form-label">Filtrar por Estado</label>
                        <select class="form-select" wire:model.live="statusFilter">
                            <option value="">Todos los estados</option>
                            @foreach($allowedStatuses as $status)
                                <option value="{{ $status->value }}">
                                    {{ $status->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tabla de Requisiciones --}}
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Folio</th>
                                <th>Solicitante</th>
                                <th>Centro de Costos</th>
                                <th>Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($requisitions as $requisition)
                                <tr>
                                    <td>
                                        <strong>{{ $requisition->folio }}</strong>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="avatar avatar-xs rounded-circle bg-primary-subtle text-primary me-2">
                                                <i class="ti ti-user"></i>
                                            </span>
                                            <span>{{ $requisition->requester?->name ?? 'Sin solicitante' }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        @php
                                            $primaryCostCenter = $requisition->primaryCostCenter();
                                        @endphp
                                        <small class="text-muted">{{ $primaryCostCenter?->code ?? 'N/A' }}</small><br>
                                        {{ \Illuminate\Support\Str::limit($primaryCostCenter?->name ?? 'Sin centro de costo', 30) }}
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $requisition->status->badgeClass() }}">
                                            {{ $requisition->status->label() }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <a href="{{ route('rfq.wizard.steps', $requisition->id) }}" 
                                           class="btn btn-primary btn-sm">
                                            <i class="ti ti-file-invoice me-1"></i> Cotizar
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="ti ti-inbox fs-3 mb-2 d-block"></i>
                                        No se encontraron requisiciones para cotizar
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Paginación --}}
                <div class="mt-3">
                    {{ $requisitions->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .avatar-xs {
        width: 2rem;
        height: 2rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.875rem;
    }
</style>
@endpush
