<?php

namespace App\Notifications;

use App\Models\Requisition;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificación que se envía al solicitante cuando su requisición
 * es enviada a Compras para aprobación y cotización
 */
class RequisitionSubmittedNotification extends Notification
{
    use Queueable;

    public Requisition $requisition;

    /**
     * Constructor
     */
    public function __construct(Requisition $requisition)
    {
        $this->requisition = $requisition;
    }

    /**
     * Canales de notificación
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Construye el mensaje de email
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = route('requisitions.show', $this->requisition->id);

        return (new MailMessage)
            ->subject('Tu Requisición ha sido enviada a Compras - ' . $this->requisition->folio)
            ->view('emails.requisitions.submitted', [
                'name'         => $notifiable->first_name ?? $notifiable->name,
                'folio'        => $this->requisition->folio,
                'costCenter'   => $this->requisition->costCenter?->name,
                'department'   => $this->requisition->department?->name,
                'itemsCount'   => $this->requisition->items()->count(),
                'requiredDate' => $this->requisition->required_date
                    ? $this->requisition->required_date->format('d/m/Y')
                    : null,
                'submittedAt'  => now()->format('d/m/Y H:i'),
                'url'          => $url,
            ]);
    }

    /**
     * Datos para la notificación en base de datos
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'requisition_submitted',
            'requisition_id' => $this->requisition->id,
            'requisition_folio' => $this->requisition->folio,
            'url' => route('requisitions.show', $this->requisition->id),
            'message' => 'Tu requisición ' . $this->requisition->folio . ' ha sido enviada a Compras.',
        ];
    }
}
