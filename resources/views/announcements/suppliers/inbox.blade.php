@extends('layouts.zircos-supplier')

{{-- TÍTULO DE LA PÁGINA --}}
@section('title', 'Announcements')

@push('styles')
<style>
    .announcement-preview {
        max-width: 320px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .thumb-cell { width: 80px; }
    .img-thumb {
        width: 80px;
        height: 60px;
        object-fit: cover;   /* recorte elegante */
        border-radius: .25rem;
    }
    .img-thumb { width: 64px; height: 64px; object-fit: cover; border-radius: .5rem; }
    .thumb-cell { width: 64px; }
</style>
@endpush

@section('page.title', 'Comunicados')
@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="javascript:void(0);">Portal Proveedores</a></li>
    <li class="breadcrumb-item active">Comunicados</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="ti ti-speakerphone me-2"></i>
            Comunicados
        </h5>
        <div class="d-flex gap-2">
            <span class="badge bg-primary" id="totalAnnouncements">0 Total</span>
        </div>
    </div>
    <div class="card-body">
        <table class="table table-sm table-striped align-middle w-100" id="announcementsTable">
            <thead>
                <tr>
                    <th style="width: 80px;">Portada</th> {{-- nueva --}}
                    <th>#</th>
                    <th>Título</th>
                    <th>Descripción</th>
                    <th>Prioridad</th>
                    <th>Estado</th>
                    <th>Publicado</th>
                    <th>Visible hasta</th>
                    <th>Vistas</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                {{-- DataTable via AJAX --}}
            </tbody>
        </table>
    </div>
</div>

{{-- Modal: Preview --}}
<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="ti ti-eye me-2"></i> Preview
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="previewContent">
                {{-- AJAX content --}}
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a id="previewPdfBtn" href="#" target="_blank" class="btn btn-outline-primary">
                    <i class="ti ti-file-text me-1"></i> Open PDF
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function () {
    $.ajaxSetup({ headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')} });

    const table = $('#announcementsTable').DataTable({
        responsive: false,
        processing: true,
        dom: '<"top"Bf>rt<"bottom"lip>',
        pageLength: 25,
        buttons: [
            { extend: 'excel', text: '<i class="ti ti-file-spreadsheet me-1"></i> Excel', className: 'btn btn-success btn-sm' },
            { extend: 'copy',  text: '<i class="ti ti-copy me-1"></i> Copiar', className: 'btn btn-warning btn-sm' },
            { extend: 'pdf',   text: '<i class="ti ti-file-text me-1"></i> PDF', className: 'btn btn-info btn-sm', orientation: 'landscape', pageSize: 'A4', title: 'Comunicados' }
        ],
        ajax: {
            url: "{{ route('supplier.announcements.datatable') }}",
        },
        columns: [
            {
                data: 'thumb_url',
                name: 'thumb',
                orderable: false,
                searchable: false,
                className: 'thumb-cell',
                render: function(data, type, row) {
                    if (data) {
                    return `<img src="${data}" alt="Portada" class="img-thumb" loading="lazy">`;
                    }
                    return '<span class="badge bg-light text-muted">—</span>';
                }
            },
            { data: 'id', name: 'id' },
            {
                data: 'title',
                name: 'title',
            },
            {
                data: 'description',
                name: 'description',
                render: function(data) {
                    const txt = data || '';
                    return `<div class="announcement-preview" title="${txt.replace(/"/g,'&quot;')}">${txt}</div>`;
                }
            },
            {
                data: 'priority',
                name: 'priority',
                render: function(data) {
                    const colors = {
                        'baja': 'success',   // verde
                        'normal': 'primary', // azul
                        'alta': 'warning',   // amarillo
                        'urgente': 'danger'  // rojo
                    };
                    const icons = {
                        'baja': 'info-circle',
                        'normal': 'circle',
                        'alta': 'alert-triangle',
                        'urgente': 'flame'
                    };
                    const key = (data || '').toLowerCase();
                    const color = colors[key] || 'secondary';
                    const icon  = icons[key] || 'help-circle';
                    return `<span class="badge bg-${color}">
                        <i class="ti ti-${icon} me-1"></i>${data.toUpperCase()}
                    </span>`;
                }
            },
            {
                data: 'estado',
                name: 'estado',
                render: function(data) {
                    const configs = {
                        'activo':   { color: 'success', icon: 'check-circle', text: 'Activo' },
                        'inactivo': { color: 'danger',  icon: 'x-circle',     text: 'Inactivo' }
                    };
                    const config = configs[data] || { color: 'secondary', icon: 'help-circle', text: data };
                    return `<span class="badge bg-${config.color}">
                        <i class="ti ti-${config.icon} me-1"></i>${config.text}
                    </span>`;
                }
            },
            {
                data: 'published_at',
                name: 'published_at',
                render: function(data) {
                    if (!data) return '<small class="text-muted">—</small>';
                    const dt = new Date(data);
                    return `<small>${dt.toLocaleDateString('es-MX',{day:'2-digit',month:'2-digit',year:'numeric'})}</small>`;
                }
            },
            {
                data: 'visible_until',
                name: 'visible_until',
                render: function(data) {
                    if (!data) return '<small class="text-muted">Sin caducidad</small>';
                    const dt = new Date(data);
                    return `<small>${dt.toLocaleDateString('es-MX',{day:'2-digit',month:'2-digit',year:'numeric'})}</small>`;
                }
            },
            {
                data: 'views',
                name: 'views',
                render: function(data) {
                    return `<span class="badge bg-light text-dark"><i class="ti ti-eye me-1"></i>${data || 0}</span>`;
                }
            },
            {
                data: 'actions',
                name: 'actions',
                orderable: false,
                searchable: false
            }
        ],
        language: { url: "{{ asset('assets/vendor/datatables.net/es-MX.json') }}" },
        order: [[0, 'desc']],
        drawCallback: function () {
            $(".dataTables_paginate > .pagination").addClass("pagination-rounded");
            const info = this.api().page.info();
            $('#totalAnnouncements').text(`${info.recordsTotal} Totales`);
        }
    });

    // Ocultar (dismiss)
    $(document).on('click', '.js-dismiss', function (e) {
        e.preventDefault();
        const url = $(this).data('url');

        Swal.fire({
            title: '¿Ocultar este comunicado?',
            text: 'Ya no volverás a ver este comunicado en tu bandeja.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, ocultar',
            cancelButtonText: 'Cancelar'
        }).then((r) => {
            if (!r.isConfirmed) return;

            $.post(url)
                .done(function () {
                    table.ajax.reload(null, false);
                    Swal.fire({ icon:'success', title:'Ocultado', timer: 1200, showConfirmButton:false });
                })
                .fail(function () {
                    Swal.fire({ icon:'error', title:'Error', text:'No se pudo ocultar el comunicado.' });
                });
        });
    });

    // Vista previa (si tienes un endpoint que entregue HTML del comunicado)
    $(document).on('click', '.js-preview', function (e) {
        e.preventDefault();
        const htmlUrl = $(this).data('url');
        const pdfUrl  = $(this).data('pdf');

        $('#previewContent').html('<div class="text-center p-4"><div class="spinner-border"></div><div class="mt-2">Cargando...</div></div>');
        const modal = new bootstrap.Modal(document.getElementById('previewModal'));
        modal.show();
        $('#previewPdfBtn').attr('href', pdfUrl || '#');

        $.get(htmlUrl)
            .done(function (html) { $('#previewContent').html(html); })
            .fail(function () { $('#previewContent').html('<div class="alert alert-danger">Error al cargar la vista previa.</div>'); });
    });
});
</script>

@endpush
