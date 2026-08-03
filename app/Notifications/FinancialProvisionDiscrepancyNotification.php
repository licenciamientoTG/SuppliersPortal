<?php

namespace App\Notifications;

use App\Models\FinancialProvision;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FinancialProvisionDiscrepancyNotification extends Notification
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
            ->subject('Discrepancia entre provisión y factura')
            ->view('emails.notifications.financial-provision-discrepancy', [
                'receptionFolio' => $this->provision->reception?->folio,
                'provisionAmount' => '$'.number_format((float) $this->provision->provision_amount, 2),
                'invoiceAmount' => '$'.number_format((float) $this->provision->invoice_amount, 2),
                'differenceAmount' => '$'.number_format((float) $this->provision->difference_amount, 2),
                'url' => route('financial-provisions.show', $this->provision),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'financial_provision_discrepancy',
            'financial_provision_id' => $this->provision->id,
            'supplier_invoice_id' => $this->provision->supplier_invoice_id,
            'difference_amount' => (float) $this->provision->difference_amount,
            'url' => route('financial-provisions.show', $this->provision),
            'message' => 'La provisión '.($this->provision->reception?->folio ?? 'N/A').' tiene una diferencia con la factura.',
        ];
    }
}
