<?php

namespace App\Notifications;

use App\Models\Rfq;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class RfqSentToSuppliersNotification extends Notification
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
        $requisitionUrl = route('requisitions.show', $this->rfq->requisition_id);
        $suppliersCount = $this->rfq->suppliers->count();
        $suppliersList = $this->rfq->suppliers->pluck('name')->join(', ');

        return (new MailMessage)
            ->subject('Solicitud de Cotización Enviada - ' . $this->rfq->folio)
            ->view('emails.requisitions.rfq-sent-to-suppliers', [
                'name'             => $notifiable->first_name ?? $notifiable->name,
                'rfqFolio'         => $this->rfq->folio,
                'requisitionFolio' => $this->rfq->requisition->folio,
                'quotationGroup'   => $this->rfq->quotationGroup?->name,
                'suppliersCount'   => $suppliersCount,
                'suppliersList'    => $suppliersList,
                'responseDeadline' => $this->rfq->response_deadline?->format('d/m/Y'),
                'sentAt'           => $this->rfq->sent_at?->format('d/m/Y H:i'),
                'url'              => $requisitionUrl,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'rfq_sent_to_suppliers',
            'rfq_id' => $this->rfq->id,
            'rfq_folio' => $this->rfq->folio,
            'requisition_id' => $this->rfq->requisition_id,
            'requisition_folio' => $this->rfq->requisition->folio,
            'suppliers_count' => $this->rfq->suppliers->count(),
            'sent_by_name' => Auth::user()->name,
            'url' => route('requisitions.show', $this->rfq->requisition_id),
            'message' => 'RFQ ' . $this->rfq->folio . ' enviada a ' . $this->rfq->suppliers->count() . ' proveedor(es) para tu requisición ' . $this->rfq->requisition->folio,
        ];
    }
}
