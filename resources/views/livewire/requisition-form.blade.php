<div>
    {{-- Mensajes de sesión --}}
    @if (session()->has('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="ti ti-check me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    @if (session()->has('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="ti ti-alert-circle me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <input type="hidden" id="requisition_livewire_id" value="{{ $this->getId() }}">

    {{-- El guardado se realiza mediante Livewire; evitamos formularios anidados. --}}
    <div class="requisition-workflow">

        <div class="requisition-page-intro">
            <img src="{{ asset('images/logos/Logo.png') }}" alt="TotalGas" class="requisition-logo-spinner">
            <div>
                <h4 class="requisition-page-title mb-1">Crea una requisición</h4>
                <p class="mb-0">Primero define el destino de la compra; después agrega los productos o servicios.</p>
            </div>
        </div>

        {{-- Información General --}}
        <section class="requisition-surface requisition-general-card">
            <div class="requisition-surface-heading">
                <span class="requisition-heading-icon"><i class="ti ti-map-pin"></i></span>
                <div>
                    <h5 class="mb-1">¿Para dónde es esta compra?</h5>
                    <p class="mb-0">Selecciona la compañía y la ubicación de entrega.</p>
                </div>
                @if (!empty($folio))
                    <span class="requisition-folio">{{ $folio }}</span>
                @endif
            </div>
            <div class="requisition-general-body">
                <div class="row g-4">
                    {{-- Compañía --}}
                    <div class="col-md-6" wire:ignore>
                        <label for="company_id" class="form-label">Compañía <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="ti ti-building"></i>
                            </span>
                            <select id="company_id"
                                    class="form-select @error('company_id') is-invalid @enderror"
                                    data-url-receiving-locations="{{ route('requisitions.receiving-locations.by-company', ['company' => '__CID__']) }}"
                                    required>
                                <option value="">Elige una compañía</option>
                                @foreach ($companies as $c)
                                    <option value="{{ $c->id }}" @selected((string) $company_id === (string) $c->id)>{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @error('company_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Ubicación de recepción --}}
                    <div class="col-md-6" wire:ignore>
                        <label for="receiving_location_id" class="form-label">Ubicación de recepción <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="ti ti-map-pin"></i>
                            </span>
                            <select id="receiving_location_id"
                                    class="form-select @error('receiving_location_id') is-invalid @enderror"
                                    required>
                                <option value="">Elige una ubicación</option>
                                @foreach ($receivingLocations as $loc)
                                    <option value="{{ $loc->id }}" @selected((string) $receiving_location_id === (string) $loc->id)>
                                        {{ $loc->name }}{{ $loc->city ? ' — ' . $loc->city : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @error('receiving_location_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Descripción con contador de caracteres --}}
                    <div class="col-12">
                        <label for="description" class="form-label d-flex justify-content-between align-items-center">
                            <span>Título de la requisición</span>
                            <span class="requisition-character-count {{ $descriptionRemainingChars < 50 ? 'is-low' : '' }}">
                                {{ $descriptionRemainingChars }} caracteres disponibles
                            </span>
                        </label>
                        <input type="text"
                               id="description"
                               class="form-control @error('description') is-invalid @enderror"
                               value="{{ $description }}"
                               placeholder="Ej. Equipo de seguridad para la planta Bajío"
                               maxlength="{{ $descriptionMaxLength }}">
                        @error('description')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </section>

        {{-- TABLA DE PARTIDAS --}}
        <section class="requisition-surface requisition-items-card mt-4">
            <div class="requisition-surface-heading">
                <span class="requisition-heading-icon requisition-heading-icon-primary"><i class="ti ti-shopping-cart-plus"></i></span>
                <div>
                    <h5 class="mb-1">Agrega las partidas</h5>
                    <p class="mb-0">Incluye cada producto o servicio que necesites comprar.</p>
                </div>
                <span id="itemsCountBadge"
                      class="requisition-items-badge {{ count($items) > 0 ? '' : 'd-none' }}">
                    {{ count($items) }} partida(s)
                </span>
            </div>
            <div class="requisition-items-body">
                <div id="itemFormPanel"
                     class="border rounded p-3 mb-3 requisition-item-form-panel {{ $company_id && $receiving_location_id ? '' : 'is-context-locked' }}"
                     wire:ignore
                     wire:key="requisition-item-form-panel">
                    <div id="itemFormLockMessage"
                         class="requisition-item-form-lock {{ $company_id && $receiving_location_id ? 'd-none' : '' }}"
                         role="status">
                        <i class="ti ti-lock"></i>
                        Selecciona la compañía y la ubicación de recepción para agregar partidas.
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h6 class="mb-1" id="itemModalTitle">Agregar partida</h6>
                            <p class="text-muted mb-0 small">Completa la información de la compra que deseas solicitar.</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnAddItem">
                            <i class="ti ti-refresh me-1"></i>Limpiar
                        </button>
                    </div>

                    <form id="itemForm" class="needs-validation" novalidate>
                        <input type="hidden" id="item_index">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="modal_cost_center_id" class="form-label fw-semibold">
                                    Centro de Costo <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="ti ti-chart-pie"></i></span>
                                    <select id="modal_cost_center_id" class="form-select" required disabled>
                                        <option value="">Seleccionar centro de costo...</option>
                                    </select>
                                </div>
                                <div class="form-text" id="modal_cost_center_help"></div>
                            </div>

                            <div class="col-md-6">
                                <label for="modal_product_id" class="form-label fw-semibold">
                                    Producto del catálogo <span class="text-danger">*</span>
                                </label>
                                <select id="modal_product_id" class="form-select" required>
                                    <option value="">Buscar producto del catálogo...</option>
                                </select>
                            </div>

                            <textarea id="modal_description" class="d-none"></textarea>

                            <div class="col-md-6">
                                <label for="modal_quantity" class="form-label fw-semibold">
                                    Cantidad <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="ti ti-numbers"></i></span>
                                    <input type="number" id="modal_quantity" class="form-control"
                                        min="0.001" step="0.001" value="1" required>
                                </div>
                                <div class="form-text">Mínimo: 0.001</div>
                            </div>

                            <div class="col-md-6">
                                <label for="modal_unit" class="form-label fw-semibold">Unidad de Medida</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="ti ti-ruler-measure"></i></span>
                                    <input type="text" id="modal_unit" class="form-control bg-light" readonly>
                                </div>
                            </div>

                            <div class="d-none">
                                <select id="modal_expense_category" class="form-select" required>
                                    <option value="">Seleccione primero un centro de costo...</option>
                                </select>
                                <select id="modal_budget_cedula" class="form-select" required disabled>
                                    <option value="">Selecciona primero una cuenta...</option>
                                </select>
                                <div id="modal_budget_cedula_help"></div>
                            </div>
                        </div>

                        <div class="row g-3 mt-2">
                            <div class="col-md-12">
                                <label for="modal_notes" class="form-label fw-semibold">Observaciones</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="ti ti-notes"></i></span>
                                    <textarea id="modal_notes" class="form-control" rows="2"
                                        placeholder="Especificaciones adicionales, requisitos especiales, información de contacto, etc."></textarea>
                                </div>
                            </div>
                            @if (! $isEditMode)
                                <div class="col-12">
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" role="switch" id="modal_attach_document">
                                        <label class="form-check-label fw-semibold" for="modal_attach_document">Adjuntar documento a esta partida</label>
                                    </div>
                                    <label id="modal_attachment_dropzone" class="requisition-attachment-dropzone d-none" data-requisition-dropzone>
                                        <input type="file" id="modal_attachment" class="requisition-attachment-input" data-requisition-file-input accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg">
                                        <i class="ti ti-cloud-upload"></i>
                                        <strong>Arrastra un archivo aquí o haz clic para seleccionarlo</strong>
                                        <span>PDF, Word, Excel o imagen · Máximo 10 MB</span>
                                    </label>
                                    <div id="modal_attachment_name" class="requisition-attachment-selected d-none"></div>
                                </div>
                            @endif
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <button type="button" class="btn btn-primary" id="btnSaveItem">
                                <i class="ti ti-check me-1"></i>Guardar Partida
                            </button>
                        </div>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th width="40">#</th>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Unidad</th>
                                <th>Centro de Costo</th>
                                <th>Notas</th>
                                <th width="100">Acciones</th>
                            </tr>
                        </thead>
                        {{--
                            Las partidas se pintan desde JavaScript tras las llamadas renderless
                            addItem/updateItem/removeItem. Al cargar un archivo Livewire sí realiza
                            un ciclo de morph para actualizar su carga temporal; no debe intentar
                            reconciliar este DOM administrado por JavaScript.
                        --}}
                        <tbody id="requisitionItemsBody" wire:ignore>
                            @forelse($items as $index => $item)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td><strong>{{ $item['product_name'] }}</strong></td>
                                    <td>{{ $item['quantity'] }}</td>
                                    <td>{{ $item['unit'] }}</td>
                                    <td>
                                        <span class="badge bg-secondary" title="{{ $item['cost_center_name'] ?? '' }}">
                                            {{ Str::limit($item['cost_center_name'] ?? '—', 25) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if(!empty($item['notes']))
                                            <span class="text-primary cursor-help"
                                                  data-bs-toggle="tooltip"
                                                  title="{{ $item['notes'] }}">
                                                <i class="ti ti-note"></i>
                                                {{ Str::limit($item['notes'], 30) }}
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-nowrap">
                                        <button type="button"
                                                class="btn btn-sm btn-warning btn-edit-item"
                                                data-index="{{ $index }}"
                                                title="Editar">
                                            <i class="ti ti-edit"></i>
                                        </button>
                                        <button type="button"
                                                class="btn btn-sm btn-danger"
                                                onclick="confirmDeleteItem({{ $index }})"
                                                title="Eliminar">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="ti ti-inbox fs-1 d-block mb-2"></i>
                                        No hay partidas agregadas. Usa el formulario superior para agregar una partida.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div id="itemAddedFeedback" class="requisition-item-feedback" aria-live="polite"></div>
            </div>
        </section>

        {{-- Botones --}}
        <div class="requisition-actions d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mt-4">
            <a href="{{ route('requisitions.index') }}" class="btn btn-outline-secondary">
                <i class="ti ti-x me-1"></i>Cancelar
            </a>
            <div class="d-flex gap-2">
                <button type="button"
                        onclick="confirmSaveDraft()"
                        class="btn btn-outline-primary">
                    <span>
                        <i class="ti ti-device-floppy me-1"></i>
                        {{ $isEditMode ? 'Actualizar Borrador' : 'Guardar como Borrador' }}
                    </span>
                </button>

                <button type="button"
                        id="btnSubmitRequisition"
                        onclick="confirmSubmit()"
                        class="btn btn-primary">
                    <span>
                        <i class="ti ti-send-2 me-1"></i>
                        {{ $isEditMode ? 'Actualizar y Enviar a Compras' : 'Enviar a Compras' }}
                    </span>
                </button>
            </div>
        </div>
    </div>

</div>

@push('styles')
<style>
    .requisition-workflow {
        max-width: 1120px;
        margin: 0 auto;
        color: #29384a;
    }

    .requisition-attachment-dropzone {
        display: flex;
        min-height: 9.5rem;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        gap: .45rem;
        padding: 1.25rem;
        border: 2px dashed #b9dcf6;
        border-radius: .75rem;
        color: #58718b;
        background: #f7fbff;
        cursor: pointer;
        text-align: center;
        transition: border-color .18s ease, background .18s ease, transform .18s ease;
    }

    .requisition-attachment-dropzone:hover,
    .requisition-attachment-dropzone.is-dragging {
        border-color: #188ae2;
        background: #eaf6ff;
        transform: translateY(-2px);
    }

    .requisition-attachment-dropzone > i {
        color: #188ae2;
        font-size: 2rem;
    }

    .requisition-attachment-dropzone strong {
        color: #29384a;
        font-size: .9rem;
    }

    .requisition-attachment-dropzone span {
        color: #738196;
        font-size: .78rem;
    }

    .requisition-attachment-dropzone.is-invalid {
        border-color: #dc3545;
    }

    .requisition-attachment-input {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        clip-path: inset(50%);
    }

    .requisition-attachment-selected {
        display: flex;
        align-items: center;
        gap: .5rem;
        margin-top: .75rem;
        padding: .65rem .8rem;
        border-radius: .55rem;
        font-size: .82rem;
    }

    .requisition-attachment-selected {
        color: #218b64;
        background: #f3fcf7;
    }

    .requisition-attachment-selected i {
        font-size: 1.1rem;
    }

    .requisition-page-intro,
    .requisition-surface-heading,
    .requisition-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .requisition-page-intro {
        gap: 1.5rem;
        margin: 0.5rem 0 1.5rem;
        justify-content: flex-start;
    }

    .requisition-page-title {
        color: #188ae2;
        font-size: 1.45rem;
    }

    .requisition-page-intro p,
    .requisition-surface-heading p {
        color: #738196;
        font-size: 0.85rem;
    }

    .requisition-folio,
    .requisition-items-badge,
    .requisition-character-count {
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .requisition-logo-spinner {
        width: 2.5rem;
        height: 2.5rem;
        flex: 0 0 auto;
        object-fit: contain;
        animation: requisition-logo-spin 8s linear infinite;
    }

    .requisition-surface {
        overflow: hidden;
        background: #fff;
        border: 1px solid #e2e9f0;
        border-radius: 0.75rem;
        box-shadow: 0 0.2rem 0.8rem rgba(32, 61, 92, 0.04);
        animation: requisition-surface-in 0.45s ease both;
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .requisition-surface:nth-of-type(2) {
        animation-delay: 0.1s;
    }

    .requisition-surface:hover {
        border-color: #d4e3ef;
        box-shadow: 0 0.6rem 1.5rem rgba(32, 61, 92, 0.09);
        transform: translateY(-2px);
    }

    .requisition-surface-heading {
        gap: 0.9rem;
        min-height: 5rem;
        padding: 1rem 1.5rem;
        border-bottom: 1px solid #edf1f5;
    }

    .requisition-surface-heading > div {
        flex: 1;
    }

    .requisition-surface-heading h5 {
        color: #29384a;
        font-size: 0.95rem;
    }

    .requisition-heading-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.5rem;
        height: 2.5rem;
        flex: 0 0 auto;
        color: #63758a;
        background: #f1f4f7;
        border-radius: 0.6rem;
        font-size: 1.2rem;
    }

    .requisition-heading-icon-primary {
        color: #188ae2;
        background: #e8f4fd;
    }

    .requisition-folio,
    .requisition-items-badge {
        padding: 0.4rem 0.65rem;
        color: #3973a3;
        background: #f0f7fd;
        border: 1px solid #d5eaf9;
        white-space: nowrap;
    }

    .requisition-general-body,
    .requisition-items-body {
        padding: 1.5rem;
    }

    .requisition-general-card .form-label,
    .requisition-item-form-panel .form-label {
        color: #42566c;
        font-size: 0.78rem;
        font-weight: 700;
    }

    .requisition-general-card .form-control,
    .requisition-general-card .form-select {
        min-height: 2.7rem;
        background-color: #fff;
    }

    .requisition-general-card .input-group .form-select {
        border-color: #dbeaf5;
    }

    .requisition-character-count {
        padding: 0.25rem 0.55rem;
        color: #748296;
        background: #f3f5f7;
    }

    .requisition-character-count.is-low {
        color: #b54708;
        background: #fff3e0;
    }

    .requisition-actions {
        gap: 1rem;
        padding: 1rem 1.25rem;
        background: #fff;
        border: 1px solid #e2e9f0;
        border-radius: 0.75rem;
    }

    .requisition-actions .btn {
        min-height: 2.55rem;
        padding-inline: 1rem;
    }

    .requisition-submit-popup {
        overflow: hidden;
        border: 0;
        border-radius: 1rem;
    }

    .requisition-submit-progress {
        padding: 0.5rem 0.5rem 0.25rem;
        text-align: center;
    }

    .requisition-submit-progress-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 4rem;
        height: 4rem;
        margin-bottom: 1rem;
        background: #edf8f2;
        border-radius: 50%;
    }

    .requisition-submit-progress-icon img {
        width: 2.7rem;
        height: 2.7rem;
        object-fit: contain;
        animation: requisition-logo-spin 2.5s linear infinite;
    }

    .requisition-submit-progress p {
        margin-bottom: 1rem;
        color: #6b7c8f;
        font-size: 0.85rem;
    }

    .requisition-submit-progress-bar {
        height: 0.45rem;
        overflow: hidden;
        background: #e8eef3;
        border-radius: 999px;
    }

    .requisition-submit-progress-bar span {
        display: block;
        width: 42%;
        height: 100%;
        background: linear-gradient(90deg, #188ae2, #4bd396, #188ae2);
        background-size: 200% 100%;
        border-radius: inherit;
        animation: requisition-progress-slide 1.35s ease-in-out infinite;
    }

    .input-group-text {
        color: #188ae2;
        background-color: #f0f7fd;
        border-color: #dbeaf5;
        border-right: 0;
    }

    .input-group > .select2-container {
        flex: 1 1 auto;
        width: 1% !important;
    }

    .input-group > .select2-container .select2-selection--single {
        height: calc(1.5em + 0.75rem + 2px) !important;
        border-color: #dbeaf5 !important;
        border-top-left-radius: 0 !important;
        border-bottom-left-radius: 0 !important;
        border-left: 0 !important;
    }

    .input-group > .select2-container .select2-selection__rendered {
        line-height: calc(1.5em + 0.75rem) !important;
        padding-left: 0.75rem !important;
    }

    .input-group > .select2-container .select2-selection__arrow {
        height: calc(1.5em + 0.75rem) !important;
    }

    #modal_product_id + .select2-container {
        width: 100% !important;
    }

    #modal_product_id + .select2-container .select2-selection--single {
        height: calc(1.5em + 0.75rem + 2px) !important;
        border-color: #ced4da;
        border-radius: 0.25rem;
    }

    #modal_product_id + .select2-container .select2-selection__rendered {
        padding-left: 0.75rem;
        color: #4c4c5c;
        line-height: calc(1.5em + 0.75rem) !important;
    }

    #modal_product_id + .select2-container .select2-selection__arrow {
        height: calc(1.5em + 0.75rem) !important;
    }

    #modal_product_id + .select2-container--focus .select2-selection--single,
    #modal_product_id + .select2-container--open .select2-selection--single {
        border-color: #188ae2;
        box-shadow: 0 0 0 0.2rem rgba(24, 138, 226, 0.15);
    }

    .select2-container--open {
        z-index: 1080;
    }

    .form-control:focus+.input-group-text,
    .form-select:focus~.input-group-text {
        border-color: #86b7fe;
        background-color: #fff;
    }

    .modal-footer {
        border-top: 1px solid #dee2e6;
    }

    #itemFormPanel.requisition-item-form-panel {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
        height: auto !important;
        position: relative;
        padding: 1.25rem !important;
        background: #f8fafc;
        border: 1px solid #e1e8ef !important;
        box-shadow: none;
        transition: border-color 0.2s ease, background-color 0.2s ease;
    }

    #itemFormPanel.requisition-item-form-panel:hover {
        background: #f5f9fd;
        border-color: #cfe2f1 !important;
    }

    #itemFormPanel.requisition-item-form-panel.is-context-locked {
        background: #f5f7fa;
        border-color: #dce3ea !important;
    }

    #itemFormPanel.requisition-item-form-panel.is-context-locked::after {
        content: '';
        position: absolute;
        inset: 0;
        z-index: 2;
        cursor: not-allowed;
    }

    .requisition-item-form-lock {
        position: absolute;
        z-index: 3;
        top: 1rem;
        right: 1rem;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.35rem 0.6rem;
        color: #64748b;
        background: #fff;
        border: 1px solid #dce3ea;
        border-radius: 0.5rem;
        font-size: 0.75rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }

    #modal_description {
        font-size: 0.95rem;
        line-height: 1.4;
    }

    .table thead th {
        font-weight: 600;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: #475569;
        border-bottom-width: 1px;
    }

    .table tbody td {
        vertical-align: middle;
        font-size: 0.875rem;
    }

    .table tbody tr:hover {
        background-color: #f8faff;
    }

    .table tbody tr.requisition-item-added td {
        background: linear-gradient(90deg, rgba(24, 138, 226, 0.15), rgba(75, 211, 150, 0.08), transparent);
        background-size: 220% 100%;
        animation: requisition-item-added 1.1s ease-out both;
    }

    .requisition-item-feedback {
        position: relative;
        height: 0;
        margin-top: 0;
        overflow: hidden;
        color: #167847;
        font-size: 0.8rem;
        font-weight: 600;
        transition: height 0.25s ease, margin-top 0.25s ease;
    }

    .requisition-item-feedback.is-visible {
        height: 2.1rem;
        margin-top: 0.75rem;
    }

    .requisition-item-feedback::before {
        position: absolute;
        inset: 0;
        content: '';
        background: linear-gradient(90deg, transparent, rgba(24, 138, 226, 0.6), #4bd396, transparent);
        transform: translateX(-100%);
    }

    .requisition-item-feedback.is-visible::before {
        animation: requisition-laser-sweep 1.1s ease-out both;
    }

    .requisition-item-feedback span {
        position: relative;
        z-index: 1;
        display: inline-flex;
        align-items: center;
        height: 100%;
        padding: 0 0.65rem;
        opacity: 0;
    }

    .requisition-item-feedback.is-visible span {
        animation: requisition-feedback-text 0.45s 0.2s ease both;
    }

    #itemsTable input.form-control-sm,
    #itemsTable select.form-select-sm {
        font-size: 0.85rem;
    }

    .rotating {
        display: inline-block;
        animation: rotate 1s linear infinite;
    }

    @keyframes rotate {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    @keyframes requisition-logo-spin {
        to { transform: rotate(360deg); }
    }

    @keyframes requisition-surface-in {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @keyframes requisition-item-added {
        from { background-position: 100% 0; }
        to { background-position: 0 0; }
    }

    @keyframes requisition-laser-sweep {
        from { transform: translateX(-100%); }
        to { transform: translateX(100%); }
    }

    @keyframes requisition-feedback-text {
        from { opacity: 0; transform: translateX(-8px); }
        to { opacity: 1; transform: translateX(0); }
    }

    @keyframes requisition-progress-slide {
        0% { transform: translateX(-100%); background-position: 100% 0; }
        50% { background-position: 0 0; }
        100% { transform: translateX(240%); background-position: 100% 0; }
    }

    @media (prefers-reduced-motion: reduce) {
        .requisition-logo-spinner,
        .requisition-submit-progress-icon img,
        .requisition-submit-progress-bar span,
        .requisition-surface,
        .table tbody tr.requisition-item-added td,
        .requisition-item-feedback.is-visible::before,
        .requisition-item-feedback.is-visible span {
            animation: none;
        }

        .requisition-surface,
        #itemFormPanel.requisition-item-form-panel {
            transition: none;
        }
    }

    @media (max-width: 575.98px) {
        .requisition-page-intro,
        .requisition-surface-heading {
            align-items: flex-start;
        }

        .requisition-page-intro {
            flex-direction: column;
        }

        .requisition-surface-heading {
            padding: 1rem;
        }

        .requisition-folio {
            display: none;
        }

        .requisition-general-body,
        .requisition-items-body {
            padding: 1rem;
        }

        .requisition-actions > div {
            width: 100%;
        }

        .requisition-actions > div .btn {
            flex: 1 1 0;
        }
    }
</style>
@endpush

@push('scripts')
<script>

let itemModalInitialState = null;
let itemAddedFeedbackTimer = null;

// =====================================================
// FUNCIÓN PARA CONFIRMAR ELIMINACIÓN DE PARTIDA
// =====================================================
function confirmDeleteItem(index) {
    Swal.fire({
        title: '¿Eliminar partida?',
        text: "Esta partida será eliminada de la requisición",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (result.isConfirmed) {
            const wire = window.getRequisitionWire?.();
            if (!wire) {
                Swal.fire('Error', 'No se pudo conectar con el formulario.', 'error');
                return;
            }

            await wire.$call('removeItem', index);
            window.renderRequisitionItems?.();
        }
    });
}

/**
 * Confirmar guardar como borrador
 */
function confirmSaveDraft() {
    const isEditMode = {{ $isEditMode ? 'true' : 'false' }};
    const title = isEditMode ? '¿Actualizar borrador?' : '¿Guardar como borrador?';
    const confirmText = isEditMode
        ? '<i class="ti ti-device-floppy me-1"></i> Sí, actualizar'
        : '<i class="ti ti-device-floppy me-1"></i> Sí, guardar borrador';

    Swal.fire({
        title: title,
        html: `
            <div class="text-start">
                <p class="mb-3"><strong>Al guardar como borrador:</strong></p>
                <ul class="text-muted small">
                    <li class="mb-2">
                        <i class="ti ti-edit text-info"></i>
                        Podrás <strong>editar, agregar o eliminar</strong> partidas después
                    </li>
                    <li class="mb-2">
                        <i class="ti ti-send text-success"></i>
                        Podrás enviarlo a Compras cuando esté listo
                    </li>
                    <li class="mb-2">
                        <i class="ti ti-trash text-danger"></i>
                        Podrás eliminarlo si ya no es necesario
                    </li>
                </ul>
                <div class="alert alert-info mt-3 mb-0">
                    <i class="ti ti-info-circle me-2"></i>
                    <small>Compras <strong>NO</strong> recibirá notificación hasta que lo envíes.</small>
                </div>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: '<i class="ti ti-x me-1"></i> Cancelar',
        confirmButtonColor: '#0d6efd',
        cancelButtonColor: '#6c757d',
        width: '600px',
        customClass: {
            confirmButton: 'btn btn-primary',
            cancelButton: 'btn btn-outline-secondary'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const wire = window.syncFormValuesToWire?.();
            if (!wire) {
                Swal.fire('Error', 'No se pudo conectar con el formulario de requisición.', 'error');
                return;
            }
            wire?.$call('saveDraft');
        }
    });
}

/**
 * Confirmar enviar a compras
 */
function confirmSubmit() {
    const isEditMode = {{ $isEditMode ? 'true' : 'false' }};
    const title = isEditMode ? '¿Actualizar y enviar a Compras?' : '¿Enviar a Compras?';

    Swal.fire({
        title: title,
        html: `
            <div class="text-start">
                <p class="mb-3"><strong>Al enviar a Compras:</strong></p>
                <ul class="text-muted small">
                    <li class="mb-2">
                        <i class="ti ti-lock text-danger"></i>
                        <strong>Ya NO podrás editar</strong> la requisición, solo <strong class="text-danger">cancelarla</strong>
                    </li>
                    <li class="mb-2">
                        <i class="ti ti-bell text-primary"></i>
                        <strong>Compras recibirá notificación</strong> para iniciar cotización
                    </li>
                    <li class="mb-2">
                        <i class="ti ti-eye text-info"></i>
                        Podrás consultar el estatus, pero <strong>no modificarla</strong>
                    </li>
                </ul>
                <div class="alert alert-warning mt-3 mb-0">
                    <i class="ti ti-alert-triangle me-2"></i>
                    <small><strong>Importante:</strong> verifica que toda la información sea correcta antes de enviar.</small>
                </div>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '<i class="ti ti-send me-1"></i> Sí, enviar a Compras',
        cancelButtonText: '<i class="ti ti-arrow-left me-1"></i> Revisar de nuevo',
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d',
        width: '600px',
        customClass: {
            confirmButton: 'btn btn-success',
            cancelButton: 'btn btn-outline-secondary'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Validar que haya partidas antes de enviar
            const wire = window.syncFormValuesToWire?.();
            if (!wire) {
                Swal.fire('Error', 'No se pudo conectar con el formulario de requisición.', 'error');
                return;
            }
            const itemsCount = Array.isArray(wire?.$get('items')) ? wire.$get('items').length : 0;

            if (itemsCount === 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Requisición vacía',
                    text: 'Debes agregar al menos una partida antes de enviar a Compras (RN-003).',
                    confirmButtonText: 'Entendido',
                    confirmButtonColor: '#dc3545'
                });
                return;
            }

            showRequisitionSubmitProgress();

            wire.$call('submit').catch(() => {
                hideRequisitionSubmitProgress();
                Swal.fire('Error', 'No se pudo enviar la requisición. Intenta nuevamente.', 'error');
            });
        }
    });
}

