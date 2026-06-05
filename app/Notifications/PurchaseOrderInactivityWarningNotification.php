<?php

namespace App\Notifications;

use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PurchaseOrderInactivityWarningNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly PurchaseOrder $po) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $deadline = $this->po->getAutoCloseDeadline();
        $url = route('purchase-orders.show', $this->po->id);

        return (new MailMessage)
            ->subject('ALERTA: OC Estándar ' . $this->po->folio . ' se cerrará en 3 días')
            ->view('emails.notifications.purchase-order-inactivity-warning', [
                'name'      => $notifiable->first_name ?? $notifiable->name,
                'folio'     => $this->po->folio,
                'supplier'  => $this->po->supplier->company_name ?? 'N/A',
                'total'     => '$' . number_format($this->po->total, 2) . ' ' . $this->po->currency,
                'createdAt' => $this->po->created_at->format('d/m/Y H:i'),
                'deadline'  => $deadline->format('d/m/Y'),
                'url'       => $url,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'po_inactivity_warning',
            'po_id' => $this->po->id,
            'po_folio' => $this->po->folio,
            'total' => $this->po->total,
            'deadline' => $this->po->getAutoCloseDeadline()->toDateString(),
            'url' => route('purchase-orders.show', $this->po->id),
            'message' => 'ALERTA: La OC Estándar ' . $this->po->folio . ' será cerrada automáticamente en 3 días si no es aprobada.',
        ];
    }
}
