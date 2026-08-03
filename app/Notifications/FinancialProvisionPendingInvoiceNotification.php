<?php

namespace App\Notifications;

use App\Models\FinancialProvision;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FinancialProvisionPendingInvoiceNotification extends Notification
{
    use Queueable;

    public function __construct(public FinancialProvision $provision) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Provisión pendiente de factura')
            ->view('emails.notifications.financial-provision-pending-invoice', [
                'receptionFolio' => $this->provision->reception?->folio,
                'amount' => '$'.number_format((float) $this->provision->provision_amount, 2).' '.$this->provision->currency,
                'url' => route('financial-provisions.index'),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'financial_provision_pending_invoice',
            'financial_provision_id' => $this->provision->id,
            'reception_id' => $this->provision->reception_id,
            'status' => $this->provision->status,
            'amount' => (float) $this->provision->provision_amount,
            'url' => route('financial-provisions.show', $this->provision),
            'message' => 'La provisión de la recepción '.($this->provision->reception?->folio ?? 'N/A').' está pendiente de factura.',
        ];
    }
}
