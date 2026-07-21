<?php

namespace App\Services;

use App\Models\SupplierDocument;
use Carbon\Carbon;

class SupplierDocumentAutoAcceptanceService
{
    private const DOCUMENT_CODES = [
        'constancia_fiscal',
        'opinion_sat',
        'opinion_imss',
        'opinion_infonavit',
    ];

    private const OPINION_CODES = [
        'opinion_sat',
        'opinion_imss',
        'opinion_infonavit',
    ];

    public function acceptIfEligible(SupplierDocument $document, SupplierDocumentRequirementService $requirements): bool
    {
        $document->loadMissing('supplier', 'documentType', 'requirement');

        if (! in_array($document->doc_type, self::DOCUMENT_CODES, true)) {
            return false;
        }

        $metadata = $document->issue_date_extraction_data ?? [];
        $detectedRfc = strtoupper((string) ($metadata['rfc'] ?? ''));
        $supplierRfc = strtoupper((string) ($document->supplier?->rfc ?? ''));
        $rfcMatches = $detectedRfc !== '' && $supplierRfc !== '' && hash_equals($supplierRfc, $detectedRfc);
        $metadata['rfc_matches_supplier'] = $rfcMatches;

        $isOpinion = in_array($document->doc_type, self::OPINION_CODES, true);
        $isPositive = strtoupper((string) ($metadata['compliance_status'] ?? '')) === 'POSITIVA';
        if ($isOpinion) {
            $metadata['compliance_is_positive'] = $isPositive;
        }

        $issuedAt = $this->issuedAt($document, $metadata);
        $isCurrent = $issuedAt
            && $document->documentType?->isCurrentOn($issuedAt, now()->startOfDay());

        $metadata['auto_acceptance'] = $rfcMatches && (! $isOpinion || $isPositive) && $isCurrent
            ? 'accepted'
            : 'pending_review';
        $metadata['auto_acceptance_checked_at'] = now()->toDateTimeString();

        $document->update([
            'issue_date_extraction_data' => $metadata,
        ]);

        if ($metadata['auto_acceptance'] !== 'accepted') {
            return false;
        }

        $document->update([
            'status' => 'accepted',
            'rejection_reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => now(),
        ]);
        $requirements->accept($document, $issuedAt->toDateString(), null);

        return true;
    }

    private function issuedAt(SupplierDocument $document, array $metadata): ?Carbon
    {
        $value = $document->issued_at ?? ($metadata['issued_at'] ?? null);
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
