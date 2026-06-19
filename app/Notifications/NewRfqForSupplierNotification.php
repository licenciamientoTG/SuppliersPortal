<?php

namespace App\Notifications;

use App\Models\Rfq;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewRfqForSupplierNotification extends Notification
{
    use Queueable;

    public Rfq $rfq;

    public function __construct(Rfq $rfq)
    {
        $this->rfq = $rfq;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $portalUrl = route('supplier.rfq.show', $this->rfq->id);
        $daysUntilDeadline = now()->diffInDays($this->rfq->response_deadline, false);
        $itemsCount = $this->rfq->quotationGroup->items->count();

        return (new MailMessage)
            ->subject('Nueva Solicitud de Cotización - ' . $this->rfq->folio)
            ->view('emails.notifications.new-rfq-for-supplier', [
                'folio'          => $this->rfq->folio,
                'group'          => $this->rfq->quotationGroup->name,
                'itemsCount'     => $itemsCount,
                'deadline'       => $this->rfq->response_deadline->format('d/m/Y'),
                'daysLeft'       => number_format(floor(abs($daysUntilDeadline) * 10) / 10, 1),
                'supplierMessage'=> $this->rfq->message,
                'url'            => $portalUrl,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_rfq',
            'rfq_id' => $this->rfq->id,
            'rfq_folio' => $this->rfq->folio,
            'requisition_folio' => $this->rfq->requisition->folio,
            'items_count' => $this->rfq->quotationGroup->items->count(),
            'response_deadline' => $this->rfq->response_deadline->toDateTimeString(),
            'url' => route('supplier.rfq.show', $this->rfq->id),
            'message' => 'Nueva solicitud de cotización ' . $this->rfq->folio . ' disponible. Responde antes del ' . $this->rfq->response_deadline->format('d/m/Y'),
        ];
    }
}
