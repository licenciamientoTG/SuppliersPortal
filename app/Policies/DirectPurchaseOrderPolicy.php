<?php

namespace App\Policies;

use App\Models\DirectPurchaseOrder;
use App\Models\User;
use App\Services\ApprovalDelegationService;

class DirectPurchaseOrderPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('superadmin') ? true : null;
    }

    public function view(User $user, DirectPurchaseOrder $directPurchaseOrder): bool
    {
        return $user->hasRole('buyer')
            || (int) $directPurchaseOrder->created_by === (int) $user->id
            || app(ApprovalDelegationService::class)->canAct($user, $directPurchaseOrder->assigned_approver_id);
    }

    public function update(User $user, DirectPurchaseOrder $directPurchaseOrder): bool
    {
        return (int) $directPurchaseOrder->created_by === (int) $user->id;
    }
}
