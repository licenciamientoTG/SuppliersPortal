<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequisitionStatusHistory extends Model
{
    protected $fillable = ['requisition_id', 'from_status', 'to_status', 'event_type', 'occurred_at', 'user_id', 'notes'];

    protected $casts = ['occurred_at' => 'datetime'];

    public function requisition(): BelongsTo { return $this->belongsTo(Requisition::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
