@extends('layouts.zircos')

@section('title', 'Ubicaciones de Recepción')
@section('page.title', 'Ubicaciones de Recepción')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Ubicaciones de Recepción</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="ti ti-map-pin me-1"></i> Ubicaciones de Recepción</h5>
    </div>
    <div class="card-body">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="ti ti-check"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="table-responsive">
            <table id="receiving-locations-table" class="table-bordered table-hover w-100 table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th>Código</th>
                        <th>Empresa</th>
                        <th>Nombre</th>
                        <th>Tipo</th>
                        <th>Ciudad</th>
                        <th>Responsable</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Portal</th>
                        <th>Creado</th>
                        <th class="text-end" style="width: 160px;">Acciones</th>
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
$(document).ready(function() {
    $.ajaxSetup({
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
    });

    var table = $('#receiving-locations-table').DataTable({
        processing: true,
        dom: '<"top"Bf>rt<"bottom"lip>',
        pageLength: 100,
        buttons: [
            @can('create', App\Models\ReceivingLocation::class)
            {
                text: '<i class="ti ti-plus me-1"></i> Nueva Ubicación',
                className: 'btn btn-primary btn-sm',
                action: function() {
                    window.location.href = "{{ route('receiving-locations.create') }}";
                }
            },
            @endcan
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
                orientation: 'landscape',
                pageSize: 'A4'
            }
        ],
        ajax: {
            url: "{{ route('receiving-locations.data') }}",
            error: function(xhr) {
                console.error('Error en DataTable:', xhr.responseText);
            }
        },
        columns: [
            { data: 'id',           name: 'id',           width: '60px' },
            { data: 'code',         name: 'code' },
            { data: 'company',      name: 'company.name' },
            { data: 'name',         name: 'name' },
            { data: 'type',         name: 'type' },
            { data: 'city',         name: 'city' },
            { data: 'manager_name', name: 'manager_name' },
            { data: 'is_active',    name: 'is_active',      orderable: false, searchable: false, className: 'text-center' },
            { data: 'portal_blocked', name: 'portal_blocked', orderable: false, searchable: false, className: 'text-center' },
            { data: 'created_at',   name: 'created_at' },
            { data: 'action',       name: 'action',         orderable: false, searchable: false, className: 'text-end' }
        ],
        order: [[1, 'asc']],
        language: {
            url: "{{ asset('assets/vendor/datatables.net/es-MX.json') }}"
        },
        drawCallback: function() {
            $(".dataTables_paginate > .pagination").addClass("pagination-rounded");
        }
    });

    // Eliminar
    $(document).on('click', '.btn-delete', function() {
        var id   = $(this).data('id');
        var name = $(this).data('name');

        Swal.fire({
            title: '¿Estás seguro?',
            text: `Se eliminará: ${name}`,
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
                $.ajax({
                    url: `{{ url('receiving-locations') }}/${id}`,
                    type: 'POST',
                    data: { _method: 'DELETE' },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Eliminado',
                                text: response.message,
                                timer: 2000,
                                showConfirmButton: false
                            });
                            table.ajax.reload(null, false);
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message,
                                customClass: { confirmButton: 'btn btn-primary' },
                                buttonsStyling: false
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Error al eliminar la ubicación.',
                            customClass: { confirmButton: 'btn btn-primary' },
                            buttonsStyling: false
                        });
                    }
                });
            }
        });
    });

    // Bloquear portal
    $(document).on('click', '.btn-block-portal', function() {
        var id = $(this).data('id');

        Swal.fire({
            title: '¿Bloquear portal?',
            text: 'Los proveedores no podrán registrar nuevas entregas en esta ubicación.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '<i class="ti ti-lock me-1"></i>Sí, bloquear',
            cancelButtonText: '<i class="ti ti-x me-1"></i>Cancelar',
            customClass: {
                confirmButton: 'btn btn-danger',
                cancelButton: 'btn btn-secondary'
            },
            buttonsStyling: false,
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `{{ url('receiving-locations') }}/${id}/block-portal`,
                    type: 'POST',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: 'Bloqueado', text: response.message, timer: 2000, showConfirmButton: false });
                            table.ajax.reload(null, false);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: response.message, customClass: { confirmButton: 'btn btn-primary' }, buttonsStyling: false });
                        }
                    }
                });
            }
        });
    });

    // Desbloquear portal
    $(document).on('click', '.btn-unblock-portal', function() {
        var id = $(this).data('id');

        Swal.fire({
            title: '¿Desbloquear portal?',
            text: 'Los proveedores podrán registrar nuevas entregas en esta ubicación.',
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: '<i class="ti ti-lock-open me-1"></i>Sí, desbloquear',
            cancelButtonText: '<i class="ti ti-x me-1"></i>Cancelar',
            customClass: {
                confirmButton: 'btn btn-success',
                cancelButton: 'btn btn-secondary'
            },
            buttonsStyling: false,
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `{{ url('receiving-locations') }}/${id}/unblock-portal`,
                    type: 'POST',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: 'Desbloqueado', text: response.message, timer: 2000, showConfirmButton: false });
                            table.ajax.reload(null, false);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: response.message, customClass: { confirmButton: 'btn btn-primary' }, buttonsStyling: false });
                        }
                    }
                });
            }
        });
    });
});
</script>
@endpush
