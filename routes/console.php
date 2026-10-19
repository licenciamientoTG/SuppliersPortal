<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Todos los jobs de este archivo corren vía el mismo cron del servidor
// (`* * * * * php artisan schedule:run`). Estándar para cualquier entrada nueva:
// ->withoutOverlapping(), ->runInBackground() salvo que sea muy ligera y frecuente
// (ver approval-delegations:expire), y ->appendOutputTo(storage_path('logs/<command>.log'))
// con el nombre del comando tal cual, sustituyendo ':' por '-'.

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
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/exchange-rates.log'));

// Avisos de vencimiento de documentos de proveedores (7, 5, 3, 1 días antes y el día 0)
Schedule::command('supplier-documents:notify-renewals')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/supplier-document-renewals.log'));

// Alertas de contratos comerciales a 30, 15 y 1 día del vencimiento
Schedule::command('contracts:notify-expiring')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/contract-expiry-alerts.log'));

// Alertas de RFQ vencidas para el requisitor y Compras
Schedule::command('rfqs:notify-expired')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/rfq-expiry-alerts.log'));

// Cierra delegaciones de aprobación vencidas (ends_at ya pasó)
Schedule::command('approval-delegations:expire')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/approval-delegations-expire.log'));

// Suspendido: falta darle formato institucional al correo. Reactivar cuando esté listo.
// Schedule::command('budget:send-daily-alerts')
//     ->dailyAt('06:00')
//     ->timezone('America/Ciudad_Juarez')
//     ->withoutOverlapping();
