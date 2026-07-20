@extends('layouts.zircos')

@php
    use Illuminate\Support\Facades\Storage;

    // Mapa de etiquetas amigables
    $labels = [
        'constancia_fiscal'        => 'Constancia de situación fiscal',
        'comprobante_domicilio'    => 'Comprobante de domicilio',
        'caratula_bancaria'        => 'Carátula bancaria',
        'opinion_sat'              => 'Opinión positiva del SAT',
        'acta_constitutiva'        => 'Acta constitutiva',
        'poder_legal'              => 'Poder legal',
        'identificacion_oficial'   => 'Identificación oficial',
        'opinion_imss'             => 'Opinión del IMSS',
        'opinion_infonavit'        => 'Opinión del INFONAVIT',
        'solicitud_alta_proveedor' => 'Solicitud de alta de proveedor',
        'repse'                    => 'REPSE',
        'acta_confidencialidad'    => 'Acta de confidencialidad',
        'curso_induccion'          => 'Curso de inducción',
    ];

    // Helper badge
    function badge_status($s) {
        return match ($s) {
            'accepted'       => '<span class="badge bg-success">Aprobado</span>',
            'rejected'       => '<span class="badge bg-danger">Rechazado</span>',
            'pending_review' => '<span class="badge bg-warning text-dark">En revisión</span>',
            default          => '<span class="badge bg-secondary">—</span>',
        };
    }
@endphp

