@extends('layouts.zircos')

@section('title', 'Buzon de Autorizaciones')
@section('page.title', 'Buzon de Autorizaciones')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Autorizacion de cotizaciones</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 text-primary">
                    <i class="ti ti-clipboard-check me-1"></i>Selecciones pendientes de tu firma
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle" id="approvals-table">
                        <thead class="table-light">
                            <tr>
                                <th>RFQ</th>
                                <th>Requisicion</th>
                                <th>Solicitante</th>
                                <th>Proveedor seleccionado</th>
                                <th>Total con IVA</th>
                                <th>Rol autorizador</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pendingApprovals as $summary)
                                @php
                                    $summaryItems = $summary->items;
                                    $itemsJson = $summaryItems->map(fn ($item) => [
                                        'id' => $item->id,
                                        'desc' => $item->requisitionItem->description ?? 'Sin descripcion',
                                        'quotedQty' => (float) $item->quoted_quantity,
                                        'approvedQty' => (float) $item->approved_quantity,
                                        'unitPrice' => (float) $item->unit_price,
                                        'ivaRate' => (float) $item->iva_rate,
                                        'price' => number_format((float) $item->unit_price, 2),
                                        'total' => number_format((float) $item->total, 2),
                                        'notes' => $item->approver_notes,
                                    ])->values()->toJson();
                                @endphp
                                <tr>
                                    <td>{{ $summary->rfq?->folio ?? 'N/A' }}</td>
                                    <td>{{ $summary->requisition?->folio ?? 'N/A' }}</td>
                                    <td>{{ $summary->requester?->name ?? 'N/A' }}</td>
                                    <td>{{ $summary->selectedSupplier?->company_name ?? 'N/A' }}</td>
                                    <td class="fw-bold">${{ number_format((float) $summary->total, 2) }}</td>
                                    <td>
                                        <span class="badge bg-soft-primary text-primary">
                                            {{ $summary->authorizerRole?->name ?? 'Sin rol' }}
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary btn-review"
                                            data-id="{{ $summary->id }}"
                                            data-rfq="{{ $summary->rfq?->folio }}"
                                            data-folio="{{ $summary->requisition?->folio }}"
                                            data-total="{{ number_format((float) $summary->total, 2) }}"
                                            data-justification="{{ $summary->justification }}"
                                            data-supplier="{{ $summary->selectedSupplier?->company_name ?? 'N/A' }}"
                                            data-payment="{{ $summaryItems->first()?->rfqResponse?->payment_terms ?? 'No especificado' }}"
                                            data-delivery="{{ $summaryItems->max(fn ($item) => (int) ($item->rfqResponse?->delivery_days ?? 0)) }}"
                                            data-role="{{ $summary->authorizerRole?->name ?? 'Sin rol' }}"
                                            data-limit="{{ $summary->effective_authorization_limit !== null ? number_format((float) $summary->effective_authorization_limit, 2) : 'Sin limite' }}"
                                            data-budget='@json($summary->budget_snapshot)'
                                            data-items='@json(json_decode($itemsJson, true))'>
                                            Revisar
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No tienes selecciones pendientes de autorizacion.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalReview" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <form id="approval-form" method="POST" class="modal-content border-0 shadow-lg">
            @csrf
            <input type="hidden" name="status" id="decision_status">

            <div class="modal-header bg-dark text-white border-0">
                <h5 class="modal-title mb-0">Revision de seleccion pendiente</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <small class="text-muted d-block">RFQ</small>
                        <strong id="modal_rfq"></strong>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">Requisicion</small>
                        <strong id="modal_folio_req"></strong>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">Facultad aplicada</small>
                        <strong id="modal_role"></strong>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <small class="text-muted d-block">Proveedor seleccionado</small>
                        <strong id="modal_supplier"></strong>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Pago</small>
                        <strong id="modal_payment_terms"></strong>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Entrega</small>
                        <strong id="modal_delivery_days"></strong>
                    </div>
                </div>

                <div class="alert alert-light border">
                    <div class="row">
                        <div class="col-md-4">
                            <small class="text-muted d-block">Monto seleccionado con IVA</small>
                            <strong class="text-primary" id="modal_total"></strong>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted d-block">Total aprobado capturado</small>
                            <strong class="text-success" id="modal_approved_total">$0.00</strong>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted d-block">Limite efectivo del aprobador</small>
                            <strong id="modal_limit"></strong>
                        </div>
                    </div>
                </div>

                <div class="card border-0 bg-soft-success mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted d-block mb-0">Presupuesto asignado a revisar</small>
                            <strong class="text-success" id="modal_budget_available_total"></strong>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-md-3">
                                <small class="text-muted d-block">Asignado</small>
                                <strong id="modal_budget_assigned_total"></strong>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted d-block">Comprometido</small>
                                <strong id="modal_budget_committed_total"></strong>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted d-block">Disponible</small>
                                <strong id="modal_budget_remaining_total"></strong>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted d-block">Solicitud</small>
                                <strong id="modal_budget_requested_total"></strong>
                            </div>
                        </div>
                        <div id="modal_budget_error" class="alert alert-warning py-2 px-3 mb-2 d-none"></div>
                        <div class="table-responsive border rounded bg-white">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>CC / Categoria / Cedula</th>
                                        <th>Mes</th>
                                        <th class="text-end">Asignado</th>
                                        <th class="text-end">Comprometido</th>
                                        <th class="text-end">Disponible</th>
                                        <th class="text-end">Solicitud</th>
                                    </tr>
                                </thead>
                                <tbody id="modal_budget_table"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="table-responsive border rounded mb-3">
                    <table class="table table-sm table-striped mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Descripcion</th>
                                <th class="text-center">Cotizada</th>
                                <th class="text-center">Aprobada</th>
                                <th>Motivo si reduce</th>
                                <th class="text-end">P. Unit.</th>
                                <th class="text-end">Total aprobado</th>
                            </tr>
                        </thead>
                        <tbody id="modal_items_table"></tbody>
                    </table>
                </div>

                <div class="card bg-light border-0 shadow-none mb-3">
                    <div class="card-body">
                        <small class="text-muted d-block mb-1">Justificacion del comprador</small>
                        <p class="mb-0" id="modal_justification"></p>
                    </div>
                </div>

                <div id="rejection_area" style="display:none;">
                    <label class="form-label text-danger fw-bold">Motivo del rechazo</label>
                    <textarea name="reason" id="rejection_reason" class="form-control border-danger" rows="3"></textarea>
                </div>
            </div>

            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-outline-danger" id="btn-reject" onclick="setDecision('rejected')">Rechazar</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-success" id="btn-approve" onclick="setDecision('approved')">Autorizar cantidades</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let approvalSubmitting = false;

    function money(value) {
        return '$' + Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function recalculateApprovedTotals() {
        let total = 0;
        $('.approved-qty-input').each(function () {
            const row = $(this).closest('tr');
            const qty = Number($(this).val() || 0);
            const unitPrice = Number(row.data('unit-price') || 0);
            const ivaRate = Number(row.data('iva-rate') || 0);
            const lineSubtotal = qty * unitPrice;
            const lineTotal = lineSubtotal + (lineSubtotal * (ivaRate / 100));
            total += lineTotal;
            row.find('.line-approved-total').text(money(lineTotal));
        });
        $('#modal_approved_total').text(money(total));
    }

    function setDecision(status) {
        if (approvalSubmitting) {
            return;
        }

        const reasonField = $('#rejection_reason');

        if (status === 'rejected') {
            if ($('#rejection_area').is(':hidden')) {
                $('#rejection_area').slideDown();
                return;
            }

            if (reasonField.val().trim().length < 10) {
                reasonField.addClass('is-invalid');
                return;
            }
        }

        if (status === 'approved') {
            let valid = true;
            $('.approved-qty-input').each(function () {
                const input = $(this);
                const row = input.closest('tr');
                const approved = Number(input.val() || 0);
                const quoted = Number(row.data('quoted-qty') || 0);
                const notes = row.find('.approver-notes-input').val().trim();
                input.removeClass('is-invalid');
                row.find('.approver-notes-input').removeClass('is-invalid');

                if (approved < 0 || approved > quoted) {
                    input.addClass('is-invalid');
                    valid = false;
                }

                if (approved < quoted && notes.length === 0) {
                    row.find('.approver-notes-input').addClass('is-invalid');
                    valid = false;
                }
            });

            if (!valid) {
                return;
            }
        }

        approvalSubmitting = true;
        $('#decision_status').val(status);
        $('#btn-approve, #btn-reject').prop('disabled', true);
        $('#btn-approve').text(status === 'approved' ? 'Procesando...' : 'Autorizar cantidades');
        $('#btn-reject').text(status === 'rejected' ? 'Procesando...' : 'Rechazar');
        $('#approval-form').submit();
    }

    $('.btn-review').on('click', function () {
        const data = $(this).data();
        $('#modal_rfq').text(data.rfq || 'N/A');
        $('#modal_folio_req').text(data.folio || 'N/A');
        $('#modal_supplier').text(data.supplier || 'N/A');
        $('#modal_total').text('$' + data.total);
        $('#modal_payment_terms').text(data.payment || 'N/A');
        $('#modal_delivery_days').text((data.delivery || 0) + ' dias');
        $('#modal_justification').text(data.justification || 'Sin justificacion registrada.');
        $('#modal_role').text(data.role || 'Sin rol');
        $('#modal_limit').text(data.limit ? '$' + data.limit : 'Sin limite');

        const budget = data.budget || {};
        $('#modal_budget_assigned_total').text(budget.assigned_total || '-');
        $('#modal_budget_committed_total').text(budget.committed_total || '-');
        $('#modal_budget_remaining_total').text(budget.available_total || '-');
        $('#modal_budget_requested_total').text(budget.requested_total || '-');
        $('#modal_budget_available_total').text(
            budget.has_budget_totals ? `Disponible actual: ${budget.available_total || '-'}` : 'Centro de consumo libre o sin limite'
        );
        $('#modal_budget_error')
            .toggleClass('d-none', !budget.error)
            .text(budget.error || '');

        let budgetHtml = '';
        (budget.lines || []).forEach(line => {
            budgetHtml += `
                <tr>
                    <td>
                        <div class="fw-semibold">${line.cost_center || 'Sin centro de costo'}</div>
                        <div class="text-muted small">${line.expense_category || 'Sin categoria'} - ${line.budget_cedula || 'Sin subcategoria'}</div>
                        ${line.message ? `<div class="small ${line.is_available ? 'text-success' : 'text-danger'}">${line.message}</div>` : ''}
                    </td>
                    <td>${line.application_month || '-'}</td>
                    <td class="text-end">${line.assigned_amount || '-'}</td>
                    <td class="text-end">${line.committed_amount || '-'}</td>
                    <td class="text-end">${line.available_amount || '-'}</td>
                    <td class="text-end fw-semibold">${line.requested_amount || '-'}</td>
                </tr>
            `;
        });

        if (!budgetHtml) {
            budgetHtml = '<tr><td colspan="6" class="text-center text-muted py-3">No se encontro desglose presupuestal para esta seleccion.</td></tr>';
        }

        $('#modal_budget_table').html(budgetHtml);

        let itemsHtml = '';
        (data.items || []).forEach((item, index) => {
            const quotedQty = Number(item.quotedQty || 0);
            const approvedQty = Number(item.approvedQty || quotedQty);
            const unitPrice = Number(item.unitPrice || 0);
            const ivaRate = Number(item.ivaRate || 0);

            itemsHtml += `
                <tr data-unit-price="${unitPrice}" data-iva-rate="${ivaRate}" data-quoted-qty="${quotedQty}">
                    <td>
                        ${item.desc}
                        <input type="hidden" name="items[${index}][id]" value="${item.id}">
                    </td>
                    <td class="text-center">${quotedQty.toFixed(3)}</td>
                    <td class="text-center" style="width: 130px;">
                        <input type="number" class="form-control form-control-sm text-end approved-qty-input"
                            name="items[${index}][approved_quantity]"
                            value="${approvedQty.toFixed(3)}"
                            min="0"
                            max="${quotedQty}"
                            step="0.001">
                    </td>
                    <td style="min-width: 220px;">
                        <input type="text" class="form-control form-control-sm approver-notes-input"
                            name="items[${index}][approver_notes]"
                            value="${item.notes || ''}"
                            maxlength="1000"
                            placeholder="Obligatorio si reduces">
                    </td>
                    <td class="text-end">$${item.price}</td>
                    <td class="text-end fw-semibold line-approved-total">$0.00</td>
                </tr>
            `;
        });

        if (!itemsHtml) {
            itemsHtml = '<tr><td colspan="6" class="text-center text-muted py-3">No se encontro detalle de partidas.</td></tr>';
        }

        $('#modal_items_table').html(itemsHtml);
        $('.approved-qty-input').on('input', recalculateApprovedTotals);
        recalculateApprovedTotals();
        $('#approval-form').attr('action', `/approvals/quotations/${data.id}/handle`);
        $('#rejection_area').hide();
        $('#rejection_reason').val('').removeClass('is-invalid');
        approvalSubmitting = false;
        $('#btn-approve, #btn-reject').prop('disabled', false);
        $('#btn-approve').text('Autorizar cantidades');
        $('#btn-reject').text('Rechazar');
        $('#modalReview').modal('show');
    });
</script>
@endpush
