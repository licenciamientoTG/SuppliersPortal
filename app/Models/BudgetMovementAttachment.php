<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetMovementAttachment extends Model
{
    protected $fillable = ['budget_movement_id', 'original_name', 'file_path', 'mime_type', 'size_bytes', 'uploaded_by'];

    public function movement(): BelongsTo
    {
        return $this->belongsTo(BudgetMovement::class, 'budget_movement_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
