<?php

namespace App\Notifications;

use App\Models\ApprovalDelegation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApprovalDelegationSummaryNotification extends Notification
{
    use Queueable;

    public function __construct(
        private ApprovalDelegation $delegation,
        private array $counts
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $delegator = $this->delegation->delegator?->name ?? 'un autorizador';
        $total = array_sum($this->counts);

        return (new MailMessage)
            ->subject("Delegación de autorizaciones de {$delegator}")
            ->greeting('Hola, '.$notifiable->name)
            ->line("{$delegator} te designó como delegado temporal de autorizaciones.")
            ->line("Pendientes actuales: {$total} (cotizaciones: {$this->counts['quotations']}, directas: {$this->counts['direct_orders']}, convenios: {$this->counts['contract_orders']}).")
            ->line($this->delegation->ends_at
                ? 'La delegación termina el '.$this->delegation->ends_at->format('d/m/Y H:i').'.'
                : 'La delegación permanecerá activa hasta que el titular la desactive.')
            ->action('Abrir mis autorizaciones', route('authorizations.index', ['scope' => 'delegated']));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'approval_delegation_summary',
            'delegation_id' => $this->delegation->id,
            'counts' => $this->counts,
            'message' => ($this->delegation->delegator?->name ?? 'Un autorizador')
                .' te delegó sus autorizaciones de compra.',
            'url' => route('authorizations.index', ['scope' => 'delegated']),
        ];
    }
}
