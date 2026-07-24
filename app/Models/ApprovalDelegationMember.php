<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalDelegationMember extends Model
{
    protected $fillable = [
        'approval_delegation_id',
        'delegate_user_id',
        'added_at',
        'removed_at',
    ];

    protected $casts = [
        'added_at' => 'datetime',
        'removed_at' => 'datetime',
    ];

    public function delegation(): BelongsTo
    {
        return $this->belongsTo(ApprovalDelegation::class, 'approval_delegation_id');
    }

    public function delegate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegate_user_id');
    }

    public function scopeEffective($query)
    {
        return $query->where('added_at', '<=', now())
            ->where(function ($subQuery) {
                $subQuery->whereNull('removed_at')->orWhere('removed_at', '>', now());
            });
    }
}
