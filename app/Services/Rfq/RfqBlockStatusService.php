<?php

namespace App\Services\Rfq;

use App\Models\Rfq;

class RfqBlockStatusService
{
    /**
     * Resume las cotizaciones recibidas que no pueden adjudicarse.
     *
     * @return array{blocked: int, submitted: int}
     */
    public function summary(Rfq $rfq): array
    {
        $rfq->loadMissing('requisition.requester', 'requisition.items.costCenter', 'suppliers');
        if (! $rfq->relationLoaded('rfqResponses')) {
            $rfq->setRelation('rfqResponses', $rfq->rfqResponses()
                ->whereIn('status', ['SUBMITTED', 'SELECTED', 'REJECTED'])
                ->get());
        }

        $submittedSupplierIds = $rfq->rfqResponses
            ->where('status', 'SUBMITTED')
            ->where('not_available', false)
            ->pluck('supplier_id')
            ->unique();

        $diagnostics = app(RfqAwardService::class);
        $blocked = $submittedSupplierIds
            ->filter(fn ($supplierId) => ! $diagnostics->supplierDiagnostics($rfq, (int) $supplierId)['allowed'])
            ->count();

        return ['blocked' => $blocked, 'submitted' => $submittedSupplierIds->count()];
    }

    /**
     * Resume las RFQ de una requisición: basta una oferta bloqueada para marcarla.
     *
     * @return array{blocked: int, submitted: int}
     */
    public function requisitionSummary(iterable $rfqs): array
    {
        $summary = ['blocked' => 0, 'submitted' => 0];

        foreach ($rfqs as $rfq) {
            $rfqSummary = $this->summary($rfq);
            $summary['blocked'] += $rfqSummary['blocked'];
            $summary['submitted'] += $rfqSummary['submitted'];
        }

        return $summary;
    }
}
