<?php

namespace App\Services;

use App\Models\BudgetMonthlyDistribution;
use App\Models\BudgetThresholdAlert;
use App\Notifications\BudgetThresholdAlertNotification;
use App\Reports\Budget\BudgetAlertsReport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * RP-03 · Aviso por evento: cuando un renglón cruza 80/90/100 % se avisa una sola vez por
 * renglón + umbral + mes. Si cruza varios umbrales a la vez, solo se avisa el más alto.
 */
class BudgetAlertService
{
    public function recordThresholdCrossings(BudgetMonthlyDistribution $distribution): void
    {
        // Mismo % de consumo que RP-01 y RP-03 (consumido / vigente).
        $progress = app(BudgetPositionService::class)->fromDistributions(collect([$distribution]))->sole()['progress_pct'];
        if ($progress === null || (float) $distribution->assigned_amount <= 0) {
            return;
        }
        $usage = round($progress * 100, 2);

        $newAlerts = collect(BudgetAlertsReport::THRESHOLDS)
            ->filter(fn (int $threshold) => $usage >= $threshold)
            ->map(fn (int $threshold) => BudgetThresholdAlert::firstOrCreate(
                ['budget_monthly_distribution_id' => $distribution->id, 'threshold_percent' => $threshold, 'alert_month' => now()->format('Y-m')],
                ['usage_percent' => $usage]
            ))
            ->filter(fn (BudgetThresholdAlert $alert) => $alert->wasRecentlyCreated);

        $alert = $newAlerts->sortByDesc('threshold_percent')->first();
        if (! $alert) {
            return;
        }

        $distribution->loadMissing('annualBudget.costCenter.responsible');
        $responsible = $distribution->annualBudget?->costCenter?->responsible;
        $notification = new BudgetThresholdAlertNotification($distribution, (int) $alert->threshold_percent, $usage);
        try {
            if ($responsible) {
                $responsible->notify($notification);
            }
            if (config('budget_alerts.controller_email')) {
                Notification::route('mail', config('budget_alerts.controller_email'))->notify($notification);
            }
        } catch (\Throwable $exception) {
            Log::warning('No se pudo enviar una alerta presupuestal RP-03.', ['alert_id' => $alert->id, 'error' => $exception->getMessage()]);
        }
        // Los umbrales menores cruzados en el mismo movimiento quedan registrados sin aviso propio.
        BudgetThresholdAlert::query()->whereKey($newAlerts->pluck('id'))->update(['notified_at' => now()]);
    }
}
