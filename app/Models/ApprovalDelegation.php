<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalDelegation extends Model
{
    protected $fillable = [
        'delegator_user_id',
        'status',
        'starts_at',
        'ends_at',
        'deactivated_at',
        'deactivated_by_user_id',
        'deactivation_reason',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'deactivated_at' => 'datetime',
    ];

    public function delegator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegator_user_id');
    }

    public function deactivatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ApprovalDelegationMember::class);
    }

    public function activeMembers(): HasMany
    {
        return $this->members()
            ->where('added_at', '<=', now())
            ->where(function ($query) {
                $query->whereNull('removed_at')->orWhere('removed_at', '>', now());
            });
    }

    public function scopeEffective($query)
    {
        return $query->where('status', 'ACTIVE')
            ->where('starts_at', '<=', now())
            ->where(function ($subQuery) {
                $subQuery->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->whereNull('deactivated_at');
    }

    public function isEffective(): bool
    {
        return $this->status === 'ACTIVE'
            && $this->deactivated_at === null
            && $this->starts_at?->lte(now())
            && ($this->ends_at === null || $this->ends_at->gt(now()));
    }
}
