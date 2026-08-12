<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetMovementDecision extends Model
{
    public const STAGE_ORIGIN = 'ORIGEN';

    public const STAGE_EXECUTIVE = 'DIRECCION';

    public const ACTION_SUBMITTED = 'ENVIADO';

    public const ACTION_APPROVED = 'APROBADO';

    public const ACTION_RETURNED = 'DEVUELTO';

    public const ACTION_REJECTED = 'RECHAZADO';

    protected $fillable = ['budget_movement_id', 'stage', 'action', 'actor_user_id', 'comments'];

    public function movement(): BelongsTo
    {
        return $this->belongsTo(BudgetMovement::class, 'budget_movement_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
