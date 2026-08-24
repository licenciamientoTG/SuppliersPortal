<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LedgerAccount extends Model
{
    protected $fillable = [
        'one_goal_id',
        'parent_id',
        'one_goal_parent_id',
        'code',
        'name',
        'alternate_name',
        'nature',
        'account_level',
        'one_goal_type_id',
        'one_goal_external_system_id',
        'is_active',
        'is_selectable',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_selectable' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('code');
    }

    public function taxGroupItems(): HasMany
    {
        return $this->hasMany(TaxGroupItem::class);
    }

    public function scopeSelectable(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('is_selectable', true);
    }

    public function getDisplayLabelAttribute(): string
    {
        return trim(implode(' — ', array_filter([$this->code, $this->name])));
    }
}
