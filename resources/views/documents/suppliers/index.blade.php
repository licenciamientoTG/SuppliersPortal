@extends('layouts.zircos-supplier')
@php
    $needsBank = blank($supplier->bank_name); // true si null, vacío o solo espacios
@endphp

{{-- Tipos de documentos requeridos --}}
@php
    use Illuminate\Support\Facades\Storage;

    $isSupplierPortal = auth('supplier')->check();
    $documentsStoreRoute = $isSupplierPortal ? 'supplier.documents.store' : 'documents.suppliers.store';
    $documentsDestroyRoute = $isSupplierPortal ? 'supplier.documents.destroy' : 'documents.suppliers.destroy';
    $bankUpdateRoute = $isSupplierPortal ? 'supplier.bank.update' : 'suppliers.bank.update';
    $bankDestroyRoute = $isSupplierPortal ? 'supplier.bank.destroy' : 'suppliers.bank.destroy';
    $repseUpdateRoute = $isSupplierPortal ? 'supplier.repse.update' : 'suppliers.repse.update';
    $sirocStoreRoute = $isSupplierPortal ? 'supplier.sirocs.store' : 'suppliers.sirocs.store';
    $sirocShowRoute = $isSupplierPortal ? 'supplier.sirocs.show' : 'suppliers.sirocs.show';
    $sirocEditRoute = $isSupplierPortal ? 'supplier.sirocs.edit' : 'suppliers.sirocs.edit';
    $sirocUpdateRoute = $isSupplierPortal ? 'supplier.sirocs.update' : 'suppliers.sirocs.update';
    $sirocDestroyRoute = $isSupplierPortal ? 'supplier.sirocs.destroy' : 'suppliers.sirocs.destroy';

    // Armar una lista simple de filas con el "último" documento por tipo
    // docsByType: Collection groupedBy('doc_type') que llega desde el controlador
    $rows = [];
    foreach ($supplierRequirements as $requirement) {
        $type = $requirement->documentType->code;
        $collection = $docsByType[$type] ?? collect();
        $latest = $collection->sortByDesc(fn($d) => $d->uploaded_at ?? $d->created_at)->first();
        $rows[] = [
            'type'  => $type,
            'label' => $requirement->documentType->name,
            'requirement' => $requirement,
            'doc'   => $latest, // puede ser null
        ];
    }

    // Contadores iniciales
    $countApproved = 0;
    $countRejected = 0;
    $countUploaded = 0;

    foreach ($rows as $r) {
        if ($r['doc']) {
            $countUploaded++;
            if ($r['doc']->status === 'accepted')  $countApproved++;
            if ($r['doc']->status === 'rejected')  $countRejected++;
        }
    }

    // Helper visual de nombres amigables
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
    foreach ($rows as $row) {
        $labels[$row['type']] = $row['label'];
    }

    // Helper de badge de estatus
    function status_badge($status) {
        return match ($status) {
            'accepted'       => '<span class="badge bg-success">Aprobado</span>',
            'rejected'       => '<span class="badge bg-danger">Rechazado</span>',
            'pending_review' => '<span class="badge bg-warning text-dark">En revisión</span>',
            default          => '<span class="badge bg-secondary">—</span>',
        };
    }
@endphp

