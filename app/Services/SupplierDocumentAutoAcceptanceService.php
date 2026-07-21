<?php

namespace App\Services;

use App\Models\SupplierDocument;
use App\Notifications\BuyerWorkflowNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

    public function __construct(private readonly BuyerNotificationService $buyerNotifications) {}

    public function acceptIfEligible(SupplierDocument $document, SupplierDocumentRequirementService $requirements): bool
    {
        $document->loadMissing('supplier', 'documentType', 'requirement');

        if ($document->status === 'accepted' || ! in_array($document->doc_type, self::DOCUMENT_CODES, true)) {
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
        $this->notifyBuyers($document, $issuedAt);

        return true;
    }

    private function notifyBuyers(SupplierDocument $document, Carbon $issuedAt): void
    {
        $document->loadMissing('supplier', 'documentType');
        $supplier = $document->supplier;
        $type = $document->documentType;
        $expiresAt = $type?->calculateExpiry($issuedAt);

        DB::afterCommit(function () use ($document, $supplier, $type, $issuedAt, $expiresAt): void {
            try {
                $this->buyerNotifications->notify(new BuyerWorkflowNotification(
                    type: 'supplier_document_auto_accepted',
                    subject: 'Documento de proveedor aceptado automaticamente',
                    heading: 'Documento aceptado automaticamente',
                    intro: 'El sistema valido el RFC, el resultado de cumplimiento y la vigencia del documento.',
                    details: [
                        'Proveedor' => $supplier?->company_name ?? 'N/A',
                        'RFC' => $supplier?->rfc ?? 'N/A',
                        'Documento' => $type?->name ?? $document->doc_type,
                        'Fecha de emision' => $issuedAt->toDateString(),
                        'Vigente hasta' => $expiresAt?->toDateString() ?? 'N/A',
                    ],
                    url: route('admin.review.suppliers.show', $document->supplier_id),
                    buttonLabel: 'Ver proveedor',
                    message: 'El sistema acepto automaticamente '.$document->doc_type.' del proveedor '.($supplier?->company_name ?? 'N/A').'.',
                    context: [
                        'supplier_id' => $document->supplier_id,
                        'supplier_document_id' => $document->id,
                        'document_code' => $document->doc_type,
                    ],
                ));
            } catch (\Throwable $exception) {
                Log::error('No fue posible notificar la aceptacion automatica de un documento de proveedor.', [
                    'supplier_document_id' => $document->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        });
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
