<?php

namespace App\Services\Rfq;

use App\Enum\RequisitionStatus;
use App\Exceptions\Rfq\AwardNotAllowedException;
use App\Exceptions\Rfq\RfqAlreadyRejectedException;
use App\Models\QuotationSummary;
use App\Models\Rfq;
use App\Notifications\BuyerWorkflowNotification;
use App\Notifications\QuotationApprovalRequestNotification;
use App\Services\ApprovalDelegationService;
use App\Services\AuthorizerResolutionService;
use App\Services\BudgetAllocationService;
use App\Services\BuyerNotificationService;
use App\Services\QuotationSummaryItemService;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Adjudicación de una RFQ a un proveedor: diagnóstico de viabilidad,
 * creación del QuotationSummary pendiente de aprobación y notificaciones.
 * Compartido por el comparativo (RfqComparisonController) y el tablero.
 */
class RfqAwardService
{
    public function __construct(
        private AuthorizerResolutionService $authorizerResolutionService,
        private BudgetAllocationService $budgetAllocationService,
        private BuyerNotificationService $buyerNotificationService,
        private QuotationSummaryItemService $quotationSummaryItemService,
        private ApprovalDelegationService $approvalDelegations,
    ) {}

    /**
     * Diagnóstico de si la oferta de un proveedor puede adjudicarse:
     * cotizaciones enviadas, vigencia, autorizador resoluble y presupuesto.
     *
     * @return array{allowed: bool, reasons: string[], budget_blocked: bool, budget_messages: string[]}
     */
    public function supplierDiagnostics(Rfq $rfq, int $supplierId): array
    {
        $responses = $rfq->rfqResponses
            ->where('supplier_id', $supplierId)
            ->where('status', 'SUBMITTED')
            ->where('not_available', false)
            ->values();

        $reasons = [];
        $budgetMessages = [];

        if ($responses->isEmpty()) {
            $reasons[] = 'El proveedor no tiene cotizaciones enviadas para esta RFQ.';
        }

        $quotationDate = $responses->whereNotNull('quotation_date')->min('quotation_date');
        $minValidityDays = $responses->whereNotNull('validity_days')->min('validity_days');

        if ($quotationDate && $minValidityDays) {
            $expiryDate = now()->parse($quotationDate)->addDays((int) $minValidityDays);

            if ($expiryDate->isPast()) {
                $reasons[] = 'La oferta está vencida desde el '.$expiryDate->format('d/m/Y').'.';
            }
        }

        if ($responses->isNotEmpty()) {
            $summary = new QuotationSummary([
                'requisition_id' => $rfq->requisition_id,
                'rfq_id' => $rfq->id,
                'subtotal' => (float) $responses->sum('subtotal'),
                'iva_amount' => (float) $responses->sum('iva_amount'),
                'total' => (float) $responses->sum('total'),
                'selected_supplier_id' => $supplierId,
                'requested_by_user_id' => $rfq->requisition->requested_by,
            ]);

            $summary->setRelation('requisition', $rfq->requisition);
            $summary->setRelation('requester', $rfq->requisition->requester);
            $summary->setRelation('rfq', $rfq);

            try {
                $this->authorizerResolutionService->resolveForSummary($summary);
            } catch (Throwable $exception) {
                $reasons[] = $exception->getMessage();
            }

            try {
                collect($this->budgetAllocationService->buildQuotationSummaryBudgetLines($summary))
                    ->each(function (array $line) use (&$reasons, &$budgetMessages) {
                        $budgetCheck = $this->budgetAllocationService->checkAvailability(
                            (int) $line['cost_center_id'],
                            (int) $line['year'],
                            (int) $line['month'],
                            (int) $line['expense_category_id'],
                            (float) $line['amount'],
                            $line['budget_cedula_id'] ?? null
                        );

                        if (! $budgetCheck['available']) {
                            $budgetMessages[] = $budgetCheck['message'];
                            $reasons[] = $budgetCheck['message'];
                        }
                    });
            } catch (Throwable $exception) {
                $budgetMessages[] = $exception->getMessage();
                $reasons[] = $exception->getMessage();
            }
        }

        return [
            'allowed' => empty($reasons),
            'reasons' => array_values(array_unique($reasons)),
            'budget_blocked' => ! empty($budgetMessages),
            'budget_messages' => array_values(array_unique($budgetMessages)),
        ];
    }

