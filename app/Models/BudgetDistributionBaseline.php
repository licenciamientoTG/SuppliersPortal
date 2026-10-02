<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class BudgetDistributionBaseline extends Model
{
    protected $guarded = [];

    protected $casts = [
        'month' => 'integer',
        'original_amount' => 'decimal:2',
        'captured_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('La base presupuestal aprobada es inmutable.'));
        static::deleting(fn () => throw new LogicException('La base presupuestal aprobada no puede eliminarse.'));
    }
}
