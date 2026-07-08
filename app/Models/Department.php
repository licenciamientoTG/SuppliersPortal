<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'abbreviated',
        'is_active',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Uppercase y trimming para el abreviado
    public function setAbbreviatedAttribute($value): void
    {
        $this->attributes['abbreviated'] = Str::upper(trim($value));
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function subaccounts()
    {
        return $this->belongsToMany(Subaccount::class, 'department_subaccount');
    }

    public function budgetProfiles(): BelongsToMany
    {
        return $this->belongsToMany(BudgetProfile::class, 'budget_profile_department');
    }

    /**
     * Devuelve los departamentos activos
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
