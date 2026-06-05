<?php

namespace App\Notifications;

use App\Models\Rfq;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class RfqCancelledForRequesterNotification extends Notification
{
    use Queueable;

    public Rfq $rfq;
    public ?string $reason;

    public function __construct(Rfq $rfq, ?string $reason = null)
    {
        $this->rfq = $rfq;
        $this->reason = $reason;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $requisitionUrl = Route::has('requisitions.show') ? route('requisitions.show', $this->rfq->requisition_id) : '#';

        return (new MailMessage)
            ->subject('Solicitud de Cotización Cancelada - ' . $this->rfq->folio)
            ->view('emails.notifications.rfq-cancelled-requester', [
                'name'             => $notifiable->first_name ?? $notifiable->name,
                'rfqFolio'         => $this->rfq->folio,
                'requisitionFolio' => $this->rfq->requisition->folio,
                'group'            => $this->rfq->quotationGroup->name,
                'cancelledBy'      => Auth::user()->name ?? 'Sistema',
                'cancelledAt'      => now()->format('d/m/Y H:i'),
                'reason'           => $this->reason,
                'url'              => $requisitionUrl,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'rfq_cancelled_for_requester',
            'rfq_id' => $this->rfq->id,
            'rfq_folio' => $this->rfq->folio,
            'requisition_id' => $this->rfq->requisition_id,
            'requisition_folio' => $this->rfq->requisition->folio,
            'cancelled_by_name' => Auth::user()->name ?? 'Sistema',
            'reason' => $this->reason,
            'url' => Route::has('requisitions.show') ? route('requisitions.show', $this->rfq->requisition_id) : '#',
            'message' => 'RFQ ' . $this->rfq->folio . ' cancelada para tu requisición ' . $this->rfq->requisition->folio,
        ];
    }
}
