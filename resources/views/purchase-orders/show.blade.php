@extends('layouts.zircos')

@section('title', 'Detalle de Orden de Compra: ' . $purchaseOrder->folio)
@section('page.title', 'Detalle de Orden de Compra')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('purchase-orders.index') }}">Ordenes de Compra</a></li>
    <li class="breadcrumb-item active">{{ $purchaseOrder->folio }}</li>
@endsection

@push('styles')
<style>
    @media print {
        .d-print-none { display: none !important; }
        .card { box-shadow: none !important; border: 0 !important; }
        footer, .topbar, .sidenav-menu, .page-title-head { display: none !important; }
        .page-content, .page-container { padding: 0 !important; margin: 0 !important; }
    }
    .js-append-supplier-note { transition: box-shadow .18s ease, transform .18s ease; }
    .js-append-supplier-note:hover { box-shadow: 0 .25rem .65rem rgba(24, 138, 226, .18); transform: translateY(-1px); }
    @media (prefers-reduced-motion: reduce) { .js-append-supplier-note { transition: none; } }
</style>
@endpush

@section('content')
@if($purchaseOrder->isPendingApproval() && (int) $purchaseOrder->assigned_approver_id !== (int) auth()->id() && $purchaseOrder->isApproverFor(auth()->user()))
    <div class="alert alert-info border-0 shadow-sm">
        <i class="ti ti-user-share me-1"></i>
        <strong>Autorización delegada por {{ $purchaseOrder->assignedApprover?->name }}.</strong>
        Si actúas, quedará registrado que lo hiciste en su representación.
    </div>
@endif
@php
    $delegatedDecision = $purchaseOrder->approvalDecisions
        ->whereNotNull('approval_delegation_id')
        ->sortByDesc('acted_at')
        ->first();
@endphp
@if($delegatedDecision)
    @php
        $delegatedActionLabel = match($delegatedDecision->action) {
            'APPROVED' => 'Autorizado',
            'REJECTED' => 'Rechazado',
            'RETURNED' => 'Devuelto',
            default => $delegatedDecision->action,
        };
    @endphp
    <div class="alert alert-light border">
        <i class="ti ti-history me-1"></i>
        {{ $delegatedActionLabel }} por
        <strong>{{ $delegatedDecision->actor?->name }}</strong>,
        en representación de <strong>{{ $delegatedDecision->principal?->name }}</strong>
        el {{ $delegatedDecision->acted_at?->format('d/m/Y H:i') }}.
    </div>
@endif
@php
    $canAuthorize = $purchaseOrder->isPendingApproval()
        && $purchaseOrder->isApproverFor(Auth::user());
    $canAppendSupplierNote = Auth::user()?->hasAnyRole(['buyer', 'superadmin'])
        && in_array($purchaseOrder->status, ['ISSUED', 'PARTIALLY_RECEIVED', 'DELIVERED_PENDING_RECEPTION'], true);
@endphp

<div class="d-flex justify-content-between align-items-center mb-3 d-print-none">
    <a href="{{ route('purchase-orders.index') }}" class="btn btn-outline-secondary">
        <i class="ti ti-arrow-left me-1"></i>Regresar
    </a>

    <div>
        @if($canAuthorize)
            <button type="button" class="btn btn-success me-1" onclick="confirmApprovePo()">
                <i class="ti ti-check me-1"></i>Autorizar OC
            </button>
            <button type="button" class="btn btn-danger me-1" onclick="confirmRejectPo()">
                <i class="ti ti-x me-1"></i>Rechazar OC
            </button>
        @endif
        @if(in_array($purchaseOrder->status, ['ISSUED', 'PARTIALLY_RECEIVED', 'RECEIVED', 'PAID', 'DELIVERED_PENDING_RECEPTION'], true))
            <a href="{{ route('purchase-orders.pdf', $purchaseOrder) }}" class="btn btn-outline-danger me-1">
                <i class="ti ti-file-type-pdf me-1"></i>Generar PDF
            </a>
        @endif
        <button type="button" onclick="window.print();" class="btn btn-primary">
            <i class="ti ti-printer me-1"></i>Imprimir OC
        </button>
    </div>
