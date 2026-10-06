<?php

namespace App\Notifications;

use App\Models\BudgetMonthlyDistribution;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BudgetThresholdAlertNotification extends Notification
{
    use Queueable;

    public function __construct(private BudgetMonthlyDistribution $distribution, private int $threshold, private float $usage) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->distribution->loadMissing('annualBudget.costCenter.company', 'expenseCategory', 'budgetCedula');
        $center = $this->distribution->annualBudget?->costCenter;

        return (new MailMessage)->subject("Alerta presupuestal: {$this->threshold}% de consumo")
            ->greeting('Alerta presupuestal')
            ->line("{$center?->company?->name} · {$center?->name} · {$this->distribution->expenseCategory?->name} · {$this->distribution->budgetCedula?->name}")
            ->line('Periodo: '.$this->distribution->annualBudget?->fiscal_year.'-'.$this->distribution->month)
            ->line('Consumo y compromiso: '.number_format($this->usage, 2).'%')
            ->line('Disponible: $'.number_format($this->distribution->getBalanceAmount(), 2))
            ->action('Ver alertas presupuestales', route('budget-alert-reports.index'));
    }
}
