<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetMovementApprovalSetting extends Model
{
    protected $fillable = ['director_user_id', 'substitute_user_id', 'substitute_starts_at', 'substitute_ends_at', 'updated_by'];

    protected $casts = ['substitute_starts_at' => 'datetime', 'substitute_ends_at' => 'datetime'];

    public function director(): BelongsTo
    {
        return $this->belongsTo(User::class, 'director_user_id');
    }

    public function substitute(): BelongsTo
    {
        return $this->belongsTo(User::class, 'substitute_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function executiveApproverIds(): array
    {
        $ids = array_filter([$this->director_user_id]);
        if ($this->substitute_user_id && $this->substitute_starts_at?->lte(now()) && $this->substitute_ends_at?->gt(now())) {
            $ids[] = $this->substitute_user_id;
        }

        return array_map('intval', $ids);
    }

    public function canApprove(User $user): bool
    {
        return in_array($user->id, $this->executiveApproverIds(), true);
    }
}
