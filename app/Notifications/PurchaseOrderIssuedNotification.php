<?php

namespace App\Notifications;

use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class PurchaseOrderIssuedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public PurchaseOrder $purchaseOrder
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = Route::has('supplier.dashboard') ? route('supplier.dashboard') : route('dashboard');

        return (new MailMessage)
            ->subject('Orden de Compra Emitida - '.$this->purchaseOrder->folio)
            ->view('emails.notifications.purchase-order-issued', [
                'greetingName' => $this->purchaseOrder->supplier->company_name ?? $notifiable->name,
                'folio' => $this->purchaseOrder->folio,
                'total' => '$'.number_format((float) $this->purchaseOrder->total, 2).' '.$this->purchaseOrder->currency,
                'paymentTerms' => $this->purchaseOrder->payment_terms ?? 'N/A',
                'requester' => $this->purchaseOrder->requisition?->requester?->name
                    ?? $this->purchaseOrder->creator?->name
                    ?? 'N/A',
                'url' => $url,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'purchase_order_issued',
            'purchase_order_id' => $this->purchaseOrder->id,
            'purchase_order_folio' => $this->purchaseOrder->folio,
            'total' => $this->purchaseOrder->total,
            'url' => Route::has('supplier.dashboard') ? route('supplier.dashboard') : route('dashboard'),
            'message' => 'Orden de Compra '.$this->purchaseOrder->folio.' ha sido emitida.',
        ];
    }
}
