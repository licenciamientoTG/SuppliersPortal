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
                                        @if((int) $summary->current_approver_user_id !== (int) auth()->id())
                                            <span class="badge bg-info-subtle text-info-emphasis d-block mt-1">
                                                <i class="ti ti-user-share me-1"></i>Delegada por {{ $summary->currentApprover?->name }}
                                            </span>
                                        @endif
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

<div class="modal fade approval-review-modal" id="modalReview" tabindex="-1" aria-labelledby="modalReviewTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form id="approval-form" method="POST" class="modal-content border-0 shadow-lg">
            @csrf
            <input type="hidden" name="status" id="decision_status">

            <div class="modal-header border-0">
                <div>
                    <span class="review-kicker"><i class="ti ti-clipboard-check me-1"></i>Autorización pendiente</span>
                    <h5 class="modal-title mt-1 mb-0" id="modalReviewTitle">Revisar selección de compra</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body pt-0">
                <section class="review-hero mb-4">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                        <div>
                            <span class="text-white-50 small">Monto seleccionado con IVA</span>
                            <div class="review-total" id="modal_total"></div>
                        </div>
                        <div class="review-hero-meta">
                            <span>RFQ <strong id="modal_rfq"></strong></span>
                            <span>Requisición <strong id="modal_folio_req"></strong></span>
                            <span>Total por autorizar <strong id="modal_approved_total">$0.00</strong></span>
                        </div>
                    </div>
                </section>

                <section class="review-section mb-4">
                    <div class="review-section-heading"><span>Contexto de la selección</span></div>
                    <div class="row g-2">
                        <div class="col-md-4"><div class="review-data"><span>Proveedor seleccionado</span><strong id="modal_supplier"></strong></div></div>
                        <div class="col-md-2"><div class="review-data"><span>Condiciones de pago</span><strong id="modal_payment_terms"></strong></div></div>
                        <div class="col-md-2"><div class="review-data"><span>Entrega</span><strong id="modal_delivery_days"></strong></div></div>
                        <div class="col-md-2"><div class="review-data"><span>Facultad aplicada</span><strong id="modal_role"></strong></div></div>
                        <div class="col-md-2"><div class="review-data"><span>Límite efectivo</span><strong id="modal_limit"></strong></div></div>
                    </div>
                </section>

                <section class="review-section mb-4">
                    <div class="review-section-heading d-flex justify-content-between align-items-center gap-2 flex-wrap">
                        <span>Impacto presupuestal</span>
                        <small id="modal_budget_available_total" class="text-success fw-semibold"></small>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6 col-lg-3"><div class="budget-metric"><span>Asignado</span><strong id="modal_budget_assigned_total"></strong></div></div>
                        <div class="col-6 col-lg-3"><div class="budget-metric budget-metric-warning budget-metric-clickable" role="button" tabindex="0" aria-label="Abrir detalle del saldo comprometido"><span>Comprometido</span><strong id="modal_budget_committed_total"></strong><small>Haz clic para ver su composición</small></div></div>
                        <div class="col-6 col-lg-3"><div class="budget-metric budget-metric-success"><span>Disponible</span><strong id="modal_budget_remaining_total"></strong></div></div>
                        <div class="col-6 col-lg-3"><div class="budget-metric budget-metric-primary"><span>Esta solicitud</span><strong id="modal_budget_requested_total"></strong></div></div>
                    </div>
                    <div id="modal_budget_error" class="alert alert-warning py-2 px-3 mb-3 d-none"></div>
                    <div id="modal_budget_lines" class="budget-lines"></div>
                </section>

                <section class="review-section mb-4">
                    <div class="review-section-heading"><span>Partidas a autorizar</span><small>Modifica cantidades si es necesario.</small></div>
                    <div class="table-responsive review-table">
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
                </section>

                <section class="review-note mb-3">
                        <small>Justificación del comprador</small>
                        <p class="mb-0" id="modal_justification"></p>
                </section>

                <div id="rejection_area" class="review-rejection" style="display:none;">
                    <label class="form-label text-danger fw-bold">Motivo del rechazo</label>
                    <textarea name="reason" id="rejection_reason" class="form-control border-danger" rows="3"></textarea>
                </div>
            </div>

            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-danger" id="btn-reject" onclick="setDecision('rejected')">Rechazar</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-success" id="btn-approve" onclick="setDecision('approved')">Autorizar cantidades</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('styles')
