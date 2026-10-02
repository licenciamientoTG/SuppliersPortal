<?php

namespace App\Console\Commands;

use App\Models\BudgetMonthlyDistribution;
use App\Notifications\BudgetThresholdAlertNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendBudgetDailyAlerts extends Command
{
    protected $signature = 'budget:send-daily-alerts';

    protected $description = 'Envía el resumen diario de renglones presupuestales en riesgo';

    public function handle(): int
    {
        $lines = BudgetMonthlyDistribution::query()->with(['annualBudget.costCenter.responsible'])
            ->whereHas('annualBudget', fn ($q) => $q->where('status', 'APROBADO'))
            ->get()->filter(fn ($line) => (float) $line->assigned_amount > 0 && ((float) $line->consumed_amount + (float) $line->committed_amount) / (float) $line->assigned_amount >= .8);
        foreach ($lines as $line) {
            $usage = ((float) $line->consumed_amount + (float) $line->committed_amount) / (float) $line->assigned_amount * 100;
            $threshold = $usage >= 100 ? 100 : ($usage >= 90 ? 90 : 80);
            $notification = new BudgetThresholdAlertNotification($line, $threshold, $usage);
            if ($line->annualBudget?->costCenter?->responsible) {
                $line->annualBudget->costCenter->responsible->notify($notification);
            }
            if (config('budget_alerts.controller_email')) {
                Notification::route('mail', config('budget_alerts.controller_email'))->notify($notification);
            }
        }
        $this->info("Resumen enviado para {$lines->count()} renglones en riesgo.");

        return self::SUCCESS;
    }
}
