<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BudgetProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function subaccounts(): BelongsToMany
    {
        return $this->belongsToMany(Subaccount::class, 'budget_profile_subaccount');
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'budget_profile_department');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
