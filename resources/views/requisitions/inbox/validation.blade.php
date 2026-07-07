@extends('layouts.zircos')

@section('title', 'Bandeja de Validación')

@section('page.title', 'Bandeja de Validación')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('requisitions.index') }}">Requisiciones</a></li>
    <li class="breadcrumb-item active">Bandeja de Validación</li>
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="ti ti-stamp"></i> Pendientes de Validación</h5>
        </div>
        <div class="card-body table-responsive">
            <table id="requisitions-table" class="table-bordered table-hover w-100 table dataTable no-footer">
                <thead class="table-light">
                    <tr>
                        <th>Folio</th>
                        <th>Solicitante</th>
                        <th>Centro de Costos</th>
                        <th>Fecha Creación</th>
                        <th>Estatus</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- DEJA ESTO VACÍO. DataTables lo llenará. No seas terco. --}}
                </tbody>
            </table>
            {{ $rows->links() }}
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // === Rechazar con motivo (SweetAlert2) ===
            document.querySelectorAll('.js-reject-btn').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const reqId = btn.dataset.id;
                    const folio = btn.dataset.folio;

                    const {
                        value: reason
                    } = await Swal.fire({
                        title: `Rechazar requisición ${folio}`,
                        input: 'textarea',
                        inputLabel: 'Motivo del rechazo',
                        inputPlaceholder: 'Escribe el motivo...',
                        inputAttributes: {
                            'aria-label': 'Motivo del rechazo'
                        },
                        icon: 'warning',
                        showCancelButton: true,
                        cancelButtonText: 'Cancelar',
                        confirmButtonText: 'Rechazar',
                        confirmButtonColor: '#d33',
                        preConfirm: (text) => {
                            if (!text) {
                                Swal.showValidationMessage(
                                    'Debes escribir un motivo');
                            }
                            return text;
                        }
                    });

                    if (!reason) return;

                    // Enviar el rechazo vía fetch POST
                    try {
                        const response = await fetch(`/requisitions/${reqId}/reject`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json',
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                reason
                            })
                        });

                        const data = await response.json();

                        if (response.ok) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Requisición rechazada',
                                text: data.message ||
                                    'Se registró el rechazo correctamente.',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => location.reload());
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message ||
                                    'No se pudo rechazar la requisición.'
                            });
                        }
                    } catch (error) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de conexión',
                            text: 'No se pudo conectar con el servidor.'
                        });
                    }
                });
            });
        });

        $(document).ready(function () {
            // Inicialización de DataTables Server-Side
            let table = $('#requisitions-table').DataTable({
                processing: true, // Muestra el mensaje "Cargando..."
                serverSide: true, // ¡CRÍTICO! Activa la paginación en el servidor
                ajax: "{{ route('requisitions.approval_datatable') }}", // La ruta que devuelve JSON
                columns: [
                    { data: 'folio', name: 'folio' },
                    { data: 'requester_name', name: 'requester.name' },
                    { data: 'cost_center_name', name: 'costCenter.name' },
                    { data: 'created_at', name: 'created_at' },
                    { data: 'status', name: 'status' },
                    { 
                        data: 'action', 
                        name: 'action', 
                        orderable: false, 
                        searchable: false, 
                        className: 'text-end' 
                    },
                ],
                // Traducción al español (Porque el usuario final no habla inglés)
                language: {
                    url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
                },
                // Estilos de Bootstrap 5
                dom: 'Bfrtip',
                buttons: ['excel', 'pdf', 'print'] // Extras de Zircos
            });
        });

        // Funciones globales para los onlick del Dropdown
        function reviewRequisition(id) {
            // Tu lógica para abrir el modal (ya te la di antes)
            console.log("Abriendo revisión para ID: " + id);
            // Aquí llamas a tu AJAX para cargar el modal
        }

        function rejectRequisition(id) {
            // SweetAlert para confirmar rechazo
            Swal.fire({
                title: '¿Rechazar Requisición?',
                text: "Esta acción es irreversible.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Sí, rechazar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // AJAX para rechazar
                }
            });
        }

        // Variable global para almacenar el ID actual
        let currentRequisitionId = null;

        function reviewRequisition(id) {
            currentRequisitionId = id;
            
            // 1. Loading State
            Swal.fire({
                title: 'Cargando datos...',
                didOpen: () => Swal.showLoading(),
                backdrop: false,
                allowOutsideClick: false
            });

            // 2. AJAX a la ruta que definimos antes
            // route('requisitions.review-data', id)
            let url = `/requisitions/${id}/review-data`; // Asumiendo estructura REST
            
            $.get(url)
                .done(function(data) {
                    Swal.close();
                    
                    // Llenar datos de cabecera
                    $('#modal_req_folio').text(data.folio);
                    $('#modal_req_user').text(data.solicitante);
                    $('#modal_req_obs').text(data.observaciones);
                    $('#modal_req_id_hidden').val(id);

                    // Llenar tabla de partidas
                    let tbody = '';
                    data.partidas.forEach(p => {
                        tbody += `
                            <tr>
                                <td>
                                    <span class="d-block fw-medium text-dark">${p.producto}</span>
                                    <small class="text-muted">Obs: ${p.observaciones || '---'}</small>
                                </td>
                                <td class="text-center">${p.cantidad}</td>
                                <td class="text-center"><span class="badge bg-light text-dark border">${p.unidad}</span></td>
                                <td>${p.categoria_sugerida}</td>
                            </tr>
                        `;
                    });
                    $('#modal_req_items_body').html(tbody);

                    // Resetear formulario
                    $('#formValidateRequisition')[0].reset();
                    
                    // Abrir Modal
                    let myModal = new bootstrap.Modal(document.getElementById('modalReviewRequisition'));
                    myModal.show();
                })
                .fail(function() {
                    Swal.fire('Error', 'No se pudo cargar la información.', 'error');
                });
        }

        function submitValidation() {
            // Validar checkboxes manualmente porque no es un submit tradicional
            let specs = $('#check_specs_clear').is(':checked');
            let time = $('#check_time_feasible').is(':checked');

            if (!specs || !time) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Validación Incompleta',
                    text: 'Debes certificar que las especificaciones y tiempos son viables antes de continuar.',
                    confirmButtonText: 'Entendido'
                });
                return;
            }

            // Enviar validación al servidor
            $.post(`/requisitions/${currentRequisitionId}/validate-technical`, {
                _token: '{{ csrf_token() }}',
                specs_clear: specs,
                time_feasible: time,
                category_id: $('#select_category_final').val() // Si decidiste implementar el cambio de categoría
            })
            .done(function(res) {
                $('#modalReviewRequisition').modal('hide');
                Swal.fire('Validado', res.message, 'success');
                $('#requisitions-table').DataTable().ajax.reload(); // Recargar tabla
            })
            .fail(function(err) {
                Swal.fire('Error', 'Ocurrió un error al procesar la validación.', 'error');
            });
        }

        function triggerRejectFlow() {
            // Cerramos el modal de revisión para abrir el SweetAlert de rechazo
            $('#modalReviewRequisition').modal('hide');
            rejectRequisition(currentRequisitionId); // Llamamos a tu función global de rechazo
        }
    </script>
@endpush
