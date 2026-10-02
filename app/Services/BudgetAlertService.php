<?php

namespace App\Services;

use App\Models\BudgetMonthlyDistribution;
use App\Models\BudgetThresholdAlert;
use App\Notifications\BudgetThresholdAlertNotification;

class BudgetAlertService
{
    public function recordThresholdCrossings(BudgetMonthlyDistribution $distribution): void
    {
        $assigned = (float) $distribution->assigned_amount;
        if ($assigned <= 0) {
            return;
        }
        $usage = round(((float) $distribution->consumed_amount + (float) $distribution->committed_amount) / $assigned * 100, 2);
        foreach ([80, 90, 100] as $threshold) {
            if ($usage < $threshold) {
                continue;
            }
            $alert = BudgetThresholdAlert::firstOrCreate(
                ['budget_monthly_distribution_id' => $distribution->id, 'threshold_percent' => $threshold, 'alert_month' => now()->format('Y-m')],
                ['usage_percent' => $usage]
            );
            if ($alert->wasRecentlyCreated) {
                $distribution->loadMissing('annualBudget.costCenter.responsible');
                $responsible = $distribution->annualBudget?->costCenter?->responsible;
                $notification = new BudgetThresholdAlertNotification($distribution, $threshold, $usage);
                try {
                    if ($responsible) {
                        $responsible->notify($notification);
                    }
                    if (config('budget_alerts.controller_email')) {
                        \Illuminate\Support\Facades\Notification::route('mail', config('budget_alerts.controller_email'))->notify($notification);
                    }
                } catch (\Throwable $exception) {
                    \Illuminate\Support\Facades\Log::warning('No se pudo enviar una alerta presupuestal RP-03.', ['alert_id' => $alert->id, 'error' => $exception->getMessage()]);
                }
                $alert->update(['notified_at' => now()]);
            }
        }
    }
}