@section('title', 'Documentos del Proveedor')
@push('styles')
<style>
    .stat-card .value { font-size: 1.35rem; font-weight: 700; }
    .stat-card .label { font-size: .85rem; color: #6c757d; }
    .table td, .table th { vertical-align: middle; }

    /* ===== DROP ZONE ===== */
    .drop-zone {
        border: 2px dashed #dee2e6;
        border-radius: 10px;
        padding: 32px 20px;
        text-align: center;
        cursor: pointer;
        transition: border-color .2s, background .2s;
        background: #fafafa;
        outline: none;
        user-select: none;
    }
    .drop-zone:hover { border-color: #86b7fe; background: #f0f6ff; }
    .drop-zone.drag-over { border-color: #0d6efd; background: rgba(13,110,253,.06); }
    .drop-zone.has-file { border-color: #198754; background: #f0fdf4; border-style: solid; padding: 18px 20px; }

    .drop-zone-icon {
        font-size: 2.4rem;
        color: #adb5bd;
        display: block;
        margin-bottom: 8px;
        line-height: 1;
        transition: color .2s;
    }
    .drop-zone:hover .drop-zone-icon,
    .drop-zone.drag-over .drop-zone-icon { color: #0d6efd; }

    .drop-zone-text { font-size: 14px; font-weight: 600; color: #495057; margin-bottom: 3px; }
    .drop-zone-sub  { font-size: 12px; color: #6c757d; }
    .drop-zone-link { color: #0d6efd; text-decoration: underline; }

    /* File preview card */
    .file-preview {
        display: flex;
        align-items: center;
        gap: 12px;
        text-align: left;
    }
    .file-preview-icon {
        width: 46px; height: 46px;
        border-radius: 8px;
        background: #e9ecef;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.4rem;
        flex-shrink: 0;
        color: #6c757d;
    }
    .file-preview-icon.is-pdf   { background: #fee2e2; color: #dc2626; }
    .file-preview-icon.is-image { background: #d1fae5; color: #059669; }

    .file-preview-info { flex: 1; min-width: 0; }
    .file-preview-name {
        font-size: 13px; font-weight: 600; color: #212529;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .file-preview-size { font-size: 12px; color: #6c757d; }

    .file-preview-remove {
        background: none; border: none; color: #adb5bd;
        cursor: pointer; padding: 5px 6px; border-radius: 6px;
        flex-shrink: 0; line-height: 1; font-size: 1rem;
        transition: color .15s, background .15s;
    }
    .file-preview-remove:hover { color: #dc2626; background: #fee2e2; }
</style>
@endpush

@section('page.title', 'Documentos del Proveedor')
@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="javascript:void(0);">Inicio</a></li>
    <li class="breadcrumb-item"><a href="javascript:void(0);">Proveedores</a></li>
    <li class="breadcrumb-item active">Documentos</li>
@endsection

@section('content')
    {{-- Cabecera / KPIs --}}
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <div class="value" id="statApproved">{{ $countApproved }}</div>
                        <div class="label">Documentos aprobados</div>
                    </div>
                    <i class="ti ti-checks fs-2 text-success"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <div class="value" id="statRejected">{{ $countRejected }}</div>
                        <div class="label">Documentos rechazados</div>
                    </div>
                    <i class="ti ti-x fs-2 text-danger"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <div class="value" id="statUploaded">{{ $countUploaded }}</div>
                        <div class="label">Documentos cargados</div>
                    </div>
                    <i class="ti ti-upload fs-2 text-primary"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabs de modos de trabajo --}}
    <ul class="nav nav-pills nav-workmodes mb-3" id="workModes" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="documents-tab" data-bs-toggle="pill" data-bs-target="#documentsPane"
                    type="button" role="tab" aria-controls="documentsPane" aria-selected="true">
                <i class="ti ti-inbox me-1"></i> Carga de documentos
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="bankDetails-tab" data-bs-toggle="pill" data-bs-target="#bankDetailsPane" type="button" role="tab" aria-controls="bankDetailsPane" aria-selected="false">
                <span class="position-relative me-1">
                    <i id="bankTabIcon" class="ti ti-building-bank"
                    @if($needsBank) data-bs-toggle="tooltip" title="Faltan datos bancarios" @endif>
                    </i>
                </span>
                Detalles bancarios
                <span id="bankTabBadge"
                    class="badge rounded-pill ms-2 {{ $needsBank ? 'bg-warning-subtle text-warning' : 'd-none' }}">
                    Pendiente
                </span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="repseDetails-tab" data-bs-toggle="pill" data-bs-target="#repseDetailsPane" type="button" role="tab" aria-controls="repseDetailsPane" aria-selected="false">
                <span class="position-relative me-1">
                    <i id="repseTabIcon" class="ti ti-file-certificate"></i>
                </span>
                REPSE (STPS)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="sirocDetails-tab" data-bs-toggle="pill" data-bs-target="#sirocDetailsPane" type="button" role="tab" aria-controls="sirocDetailsPane" aria-selected="false">
                <span class="position-relative me-1">
                    <i id="sirocTabIcon" class="ti ti-file-certificate"></i>
                </span>
                SIROC (IMSS)
            </button>
        </li>
    </ul>

    <div class="tab-content" id="workModesContent">
        <div class="tab-pane fade show active" id="documentsPane" role="tabpanel" aria-labelledby="documents-tab" tabindex="0">
            {{-- Tabla principal --}}
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Carga de documentos</h5>
                    <div class="text-muted small">
                        Proveedor: <strong>ID {{ $supplier->id }}</strong> — {{ $supplier->company_name ?? $supplier->name ?? 'Proveedor' }}
                    </div>
                </div>
                <div class="card-body">
                    <table class="table-bordered table-hover w-100 table" id="docsTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width:35%;">Documento</th>
                                <th style="width:15%;">Status</th>
                                <th style="width:25%;">Última actualización</th>
                                <th style="width:25%;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $r)
                                @php
                                    /** @var \App\Models\SupplierDocument|null $doc */
                                    $doc = $r['doc'];
                                    $type = $r['type'];
                                    $label = $r['label'];
                                    $statusHtml = $doc ? status_badge($doc->status) : '<span class="badge bg-secondary">Sin cargar</span>';
                                    $dateHuman = $doc
                                        ? optional($doc->uploaded_at ?? $doc->created_at)->format('Y-m-d H:i')
                                        : '—';
                                    $fileUrl = $doc ? Storage::url($doc->path_file) : '#';
                                @endphp

                                <tr data-doc-type="{{ $type }}">
                                    <td class="doc-label">
                                        <div class="fw-semibold">{{ $label }}</div>
                                        @if ($doc && $doc->status === 'rejected' && $doc->rejection_reason)
                                            <div class="text-danger small mt-1">
                                                <i class="ti ti-alert-triangle me-1"></i> {{ $doc->rejection_reason }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="doc-status">{!! $statusHtml !!}</td>
                                    <td class="doc-date">{{ $dateHuman }}</td>
                                    <td class="doc-actions">
                                        @if (!$doc)
                                            <button class="btn btn-sm btn-primary js-open-upload"
                                                    data-doc-type="{{ $type }}"
                                                    data-action="create">
                                                <i class="ti ti-upload me-1"></i> Subir
                                            </button>
                                        @else
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-info js-open-upload"
                                                        data-doc-type="{{ $type }}"
                                                        data-action="update">
                                                    <i class="ti ti-refresh me-1"></i> Actualizar
                                                </button>
                                                <a class="btn btn-sm btn-secondary js-view-file"
                                                href="{{ $fileUrl }}" target="_blank" rel="noopener">
                                                    <i class="ti ti-eye me-1"></i> Ver
                                                </a>
                                                <button class="btn btn-sm btn-outline-danger js-delete-doc" data-doc-type="{{ $type }}" data-doc-id="{{ $doc->id }}" data-url="{{ route($documentsDestroyRoute, [$supplier, $doc->id]) }}">
                                                    <i class="ti ti-trash me-1"></i> Eliminar
                                                </button>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="text-muted small mt-2">
                        <i class="ti ti-info-circle me-1"></i>
                        Al subir un archivo, el estatus pasa a <strong>En revisión</strong> hasta que un revisor lo acepte o rechace.
                    </div>
                </div>
            </div>
        </div>
        <div class="tab-pane fade show" id="bankDetailsPane" role="tabpanel" aria-labelledby="bankDetails-tab" tabindex="0">
            {{-- Tabla principal --}}
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Detalles bancarios</h5>
                    <div class="text-muted small">
                        Proveedor: <strong>ID {{ $supplier->id }}</strong> — {{ $supplier->company_name ?? $supplier->name ?? 'Proveedor' }}
                    </div>
                </div>
                <div class="card-body">
                    {{-- Resumen de datos actuales --}}
                    <div id="bankSummary" class="mb-4">
                        <div class="row g-3">
                            @php
                                $bankMxComplete = !blank($supplier->bank_name) && !blank($supplier->clabe);
                                $bankUsComplete = !blank($supplier->us_bank_name) && !blank($supplier->swift_bic);
                            @endphp
                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100 {{ $bankMxComplete ? 'border-success' : '' }}">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0"><i class="ti ti-building-bank me-1"></i> Banco</h6>
                                        @if($bankMxComplete)
                                            <span class="badge bg-success-subtle text-success">
                                                <i class="ti ti-circle-check me-1"></i>Completo
                                            </span>
                                        @else
                                            <span class="badge bg-light text-dark">Actual</span>
                                        @endif
                                    </div>
                                    <dl class="row mb-0 small">
                                        <dt class="col-5">Nombre del banco</dt>
                                        <dd class="col-7" data-field="bank_name">{{ $supplier->bank_name ?? '—' }}</dd>
                                        <dt class="col-5">CLABE (MX)</dt>
                                        <dd class="col-7" data-field="clabe">{{ $supplier->clabe ?? '—' }}</dd>
                                        <dt class="col-5"># Cuenta</dt>
                                        <dd class="col-7" data-field="account_number">{{ $supplier->account_number ?? '—' }}</dd>
                                        <dt class="col-5">Moneda</dt>
                                        <dd class="col-7" data-field="currency">{{ $supplier->currency ?? 'MXN' }}</dd>
                                    </dl>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100 {{ $bankUsComplete ? 'border-success' : '' }}">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0"><i class="ti ti-credit-card me-1"></i> Para pagos internacionales</h6>
                                        @if($bankUsComplete)
                                            <span class="badge bg-success-subtle text-success">
                                                <i class="ti ti-circle-check me-1"></i>Completo
                                            </span>
                                        @else
                                            <span class="badge bg-light text-dark">Actual</span>
                                        @endif
                                    </div>
                                    <dl class="row mb-0 small">
                                        <dt class="col-5">Banco en EE.UU.</dt>
                                        <dd class="col-7" data-field="us_bank_name">{{ $supplier->us_bank_name ?? '—' }}</dd>
                                        <dt class="col-5">SWIFT/BIC</dt>
                                        <dd class="col-7" data-field="swift_bic">{{ $supplier->swift_bic ?? '—' }}</dd>
                                        <dt class="col-5">IBAN</dt>
                                        <dd class="col-7" data-field="iban">{{ $supplier->iban ?? '—' }}</dd>
                                        <dt class="col-5">ABA Routing (US)</dt>
                                        <dd class="col-7" data-field="aba_routing">{{ $supplier->aba_routing ?? '—' }}</dd>
                                        <dt class="col-5">Dirección del banco</dt>
                                        <dd class="col-7" data-field="bank_address">{{ $supplier->bank_address ?? '—' }}</dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Formulario de edición/creación --}}
                    <form id="bankForm" autocomplete="off" data-url="{{ route($bankUpdateRoute, $supplier) }}" data-method="PATCH">
                        @csrf
                        <div class="row g-4">
                            {{-- Columna izquierda: datos básicos --}}
                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <h6 class="mb-3"><i class="ti ti-building-bank me-1"></i> Datos básicos</h6>

                                    <div class="mb-3">
                                        <label class="form-label">Nombre del banco</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ti ti-building"></i></span>
                                            <input type="text" name="bank_name" class="form-control"
                                                value="{{ old('bank_name', $supplier->bank_name) }}"
                                                placeholder="Ej. BBVA, Santander, Chase">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Moneda (ISO 4217)</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ti ti-currency-dollar"></i></span>
                                            <select name="currency" class="form-select">
                                                @php
                                                    $currencies = ['MXN' => 'Peso mexicano (MXN)',
                                                                'USD' => 'Dólar estadounidense (USD)',
                                                                'EUR' => 'Euro (EUR)',
                                                                'GBP' => 'Libra esterlina (GBP)',
                                                                'JPY' => 'Yen japonés (JPY)'];
                                                    $selected = old('currency', $supplier->currency ?? 'MXN');
                                                @endphp

                                                @foreach($currencies as $code => $label)
                                                    <option value="{{ $code }}" {{ $selected === $code ? 'selected' : '' }}>
                                                        {{ $label }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-text">Selecciona la moneda principal de la cuenta.</div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label"># de Cuenta</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ti ti-credit-card"></i></span>
                                            <input type="text" name="account_number" class="form-control"
                                                pattern="[0-9]*"
                                                oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                                value="{{ old('account_number', $supplier->account_number) }}" maxlength="20"
                                                placeholder="Tu número de cuenta" inputmode="numeric">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">CLABE (México)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">CL</span>
                                            <input type="text" name="clabe" class="form-control"
                                                pattern="[0-9]{18}"
                                                oninput="this.value = this.value.replace(/[^0-9]/g, '')" value="{{ old('clabe', $supplier->clabe) }}"
                                                placeholder="18 dígitos" maxlength="18" inputmode="numeric" title="La CLABE debe tener exactamente 18 dígitos">
                                            <span class="input-group-text"><i class="ti ti-lock"></i></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Columna derecha: pagos internacionales --}}
                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <h6 class="mb-3"><i class="ti ti-world me-1"></i> Pagos internacionales</h6>

                                    <div class="mb-3">
                                        <label class="form-label">Banco en EE.UU. (opcional)</label>
                                        <input type="text" name="us_bank_name" class="form-control"
                                            value="{{ old('us_bank_name', $supplier->us_bank_name) }}"
                                            placeholder="Ej. Wells Fargo, Bank of America" maxlength="100">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">SWIFT / BIC</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ti ti-share"></i></span>
                                            <input type="text" name="swift_bic" class="form-control text-uppercase"
                                                value="{{ old('swift_bic', $supplier->swift_bic) }}"
                                                placeholder="8 u 11 caracteres" maxlength="11">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">IBAN</label>
                                        <div class="input-group">
                                            <span class="input-group-text">IB</span>
                                            <input type="text" name="iban" class="form-control text-uppercase"
                                                value="{{ old('iban', $supplier->iban) }}"
                                                placeholder="Máx. 34 caracteres" maxlength="34">
                                            <span class="input-group-text"><i class="ti ti-world"></i></span>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Dirección del banco (ciudad, país)</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ti ti-map-pin"></i></span>
                                            <input type="text" name="bank_address" class="form-control"
                                                value="{{ old('bank_address', $supplier->bank_address) }}"
                                                placeholder="Cd. Juárez, Chihuahua, MX / Dallas, USA">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">ABA Routing (EE.UU.)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">AB</span>
                                            <input type="text" name="aba_routing" class="form-control"
                                                value="{{ old('aba_routing', $supplier->aba_routing) }}"
                                                placeholder="9 dígitos" maxlength="9">
                                            <span class="input-group-text"><i class="ti ti-flag"></i></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <button type="button" id="btnSaveBank" class="btn btn-primary">
                                <i class="ti ti-device-floppy me-1"></i> Guardar cambios
                            </button>
                            <button type="button" id="btnDeleteBank" class="btn btn-outline-danger"
                                    data-url="{{ route($bankDestroyRoute, $supplier) }}">
                                <i class="ti ti-trash me-1"></i> Eliminar datos bancarios
                            </button>
                        </div>
                    </form>


                    {{-- Área para feedback --}}
                    <div id="bankFeedback" class="mt-3 d-none">
                        <div class="alert" role="alert"></div>
                    </div>


                    <div class="text-muted small mt-2">
                        <i class="ti ti-info-circle me-1"></i>
                        Al subir un archivo, el estatus pasa a <strong>En revisión</strong> hasta que un revisor lo acepte o rechace.
                    </div>
                </div>
            </div>
        </div>
        <div class="tab-pane fade show" id="repseDetailsPane" role="tabpanel" aria-labelledby="repseDetails-tab" tabindex="0">
            {{-- Tabla principal --}}
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">REPSE (STPS)</h5>
                            <p class="text-muted small mb-0 mt-1">
                                Registro de Prestadoras de Servicios Especializados u Obras Especializadas de la STPS;
                                requerido para empresas que brindan servicios u obras especializadas.
                            </p>
                        </div>
                        <div class="text-muted small">
                            Proveedor: <strong>ID {{ $supplier->id }}</strong> — {{ $supplier->company_name ?? $supplier->name ?? 'Proveedor' }}
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    {{-- REPSE / Servicios especializados --}}
                    <form id="repseForm" autocomplete="off"
                        data-url="{{ route($repseUpdateRoute, $supplier) }}"
                        data-method="PATCH">
                        @csrf
                        <div class="row g-4">
                            {{-- Columna izquierda --}}
                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <h6 class="mb-3">
                                        <i class="ti ti-file-certificate me-1"></i> Registro REPSE
                                    </h6>

                                    {{-- ¿Provee servicios especializados? --}}
                                    <div class="mb-3">
                                        <label class="form-label d-flex align-items-center gap-2">
                                            <span>¿Provee servicios especializados?</span>
                                            <i class="ti ti-info-circle text-muted" data-bs-toggle="tooltip"
                                            title="Marca SÍ si tu empresa está catalogada como de servicios especializados conforme a la Ley."></i>
                                        </label>
                                        <div class="form-check form-switch">
                                            @php $provides = (bool) old('provides_specialized_services', $supplier->provides_specialized_services); @endphp
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                name="provides_specialized_services" id="provides_specialized_services"
                                                {{ $provides ? 'checked' : '' }}>
                                            <label class="form-check-label" for="provides_specialized_services">
                                                {{ $provides ? 'Sí' : 'No' }}
                                            </label>
                                        </div>
                                        <div class="form-text">
                                            Este valor condiciona la obligatoriedad del REPSE.
                                        </div>
                                    </div>

                                    <div id="repseDisabledNotice" class="alert alert-light border text-muted small py-2 {{ $provides ? 'd-none' : '' }}">
                                        <i class="ti ti-lock me-1"></i>
                                        Activa el switch para habilitar los campos REPSE.
                                    </div>

                                    {{-- Número de registro REPSE --}}
                                    <div class="mb-3">
                                        <label class="form-label">Número de registro REPSE</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ti ti-hash"></i></span>
                                            <input type="text" name="repse_registration_number"
                                                class="form-control text-uppercase"
                                                value="{{ old('repse_registration_number', $supplier->repse_registration_number) }}"
                                                placeholder="Ej. ABCD-123456-2025">
                                        </div>
                                        <div class="form-text">
                                            Si no aplica, deja en blanco.
                                        </div>
                                    </div>

                                    {{-- Fecha de vencimiento --}}
                                    <div class="mb-3">
                                        <label class="form-label">Fecha de vencimiento REPSE</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ti ti-calendar"></i></span>
                                            <input type="date" name="repse_expiry_date" class="form-control"
                                                value="{{ old('repse_expiry_date', optional($supplier->repse_expiry_date)->format('Y-m-d')) }}">
                                        </div>
                                        <div class="form-text">
                                            Úsalo para activar alertas de renovación.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Columna derecha --}}
                            @php
                                // Catálogo código => etiqueta
                                $servCats = [
                                    'limpieza'      => 'Servicios de limpieza',
                                    'vigilancia'    => 'Vigilancia y seguridad',
                                    'mantenimiento' => 'Mantenimiento',
                                    'alimentacion'  => 'Servicios de alimentación',
                                    'contabilidad'  => 'Servicios contables/administrativos',
                                    'sistemas'      => 'Servicios de sistemas/TI',
                                    'otros'         => 'Otros',
                                ];

                                // Normaliza lo que ya tengas guardado (array o JSON string)
                                $existing = $supplier->specialized_services_types ?? [];
                                if (is_string($existing)) {
                                    $decoded = json_decode($existing, true);
                                    $existing = is_array($decoded) ? $decoded : [];
                                }

                                // Mapear existentes a códigos (si coinciden con etiquetas) y separar "otros"
                                $labelToCode = collect($servCats)
                                    ->mapWithKeys(fn($label, $code) => [mb_strtolower($label) => $code])
                                    ->all();

                                $selectedCodes = [];
                                $othersInit    = [];

                                foreach ($existing as $item) {
                                    $key = mb_strtolower(trim((string)$item));
                                    if (isset($labelToCode[$key]) && $labelToCode[$key] !== 'otros') {
                                        $selectedCodes[] = $labelToCode[$key];
                                    } else {
                                        // No match exacto con una etiqueta conocida => va a "otros"
                                        if ($key !== '') $othersInit[] = (string)$item;
                                    }
                                }

                                $isOtrosSelected = !empty($othersInit);
                            @endphp

                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <h6 class="mb-3">
                                        <i class="ti ti-briefcase me-1"></i> Tipos de servicios especializados
                                    </h6>

                                    {{-- Select múltiple --}}
                                    <div class="mb-3">
                                        <label class="form-label">Giros / actividades</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ti ti-list-details"></i></span>
                                            <select id="repseTypesSelect" class="form-select" multiple size="7">
                                                @foreach($servCats as $code => $label)
                                                    <option value="{{ $code }}"
                                                        {{ in_array($code, old('repse_types', $selectedCodes), true) || ($code === 'otros' && $isOtrosSelected) ? 'selected' : '' }}>
                                                        {{ $label }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-text">
                                            Selecciona todos los giros que apliquen mantenido presionada la tecla ctrl. Si marcas <b>Otros</b>, describe abajo.
                                        </div>
                                    </div>

                                    {{-- Campo libre para "Otros" (aparece solo si se selecciona "otros") --}}
                                    <div id="repseOtrosWrapper" class="{{ $isOtrosSelected ? '' : 'd-none' }}">
                                        <label class="form-label">Otros giros</label>
                                        <textarea id="repseOtrosText" class="form-control" rows="3"
                                            placeholder="Agrega los giros tal como aparecen en la constancia (Actividad económica).">{{ implode(', ', $othersInit) }}</textarea>
                                        <div class="form-text">
                                            Separa múltiples giros con coma. Agrega los giros tal como aparecen en la constancia (Actividad económica).
                                        </div>
                                    </div>

                                    {{-- Valor real que viaja al backend (JSON array de etiquetas legibles) --}}
                                    <input type="hidden" name="specialized_services_types" id="specialized_services_types_hidden"
                                        value='@json($existing)'>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <button type="button" id="btnSaveRepse" class="btn btn-primary">
                                <i class="ti ti-device-floppy me-1"></i> Guardar cambios
                            </button>
                            <button type="button" id="btnClearRepse" class="btn btn-outline-danger">
                                <i class="ti ti-eraser me-1"></i> Limpiar campos
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
        <div class="tab-pane fade show" id="sirocDetailsPane" role="tabpanel" aria-labelledby="sirocDetails-tab" tabindex="0">
            {{-- Tabla principal --}}
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">SIROC (IMSS)</h5>
                            <p class="text-muted small mb-0 mt-1">
                                Sistema del IMSS para registrar obras de construcción, contratos y subcontratistas.
                            </p>
                        </div>
                        <div class="text-muted small">
                            Proveedor: <strong>ID {{ $supplier->id }}</strong> — {{ $supplier->company_name ?? $supplier->name ?? 'Proveedor' }}
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    {{-- === ALTA DE SIROC (por proveedor) === --}}
                    <form id="sirocForm" autocomplete="off"
                        data-url="{{ route($sirocStoreRoute, $supplier) }}"
                        data-method="POST" enctype="multipart/form-data">
                        @csrf

                        <div class="row g-4">
                            {{-- Columna izquierda --}}
                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <h6 class="mb-3">
                                        <i class="ti ti-building-construction me-1"></i> Registro SIROC (IMSS)
                                    </h6>

                                    {{-- Número de registro SIROC --}}
                                    <div class="mb-3">
                                        <label class="form-label">Número de registro SIROC</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ti ti-hash"></i></span>
                                            <input type="text" name="siroc_number" id="siroc_number" class="form-control text-uppercase"
                                                maxlength="50"
                                                placeholder="Ej. IMSS-OBRA-00012345" value="{{ old('siroc_number') }}">
                                        </div>
                                        <div class="form-text">
                                            Número de registro emitido al dar de alta la obra ante el IMSS.
                                        </div>
                                    </div>

                                    <div id="sirocDisabledNotice" class="alert alert-light border text-muted small py-2 {{ old('siroc_number') ? 'd-none' : '' }}">
                                        <i class="ti ti-lock me-1"></i>
                                        Ingresa el número de registro SIROC para habilitar los demás campos.
                                    </div>

                                    {{-- Número de contrato (opcional) --}}
                                    <div class="mb-3">
                                        <label class="form-label">Número de contrato (opcional)</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ti ti-file-text"></i></span>
                                            <input type="text" name="contract_number" class="form-control"
                                                maxlength="100" placeholder="Contrato interno o con cliente"
                                                value="{{ old('contract_number') }}">
                                        </div>
                                        <div class="form-text">
                                            Si asociarás el SIROC a un contrato, colócalo aquí.
                                        </div>
                                    </div>

                                    {{-- Nombre / descripción de la obra --}}
                                    <div class="mb-3">
                                        <label class="form-label">Nombre / descripción de la obra</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ti ti-clipboard-text"></i></span>
                                            <input type="text" name="work_name" class="form-control"
                                                maxlength="255" placeholder="Ej. Construcción de techumbre en estación X"
                                                value="{{ old('work_name') }}">
                                        </div>
                                    </div>

                                    {{-- Ubicación de la obra --}}
                                    <div class="mb-3">
                                        <label class="form-label">Ubicación de la obra</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ti ti-map-pin"></i></span>
                                            <input type="text" name="work_location" class="form-control"
                                                maxlength="255" placeholder="Dirección, ciudad, estado"
                                                value="{{ old('work_location') }}">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Columna derecha --}}
                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <h6 class="mb-3">
                                        <i class="ti ti-calendar-stats me-1"></i> Vigencia, estatus y evidencia
                                    </h6>

                                    {{-- Fechas --}}
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Fecha de inicio</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="ti ti-calendar"></i></span>
                                                <input type="date" name="start_date" class="form-control"
                                                    value="{{ old('start_date') }}">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Fecha de término (estimada/real)</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="ti ti-calendar-check"></i></span>
                                                <input type="date" name="end_date" class="form-control"
                                                    value="{{ old('end_date') }}">
                                            </div>
                                            <div class="form-text">
                                                Debe ser mayor o igual a la fecha de inicio.
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Estatus --}}
                                    <div class="mt-3">
                                        <label class="form-label">Estatus de la obra</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ti ti-traffic-cone"></i></span>
                                            <select name="status" class="form-select">
                                                @php $st = old('status', 'vigente'); @endphp
                                                <option value="vigente"    {{ $st === 'vigente' ? 'selected' : '' }}>Vigente</option>
                                                <option value="suspendido" {{ $st === 'suspendido' ? 'selected' : '' }}>Suspendido</option>
                                                <option value="terminado"  {{ $st === 'terminado' ? 'selected' : '' }}>Terminado</option>
                                            </select>
                                        </div>
                                    </div>

                                    {{-- PDF evidencia --}}
                                    <div class="mt-3">
                                        <label class="form-label d-flex align-items-center gap-2">
                                            <span>Constancia de alta SIROC (PDF)</span>
                                            <i class="ti ti-info-circle text-muted" data-bs-toggle="tooltip"
                                            title="Sube el PDF de la constancia emitida por el portal del IMSS al dar de alta la obra. Máx. 5 MB."></i>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ti ti-file-type-pdf"></i></span>
                                            <input type="file" name="siroc_file" id="siroc_file"
                                                class="form-control" accept="application/pdf">
                                        </div>
                                        <div class="form-text" id="sirocFileName">Sin archivo seleccionado.</div>
                                    </div>

                                    {{-- Observaciones --}}
                                    <div class="mt-3">
                                        <label class="form-label">Observaciones</label>
                                        <textarea name="observations" rows="3" class="form-control"
                                                placeholder="Notas internas, aclaraciones, incidencias...">{{ old('observations') }}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Errores --}}
                        <div id="sirocErrors" class="alert alert-danger mt-3 d-none"></div>

                        {{-- Botones de acción --}}
                        <div class="d-flex gap-2 mt-4">
                            <button type="button" id="btnSaveSiroc" class="btn btn-primary">
                                <i class="ti ti-device-floppy me-1"></i> Guardar SIROC
                            </button>
                            <button type="button" id="btnClearSiroc" class="btn btn-outline-danger">
                                <i class="ti ti-eraser me-1"></i> Limpiar campos
                            </button>
                            @isset($closeButton)
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                    <i class="ti ti-x me-1"></i> Cerrar
                                </button>
                            @endisset
                        </div>
                    </form>

                    <hr class="my-4">

                    {{-- === LISTA DE SIROC CARGADOS === --}}
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0"><i class="ti ti-list-details me-1"></i> Registros cargados</h6>
                        <span class="text-muted small">Total: {{ $supplier->sirocs->count() }}</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0" id="sirocTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 48px;">#</th>
                                    <th>Registro SIROC</th>
                                    <th>Obra</th>
                                    <th>Ubicación</th>
                                    <th>Inicio</th>
                                    <th>Fin</th>
                                    <th>Estatus</th>
                                    <th>Evidencia</th>
                                    <th style="width: 60px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($supplier->sirocs as $idx => $s)
                                    <tr data-id="{{ $s->id }}">
                                        <td class="col-idx text-muted">{{ $idx + 1 }}</td>
                                        <td class="col-number"><code>{{ $s->siroc_number }}</code></td>
                                        <td class="col-work">{{ $s->work_name ?: '—' }}</td>
                                        <td class="col-location text-truncate" style="max-width: 260px;">{{ $s->work_location ?: '—' }}</td>
                                        <td class="col-start">{{ optional($s->start_date)->format('Y-m-d') ?: '—' }}</td>
                                        <td class="col-end">{{ optional($s->end_date)->format('Y-m-d') ?: '—' }}</td>
                                        <td class="col-status">
                                            @php
                                                $badges = ['vigente' => 'success', 'suspendido' => 'warning', 'terminado' => 'secondary'];
                                                $icons  = ['vigente' => 'check',   'suspendido' => 'alert-triangle', 'terminado' => 'circle-off'];
                                                $b = $badges[$s->status] ?? 'secondary';
                                                $i = $icons[$s->status]  ?? 'info-circle';
                                            @endphp
                                            <span class="badge bg-{{ $b }}">
                                                <i class="ti ti-{{ $i }} me-1"></i>{{ Str::ucfirst($s->status) }}
                                            </span>
                                        </td>
                                        <td class="col-file">
                                            @if($s->siroc_file)
                                                <a href="{{ asset('storage/' . $s->siroc_file) }}" target="_blank" class="btn btn-xs btn-outline-primary">
                                                    <i class="ti ti-file-type-pdf"></i> PDF
                                                </a>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="col-actions text-end">
                                            <div class="d-flex justify-content-end gap-1">
                                                <a href="{{ route($sirocShowRoute, [$supplier, $s]) }}" class="btn btn-sm btn-outline-secondary" title="Ver"><i class="ti ti-eye"></i></a>
                                                <button type="button" class="btn btn-sm btn-outline-primary js-edit-siroc"
                                                    data-update-url="{{ route($sirocUpdateRoute, [$s->supplier, $s]) }}"
                                                    data-siroc-number="{{ e($s->siroc_number) }}"
                                                    data-contract-number="{{ e($s->contract_number) }}"
                                                    data-work-name="{{ e($s->work_name) }}"
                                                    data-work-location="{{ e($s->work_location) }}"
                                                    data-start-date="{{ optional($s->start_date)->format('Y-m-d') }}"
                                                    data-end-date="{{ optional($s->end_date)->format('Y-m-d') }}"
                                                    data-status="{{ $s->status }}"
                                                    data-observations="{{ e($s->observations) }}"
                                                    data-file-url="{{ $s->siroc_file ? asset('storage/'.$s->siroc_file) : '' }}"
                                                    title="Editar"><i class="ti ti-pencil"></i></button>
                                                <button class="btn btn-sm btn-outline-danger js-del-siroc"
                                                        data-url="{{ route($sirocDestroyRoute, [$supplier, $s]) }}"
                                                        title="Eliminar"><i class="ti ti-trash"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr id="sirocEmptyRow"><td colspan="9" class="text-center text-muted py-3">Sin registros.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        <div class="modal fade" id="sirocEditModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title"><i class="ti ti-pencil me-2"></i> Editar SIROC</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                </div>

                                <form id="editSirocForm" autocomplete="off" enctype="multipart/form-data">
                                    @csrf
                                    <input type="hidden" name="_method" value="PUT">

                                    <div class="modal-body">
                                    <div id="editSirocErrors" class="alert alert-danger d-none"></div>

                                    <div class="row g-3">
                                        <div class="col-md-6">
                                        <label class="form-label">Número de registro SIROC</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ti ti-hash"></i></span>
                                            <input type="text" name="siroc_number" class="form-control text-uppercase" maxlength="50">
                                        </div>
                                        </div>

                                        <div class="col-md-6">
                                        <label class="form-label">Número de contrato (opcional)</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ti ti-file-text"></i></span>
                                            <input type="text" name="contract_number" class="form-control" maxlength="100">
                                        </div>
                                        </div>

                                        <div class="col-md-6">
                                        <label class="form-label">Nombre / descripción de la obra</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ti ti-clipboard-text"></i></span>
                                            <input type="text" name="work_name" class="form-control" maxlength="255">
                                        </div>
                                        </div>

                                        <div class="col-md-6">
                                        <label class="form-label">Ubicación de la obra</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ti ti-map-pin"></i></span>
                                            <input type="text" name="work_location" class="form-control" maxlength="255">
                                        </div>
                                        </div>

                                        <div class="col-md-6">
                                        <label class="form-label">Fecha de inicio</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ti ti-calendar"></i></span>
                                            <input type="date" name="start_date" class="form-control">
                                        </div>
                                        </div>

                                        <div class="col-md-6">
                                        <label class="form-label">Fecha de término (estimada/real)</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ti ti-calendar-check"></i></span>
                                            <input type="date" name="end_date" class="form-control">
                                        </div>
                                        <div class="form-text">Debe ser mayor o igual a la fecha de inicio.</div>
                                        </div>

                                        <div class="col-md-6">
                                        <label class="form-label">Estatus de la obra</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ti ti-traffic-cone"></i></span>
                                            <select name="status" class="form-select">
                                            <option value="vigente">Vigente</option>
                                            <option value="suspendido">Suspendido</option>
                                            <option value="terminado">Terminado</option>
                                            </select>
                                        </div>
                                        </div>

                                        <div class="col-md-6">
                                        <label class="form-label d-flex align-items-center gap-2">
                                            <span>Constancia SIROC (PDF)</span>
                                            <i class="ti ti-info-circle text-muted" data-bs-toggle="tooltip" title="Si adjuntas un PDF, reemplazará al existente. Máx. 5 MB."></i>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ti ti-file-type-pdf"></i></span>
                                            <input type="file" name="siroc_file" id="edit_siroc_file" class="form-control" accept="application/pdf">
                                        </div>
                                        <div class="form-text">
                                            Actual: <a href="#" target="_blank" id="currentSirocFileLink">—</a>
                                        </div>
                                        </div>

                                        <div class="col-12">
                                        <label class="form-label">Observaciones</label>
                                        <textarea name="observations" rows="3" class="form-control"></textarea>
                                        </div>
                                    </div>
                                    </div>

                                    <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i> Cancelar</button>
                                    <button type="submit" class="btn btn-primary" id="btnUpdateSiroc">
                                        <i class="ti ti-device-floppy me-1"></i> Guardar cambios
                                    </button>
                                    </div>
                                </form>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    {{-- Modal de Upload/Update --}}
    <div class="modal fade" id="docModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-scrollable">
            <div class="modal-content">
                <form id="docForm" method="post" enctype="multipart/form-data"
                      action="{{ route($documentsStoreRoute, $supplier) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="docModalTitle">Subir documento</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div id="formErrors" class="d-none"></div>

                        <input type="hidden" name="doc_type" id="docTypeInput" value="">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Documento a cargar</label>

                            {{-- Drop zone --}}
                            <div id="dropZone" class="drop-zone" role="button" tabindex="0" aria-label="Área de carga de archivo">
                                {{-- Estado: sin archivo --}}
                                <div id="dropZonePrompt">
                                    <i class="ti ti-cloud-upload drop-zone-icon"></i>
                                    <div class="drop-zone-text">Arrastra el archivo aquí</div>
                                    <div class="drop-zone-sub">o <span class="drop-zone-link">haz clic para seleccionar</span></div>
                                </div>
                                {{-- Estado: archivo seleccionado --}}
                                <div id="dropZonePreview" class="d-none">
                                    <div class="file-preview">
                                        <div class="file-preview-icon" id="previewIcon">
                                            <i class="ti ti-file"></i>
                                        </div>
                                        <div class="file-preview-info">
                                            <div class="file-preview-name" id="previewName"></div>
                                            <div class="file-preview-size" id="previewSize"></div>
                                        </div>
                                        <button type="button" class="file-preview-remove" id="removeFile" title="Quitar archivo">
                                            <i class="ti ti-x"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {{-- Input real (oculto, lo usa el formulario) --}}
                            <input type="file" name="file" id="fileInput" class="d-none"
                                   accept=".jpg,.jpeg,.png,.pdf" required>

                            <div class="form-text mt-2" id="sizeHelp">
                                <i class="ti ti-info-circle me-1"></i>
                                PDF / JPG / PNG &nbsp;·&nbsp; Máx. <span id="maxMb">10</span> MB
                            </div>
                        </div>

                        <div class="alert alert-info d-flex align-items-center py-2">
                            <i class="ti ti-info-circle me-2"></i>
                            <div><strong>Nota:</strong> Al actualizar, se reemplaza la versión anterior.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-primary" type="submit" id="btnSubmitDoc">
                            <i class="ti ti-upload me-1"></i> Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form   = document.getElementById('bankForm');
    const btnSave = document.getElementById('btnSaveBank');
    const btnDel  = document.getElementById('btnDeleteBank');
    const feedback = document.getElementById('bankFeedback');
    const summary  = document.getElementById('bankSummary');

    function showAlert(type, msg) {
        feedback.classList.remove('d-none');
        const alert = feedback.querySelector('.alert');
        alert.className = 'alert alert-' + type;
        alert.innerHTML = msg;
        setTimeout(() => feedback.classList.add('d-none'), 4000);
    }

    function updateSummary(data) {
        if (!data) return;
        const fields = [
            'bank_name','bank_address','currency',
            'account_number','clabe','swift_bic','iban','aba_routing',
            'us_bank_name'
        ];
        fields.forEach(f => {
            const el = summary.querySelector(`[data-field="${f}"]`);
            if (el) el.textContent = data[f] ? data[f] : '—';
        });
    }

    // Guardar (POST + _method=PATCH)
    btnSave.addEventListener('click', async () => {
        const url = form.dataset.url;
        const fd  = new FormData(form);
        fd.append('_method', 'PATCH'); // <- clave

        try {
            const res = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin', // asegura cookies de sesión
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: fd
            });

            if (res.status === 422) {
                const err = await res.json();
                const msgs = Object.values(err.errors || {}).flat().join('<br>');
                showAlert('danger', msgs || 'Revisa la información.');
                return;
            }

            const json = await res.json();
            updateSummary(json.supplier);
            showAlert('success', json.message || 'Guardado.');
        } catch (e) {
            showAlert('danger', 'Ocurrió un error al guardar.');
        }
    });

    // Eliminar (POST + _method=DELETE)
    btnDel.addEventListener('click', async () => {
        const url = btnDel.dataset.url;

        Swal.fire({
            title: '¿Eliminar datos bancarios?',
            html: 'Se eliminarán los <b>datos bancarios</b> asociadas a este proveedor.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            focusCancel: true,
            customClass: {
                confirmButton: 'btn btn-danger',
                cancelButton: 'btn btn-secondary'
            },
            buttonsStyling: false,
            showLoaderOnConfirm: true,
            preConfirm: async () => {
                const fd = new FormData();
                fd.append('_method', 'DELETE');

                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        body: fd
                    });

                    if (!res.ok) {
                        const txt = await res.text();
                        throw new Error(txt || 'Error al eliminar.');
                    }

                    return res.json(); // <-- pasa al then como result.value
                } catch (err) {
                    Swal.showValidationMessage(err.message || 'No se pudo eliminar.');
                }
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (!result.isConfirmed) return;

            const json = result.value || {};

            // Limpia UI
            form.reset();
            updateSummary({
                bank_name: null,
                bank_address: null,
                account_number: null,
                clabe: null,
                currency: '{{ $supplier->currency ?? 'MXN' }}',
                swift_bic: null,
                iban: null,
                aba_routing: null
            });

            // Si usas el indicador del tab:
            if (typeof setBankTabState === 'function') setBankTabState(false);

            Swal.fire({
                icon: 'success',
                title: 'Eliminado',
                text: json.message || 'Datos bancarios eliminados.',
                timer: 1800,
                showConfirmButton: false
            });
        });
    });
});

