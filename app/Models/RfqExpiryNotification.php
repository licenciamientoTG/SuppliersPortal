<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfqExpiryNotification extends Model
{
    protected $fillable = [
        'rfq_id',
        'user_id',
    ];

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
