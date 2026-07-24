<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalDecision extends Model
{
    protected $fillable = [
        'approvable_type',
        'approvable_id',
        'assigned_principal_user_id',
        'acted_by_user_id',
        'approval_delegation_id',
        'action',
        'comments',
        'acted_at',
    ];

    protected $casts = [
        'acted_at' => 'datetime',
    ];

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function principal(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_principal_user_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by_user_id');
    }

    public function delegation(): BelongsTo
    {
        return $this->belongsTo(ApprovalDelegation::class, 'approval_delegation_id');
    }
}
