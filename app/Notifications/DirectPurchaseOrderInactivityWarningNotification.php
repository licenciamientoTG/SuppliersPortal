<?php

namespace App\Notifications;

use App\Models\DirectPurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DirectPurchaseOrderInactivityWarningNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly DirectPurchaseOrder $ocd) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $deadline = $this->ocd->getAutoCloseDeadline();
        $url = route('direct-purchase-orders.show', $this->ocd->id);

        return (new MailMessage)
            ->subject('ALERTA: OC Directa ' . $this->ocd->folio . ' se cerrará en 3 días')
            ->view('emails.notifications.direct-purchase-order-inactivity-warning', [
                'name'        => $notifiable->first_name ?? $notifiable->name,
                'folio'       => $this->ocd->folio,
                'supplier'    => $this->ocd->supplier->company_name ?? 'N/A',
                'costCenter'  => $this->ocd->costCenter->name ?? 'N/A',
                'total'       => '$' . number_format($this->ocd->total, 2) . ' ' . $this->ocd->currency,
                'requester'   => $this->ocd->creator->name ?? 'N/A',
                'submittedAt' => $this->ocd->submitted_at?->format('d/m/Y H:i') ?? 'N/A',
                'deadline'    => $deadline?->format('d/m/Y') ?? 'N/A',
                'url'         => $url,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ocd_inactivity_warning',
            'ocd_id' => $this->ocd->id,
            'ocd_folio' => $this->ocd->folio,
            'total' => $this->ocd->total,
            'deadline' => $this->ocd->getAutoCloseDeadline()?->toDateString(),
            'url' => route('direct-purchase-orders.show', $this->ocd->id),
            'message' => 'ALERTA: La OC Directa ' . $this->ocd->folio . ' será cerrada automáticamente en 3 días si no es aprobada.',
        ];
    }
}
