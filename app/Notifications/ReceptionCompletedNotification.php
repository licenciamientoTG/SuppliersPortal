<?php

namespace App\Notifications;

use App\Models\Reception;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificación enviada al Comprador (creador de la OC) y al equipo de Compras
 * cuando se registra una recepción (total o parcial) para una OC estándar u OCD.
 */
class ReceptionCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Reception $reception) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $reception = $this->reception;
        $order     = $reception->receivable;
        $isComplete = $reception->isCompleted();

        $statusLabel = $isComplete ? 'COMPLETADA' : 'PARCIAL';

        $url = route('receptions.show', $reception->id);

        return (new MailMessage)
            ->subject("Recepción {$reception->folio} ({$statusLabel}) — {$order->folio}")
            ->view('emails.notifications.reception-completed', [
                'name'              => $notifiable->first_name ?? $notifiable->name,
                'isComplete'        => $isComplete,
                'statusLabel'       => $statusLabel,
                'receptionFolio'    => $reception->folio,
                'orderFolio'        => $order->folio,
                'supplier'          => $order->supplier->company_name ?? 'N/A',
                'deliveryPoint'     => $reception->receivingLocation->name ?? 'N/A',
                'receiver'          => $reception->receiver->name ?? 'N/A',
                'receivedAt'        => $reception->received_at->format('d/m/Y H:i'),
                'orderStatus'       => $order->getStatusLabel(),
                'deliveryReference' => $reception->delivery_reference,
                'url'               => $url,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        $order = $this->reception->receivable;

        return [
            'type'             => 'reception_completed',
            'reception_id'     => $this->reception->id,
            'reception_folio'  => $this->reception->folio,
            'reception_status' => $this->reception->status,
            'order_id'         => $order->id,
            'order_folio'      => $order->folio,
            'url'              => route('receptions.show', $this->reception->id),
            'message'          => "Recepción {$this->reception->folio} ({$this->reception->getStatusLabel()}) registrada para la orden {$order->folio}.",
        ];
    }
}
