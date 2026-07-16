<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierDocumentType extends Model
{
    public const RENEWAL_MODES = ['once', 'periodic'];

    public const VALIDITY_SOURCES = ['manual', 'qr'];

    public const PERIODICITY_UNITS = ['days', 'weeks', 'months', 'years'];

    protected $fillable = [
        'code', 'name', 'description', 'is_active', 'is_required',
        'applies_to_physical', 'applies_to_legal', 'requires_repse',
        'renewal_mode', 'renewal_interval_days', 'renewal_interval_value', 'renewal_interval_unit', 'validity_source', 'activated_at',
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

    public function periodicityValue(): ?int
    {
        return $this->renewal_interval_value ?? $this->renewal_interval_days;
    }

    public function periodicityUnit(): string
    {
        return $this->renewal_interval_unit ?: 'days';
    }

    public function calculateExpiry(CarbonInterface $from): ?CarbonInterface
    {
        if ($this->renewal_mode !== 'periodic' || ! $this->periodicityValue()) {
            return null;
        }

        $value = $this->periodicityValue();

        return match ($this->periodicityUnit()) {
            'weeks' => $from->copy()->addWeeks($value),
            'months' => $from->copy()->addMonthsNoOverflow($value),
            'years' => $from->copy()->addYearsNoOverflow($value),
            default => $from->copy()->addDays($value),
        };
    }

    public function periodicityLabel(): string
    {
        if ($this->renewal_mode !== 'periodic' || ! $this->periodicityValue()) {
            return 'Una sola vez';
        }

        $value = $this->periodicityValue();
        $labels = [
            'days' => ['día', 'días'],
            'weeks' => ['semana', 'semanas'],
            'months' => ['mes', 'meses'],
            'years' => ['año', 'años'],
        ];
        $unit = $labels[$this->periodicityUnit()] ?? $labels['days'];

        return 'Cada '.$value.' '.($value === 1 ? $unit[0] : $unit[1]);
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
