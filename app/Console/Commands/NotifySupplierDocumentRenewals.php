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
            if ($days > 7) {
                return;
            }

            $milestone = collect([0, 1, 3, 5, 7])
                ->first(fn (int $threshold) => $days <= $threshold);

            if ($milestone === null) {
                return;
            }

            $notice = SupplierDocumentRequirementNotification::firstOrCreate([
                'supplier_document_requirement_id' => $requirement->id,
                'milestone_days' => $milestone,
            ]);
            if (! $notice->wasRecentlyCreated) {
                return;
            }
            if ($days <= 0) {
                $requirement->update(['status' => 'expired']);
                $requirement->supplier->recalculateDocumentStatus();
            }
            app(\App\Services\SafeNotificationService::class)->notify(
                new SupplierDocumentRenewalNotification($requirement, max($days, 0)),
                [$requirement->supplier],
                'de renovación documental de proveedor',
                $requirement->supplier->company_name,
            );
        });

        return self::SUCCESS;
    }
}
