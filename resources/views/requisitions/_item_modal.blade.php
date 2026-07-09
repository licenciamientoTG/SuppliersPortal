{{-- Modal para agregar/editar ítem --}}
<div class="modal fade" id="itemModal" tabindex="-1" aria-labelledby="itemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="itemModalLabel">
                    <i class="ti ti-package me-2"></i>
                    <span id="itemModalTitle">Agregar Ítem</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="itemForm">
                    <input type="hidden" id="item_index" value="">

                    {{-- Producto del catálogo --}}
                    <div class="mb-3">
                        <label for="modal_product_id" class="form-label">
                            Producto/Servicio <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" id="modal_product_id" required>
                            <option value="">Buscar producto del catálogo...</option>
                        </select>
                        <small class="text-muted">
                            ¿No encuentras el producto?
                            <a href="#" id="requestNewProductLink">Solicitar nuevo producto</a>
                        </small>
                    </div>

                    {{-- Descripción (readonly) --}}
                    <div class="mb-3">
                        <label for="modal_description" class="form-label">Descripción Técnica</label>
                        <textarea class="form-control bg-light" id="modal_description" rows="2" readonly></textarea>
                    </div>

                    <div class="row g-3">
                        {{-- Cantidad --}}
                        <div class="col-md-4">
                            <label for="modal_quantity" class="form-label">
                                Cantidad <span class="text-danger">*</span>
                            </label>
                            <input type="number" class="form-control" id="modal_quantity"
                                min="1" step="1" value="1" required>
                        </div>

                        {{-- Unidad --}}
                        <div class="col-md-4">
                            <label for="modal_unit" class="form-label">Unidad</label>
                            <select class="form-select" id="modal_unit">
                                @foreach ($unitOptions as $group => $units)
                                <optgroup label="{{ $group }}">
                                    @foreach ($units as $code => $label)
                                    <option value="{{ $code }}">{{ $label }}</option>
                                    @endforeach
                                </optgroup>
                                @endforeach
                            </select>
                        </div>

                        {{-- Precio Unitario (readonly) --}}
                        <div class="col-md-4">
                            <label for="modal_price" class="form-label">Precio Unitario</label>
                            <input type="number" class="form-control bg-light" id="modal_price"
                                step="0.01" readonly>
                        </div>

                        {{-- IVA --}}
                        <div class="col-md-6">
                            <label for="modal_tax_id" class="form-label">IVA</label>
                            <select class="form-select" id="modal_tax_id">
                                <option value="" data-rate="0">Sin IVA</option>
                                @foreach ($taxes as $tax)
                                <option value="{{ $tax->id }}" data-rate="{{ $tax->rate_percent }}">
                                    {{ $tax->name }} ({{ $tax->rate_percent }}%)
                                </option>
                                @endforeach
                            </select>
                            <input type="hidden" id="modal_tax_rate" value="0">
                        </div>

                        {{-- Categoría de Gasto --}}
                        <div class="col-md-6">
                            <label for="modal_expense_category" class="form-label">
                                Cuenta <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="modal_expense_category" required>
                                <option value="">Seleccionar cuenta...</option>
                            </select>
                            <small class="text-muted">Del presupuesto del centro de costo</small>
                        </div>

                        {{-- Mes de Aplicación --}}
                        <div class="col-md-6">
                            <label for="modal_month" class="form-label">
                                Mes de Aplicación <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="modal_month" required>
                                <option value="">Seleccionar mes...</option>
                                <option value="1">Enero</option>
                                <option value="2">Febrero</option>
                                <option value="3">Marzo</option>
                                <option value="4">Abril</option>
                                <option value="5">Mayo</option>
                                <option value="6">Junio</option>
                                <option value="7">Julio</option>
                                <option value="8">Agosto</option>
                                <option value="9">Septiembre</option>
                                <option value="10">Octubre</option>
                                <option value="11">Noviembre</option>
                                <option value="12">Diciembre</option>
                            </select>
                        </div>

                        {{-- Presupuesto Disponible --}}
                        <div class="col-md-6">
                            <label class="form-label">Presupuesto Disponible</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="ti ti-wallet"></i>
                                </span>
                                <input type="text" class="form-control bg-light" id="modal_budget_available"
                                    value="—" readonly>
                            </div>
                            <small class="text-muted" id="modal_budget_status"></small>
                        </div>
                    </div>

                    <hr class="my-3">

                    {{-- Totales calculados --}}
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Subtotal</label>
                            <input type="text" class="form-control bg-light fw-bold" id="modal_subtotal" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Total</label>
                            <input type="text" class="form-control bg-success bg-opacity-10 fw-bold text-success"
                                id="modal_total" readonly>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="ti ti-x me-1"></i>Cancelar
                </button>
                <button type="button" class="btn btn-primary" id="btnSaveItem">
                    <i class="ti ti-check me-1"></i>Aceptar
                </button>
            </div>
        </div>
    </div>
</div>