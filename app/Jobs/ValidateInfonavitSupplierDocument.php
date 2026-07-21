<?php

namespace App\Jobs;

use App\Models\SupplierDocument;
use App\Services\InfonavitQrValidationService;
use App\Services\SupplierDocumentAutoAcceptanceService;
use App\Services\SupplierDocumentRequirementService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ValidateInfonavitSupplierDocument implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(public readonly int $documentId) {}

    public function handle(InfonavitQrValidationService $infonavit, SupplierDocumentAutoAcceptanceService $autoAcceptance, SupplierDocumentRequirementService $requirements): void
    {
        $document = SupplierDocument::query()
            ->whereKey($this->documentId)
            ->where('doc_type', 'opinion_infonavit')
            ->where('status', 'pending_review')
            ->first();

        if (! $document) {
            return;
        }

        if ($infonavit->validateDocument($document)) {
            $document->refresh();
            $autoAcceptance->acceptIfEligible($document, $requirements);

            return;
        }

        if ($document->fresh()?->status === 'pending_review') {
            $this->release(120);
        }
    }
}
