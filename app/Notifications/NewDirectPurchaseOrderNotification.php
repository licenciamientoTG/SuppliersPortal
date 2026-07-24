<?php

namespace App\Notifications;

use App\Models\DirectPurchaseOrder;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewDirectPurchaseOrderNotification extends Notification
{
    use Queueable;

    public DirectPurchaseOrder $ocd;

    /**
     * Create a new notification instance.
     */
    public function __construct(DirectPurchaseOrder $ocd, public ?User $delegatedFor = null)
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
        $url = route('direct-purchase-orders.show', $this->ocd->id);

        return (new MailMessage)
            ->subject('Nueva OC Directa para Revisión - ' . $this->ocd->folio)
            ->view('emails.notifications.new-direct-purchase-order', [
                'name'           => $notifiable->first_name ?? $notifiable->name,
                'folio'          => $this->ocd->folio,
                'supplier'       => $this->ocd->supplier->company_name ?? 'N/A',
                'costCenter'     => $this->ocd->costCenter->name ?? 'N/A',
                'total'          => '$' . number_format($this->ocd->total, 2) . ' ' . $this->ocd->currency,
                'authorizerRole' => $this->ocd->authorizerRole->name ?? 'Sin rol asignado',
                'limit'          => $this->ocd->effective_authorization_limit !== null
                    ? '$' . number_format((float) $this->ocd->effective_authorization_limit, 2) . ' MXN'
                    : 'Sin límite',
                'requester'      => $this->ocd->creator->name ?? 'N/A',
                'justification'  => $this->ocd->justification ?: 'Sin justificación',
                'url'            => $url,
                'delegatedFor'   => $this->delegatedFor?->name,
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
            'type' => 'new_direct_purchase_order',
            'ocd_id' => $this->ocd->id,
            'ocd_folio' => $this->ocd->folio,
            'total' => $this->ocd->total,
            'delegated_for_user_id' => $this->delegatedFor?->id,
            'url' => route('direct-purchase-orders.show', $this->ocd->id),
            'message' => ($this->delegatedFor ? 'Delegada por '.$this->delegatedFor->name.': ' : '')
                .'Nueva OC Directa '.$this->ocd->folio.' pendiente de aprobación.',
        ];
    }
}
