@extends('layouts.zircos')

@section('title', 'Selección de Proveedores')

@section('page.title', 'Selección de Proveedores')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('rfq.index') }}">RFQs</a></li>
    <li class="breadcrumb-item active">Selección de Proveedores</li>
@endsection

@section('content')
<div class="container-fluid">
    {{-- HEADER --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1">
                        <i class="ti ti-building-store me-2"></i>
                        Selección de Proveedores
                    </h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{ route('requisitions.index') }}">Requisiciones</a>
                            </li>
                            <li class="breadcrumb-item">
                                <a href="{{ route('requisitions.quotation-planner.show', $requisition) }}">
                                    Planificador
                                </a>
                            </li>
                            <li class="breadcrumb-item active">Proveedores</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="{{ route('requisitions.quotation-planner.show', $requisition) }}" 
                       class="btn btn-outline-secondary">
                        <i class="ti ti-arrow-left"></i> Volver
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- INFORMACIÓN DE LA REQUISICIÓN --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-primary">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h6 class="text-muted mb-1">Folio</h6>
                            <p class="mb-0 fw-bold">{{ $requisition->folio }}</p>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted mb-1">Grupos Creados</h6>
                            <p class="mb-0">
                                <span class="badge bg-primary fs-6">{{ $groups->count() }}</span>
                            </p>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted mb-1">Total de Partidas</h6>
                            <p class="mb-0">
                                <span class="badge bg-info fs-6">
                                    {{ $groups->sum(fn($g) => $g->items->count()) }}
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- INSTRUCCIONES --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-info d-flex align-items-start" role="alert">
                <i class="ti ti-info-circle fs-4 me-3 mt-1"></i>
                <div>
                    <h5 class="alert-heading mb-2">¿Cómo seleccionar proveedores?</h5>
                    <p class="mb-2">
                        Para cada grupo de cotización, selecciona <strong>al menos 1 proveedor</strong> 
                        (se recomienda 3 o más para obtener mejores cotizaciones).
                    </p>
                    <p class="mb-0">
                        <i class="ti ti-bulb-filled fs-5 text-warning"></i> <strong>Tip:</strong> Mantén presionado <b>`Ctrl`</b> para seleccionar múltiples proveedores a la vez.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <form action="{{ route('rfq.create', $requisition) }}" method="POST" id="supplierSelectionForm">
        @csrf

        {{-- GRUPOS Y SELECCIÓN DE PROVEEDORES --}}
        @foreach($groups as $index => $group)
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="ti ti-box fs-5 text-primary"></i> {{ $group->name }}
                                <span class="badge bg-secondary ms-2">
                                    {{ $group->items->count() }} partida(s)
                                </span>
                            </h5>
                            <button type="button" 
                                    class="btn btn-sm btn-outline-primary" 
                                    data-bs-toggle="collapse" 
                                    data-bs-target="#groupItems{{ $index }}">
                                <i class="ti ti-eye"></i> Ver Partidas
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <input type="hidden" 
                               name="groups[{{ $index }}][group_id]" 
                               value="{{ $group->id }}">

                        {{-- Partidas del grupo (COLAPSABLES) --}}
                        <div class="collapse mb-3" id="groupItems{{ $index }}">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="5%">#</th>
                                            <th width="45%">Descripción</th>
                                            <th width="15%">Cantidad</th>
                                            <th width="15%">Unidad</th>
                                            <th width="20%">Cuenta</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($group->items as $itemIndex => $item)
                                        <tr>
                                            <td>{{ $itemIndex + 1 }}</td>
                                            <td>
                                                <strong>{{ $item->description }}</strong>
                                                @if($item->notes)
                                                    <br>
                                                    <small class="text-muted">
                                                        <i class="ti ti-note"></i> {{ $item->notes }}
                                                    </small>
                                                @endif
                                            </td>
                                            <td>{{ number_format($item->quantity, 2) }}</td>
                                            <td>{{ $item->unit }}</td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    {{ $item->expenseCategory->name ?? 'N/A' }}
                                                </span>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        @if(!$loop->first)
                            <hr>
                        @endif

                        {{-- Selección de proveedores --}}
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">
                                    <i class="ti ti-building-store"></i>
                                    Proveedores a Invitar <span class="text-danger">*</span>
                                </label>
                                <select class="form-select supplier-select @error("groups.{$index}.supplier_ids") is-invalid @enderror" 
                                        name="groups[{{ $index }}][supplier_ids][]" 
                                        multiple
                                        size="5"
                                        required>
                                    @forelse($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}"
                                                {{ in_array($supplier->id, old("groups.{$index}.supplier_ids", [])) ? 'selected' : '' }}>
                                            {{ $supplier->company_name }}
                                        </option>
                                    @empty
                                        <option disabled>No hay proveedores aprobados</option>
                                    @endforelse
                                </select>
                                <small class="text-muted d-block mt-1">
                                    <i class="ti ti-info-circle"></i>
                                    Mantén presionado <b>`Ctrl`</b> para seleccionar múltiples
                                </small>
                                @error("groups.{$index}.supplier_ids")
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-bold">
                                    <i class="ti ti-calendar"></i>
                                    Fecha Límite para Cotización <span class="text-danger">*</span>
                                </label>
                                <input type="date" 
                                       class="form-control @error("groups.{$index}.response_deadline") is-invalid @enderror" 
                                       name="groups[{{ $index }}][response_deadline]"
                                       min="{{ now()->addDay()->format('Y-m-d') }}"
                                       value="{{ old("groups.{$index}.response_deadline", now()->addDays(7)->format('Y-m-d')) }}"
                                       required>
                                <small class="text-muted d-block mt-1">
                                    <i class="ti ti-info-circle"></i>
                                    Plazo para recibir cotizaciones
                                </small>
                                @error("groups.{$index}.response_deadline")
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-bold">
                                    <i class="ti ti-users"></i>
                                    Proveedores Seleccionados
                                </label>
                                <div class="form-control bg-light supplier-count-display" 
                                     style="height: 38px; display: flex; align-items: center; justify-content: center;">
                                    <span class="badge bg-secondary supplier-count-{{ $index }}">0</span>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-12">
                                <label class="form-label fw-bold">
                                    <i class="ti ti-notes"></i>
                                    Notas / Instrucciones Especiales
                                </label>
                                <textarea class="form-control" 
                                          name="groups[{{ $index }}][notes]" 
                                          rows="2"
                                          placeholder="Ej: Solicitar muestras, incluir garantía, plazo de entrega especial, etc.">{{ old("groups.{$index}.notes", $group->notes) }}</textarea>
                                <small class="text-muted">
                                    Opcional - Estas notas se enviarán a los proveedores junto con la solicitud
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endforeach

        {{-- BOTONES DE ACCIÓN --}}
        <div class="row">
            <div class="col-12">
                <div class="card border-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">
                                    <i class="ti ti-send me-2"></i>
                                    Resumen
                                </h6>
                                <p class="mb-0 text-muted">
                                    Se crearán <strong class="text-primary">{{ $groups->count() }}</strong> 
                                    solicitud(es) de cotización para 
                                    <strong class="text-primary">{{ $groups->sum(fn($g) => $g->items->count()) }}</strong> 
                                    partida(s)
                                </p>
                            </div>
                            <div>
                                <a href="{{ route('requisitions.quotation-planner.show', $requisition) }}" 
                                   class="btn btn-outline-secondary me-2">
                                    <i class="ti ti-arrow-left"></i> Volver al Planificador
                                </a>
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="ti ti-send"></i> Crear Solicitudes de Cotización
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<style>
.supplier-select {
    font-size: 0.95rem;
}

.supplier-select option {
    padding: 0.5rem;
}

.supplier-count-display {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
}

kbd {
    background-color: #e9ecef;
    border: 1px solid #adb5bd;
    border-radius: 3px;
    padding: 2px 6px;
    font-family: monospace;
    font-size: 0.85em;
}
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    console.log('🎨 Inicializando selección de proveedores');
    
    // Inicializar Select2 en todos los selectores
    $('.supplier-select').each(function(index) {
        $(this).select2({
            theme: 'bootstrap-5',
            placeholder: 'Selecciona uno o más proveedores...',
            allowClear: false,
            width: '100%',
            language: {
                noResults: function() {
                    return "No se encontraron proveedores";
                }
            }
        }).on('change', function() {
            updateSupplierCount(index);
        });
        
        // Actualizar contador inicial
        updateSupplierCount(index);
    });
    
    /**
     * Actualizar contador de proveedores seleccionados
     */
    function updateSupplierCount(index) {
        const count = $(`.supplier-select:eq(${index})`).val()?.length || 0;
        const badge = $(`.supplier-count-${index}`);
        
        badge.text(count);
        
        if (count === 0) {
            badge.removeClass('bg-primary bg-success').addClass('bg-secondary');
        } else if (count >= 3) {
            badge.removeClass('bg-primary bg-secondary').addClass('bg-success');
        } else {
            badge.removeClass('bg-secondary bg-success').addClass('bg-primary');
        }
    }
    
    /**
     * Validación antes de enviar
     */
    $('#supplierSelectionForm').on('submit', function(e) {
        let hasErrors = false;
        let emptyGroups = [];
        
        $('.supplier-select').each(function(index) {
            const count = $(this).val()?.length || 0;
            if (count === 0) {
                hasErrors = true;
                const groupName = $(this).closest('.card').find('h5').text().trim();
                emptyGroups.push(groupName);
            }
        });
        
        if (hasErrors) {
            e.preventDefault();
            
            Swal.fire({
                icon: 'warning',
                title: 'Proveedores faltantes',
                html: `
                    <p>Los siguientes grupos no tienen proveedores seleccionados:</p>
                    <ul class="text-start">
                        ${emptyGroups.map(g => `<li>${g}</li>`).join('')}
                    </ul>
                    <p class="mb-0">Debes seleccionar al menos 1 proveedor por grupo.</p>
                `,
                confirmButtonText: 'Entendido'
            });
            
            return false;
        }
        
        // Confirmación final
        e.preventDefault();
        
        const totalGroups = {{ $groups->count() }};
        let totalSuppliers = 0;
        $('.supplier-select').each(function() {
            totalSuppliers += ($(this).val()?.length || 0);
        });
        
        Swal.fire({
            icon: 'question',
            title: '¿Crear solicitudes de cotización?',
            html: `
                <p>Se crearán <strong>${totalGroups}</strong> solicitud(es) de cotización.</p>
                <p>Se invitarán <strong>${totalSuppliers}</strong> proveedor(es) en total.</p>
                <p class="mb-0 text-muted small">Una vez creadas, se enviarán notificaciones a los proveedores.</p>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="ti ti-send me-2"></i>Sí, crear solicitudes',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#0d6efd'
        }).then((result) => {
            if (result.isConfirmed) {
                // Mostrar loading
                Swal.fire({
                    title: 'Creando solicitudes...',
                    html: 'Por favor espera un momento',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Enviar formulario
                this.submit();
            }
        });
    });
});
</script>
@endpush