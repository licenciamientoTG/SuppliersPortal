{{--
    Modal para solicitar nuevo producto desde requisiciones
    Incluir en: resources/views/requisitions/create.blade.php

    Uso: @include('requisitions._request_product_modal')
--}}

<div class="modal fade" id="requestProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="requestProductForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="ti ti-package-plus me-2"></i>Solicitar Nuevo Producto al Catálogo
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="ti ti-info-circle me-2"></i>
                        <strong>¿No encuentras el producto que necesitas?</strong><br>
                        Solicita el alta de un nuevo producto. El Administrador del Catálogo lo revisará y
                        completará la información contable antes de aprobarlo. Tu requisición quedará en estado
                        <strong>PAUSADA</strong> hasta que el producto sea aprobado.
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Compañía <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="modal_company_name" readonly>
                                <input type="hidden" id="modal_company_id" name="company_id">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Centro de Costo <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="modal_cost_center_name" readonly>
                                <input type="hidden" id="modal_cost_center_id" name="cost_center_id">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="modal_category_name" class="form-label">
                            Categoría asignada
                        </label>
                        <input type="text" class="form-control" id="modal_category_name"
                            value="Se tomará automáticamente de la categoría del centro de costo" readonly>
                        <div class="form-text">
                            La solicitud hereda la categoría configurada en el centro de costo de la requisición.
                        </div>
                    </div>

                    <input type="hidden" name="product_type" value="PRODUCTO">
                    <input type="hidden" name="unit_of_measure" value="PIEZA">

                    <div class="mb-3">
                        <label for="modal_subcategory" class="form-label">
                            Subcategoría <small class="text-muted">(opcional)</small>
                        </label>
                        <input type="text" class="form-control" id="modal_subcategory" name="subcategory"
                            maxlength="100" placeholder="Ej: Material de oficina, Servicios de limpieza...">
                    </div>

                    <div class="mb-3">
                        <label for="modal_technical_description" class="form-label">
                            Descripción Técnica <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" id="modal_technical_description" name="technical_description" rows="3" required
                            minlength="20" maxlength="5000"
                            placeholder="Describe el producto/servicio de forma detallada (mínimo 20 caracteres)..."></textarea>
                        <div class="form-text">Mínimo 20 caracteres. Sea lo más específico posible.</div>
                        <div class="invalid-feedback">La descripción debe tener al menos 20 caracteres.</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="modal_estimated_price" class="form-label">
                                    Precio Estimado <span class="text-danger">*</span>
                                </label>
                                <input type="number" class="form-control" id="modal_estimated_price"
                                    name="estimated_price" step="0.01" min="0" required placeholder="0.00">
                                <div class="form-text">Precio referencial aproximado.</div>
                                <div class="invalid-feedback">Ingrese un precio válido.</div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="modal_currency_code" class="form-label">Moneda</label>
                                <select class="form-select" id="modal_currency_code" name="currency_code">
                                    <option value="MXN" selected>MXN - Peso Mexicano</option>
                                    <option value="USD">USD - Dólar Estadounidense</option>
                                    <option value="EUR">EUR - Euro</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning">
                        <i class="ti ti-alert-triangle me-2"></i>
                        <strong>Nota importante:</strong> No necesitas completar la estructura contable
                        (Cuenta Mayor, Subcuenta, etc.). El Administrador del Catálogo la completará antes de aprobar.
                    </div>

                    <div id="modal_alert" class="alert alert-dismissible fade" role="alert" style="display:none;">
                        <span id="modal_alert_message"></span>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="submitProductRequest">
                        <i class="ti ti-send me-2"></i>Enviar Solicitud
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        $(function() {
            // Enviar solicitud de nuevo producto
            $('#requestProductForm').on('submit', function(e) {
                e.preventDefault();

                const form = $(this)[0];

                // Validación HTML5
                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return;
                }

                const $submitBtn = $('#submitProductRequest');
                const originalText = $submitBtn.html();

                // Deshabilitar botón
                $submitBtn.prop('disabled', true).html(
                    '<span class="spinner-border spinner-border-sm me-2"></span>Enviando...');
                $('#modal_alert').hide();

                // Enviar AJAX
                $.ajax({
                    url: '{{ route('products-services.store-from-requisition') }}',
                    method: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        if (response.success) {
                            // Mostrar éxito
                            $('#modal_alert')
                                .removeClass('alert-danger')
                                .addClass('alert-success show')
                                .find('#modal_alert_message')
                                .html('<i class="ti ti-check me-2"></i>' + response.message);
                            $('#modal_alert').show();

                            // Cerrar modal después de 2 segundos
                            setTimeout(function() {
                                $('#requestProductModal').modal('hide');

                                // Mostrar notificación
                                Swal.fire({
                                    icon: 'success',
                                    title: '¡Solicitud Enviada!',
                                    html: response.message +
                                        '<br><br><strong>Código asignado:</strong> ' +
                                        response.product.code +
                                        '<br><br><div class="alert alert-info mb-0"><i class="ti ti-info-circle me-2"></i>' +
                                        'Tu requisición quedará en estado <strong>PAUSADA</strong> hasta que el producto sea aprobado.</div>',
                                    confirmButtonText: 'Entendido'
                                });

                                // Resetear formulario
                                $('#requestProductForm')[0].reset();
                                $('#requestProductForm').removeClass('was-validated');
                            }, 2000);
                        }
                    },
                    error: function(xhr) {
                        let errorMsg = 'Ocurrió un error al enviar la solicitud.';

                        if (xhr.responseJSON && xhr.responseJSON.errors) {
                            const errors = Object.values(xhr.responseJSON.errors).flat();
                            errorMsg = errors.join('<br>');
                        } else if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        }

                        $('#modal_alert')
                            .removeClass('alert-success')
                            .addClass('alert-danger show')
                            .find('#modal_alert_message')
                            .html('<i class="ti ti-alert-circle me-2"></i>' + errorMsg);
                        $('#modal_alert').show();

                        $submitBtn.prop('disabled', false).html(originalText);
                    }
                });
            });

            // Resetear modal al cerrar
            $('#requestProductModal').on('hidden.bs.modal', function() {
                $('#requestProductForm')[0].reset();
                $('#requestProductForm').removeClass('was-validated');
                $('#modal_alert').hide();
                $('#submitProductRequest').prop('disabled', false).html(
                    '<i class="ti ti-send me-2"></i>Enviar Solicitud');
            });
        });
    </script>
@endpush