<style>
    .approval-review-modal .modal-content { border-radius: .85rem; overflow: hidden; }
    .approval-review-modal .modal-header { padding: 1.25rem 1.5rem .75rem; }
    .approval-review-modal .modal-body { padding: .5rem 1.5rem 1.5rem; background: #f7fbff; }
    .approval-review-modal .modal-footer { padding: 1rem 1.5rem; background: #fff; box-shadow: 0 -4px 14px rgba(20, 55, 82, .06); }
    .review-kicker { color: #188ae2; font-size: .72rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
    .review-hero { padding: 1.25rem; border-radius: .75rem; color: #fff; background: #188ae2; box-shadow: 0 8px 20px rgba(24, 138, 226, .18); }
    .review-total { font-size: clamp(1.7rem, 4vw, 2.35rem); font-weight: 700; line-height: 1.2; }
    .review-hero-meta { display: flex; flex-wrap: wrap; gap: 1rem; font-size: .82rem; }
    .review-hero-meta span { display: grid; gap: .15rem; color: rgba(255,255,255,.72); }
    .review-hero-meta strong { color: #fff; font-size: .95rem; }
    .review-section { border: 1px solid #e2e9f0; border-radius: .75rem; background: #fff; padding: 1rem; }
    .review-section-heading { margin-bottom: .75rem; color: #243b53; font-weight: 700; }
    .review-section-heading > small { display: block; margin-top: .2rem; color: #7b8a9a; font-size: .78rem; font-weight: 400; }
    .review-data, .budget-metric { height: 100%; padding: .8rem; border: 1px solid #e8eef4; border-radius: .65rem; background: #fff; }
    .review-data span, .budget-metric span { display: block; margin-bottom: .25rem; color: #718096; font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; }
    .review-data strong { color: #25364a; font-size: .9rem; }
    .budget-metric strong { color: #25364a; font-size: 1.05rem; }
    .budget-metric small { display: block; margin-top: .25rem; color: #947217; font-size: .7rem; line-height: 1.25; }
    .budget-metric-warning { background: #fffaf0; border-color: #f6dfad; }
    .budget-metric-clickable { cursor: pointer; transition: transform .18s ease, box-shadow .18s ease; }
    .budget-metric-clickable:hover, .budget-metric-clickable:focus { box-shadow: 0 5px 12px rgba(148, 114, 23, .13); outline: 2px solid #e6bd63; outline-offset: 2px; transform: translateY(-2px); }
    .budget-metric-success { background: #f2fcf7; border-color: #ccefe0; }
    .budget-metric-success strong { color: #21875d; }
    .budget-metric-primary { background: #f1f8fe; border-color: #cfe7f8; }
    .budget-metric-primary strong { color: #188ae2; }
    .budget-lines { display: grid; gap: .65rem; }
    .budget-line { border: 1px solid #e2e9f0; border-radius: .65rem; overflow: hidden; }
    .budget-line-main { display: grid; grid-template-columns: minmax(200px, 1.6fr) repeat(4, minmax(90px, .7fr)); gap: .75rem; align-items: center; padding: .85rem; }
    .budget-line-label strong { display: block; color: #25364a; font-size: .88rem; }
    .budget-line-label small { color: #718096; }
    .budget-line-value { text-align: right; }
    .budget-line-value span { display: block; color: #718096; font-size: .68rem; text-transform: uppercase; }
    .budget-line-value strong { font-size: .85rem; color: #334e68; }
    .commitment-toggle { width: 100%; padding: .65rem .85rem; border: 0; border-top: 1px solid #e2e9f0; background: #f7fbff; color: #176eaf; text-align: left; font-size: .8rem; font-weight: 600; }
    .commitment-toggle:hover, .commitment-toggle:focus { background: #edf7fe; color: #0d5d99; }
    .commitment-toggle i { transition: transform .18s ease; }
    .commitment-toggle[aria-expanded="true"] i { transform: rotate(180deg); }
    .commitment-detail { padding: .75rem .85rem; background: #fff; }
    .commitment-item { display: grid; grid-template-columns: auto 1fr auto; gap: .75rem; align-items: center; padding: .6rem 0; border-bottom: 1px solid #edf1f5; }
    .commitment-item:last-child { border-bottom: 0; }
    .commitment-item small { color: #718096; }
    .commitment-item.untraced { padding: .75rem; border: 1px dashed #e6bd63; border-radius: .5rem; background: #fffaf0; }
    .commitment-item.current-commitment { padding: .75rem; border: 1px solid #b9def7; border-radius: .5rem; background: #f1f8fe; }
    .commitment-pagination { display: flex; justify-content: space-between; align-items: center; gap: .5rem; margin-top: .75rem; padding-top: .75rem; border-top: 1px solid #edf1f5; }
    .commitment-pagination small { color: #718096; }
    .review-table { border: 1px solid #e2e9f0; border-radius: .65rem; }
    .review-note { padding: .9rem 1rem; border-left: 3px solid #188ae2; border-radius: .35rem; background: #edf7fe; }
    .review-note small { display: block; margin-bottom: .3rem; color: #176eaf; font-weight: 700; }
    .review-rejection { padding: 1rem; border: 1px solid #f1b7b7; border-radius: .65rem; background: #fff7f7; }
    @media (max-width: 767.98px) { .budget-line-main { grid-template-columns: 1fr 1fr; } .budget-line-label { grid-column: 1 / -1; } .budget-line-value { text-align: left; } }
    @media (prefers-reduced-motion: reduce) { .approval-review-modal *, .approval-review-modal *::before, .approval-review-modal *::after { transition: none !important; } }
</style>
@endpush

@push('scripts')
<script>
    let approvalSubmitting = false;

    function money(value) {
        return '$' + Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>'"]/g, character => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
        }[character]));
    }

    function renderCommitmentPage($detail, page) {
        const pageSize = 5;
        const $items = $detail.find('.commitment-item');
        const pages = Math.ceil($items.length / pageSize);
        const currentPage = Math.min(Math.max(page, 1), pages);

        $items.each(function (index) {
            $(this).toggleClass('d-none', index < (currentPage - 1) * pageSize || index >= currentPage * pageSize);
        });

        $detail.find('.commitment-page-current').text(`Página ${currentPage} de ${pages}`);
        $detail.find('[data-commitment-page="previous"]').prop('disabled', currentPage === 1);
        $detail.find('[data-commitment-page="next"]').prop('disabled', currentPage === pages);
        $detail.data('commitment-page', currentPage);
    }

    function initializeCommitmentPagination() {
        $('.commitment-detail').each(function () {
            const $detail = $(this);
            const itemCount = $detail.find('.commitment-item').length;

            if (itemCount <= 5) {
                return;
            }

            $detail.append(`
                <div class="commitment-pagination">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-commitment-page="previous"><i class="ti ti-chevron-left"></i> Anterior</button>
                    <small class="commitment-page-current"></small>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-commitment-page="next">Siguiente <i class="ti ti-chevron-right"></i></button>
                </div>`);
            renderCommitmentPage($detail, 1);
        });

        $(document).off('click.commitmentPagination', '[data-commitment-page]').on('click.commitmentPagination', '[data-commitment-page]', function () {
            const $detail = $(this).closest('.commitment-detail');
            const page = Number($detail.data('commitment-page') || 1);
            renderCommitmentPage($detail, $(this).data('commitment-page') === 'next' ? page + 1 : page - 1);
        });
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
        (budget.lines || []).forEach((line, index) => {
            const componentId = `commitment-components-${index}`;
            const components = line.committed_components || [];
            const componentsHtml = components.length
                ? components.map(component => `
                    <div class="commitment-item ${component.is_untraced ? 'untraced' : ''} ${component.is_current ? 'current-commitment' : ''}">
                        <span class="badge bg-soft-primary text-primary">${escapeHtml(component.type)}</span>
                        <div><strong class="d-block small">${escapeHtml(component.folio)}</strong><small>${escapeHtml(component.supplier)} · Comprometido el ${escapeHtml(component.committed_at)}</small></div>
                        <strong>${escapeHtml(component.amount)}</strong>
                    </div>`).join('')
                : '<p class="text-muted small mb-0">No hay compromisos activos registrados para esta combinación presupuestal.</p>';
            const componentLabel = components.length === 1 ? 'Ver 1 componente del comprometido' : `Ver ${components.length} componentes del comprometido`;

            budgetHtml += `
                <article class="budget-line">
                    <div class="budget-line-main">
                        <div class="budget-line-label">
                            <strong>${escapeHtml(line.cost_center || 'Sin centro de costo')}</strong>
                            <small>${escapeHtml(line.expense_category || 'Sin cuenta')} · ${escapeHtml(line.budget_cedula || 'Sin subcuenta')} · ${escapeHtml(line.application_month || '-')}</small>
                            ${line.message ? `<small class="d-block mt-1 ${line.is_available ? 'text-success' : 'text-danger'}">${escapeHtml(line.message)}</small>` : ''}
                        </div>
                        <div class="budget-line-value"><span>Asignado</span><strong>${escapeHtml(line.assigned_amount || '-')}</strong></div>
                        <div class="budget-line-value"><span>Comprometido</span><strong>${escapeHtml(line.committed_amount || '-')}</strong></div>
                        <div class="budget-line-value"><span>Disponible</span><strong>${escapeHtml(line.available_amount || '-')}</strong></div>
                        <div class="budget-line-value"><span>Solicitud</span><strong>${escapeHtml(line.requested_amount || '-')}</strong></div>
                    </div>
                    <button class="commitment-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#${componentId}" aria-expanded="false" aria-controls="${componentId}">
                        ${componentLabel} <i class="ti ti-chevron-down float-end"></i>
                    </button>
                    <div class="collapse" id="${componentId}"><div class="commitment-detail">${componentsHtml}</div></div>
                </article>`;
        });

        if (!budgetHtml) {
            budgetHtml = '<div class="text-center text-muted py-3">No se encontró desglose presupuestal para esta selección.</div>';
        }

        $('#modal_budget_lines').html(budgetHtml);
        initializeCommitmentPagination();

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

    $(document).on('click keydown', '.budget-metric-clickable', function (event) {
        if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        event.preventDefault();
        $('.commitment-toggle').trigger('click');
    });
</script>
@endpush
