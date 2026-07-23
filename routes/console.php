<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cierre automático por inactividad de OC directas (7 días) y estándar (10 días)
Schedule::command('purchase-orders:close-inactive')
    ->dailyAt('00:30')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/close-inactive-purchase-orders.log'));

// Sincronización del tipo de cambio USD/MXN — cada hora, L-V, 8-18h
Schedule::command('exchange-rates:sync')
    ->hourly()
    ->weekdays()
    ->between('8:00', '18:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/exchange-rates.log'));

Schedule::command('supplier-documents:notify-renewals')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/supplier-document-renewals.log'));

// Alertas de contratos comerciales a 30, 15 y 1 día del vencimiento
Schedule::command('contracts:notify-expiring')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/contract-expiry-alerts.log'));
