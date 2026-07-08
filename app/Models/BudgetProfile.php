<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function employeePositions(): HasMany
    {
        return $this->hasMany(EmployeePositionBudgetProfile::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
