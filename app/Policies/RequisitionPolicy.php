<?php

namespace App\Policies;

use App\Models\Requisition;
use App\Models\User;

class RequisitionPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('superadmin') ? true : null;
    }

    /** Compras necesita consultar el expediente para operar el flujo. */
    public function view(User $user, Requisition $requisition): bool
    {
        return $user->hasRole('buyer')
            || $this->isOwner($user, $requisition)
            || (int) $requisition->department?->manager_user_id === (int) $user->id
            || $this->isAssignedApprover($user, $requisition);
    }

    /** Solo quien la solicitó puede modificar o cancelar su requisición. */
    public function update(User $user, Requisition $requisition): bool
    {
        return $this->isOwner($user, $requisition);
    }

    private function isOwner(User $user, Requisition $requisition): bool
    {
        return (int) $requisition->requested_by === (int) $user->id
            || (int) $requisition->created_by === (int) $user->id;
    }

    private function isAssignedApprover(User $user, Requisition $requisition): bool
    {
        $principalIds = app(\App\Services\ApprovalDelegationService::class)->accessiblePrincipalIds($user);

        return $requisition->quotationSummaries()
            ->whereIn('current_approver_user_id', $principalIds)
            ->exists()
            || $requisition->purchaseOrders()
                ->whereIn('assigned_approver_id', $principalIds)
                ->exists();
    }
}
