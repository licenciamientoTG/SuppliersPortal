<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxGroup extends Model
{
    protected $fillable = [
        'one_goal_id',
        'name',
        'one_goal_type_id',
        'one_goal_compound_id',
        'is_payment_tax',
        'is_border_zone',
        'is_vat_tax',
        'sat_tax_object',
        'is_south_border_zone',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_payment_tax' => 'boolean',
            'is_border_zone' => 'boolean',
            'is_vat_tax' => 'boolean',
            'is_south_border_zone' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(TaxGroupItem::class)->orderBy('one_goal_id');
    }
}
