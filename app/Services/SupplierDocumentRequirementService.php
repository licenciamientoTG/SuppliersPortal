<?php

namespace App\Services;

use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Models\SupplierDocumentRequirement;
use App\Models\SupplierDocumentType;
use Illuminate\Support\Collection;

class SupplierDocumentRequirementService
{
    public function ensureForSupplier(Supplier $supplier, bool $graceForNewRequirements = false): Collection
    {
        $types = SupplierDocumentType::query()->requiredForSupplier($supplier)->get();

        foreach ($types as $type) {
            $requirement = SupplierDocumentRequirement::firstOrNew([
                'supplier_id' => $supplier->id,
                'supplier_document_type_id' => $type->id,
            ]);

            if (! $requirement->exists) {
                $requirement->fill([
                    'is_enforced' => true,
                    'status' => 'pending',
                    'due_at' => $graceForNewRequirements ? now()->addDays(14) : null,
                ])->save();
            } elseif (! $requirement->is_enforced) {
                $requirement->fill([
                    'is_enforced' => true,
                    'status' => 'pending',
                    'due_at' => $graceForNewRequirements ? now()->addDays(14) : null,
                ])->save();
            }

            $this->refreshRequirement($requirement);
        }

        return $supplier->documentRequirements()->with(['documentType', 'currentDocument'])->get();
    }

    public function synchronizeExistingForType(SupplierDocumentType $type): void
    {
        Supplier::query()->orderBy('id')->each(function (Supplier $supplier) use ($type): void {
            $requirement = SupplierDocumentRequirement::firstOrNew([
                'supplier_id' => $supplier->id,
                'supplier_document_type_id' => $type->id,
            ]);

            if (! $type->is_active || ! $type->is_required || ! $type->appliesTo($supplier)) {
                if ($requirement->exists) {
                    $requirement->update(['is_enforced' => false]);
                }

                return;
            }

            $wasEnforced = $requirement->exists && $requirement->is_enforced;
            if (! $requirement->exists) {
                $requirement->fill(['status' => 'pending']);
            }
            $requirement->fill([
                'is_enforced' => true,
                'due_at' => $wasEnforced ? $requirement->due_at : now()->addDays(14),
            ])->save();
            $this->refreshRequirement($requirement);
        });
    }

    public function requirementForUpload(Supplier $supplier, SupplierDocumentType $type): ?SupplierDocumentRequirement
    {
        if (! $type->is_required || ! $type->appliesTo($supplier)) {
            return null;
        }

        $this->ensureForSupplier($supplier);

        return SupplierDocumentRequirement::query()
            ->where('supplier_id', $supplier->id)
            ->where('supplier_document_type_id', $type->id)
            ->first();
    }

    public function markSubmitted(SupplierDocumentRequirement $requirement): void
    {
        $requirement->update(['status' => 'submitted']);
    }

    public function accept(SupplierDocument $document, ?string $documentExpirationDate, ?int $reviewerId): void
    {
        $type = $document->documentType;
        $requirement = $document->requirement;
        if (! $type || ! $requirement) {
            return;
        }

        $expiresAt = $type->renewal_mode === 'periodic'
            ? now()->addDays((int) $type->renewal_interval_days)
            : null;

        $document->update([
            'document_expiration_date' => $documentExpirationDate,
            'expiration_verified_at' => $type->renewal_mode === 'periodic' ? now() : null,
            'expiration_verified_by' => $type->renewal_mode === 'periodic' ? $reviewerId : null,
        ]);

        $requirement->update([
            'current_document_id' => $document->id,
            'status' => 'compliant',
            'due_at' => null,
            'expires_at' => $expiresAt,
            'fulfilled_at' => now(),
        ]);
    }

    public function reject(SupplierDocument $document): void
    {
        if ($document->requirement) {
            $document->requirement->update(['status' => 'rejected']);
        }
    }

    public function refreshRequirement(SupplierDocumentRequirement $requirement): void
    {
        $type = $requirement->documentType ?: SupplierDocumentType::find($requirement->supplier_document_type_id);
        if (! $type) {
            return;
        }

        $latestAccepted = $requirement->supplier->documents()
            ->where(function ($query) use ($type) {
                $query->where('supplier_document_type_id', $type->id)
                    ->orWhere('doc_type', $type->code);
            })
            ->where('status', 'accepted')
            ->latest('uploaded_at')
            ->first();

        if ($latestAccepted) {
            $expiresAt = $requirement->expires_at;
            if ($type->renewal_mode === 'periodic' && ! $expiresAt) {
                $expiresAt = ($latestAccepted->reviewed_at ?? $latestAccepted->uploaded_at ?? now())
                    ->copy()->addDays((int) $type->renewal_interval_days);
            }
            $requirement->update([
                'current_document_id' => $latestAccepted->id,
                'status' => $expiresAt && $expiresAt->isPast() ? 'expired' : 'compliant',
                'expires_at' => $expiresAt,
                'fulfilled_at' => $latestAccepted->reviewed_at ?? $latestAccepted->uploaded_at,
            ]);
            $latestAccepted->update([
                'supplier_document_type_id' => $type->id,
                'supplier_document_requirement_id' => $requirement->id,
            ]);

            return;
        }

        $latest = $requirement->supplier->documents()
            ->where(function ($query) use ($type) {
                $query->where('supplier_document_type_id', $type->id)
                    ->orWhere('doc_type', $type->code);
            })
            ->latest('uploaded_at')
            ->first();
        $requirement->update(['status' => $latest?->status === 'rejected' ? 'rejected' : ($latest ? 'submitted' : 'pending')]);
    }

    public function hasBlockingRequirements(Supplier $supplier): bool
    {
        $requirements = $this->ensureForSupplier($supplier);

        foreach ($requirements->where('is_enforced', true) as $requirement) {
            $type = $requirement->documentType;
            if (! $type?->is_active || ! $type->is_required) {
                continue;
            }
            if (in_array($requirement->status, ['rejected', 'expired'], true)) {
                return true;
            }
            if (! $requirement->current_document_id && (! $requirement->due_at || $requirement->due_at->isPast())) {
                return true;
            }
            if ($requirement->expires_at && $requirement->expires_at->isPast()) {
                return true;
            }
        }

        return false;
    }
}