$(function () {
    // Tipos que permiten 50 MB
    const LARGE_TYPES = ['acta_constitutiva', 'poder_legal'];
    const getMaxMb = (docType) => (LARGE_TYPES.includes((docType||'').trim()) ? 50 : 10);
    const getMaxBytes = (docType) => getMaxMb(docType) * 1024 * 1024;

    // CSRF p/ AJAX
    $.ajaxSetup({
        headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}
    });

    const modalEl = document.getElementById('docModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

    // Abrir modal para Subir/Actualizar
    $(document).on('click', '.js-open-upload', function (e) {
        e.preventDefault();
        const docType = $(this).data('doc-type');
        const action  = $(this).data('action'); // create | update

        $('#docTypeInput').val(docType);
        $('#fileInput').val('');
        $('#formErrors').addClass('d-none').empty();

        // Actualizar el texto de máximo según el tipo
        $('#maxMb').text(getMaxMb(docType));

        const label = docTypeToLabel(docType);
        $('#docModalTitle').text((action === 'update' ? 'Actualizar' : 'Subir') + ' — ' + label);

        modal.show();
    });

    // Validación de tamaño en cliente según doc_type
    $(document).on('change', '#fileInput', function () {
        const file = this.files && this.files[0];
        if (!file) return;

        const docType = $('#docTypeInput').val();
        const maxBytes = getMaxBytes(docType);

        if (file.size > maxBytes) {
            $('#formErrors').html(
            `<div class="alert alert-danger">
                <i class="ti ti-alert-triangle me-1"></i>
                El archivo supera el límite permitido (${getMaxMb(docType)}MB) para este documento.
            </div>`
            ).removeClass('d-none');
            this.value = ''; // limpia la selección
        } else {
            $('#formErrors').addClass('d-none').empty();
        }
    });

    // Enviar formulario (Subir/Actualizar)
    $(document).on('submit', '#docForm', function (e) {
        e.preventDefault();
        const $form = $(this);
        const action = $form.attr('action');
        const formData = new FormData($form[0]);

        $('#btnSubmitDoc').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> Guardando...');

        $.ajax({
            url: action,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json'
        })
        .done(function (res) {
            $('#btnSubmitDoc').prop('disabled', false).html('<i class="ti ti-upload me-1"></i> Guardar');
            const el = document.getElementById('docModal');
            const modal = bootstrap.Modal.getInstance(el);
            modal && modal.hide();

            const docType = res.doc_type; // <- viene del backend
            const $tr = $('#docsTable tbody tr[data-doc-type="'+docType+'"]');

            setRowFromPayload($tr, res);  // 👈 ACTUALIZA botones con id/url reales
            recalcCounters();
            toastOk && toastOk('Documento cargado. Quedó en revisión.');
        })
        .fail(function (xhr) {
            $('#btnSubmitDoc').prop('disabled', false)
                .html('<i class="ti ti-upload me-1"></i> Guardar');

            // ⚠️ El archivo excedió el tamaño permitido en el servidor
            if (xhr.status === 413) {
                const docType = $('#docTypeInput').val();
                $('#formErrors').html(
                    '<div class="alert alert-danger">' +
                    '<i class="ti ti-alert-triangle me-1"></i>' +
                    `El archivo excede el tamaño máximo permitido (${getMaxMb(docType)}MB). ` +
                    'Por favor selecciona un archivo más ligero.' +
                    '</div>'
                ).removeClass('d-none');
                return;
            }

            // Validación de Laravel (regla max, mimes, etc.)
            if (xhr.status === 422) {
                let html = '<div class="alert alert-danger"><ul class="mb-0">';
                try {
                    const res = xhr.responseJSON;
                    Object.values(res.errors || {}).forEach(arr =>
                        arr.forEach(msg => html += `<li>${msg}</li>`)
                    );
                } catch (e) {
                    html += '<li>Datos inválidos.</li>';
                }
                html += '</ul></div>';
                $('#formErrors').html(html).removeClass('d-none');
                return;
            }

            // Otros errores inesperados
            $('#formErrors').html(
                '<div class="alert alert-danger">Error inesperado al cargar el archivo.</div>'
            ).removeClass('d-none');
            if (window.console) console.error(xhr?.responseText || xhr);
        });
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
        $('#docTypeInput').val('');
        $('#fileInput').val('');
        $('#formErrors').addClass('d-none').empty();
        $('#maxMb').text('10'); // visual por defecto
    });


    // Ver (usa <a target="_blank">), no requiere JS adicional

    // Eliminar con SweetAlert2
    $(document).on('click', '.js-delete-doc', function (e) {
        e.preventDefault();
        const $btn = $(this);
        const docType = $btn.data('doc-type');
        const url     = $btn.data('url');

        Swal.fire({
            title: '¿Eliminar documento?',
            text: 'Esta acción no se puede deshacer.',
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
        }).then((r) => {
            if (!r.isConfirmed) return;

            $.ajax({
                url: url,
                type: 'POST',
                data: { _method: 'DELETE' } // spoof
            })
            .done(function () {
                const $tr = $('#docsTable tbody tr[data-doc-type="'+docType+'"]');
                setRowAsEmpty($tr);
                recalcCounters();
                Swal.fire({ icon: 'success', title: 'Eliminado', timer: 1600, showConfirmButton: false });
            })
            .fail(function (xhr) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo eliminar el documento.' });
                console.error(xhr?.responseText || xhr);
            });
        });
        });


    // Helpers UI
    function setRowFromPayload($tr, payload) {
        // status + fecha
        $tr.find('.doc-status').html('<span class="badge bg-warning text-dark">En revisión</span>');
        $tr.find('.doc-date').text(payload.uploaded_at || nowStr());

        // Acciones con datos REALES
        const docType = $tr.data('doc-type');
        const destroyUrl = payload.destroy_url; // viene del backend
        const viewUrl    = payload.url || '#';

        const actionsHtml = `
            <div class="btn-group" role="group">
                <button class="btn btn-sm btn-info js-open-upload" data-doc-type="${docType}" data-action="update">
                    <i class="ti ti-refresh me-1"></i> Actualizar
                </button>
                <a class="btn btn-sm btn-secondary js-view-file" href="${viewUrl}" target="_blank" rel="noopener">
                    <i class="ti ti-eye me-1"></i> Ver
                </a>
                <button class="btn btn-sm btn-outline-danger js-delete-doc"
                        data-doc-type="${docType}"
                        data-doc-id="${payload.id}"
                        data-url="${destroyUrl}">
                    <i class="ti ti-trash me-1"></i> Eliminar
                </button>
            </div>`;

        $tr.find('.doc-actions').html(actionsHtml);

        // Si el doc estaba rechazado, limpia el motivo (es una nueva versión)
        $tr.find('.doc-label .text-danger').remove();
    }

    function setRowAsEmpty($tr) {
        $tr.find('.doc-status').html('<span class="badge bg-secondary">Sin cargar</span>');
        $tr.find('.doc-date').text('—');
        const docType = $tr.data('doc-type');
        $tr.find('.doc-actions').html(`
            <button class="btn btn-sm btn-primary js-open-upload" data-doc-type="${docType}" data-action="create">
                <i class="ti ti-upload me-1"></i> Subir
            </button>
        `);
        // Quitar motivo de rechazo si estaba presente
        $tr.find('.doc-label .text-danger').remove();
    }

    function recalcCounters() {
        let approved = 0, rejected = 0, uploaded = 0;
        $('#docsTable tbody tr').each(function () {
            const statusText = $(this).find('.doc-status .badge').text().trim().toLowerCase();
            if (statusText !== 'sin cargar' && statusText !== '—' && statusText !== '') uploaded++;
            if (statusText === 'aprobado') approved++;
            if (statusText === 'rechazado') rejected++;
        });
        $('#statApproved').text(approved);
        $('#statRejected').text(rejected);
        $('#statUploaded').text(uploaded);
    }

    function nowStr() {
        const d = new Date();
        const pad = (n) => (n<10?'0':'') + n;
        return d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate()) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    function docTypeToLabel(type) {
        const map = @json($labels);
        return map[type] || type.replaceAll('_',' ').replace(/\b\w/g, c => c.toUpperCase());
    }

    // Limpieza modal por si acaso
    $('#docModal').on('hidden.bs.modal', function () {
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('padding-right');
        $('.modal-backdrop').remove();
    });

    // Toast simple reutilizable (si ya lo tienes, omite)
    window.toastOk = window.toastOk || function (msg = 'Operación exitosa') {
        // Puedes reemplazar esto por tu SweetAlert toasty si ya está global
        console.log('[OK]', msg);
    };
});


