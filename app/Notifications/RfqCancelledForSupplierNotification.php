<?php

namespace App\Notifications;

use App\Models\Rfq;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class RfqCancelledForSupplierNotification extends Notification
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
        $portalUrl = Route::has('supplier.dashboard') ? route('supplier.dashboard') : '#';

        return (new MailMessage)
            ->subject('Solicitud de Cotización Cancelada - ' . $this->rfq->folio)
            ->view('emails.notifications.rfq-cancelled-supplier', [
                'rfqFolio'    => $this->rfq->folio,
                'group'       => $this->rfq->quotationGroup->name,
                'cancelledAt' => now()->format('d/m/Y H:i'),
                'reason'      => $this->reason,
                'url'         => $portalUrl,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'rfq_cancelled_for_supplier',
            'rfq_id' => $this->rfq->id,
            'rfq_folio' => $this->rfq->folio,
            'reason' => $this->reason,
            'url' => Route::has('supplier.dashboard') ? route('supplier.dashboard') : '#',
            'message' => 'RFQ ' . $this->rfq->folio . ' ha sido cancelada. No es necesario enviar cotización.',
        ];
    }
}
