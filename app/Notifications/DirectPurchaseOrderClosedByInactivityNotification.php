<?php

namespace App\Notifications;

use App\Models\DirectPurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DirectPurchaseOrderClosedByInactivityNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly DirectPurchaseOrder $ocd) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('OC Directa ' . $this->ocd->folio . ' cerrada por inactividad')
            ->view('emails.notifications.direct-purchase-order-closed-by-inactivity', [
                'name'           => $notifiable->first_name ?? $notifiable->name,
                'folio'          => $this->ocd->folio,
                'supplier'       => $this->ocd->supplier->company_name ?? 'N/A',
                'costCenter'     => $this->ocd->costCenter->name ?? 'N/A',
                'total'          => '$' . number_format($this->ocd->total, 2) . ' ' . $this->ocd->currency,
                'requester'      => $this->ocd->creator->name ?? 'N/A',
                'submittedAt'    => $this->ocd->submitted_at?->format('d/m/Y H:i') ?? 'N/A',
                'closedAt'       => $this->ocd->closed_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i'),
                'inactivityDays' => DirectPurchaseOrder::INACTIVITY_DAYS,
                'url'            => route('direct-purchase-orders.show', $this->ocd->id),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ocd_closed_by_inactivity',
            'ocd_id' => $this->ocd->id,
            'ocd_folio' => $this->ocd->folio,
            'total' => $this->ocd->total,
            'closed_at' => $this->ocd->closed_at?->toDateTimeString(),
            'url' => route('direct-purchase-orders.show', $this->ocd->id),
            'message' => 'La OC Directa ' . $this->ocd->folio . ' fue cerrada automáticamente por inactividad.',
        ];
    }
}
