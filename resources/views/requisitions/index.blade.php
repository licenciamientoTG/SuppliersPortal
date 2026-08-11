@extends('layouts.zircos')

@section('title', 'Requisiciones')
@section('page.title', 'Requisiciones')
@section('page.breadcrumbs')
<li class="breadcrumb-item"><a href="{{ route('requisitions.index') }}">Requisiciones</a></li>
<li class="breadcrumb-item active">Listado</li>
@endsection

@push('styles')
<style>
    .filter-input {
        width: 100%;
        padding: 0.375rem 0.5rem;
        font-size: 0.875rem;
        border: 1px solid #dee2e6;
        border-radius: 0.25rem;
    }

    .filter-input:focus {
        border-color: #86b7fe;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }

    .filter-row th {
        padding: 3px !important;
    }

    .requisition-title-cell {
        max-width: 320px;
    }

    .requisition-title-preview {
        display: block;
        overflow: hidden;
        color: #34465a;
        font-weight: 600;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
</style>
@endpush

@section('content')

{{-- GLOSARIO DE ESTADOS --}}
<div class="row mb-3">
    <div class="col-12">
        <div class="alert alert-light border mb-0 py-2" role="alert">
            <div class="d-flex align-items-center">
                <i class="ti ti-info-circle me-2 text-primary"></i>
                <strong class="me-3">Estados:</strong>
                <div class="d-flex flex-wrap gap-2 small">
                    <span>
                        <span class="badge bg-secondary" data-bs-toggle="tooltip" title="No enviado">Borrador</span>
                    </span>
                    <span class="text-muted">|</span>
                    <span>
                        <span class="badge bg-warning" data-bs-toggle="tooltip" title="Por validar (Compras)">Pendiente</span>
                    </span>
                    <span class="text-muted">|</span>
                    <span>
                        <span class="badge bg-info" data-bs-toggle="tooltip" title="Esperando catálogo (Compras)">Pausada</span>
                    </span>
                    <span class="text-muted">|</span>
                    <span>
                        <span class="badge bg-success" data-bs-toggle="tooltip" title="Validada por Compras">Validada</span>
                    </span>
                    <span class="text-muted">|</span>
                    <span>
                        <span class="badge bg-danger" data-bs-toggle="tooltip" title="No aprobada">Rechazada</span>
                    </span>
                    <span class="text-muted">|</span>
                    <span>
                        <span class="badge bg-primary" data-bs-toggle="tooltip" title="Buscando proveedores">En Cotización</span>
                    </span>
                    <span class="text-muted">|</span>
                    <span>
                        <span class="badge bg-primary" data-bs-toggle="tooltip" title="Cotizaciones recibidas">Cotizada</span>
                    </span>
                    <span class="text-muted">|</span>
                    <span>
                        <span class="badge bg-warning" data-bs-toggle="tooltip" title="Requiere más presupuesto">Ajuste Presupuestal</span>
                    </span>
                    <span class="text-muted">|</span>
                    <span>
                        <span class="badge bg-success" data-bs-toggle="tooltip" title="Proceso finalizado">Completada</span>
                    </span>
                    <span class="text-muted">|</span>
                    <span>
                        <span class="badge bg-dark" data-bs-toggle="tooltip" title="Sin efecto">Cancelada</span>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="mb-3">
            <button id="clearFilters" class="btn btn-sm btn-outline-secondary">
                <i class="ti ti-filter-off me-1"></i> Limpiar filtros
            </button>
        </div>
        @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        <div class="table-responsive">
            <table id="requisitionsTable" class="table-bordered table-hover w-100 table">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Folio</th>
                        <th>Título</th>
                        <th>Centro de costo</th>
                        <th>Solicitante</th>
                        <th class="text-center">Partidas</th>
                        <th>Estatus</th>
                        <th>Fecha creación</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <thead>
                    <tr class="filter-row">
                        <th></th> <!-- 0: ID -->
                        <th><input type="text" class="filter-input" placeholder="Buscar folio" data-column="1"></th>
                        <th><input type="text" class="filter-input" placeholder="Buscar título" data-column="2"></th>
                        <th><input type="text" class="filter-input" placeholder="Buscar centro" data-column="3"></th>
                        <th><input type="text" class="filter-input" placeholder="Buscar solicitante" data-column="4"></th>
                        <th></th> <!-- 5: Contador de partidas -->
                        <th>
                            <select class="filter-input" data-column="6">
                                <option value="">Todos</option>
                                <option value="DRAFT">Borrador</option>
                                <option value="PENDING">Pendiente de Validación</option>
                                <option value="PAUSED">Pausada (Esperando Catálogo)</option>
                                <option value="APPROVED">Aprobada</option>
                                <option value="REJECTED">Rechazada</option>
                                <option value="IN_QUOTATION">En Cotización</option>
                                <option value="QUOTED">Cotizada</option>
                                <option value="PENDING_BUDGET_ADJUSTMENT">Pendiente Ajuste Presupuestal</option>
                                <option value="COMPLETED">Completada</option>
                                <option value="CANCELLED">Cancelada</option>
                            </select>
                        </th>
                        <th><input type="date" class="filter-input" data-column="7"></th>
                        <th></th> <!-- 8: Acciones -->
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(function() {

        // =====================================================
        // ELIMINAR REQUISICIÓN (solo BORRADOR)
        // =====================================================
        $(document).on('click', '.js-delete-btn', function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const $form = $btn.closest('form');
            const folio = $btn.data('folio');
            
            Swal.fire({
                title: `¿Eliminar requisición ${folio}?`,
                html: `
                    <div class="text-start">
                        <p class="mb-3"><strong>Esta acción:</strong></p>
                        <ul class="text-muted small">
                            <li class="mb-2">
                                <i class="ti ti-trash text-danger"></i> 
                                Eliminará <strong>permanentemente</strong> la requisición
                            </li>
                            <li class="mb-2">
                                <i class="ti ti-file-x text-danger"></i> 
                                Eliminará <strong>todas las partidas</strong> asociadas
                            </li>
                            <li class="mb-2">
                                <i class="ti ti-alert-triangle text-warning"></i> 
                                Esta acción <strong>NO se puede deshacer</strong>
                            </li>
                        </ul>
                        <div class="alert alert-danger mt-3 mb-0">
                            <i class="ti ti-alert-circle me-2"></i>
                            <small><strong>¡Advertencia!</strong> Solo elimina requisiciones que ya no necesites.</small>
                        </div>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="ti ti-trash me-1"></i>Sí, Eliminar',
                cancelButtonText: '<i class="ti ti-x me-1"></i>Cancelar',
                width: '600px',
                customClass: {
                    confirmButton: 'btn btn-danger',
                    cancelButton: 'btn btn-secondary'
                },
                buttonsStyling: false,
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    $form.submit();
                }
            });
        });

        // =====================================================
        // CANCELAR REQUISICIÓN (PENDING, PAUSED, etc.)
        // =====================================================
        $(document).on('click', '.js-cancel-btn', function(e) {
            e.preventDefault();
            
            const folio = $(this).data('folio');
            const url = $(this).data('url');
            
            Swal.fire({
                title: `¿Cancelar requisición ${folio}?`,
                html: `
                    <div class="text-start">
                        <p class="mb-3"><strong>Al cancelar esta requisición:</strong></p>
                        <ul class="text-muted small">
                            <li class="mb-2">
                                <i class="ti ti-ban text-warning"></i> 
                                Cambiará el estado a <span class="badge bg-dark">CANCELADA</span>
                            </li>
                            <li class="mb-2">
                                <i class="ti ti-lock text-info"></i> 
                                Ya <strong>no se podrá modificar</strong> ni reactivar
                            </li>
                            <li class="mb-2">
                                <i class="ti ti-file-check text-success"></i> 
                                El registro permanecerá en el sistema para auditoría
                            </li>
                        </ul>
                        
                        <div class="mt-3">
                            <label for="cancellation_reason" class="form-label fw-bold">
                                Motivo de cancelación <span class="text-danger">*</span>
                            </label>
                            <textarea 
                                id="cancellation_reason" 
                                class="form-control" 
                                rows="3" 
                                placeholder="Explica el motivo de la cancelación..."
                                maxlength="1000"
                                required></textarea>
                            <small class="form-text text-muted">Máximo 1000 caracteres</small>
                        </div>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="ti ti-ban me-1"></i>Sí, Cancelar Requisición',
                cancelButtonText: '<i class="ti ti-x me-1"></i>No Cancelar',
                width: '650px',
                customClass: {
                    confirmButton: 'btn btn-warning',
                    cancelButton: 'btn btn-secondary'
                },
                buttonsStyling: false,
                reverseButtons: true,
                preConfirm: () => {
                    const reason = document.getElementById('cancellation_reason').value;
                    
                    if (!reason || reason.trim().length === 0) {
                        Swal.showValidationMessage('Debes proporcionar un motivo de cancelación');
                        return false;
                    }
                    
                    if (reason.length < 10) {
                        Swal.showValidationMessage('El motivo debe tener al menos 10 caracteres');
                        return false;
                    }
                    
                    return reason;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Crear formulario dinámico para enviar
                    const form = $('<form>', {
                        method: 'POST',
                        action: url
                    });
                    
                    form.append($('<input>', {
                        type: 'hidden',
                        name: '_token',
                        value: '{{ csrf_token() }}'
                    }));
                    
                    form.append($('<input>', {
                        type: 'hidden',
                        name: '_method',
                        value: 'PUT'
                    }));
                    
                    form.append($('<input>', {
                        type: 'hidden',
                        name: 'cancellation_reason',
                        value: result.value
                    }));
                    
                    $('body').append(form);
                    form.submit();
                }
            });
        });

        const table = $('#requisitionsTable').DataTable({
            responsive: false,
            processing: true,
            dom: '<"top"Bf>rt<"bottom"lip>',
            pageLength: 50,
            order: [
                [0, 'desc']
            ], // Ordenar por ID descendente (más recientes primero)
            buttons: [{
                    text: '<i class="ti ti-file-dollar me-1"></i> Nueva Requisición',
                    className: 'btn btn-primary btn-sm',
                    action: function() {
                        window.location.href = "{{ route('requisitions.create-livewire') }}";
                    }
                },
                {
                    extend: 'excel',
                    text: '<i class="ti ti-file-spreadsheet me-1"></i> Excel',
                    className: 'btn btn-success btn-sm',
                    exportOptions: {
                        columns: ':not(:last-child)' // Excluir columna de acciones
                    }
                },
                {
                    extend: 'copy',
                    text: '<i class="ti ti-copy me-1"></i> Copiar',
                    className: 'btn btn-warning btn-sm',
                    exportOptions: {
                        columns: ':not(:last-child)'
                    }
                },
                {
                    extend: 'pdf',
                    text: '<i class="ti ti-file-text me-1"></i> PDF',
                    className: 'btn btn-info btn-sm',
                    orientation: 'landscape',
                    pageSize: 'A4',
                    exportOptions: {
                        columns: ':not(:last-child)'
                    }
                }
            ],
            ajax: {
                url: "{{ route('requisitions.datatable') }}",
                type: "GET",
                error: function(xhr) {
                    console.error('Error en DataTable:', xhr.responseText);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error al cargar los datos de requisiciones.',
                        customClass: {
                            confirmButton: 'btn btn-primary'
                        },
                        buttonsStyling: false
                    });
                }
            },
            columns: [
                {
                    data: 'id',
                    name: 'id',
                    width: '60px'
                },
                {
                    data: 'folio',
                    name: 'folio'
                },
                {
                    data: 'title',
                    name: 'description',
                    className: 'requisition-title-cell',
                    render: function(data, type) {
                        const title = data || 'Sin título';

                        if (type !== 'display') {
                            return title;
                        }

                        const escapeHtml = (value) => $('<div>').text(value).html();
                        const preview = title.length > 72 ? `${title.slice(0, 72).trimEnd()}…` : title;

                        return `<span class="requisition-title-preview" title="${escapeHtml(title)}">${escapeHtml(preview)}</span>`;
                    }
                },
                {
                    data: 'cost_center',
                    name: 'costCenter.name'
                },
                {
                    data: 'requester',
                    name: 'requester.name'
                },
                {
                    data: 'items_count',
                    name: 'items_count',
                    className: 'text-center',
                    render: function(data) {
                        const count = parseInt(data) || 0;
                        const badgeClass = count > 0 ? 'bg-primary' : 'bg-secondary';
                        return `<span class="badge ${badgeClass}">${count}</span>`;
                    }
                },
                {
                    data: 'status',
                    name: 'status',
                    orderable: false,
                    render: function(data, type, row) {
                        if (type === 'filter') {
                            // Extraer el valor del data-status
                            const match = data.match(/data-status="([^"]+)"/);
                            return match ? match[1] : '';
                        }
                        return data; // HTML del badge
                    }
                },
                {
                    data: 'created_at',
                    name: 'created_at',
                    // ✅ Ya viene formateada del backend, solo mostrarla
                    render: function(data, type, row) {
                        if (!data || data === '—') return '—';
                        
                        // Para filtrado: convertir DD/MM/YYYY a YYYY-MM-DD
                        if (type === 'filter' || type === 'sort') {
                            const parts = data.split('/');
                            if (parts.length === 3) {
                                return `${parts[2]}-${parts[1]}-${parts[0]}`; // YYYY-MM-DD
                            }
                        }
                        
                        // Para display: mostrar tal cual viene del backend
                        return data;
                    }
                },
                {
                    data: 'actions',
                    name: 'actions',
                    orderable: false,
                    searchable: false,
                    className: 'text-end'
                }
            ],
            language: {
                url: "{{ asset('assets/vendor/datatables.net/es-MX.json') }}"
            },
            drawCallback: function() {
                $(".dataTables_paginate > .pagination").addClass("pagination-rounded");
            },
            // Agregar esta configuración
            initComplete: function() {
                // Aplicar la búsqueda cuando se escriba en los inputs
                this.api().columns().every(function() {
                    const column = this;
                    const columnIndex = column.index();

                    // No aplicar a columnas sin búsqueda (ID, items_count, acciones)
                    if ([0, 5, 8].includes(columnIndex)) return;

                    // Para el select de estatus (columna 6)
                    if (columnIndex === 6) {
                        $('select[data-column="' + columnIndex + '"]').on('change', function() {
                            column.search(this.value).draw();
                        });
                    }
                    // Para el resto de los inputs de texto y fecha
                    else {
                        $('input[data-column="' + columnIndex + '"]').on('keyup change clear', function() {
                            if (column.search() !== this.value) {
                                column.search(this.value).draw();
                            }
                        });
                    }
                });
            }
        });

        // Limpiar filtros
        $('#clearFilters').on('click', function() {
            $('.filter-input').val('').trigger('change');
            table.search('').columns().search('').draw();
        });
    });
</script>
@endpush