// ===== DRAG AND DROP =====
(function () {
    const dropZone       = document.getElementById('dropZone');
    const fileInput      = document.getElementById('fileInput');
    const dropZonePrompt = document.getElementById('dropZonePrompt');
    const dropZonePreview= document.getElementById('dropZonePreview');
    const previewName    = document.getElementById('previewName');
    const previewSize    = document.getElementById('previewSize');
    const previewIcon    = document.getElementById('previewIcon');
    const removeFile     = document.getElementById('removeFile');
    const docModal       = document.getElementById('docModal');

    if (!dropZone || !fileInput) return;

    // Clic en la zona → abre el selector de archivo
    dropZone.addEventListener('click', function (e) {
        if (e.target.closest('#removeFile')) return;
        fileInput.click();
    });

    // Accesibilidad teclado
    dropZone.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fileInput.click(); }
    });

    // Drag events
    ['dragenter', 'dragover'].forEach(evt => {
        dropZone.addEventListener(evt, function (e) {
            e.preventDefault(); e.stopPropagation();
            dropZone.classList.add('drag-over');
        });
    });

    ['dragleave', 'dragend'].forEach(evt => {
        dropZone.addEventListener(evt, function (e) {
            // Ignorar si el cursor sigue dentro de la zona
            if (dropZone.contains(e.relatedTarget)) return;
            dropZone.classList.remove('drag-over');
        });
    });

    dropZone.addEventListener('drop', function (e) {
        e.preventDefault(); e.stopPropagation();
        dropZone.classList.remove('drag-over');
        const files = e.dataTransfer.files;
        if (!files.length) return;

        // Asignar al input real
        const dt = new DataTransfer();
        dt.items.add(files[0]);
        fileInput.files = dt.files;

        // Disparar change para que la validación existente de tamaño se ejecute
        fileInput.dispatchEvent(new Event('change', { bubbles: true }));
    });

    // Cuando cambia el input (click o drop)
    fileInput.addEventListener('change', function () {
        const file = this.files && this.files[0];
        if (file) {
            showPreview(file);
        } else {
            clearPreview();
        }
    });

    // Botón quitar archivo
    if (removeFile) {
        removeFile.addEventListener('click', function (e) {
            e.stopPropagation();
            fileInput.value = '';
            clearPreview();
            document.getElementById('formErrors').classList.add('d-none');
            document.getElementById('formErrors').innerHTML = '';
        });
    }

    function showPreview(file) {
        const ext = (file.name.split('.').pop() || '').toLowerCase();
        const isPdf   = ext === 'pdf';
        const isImage = ['jpg','jpeg','png','gif','webp'].includes(ext);

        previewName.textContent = file.name;
        previewSize.textContent = formatBytes(file.size);

        previewIcon.className = 'file-preview-icon';
        if (isPdf) {
            previewIcon.classList.add('is-pdf');
            previewIcon.innerHTML = '<i class="ti ti-file-type-pdf"></i>';
        } else if (isImage) {
            previewIcon.classList.add('is-image');
            previewIcon.innerHTML = '<i class="ti ti-photo"></i>';
        } else {
            previewIcon.innerHTML = '<i class="ti ti-file-text"></i>';
        }

        dropZonePrompt.classList.add('d-none');
        dropZonePreview.classList.remove('d-none');
        dropZone.classList.add('has-file');
        dropZone.classList.remove('drag-over');
    }

    function clearPreview() {
        dropZonePrompt.classList.remove('d-none');
        dropZonePreview.classList.add('d-none');
        dropZone.classList.remove('has-file', 'drag-over');
    }

    function formatBytes(bytes) {
        if (!bytes) return '0 B';
        const k = 1024, sizes = ['B','KB','MB','GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }

    // Limpiar preview cada vez que el modal se abre o cierra
    if (docModal) {
        docModal.addEventListener('show.bs.modal',   clearPreview);
        docModal.addEventListener('hidden.bs.modal', clearPreview);
    }
})();

