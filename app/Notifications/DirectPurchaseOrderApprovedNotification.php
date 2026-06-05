<?php

namespace App\Notifications;

use App\Models\DirectPurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class DirectPurchaseOrderApprovedNotification extends Notification
{
    use Queueable;

    public DirectPurchaseOrder $ocd;

    /**
     * Create a new notification instance.
     */
    public function __construct(DirectPurchaseOrder $ocd)
    {
        $this->ocd = $ocd;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = Route::has('supplier.dashboard') ? route('supplier.dashboard') : route('dashboard');

        return (new MailMessage)
            ->subject('Orden de Compra Aprobada - ' . $this->ocd->folio)
            ->view('emails.notifications.direct-purchase-order-approved', [
                'greetingName' => $this->ocd->supplier->company_name ?? $notifiable->name,
                'folio'        => $this->ocd->folio,
                'total'        => '$' . number_format($this->ocd->total, 2) . ' ' . $this->ocd->currency,
                'paymentTerms' => $this->ocd->payment_terms ?? 'N/A',
                'requester'    => $this->ocd->creator->name ?? 'N/A',
                'url'          => $url,
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'direct_purchase_order_approved',
            'ocd_id' => $this->ocd->id,
            'ocd_folio' => $this->ocd->folio,
            'total' => $this->ocd->total,
            'url' => Route::has('supplier.dashboard') ? route('supplier.dashboard') : route('dashboard'),
            'message' => 'Orden de Compra ' . $this->ocd->folio . ' ha sido aprobada.',
        ];
    }
}