@section('title','Revisión de documentos')
@push('styles')
<style>
    .nav-workmodes .nav-link { font-weight: 600; }
    .stat-card .value { font-size: 1.35rem; font-weight: 700; }
    .stat-card .label { font-size: .85rem; color: #6c757d; }
    .table td, .table th { vertical-align: middle; }
    .chip { display:inline-block; padding:.15rem .5rem; border-radius:999px; font-size:.75rem; }
    .chip.ok { background:#e9f7ef; color:#198754; }
    .chip.bad { background:#fdecea; color:#dc3545; }
    .chip.wait { background:#fff3cd; color:#856404; }
    /* SweetAlert2 debe quedar por encima del modal de Bootstrap */
    .swal2-container { z-index: 99999 !important; }
</style>
@endpush

@section('page.title','Revisión de documentos')
@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
    <li class="breadcrumb-item">Administración</li>
    <li class="breadcrumb-item active">Revisión</li>
@endsection

@section('content')

    {{-- KPIs superiores (solo display) --}}
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <div class="value" id="kpiPendientes">{{ $kpiPendientes }}</div>
                        <div class="label">Pendientes de revisión</div>
                    </div>
                    <i class="ti ti-hourglass-high fs-2 text-warning"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <div class="value" id="kpiAprobadosHoy">{{ $kpiAprobadosHoy }}</div>
                        <div class="label">Aprobados hoy</div>
                    </div>
                    <i class="ti ti-checks fs-2 text-success"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <div class="value" id="kpiRechazadosHoy">{{ $kpiRechazadosHoy }}</div>
                        <div class="label">Rechazados hoy</div>
                    </div>
                    <i class="ti ti-x fs-2 text-danger"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabs de modos de trabajo --}}
    <ul class="nav nav-pills nav-workmodes mb-3" id="workModes" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link {{ ($activeTab ?? 'bandeja') === 'bandeja' ? 'active' : '' }}" id="bandeja-tab" href="{{ route('admin.review.index') }}" role="tab" aria-controls="bandejaPane" aria-selected="{{ ($activeTab ?? 'bandeja') === 'bandeja' ? 'true' : 'false' }}">
                <i class="ti ti-inbox me-1"></i> Bandeja (documentos)
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link {{ ($activeTab ?? 'bandeja') === 'proveedores' ? 'active' : '' }}" id="proveedores-tab" href="{{ route('admin.review.index', ['tab' => 'proveedores']) }}" role="tab" aria-controls="proveedoresPane" aria-selected="{{ ($activeTab ?? 'bandeja') === 'proveedores' ? 'true' : 'false' }}">
                <i class="ti ti-users me-1"></i> Proveedores
            </a>
        </li>
    </ul>

    <div class="tab-content" id="workModesContent">

        {{-- PANE 1: BANDEJA --}}
        <div class="tab-pane fade {{ ($activeTab ?? 'bandeja') === 'bandeja' ? 'show active' : '' }}" id="bandejaPane" role="tabpanel" aria-labelledby="bandeja-tab" tabindex="0">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">Documentos pendientes de revisión</h5>
                    <span class="text-muted small"></span>
                </div>
                <div class="card-body">
                    <table class="table-bordered table-hover w-100 table">
                        <thead class="table-light">
                            <tr>
                                <th>Proveedor</th>
                                <th>RFC</th>
                                <th>Tipo de documento</th>
                                <th>Subido por</th>
                                <th>Fecha carga</th>
                                <th>Validacion</th>
                                <th>Status</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($pendingDocs as $doc)
                                @php
                                    $prov = $doc->supplier;
                                    $uploader = $doc->uploader;
                                    $type        = $doc->doc_type;
                                    $label = $labels[$doc->doc_type] ?? ucfirst(str_replace('_',' ',$doc->doc_type));
                                    $url = Storage::disk('public')->url($doc->path_file);
                                    // Construimos la URL de retroalimentación para este documento
                                    $feedbackUrl = $prov ? route('documents.suppliers.feedback', [
                                        'supplier' => $prov->id,
                                        'type'     => $type,
                                        'document' => $doc->id, // o null si quieres mantenerlo opcional
                                    ]) : '';
                                @endphp
                                <tr>
                                    <td>{{ $prov?->company_name ?? '—' }}</td>
                                    <td>{{ $prov?->rfc ?? '—' }}</td>
                                    <td><span class="badge bg-info">{{ $label }}</span></td>
                                    <td>{{ $uploader?->name ?? '—' }}</td>
                                    <td>{{ optional($doc->uploaded_at ?? $doc->created_at)->format('Y-m-d H:i') }}</td>
                                    <td>
                                        @php($validation = $doc->issue_date_extraction_data ?? [])
                                        @if(($validation['status'] ?? null) === 'extracted')
                                            @if(($validation['rfc_matches_supplier'] ?? true) === false || ($validation['compliance_is_positive'] ?? true) === false)
                                                <span class="badge bg-danger">Inconsistente</span>
                                            @else
                                                <span class="badge bg-success">
                                                    {{ $validation['compliance_status'] ?? 'Validado' }}
                                                </span>
                                            @endif
                                        @elseif($doc->documentType?->validity_source === 'qr')
                                            <span class="badge bg-warning text-dark">Pendiente QR</span>
                                        @else
                                            <span class="text-muted">No aplica</span>
                                        @endif
                                    </td>
                                    <td>{!! badge_status($doc->status) !!}</td>
                                    <td>
                                        <div class="d-flex justify-content-end gap-1">
                                            <button type="button"
                                                class="btn btn-sm btn-outline-primary js-review-doc"
                                                data-file-url="{{ $url }}"
                                                data-label="{{ $label }}"
                                                data-accept-url="{{ route('admin.review.documents.accept', $doc) }}"
                                                data-reject-url="{{ route('admin.review.documents.reject', $doc) }}"
                                                data-feedback-url="{{ $feedbackUrl }}"
                                                data-supplier="{{ $prov?->id }}"
                                                data-supplier-rfc="{{ $prov?->rfc }}"
                                                data-type="{{ $type }}"
                                                data-doc="{{ $doc->id ?? '' }}"
                                                data-periodic="{{ $doc->documentType?->renewal_mode === 'periodic' ? '1' : '0' }}"
                                                data-issued-at="{{ $doc->issued_at?->format('Y-m-d') }}"
                                                data-validation="{{ e(json_encode($doc->issue_date_extraction_data ?? [], JSON_UNESCAPED_UNICODE)) }}"
                                                data-validation-rfc="{{ $validation['rfc'] ?? '' }}"
                                                data-validation-status="{{ $validation['compliance_status'] ?? '' }}"
                                                data-validation-issued-at="{{ $validation['issued_at'] ?? $doc->issued_at?->format('Y-m-d') }}"
                                                data-validation-method="{{ $validation['validation_method'] ?? '' }}"
                                                data-validation-rfc-match="{{ array_key_exists('rfc_matches_supplier', $validation) ? ($validation['rfc_matches_supplier'] ? '1' : '0') : '' }}"
                                                data-validation-positive="{{ array_key_exists('compliance_is_positive', $validation) ? ($validation['compliance_is_positive'] ? '1' : '0') : '' }}"
                                                data-revalidate-url=""
                                                title="Revisar">
                                                <i class="ti ti-eye me-1"></i> Revisar
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-5">
                                        <div class="d-flex flex-column align-items-center justify-content-center text-muted">
                                            <i class="ti ti-inbox-off fs-1 mb-2"></i>
                                            <h6 class="mb-1">Sin documentos pendientes</h6>
                                            <p class="mb-0 small">Por ahora no hay archivos que requieran revisión.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="text-muted small mt-2">
                        <i class="ti ti-info-circle me-1"></i>
                        En esta bandeja puedes <strong>aprobar o rechazar</strong> documentos cargados por los proveedores.
                        Está pensada para revisión rápida, documento por documento.
                    </div>
                </div>
            </div>
        </div>

        {{-- PANE 2: PROVEEDORES --}}
        <div class="tab-pane fade {{ ($activeTab ?? 'bandeja') === 'proveedores' ? 'show active' : '' }}" id="proveedoresPane" role="tabpanel" aria-labelledby="proveedores-tab" tabindex="0">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">Estado por proveedor</h5>
                    <span class="text-muted small"></span>
                </div>
                <div class="card-body">
                    <table class="table-bordered table-hover w-100 table">
                        <thead class="table-light">
                            <tr>
                                <th>Proveedor</th>
                                <th>RFC</th>
                                <th>Avance</th>
                                <th>Aprobados</th>
                                <th>Rechazados</th>
                                <th>Pendientes</th>
                                <th>Última actividad</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($suppliersSummary as $row)
                                <tr>
                                    <td>{{ $row['supplier']?->company_name ?? '—' }}</td>
                                    <td>{{ $row['supplier']?->rfc ?? '—' }}</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress" style="width:140px;height:10px;">
                                                <div class="progress-bar" role="progressbar"
                                                     style="width: {{ $row['progress_percent'] ?? 0 }}%;" aria-valuenow="{{ $row['progress_percent'] ?? 0 }}" aria-valuemin="0" aria-valuemax="100">
                                                </div>
                                            </div>
                                            <span class="small">{{ $row['progress_percent'] ?? 0 }}%</span>
                                        </div>
                                    </td>
                                    <td><span class="chip ok">{{ $row['accepted'] ?? 0 }}</span></td>
                                    <td><span class="chip bad">{{ $row['rejected'] ?? 0 }}</span></td>
                                    <td><span class="chip wait">{{ $row['pending'] ?? 0 }}</span></td>
                                    <td>{{ !empty($row['last_activity_at']) ? \Illuminate\Support\Carbon::parse($row['last_activity_at'])->format('Y-m-d H:i') : '—' }}</td>
                                    <td>
                                        @if($row['supplier'] ?? null)
                                            <a href="{{ route('admin.review.suppliers.show', $row['supplier']->id) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="ti ti-eye mx-1"></i> Ver detalles
                                            </a>
                                        @else
                                            <span class="text-muted small">Sin proveedor</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted py-4">Sin proveedores en seguimiento.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="text-muted small mt-2">
                        <i class="ti ti-info-circle me-1"></i>
                        Esta vista muestra el <strong>avance por proveedor</strong>.
                        Aquí no se aprueban ni rechazan documentos, solo es para seguimiento y auditoría.
                    </div>
                </div>
            </div>
        </div>

    </div>
{{-- Modal de revisión de documento --}}
<div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reviewModalLabel">
                    <i class="ti ti-file-search me-2"></i>
                    <span id="reviewModalDocLabel">Revisar documento</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body p-0">
                {{-- Visor del documento --}}
                <div id="reviewViewer">
                    <div id="qrValidationSummary" class="border-bottom bg-light p-3 small d-none"></div>
                    <iframe id="reviewModalFrame" src="" style="width:100%;height:72vh;border:0;" allowfullscreen></iframe>
                </div>

                {{-- Panel de rechazo (inline) --}}
                <div id="reviewRejectPanel" class="d-none p-4">
                    <h6 class="mb-3">
                        <i class="ti ti-alert-triangle me-2 text-danger"></i>Motivo de rechazo
                    </h6>
                    <textarea id="rejectReasonInput" class="form-control" rows="6"
                        placeholder="Escribe el motivo (mín. 5 caracteres)…"></textarea>
                    <div id="rejectReasonError" class="text-danger small mt-1 d-none">
                        El motivo es obligatorio (mín. 5 caracteres).
                    </div>
                </div>

                {{-- Panel de retroalimentación (inline) --}}
                <div id="reviewFeedbackPanel" class="d-none p-4">
                    <h6 class="mb-3">
                        <i class="ti ti-message-dots me-2 text-info"></i>Retroalimentación para el proveedor
                    </h6>
                    <p class="small text-muted mb-2">
                        Documento: <strong id="feedbackDocLabel"></strong>
                    </p>
                    <textarea id="feedbackMessageInput" class="form-control" rows="6"
                        placeholder="Escribe el mensaje (mín. 5 caracteres)…"></textarea>
                    <div id="feedbackMessageError" class="text-danger small mt-1 d-none">
                        El mensaje es obligatorio (mín. 5 caracteres).
                    </div>
                    <div class="form-text mt-1">Este mensaje se enviará por correo al contacto del proveedor.</div>
                </div>

                {{-- Panel de confirmación de aprobación (inline) --}}
                <div id="reviewAcceptPanel" class="d-none p-4">
                    <div class="mx-auto text-start" style="max-width: 760px;">
                        <h6 class="mb-3">
                            <i class="ti ti-checkup-list me-2 text-success"></i>Confirmación de validación
                        </h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">RFC detectado</label>
                                <input id="validatedRfcInput" type="text" class="form-control text-uppercase" maxlength="13">
                                <div class="form-text">Debe coincidir con el RFC del proveedor.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Validación</label>
                                <select id="complianceStatusInput" class="form-select">
                                    <option value="POSITIVA">POSITIVA</option>
                                    <option value="NEGATIVA">NEGATIVA</option>
                                    <option value="SIN OPINION">SIN OPINIÓN</option>
                                    <option value="NO APLICA">NO APLICA</option>
                                </select>
                                <div class="form-text">Para opiniones de cumplimiento debe ser POSITIVA.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Fecha de origen</label>
                                <input id="documentIssuedAt" type="date" class="form-control">
                                <div class="form-text">Fecha de emisión/oficio del documento.</div>
                            </div>
                        </div>
                        <div id="acceptValidationError" class="alert alert-danger d-none mt-3 mb-0 small"></div>
                        <p class="text-muted small mt-3 mb-0">Puedes corregir estos campos si la lectura automática u OCR detectó algo mal.</p>
                    </div>
                </div>
            </div>

            {{-- Footer: vista normal --}}
            <div class="modal-footer justify-content-between" id="reviewFooterDefault">
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="ti ti-arrow-back me-1"></i> Cerrar
                    </button>
                    <button type="button" class="btn btn-outline-info js-show-feedback">
                        <i class="ti ti-message-dots me-1"></i> Retroalimentación
                    </button>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-danger js-show-reject">
                        <i class="ti ti-x me-1"></i> Rechazar
                    </button>
                    <button type="button" class="btn btn-success js-show-accept">
                        <i class="ti ti-check me-1"></i> Validar y aceptar
                    </button>
                </div>
            </div>

            {{-- Footer: rechazo --}}
            <div class="modal-footer justify-content-between d-none" id="reviewFooterReject">
                <button type="button" class="btn btn-outline-secondary js-cancel-subform">
                    <i class="ti ti-arrow-back me-1"></i> Cancelar
                </button>
                <button type="button" class="btn btn-danger js-do-reject">
                    <i class="ti ti-x me-1"></i> Confirmar rechazo
                </button>
            </div>

            {{-- Footer: retroalimentación --}}
            <div class="modal-footer justify-content-between d-none" id="reviewFooterFeedback">
                <button type="button" class="btn btn-outline-secondary js-cancel-subform">
                    <i class="ti ti-arrow-back me-1"></i> Cancelar
                </button>
                <button type="button" class="btn btn-primary js-do-feedback">
                    <i class="ti ti-send me-1"></i> Enviar retroalimentación
                </button>
            </div>

            {{-- Footer: confirmación de aprobación --}}
            <div class="modal-footer justify-content-between d-none" id="reviewFooterAccept">
                <button type="button" class="btn btn-outline-secondary js-cancel-subform">
                    <i class="ti ti-arrow-back me-1"></i> Cancelar
                </button>
                <button type="button" class="btn btn-success js-do-accept">
                    <i class="ti ti-check me-1"></i> Sí, aprobar
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$.ajaxSetup({
    headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')}
});

// Toast SweetAlert2 (solo se usa FUERA del modal, para notificaciones de resultado)
const toast = (icon, title, text) => Swal.fire({
    icon, title, text,
    toast: true,
    position: 'top-end',
    timer: 2000,
    showConfirmButton: false,
    timerProgressBar: true,
});

$(function () {
    // Estado del modal
    let $activeRow = null;

    function showPanel(panelId, footerId) {
        $('#reviewViewer, #reviewRejectPanel, #reviewFeedbackPanel, #reviewAcceptPanel').addClass('d-none');
        $('#reviewFooterDefault, #reviewFooterReject, #reviewFooterFeedback, #reviewFooterAccept').addClass('d-none');
        $('#' + panelId).removeClass('d-none');
        $('#' + footerId).removeClass('d-none');
    }

    function resetModal() {
        showPanel('reviewViewer', 'reviewFooterDefault');
        $('#rejectReasonInput').val('');
        $('#rejectReasonError').addClass('d-none');
        $('#feedbackMessageInput').val('');
        $('#feedbackMessageError').addClass('d-none');
        $('#qrValidationSummary').addClass('d-none').empty();
        $('#acceptValidationError').addClass('d-none').empty();
        $('#validatedRfcInput').val('');
        $('#complianceStatusInput').val('NO APLICA');
        $('#documentIssuedAt').val('');
    }

    // ── Abrir modal ──────────────────────────────────────────────────────────
    $(document).on('click', '.js-review-doc', function () {
        $activeRow = $(this).closest('tr');
        const btn = $(this);

        resetModal();
        $('#reviewModalFrame').attr('src', btn.data('file-url'));
        $('#reviewModalDocLabel').text(btn.data('label'));
        $('#feedbackDocLabel').text(btn.data('label'));
        $('#reviewModal')
            .data('accept-url',   btn.data('accept-url'))
            .data('reject-url',   btn.data('reject-url'))
            .data('feedback-url', btn.data('feedback-url'))
            .data('type',         btn.data('type'))
            .data('doc',          btn.data('doc'))
            .data('supplier-rfc', btn.data('supplier-rfc') || '');
        $('#reviewModal').data('periodic', btn.data('periodic') === 1);
        $('#reviewModal').data('issued-at', btn.data('issued-at') || '');
        $('#reviewModal').data('revalidate-url', btn.data('revalidate-url') || '');
        let validation = btn.data('validation') || {};
        if (typeof validation === 'string') {
            try {
                validation = JSON.parse(validation);
            } catch (error) {
                validation = {};
            }
        }
        validation.rfc = validation.rfc || btn.data('validation-rfc') || '';
        validation.compliance_status = validation.compliance_status || btn.data('validation-status') || '';
        validation.issued_at = validation.issued_at || btn.data('validation-issued-at') || btn.data('issued-at') || '';
        validation.validation_method = validation.validation_method || btn.data('validation-method') || '';
        const rfcMatchAttr = btn.attr('data-validation-rfc-match');
        const positiveAttr = btn.attr('data-validation-positive');
        if (validation.rfc_matches_supplier === undefined && rfcMatchAttr !== undefined && rfcMatchAttr !== '') {
            validation.rfc_matches_supplier = rfcMatchAttr === '1';
        }
        if (validation.compliance_is_positive === undefined && positiveAttr !== undefined && positiveAttr !== '') {
            validation.compliance_is_positive = positiveAttr === '1';
        }
        $('#reviewModal').data('validation', validation);
        if (Object.keys(validation).length || btn.data('supplier-rfc')) {
            const rfc = validation.rfc || 'No identificado';
            const date = validation.issued_at || btn.data('issued-at') || 'No identificada';
            const opinion = validation.compliance_status || 'No aplica';
            const rfcState = validation.rfc_matches_supplier === false ? 'No coincide' : (validation.rfc_matches_supplier === true ? 'Coincide' : 'Pendiente');
            const opinionState = validation.compliance_is_positive === false ? 'No positiva' : opinion;
            const sourceLabels = {
                infonavit_pdftotext: 'PDF',
                infonavit_ocr: 'OCR',
                imss_qr_payload: 'QR IMSS',
                sat_csf_cadena_original: 'QR SAT',
                sat_csf_qr_payload: 'QR SAT',
            };
            const source = sourceLabels[validation.validation_method] || (validation.validation_method ? validation.validation_method : 'Automatico');
            const opinionClass = validation.compliance_is_positive === false ? 'bg-danger' : (opinion === 'No aplica' ? 'bg-secondary' : 'bg-success');
            const rfcClass = validation.rfc_matches_supplier === false ? 'bg-danger' : (validation.rfc_matches_supplier === true ? 'bg-success' : 'bg-warning text-dark');
            $('#qrValidationSummary')
                .removeClass('d-none')
                .html(`
                    <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-2">
                        <strong class="me-lg-2">Resumen de validacion</strong>
                        <span class="badge ${rfcClass}">RFC ${$('<div>').text(rfcState).html()}</span>
                        <span class="badge ${opinionClass}">${$('<div>').text(opinionState).html()}</span>
                        <span class="badge bg-info text-dark">${$('<div>').text(source).html()}</span>
                        <span class="text-muted ms-lg-2">RFC detectado: <strong>${$('<div>').text(rfc).html()}</strong></span>
                        <span class="text-muted">Fecha origen: <strong>${$('<div>').text(date).html()}</strong></span>
                        <span class="text-muted">Proveedor: <strong>${$('<div>').text(btn.data('supplier-rfc') || 'Sin RFC').html()}</strong></span>
                    </div>
                `);
            if (validation.status === 'pending_external_validation' && btn.data('revalidate-url')) {
                $('#qrValidationSummary').append('<button type="button" class="btn btn-sm btn-outline-primary ms-3 js-revalidate-infonavit"><i class="ti ti-refresh me-1"></i>Consultar INFONAVIT</button>');
            }
        }

        bootstrap.Modal.getOrCreateInstance(document.getElementById('reviewModal')).show();
    });

    $(document).on('click', '.js-revalidate-infonavit', function () {
        const $button = $(this).prop('disabled', true).text('Consultando...');
        $.post($('#reviewModal').data('revalidate-url')).done(response => {
            if (!response.ok) {
                toast('warning', 'Consulta pendiente', response.message);
                return;
            }
            $('#reviewModal').data('issued-at', response.issued_at);
            $('#documentIssuedAt').val(response.issued_at);
            $('#qrValidationSummary').html(
                `<strong>Validacion automatica</strong><span class="ms-3">RFC: ${$('<div>').text(response.validation.rfc).html()} (${response.validation.rfc_matches_supplier ? 'Coincide' : 'No coincide'})</span><span class="ms-3">Emision: ${response.issued_at}</span><span class="ms-3">Opinion: ${$('<div>').text(response.validation.compliance_status).html()}</span>`
            );
            toast('success', 'Consulta completada');
        }).fail(xhr => {
            toast('error', 'No fue posible consultar', xhr.responseJSON?.message || 'Intenta nuevamente.');
        }).always(() => {
            $button.prop('disabled', false).html('<i class="ti ti-refresh me-1"></i>Consultar INFONAVIT');
        });
    });

    document.getElementById('reviewModal').addEventListener('hidden.bs.modal', () => {
        $('#reviewModalFrame').attr('src', '');
        $activeRow = null;
    });

    // ── Navegar a subpaneles ─────────────────────────────────────────────────
    $(document).on('click', '.js-show-reject', () => {
        showPanel('reviewRejectPanel', 'reviewFooterReject');
        $('#rejectReasonInput').trigger('focus');
    });

    $(document).on('click', '.js-show-feedback', () => {
        showPanel('reviewFeedbackPanel', 'reviewFooterFeedback');
        $('#feedbackMessageInput').trigger('focus');
    });

    $(document).on('click', '.js-show-accept', () => {
        const modal = $('#reviewModal');
        const validation = modal.data('validation') || {};
        const type = String(modal.data('type') || '');
        const detectedRfc = validation.rfc || modal.data('supplier-rfc') || '';
        const detectedStatus = validation.compliance_status || (type.startsWith('opinion_') ? 'POSITIVA' : 'NO APLICA');

        $('#validatedRfcInput').val(detectedRfc);
        $('#complianceStatusInput').val(detectedStatus);
        $('#documentIssuedAt').val(validation.issued_at || modal.data('issued-at'));
        $('#acceptValidationError').addClass('d-none').empty();
        showPanel('reviewAcceptPanel', 'reviewFooterAccept');
    });

    $(document).on('click', '.js-cancel-subform', () => {
        showPanel('reviewViewer', 'reviewFooterDefault');
    });

    // ── Confirmar aprobación ─────────────────────────────────────────────────
    // Confirmar aprobacion
    $(document).on('click', '.js-do-accept', function () {
        const modal = $('#reviewModal');
        const url = modal.data('accept-url');
        const $btn = $(this).prop('disabled', true).text('Aprobando...');
        const restoreButton = () => $btn.prop('disabled', false).html('<i class="ti ti-check me-1"></i> Si, aprobar');
        const showAcceptError = (message) => {
            $('#acceptValidationError').removeClass('d-none').text(message);
            restoreButton();
        };

        const periodic = modal.data('periodic');
        const issuedAt = $('#documentIssuedAt').val();
        const validatedRfc = $('#validatedRfcInput').val().trim().toUpperCase();
        const complianceStatus = $('#complianceStatusInput').val();
        const supplierRfc = String(modal.data('supplier-rfc') || '').trim().toUpperCase();
        const type = String(modal.data('type') || '');
        $('#acceptValidationError').addClass('d-none').empty();

        if (supplierRfc && validatedRfc && supplierRfc !== validatedRfc) {
            showAcceptError('El RFC confirmado no coincide con el RFC del proveedor.');
            return;
        }

        if (type.startsWith('opinion_') && complianceStatus !== 'POSITIVA') {
            showAcceptError('Para aprobar una opinion de cumplimiento, la validacion debe ser POSITIVA.');
            return;
        }

        if (periodic && !issuedAt) {
            showAcceptError('Confirma la fecha de origen mostrada en el documento.');
            return;
        }

        $.post(url, {
            issued_at: issuedAt,
            validated_rfc: validatedRfc,
            compliance_status: complianceStatus,
        }).done(() => {
            bootstrap.Modal.getInstance(document.getElementById('reviewModal')).hide();
            if ($activeRow) {
                $activeRow.find('td:nth-child(6)').html('<span class="badge bg-success">Aprobado</span>');
                $activeRow.find('td:nth-child(7)').html('<span class="badge bg-success">Aprobado</span>');
            }
            $('#kpiPendientes').text(Math.max(0, parseInt($('#kpiPendientes').text()) - 1));
            $('#kpiAprobadosHoy').text(parseInt($('#kpiAprobadosHoy').text()) + 1);
            toast('success', 'Aprobado');
        }).fail(xhr => {
            restoreButton();
            let msg = 'No se pudo aprobar.';
            if (xhr.status === 422 && xhr.responseJSON?.errors) {
                msg = Object.values(xhr.responseJSON.errors).flat().join('\n');
                $('#acceptValidationError').removeClass('d-none').text(msg);
            }
            toast('error', 'Error', msg);
            console.error(xhr?.responseText || xhr);
        });
    });

    // ── Confirmar rechazo ────────────────────────────────────────────────────
    $(document).on('click', '.js-do-reject', function () {
        const reason = $('#rejectReasonInput').val().trim();
        if (reason.length < 5) {
            $('#rejectReasonError').removeClass('d-none');
            $('#rejectReasonInput').trigger('focus');
            return;
        }
        $('#rejectReasonError').addClass('d-none');

        const url  = $('#reviewModal').data('reject-url');
        const $btn = $(this).prop('disabled', true).text('Rechazando…');

        $.post(url, { reason }).done(() => {
            bootstrap.Modal.getInstance(document.getElementById('reviewModal')).hide();
            if ($activeRow) $activeRow.find('td:nth-child(6)').html('<span class="badge bg-danger">Rechazado</span>');
            $('#kpiPendientes').text(Math.max(0, parseInt($('#kpiPendientes').text()) - 1));
            $('#kpiRechazadosHoy').text(parseInt($('#kpiRechazadosHoy').text()) + 1);
            toast('success', 'Rechazado');
        }).fail(xhr => {
            $btn.prop('disabled', false).html('<i class="ti ti-x me-1"></i> Confirmar rechazo');
            let msg = 'No se pudo rechazar.';
            if (xhr.status === 422 && xhr.responseJSON?.errors?.reason) msg = xhr.responseJSON.errors.reason.join('\n');
            toast('error', 'Error', msg);
            console.error(xhr?.responseText || xhr);
        });
    });

    // ── Enviar retroalimentación ─────────────────────────────────────────────
    $(document).on('click', '.js-do-feedback', function () {
        const message = $('#feedbackMessageInput').val().trim();
        if (message.length < 5) {
            $('#feedbackMessageError').removeClass('d-none');
            $('#feedbackMessageInput').trigger('focus');
            return;
        }
        $('#feedbackMessageError').addClass('d-none');

        const modal = $('#reviewModal');
        const url   = modal.data('feedback-url');
        const type  = modal.data('type');
        const docId = modal.data('doc') || '';
        const $btn  = $(this).prop('disabled', true).text('Enviando…');

        $.post(url, { feedback: message, doc_id: docId, type }).done(() => {
            showPanel('reviewViewer', 'reviewFooterDefault');
            toast('success', 'Enviado', 'La retroalimentación fue enviada al proveedor.');
        }).fail(xhr => {
            $btn.prop('disabled', false).html('<i class="ti ti-send me-1"></i> Enviar retroalimentación');
            let msg = 'No se pudo enviar.';
            if (xhr.status === 422 && xhr.responseJSON?.errors?.message) msg = xhr.responseJSON.errors.message.join('\n');
            toast('error', 'Error', msg);
            console.error(xhr?.responseText || xhr);
        });
    });
});
</script>
@endpush