</script>
<script>
(function() {
    // Tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(el => new bootstrap.Tooltip(el));

    // ── REPSE: habilitar/deshabilitar campos según el switch ────────────────
    (function () {
        const sw     = document.getElementById('provides_specialized_services');
        const notice = document.getElementById('repseDisabledNotice');
        if (!sw) return;

        function applyRepseState(active) {
            document.querySelectorAll(
                '#repseForm input:not(#provides_specialized_services):not([name="_token"]):not([type="hidden"]), ' +
                '#repseForm select, ' +
                '#repseForm textarea, ' +
                '#repseForm button'
            ).forEach(el => { el.disabled = !active; });

            notice?.classList.toggle('d-none', active);
        }

        sw.addEventListener('change', function () {
            this.nextElementSibling.textContent = this.checked ? 'Sí' : 'No';
            applyRepseState(this.checked);
        });

        applyRepseState(sw.checked);
    })();
    // ────────────────────────────────────────────────────────────────────────

    // Guardar
    $('#btnSaveRepse').on('click', function() {
        const form   = document.getElementById('repseForm');
        const url    = form.dataset.url;
        const method = form.dataset.method || 'PATCH';

        const fd = new FormData();
        fd.append('_method', method);
        fd.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

        // Normaliza checkbox a 0/1
        const provides = document.getElementById('provides_specialized_services').checked ? 1 : 0;
        fd.append('provides_specialized_services', provides);

        fd.append('repse_registration_number', document.querySelector('[name="repse_registration_number"]').value.trim().toUpperCase());
        fd.append('repse_expiry_date', document.querySelector('[name="repse_expiry_date"]').value);
        fd.append('specialized_services_types', document.getElementById('specialized_services_types_hidden').value);


        $.ajax({
            url: url,
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .done(function (json) {
            Swal.fire({ icon: 'success', title: 'Guardado', timer: 1400, showConfirmButton: false });
        })
        .fail(function (xhr) {
            let msg = 'No se pudo guardar.';
            if (xhr.status === 422 && xhr.responseJSON?.errors) {
                msg = Object.values(xhr.responseJSON.errors).flat().join('\n');
            }
            Swal.fire({ icon: 'error', title: 'Error', text: msg });
            console.error(xhr?.responseText || xhr);
        });
    });

    // Limpiar
    $('#btnClearRepse').on('click', function() {
        Swal.fire({
            title: '¿Limpiar campos?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, limpiar',
            cancelButtonText: 'Cancelar'
        }).then(res => {
            if (!res.isConfirmed) return;
            document.getElementById('provides_specialized_services').checked = false;
            document.querySelector('[name="repse_registration_number"]').value = '';
            document.querySelector('[name="repse_expiry_date"]').value = '';
            document.querySelector('[name="specialized_services_types"]').value = '';
        });
    });
})();
</script>
<script>
(function() {
    // ===== Utilidades de tags =====
    const hidden   = document.getElementById('specialized_services_types_hidden');
    const input    = document.getElementById('repseTagInput');
    const addBtn   = document.getElementById('repseAddTag');
    const box      = document.getElementById('repseTagsBox');

    function getTags() {
        try { return JSON.parse(hidden.value || '[]'); } catch { return []; }
    }
    function setTags(arr) {
        // Normaliza: trim, sin vacíos, únicos (case-insensitive)
        const norm = [];
        const seen = new Set();
        for (let v of arr) {
            if (typeof v !== 'string') continue;
            const t = v.trim();
            if (!t) continue;
            const key = t.toLowerCase();
            if (seen.has(key)) continue;
            seen.add(key);
            norm.push(t);
        }
        hidden.value = JSON.stringify(norm);
        renderTags();
    }
    function renderTags() {
        const tags = getTags();
        box.innerHTML = '';
        tags.forEach((t, idx) => {
            const pill = document.createElement('span');
            pill.className = 'badge bg-primary-subtle text-primary d-inline-flex align-items-center';
            pill.innerHTML = `<span class="me-1">${escapeHtml(t)}</span>
                              <button type="button" class="btn-close btn-close-white btn-close-sm ms-1" aria-label="Quitar" data-idx="${idx}"></button>`;
            box.appendChild(pill);
        });
    }
    function addCurrent() {
        const val = (input.value || '').trim();
        if (!val) return;
        const tags = getTags();
        tags.push(val);
        setTags(tags);
        input.value = '';
        input.focus();
    }
    function removeAt(idx) {
        const tags = getTags();
        tags.splice(idx, 1);
        setTags(tags);
    }
    function escapeHtml(s) {
        return s.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
    }

    // Inicializa desde el hidden
    renderTags();

    // Eventos
    addBtn.addEventListener('click', addCurrent);
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); addCurrent(); }
        // Borrar el último tag con Backspace si input está vacío
        if (e.key === 'Backspace' && !input.value) {
            const tags = getTags();
            if (tags.length) {
                tags.pop();
                setTags(tags);
            }
        }
    });
    box.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-idx]');
        if (!btn) return;
        removeAt(parseInt(btn.dataset.idx, 10));
    });

    // ===== Integración con tu guardado existente =====
    // En tu handler de #btnSaveRepse ya estabas armando FormData.
    // Asegúrate de tomar el hidden (JSON) en lugar de textarea:
    const saveBtn = document.getElementById('btnSaveRepse');
    if (saveBtn) {
        saveBtn.addEventListener('click', function() {
            // Si ya tienes un handler previo para guardar, evita duplicarlo.
            // Este bloque solo recuerda que specialized_services_types viaja como hidden.value (JSON).
            // Ejemplo (tu código ya lo hace con FormData):
            // fd.append('specialized_services_types', hidden.value);
        }, { once: false });
    }
})();
</script>
<script>
(function() {
    const selectEl  = document.getElementById('repseTypesSelect');
    const otrosWrap = document.getElementById('repseOtrosWrapper');
    const otrosText = document.getElementById('repseOtrosText');
    const hidden    = document.getElementById('specialized_services_types_hidden');

    // Mapa código => etiqueta (debe coincidir con Blade)
    const CODE_TO_LABEL = {
        limpieza:      'Servicios de limpieza',
        vigilancia:    'Vigilancia y seguridad',
        mantenimiento: 'Mantenimiento',
        alimentacion:  'Servicios de alimentación',
        contabilidad:  'Servicios contables/administrativos',
        sistemas:      'Servicios de sistemas/TI',
        otros:         'Otros'
    };

    function parseCSV(str) {
        return (str || '')
            .split(',')
            .map(s => s.trim())
            .filter(s => s.length > 0);
    }

    function buildJsonValue() {
        const selected = Array.from(selectEl.selectedOptions).map(o => o.value);
        const labels = [];

        // Agrega etiquetas de las opciones seleccionadas (menos "otros")
        selected.forEach(code => {
            if (code !== 'otros') {
                const label = CODE_TO_LABEL[code] || code;
                labels.push(label);
            }
        });

        // Manejo de "otros"
        if (selected.includes('otros')) {
            const extras = parseCSV(otrosText.value);
            labels.push(...extras);
        }

        // Normaliza duplicados (case-insensitive)
        const seen = new Set();
        const clean = [];
        for (const lbl of labels) {
            const k = lbl.toLowerCase();
            if (!seen.has(k)) {
                seen.add(k);
                clean.push(lbl);
            }
        }

        hidden.value = JSON.stringify(clean);
    }

    function toggleOtros() {
        const selected = Array.from(selectEl.selectedOptions).map(o => o.value);
        const show = selected.includes('otros');
        otrosWrap.classList.toggle('d-none', !show);
    }

    // Eventos
    selectEl.addEventListener('change', function() {
        toggleOtros();
        buildJsonValue();
    });
    if (otrosText) {
        otrosText.addEventListener('input', buildJsonValue);
    }

    // Inicializar al cargar
    toggleOtros();
    buildJsonValue();

    // Si ya tienes un handler de #btnSaveRepse con FormData,
    // asegúrate que uses el hidden:
    // fd.append('specialized_services_types', document.getElementById('specialized_services_types_hidden').value);
})();
</script>

