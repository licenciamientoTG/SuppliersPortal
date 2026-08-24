<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxCode extends Model
{
    protected $fillable = [
        'one_goal_id',
        'name',
        'rate',
        'calculation_type',
        'one_goal_tax_type_id',
        'is_vat',
        'is_withholding',
        'is_exempt',
        'is_active',
        'is_selectable',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'is_vat' => 'boolean',
            'is_withholding' => 'boolean',
            'is_exempt' => 'boolean',
            'is_active' => 'boolean',
            'is_selectable' => 'boolean',
        ];
    }

    public function satRetenciones(): HasMany
    {
        return $this->hasMany(SatRetencion::class);
    }

    public function scopeSelectable(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('is_selectable', true);
    }
}
