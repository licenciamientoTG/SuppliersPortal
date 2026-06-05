<?php

namespace App\Notifications;

use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PurchaseOrderClosedByInactivityNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly PurchaseOrder $po) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('OC Estándar ' . $this->po->folio . ' cerrada por inactividad')
            ->view('emails.notifications.purchase-order-closed-by-inactivity', [
                'name'           => $notifiable->first_name ?? $notifiable->name,
                'folio'          => $this->po->folio,
                'supplier'       => $this->po->supplier->company_name ?? 'N/A',
                'total'          => '$' . number_format($this->po->total, 2) . ' ' . $this->po->currency,
                'createdAt'      => $this->po->created_at->format('d/m/Y H:i'),
                'closedAt'       => $this->po->closed_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i'),
                'inactivityDays' => PurchaseOrder::INACTIVITY_DAYS,
                'url'            => route('purchase-orders.show', $this->po->id),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'po_closed_by_inactivity',
            'po_id' => $this->po->id,
            'po_folio' => $this->po->folio,
            'total' => $this->po->total,
            'closed_at' => $this->po->closed_at?->toDateTimeString(),
            'url' => route('purchase-orders.show', $this->po->id),
            'message' => 'La OC Estándar ' . $this->po->folio . ' fue cerrada automáticamente por inactividad.',
        ];
    }
}
