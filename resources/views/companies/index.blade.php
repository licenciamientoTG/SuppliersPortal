@extends('layouts.zircos')

{{-- TÍTULO DE LA PÁGINA --}}
@section('title', 'Empresas')

@push('styles')
    <style>
        .modal-dialog-scrollable .modal-body {
            max-height: calc(100vh - 200px);
            overflow-y: auto;
        }
    </style>
@endpush

@section('page.title', 'Empresas')
@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
    <li class="breadcrumb-item">Administración</li>
    <li class="breadcrumb-item active">Empresas</li>
@endsection

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Listado de Empresas</h5>
        </div>
        <div class="card-body">
            <table class="table-bordered table-hover w-100 table" id="companiesTable">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>RFC</th>
                        <th>Régimen Fiscal</th>
                        <th>Domicilio Fiscal</th>
                        <th>Email</th>
                        <th>Activo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    {{-- Modal genérico --}}
    <div class="modal fade" id="companyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content" id="companyModalContent">
                {{-- Aquí se inyecta companies.partials.form vía AJAX --}}
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
$(function () {
    // CSRF para AJAX
    $.ajaxSetup({
        headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}
    });

    // DataTable
    const table = $('#companiesTable').DataTable({
        responsive: false,
        processing: true,
        dom: '<"top"Bf>rt<"bottom"lip>',
        pageLength: 50,
        buttons: [
            {
                text: '<i class="ti ti-building-plus me-1"></i> Nueva empresa',
                className: 'btn btn-primary btn-sm',
                attr: { id: 'btnCreateCompany', title: 'Crear nueva empresa' },
                action: function () {
                    openCompanyModal("{{ route('companies.create') }}");
                }
            },
            {
                extend: 'excel',
                text: '<i class="ti ti-file-spreadsheet me-1"></i> Excel',
                className: 'btn btn-success btn-sm'
            },
            {
                extend: 'copy',
                text: '<i class="ti ti-copy me-1"></i> Copiar',
                className: 'btn btn-warning btn-sm'
            },
            {
                extend: 'pdf',
                text: '<i class="ti ti-file-text me-1"></i> PDF',
                className: 'btn btn-info btn-sm',
                orientation: 'portrait',
                pageSize: 'A4'
            }
        ],
        ajax: {
            url: "{{ route('companies.datatable') }}",
            type: "GET",
            error: function(xhr) {
                console.error('Error en DataTable:', xhr.responseText);
            }
        },
        columns: [
            { data: 'id', name: 'id', width: '60px' },
            { data: 'code', name: 'code' },
            { data: 'name', name: 'name' },
            { data: 'rfc', name: 'rfc' },
            { data: 'tax_regime_label', name: 'tax_regime' },
            { data: 'fiscal_address', name: 'fiscal_address', orderable: false, className: 'small' },
            { data: 'email', name: 'email' },
            {
                data: 'is_active',
                name: 'is_active',
                orderable: false,
                searchable: false,
                className: 'text-center'
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
        drawCallback: function () {
            $(".dataTables_paginate > .pagination").addClass("pagination-rounded");
        }
    });

    // Abrir modal (crear)
    $(document).on('click', '#btnCreateCompany', function (e) {
        e.preventDefault();
        openCompanyModal("{{ route('companies.create') }}");
    });

    // Abrir modal (editar) — botón con clase .js-open-company-modal y data-url
    $(document).on('click', '.js-open-company-modal', function (e) {
        e.preventDefault();
        openCompanyModal($(this).data('url'));
    });

    function openCompanyModal(url) {
        const el = document.getElementById('companyModal');
        const modal = bootstrap.Modal.getOrCreateInstance(el);

        $('#companyModalContent').html('<div class="p-5 text-center">Cargando...</div>');
        modal.show();

        $.get(url)
            .done(function (html) { $('#companyModalContent').html(html); })
            .fail(function () {
                $('#companyModalContent').html('<div class="p-5 text-danger">No se pudo cargar el formulario.</div>');
            });
    }

    // Submit del form del modal (create/edit)
    $(document).on('submit', '#companyForm', function (e) {
        e.preventDefault();
        const $form  = $(this);
        const action = $form.attr('action');
        const method = ($form.attr('method') || 'POST').toUpperCase();
        const data   = $form.serialize();

        $form.find('button[type="submit"]').prop('disabled', true);
        $('#formErrors').addClass('d-none').empty();

        $.ajax({ url: action, type: method, data: data })
            .done(function () {
                const el = document.getElementById('companyModal');
                const modal = bootstrap.Modal.getInstance(el);
                if (modal) modal.hide();

                $('#companyModal').one('hidden.bs.modal', function () {
                    $('#companyModalContent').empty();
                    table.ajax.reload(null, false);
                    if (typeof toastOk === 'function') toastOk('Guardado correctamente');
                    cleanupModalBackdrops();
                });
            })
            .fail(function (xhr) {
                $form.find('button[type="submit"]').prop('disabled', false);
                if (xhr.status === 422) {
                    const res = xhr.responseJSON;
                    let html = '<div class="alert alert-danger"><ul class="mb-0">';
                    Object.values(res.errors || {}).forEach(arr => arr.forEach(msg => html += `<li>${msg}</li>`));
                    html += '</ul></div>';
                    $('#formErrors').html(html).removeClass('d-none');
                } else {
                    $('#formErrors').html('<div class="alert alert-danger">Error inesperado.</div>').removeClass('d-none');
                }
            });
    });

    // Eliminar
    $(document).on('click', '.js-delete-company', function (e) {
        e.preventDefault();
        const url = $(this).data('url');
        const name = $(this).data('name') || 'esta empresa';

        Swal.fire({
            title: `¿Eliminar ${name}?`,
            text: "Esta acción no se puede deshacer.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '<i class="ti ti-trash me-1"></i>Sí, eliminar',
            cancelButtonText: '<i class="ti ti-x me-1"></i>Cancelar',
            customClass: {
                confirmButton: 'btn btn-danger',
                cancelButton: 'btn btn-secondary'
            },
            buttonsStyling: false,
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({ url, type: 'POST', data: { _method: 'DELETE' } })
                    .done(function () {
                        table.ajax.reload(null, false);
                        Swal.fire({
                            icon: 'success',
                            title: 'Eliminado',
                            text: `${name} fue eliminado correctamente`,
                            timer: 2000,
                            showConfirmButton: false
                        });
                    })
                    .fail(function () {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'No se pudo eliminar la empresa.',
                            customClass: { confirmButton: 'btn btn-primary' },
                            buttonsStyling: false
                        });
                    });
            }
        });
    });

    // Toggle activo (si lo incluyes en el partial como switch/botón)
    $(document).on('click', '.js-toggle-company-active', function (e) {
        e.preventDefault();
        const url = $(this).data('url');
        $.ajax({ url, type: 'PATCH' })
            .done(function () { table.ajax.reload(null, false); })
            .fail(function () {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo cambiar el estado.',
                    customClass: { confirmButton: 'btn btn-primary' },
                    buttonsStyling: false
                });
            });
    });

    // Limpieza de backdrops al cerrar modal
    $('#companyModal').on('hidden.bs.modal', function () {
        cleanupModalBackdrops();
    });

    function cleanupModalBackdrops() {
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('padding-right');
        $('.modal-backdrop').remove();
    }
});

// Toast genérico (si ya lo tienes, omite esto)
window.toastOk = function (msg = 'Operación exitosa') {
    const Toast = Swal.mixin({
        toast: true, position: 'top-end', showConfirmButton: false,
        timer: 2000, timerProgressBar: true
    });
    Toast.fire({ icon: 'success', title: msg });
};
</script>
@endpush
