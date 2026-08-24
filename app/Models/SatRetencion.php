<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SatRetencion extends Model
{
    protected $table = 'sat_retenciones';

    protected $fillable = [
        'clave',
        'tax_code_id',
        'nombre',
        'impuesto',
        'descripcion',
        'porcentaje',
        'porcentaje_display',
        'base_calculo',
        'aplica_cuando',
        'base_legal',
        'requiere_cfdi_retencion',
        'notas',
        'activo',
    ];

    protected $casts = [
        'porcentaje' => 'decimal:4',
        'requiere_cfdi_retencion' => 'boolean',
        'activo' => 'boolean',
    ];

    // ── Scopes ──────────────────────────────────────────────

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class);
    }

    public function scopeDeImpuesto(Builder $query, string $impuesto): Builder
    {
        return $query->where('impuesto', strtoupper($impuesto));
    }

    public function scopeConCfdiRetencion(Builder $query): Builder
    {
        return $query->where('requiere_cfdi_retencion', true);
    }

    // ── Helpers ─────────────────────────────────────────────

    /**
     * Calcula el monto a retener dado un importe.
     * Devuelve null si la retención es de tasa variable.
     */
    public function calcularRetencion(float $importe): ?float
    {
        if (is_null($this->porcentaje)) {
            return null;
        }

        return round($importe * ($this->porcentaje / 100), 2);
    }

    public function esTasaVariable(): bool
    {
        return is_null($this->porcentaje);
    }
}
