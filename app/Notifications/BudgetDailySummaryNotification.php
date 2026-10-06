<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/** Resumen diario de RP-03: un solo correo con todos los renglones en riesgo del destinatario. */
class BudgetDailySummaryNotification extends Notification
{
    use Queueable;

    /** Renglones que se listan en el correo; el resto se consulta en el reporte. */
    public const MAX_LINES = 25;

    /** @param  Collection<int, array>  $lines  filas de BudgetAlertsReport::riskLines */
    public function __construct(public readonly Collection $lines) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = $this->lines->count();
        $mail = (new MailMessage)
            ->subject("Resumen presupuestal: {$count} ".($count === 1 ? 'renglón' : 'renglones').' en riesgo')
            ->greeting('Resumen diario de presupuesto')
            ->line(sprintf('Sobregiro o 100 %%: %d · 90 %%: %d · 80 %%: %d',
                $this->lines->where('alert_level', 100)->count(),
                $this->lines->where('alert_level', 90)->count(),
                $this->lines->where('alert_level', 80)->count()));

        foreach ($this->lines->take(self::MAX_LINES) as $line) {
            $mail->line(sprintf('%s · %s %s%s · mes %02d · %s consumido · disponible $%s',
                $line['cost_center_code'],
                $line['budget_line_code'],
                $line['budget_line_name'],
                $line['budget_cedula_name'] ? ' / '.$line['budget_cedula_name'] : '',
                $line['month'],
                $line['progress_pct'] === null ? 'sin vigente' : number_format($line['progress_pct'] * 100, 1).' %',
                number_format($line['available'], 2)));
        }
        if ($count > self::MAX_LINES) {
            $mail->line('… y '.($count - self::MAX_LINES).' renglones más en el reporte.');
        }

        return $mail->action('Ver alertas presupuestales', route('budget-alert-reports.index'));
    }
}