    /**
     * Adjudica la RFQ al proveedor: crea/actualiza el QuotationSummary en
     * 'pending', resuelve al autorizador, reserva presupuesto, marca la RFQ
     * como EVALUATED y la requisición como QUOTED, y dispara notificaciones.
     *
     * @throws RfqAlreadyRejectedException
     * @throws AwardNotAllowedException
     */
    public function award(Rfq $rfq, int $supplierId, string $justification, ?string $notes, int $userId): QuotationSummary
    {
        if ($rfq->isRejected()) {
            throw RfqAlreadyRejectedException::make();
        }

        $rfq->loadMissing('requisition.requester', 'requisition.items.costCenter', 'rfqResponses.requisitionItem');

        $totals = $rfq->rfqResponses()
            ->where('supplier_id', $supplierId)
            ->where('status', 'SUBMITTED')
            ->where('not_available', false)
            ->selectRaw('SUM(subtotal) as subtotal, SUM(iva_amount) as iva, SUM(total) as total')
            ->first();

        if (! $totals || (float) ($totals->total ?? 0) <= 0) {
            throw new AwardNotAllowedException('El proveedor seleccionado no tiene cotizaciones enviadas para esta RFQ.');
        }

        $diagnostics = $this->supplierDiagnostics($rfq, $supplierId);

        if (! $diagnostics['allowed']) {
            throw new AwardNotAllowedException(
                'No se puede adjudicar esta oferta: '.implode(' ', $diagnostics['reasons']),
                $diagnostics['reasons']
            );
        }

        $summary = DB::transaction(function () use ($rfq, $supplierId, $justification, $notes, $userId, $totals) {
            $summary = QuotationSummary::updateOrCreate(
                ['rfq_id' => $rfq->id],
                [
                    'requisition_id' => $rfq->requisition_id,
                    'subtotal' => (float) $totals->subtotal,
                    'iva_amount' => (float) $totals->iva,
                    'total' => (float) $totals->total,
                    'selected_supplier_id' => $supplierId,
                    'requested_by_user_id' => $rfq->requisition->requested_by,
                    'selected_by_user_id' => $userId,
                    'approval_status' => 'pending',
                    'justification' => $justification,
                    'notes' => $notes,
                    'approved_by' => null,
                    'approved_at' => null,
                    'rejected_by' => null,
                    'rejected_at' => null,
                    'rejection_reason' => null,
                ]
            );

            $summary->loadMissing('requester', 'requisition.requester');

            if (! app(\App\Services\CostCenterApprovalFlowService::class)->initialize($summary)) {
                $resolution = $this->authorizerResolutionService->resolveForSummary($summary);
                $summary->update(['current_approver_user_id' => $resolution['approver_user']->id, 'authorizer_role_id' => $resolution['authorizer_role']->id, 'effective_authorization_limit' => $resolution['effective_limit'], 'approval_chain_snapshot' => $resolution['chain'], 'resolution_notes' => $resolution['resolution_notes']]);
            }

            $this->quotationSummaryItemService->syncFromSelectedSupplier($summary);
            $this->budgetAllocationService->reserveQuotationSummary($summary);

            $rfq->update(['status' => 'EVALUATED']);
            $rfq->requisition->update(['status' => RequisitionStatus::QUOTED->value]);

            return $summary->fresh(['currentApprover', 'selectedSupplier', 'rfq', 'requisition']);
        });

        $this->notifyQuotationSentForApproval($summary, false);

        return $summary;
    }

    /**
     * Notifica a compradores y a los destinatarios de aprobación que una
     * adjudicación (o re-adjudicación) quedó pendiente de autorización.
     */
    public function notifyQuotationSentForApproval(QuotationSummary $summary, bool $reawarded): void
    {
        $escalated = collect($summary->approval_chain_snapshot)
            ->contains(fn ($step) => ($step['status'] ?? null) !== 'eligible');

        $this->notifyBuyersQuotationPendingApproval($summary, $reawarded);
        $this->notifyApprovalRecipients($summary, $escalated);
    }

    private function notifyBuyersQuotationPendingApproval(QuotationSummary $summary, bool $reawarded): void
    {
        $summary->loadMissing(['rfq', 'requisition.requester', 'selectedSupplier', 'currentApprover']);

        $heading = $reawarded ? 'Re-adjudicación enviada a aprobación' : 'Adjudicación enviada a aprobación';
        $messagePrefix = $reawarded ? 'La re-adjudicación' : 'La adjudicación';

        $this->buyerNotificationService->notify(
            new BuyerWorkflowNotification(
                type: 'buyer_quotation_pending_approval',
                subject: $heading.' - '.($summary->rfq?->folio ?? 'RFQ'),
                heading: $heading,
                intro: 'la cotización adjudicada fue enviada al flujo de aprobación.',
                details: [
                    'RFQ' => $summary->rfq?->folio ?? 'N/A',
                    'Requisición' => $summary->requisition?->folio ?? 'N/A',
                    'Solicitante' => $summary->requisition?->requester?->name ?? 'N/A',
                    'Proveedor adjudicado' => $summary->selectedSupplier?->company_name ?? 'N/A',
                    'Monto total con IVA' => '$'.number_format((float) $summary->total, 2),
                    'Aprobador actual' => $summary->currentApprover?->name ?? 'N/A',
                ],
                url: route('rfq.comparison.index', $summary->rfq_id),
                buttonLabel: 'Ver comparativo',
                message: $messagePrefix.' de la RFQ '.($summary->rfq?->folio ?? 'N/A').' fue enviada a aprobación.',
                context: [
                    'summary_id' => $summary->id,
                    'rfq_id' => $summary->rfq_id,
                    'rfq_folio' => $summary->rfq?->folio,
                    'requisition_id' => $summary->requisition_id,
                    'requisition_folio' => $summary->requisition?->folio,
                    'reawarded' => $reawarded,
                ],
            ),
        );
    }

    private function notifyApprovalRecipients(QuotationSummary $summary, bool $escalated): void
    {
        $principal = $summary->currentApprover;

        if (! $principal) {
            return;
        }

        $this->approvalDelegations->recipientsForPrincipal($principal)
            ->each(fn ($recipient) => $recipient->notify(
                new QuotationApprovalRequestNotification(
                    $summary,
                    $escalated,
                    (int) $recipient->id === (int) $principal->id ? null : $principal
                )
            ));
    }
}