function showRequisitionSubmitProgress() {
    $('#btnSubmitRequisition').prop('disabled', true);

    Swal.fire({
        title: 'Enviando a Compras',
        html: `
            <div class="requisition-submit-progress">
                <div class="requisition-submit-progress-icon">
                    <img src="{{ asset('images/logos/Logo.png') }}" alt="TotalGas">
                </div>
                <p>Estamos registrando tu requisición y notificando al equipo de Compras.</p>
                <div class="requisition-submit-progress-bar" aria-label="Envío en proceso"><span></span></div>
            </div>
        `,
        showConfirmButton: false,
        allowOutsideClick: false,
        allowEscapeKey: false,
        customClass: {
            popup: 'requisition-submit-popup'
        }
    });
}

function hideRequisitionSubmitProgress() {
    const popup = Swal.getPopup();

    if (popup?.classList.contains('requisition-submit-popup')) {
        Swal.close();
    }

    $('#btnSubmitRequisition').prop('disabled', false);
}

$(function() {
    let modalAttachmentKey = null;
    let modalAttachmentUploading = false;

    function resetModalAttachment() {
        modalAttachmentKey = null;
        modalAttachmentUploading = false;
        $('#modal_attach_document').prop('checked', false);
        $('#modal_attachment').val('');
        $('#modal_attachment_dropzone, #modal_attachment_name').addClass('d-none');
        $('#modal_attachment_name').empty();
    }

    function uploadModalAttachment(file) {
        const wire = getRequisitionWire();
        if (!wire || !file) return;

        modalAttachmentUploading = true;
        modalAttachmentKey = modalAttachmentKey || (window.crypto?.randomUUID?.() || `attachment-${Date.now()}`);
        $('#modal_attachment_name').removeClass('d-none').html('<span class="spinner-border spinner-border-sm"></span> Cargando documento…');
        wire.upload(`itemAttachments.${modalAttachmentKey}`, file,
            () => {
                modalAttachmentUploading = false;
                $('#modal_attachment_name').html('<i class="ti ti-file-check"></i> ' + $('<div>').text(file.name).html());
            },
            () => {
                modalAttachmentUploading = false;
                resetModalAttachment();
                Swal.fire('Error', 'No se pudo cargar el documento.', 'error');
            }
        );
    }

    $(document).on('change', '#modal_attach_document', function() {
        $('#modal_attachment_dropzone').toggleClass('d-none', !this.checked);
        if (!this.checked) resetModalAttachment();
    });

    $(document).on('change', '#modal_attachment', function() {
        uploadModalAttachment(this.files?.[0]);
    });

    document.addEventListener('dragover', event => {
        const dropzone = event.target.closest('[data-requisition-dropzone]');
        if (!dropzone) return;

        event.preventDefault();
        dropzone.classList.add('is-dragging');
    });

    document.addEventListener('dragleave', event => {
        const dropzone = event.target.closest('[data-requisition-dropzone]');
        if (dropzone) dropzone.classList.remove('is-dragging');
    });

    document.addEventListener('drop', event => {
        const dropzone = event.target.closest('[data-requisition-dropzone]');
        if (!dropzone) return;

        event.preventDefault();
        dropzone.classList.remove('is-dragging');

        const file = event.dataTransfer?.files?.[0];
        const input = dropzone.querySelector('[data-requisition-file-input]');
        if (!file || !input) return;

        const transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });

    'use strict';

    // =====================================================
    // VARIABLE GLOBAL
    // =====================================================
    let editingIndex = null;

    function normalizeLivewireReference(candidate) {
        if (candidate && typeof candidate.$call === 'function') {
            return candidate;
        }

        if (candidate?.$wire && typeof candidate.$wire.$call === 'function') {
            return candidate.$wire;
        }

        return null;
    }

    function getRequisitionWire() {
        if (typeof Livewire !== 'undefined') {
            const componentId = document.getElementById('requisition_livewire_id')?.value;
            if (componentId) {
                const byId = normalizeLivewireReference(Livewire.find(componentId));
                if (byId) {
                    return byId;
                }
            }
        }

        return null;
    }
    window.getRequisitionWire = getRequisitionWire;

    const costCenterCatalog = @json($costCenterCatalog);

    function escapeHtml(value) {
        return $('<div>').text(value ?? '').html();
    }

    function truncate(value, length) {
        const text = String(value ?? '');
        return text.length > length ? `${text.slice(0, length - 1)}…` : text;
    }

    function ensureItemFormVisible() {
        const panel = document.getElementById('itemFormPanel');

        if (!panel) {
            console.error('No se encontró el formulario de partida en el DOM.');
            return;
        }

        panel.classList.remove('d-none', 'collapse', 'collapsing');
        panel.hidden = false;
        panel.style.setProperty('display', 'block', 'important');
        panel.style.setProperty('visibility', 'visible', 'important');
        panel.style.setProperty('opacity', '1', 'important');
        panel.style.removeProperty('height');
    }
    window.ensureItemFormVisible = ensureItemFormVisible;

    function updateItemFormAvailability() {
        const $panel = $('#itemFormPanel');
        const isReady = Boolean($('#company_id').val() && $('#receiving_location_id').val());

        $panel.toggleClass('is-context-locked', !isReady)
            .attr('aria-disabled', String(!isReady));
        $('#itemFormLockMessage').toggleClass('d-none', isReady);
        $('#btnAddItem, #btnSaveItem').prop('disabled', !isReady);
    }

    function renderRequisitionItems(animateLastItem = false, suppliedItems = null) {
        ensureItemFormVisible();

        const wire = getRequisitionWire();
        const items = Array.isArray(suppliedItems)
            ? suppliedItems
            : (Array.isArray(wire?.$get('items')) ? wire.$get('items') : []);
        const $body = $('#requisitionItemsBody');
        const $badge = $('#itemsCountBadge');

        if (!$body.length) {
            return;
        }

        $badge.text(`${items.length} partida(s)`).toggleClass('d-none', items.length === 0);

        if (items.length === 0) {
            $body.html(`
                <tr>
                <td colspan="7" class="text-center text-muted py-4">
                        <i class="ti ti-inbox fs-1 d-block mb-2"></i>
                        No hay partidas agregadas. Usa el formulario superior para agregar una partida.
                    </td>
                </tr>
            `);
            return;
        }

        const newItemIndex = animateLastItem ? items.length - 1 : -1;

        $body.html(items.map((item, index) => {
            const productName = escapeHtml(item.product_name);
            const costCenterName = escapeHtml(item.cost_center_name || '—');
            const notes = escapeHtml(item.notes || '');
            const notesCell = notes
                ? `<span class="text-primary cursor-help" title="${notes}">
                       <i class="ti ti-note"></i> ${escapeHtml(truncate(item.notes, 30))}
                   </span>`
                : '<span class="text-muted">—</span>';

            return `
                <tr class="${index === newItemIndex ? 'requisition-item-added' : ''}">
                    <td>${index + 1}</td>
                    <td><strong>${productName}</strong></td>
                    <td>${escapeHtml(item.quantity)}</td>
                    <td>${escapeHtml(item.unit)}</td>
                    <td>
                        <span class="badge bg-secondary" title="${costCenterName}">
                            ${escapeHtml(truncate(item.cost_center_name || '—', 25))}
                        </span>
                    </td>
                    <td>${notesCell}</td>
                    <td class="text-nowrap">
                        <button type="button" class="btn btn-sm btn-warning btn-edit-item"
                                data-index="${index}" title="Editar">
                            <i class="ti ti-edit"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-danger"
                                onclick="confirmDeleteItem(${index})" title="Eliminar">
                            <i class="ti ti-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join(''));
    }
    window.renderRequisitionItems = renderRequisitionItems;

    function showItemAddedFeedback(productName) {
        const $feedback = $('#itemAddedFeedback');

        if (!$feedback.length || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        clearTimeout(itemAddedFeedbackTimer);
        $feedback.removeClass('is-visible').html(`<span><i class="ti ti-sparkles me-1"></i>${escapeHtml(productName)} se agregó a las partidas</span>`);
        void $feedback[0].offsetWidth;
        $feedback.addClass('is-visible');

        itemAddedFeedbackTimer = setTimeout(() => {
            $feedback.removeClass('is-visible');
        }, 3200);
    }

    function syncFormValuesToWire() {
        const wire = getRequisitionWire();

        if (!wire) {
            return null;
        }

        // Deferimos los valores para enviarlos junto con saveDraft/submit.
        const selectedCompanyId = $('#company_id').val() || '';
        if (String(wire.$get('company_id') || '') !== String(selectedCompanyId)) {
            wire.$set('company_id', selectedCompanyId, false);
        }

        wire.$set('receiving_location_id', $('#receiving_location_id').val() || '', false);
        wire.$set('description', $('#description').val() || '', false);

        return wire;
    }
    window.syncFormValuesToWire = syncFormValuesToWire;

    function renderModalCostCenters(mode = 'reset') {
        const companyId    = $('#company_id').val() || '';
        const $cc          = $('#modal_cost_center_id');

        $cc.empty();

        if (!companyId) {
            $cc.prop('disabled', true)
               .append('<option value="">Selecciona primero una compañía...</option>');
            $('#modal_cost_center_help').text('');
            initializeSearchableSelect($cc, 'Seleccionar centro de costo...', {
                dropdownParent: $(document.body)
            });
            return;
        }

        const matches = costCenterCatalog.filter(row =>
            String(row.company_id) === String(companyId)
        );

        if (matches.length === 0) {
            $cc.prop('disabled', true)
               .append('<option value="">Sin centros de costo para esta compañía</option>');
            $('#modal_cost_center_help').text('No tienes centros de costo asignados en esta compañía.');
            initializeSearchableSelect($cc, 'Seleccionar centro de costo...', {
                dropdownParent: $(document.body)
            });
            return;
        }

        $cc.prop('disabled', false)
           .append('<option value="">Seleccionar centro de costo...</option>');

        matches.forEach(row => {
            const ccLabel = row.code ? `[${row.code}] ${row.name}` : row.name;
            $cc.append($('<option>', {
                value: row.id,
                text: ccLabel,
                'data-name': row.name,
                'data-purchase-type': row.purchase_type
            }));
        });

        if (mode === 'edit' && $cc.data('pending-value')) {
            $cc.val(String($cc.data('pending-value')));
            $cc.data('pending-value', null);
        } else if (matches.length === 1) {
            $cc.val(String(matches[0].id));
        }

        initializeSearchableSelect($cc, 'Seleccionar centro de costo...', {
            dropdownParent: $(document.body)
        });

        if ($cc.val()) {
            loadProductsForCostCenter();
            loadExpenseCategories();
        }
    }

    function initializeSearchableSelect($element, placeholder, options = {}) {
        if (!$element.length) {
            return;
        }

        if ($element.data('select2')) {
            $element.off('.requisitionSelect2');
            $element.select2('destroy');
        }

        $element.select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: placeholder,
            allowClear: options.allowClear ?? true,
            language: {
                noResults: function() { return 'No se encontraron resultados'; },
                searching: function() { return 'Buscando...'; }
            },
            ...options
        });
    }

    function initializeExpenseCategorySelect() {
        initializeSearchableSelect($('#modal_expense_category'), 'Buscar cuenta...', {
            dropdownParent: $(document.body),
            allowClear: false
        });
    }

    function initializeHeaderSelects() {
        initializeSearchableSelect($('#company_id'), 'Seleccionar compañía...');
        initializeSearchableSelect($('#receiving_location_id'), 'Seleccionar ubicación de recepción...');
    }

    function initializeModalBaseSelects() {
        initializeSearchableSelect($('#modal_cost_center_id'), 'Seleccionar centro de costo...', {
            dropdownParent: $(document.body)
        });
    }

    function initializeRequisitionSelects() {
        initializeHeaderSelects();
        initializeModalBaseSelects();
        initializeExpenseCategorySelect();
    }

    function resetItemFormControls() {
        if (document.getElementById('itemForm')) {
            document.getElementById('itemForm').reset();
        }

        $('#item_index').val('');
        resetModalAttachment();
        $('#itemModalTitle').text('Agregar Partida');
        setItemSaveButtonMode(false);
        $('#modal_product_id').empty().append('<option value="">Buscar producto del catálogo...</option>');
        $('#modal_expense_category').empty().append('<option value="">Seleccione primero un centro de costo...</option>').prop('disabled', true);
        resetBudgetCedulaSelect();
        renderModalCostCenters('reset');
        initializeExpenseCategorySelect();
    }

    function resetItemFormForNextItem(keepCostCenter = true) {
        const currentCostCenterId = keepCostCenter ? $('#modal_cost_center_id').val() : '';

        $('#item_index').val('');
        resetModalAttachment();
        $('#itemModalTitle').text('Agregar Partida');
        setItemSaveButtonMode(false);
        $('#modal_product_id').val(null).trigger('change');
        $('#modal_product_id').empty().append('<option value="">Buscar producto del catálogo...</option>');
        $('#modal_description').val('');
        $('#modal_quantity').val('1');
        $('#modal_unit').val('');
        $('#modal_notes').val('');
        $('#modal_expense_category').empty().append('<option value="">Seleccione primero un centro de costo...</option>').prop('disabled', true);
        resetBudgetCedulaSelect();

        if (currentCostCenterId) {
            $('#modal_cost_center_id').val(String(currentCostCenterId)).trigger('change');
        } else {
            renderModalCostCenters('reset');
        }

        setTimeout(setItemModalInitialState, 100);
    }

    function getItemModalState() {
        return JSON.stringify({
            cost_center_id: $('#modal_cost_center_id').val() || '',
            product_id: $('#modal_product_id').val() || '',
            description: $('#modal_description').val() || '',
            quantity: $('#modal_quantity').val() || '',
            unit: $('#modal_unit').val() || '',
            expense_category_id: $('#modal_expense_category').val() || '',
            budget_cedula_id: $('#modal_budget_cedula').val() || '',
            notes: $('#modal_notes').val() || ''
        });
    }

    function setItemSaveButtonMode(isEditing) {
        $('#btnSaveItem').html(isEditing
            ? '<i class="ti ti-device-floppy me-1"></i>Actualizar Partida'
            : '<i class="ti ti-check me-1"></i>Guardar Partida');
    }

    function setItemModalInitialState() {
        itemModalInitialState = getItemModalState();
    }

    function itemModalHasUnsavedChanges() {
        if (itemModalInitialState === null) {
            return false;
        }

        return getItemModalState() !== itemModalInitialState;
    }

    function confirmItemModalClose(onConfirm) {
        Swal.fire({
            title: '¿Cerrar sin guardar?',
            text: 'Tienes cambios sin guardar en la partida. Si continúas, se perderán.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, cerrar',
            cancelButtonText: 'Seguir editando',
            confirmButtonColor: '#d33',
            cancelButtonColor: '#0d6efd'
        }).then((result) => {
            if (result.isConfirmed) {
                onConfirm();
            }
        });
    }

    initializeRequisitionSelects();
    ensureItemFormVisible();
    resetItemFormControls();
    updateItemFormAvailability();

    function loadReceivingLocationsForCompany(companyId, selectedValue = '') {
        const $company = $('#company_id');
        const $location = $('#receiving_location_id');

        if (!companyId) {
            $location.empty().append('<option value="">Seleccionar...</option>').val('');
            initializeSearchableSelect($location, 'Seleccionar ubicación de recepción...');
            return;
        }

        $location.prop('disabled', true).empty().append('<option value="">Cargando...</option>');
        initializeSearchableSelect($location, 'Seleccionar ubicación de recepción...');

        const url = String($company.attr('data-url-receiving-locations')).replace('__CID__', companyId);

        $.getJSON(url)
            .done(function(data) {
                const list = Array.isArray(data) ? data : [];
                $location.empty().append('<option value="">Seleccionar...</option>');

                list.forEach(function(row) {
                    $location.append($('<option>', {
                        value: row.id,
                        text: row.label
                    }));
                });

                if (selectedValue && list.some(row => String(row.id) === String(selectedValue))) {
                    $location.val(String(selectedValue));
                } else {
                    $location.val('');
                }

                const wire = getRequisitionWire();
                if (wire) {
                    wire.$set('receiving_location_id', $location.val() || '', false);
                }
            })
            .always(function() {
                $location.prop('disabled', false);
                initializeSearchableSelect($location, 'Seleccionar ubicación de recepción...');
                $location.trigger('change');
            });
    }

    $(document)
        .off('change.requisitionCompany', '#company_id')
        .on('change.requisitionCompany', '#company_id', async function () {
            const wire = getRequisitionWire();
            const companyId = $(this).val() || '';

            if (wire) {
                await wire.$call('selectCompany', companyId);
                renderRequisitionItems();
            }

            ensureItemFormVisible();
            resetItemFormControls();
            loadReceivingLocationsForCompany(companyId);
            updateItemFormAvailability();
        });

    $(document)
        .off('change.requisitionLocation', '#receiving_location_id')
        .on('change.requisitionLocation', '#receiving_location_id', function () {
            const wire = getRequisitionWire();

            if (wire) {
                wire.$set('receiving_location_id', $(this).val() || '', false);
            }

            updateItemFormAvailability();
        });

    // =====================================================
    // LISTENER: Cambio de Centro de Costo en modal
    // =====================================================
    $(document)
        .off('change.modalCostCenter', '#modal_cost_center_id')
        .on('change.modalCostCenter', '#modal_cost_center_id', function () {
            const ccId = $(this).val();
            $('#modal_expense_category').val(null).trigger('change');
            resetBudgetCedulaSelect();

            if (ccId) {
                loadProductsForCostCenter();
                loadExpenseCategories();
            } else {
                $('#modal_expense_category')
                    .empty()
                    .append('<option value="">Seleccione primero un centro de costo...</option>')
                    .prop('disabled', true);
                initializeExpenseCategorySelect();
            }
        });

    // =====================================================
    // 1. LIMPIAR FORMULARIO PARA AGREGAR
    // =====================================================
    $(document).off('click.requisitionAddItem', '#btnAddItem').on('click.requisitionAddItem', '#btnAddItem', function() {
        const companyId = $('#company_id').val();

        if (!companyId) {
            Swal.fire('Datos incompletos', 'Primero selecciona una compañía.', 'warning');
            return;
        }

        resetItemFormForNextItem(false);
    });

    /**
     * Verificar si hay productos/servicios activos disponibles para el centro de costo.
     */
    function checkProductsAvailability(companyId, costCenterId) {
        return new Promise((resolve, reject) => {
            $.ajax({
                url: '{{ route("products-services.api.active-for-requisitions") }}',
                type: 'GET',
                data: {
                    company_id: companyId,
                    cost_center_id: costCenterId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.products && response.products.length > 0) {
                        console.log(`Productos disponibles: ${response.products.length}`);
                        resolve(true);
                    } else {
                        Swal.fire({
                            title: 'Sin productos en el catálogo',
                            html: `
                                <div class="text-start">
                                    <div class="alert alert-warning mb-3">
                                        <i class="ti ti-alert-triangle me-2"></i>
                                        <strong>No se puede agregar partida</strong>
                                    </div>

                                    <p class="mb-3">
                                        No hay productos o servicios <strong>activos</strong> registrados en el
                                        catálogo para este centro de costo.
                                    </p>

                                    <div class="card bg-light border-0 mb-3">
                                        <div class="card-body">
                                            <h6 class="card-title text-primary mb-2">
                                                <i class="ti ti-info-circle me-1"></i>¿Qué significa esto?
                                            </h6>
                                            <p class="small mb-0">
                                                Solo puedes requisar productos que estén previamente registrados
                                                y aprobados en el <strong>catálogo de productos y servicios</strong>.
                                            </p>
                                        </div>
                                    </div>

                                    <div class="card border-primary mb-0">
                                        <div class="card-body">
                                            <h6 class="card-title text-primary mb-2">
                                                <i class="ti ti-checklist me-1"></i>¿Qué debo hacer?
                                            </h6>
                                            <ol class="small mb-0 ps-3">
                                                <li class="mb-2">
                                                    Accede al módulo de <strong>Catálogo de Productos/Servicios</strong>
                                                </li>
                                                <li class="mb-2">
                                                    Crea un nuevo producto/servicio para tu centro de costo
                                                </li>
                                                <li class="mb-2">
                                                    Espera a que sea <strong>aprobado</strong> por el administrador del catálogo
                                                </li>
                                                <li>
                                                    Una vez aprobado, podrás agregarlo a tus requisiciones
                                                </li>
                                            </ol>
                                        </div>
                                    </div>
                                </div>
                            `,
                            icon: 'warning',
                            confirmButtonText: 'Entendido',
                            confirmButtonColor: '#0d6efd',
                            width: '600px',
                            customClass: {
                                popup: 'text-start'
                            }
                        });
                        resolve(false);
                    }
                },
                error: function(xhr) {
                    console.error('Error al verificar productos:', xhr);
                    Swal.fire({
                        title: 'Error',
                        text: xhr.responseJSON?.message || 'Error al verificar productos disponibles en el catálogo.',
                        icon: 'error',
                        confirmButtonText: 'Entendido'
                    });
                    resolve(false);
                }
            });
        });
    }

    /**
     * Abrir modal en modo AGREGAR (limpio).
     */
    function openItemModal() {
        editingIndex = null;

        $('#itemModalTitle').text('Agregar Partida');
        document.getElementById('itemForm').reset();
        $('#item_index').val('');
        $('#budgetAlert').hide();

        loadExpenseCategories();

        setTimeout(setItemModalInitialState, 100);
    }

    // =====================================================
    // 2. EDITAR PARTIDA
    // =====================================================
    $(document).on('click', '.btn-edit-item', function() {
        const index = parseInt($(this).data('index'));

        const wire = getRequisitionWire();
        const item = Array.isArray(wire?.$get('items')) ? wire.$get('items')[index] : null;

        if (!item) {
            Swal.fire('Error', 'No se pudo cargar la partida para editar.', 'error');
            return;
        }

        openItemModalForEdit(index, item);
    });

    /**
     * Abrir modal en modo EDITAR con datos pre-cargados.
     */
    function openItemModalForEdit(index, item) {
        editingIndex = index;

        $('#itemModalTitle').text('Editar Partida');
        $('#item_index').val(index);
        setItemSaveButtonMode(true);
        $('#budgetAlert').hide();

        loadProductsForCostCenter();
        loadExpenseCategories();

        setTimeout(() => {
            $('#modal_product_id').val(item.product_id).trigger('change');
            applySelectedProductClassification();
            $('#modal_description').val(item.description);
            $('#modal_quantity').val(item.quantity);
            $('#modal_unit').val(item.unit);
            $('#modal_expense_category').val(item.expense_category_id).trigger('change');
            $('#modal_notes').val(item.notes || '');
        }, 500);

        setTimeout(setItemModalInitialState, 200);
    }

    // =====================================================
    // 3. CARGAR PRODUCTOS DEL CATÁLOGO
    // =====================================================
    function loadProductsForCostCenter() {
        const costCenterId = $('#modal_cost_center_id').val();
        const $product = $('#modal_product_id');

        $product.empty().append('<option value="">Buscar producto...</option>');
        $product.prop('disabled', !costCenterId);
        initializeProductSelect2();
    }

    // =====================================================
    // 4. INICIALIZAR SELECT2
    // =====================================================
    function hideManualBudgetSelectors() {
        $('#modal_expense_category').closest('.mb-3').hide();
        $('#modal_budget_cedula').closest('.mb-3').hide();
    }

    function selectedProductClassification() {
        const $product = $('#modal_product_id');
        const classification = $product.data('budget-classification')
            || $product.find('option:selected').data('budget-classification');

        return classification || null;
    }

    function applySelectedProductClassification() {
        const classification = selectedProductClassification();

        if (!classification) {
            $('#modal_expense_category').empty().append('<option value=""></option>').val('');
            $('#modal_budget_cedula').empty().append('<option value=""></option>').val('');

            return false;
        }

        $('#modal_expense_category')
            .empty()
            .append(`<option value="${classification.expense_category_id}">${classification.expense_category_name}</option>`)
            .val(String(classification.expense_category_id));

        $('#modal_budget_cedula')
            .empty()
            .append(`<option value="${classification.budget_cedula_id}">${classification.budget_cedula_name}</option>`)
            .val(String(classification.budget_cedula_id));

        return true;
    }

    function initializeProductSelect2() {
        hideManualBudgetSelectors();

        const $product = $('#modal_product_id');

        initializeSearchableSelect($product, 'Buscar producto...', {
            dropdownParent: $(document.body),
            allowClear: true,
            ajax: {
                url: '{{ route("products-services.api.active-for-requisitions") }}',
                dataType: 'json',
                delay: 250,
                data: params => ({
                    company_id: $('#company_id').val(),
                    cost_center_id: $('#modal_cost_center_id').val(),
                    search: params.term || '',
                    page: params.page || 1,
                }),
                processResults: response => ({
                    results: (response.products || []).map(product => ({
                        ...product,
                        text: `[${product.code}] ${product.short_name || product.description || 'Sin nombre'}`,
                    })),
                    pagination: response.pagination || { more: false },
                }),
            },
        });

        $product.off('.requisitionProduct');

        $product.on('select2:select.requisitionProduct', function(e) {
            const product = e.params.data;

            $product.data('requisition-product', product);
            $product.data('budget-classification', product.budget_classification || null);
            $product.find('option:selected').data('budget-classification', product.budget_classification || null);
            $('#modal_description').val(product.description || '');
            $('#modal_unit').val(product.unit_of_measure || 'PZA');
            $('#modal_suggested_vendor').val(product.default_vendor_name || 'Sin proveedor sugerido');

            const minQty = product.minimum_quantity;
            const maxQty = product.maximum_quantity;
            const unit = product.unit_of_measure || 'PZA';

            let helpText = 'Mínimo: 0.001';
            if (minQty) {
                helpText = `Mínimo: ${minQty} ${unit}`;
            }
            if (maxQty) {
                helpText += ` | Máximo: ${maxQty} ${unit}`;
            }

            $('#modal_quantity').siblings('.form-text').html(`<i class="ti ti-info-circle me-1"></i>${helpText}`);

            applySelectedProductClassification();
        });

        $product.on('select2:clear.requisitionProduct', function() {
            $product.removeData('requisition-product');
            $product.removeData('budget-classification');
            $('#modal_expense_category').empty().append('<option value=""></option>').val('');
            $('#modal_budget_cedula').empty().append('<option value=""></option>').val('');
        });
    }

    // =====================================================
    // 5. CARGAR CATEGORÍAS DE GASTO
    // =====================================================
    function loadExpenseCategories() {
        return new Promise((resolve, reject) => {
            const $select = $('#modal_expense_category');
            const costCenterId = $('#modal_cost_center_id').val();

            if (!costCenterId) {
                $select.empty()
                    .append('<option value="">Seleccione primero un Centro de Costo...</option>')
                    .prop('disabled', true);
                initializeExpenseCategorySelect();
                resolve(false);
                return;
            }

            $select.prop('disabled', true)
                .empty()
                .append('<option value="">Cargando cuentas...</option>');
            initializeExpenseCategorySelect();

            $.ajax({
                url: '{{ route("expense-categories.by-cost-center") }}',
                type: 'GET',
                data: {
                    cost_center_id: costCenterId
                },
                dataType: 'json',
                success: function(response) {
                    $select.empty().append('<option value="">Seleccionar cuenta...</option>');

                    if (response.success && response.categories && response.categories.length > 0) {
                        response.categories.forEach(cat => {
                            const optionText = `${cat.code} - ${cat.name}`;

                            $select.append($('<option>', {
                                value: cat.id,
                                text: optionText,
                                'data-name': cat.name
                            }));
                        });

                        $select.prop('disabled', false);
                        initializeExpenseCategorySelect();

                        if (response.budget_type === 'FREE_CONSUMPTION') {
                            const Toast = Swal.mixin({
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 4000,
                                timerProgressBar: true,
                                didOpen: (toast) => {
                                    toast.addEventListener('mouseenter', Swal.stopTimer)
                                    toast.addEventListener('mouseleave', Swal.resumeTimer)
                                }
                            });

                            Toast.fire({
                                icon: 'info',
                                title: 'Centro de consumo libre',
                                html: '<small>Todas las cuentas disponibles</small>'
                            });
                        }

                        resolve(true);
                    } else {
                        $select.append('<option value="">Sin cuentas disponibles</option>');
                        initializeExpenseCategorySelect();
                        showBudgetError(response);
                        resolve(false);
                    }
                },
                error: function(xhr) {
                    $select.empty().append('<option value="">Error al cargar</option>');
                    initializeExpenseCategorySelect();

                    if (xhr.status === 404 && xhr.responseJSON) {
                        showBudgetError(xhr.responseJSON);
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: 'Error al cargar las cuentas.',
                            icon: 'error',
                            confirmButtonText: 'Entendido'
                        });
                    }

                    resolve(false);
                }
            });
        });
    }

    /**
    * Mostrar alerta específica según el tipo de error de presupuesto
    */
    function showBudgetError(response) {
        const errorType = response.error_type;
        const currentYear = new Date().getFullYear();

        let title, html, icon;

        if (errorType === 'NO_BUDGET') {
            title = 'Presupuesto no configurado';
            html = `
                <div class="text-start">
                    <div class="alert alert-warning mb-3">
                        <i class="ti ti-alert-triangle me-2"></i>
                        <strong>No se puede crear la requisición</strong>
                    </div>

                    <p class="mb-3">${response.message}</p>

                    <div class="card bg-light border-0 mb-3">
                        <div class="card-body">
                            <h6 class="card-title text-primary mb-2">
                                <i class="ti ti-info-circle me-1"></i>¿Qué significa esto?
                            </h6>
                            <p class="small mb-0">
                                Todos los gastos deben estar dentro del <strong>plan financiero anual</strong>.
                                Sin un presupuesto existente, no es posible crear requisiciones.
                            </p>
                        </div>
                    </div>

                    <div class="card border-primary mb-0">
                        <div class="card-body">
                            <h6 class="card-title text-primary mb-2">
                                <i class="ti ti-checklist me-1"></i>¿Qué debo hacer?
                            </h6>
                            <ol class="small mb-0 ps-3">
                                <li class="mb-2">Contacta al <strong>responsable del centro de costo</strong></li>
                                <li class="mb-2">Solicita que configure el <strong>presupuesto anual ${currentYear}</strong></li>
                                <li>Una vez existente, podrás crear requisiciones</li>
                            </ol>
                        </div>
                    </div>
                </div>
            `;
            icon = 'warning';
        } else if (errorType === 'NO_CATEGORIES') {
            title = 'Distribución presupuestal incompleta';
            html = `
                <div class="text-start">
                    <div class="alert alert-info mb-3">
                        <i class="ti ti-info-circle me-2"></i>
                        <strong>El presupuesto existe, pero está incompleto</strong>
                    </div>

                    <p class="mb-3">${response.message}</p>

                    <div class="card bg-light border-0 mb-3">
                        <div class="card-body">
                            <h6 class="card-title text-primary mb-2">
                                <i class="ti ti-info-circle me-1"></i>¿Qué significa esto?
                            </h6>
                            <p class="small mb-0">
                                El presupuesto anual existe, pero no tiene <strong>distribuciones mensuales</strong>
                                asignadas a cuentas. Sin esto, no se pueden crear requisiciones.
                            </p>
                        </div>
                    </div>

                    <div class="card border-primary mb-0">
                        <div class="card-body">
                            <h6 class="card-title text-primary mb-2">
                                <i class="ti ti-checklist me-1"></i>¿Qué debo hacer?
                            </h6>
                            <ol class="small mb-0 ps-3">
                                <li class="mb-2">Contacta al <strong>responsable del centro de costo</strong></li>
                                <li class="mb-2">Solicita que configure las <strong>distribuciones mensuales</strong> del presupuesto</li>
                                <li>Debe asignar montos a las cuentas por mes</li>
                            </ol>
                        </div>
                    </div>
                </div>
            `;
            icon = 'info';
        } else {
            title = 'Sin cuentas disponibles';
            html = `
                <p>${response.message || 'No hay cuentas disponibles para este centro de costo.'}</p>
                <p class="text-muted small">${response.instructions || 'Contacta al administrador del sistema.'}</p>
            `;
            icon = 'warning';
        }

        Swal.fire({
            title: title,
            html: html,
            icon: icon,
            confirmButtonText: 'Entendido',
            confirmButtonColor: '#0d6efd',
            width: '600px',
            customClass: {
                popup: 'text-start'
            }
        });
    }

    // =====================================================
    // 6. GUARDAR PARTIDA -> Llamar a Livewire
    // =====================================================
    function initializeBudgetCedulaSelect() {
        initializeSearchableSelect($('#modal_budget_cedula'), 'Buscar subcuenta...', {
            dropdownParent: $(document.body),
            allowClear: false
        });
    }

    function enableBudgetCedulaSelect($cedula, selectedCedulaId = null) {
        $cedula.prop('disabled', false);
        initializeBudgetCedulaSelect();

        const pendingValue = selectedCedulaId || $cedula.data('pending-value');
        if (pendingValue) {
            $cedula.val(String(pendingValue)).trigger('change');
            $cedula.data('pending-value', null);
        }
    }

    function getRequisitionFiscalYear() {
        return {{ $isEditMode ? (int) ($requisition->created_at?->year ?? now()->year) : now()->year }};
    }

    function resetBudgetCedulaSelect(message = 'Selecciona primero una cuenta...') {
        const $cedula = $('#modal_budget_cedula');
        $cedula.val(null);
        $cedula.data('pending-value', null);
        $cedula.empty().append(`<option value="">${message}</option>`).prop('disabled', true);
        initializeBudgetCedulaSelect();
    }

    function loadBudgetCedulas(selectedCedulaId = null) {
        return new Promise((resolve) => {
            const costCenterId = $('#modal_cost_center_id').val();
            const categoryId = $('#modal_expense_category').val();
            const $cedula = $('#modal_budget_cedula');

            if (!costCenterId || !categoryId) {
                resetBudgetCedulaSelect();
                resolve(false);
                return;
            }

            $cedula.prop('disabled', true)
                .empty()
                .append('<option value="">Cargando subcuentas...</option>');
            initializeBudgetCedulaSelect();

            $.ajax({
                url: '{{ route("expense-categories.cedulas-by-cost-center") }}',
                type: 'GET',
                dataType: 'json',
                data: {
                    cost_center_id: costCenterId,
                    expense_category_id: categoryId,
                    fiscal_year: getRequisitionFiscalYear()
                },
                success: function(response) {
                    $cedula.empty().append('<option value="">Seleccionar subcuenta...</option>');

                    if (response.success && response.cedulas && response.cedulas.length > 0) {
                        response.cedulas.forEach(cedula => {
                            $cedula.append($('<option>', {
                                value: cedula.id,
                                text: cedula.name
                            }));
                        });

                        enableBudgetCedulaSelect($cedula, selectedCedulaId);

                        resolve(true);
                        return;
                    }

                    resetBudgetCedulaSelect('No hay subcuentas configuradas para esta cuenta.');
                    resolve(false);
                },
                error: function(xhr) {
                    resetBudgetCedulaSelect('No se pudieron cargar las subcuentas.');
                    console.error('Error al cargar subcuentas:', xhr);
                    resolve(false);
                }
            });
        });
    }

    initializeBudgetCedulaSelect();
    resetBudgetCedulaSelect();

    $(document)
        .off('change.requisitionExpenseCategory', '#modal_expense_category')
        .on('change.requisitionExpenseCategory', '#modal_expense_category', function() {
            if ($(this).val()) {
                loadBudgetCedulas();
                return;
            }

            resetBudgetCedulaSelect();
        });

    $(document)
        .off('select2:clear.requisitionExpenseCategory', '#modal_expense_category')
        .on('select2:clear.requisitionExpenseCategory', '#modal_expense_category', function() {
            resetBudgetCedulaSelect();
        });

    openItemModal = function() {
        editingIndex = null;

        $('#itemModalTitle').text('Agregar Partida');
        document.getElementById('itemForm').reset();
        $('#item_index').val('');
        setItemSaveButtonMode(false);
        $('#budgetAlert').hide();
        resetBudgetCedulaSelect();

        renderModalCostCenters('reset');
        $('#modal_expense_category').empty()
            .append('<option value="">Seleccione primero un centro de costo...</option>')
            .prop('disabled', true);
        initializeExpenseCategorySelect();
        $('#modal_product_id').empty().append('<option value="">Buscar producto del catálogo...</option>');

        setTimeout(setItemModalInitialState, 100);
    }

    openItemModalForEdit = function(index, item) {
        editingIndex = index;

        $('#itemModalTitle').text('Editar Partida');
        $('#item_index').val(index);
        setItemSaveButtonMode(true);
        $('#budgetAlert').hide();
        resetBudgetCedulaSelect();

        $('#modal_cost_center_id').data('pending-value', item.cost_center_id || null);
        renderModalCostCenters('edit');

        setTimeout(() => {
            const $product = $('#modal_product_id');
            const classification = {
                expense_category_id: item.expense_category_id,
                expense_category_name: item.expense_category_name,
                budget_cedula_id: item.budget_cedula_id,
                budget_cedula_name: item.budget_cedula_name,
            };
            const option = new Option(item.product_name, item.product_id, true, true);
            $(option).data('budget-classification', classification);
            $product.append(option).trigger('change');
            applySelectedProductClassification();
            $('#modal_description').val(item.description);
            $('#modal_quantity').val(item.quantity);
            $('#modal_unit').val(item.unit);
            $('#modal_budget_cedula').data('pending-value', item.budget_cedula_id || null);
            $('#modal_expense_category').val(item.expense_category_id).trigger('change');
            $('#modal_notes').val(item.notes || '');
            setTimeout(setItemModalInitialState, 200);
        }, 600);

    }

    $(document).off('click.requisitionSaveItem', '#btnSaveItem').on('click.requisitionSaveItem', '#btnSaveItem', async function() {
        const $saveButton = $(this);

        if ($saveButton.prop('disabled')) {
            return;
        }

        const $costCenter = $('#modal_cost_center_id');
        const selectedCostCenterData = $costCenter.data('select2')
            ? ($costCenter.select2('data')[0] || {})
            : {};
        const costCenterId = $costCenter.val() || selectedCostCenterData.id || '';
        const $selectedCc = $costCenter.find('option:selected');
        const costCenterName = $selectedCc.data('name') || '';
        const purchaseType   = $selectedCc.data('purchase-type') || '';
        const productId      = $('#modal_product_id').val();
        const quantity       = parseFloat($('#modal_quantity').val());
        const classification = selectedProductClassification();
        const categoryId     = classification?.expense_category_id || '';
        const budgetCedulaId = classification?.budget_cedula_id || '';

        if (!costCenterId) {
            Swal.fire('Campo requerido', 'Selecciona el centro de costo de esta partida.', 'error');
            return;
        }

        if (!productId) {
            Swal.fire('Error', 'Selecciona un producto del catálogo (RN-001).', 'error');
            return;
        }

        if (!quantity || quantity <= 0) {
            Swal.fire('Error', 'La cantidad debe ser mayor a cero.', 'error');
            return;
        }

        if (!classification) {
            Swal.fire('Error', 'El producto no tiene subcuenta asignada.', 'error');
            return;
        }

        const selectedProduct = $('#modal_product_id').data('requisition-product') || {};
        const minQty = parseFloat(selectedProduct.minimum_quantity);
        const maxQty = parseFloat(selectedProduct.maximum_quantity);
        const unit = selectedProduct.unit_of_measure || 'PZA';

        if (minQty && quantity < minQty) {
            Swal.fire({
                icon: 'error',
                title: 'Cantidad insuficiente',
                text: `La cantidad mínima para este producto es ${minQty} ${unit}`
            });
            return;
        }

        if (maxQty && quantity > maxQty) {
            Swal.fire({
                icon: 'error',
                title: 'Cantidad excedida',
                text: `La cantidad máxima permitida es ${maxQty} ${unit}`
            });
            return;
        }

        if (modalAttachmentUploading) {
            Swal.fire('Archivo en carga', 'Espera a que termine de cargarse el documento antes de guardar la partida.', 'info');
            return;
        }

        if ($('#modal_attach_document').is(':checked') && !modalAttachmentKey) {
            Swal.fire('Documento requerido', 'Selecciona un documento para esta partida o desmarca la opción de adjuntar.', 'info');
            return;
        }

        const itemData = {
            product_id:            productId,
            product_name:          $('#modal_product_id option:selected').text(),
            description:           $('#modal_description').val(),
            quantity:              quantity,
            unit:                  $('#modal_unit').val(),
            expense_category_id:   categoryId,
            expense_category_name: classification.expense_category_name,
            budget_cedula_id:      budgetCedulaId,
            budget_cedula_name:    classification.budget_cedula_name,
            account_name:          classification.account_name || '',
            subaccount_name:       classification.subaccount_name || '',
            cost_center_id:        costCenterId,
            cost_center_name:      costCenterName,
            purchase_type:         purchaseType,
            notes:                 $('#modal_notes').val() || '',
            attachment_key:        $('#modal_attach_document').is(':checked') ? modalAttachmentKey : null,
        };

        const editIndex = $('#item_index').val();
        const isNewItem = editIndex === '' || editIndex === null;
        const wire = getRequisitionWire();

        if (!wire) {
            Swal.fire('Error', 'No se pudo conectar con el formulario para guardar la partida.', 'error');
            return;
        }

        $saveButton.prop('disabled', true);

        try {
            if (editIndex !== '' && editIndex !== null) {
                await wire.$call('updateItem', parseInt(editIndex), itemData);
            } else {
                await wire.$call('addItem', itemData);
            }
        } catch (error) {
            console.error('Error al guardar partida:', error);
            Swal.fire('Error', 'No se pudo guardar la partida.', 'error');
        } finally {
            $saveButton.prop('disabled', false);
        }
    });

    // =====================================================
    // 7. LISTENERS DE EVENTOS DE LIVEWIRE
    // =====================================================
    Livewire.on('item-added', (event) => {
        renderRequisitionItems(true, event.items);
        showItemAddedFeedback(event.item?.product_name || 'La partida');
        resetItemFormForNextItem(true);

        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });

        Toast.fire({
            icon: 'success',
            title: 'Partida agregada'
        });
    });

    Livewire.on('item-updated', (event) => {
        renderRequisitionItems(false, event.items);
        resetItemFormForNextItem(true);

        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });

        Toast.fire({
            icon: 'success',
            title: 'Partida actualizada'
        });
    });

    Livewire.on('item-removed', (event) => {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });

        Toast.fire({
            icon: 'success',
            title: 'Partida eliminada'
        });
    });

    Livewire.on('item-error', (event) => {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 4000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });

        Toast.fire({
            icon: 'error',
            title: event.message || 'Ocurrió un error al procesar la partida'
        });
    });

    Livewire.on('company-context-changed', (event) => {
        ensureItemFormVisible();

        if (!event.itemsCleared) {
            return;
        }

        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });

        Toast.fire({
            icon: 'info',
            title: 'Se reiniciaron las partidas por cambio de compañía'
        });
    });

    document.addEventListener('livewire:morph.updated', ensureItemFormVisible);
    document.addEventListener('livewire:navigated', ensureItemFormVisible);

    // =====================================================
    // 8. LISTENERS ADICIONALES DE VALIDACIÓN Y GUARDADO
    // =====================================================
    Livewire.on('validation-error', (event) => {
        hideRequisitionSubmitProgress();
        Swal.fire({
            icon: 'error',
            title: 'Validación',
            text: event.message || 'Por favor, completa todos los campos requeridos'
        });
    });

    Livewire.on('save-error', (event) => {
        hideRequisitionSubmitProgress();
        Swal.fire({
            icon: 'error',
            title: 'Error al guardar',
            text: event.message || 'Ocurrió un error al guardar la requisición'
        });
    });
});
</script>
@endpush
