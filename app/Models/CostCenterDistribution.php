<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostCenterDistribution extends Model
{
    protected $fillable = ['distribution_cost_center_id', 'target_cost_center_id', 'percentage'];

    protected $casts = ['percentage' => 'decimal:4'];

    public function distributionCostCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'distribution_cost_center_id');
    }

    public function targetCostCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'target_cost_center_id');
    }
}
