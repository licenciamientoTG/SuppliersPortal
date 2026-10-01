<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetThresholdAlert extends Model
{
    protected $guarded = [];

    protected $casts = ['usage_percent' => 'decimal:2', 'notified_at' => 'datetime'];
}
