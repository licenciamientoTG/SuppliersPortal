<?php

namespace App\Notifications;

use App\Models\DirectPurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DirectPurchaseOrderReturnedNotification extends Notification
{
    use Queueable;

    public DirectPurchaseOrder $ocd;
    public string $instructions;

    public function __construct(DirectPurchaseOrder $ocd, string $instructions)
    {
        $this->ocd          = $ocd;
        $this->instructions = $instructions;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('direct-purchase-orders.show', $this->ocd->id);

        return (new MailMessage)
            ->subject('Orden de Compra Devuelta para Corrección - ' . $this->ocd->folio)
            ->view('emails.notifications.direct-purchase-order-returned', [
                'name'         => $notifiable->first_name ?? $notifiable->name,
                'folio'        => $this->ocd->folio,
                'total'        => '$' . number_format($this->ocd->total, 2) . ' ' . $this->ocd->currency,
                'supplier'     => $this->ocd->supplier->company_name ?? 'N/A',
                'instructions' => $this->instructions,
                'url'          => $url,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'direct_purchase_order_returned',
            'ocd_id'       => $this->ocd->id,
            'ocd_folio'    => $this->ocd->folio,
            'total'        => $this->ocd->total,
            'instructions' => $this->instructions,
            'url'          => route('direct-purchase-orders.show', $this->ocd->id),
            'message'      => 'OC ' . $this->ocd->folio . ' devuelta para corrección.',
        ];
    }
}
