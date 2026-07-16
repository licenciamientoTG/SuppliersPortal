<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierDocumentType extends Model
{
    public const RENEWAL_MODES = ['once', 'periodic'];

    public const VALIDITY_SOURCES = ['manual', 'qr'];

    protected $fillable = [
        'code', 'name', 'description', 'is_active', 'is_required',
        'applies_to_physical', 'applies_to_legal', 'requires_repse',
        'renewal_mode', 'renewal_interval_days', 'validity_source', 'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean', 'is_required' => 'boolean',
            'applies_to_physical' => 'boolean', 'applies_to_legal' => 'boolean',
            'requires_repse' => 'boolean', 'activated_at' => 'datetime',
        ];
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(SupplierDocumentRequirement::class);
    }

    public function appliesTo(Supplier $supplier): bool
    {
        $matchesPerson = ($supplier->person_type === 'fisica' && $this->applies_to_physical)
            || ($supplier->person_type === 'moral' && $this->applies_to_legal);

        return $matchesPerson && (! $this->requires_repse || $supplier->requiresRepseRegistration());
    }

    public function scopeRequiredForSupplier(Builder $query, Supplier $supplier): Builder
    {
        return $query->where('is_active', true)->where('is_required', true)
            ->where(function (Builder $personQuery) use ($supplier) {
                $personQuery->where($supplier->person_type === 'fisica' ? 'applies_to_physical' : 'applies_to_legal', true);
            })
            ->where(function (Builder $repseQuery) use ($supplier) {
                $repseQuery->where('requires_repse', false);
                if ($supplier->requiresRepseRegistration()) {
                    $repseQuery->orWhere('requires_repse', true);
                }
            });
    }
}
