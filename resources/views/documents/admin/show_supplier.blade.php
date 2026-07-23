@extends('layouts.zircos')

@php
    use Illuminate\Support\Facades\Storage;

    // Etiquetas amigables
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

    function badge_status($s) {
        return match ($s) {
            'accepted'       => '<span class="badge bg-success">Aprobado</span>',
            'rejected'       => '<span class="badge bg-danger">Rechazado</span>',
            'pending_review' => '<span class="badge bg-warning text-dark">En revisión</span>',
            default          => '<span class="badge bg-secondary">—</span>',
        };
    }

    // KPIs por proveedor
    $kpiTotal   = $supplierRequirements->count();
    $kpiSubidos = $supplierRequirements->whereIn('status', ['submitted', 'compliant', 'rejected', 'expired'])->count();
    $kpiAprob   = $supplierRequirements->where('status', 'compliant')->count();
    $kpiRech    = $supplierRequirements->whereIn('status', ['rejected', 'expired'])->count();
@endphp

@section('title', 'Proveedor: ' . ($supplier->company_name ?? '—'))

@push('styles')
<style>
    .stat-card .value { font-size: 1.35rem; font-weight: 700; }
    .stat-card .label { font-size: .85rem; color: #6c757d; }
    .table td, .table th { vertical-align: middle; }
    .doc-label { font-weight: 600; }
    .history-badge { font-size: .75rem; }
</style>
@endpush

@section('page.title', 'Detalle de proveedor')
@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.review.index') }}">Revisión</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.review.suppliers') }}">Proveedores</a></li>
    <li class="breadcrumb-item active">{{ $supplier->company_name ?? 'Proveedor' }}</li>
@endsection

