<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\ApprovalDelegationService;

class PurchaseOrderPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('superadmin') ? true : null;
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        if ($user->hasRole('buyer')) {
            return true;
        }

        if ((int) $purchaseOrder->created_by === (int) $user->id) {
            return true;
        }

        if (app(ApprovalDelegationService::class)->canAct($user, $purchaseOrder->assigned_approver_id)) {
            return true;
        }

        $requisition = $purchaseOrder->requisition;

        return $requisition !== null && (
            (int) $requisition->requested_by === (int) $user->id
            || (int) $requisition->created_by === (int) $user->id
        );
    }
}
