<?php

if (! function_exists('format_amount')) {
    function format_amount(float|int|string|null $amount, int $decimals = 2): string
    {
        return number_format((float) ($amount ?? 0), $decimals, '.', ',');
    }
}

if (! function_exists('currency_symbol')) {
    function currency_symbol(?string $currency = null): string
    {
        return match (strtoupper((string) ($currency ?? 'MXN'))) {
            'USD' => 'US$',
            'EUR' => '€',
            default => '$',
        };
    }
}

if (! function_exists('format_money')) {
    function format_money(float|int|string|null $amount, ?string $currency = null, bool $appendCurrencyCode = false): string
    {
        $formatted = currency_symbol($currency) . format_amount($amount, 2);

        if (! $appendCurrencyCode || blank($currency)) {
            return $formatted;
        }

        return $formatted . ' ' . strtoupper((string) $currency);
    }
}

if (! function_exists('format_days')) {
    function format_days(float|int|string|null $days): string
    {
        return (string) ((int) round((float) ($days ?? 0)));
    }
}
