@extends('layouts.zircos')

@section('title', 'Empleados')
@section('page.title', 'Empleados')
@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Empleados</li>
@endsection

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="ti ti-id-badge me-1"></i> Catálogo de Empleados</h5>
            <small class="text-muted">Sincronizado diariamente desde TRESS</small>
        </div>

        <div class="card-body">
            <div class="row mb-3 g-2">
                <div class="col-md-3">
                    <label class="form-label mb-1" style="font-size: 12px;">Filtrar por Empresa</label>
                    <select id="filterCompany" class="form-select form-select-sm">
                        <option value="">Todas las empresas</option>
                        @foreach($companies as $company)
                            <option value="{{ $company }}">{{ $company }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1" style="font-size: 12px;">Estado</label>
                    <select id="filterStatus" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="SI" selected>Activos</option>
                        <option value="NO">Inactivos</option>
                    </select>
                </div>
            </div>

            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="ti ti-check"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="table-responsive">
                <table id="employeesTable" class="table table-bordered table-hover w-100">
                    <thead class="table-light">
                        <tr>
                            <th style="width:52px;"></th>
                            <th style="width: 60px;">#</th>
                            <th>No. Empleado</th>
                            <th>Nombre Completo</th>
                            <th>Empresa</th>
                            <th>Departamento</th>
                            <th>Puesto</th>
                            <th>Líder</th>
                            <th class="text-center" style="width: 80px;">Activo</th>
                            <th class="text-end" style="width: 110px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Modal: Promover a usuario staff --}}
    <div class="modal fade" id="promoteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" id="promoteModalContent">
                <div class="modal-body text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal: Preview de foto grande --}}
    <div class="modal fade" id="photoPreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-transparent border-0">
                <div class="modal-body text-center p-2">
                    <img id="photoPreviewImg" src="" class="img-fluid rounded" style="max-height:80vh;" alt="Foto">
                </div>
            </div>
        </div>
    </div>

    {{-- Modal: Carga de foto del empleado --}}
    <div class="modal fade" id="photoUploadModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content" id="photoUploadModalContent">
                <div class="modal-body text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            const employeesTable = $('#employeesTable').DataTable({
                responsive: false,
                processing: true,
                serverSide: true,
                dom: '<"top"Bf>rt<"bottom"lip>',
                pageLength: 50,
                order: [[2, 'asc']],
                buttons: [
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
                    url: "{{ route('employees.datatable') }}",
                    type: "GET",
                    data: function (d) {
                        d.is_active = $('#filterStatus').val();
                        d.company = $('#filterCompany').val();
                    },
                    error: function (xhr) {
                        console.error('Error en DataTable:', xhr.responseText);
                    }
                },
                columns: [
                    {
                        data: 'photo',
                        name: 'photo',
                        orderable: false,
                        searchable: false,
                        className: 'text-center'
                    },
                    { data: 'id',              name: 'id',              width: '60px' },
                    { data: 'employee_number', name: 'employee_number' },
                    { data: 'full_name',       name: 'full_name',       searchable: true, orderable: true },
                    { data: 'company',         name: 'company' },
                    { data: 'department',      name: 'department' },
                    { data: 'job_title',       name: 'job_title' },
                    { data: 'leader',          name: 'leader' },
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
                    $('[data-bs-toggle="tooltip"]').tooltip();
                }
            });

            $('#filterStatus, #filterCompany').on('change', function() {
                employeesTable.ajax.reload();
            });

            // ── Photo Preview ─────────────────────────────────────────────────
            const photoPreviewModal = new bootstrap.Modal(document.getElementById('photoPreviewModal'));

            $(document).on('click', '.js-photo-preview', function () {
                document.getElementById('photoPreviewImg').src = $(this).data('url');
                photoPreviewModal.show();
            });

            // ── Photo Upload ──────────────────────────────────────────────────
            const photoUploadModal = new bootstrap.Modal(document.getElementById('photoUploadModal'));

            $(document).on('click', '.js-photo-btn', function () {
                const employeeId = $(this).data('id');

                $('#photoUploadModalContent').html(
                    '<div class="modal-body text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>'
                );
                photoUploadModal.show();

                fetch(`/employees/${employeeId}/photo-form`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(res => {
                    if (!res.ok) throw new Error(`HTTP ${res.status}`);
                    return res.text();
                })
                .then(html => {
                    $('#photoUploadModalContent').html(html);
                })
                .catch(() => {
                    photoUploadModal.hide();
                    Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo cargar el formulario.' });
                });
            });

            $(document).on('submit', '#photoForm', function (e) {
                e.preventDefault();

                const form      = this;
                const url       = form.action;
                const formData  = new FormData(form);
                const submitBtn = document.getElementById('photoSubmitBtn');

                form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
                const errEl = document.getElementById('err-photo');
                if (errEl) errEl.textContent = '';

                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';

                fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: formData
                })
                .then(res => res.json().then(data => ({ status: res.status, data })))
                .then(({ status, data }) => {
                    if (status === 200 && data.success) {
                        photoUploadModal.hide();
                        employeesTable.ajax.reload(null, false);
                        Swal.fire({ icon: 'success', title: '¡Listo!', text: 'Fotografía actualizada.', timer: 2500, showConfirmButton: false });
                    } else if (status === 422 && data.errors) {
                        const input = form.querySelector('[name="photo"]');
                        if (input) input.classList.add('is-invalid');
                        if (errEl) errEl.textContent = Object.values(data.errors).flat()[0];
                    }
                })
                .catch(() => {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo guardar la fotografía.' });
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="ti ti-device-floppy me-1"></i>Guardar foto';
                });
            });

            // ── Promote Employee to User ──────────────────────────────────────
            const promoteModal = new bootstrap.Modal(document.getElementById('promoteModal'));

            $(document).on('click', '.js-promote-btn', function () {
                const employeeId = $(this).data('id');
                const url = `/employees/${employeeId}/promote-form`;

                $('#promoteModalContent').html(
                    '<div class="modal-body text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>'
                );
                promoteModal.show();

                fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(res => {
                    if (res.status === 409) {
                        promoteModal.hide();
                        return res.json().then(data => {
                            Swal.fire({ icon: 'warning', title: 'Atención', text: data.error });
                        });
                    }
                    return res.text().then(html => {
                        $('#promoteModalContent').html(html);
                    });
                })
                .catch(() => {
                    promoteModal.hide();
                    Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo cargar el formulario.' });
                });
            });

            $(document).on('submit', '#promoteForm', function (e) {
                e.preventDefault();

                const form    = this;
                const url     = form.action;
                const formData = new FormData(form);
                const submitBtn = document.getElementById('promoteSubmitBtn');

                // Limpiar errores previos
                form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
                form.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');
                document.getElementById('promoteGeneralError')?.classList.add('d-none');

                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Creando...';

                fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: formData
                })
                .then(res => res.json().then(data => ({ status: res.status, data })))
                .then(({ status, data }) => {
                    if (status === 200 && data.success) {
                        promoteModal.hide();
                        employeesTable.ajax.reload(null, false);
                        Swal.fire({ icon: 'success', title: '¡Listo!', text: data.message, timer: 3000, showConfirmButton: false });
                    } else if (status === 422 && data.errors) {
                        Object.entries(data.errors).forEach(([field, messages]) => {
                            const input = form.querySelector(`[name="${field}"]`);
                            const errEl = document.getElementById(`err-${field}`);
                            if (input) input.classList.add('is-invalid');
                            if (errEl) errEl.textContent = messages[0];
                        });
                    } else {
                        const errEl = document.getElementById('promoteGeneralError');
                        if (errEl) { errEl.textContent = data.error || 'Ocurrió un error.'; errEl.classList.remove('d-none'); }
                    }
                })
                .catch(() => {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo completar la operación.' });
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="ti ti-user-plus me-1"></i>Crear usuario';
                });
            });
        });
    </script>
@endpush
