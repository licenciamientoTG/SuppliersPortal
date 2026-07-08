<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subaccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'legacy_budget_cedula_id',
        'account_id',
        'name',
        'subaccount_category',
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

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function productServices(): BelongsToMany
    {
        return $this->belongsToMany(ProductService::class, 'product_service_subaccount');
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'department_subaccount');
    }

    public function budgetProfiles(): BelongsToMany
    {
        return $this->belongsToMany(BudgetProfile::class, 'budget_profile_subaccount');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'subaccount_user');
    }

    public function requisitionItems(): HasMany
    {
        return $this->hasMany(RequisitionItem::class, 'budget_cedula_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