<script>
(() => {
    // ── SIROC: habilitar campos cuando se escribe el número de registro ────
    (function () {
        const numInput = document.getElementById('siroc_number');
        const notice   = document.getElementById('sirocDisabledNotice');
        if (!numInput) return;

        function applySirocState(active) {
            document.querySelectorAll(
                '#sirocForm input:not(#siroc_number):not([name="_token"]):not([type="hidden"]), ' +
                '#sirocForm select, ' +
                '#sirocForm textarea, ' +
                '#sirocForm button'
            ).forEach(el => { el.disabled = !active; });

            notice?.classList.toggle('d-none', active);
        }

        numInput.addEventListener('input', function () {
            applySirocState(this.value.trim().length > 0);
        });

        applySirocState(numInput.value.trim().length > 0);
    })();
    // ────────────────────────────────────────────────────────────────────────

    const form      = document.getElementById('sirocForm');
    const btnSave   = document.getElementById('btnSaveSiroc');
    const btnClear  = document.getElementById('btnClearSiroc');
    const errBox    = document.getElementById('sirocErrors');
    const fileInput = document.getElementById('siroc_file');
    const fileName  = document.getElementById('sirocFileName');
    const tableBody = document.querySelector('#sirocTable tbody');

    // --- Helpers ---
    const setLoading = (el, isLoading, textIdle = 'Guardar SIROC') => {
        if (!el) return;
        el.disabled = !!isLoading;
        el.innerHTML = isLoading
            ? `<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Guardando...`
            : `<i class="ti ti-device-floppy me-1"></i> ${textIdle}`;
    };

    const buildErrorListHTML = (errorsObj) => {
        const list = Object.values(errorsObj || {}).flat();
        if (!list.length) return null;
        return `<ul class="text-start mb-0">${list.map(e => `<li>${e}</li>`).join('')}</ul>`;
    };

    const fmtDate = (s) => (s || '').slice(0, 10) || '—';

    const statusBadge = (status) => {
        const map = {
            vigente: {b: 'success', i: 'check', label: 'Vigente'},
            suspendido: {b: 'warning', i: 'alert-triangle', label: 'Suspendido'},
            terminado: {b: 'secondary', i: 'circle-off', label: 'Terminado'}
        };
        const m = map[status] || {b:'secondary', i:'info-circle', label: (status||'—')};
        return `<span class="badge bg-${m.b}"><i class="ti ti-${m.i} me-1"></i>${m.label}</span>`;
    };

    const buildRowHTML = (s) => {
        const pdfBtn = s.siroc_file_url
            ? `<a href="${s.siroc_file_url}" target="_blank" class="btn btn-xs btn-outline-primary">
                    <i class="ti ti-file-type-pdf"></i> PDF
               </a>`
            : `<span class="text-muted">—</span>`;

        // Ojo: los enlaces de Ver/Editar requieren IDs; asumimos que controlador devuelve 'id'
        return `
        <tr data-id="${s.id}">
            <td class="text-muted">—</td>
            <td><code>${s.siroc_number || '—'}</code></td>
            <td>${s.work_name || '—'}</td>
            <td class="text-truncate" style="max-width:260px;">${s.work_location || '—'}</td>
            <td>${fmtDate(s.start_date)}</td>
            <td>${fmtDate(s.end_date)}</td>
            <td>${statusBadge(s.status)}</td>
            <td>${pdfBtn}</td>
            <td class="text-end">
                <div class="d-flex justify-content-end gap-1">
                    <a href="${s.show_url || '#'}" class="btn btn-sm btn-outline-secondary" title="Ver"><i class="ti ti-eye"></i></a>
                    <a href="${s.edit_url || '#'}" class="btn btn-sm btn-outline-primary" title="Editar"><i class="ti ti-pencil"></i></a>
                    <button class="btn btn-sm btn-outline-danger js-del-siroc" data-url="${s.destroy_url || '#'}" title="Eliminar"><i class="ti ti-trash"></i></button>
                </div>
            </td>
        </tr>`;
    };

    const renumberRows = () => {
        tableBody.querySelectorAll('tr').forEach((tr, idx) => {
            const cell = tr.querySelector('td');
            if (cell) cell.textContent = idx + 1;
        });
    };

    // Mostrar nombre del archivo seleccionado
    fileInput?.addEventListener('change', () => {
        fileName.textContent = fileInput.files?.[0]?.name ?? 'Sin archivo seleccionado.';
    });

    // Validación simple de fechas (cliente)
    function validDates() {
        const s = form.querySelector('[name="start_date"]').value;
        const e = form.querySelector('[name="end_date"]').value;
        if (s && e && e < s) return 'La fecha de término debe ser mayor o igual a la fecha de inicio.';
        return null;
    }

    // Limpiar formulario
    btnClear?.addEventListener('click', async () => {
        const res = await Swal.fire({
            icon: 'question',
            title: '¿Limpiar formulario?',
            text: 'Se borrarán los campos capturados.',
            showCancelButton: true,
            confirmButtonText: 'Sí, limpiar',
            cancelButtonText: 'Cancelar',
        });
        if (!res.isConfirmed) return;

        form.reset();
        fileName.textContent = 'Sin archivo seleccionado.';
        errBox.classList.add('d-none');
        errBox.innerHTML = '';
        Swal.fire({ icon: 'info', title: 'Formulario limpio', timer: 1400, showConfirmButton: false });
    });

    // Guardar (AJAX)
    btnSave?.addEventListener('click', async () => {
        errBox.classList.add('d-none');
        errBox.innerHTML = '';

        const dateError = validDates();
        if (dateError) {
            Swal.fire({ icon: 'warning', title: 'Revisa las fechas', text: dateError });
            return;
        }

        const url    = form.dataset.url;
        const method = form.dataset.method || 'POST';
        const fd     = new FormData(form);

        try {
            setLoading(btnSave, true);

            const res = await fetch(url, {
                method,
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: fd
            });

            let json = {};
            try { json = await res.json(); } catch (_) {}

            if (!res.ok) {
                if (res.status === 422 && json?.errors) {
                    const html = buildErrorListHTML(json.errors);
                    errBox.classList.remove('d-none');
                    errBox.innerHTML = html ?? 'Ocurrió un error al guardar.';
                    Swal.fire({ icon: 'error', title: 'Datos inválidos', html: html ?? 'Revisa la información capturada.' });
                    return;
                }
                Swal.fire({ icon: 'error', title: 'Error al guardar', text: json?.message || 'Error inesperado.' });
                return;
            }

            // Éxito: insertar fila arriba
            const data = json?.data || {};
            // si desde tu controlador agregas URLs útiles:
            // data.show_url    = "{{ route('suppliers.sirocs.show', [$supplier, 'SIROC_ID']) }}".replace('SIROC_ID', data.id);
            // data.edit_url    = "{{ route('suppliers.sirocs.edit', [$supplier, 'SIROC_ID']) }}".replace('SIROC_ID', data.id);
            // data.destroy_url = "{{ route('suppliers.sirocs.destroy', [$supplier, 'SIROC_ID']) }}".replace('SIROC_ID', data.id);

            // si llega una fila vacía, evita romper la tabla
            const emptyRow = document.getElementById('sirocEmptyRow');
            if (emptyRow) emptyRow.remove();

            tableBody.insertAdjacentHTML('afterbegin', buildRowHTML(data));
            renumberRows();

            form.reset();
            fileName.textContent = 'Sin archivo seleccionado.';

            Swal.fire({ icon: 'success', title: '¡Éxito!', text: json?.message || 'SIROC guardado correctamente.', timer: 2000, showConfirmButton: false });

        } catch (err) {
            console.error(err);
            Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo conectar con el servidor.' });
        } finally {
            setLoading(btnSave, false);
        }
    });

    // Delegación para eliminar SIROC
    tableBody.addEventListener('click', async (ev) => {
        const btn = ev.target.closest('.js-del-siroc');
        if (!btn) return;

        const url = btn.dataset.url;
        const tr  = btn.closest('tr');

        const conf = await Swal.fire({
            icon: 'warning',
            title: '¿Eliminar SIROC?',
            text: 'Esta acción no se puede deshacer.',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        });
        if (!conf.isConfirmed) return;

        try {
            const res = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: new URLSearchParams({ _method: 'DELETE' })
            });

            if (!res.ok) {
                const j = await res.json().catch(() => ({}));
                Swal.fire({ icon: 'error', title: 'No se pudo eliminar', text: j?.message || 'Error inesperado.' });
                return;
            }

            tr.remove();
            renumberRows();

            // si quedó vacía, pinta fila "Sin registros"
            if (!tableBody.querySelector('tr')) {
                tableBody.insertAdjacentHTML('beforeend',
                    `<tr id="sirocEmptyRow"><td colspan="9" class="text-center text-muted py-3">Sin registros.</td></tr>`
                );
            }

            Swal.fire({ icon: 'success', title: 'Eliminado', timer: 1300, showConfirmButton: false });

        } catch (err) {
            console.error(err);
            Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo conectar con el servidor.' });
        }
    });
})();
</script>

