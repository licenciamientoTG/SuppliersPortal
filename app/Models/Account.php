<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'legacy_expense_category_id',
        'code',
        'name',
        'description',
        'account_category',
        'is_fixed_asset',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_fixed_asset' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function subaccounts(): HasMany
    {
        return $this->hasMany(Subaccount::class);
    }

    public function productServices(): BelongsToMany
    {
        return $this->belongsToMany(ProductService::class, 'account_product_service');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
