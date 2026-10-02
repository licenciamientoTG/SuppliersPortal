<?php

namespace App\Notifications;

use App\Models\Rfq;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RfqExpiredNotification extends Notification
{
    use Queueable;

    public function __construct(public Rfq $rfq) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('RFQ vencida - '.$this->rfq->folio)
            ->view('emails.notifications.rfq-expired', [
                'name' => $notifiable->first_name ?? $notifiable->name,
                'rfq' => $this->rfq,
                'requisition' => $this->rfq->requisition,
                'url' => route('rfq.show', $this->rfq),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'rfq_expired',
            'rfq_id' => $this->rfq->id,
            'rfq_folio' => $this->rfq->folio,
            'requisition_id' => $this->rfq->requisition_id,
            'requisition_folio' => $this->rfq->requisition?->folio,
            'response_deadline' => $this->rfq->response_deadline?->toDateTimeString(),
            'url' => route('rfq.show', $this->rfq),
            'message' => 'La RFQ '.$this->rfq->folio.' venció y requiere seguimiento.',
        ];
    }
}