<script>
(() => {
    const modalEl   = document.getElementById('sirocEditModal');
    const modal     = new bootstrap.Modal(modalEl);
    const form      = document.getElementById('editSirocForm');
    const errBox    = document.getElementById('editSirocErrors');
    const btnUpdate = document.getElementById('btnUpdateSiroc');

    let currentRow  = null;
    let updateUrl   = null;

    const setLoading = (is) => {
        btnUpdate.disabled = !!is;
        btnUpdate.innerHTML = is
            ? `<span class="spinner-border spinner-border-sm me-2"></span> Guardando...`
            : `<i class="ti ti-device-floppy me-1"></i> Guardar cambios`;
    };

    const statusBadge = (status) => {
        const map = {
            vigente:    {b:'success',   i:'check',          label:'Vigente'},
            suspendido: {b:'warning',   i:'alert-triangle', label:'Suspendido'},
            terminado:  {b:'secondary', i:'circle-off',     label:'Terminado'}
        };
        const m = map[status] || {b:'secondary', i:'info-circle', label: (status||'—')};
        return `<span class="badge bg-${m.b}"><i class="ti ti-${m.i} me-1"></i>${m.label}</span>`;
    };

    const fmt = (d) => (d || '').slice(0,10) || '—';

    // Abrir modal (prefill)
    document.addEventListener('click', (ev) => {
        const btn = ev.target.closest('.js-edit-siroc');
        if (!btn) return;

        currentRow = btn.closest('tr');
        updateUrl  = btn.dataset.updateUrl;

        form.siroc_number.value    = btn.dataset.sirocNumber || '';
        form.contract_number.value = btn.dataset.contractNumber || '';
        form.work_name.value       = btn.dataset.workName || '';
        form.work_location.value   = btn.dataset.workLocation || '';
        form.start_date.value      = btn.dataset.startDate || '';
        form.end_date.value        = btn.dataset.endDate || '';
        form.status.value          = btn.dataset.status || 'vigente';
        form.observations.value    = btn.dataset.observations || '';

        const link = document.getElementById('currentSirocFileLink');
        const fileUrl = btn.dataset.fileUrl || '';
        link.textContent = fileUrl ? 'Abrir PDF actual' : '—';
        link.href = fileUrl || '#';

        errBox.classList.add('d-none');
        errBox.innerHTML = '';

        modal.show();
    });

    // Enviar PUT (AJAX) y refrescar fila por CLASES
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const s = form.start_date.value;
        const x = form.end_date.value;
        if (s && x && x < s) {
            Swal.fire({icon:'warning', title:'Revisa las fechas', text:'La fecha de término debe ser >= inicio.'});
            return;
        }

        const fd = new FormData(form); // incluye _method=PUT
        errBox.classList.add('d-none');
        errBox.innerHTML = '';

        try {
            setLoading(true);
            const res = await fetch(updateUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: fd
            });

            let json = {};
            try { json = await res.json(); } catch(_) {}

            if (!res.ok) {
                if (res.status === 422 && json?.errors) {
                    const list = Object.values(json.errors).flat().map(e => `<li>${e}</li>`).join('');
                    errBox.classList.remove('d-none');
                    errBox.innerHTML = `<ul class="mb-0">${list}</ul>`;
                    Swal.fire({icon:'error', title:'Datos inválidos', html:`<ul class="text-start">${list}</ul>`});
                    return;
                }
                Swal.fire({icon:'error', title:'Error', text: json?.message || 'No se pudo actualizar.'});
                return;
            }

            const d = json?.data || {};

            // 🔧 Actualiza por CLASE (coinciden con la tabla del proveedor)
            const cNum  = currentRow.querySelector('.col-number');
            const cWork = currentRow.querySelector('.col-work');
            const cLoc  = currentRow.querySelector('.col-location');
            const cIni  = currentRow.querySelector('.col-start');
            const cFin  = currentRow.querySelector('.col-end');
            const cSta  = currentRow.querySelector('.col-status');
            const cFile = currentRow.querySelector('.col-file');

            if (cNum)  cNum.innerHTML = `<code>${d.siroc_number || '—'}</code>`;
            if (cWork) cWork.textContent = d.work_name || '—';
            if (cLoc)  cLoc.textContent  = d.work_location || '—';
            if (cIni)  cIni.textContent  = fmt(d.start_date);
            if (cFin)  cFin.textContent  = fmt(d.end_date);
            if (cSta)  cSta.innerHTML    = statusBadge(d.status);
            if (cFile) cFile.innerHTML   = d.siroc_file_url
                ? `<a href="${d.siroc_file_url}" target="_blank" class="btn btn-xs btn-outline-primary">
                        <i class="ti ti-file-type-pdf"></i> PDF
                   </a>`
                : `<span class="text-muted">—</span>`;

            // Refresca data-* del botón Editar
            const editBtn = currentRow.querySelector('.js-edit-siroc');
            if (editBtn) {
                editBtn.dataset.sirocNumber    = d.siroc_number || '';
                editBtn.dataset.contractNumber = d.contract_number || '';
                editBtn.dataset.workName       = d.work_name || '';
                editBtn.dataset.workLocation   = d.work_location || '';
                editBtn.dataset.startDate      = d.start_date ? d.start_date.substring(0,10) : '';
                editBtn.dataset.endDate        = d.end_date ? d.end_date.substring(0,10) : '';
                editBtn.dataset.status         = d.status || 'vigente';
                editBtn.dataset.observations   = d.observations || '';
                editBtn.dataset.fileUrl        = d.siroc_file_url || '';
            }

            Swal.fire({icon:'success', title:'Actualizado', timer:1600, showConfirmButton:false});
            modal.hide();

        } catch (err) {
            console.error(err);
            Swal.fire({icon:'error', title:'Error de red', text:'No se pudo conectar con el servidor.'});
        } finally {
            setLoading(false);
            form.querySelector('#edit_siroc_file').value = '';
        }
    });
})();
</script>
@endpush
