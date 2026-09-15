<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuthorizerRole extends Model
{
    protected $fillable = [
        'name',
        'approval_limit',
        'is_unlimited',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'approval_limit' => 'decimal:2',
        'is_unlimited' => 'boolean',
        'display_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(UserAuthorizerRole::class);
    }

    public function quotationSummaries(): HasMany
    {
        return $this->hasMany(QuotationSummary::class);
    }

    public function directPurchaseOrders(): HasMany
    {
        return $this->hasMany(DirectPurchaseOrder::class);
    }
}
