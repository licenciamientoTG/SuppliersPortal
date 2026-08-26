<?php

namespace App\Services;

use App\Models\SupplierDocument;
use App\Models\User;
use App\Notifications\SupplierDocumentFileCompletedNotification;
use App\Notifications\SupplierDocumentReviewedNotification;
use Illuminate\Support\Facades\DB;

class SupplierDocumentReviewService
{
    public function __construct(private readonly SupplierDocumentRequirementService $requirements) {}

    /**
     * @param  array<string, mixed>|null  $validationMetadata
     */
    public function accept(
        SupplierDocument $document,
        ?string $issuedAt,
        ?User $reviewer,
        ?array $validationMetadata = null,
        bool $automatic = false,
    ): SupplierDocument {
        return DB::transaction(function () use ($document, $issuedAt, $reviewer, $validationMetadata, $automatic): SupplierDocument {
            $locked = SupplierDocument::query()
                ->with(['supplier', 'documentType', 'requirement'])
                ->lockForUpdate()
                ->findOrFail($document->id);

            if ($locked->status === 'accepted') {
                return $locked;
            }

            $previousDocumentStatus = $locked->supplier?->document_status;
            $locked->update([
                'status' => 'accepted',
                'rejection_reason' => null,
                'reviewed_by' => $reviewer?->id,
                'reviewed_at' => now(),
                'issue_date_extraction_data' => $validationMetadata ?? $locked->issue_date_extraction_data,
            ]);
            $this->requirements->accept($locked, $issuedAt, $reviewer?->id);
            $newDocumentStatus = $locked->supplier?->recalculateDocumentStatus();

            $this->notifyAfterCommit(
                $locked,
                accepted: true,
                automatic: $automatic,
                fileCompleted: $previousDocumentStatus !== 'approved' && $newDocumentStatus === 'approved',
            );

            return $locked->refresh();
        });
    }

    public function reject(SupplierDocument $document, string $reason, User $reviewer): SupplierDocument
    {
        return DB::transaction(function () use ($document, $reason, $reviewer): SupplierDocument {
            $locked = SupplierDocument::query()
                ->with(['supplier', 'documentType', 'requirement'])
                ->lockForUpdate()
                ->findOrFail($document->id);

            if ($locked->status === 'rejected' && $locked->rejection_reason === $reason) {
                return $locked;
            }

            $locked->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);
            $this->requirements->reject($locked);
            $locked->supplier?->recalculateDocumentStatus();

            $this->notifyAfterCommit($locked, accepted: false);

            return $locked->refresh();
        });
    }

    private function notifyAfterCommit(
        SupplierDocument $document,
        bool $accepted,
        bool $automatic = false,
        bool $fileCompleted = false,
    ): void {
        $documentId = $document->id;

        DB::afterCommit(function () use ($documentId, $accepted, $automatic, $fileCompleted): void {
            $freshDocument = SupplierDocument::with(['supplier', 'documentType', 'requirement'])->find($documentId);
            $supplier = $freshDocument?->supplier;

            if (! $freshDocument || ! $supplier) {
                return;
            }

            app(SafeNotificationService::class)->notify(
                new SupplierDocumentReviewedNotification($freshDocument, $accepted, $automatic),
                [$supplier],
                'de revisión de documento de proveedor',
                (string) $freshDocument->id,
            );

            if ($fileCompleted) {
                app(SafeNotificationService::class)->notify(
                    new SupplierDocumentFileCompletedNotification($supplier->isApproved()),
                    [$supplier],
                    'de expediente completo de proveedor',
                    (string) $freshDocument->id,
                );
            }
        });
    }
}
