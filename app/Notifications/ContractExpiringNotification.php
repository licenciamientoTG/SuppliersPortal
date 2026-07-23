<?php

namespace App\Notifications;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContractExpiringNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Contract $contract, private readonly int $milestoneDays) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $c = $this->contract;
        $remaining = $this->milestoneDays === 1
            ? 'vence mañana'
            : "vence en {$this->milestoneDays} días";

        return (new MailMessage)
            ->subject("Contrato por vencer: {$c->folio}")
            ->greeting('Hola, ' . $notifiable->name)
            ->line("El contrato {$c->folio} con el proveedor " . ($c->supplier->company_name ?? '—')
                . ' (' . ($c->company->name ?? '—') . ") {$remaining}.")
            ->line('Fecha de vencimiento: ' . $c->end_date->format('d/m/Y') . '.')
            ->action('Ver contrato', route('contracts.show', $c))
            ->line('Renueva o renegocia el contrato antes de su vencimiento para no perder los precios pactados.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'           => 'contract_expiring',
            'contract_id'    => $this->contract->id,
            'folio'          => $this->contract->folio,
            'milestone_days' => $this->milestoneDays,
            'url'            => route('contracts.show', $this->contract),
            'message'        => $this->milestoneDays === 1
                ? "El contrato {$this->contract->folio} vence mañana."
                : "El contrato {$this->contract->folio} vence en {$this->milestoneDays} días.",
        ];
    }
}
