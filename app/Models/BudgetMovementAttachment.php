<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetMovementAttachment extends Model
{
    protected $guarded = [];

    protected $casts = ['file_size' => 'integer'];

    public function movement(): BelongsTo
    {
        return $this->belongsTo(BudgetMovement::class, 'budget_movement_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