@section('content')

    {{-- Encabezado proveedor --}}
    <div class="card mb-3">
        <div class="card-body d-md-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-1">{{ $supplier->company_name ?? '—' }}</h4>
                <div class="text-muted">
                    <i class="ti ti-id me-1"></i> RFC: <strong>{{ $supplier->rfc ?? '—' }}</strong>
                    <span class="mx-2">·</span>
                    <i class="ti ti-mail me-1"></i> {{ $supplier->email ?? '—' }}
                    @if(!empty($supplier->phone_number))
                        <span class="mx-2">·</span>
                        <i class="ti ti-phone me-1"></i> {{ $supplier->phone_number }}
                    @endif
                </div>
            </div>
            <div class="mt-3 mt-md-0">
                <a href="{{ route('admin.review.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="ti ti-arrow-left"></i> Volver
                </a>
            </div>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <div class="value">{{ $kpiTotal }}</div>
                        <div class="label">Requeridos</div>
                    </div>
                    <i class="ti ti-list-details fs-2 text-primary"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <div class="value">{{ $kpiSubidos }}</div>
                        <div class="label">Cargados</div>
                    </div>
                    <i class="ti ti-upload fs-2 text-info"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <div class="value">{{ $kpiAprob }}</div>
                        <div class="label">Aprobados</div>
                    </div>
                    <i class="ti ti-checks fs-2 text-success"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <div class="value">{{ $kpiRech }}</div>
                        <div class="label">Rechazados</div>
                    </div>
                    <i class="ti ti-x fs-2 text-danger"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Matriz de documentos requeridos --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Documentos requeridos</h5>
            <span class="text-muted small">Última versión por documento</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
            <table class="table table-sm table-striped align-middle w-100">
                <thead>
                    <tr>
                        <th style="width:26%">Documento</th>
                        <th style="width:12%">Estado</th>
                        <th style="width:14%">Última carga</th>
                        <th style="width:18%">Subido por</th>
                        <th style="width:18%">Revisado por</th>
                        <th style="width:12%">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($requiredTypes as $type)
                        @php
                            /** @var \Illuminate\Support\Collection $versions */
                            $versions = $docsByType->get($type, collect());
                            // última versión (la colección viene orderByDesc('uploaded_at'))
                            $doc = $versions->first();
                            $label = $labels[$type] ?? ucfirst(str_replace('_',' ',$type));
                            $statusHtml = $doc ? badge_status($doc->status) : '<span class="badge bg-secondary">Sin cargar</span>';
                            $uploadedAt = $doc?->uploaded_at ?? $doc?->created_at;
                            $uploadedAtStr = $uploadedAt ? $uploadedAt->format('Y-m-d H:i') : '—';
                            $uploader = $doc?->uploader?->name ?? '—';
                            $reviewer = $doc?->reviewer?->name ?? '—';
                            $reviewedAt = $doc?->reviewed_at ? $doc->reviewed_at->format('Y-m-d H:i') : '—';
                            $viewUrl = $doc ? route('supplier-documents.file', $doc) : null;
                        @endphp
                        <tr data-doc-type="{{ $type }}">
                            <td class="doc-label">
                                {{ $label }}
                                @if ($type == "repse")
                                    <span class="badge text-bg-success">{{ $supplier->repse_registration_number }}</span>
                                @endif
                                @if($doc && $doc->status === 'rejected' && $doc->rejection_reason)
                                    <div class="small text-danger mt-1">
                                        <i class="ti ti-alert-circle me-1"></i>
                                        Motivo: {{ $doc->rejection_reason }}
                                    </div>
                                @endif
                            </td>
                            <td class="doc-status">{!! $statusHtml !!}</td>
                            <td class="doc-date">{{ $uploadedAtStr }}</td>
                            <td>{{ $uploader }}</td>
                            <td>
                                @if($doc && $doc->status !== 'pending_review')
                                    {{ $reviewer }} <span class="text-muted">· {{ $reviewedAt }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-nowrap doc-actions">
                                @php
                                    $feedbackUrl = route('documents.suppliers.feedback', [
                                        'supplier' => $supplier->id,
                                        'type'     => $type,
                                        'doc'      => $doc->id ?? null, // puede ser null
                                    ]);
                                @endphp

                                <div class="d-flex justify-content-end gap-1">
                                        @if($doc)
                                            <a href="{{ $viewUrl }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="Abrir"><i class="ti ti-eye"></i></a>
                                            @if($doc->status === 'pending_review')
                                                <a href="{{ route('admin.review.index', ['queue_search' => $supplier->rfc]) }}"
                                                    class="btn btn-sm btn-outline-primary" title="Revisar en bandeja">
                                                    <i class="ti ti-clipboard-check"></i>
                                                </a>
                                            @endif
                                        @endif
                                        {{-- Retroalimentación: SIEMPRE visible --}}
                                        <a href="javascript:void(0);" class="btn btn-sm btn-outline-info js-feedback-doc"
                                            data-url="{{ $feedbackUrl }}"
                                            data-supplier="{{ $supplier->id }}"
                                            data-type="{{ $type }}"
                                            data-doc="{{ $doc->id ?? '' }}"
                                            title="Retroalimentación"><i class="ti ti-message-dots"></i></a>
                                </div>
                            </td>
                        </tr>
                    @endforeach

                    @if(empty($requiredTypes) || count($requiredTypes) === 0)
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No se definieron tipos requeridos.</td>
                        </tr>
                    @endif
                </tbody>
            </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
// Nada especial aquí; tus handlers globales de .js-accept-doc y .js-reject-doc ya funcionan.
// Si quieres actualizar KPIs del encabezado tras aprobar/rechazar, ajusta aquí:

function refreshSupplierKpisAfter(action) {
    const $ap = $('.stat-card .value').eq(2); // Aprobados (tercer card)
    const $rj = $('.stat-card .value').eq(3); // Rechazados (cuarto card)

    if (action === 'accept') {
        $ap.text(parseInt($ap.text()) + 1);
    } else if (action === 'reject') {
        $rj.text(parseInt($rj.text()) + 1);
    }
}

// CSRF para todas las peticiones AJAX (si ya lo hiciste global, omite esto)
$.ajaxSetup({
    headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')}
});

// ACEPTAR
$(document).on('click', '.js-accept-doc', function (e) {
    e.preventDefault();
    const url = $(this).data('url');
    const $row = $(this).closest('tr');

    Swal.fire({
        title: '¿Aprobar documento?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, aprobar',
        cancelButtonText: 'Cancelar'
    }).then(res => {
        if (!res.isConfirmed) return;

        $.post(url)
        .done(function () {
            // Cambia badge y acciones de la fila
            $row.find('.doc-status').html('<span class="badge bg-success">Aprobado</span>');
            // Oculta "Aprobar" y deja "Rechazar"
            $row.find('.js-accept-doc').closest('li').remove();
            if ($row.find('.js-reject-doc').length === 0) {
                // por si no existía, la agregas (opcional)
            }
            Swal.fire({ icon: 'success', title: 'Aprobado', timer: 1400, showConfirmButton: false });
        })
        .fail(function (xhr) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo aprobar.' });
            console.error(xhr?.responseText || xhr);
        });
    });
});

