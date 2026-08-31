<?php

namespace App\Observers;

use App\Models\Requisition;
use App\Models\RequisitionStatusHistory;

class RequisitionObserver
{
    private function value(mixed $status): ?string
    {
        return is_object($status) && isset($status->value) ? $status->value : ($status === null ? null : (string) $status);
    }

    public function created(Requisition $requisition): void
    {
        RequisitionStatusHistory::firstOrCreate([
            'requisition_id' => $requisition->id, 'event_type' => 'CREATED', 'to_status' => $this->value($requisition->status),
        ], ['occurred_at' => $requisition->created_at, 'user_id' => $requisition->created_by ?? $requisition->requested_by]);
    }

    public function updated(Requisition $requisition): void
    {
        if (! $requisition->wasChanged('status')) return;
        RequisitionStatusHistory::create([
            'requisition_id' => $requisition->id,
            'from_status' => $this->value($requisition->getOriginal('status')),
            'to_status' => $this->value($requisition->status),
            'event_type' => 'STATUS_CHANGED', 'occurred_at' => $requisition->updated_at,
            'user_id' => $requisition->updated_by ?? auth()->id(),
        ]);
    }
}
