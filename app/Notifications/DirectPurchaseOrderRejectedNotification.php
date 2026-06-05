<?php

namespace App\Notifications;

use App\Models\DirectPurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DirectPurchaseOrderRejectedNotification extends Notification
{
    use Queueable;

    public DirectPurchaseOrder $ocd;
    public string $rejectionReason;

    public function __construct(DirectPurchaseOrder $ocd, string $rejectionReason)
    {
        $this->ocd = $ocd;
        $this->rejectionReason = $rejectionReason;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('direct-purchase-orders.show', $this->ocd->id);

        return (new MailMessage)
            ->subject('Orden de Compra Rechazada - ' . $this->ocd->folio)
            ->view('emails.notifications.direct-purchase-order-rejected', [
                'name'       => $notifiable->first_name ?? $notifiable->name,
                'folio'      => $this->ocd->folio,
                'total'      => '$' . number_format($this->ocd->total, 2) . ' ' . $this->ocd->currency,
                'supplier'   => $this->ocd->supplier->company_name ?? 'N/A',
                'costCenter' => $this->ocd->costCenter->name ?? 'N/A',
                'requester'  => $this->ocd->creator->name ?? 'N/A',
                'reason'     => $this->rejectionReason,
                'url'        => $url,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'             => 'direct_purchase_order_rejected',
            'ocd_id'           => $this->ocd->id,
            'ocd_folio'        => $this->ocd->folio,
            'total'            => $this->ocd->total,
            'rejection_reason' => $this->rejectionReason,
            'url'              => route('direct-purchase-orders.show', $this->ocd->id),
            'message'          => 'Orden de Compra ' . $this->ocd->folio . ' ha sido rechazada.',
        ];
    }
}
