<?php

namespace App\Services;

use App\Enum\RequisitionStatus;
use App\Models\QuotationGroup;
use App\Models\QuotationSummary;
use App\Models\Requisition;
use App\Models\Rfq;
use App\Models\RfqResponse;
use App\Notifications\RfqCancelledForRequesterNotification;
use App\Notifications\RfqCancelledForSupplierNotification;
use Illuminate\Support\Facades\DB;

class QuotationRejectionWorkflowService
{
    public function __construct(
        private BudgetAllocationService $budgetAllocationService,
        private AuthorizerResolutionService $authorizerResolutionService,
    ) {}

    public function handleApprovalRejection(QuotationSummary $summary, int $userId, string $reason): void
    {
        DB::transaction(function () use ($summary, $userId, $reason) {
            $summary->loadMissing('rfq.quotationGroup', 'requisition');

            $summary->reject($userId, $reason);
            $this->budgetAllocationService->releaseQuotationSummary($summary);

            $summary->rfq->reject($reason, $userId);
            $summary->requisition->update([
                'status' => RequisitionStatus::IN_QUOTATION->value,
                'updated_by' => $userId,
            ]);
        });
    }

    public function reawardRejectedQuotation(
        Rfq $rejectedRfq,
        int $supplierId,
        string $justification,
        int $userId,
        ?string $notes = null,
    ): QuotationSummary {
        return DB::transaction(function () use ($rejectedRfq, $supplierId, $justification, $userId, $notes) {
            $rejectedRfq->loadMissing([
                'requisition.requester',
                'requisition.costCenter',
                'quotationGroup',
                'suppliers',
                'rfqResponses.requisitionItem',
            ]);

            if (! $rejectedRfq->isRejected()) {
                throw new \RuntimeException('Solo se pueden re-adjudicar RFQs rechazadas.');
            }

            if ((int) $rejectedRfq->quotationSummary?->selected_supplier_id === $supplierId) {
                throw new \RuntimeException('Debes seleccionar un proveedor distinto al rechazado anteriormente.');
            }

            $replacementRfq = $this->createReplacementRfq($rejectedRfq, $userId);

            $totals = $replacementRfq->rfqResponses()
                ->where('supplier_id', $supplierId)
                ->where('status', 'SUBMITTED')
                ->selectRaw('SUM(subtotal) as subtotal, SUM(iva_amount) as iva, SUM(total) as total')
                ->first();

            if (! $totals || (float) ($totals->total ?? 0) <= 0) {
                throw new \RuntimeException('El proveedor seleccionado no tiene cotizaciones enviadas para esta nueva vuelta.');
            }

            $summary = QuotationSummary::updateOrCreate(
                ['rfq_id' => $replacementRfq->id],
                [
                    'requisition_id' => $replacementRfq->requisition_id,
                    'subtotal' => (float) $totals->subtotal,
                    'iva_amount' => (float) $totals->iva,
                    'total' => (float) $totals->total,
                    'selected_supplier_id' => $supplierId,
                    'requested_by_user_id' => $replacementRfq->requisition->requested_by,
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

            $resolution = $this->authorizerResolutionService->resolveForSummary($summary);

            $summary->update([
                'current_approver_user_id' => $resolution['approver_user']->id,
                'authorizer_role_id' => $resolution['authorizer_role']->id,
                'effective_authorization_limit' => $resolution['effective_limit'],
                'approval_chain_snapshot' => $resolution['chain'],
                'resolution_notes' => $resolution['resolution_notes'],
            ]);

            $this->budgetAllocationService->reserveQuotationSummary($summary);

            $replacementRfq->update([
                'status' => 'EVALUATED',
                'updated_by' => $userId,
            ]);

            $replacementRfq->requisition->update([
                'status' => RequisitionStatus::QUOTED->value,
                'updated_by' => $userId,
            ]);

            if ($replacementRfq->quotationGroup && $replacementRfq->quotationGroup->isRejected()) {
                $replacementRfq->quotationGroup->reopen($userId);
            }

            return $summary->fresh(['currentApprover', 'selectedSupplier', 'rfq', 'requisition']);
        });
    }

    public function cancelRejectedRfq(Rfq $rfq, int $userId, string $reason): void
    {
        DB::transaction(function () use ($rfq, $userId, $reason) {
            $rfq->loadMissing('quotationSummary', 'quotationGroup', 'requisition', 'suppliers', 'requisition.requester');

            if ($rfq->quotationSummary && $rfq->quotationSummary->isPending()) {
                $rfq->quotationSummary->reject($userId, $reason);
                $this->budgetAllocationService->releaseQuotationSummary($rfq->quotationSummary);
            }

            $rfq->cancel($reason, $userId);

            if ($rfq->quotationGroup && ! $rfq->quotationGroup->rfqs()->active()->exists()) {
                $rfq->quotationGroup->reject($reason, $userId);
            }

            $this->notifyRfqCancellation($rfq, $reason);
            $this->refreshRequisitionStatus($rfq->requisition);
        });
    }

    public function cancelRequisitionFromPurchasing(Requisition $requisition, int $userId, string $reason): void
    {
        DB::transaction(function () use ($requisition, $userId, $reason) {
            $requisition->loadMissing([
                'requester',
                'rfqs.quotationSummary',
                'rfqs.suppliers',
                'quotationGroups',
            ]);

            foreach ($requisition->rfqs as $rfq) {
                if ($rfq->quotationSummary && $rfq->quotationSummary->isPending()) {
                    $rfq->quotationSummary->reject($userId, 'Requisición cancelada por Compras. '.$reason);
                    $this->budgetAllocationService->releaseQuotationSummary($rfq->quotationSummary);
                }

                if ($rfq->isActive()) {
                    $rfq->cancel($reason, $userId);
                    $this->notifyRfqCancellation($rfq, $reason);
                }
            }

            foreach ($requisition->quotationGroups as $group) {
                if ($group->isActive()) {
                    $group->cancel($reason, $userId);
                }
            }

            $requisition->cancel($reason, $userId);
        });
    }

    public function refreshRequisitionStatus(Requisition|int $requisition): void
    {
        $model = $requisition instanceof Requisition
            ? $requisition->loadMissing('rfqs.quotationSummary')
            : Requisition::with('rfqs.quotationSummary')->findOrFail($requisition);

        if ($model->status === RequisitionStatus::CANCELLED) {
            return;
        }

        $rfqs = $model->rfqs;
        $activeRfqs = $rfqs->filter(fn (Rfq $rfq) => $rfq->isActive());

        if ($activeRfqs->contains(fn (Rfq $rfq) => $rfq->quotationSummary && $rfq->quotationSummary->approval_status === 'pending')) {
            $model->update(['status' => RequisitionStatus::QUOTED->value]);

            return;
        }

        if ($activeRfqs->isNotEmpty()) {
            $model->update(['status' => RequisitionStatus::IN_QUOTATION->value]);

            return;
        }

        if ($rfqs->contains(fn (Rfq $rfq) => $rfq->status === 'COMPLETED')) {
            $model->update(['status' => RequisitionStatus::COMPLETED->value]);

            return;
        }

        $model->update(['status' => RequisitionStatus::IN_QUOTATION->value]);
    }

    private function createReplacementRfq(Rfq $sourceRfq, int $userId): Rfq
    {
        $existingActiveReplacement = $sourceRfq->successorRfqs()->active()->first();

        if ($existingActiveReplacement) {
            return $existingActiveReplacement->load(['requisition.requester', 'requisition.costCenter', 'rfqResponses.requisitionItem']);
        }

        $replacementRfq = Rfq::create([
            'folio' => $this->generateReplacementFolio(),
            'requisition_id' => $sourceRfq->requisition_id,
            'quotation_group_id' => $sourceRfq->quotation_group_id,
            'requisition_item_id' => $sourceRfq->requisition_item_id,
            'supplier_id' => $sourceRfq->supplier_id,
            'supersedes_rfq_id' => $sourceRfq->id,
            'source' => $sourceRfq->source,
            'external_contact_method' => $sourceRfq->external_contact_method,
            'external_notes' => $sourceRfq->external_notes,
            'status' => 'RECEIVED',
            'sent_at' => $sourceRfq->sent_at ?? now(),
            'response_deadline' => $sourceRfq->response_deadline,
            'notes' => $sourceRfq->notes,
            'message' => $sourceRfq->message,
            'requirements' => $sourceRfq->requirements,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        $replacementRfq->suppliers()->attach(
            $sourceRfq->suppliers->mapWithKeys(fn ($supplier) => [
                $supplier->id => [
                    'invited_at' => $supplier->pivot->invited_at ?? now(),
                    'responded_at' => $supplier->pivot->responded_at,
                    'quotation_pdf_path' => $supplier->pivot->quotation_pdf_path,
                    'notes' => $supplier->pivot->notes,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ])->all()
        );

        $sourceRfq->rfqResponses->each(function (RfqResponse $response) use ($replacementRfq) {
            $newResponse = $response->replicate([
                'id',
                'rfq_id',
                'created_at',
                'updated_at',
                'deleted_at',
            ]);

            $newResponse->rfq_id = $replacementRfq->id;
            $newResponse->status = 'SUBMITTED';
            $newResponse->selection_justification = null;
            $newResponse->evaluated_by = null;
            $newResponse->evaluated_at = null;
            $newResponse->save();
        });

        return $replacementRfq->load(['requisition.requester', 'requisition.costCenter', 'rfqResponses.requisitionItem']);
    }

    private function notifyRfqCancellation(Rfq $rfq, string $reason): void
    {
        if ($rfq->requisition && $rfq->requisition->requester) {
            $rfq->requisition->requester->notify(new RfqCancelledForRequesterNotification($rfq, $reason));
        }

        if ($rfq->status !== 'DRAFT') {
            foreach ($rfq->suppliers as $supplier) {
                $supplier->notify(new RfqCancelledForSupplierNotification($rfq, $reason));
            }
        }
    }

    private function generateReplacementFolio(): string
    {
        $count = Rfq::whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])->count() + 1;

        return 'RFQ-' . now()->format('Ymd') . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