</div>

@if($purchaseOrder->isPendingApproval())
    <div class="alert alert-warning d-print-none">
        <i class="ti ti-clock me-1"></i>
        Esta OC proviene de un contrato por <strong>convenio de precios</strong> y está pendiente de autorización de
        <strong>{{ $purchaseOrder->assignedApprover->name ?? '—' }}</strong>
        @if($purchaseOrder->authorizerRole)
            ({{ $purchaseOrder->authorizerRole->name }}, límite
            ${{ number_format((float) $purchaseOrder->effective_authorization_limit, 2) }})
        @endif
        — no se emitirá al proveedor hasta autorizarse.
    </div>
@elseif($purchaseOrder->isRejected())
    <div class="alert alert-danger d-print-none">
        <i class="ti ti-ban me-1"></i>
        OC rechazada por {{ $purchaseOrder->approvals->where('action', 'REJECTED')->last()?->approver?->name ?? '—' }}
        el {{ $purchaseOrder->rejected_at?->format('d/m/Y H:i') ?? '—' }}.
        @if($reason = $purchaseOrder->approvals->where('action', 'REJECTED')->last()?->comments)
            <br><strong>Motivo:</strong> {{ $reason }}
        @endif
    </div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-body p-4 p-lg-5">
        <div class="row align-items-start mb-4">
            <div class="col-md-7">
                <h2 class="mb-1">Orden de Compra</h2>
                <div class="text-muted">{{ $purchaseOrder->folio }}</div>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0">
                <span class="badge bg-{{ $purchaseOrder->getStatusBadgeClass() }} fs-6">
                    {{ $purchaseOrder->getStatusLabel() }}
                </span>
                <div class="small text-muted mt-2">
                    Emitida: {{ $purchaseOrder->created_at?->format('d/m/Y H:i') ?? '-' }}
                </div>
                @if ($purchaseOrder->approved_at)
                    <div class="small text-muted">
                        Aprobada: {{ $purchaseOrder->approved_at->format('d/m/Y H:i') }}
                    </div>
                @endif
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="border rounded p-3 h-100">
                    <h6 class="text-uppercase text-muted mb-3">Proveedor</h6>
                    <div class="fw-bold">{{ $purchaseOrder->supplier->company_name ?? '-' }}</div>
                    <div class="small text-muted mt-2">RFC</div>
                    <div>{{ $purchaseOrder->supplier->rfc ?? '-' }}</div>
                    <div class="small text-muted mt-2">Contacto</div>
                    <div>{{ $purchaseOrder->supplier->contact_person ?? '-' }}</div>
                    <div class="small text-muted mt-2">Correo</div>
                    <div>{{ $purchaseOrder->supplier->email ?? '-' }}</div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="border rounded p-3 h-100">
                    <h6 class="text-uppercase text-muted mb-3">Requisicion Origen</h6>
                    <div class="fw-bold">{{ $purchaseOrder->requisition->folio ?? '-' }}</div>
                    <div class="small text-muted mt-2">Empresa</div>
                    <div>{{ $purchaseOrder->requisition->company->name ?? '-' }}</div>
                    <div class="small text-muted mt-2">Solicitado por</div>
                    <div>
                        {{ $purchaseOrder->requisition->requester->name
                            ?? $purchaseOrder->requisition->creator->name
                            ?? '-' }}
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="border rounded p-3 h-100">
                    <h6 class="text-uppercase text-muted mb-3">Entrega y Compra</h6>
                    <div class="small text-muted">Punto de entrega</div>
                    <div>
                        {{ $purchaseOrder->receivingLocation
                            ? $purchaseOrder->receivingLocation->code . ' - ' . $purchaseOrder->receivingLocation->name
                            : '-' }}
                    </div>
                    <div class="small text-muted mt-2">Condiciones de pago</div>
                    <div>{{ $purchaseOrder->payment_terms ?? '-' }}</div>
                    <div class="small text-muted mt-2">Moneda</div>
                    <div>{{ $purchaseOrder->currency ?? 'MXN' }}</div>
                    <div class="small text-muted mt-2">Generado por</div>
                    <div>{{ $purchaseOrder->creator->name ?? '-' }}</div>
                </div>
            </div>
        </div>

        <div class="table-responsive mb-4">
            <table class="table table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Descripcion</th>
                        <th class="text-center">Cantidad</th>
                        <th class="text-center">Unidad</th>
                        <th class="text-end">P. Unitario</th>
                        <th class="text-end">IVA</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($purchaseOrder->items as $index => $item) { ?>
                        <?php
                            $requisitionItem = $item->requisitionItem;
                        $currencySymbol = ($purchaseOrder->currency ?? 'MXN') === 'USD' ? 'US$' : '$';
                        $ivaRate = $item->subtotal > 0
                            ? round(((float) $item->iva_amount / (float) $item->subtotal) * 100)
                            : 16;
                        ?>
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>
                                <div class="fw-semibold">{{ $item->description }}</div>
                                @if ($requisitionItem?->notes)
                                    <div class="small text-primary mt-1"><i class="ti ti-note me-1"></i><strong>Nota para proveedor:</strong> {!! nl2br(e($requisitionItem->notes)) !!}</div>
                                @endif
                                @if ($canAppendSupplierNote && $requisitionItem)
                                    <button type="button"
                                            class="btn btn-sm btn-outline-primary mt-2 js-append-supplier-note"
                                            data-action="{{ route('purchase-orders.items.supplier-note', [$purchaseOrder, $item]) }}"
                                            data-description="{{ $item->description }}">
                                        <i class="ti ti-message-plus me-1"></i>{{ $requisitionItem->notes ? 'Anexar nota de Compras' : 'Agregar nota para proveedor' }}
                                    </button>
                                @endif
                            </td>
                            <td class="text-center">{{ number_format((float) $item->quantity, 2) }}</td>
                            <td class="text-center">{{ $requisitionItem->unit ?? '-' }}</td>
                            <td class="text-end">{{ $currencySymbol }}{{ number_format((float) $item->unit_price, 2) }}</td>
                            <td class="text-end">{{ $ivaRate }}%</td>
                            <td class="text-end fw-bold">{{ $currencySymbol }}{{ number_format((float) $item->total, 2) }}</td>
                        </tr>
                    <?php } ?>
                </tbody>
                <tfoot>
                    @php
                        $currencySymbol = ($purchaseOrder->currency ?? 'MXN') === 'USD' ? 'US$' : '$';
                    @endphp
                    <tr>
                        <td colspan="6" class="text-end fw-semibold">Subtotal</td>
                        <td class="text-end">{{ $currencySymbol }}{{ number_format((float) $purchaseOrder->subtotal, 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="6" class="text-end fw-semibold">IVA</td>
                        <td class="text-end">{{ $currencySymbol }}{{ number_format((float) $purchaseOrder->iva_amount, 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="6" class="text-end fw-bold fs-5">Total</td>
                        <td class="text-end fw-bold fs-5">{{ $currencySymbol }}{{ number_format((float) $purchaseOrder->total, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        @if ($purchaseOrder->requisition->description)
            <div class="border rounded p-3 mb-4">
                <h6 class="text-uppercase text-muted mb-2">Observaciones de la requisicion</h6>
                <div>{{ $purchaseOrder->requisition->description }}</div>
            </div>
        @endif

        <div class="card border mt-4 d-print-none">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Historial de Recepciones</h6>
                @if ($purchaseOrder->canBeReceived())
                    <a href="{{ route('receptions.create', $purchaseOrder) }}" class="btn btn-sm btn-outline-success">
                        <i class="ti ti-package-import me-1"></i>Registrar Recepcion
                    </a>
                @endif
            </div>
            <div class="card-body p-0">
                @if ($purchaseOrder->receptions->isEmpty())
                    <div class="text-center text-muted py-4">
                        Aun no hay recepciones registradas para esta orden.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Folio</th>
                                    <th>Fecha</th>
                                    <th>Receptor</th>
                                    <th>Punto de entrega</th>
                                    <th class="text-center">Partidas</th>
                                    <th class="text-center">Estado</th>
                                    <th class="text-center">Detalle</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($purchaseOrder->receptions->sortByDesc('received_at') as $reception)
                                    <tr>
                                        <td class="fw-semibold">{{ $reception->folio }}</td>
                                        <td>{{ $reception->received_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                        <td>{{ $reception->receiver->name ?? '-' }}</td>
                                        <td>{{ $reception->receivingLocation->name ?? '-' }}</td>
                                        <td class="text-center">{{ $reception->items->count() }}</td>
                                        <td class="text-center">
                                            <span class="badge bg-{{ $reception->getStatusBadgeClass() }}">
                                                {{ $reception->getStatusLabel() }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <a href="{{ route('receptions.show', $reception) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="ti ti-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@if($canAuthorize)
    <form id="form-approve-po" action="{{ route('purchase-orders.approve', $purchaseOrder) }}" method="POST" class="d-none">@csrf</form>
    <form id="form-reject-po" action="{{ route('purchase-orders.reject', $purchaseOrder) }}" method="POST" class="d-none">
        @csrf
        <input type="hidden" name="comments" id="reject-po-comments">
    </form>
@endif
@if($canAppendSupplierNote)
    <form id="form-append-supplier-note" method="POST" class="d-none">
        @csrf
        <input type="hidden" name="note" id="append-supplier-note-value">
    </form>
@endif
@endsection

@push('scripts')
<script>
    @if(session('success'))
        Swal.fire({ title: '¡Operación Exitosa!', text: @json(session('success')), icon: 'success', confirmButtonColor: '#28a745' });
    @endif

    @if($canAuthorize)
    function confirmApprovePo() {
        Swal.fire({
            title: '¿Autorizar esta OC?',
            text: 'Se emitirá al proveedor y se comprometerá el presupuesto.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            confirmButtonText: 'Sí, autorizar',
            cancelButtonText: 'Cancelar',
        }).then((r) => { if (r.isConfirmed) document.getElementById('form-approve-po').submit(); });
    }

    function confirmRejectPo() {
        Swal.fire({
            title: 'Rechazar OC',
            input: 'textarea',
            inputLabel: 'Motivo del rechazo (mínimo 50 caracteres)',
            inputAttributes: { minlength: 50, maxlength: 500 },
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Rechazar',
            cancelButtonText: 'Cancelar',
            preConfirm: (value) => {
                if (!value || value.trim().length < 50) {
                    Swal.showValidationMessage('El motivo debe tener al menos 50 caracteres.');
                    return false;
                }
                return value;
            },
        }).then((r) => {
            if (r.isConfirmed) {
                document.getElementById('reject-po-comments').value = r.value;
                document.getElementById('form-reject-po').submit();
            }
        });
    }
    @endif

    @if($canAppendSupplierNote)
    document.querySelectorAll('.js-append-supplier-note').forEach((button) => {
        button.addEventListener('click', () => {
            Swal.fire({
                title: 'Nota adicional de Compras',
                text: `Esta nota se anexará a la instrucción existente para el proveedor en: ${button.dataset.description}`,
                input: 'textarea',
                inputLabel: 'Instrucción adicional (opcional antes de abrir este diálogo)',
                inputPlaceholder: 'Ej. Favor de entregar con remisión sellada.',
                inputAttributes: { maxlength: 1000, 'aria-label': 'Nota adicional para proveedor' },
                showCancelButton: true,
                confirmButtonColor: '#188ae2',
                confirmButtonText: 'Anexar nota',
                cancelButtonText: 'Cancelar',
                preConfirm: (value) => {
                    if (!value || value.trim().length < 3) {
                        Swal.showValidationMessage('Captura una nota de al menos 3 caracteres.');
                        return false;
                    }
                    return value.trim();
                },
            }).then((result) => {
                if (!result.isConfirmed) return;
                document.getElementById('form-append-supplier-note').action = button.dataset.action;
                document.getElementById('append-supplier-note-value').value = result.value;
                document.getElementById('form-append-supplier-note').submit();
            });
        });
    });
    @endif
</script>
@endpush
