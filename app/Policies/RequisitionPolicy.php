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
        return $user->hasRole('buyer') || $this->isOwner($user, $requisition);
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
}
