<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'currency_from',
        'currency_to',
        'rate',
        'fetched_at',
    ];

    protected $casts = [
        'fetched_at' => 'datetime',
        'rate' => 'decimal:4',
    ];

    public static function current(string $from, string $to): ?self
    {
        return static::where('currency_from', $from)
            ->where('currency_to', $to)
            ->latest('fetched_at')
            ->first();
    }

    /**
     * Tipo de cambio vigente de $currency a MXN. 1.0 si ya es MXN.
     *
     * @throws \RuntimeException si no hay tipo de cambio sincronizado para esa moneda.
     */
    public static function rateToMxn(string $currency): float
    {
        if ($currency === 'MXN') {
            return 1.0;
        }

        $rate = static::current($currency, 'MXN');

        if (! $rate) {
            throw new \RuntimeException("No hay tipo de cambio sincronizado para {$currency} -> MXN.");
        }

        return (float) $rate->rate;
    }

    /**
     * Convierte un monto de $currency a MXN usando el tipo de cambio vigente.
     *
     * @throws \RuntimeException si no hay tipo de cambio sincronizado para esa moneda.
     */
    public static function convertToMxn(float $amount, string $currency): float
    {
        if ($currency === 'MXN') {
            return $amount;
        }

        return round($amount * static::rateToMxn($currency), 2);
    }
}
