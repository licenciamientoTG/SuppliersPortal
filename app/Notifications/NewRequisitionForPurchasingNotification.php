<?php

namespace App\Notifications;

use App\Models\Requisition;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificación que se envía al Departamento de Compras cuando
 * reciben una nueva requisición para cotizar
 */
class NewRequisitionForPurchasingNotification extends Notification
{
    use Queueable;

    public Requisition $requisition;

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
     * Construye el mensaje de email para Compras
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = route('requisitions.show', $this->requisition->id);

        return (new MailMessage)
            ->subject('Nueva Requisición para Cotizar - ' . $this->requisition->folio)
            ->view('emails.notifications.new-requisition-for-purchasing', [
                'name'         => $notifiable->first_name ?? $notifiable->name,
                'folio'        => $this->requisition->folio,
                'requester'    => $this->requisition->requester?->name ?? '—',
                'department'   => $this->requisition->department?->name ?? '—',
                'costCenter'   => $this->requisition->costCenter?->name ?? '—',
                'company'      => $this->requisition->company?->name ?? '—',
                'itemsCount'   => $this->requisition->items()->count(),
                'requiredDate' => $this->requisition->required_date ? $this->requisition->required_date->format('d/m/Y') : 'No especificada',
                'description'  => $this->requisition->description ?: 'Sin descripción',
                'url'          => $url,
            ]);
    }

    /**
     * Datos para la notificación en base de datos
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_requisition_for_purchasing',
            'requisition_id' => $this->requisition->id,
            'requisition_folio' => $this->requisition->folio,
            'requester_name' => $this->requisition->requester?->name ?? '—',
            'url' => route('requisitions.show', $this->requisition->id),
            'message' => 'Nueva requisición ' . $this->requisition->folio . ' recibida de ' . ($this->requisition->requester?->name ?? '—'),
        ];
    }
}