// RECHAZAR
$(document).on('click', '.js-reject-doc', function (e) {
    e.preventDefault();
    const url = $(this).data('url');
    const $row = $(this).closest('tr');

    Swal.fire({
        title: 'Rechazar documento',
        input: 'textarea',
        inputLabel: 'Motivo de rechazo',
        inputPlaceholder: 'Escribe el motivo…',
        showCancelButton: true,
        confirmButtonText: 'Rechazar',
        cancelButtonText: 'Cancelar',
        preConfirm: (reason) => {
            if (!reason || reason.trim().length < 5) {
                Swal.showValidationMessage('El motivo es obligatorio (mín. 5 caracteres).');
            }
            return reason;
        }
    }).then(res => {
        if (!res.isConfirmed) return;

        $.post(url, { reason: res.value })
        .done(function () {
            // Cambia badge y acciones de la fila
            $row.find('.doc-status').html('<span class="badge bg-danger">Rechazado</span>');
            // Oculta "Rechazar" y deja "Aprobar"
            $row.find('.js-reject-doc').closest('li').remove();
            if ($row.find('.js-accept-doc').length === 0) {
                // por si no existía, la agregas (opcional)
            }
            Swal.fire({ icon: 'success', title: 'Rechazado', timer: 1400, showConfirmButton: false });
        })
        .fail(function (xhr) {
            let msg = 'No se pudo rechazar.';
            if (xhr.status === 422 && xhr.responseJSON?.errors?.reason) {
                msg = xhr.responseJSON.errors.reason.join('\n');
            }
            Swal.fire({ icon: 'error', title: 'Error', text: msg });
            console.error(xhr?.responseText || xhr);
        });
    });
});

// RETROALIMENTACIÓN (envía correo al proveedor)
$(document).on('click', '.js-feedback-doc', function (e) {
    e.preventDefault();
    const url       = $(this).data('url');      // ruta para enviar el correo
    const type      = $(this).data('type');     // tipo de documento (string)
    const docId     = $(this).data('doc') || ''; // id del doc (puede venir vacío)
    const labelCell = $(this).closest('tr').find('.doc-label').text().trim();

    Swal.fire({
        title: 'Enviar retroalimentación',
        html: `
            <div class="text-start">
                <div class="mb-2 small text-muted">
                    <i class="ti ti-file-description me-1"></i>
                    Documento / Tipo: <strong>${labelCell || type}</strong>
                </div>
                <textarea id="swal-feedback-message" class="form-control" rows="5" placeholder="Escribe la retroalimentación para el proveedor..."></textarea>
                <div class="form-text mt-1">
                    Este mensaje se enviará por correo al contacto del proveedor.
                </div>
            </div>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Enviar',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            const message = document.getElementById('swal-feedback-message').value || '';
            if (message.trim().length < 5) {
                Swal.showValidationMessage('El mensaje es obligatorio (mín. 5 caracteres).');
                return false;
            }
            return { message };
        }
    }).then(res => {
        if (!res.isConfirmed) return;

        $.post(url, { feedback: res.value.message, doc_id: docId, type: type })
            .done(() => {
                Swal.fire({ icon: 'success', title: 'Enviado', text: 'La retroalimentación fue enviada al proveedor.', timer: 1600, showConfirmButton: false });
            })
            .fail(xhr => {
                let msg = 'No se pudo enviar la retroalimentación.';
                if (xhr.responseJSON?.message) {
                    msg = xhr.responseJSON.message;
                } else if (xhr.status === 422 && xhr.responseJSON?.errors?.message) {
                    msg = xhr.responseJSON.errors.message.join('\n');
                }
                Swal.fire({ icon: 'error', title: 'Error', text: msg });
                console.error(xhr?.responseText || xhr);
            });
    });
});
</script>
@endpush
