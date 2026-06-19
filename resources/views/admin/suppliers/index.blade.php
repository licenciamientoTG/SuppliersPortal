@extends('layouts.zircos')

@section('title', 'Proveedores')
@section('page.title', 'Proveedores')
@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
    <li class="breadcrumb-item">Administración</li>
    <li class="breadcrumb-item active">Proveedores</li>
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Catálogo de proveedores</h5>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-hover w-100" id="suppliersTable">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Empresa</th>
                        <th>RFC</th>
                        <th>Contacto</th>
                        <th>Teléfono</th>
                        <th>Correo</th>
                        <th>Banco</th>
                        <th>Monedas</th>
                        <th>Último acceso</th>
                        <th>Aprobación</th>
                        <th>Documentos</th>
                        <th>Activo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="supplierModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content" id="supplierModalContent"></div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
$(function () {
    $.ajaxSetup({
        headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}
    });

    const table = $('#suppliersTable').DataTable({
        processing: true,
        serverSide: true,
        responsive: false,
        ajax: "{{ route('admin.suppliers.datatable') }}",
        columns: [
            { data: 'id' },
            { data: 'company_name' },
            { data: 'rfc' },
            { data: 'contact_person' },
            { data: 'contact_phone' },
            { data: 'email' },
            { data: 'bank_name' },
            { data: 'accepted_currencies', orderable: false, searchable: false, className: 'text-center' },
            { data: 'last_login' },
            { data: 'approval_status' },
            { data: 'document_status' },
            { data: 'is_active', render: function(data) {
                return data
                    ? '<span class="badge bg-success">Sí</span>'
                    : '<span class="badge bg-danger">No</span>';
            }},
            { data: 'actions', orderable: false, searchable: false },
        ],
        language: {
            url: "{{ asset('assets/vendor/datatables.net/es-MX.json') }}"
        }
    });

    $(document).on('click', '.js-open-supplier-modal', function (e) {
        e.preventDefault();
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('supplierModal'));
        $('#supplierModalContent').html('<div class="p-5 text-center">Cargando...</div>');
        modal.show();

        $.get($(this).data('url'))
            .done(html => $('#supplierModalContent').html(html))
            .fail(() => $('#supplierModalContent').html('<div class="p-5 text-danger">No se pudo cargar el formulario.</div>'));
    });

    $(document).on('submit', '#supplierForm', function (e) {
        e.preventDefault();
        const $form = $(this);
        $.ajax({
            url: $form.attr('action'),
            type: 'POST',
            data: $form.serialize()
        }).done(function (res) {
            bootstrap.Modal.getInstance(document.getElementById('supplierModal'))?.hide();
            table.ajax.reload(null, false);
            toastOk(res.message || 'Guardado correctamente.');
        }).fail(function (xhr) {
            let html = '<div class="alert alert-danger"><ul class="mb-0">';
            Object.values(xhr.responseJSON?.errors || {}).forEach(arr => arr.forEach(msg => html += `<li>${msg}</li>`));
            html += '</ul></div>';
            $('#formErrors').html(html).removeClass('d-none');
        });
    });

    $(document).on('click', '.js-toggle-supplier', function () {
        $.ajax({ url: $(this).data('url'), type: 'PATCH' })
            .done(res => { table.ajax.reload(null, false); toastOk(res.message); });
    });

    $(document).on('click', '.js-approve-supplier', function () {
        $.post($(this).data('url'))
            .done(res => { table.ajax.reload(null, false); toastOk(res.message); });
    });

    $(document).on('click', '.js-reject-supplier', function () {
        const url = $(this).data('url');
        Swal.fire({
            title: 'Rechazar proveedor',
            input: 'textarea',
            inputLabel: 'Motivo',
            inputAttributes: { minlength: 5 },
            showCancelButton: true,
            confirmButtonText: 'Rechazar',
            cancelButtonText: 'Cancelar',
            customClass: { confirmButton: 'btn btn-warning', cancelButton: 'btn btn-secondary' },
            buttonsStyling: false,
            inputValidator: (value) => !value || value.length < 5 ? 'Captura un motivo de al menos 5 caracteres.' : undefined
        }).then((result) => {
            if (!result.isConfirmed) return;
            $.post(url, { approval_notes: result.value })
                .done(res => { table.ajax.reload(null, false); toastOk(res.message); });
        });
    });

    $(document).on('click', '.js-delete-supplier', function () {
        const url = $(this).data('url');
        const name = $(this).data('name');
        Swal.fire({
            title: `¿Eliminar ${name}?`,
            text: 'Esta acción no se puede deshacer.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar',
            customClass: { confirmButton: 'btn btn-danger', cancelButton: 'btn btn-secondary' },
            buttonsStyling: false,
        }).then((result) => {
            if (!result.isConfirmed) return;
            $.ajax({ url, type: 'POST', data: { _method: 'DELETE' } })
                .done(res => { table.ajax.reload(null, false); toastOk(res.message); });
        });
    });
});

window.toastOk = window.toastOk || function (msg = 'Operación exitosa') {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 2000,
        timerProgressBar: true
    });
    Toast.fire({ icon: 'success', title: msg });
};
</script>
@endpush
