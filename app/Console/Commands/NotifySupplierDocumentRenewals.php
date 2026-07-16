<?php

namespace App\Console\Commands;

use App\Models\SupplierDocumentRequirement;
use App\Models\SupplierDocumentRequirementNotification;
use App\Notifications\SupplierDocumentRenewalNotification;
use Illuminate\Console\Command;

class NotifySupplierDocumentRenewals extends Command
{
    protected $signature = 'supplier-documents:notify-renewals';

    protected $description = 'Envia avisos de renovacion y vencimiento documental a proveedores.';

    public function handle(): int
    {
        $today = now()->startOfDay();
        SupplierDocumentRequirement::query()->with(['supplier', 'documentType'])->where('is_enforced', true)->whereNotNull('expires_at')->whereHas('documentType', fn ($query) => $query->where('is_active', true)->where('is_required', true))->orderBy('id')->each(function (SupplierDocumentRequirement $requirement) use ($today): void {
            $days = $today->diffInDays($requirement->expires_at->copy()->startOfDay(), false);
            if (! in_array($days, [7, 5, 3, 1, 0], true)) {
                return;
            }
            $notice = SupplierDocumentRequirementNotification::firstOrCreate(['supplier_document_requirement_id' => $requirement->id, 'milestone_days' => $days]);
            if (! $notice->wasRecentlyCreated) {
                return;
            }
            if ($days === 0) {
                $requirement->update(['status' => 'expired']);
                $requirement->supplier->recalculateDocumentStatus();
            }
            $requirement->supplier->notify(new SupplierDocumentRenewalNotification($requirement, $days));
        });

        return self::SUCCESS;
    }
}
