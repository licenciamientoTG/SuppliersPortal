<?php

namespace App\Console\Commands;

use App\Models\CostCenter;
use App\Models\User;
use App\Notifications\BudgetDailySummaryNotification;
use App\Reports\Budget\BudgetAlertsReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * RP-03 · Resumen diario: un correo por responsable con todos sus renglones en riesgo y uno para
 * Contraloría con todos. Los avisos al cruzar un umbral salen aparte, por evento (BudgetAlertService).
 */
class SendBudgetDailyAlerts extends Command
{
    protected $signature = 'budget:send-daily-alerts {--dry-run : Muestra a quién se enviaría sin mandar correos}';

    protected $description = 'Envía el resumen diario de renglones presupuestales en riesgo (RP-03)';

    public function handle(BudgetAlertsReport $report): int
    {
        $year = now(config('app.timezone'))->year;
        $lines = $report->riskLines(CostCenter::query()->pluck('id'), [
            'fiscal_year' => $year, 'threshold' => 80, 'months' => [],
            'exceptions_from' => "{$year}-01-01", 'exceptions_to' => "{$year}-12-31",
        ]);
        // El resumen avisa de consumo y sobregiro; las líneas que solo tienen excepción quedan en el reporte.
        $lines = $lines->whereNotNull('alert_level')->values();

        $sent = 0;
        foreach ($lines->groupBy('responsible_user_id') as $userId => $own) {
            $user = $userId ? User::query()->where('is_active', true)->find($userId) : null;
            if (! $user) {
                continue;
            }
            $this->line("{$user->email}: {$own->count()} renglones");
            if (! $this->option('dry-run')) {
                $sent += $this->send(fn () => $user->notify(new BudgetDailySummaryNotification($own->values())), $user->email);
            }
        }

        $controller = config('budget_alerts.controller_email');
        if ($controller && $lines->isNotEmpty()) {
            $this->line("{$controller}: {$lines->count()} renglones (Contraloría)");
            if (! $this->option('dry-run')) {
                $sent += $this->send(fn () => Notification::route('mail', $controller)->notify(new BudgetDailySummaryNotification($lines)), $controller);
            }
        }

        $this->info("Renglones en riesgo: {$lines->count()}. Correos enviados: {$sent}.");

        return self::SUCCESS;
    }

    /** Un correo que falla no detiene el resto del resumen. */
    private function send(callable $notify, string $recipient): int
    {
        try {
            $notify();

            return 1;
        } catch (\Throwable $exception) {
            Log::warning('No se pudo enviar el resumen presupuestal RP-03.', ['recipient' => $recipient, 'error' => $exception->getMessage()]);

            return 0;
        }
    }
}
