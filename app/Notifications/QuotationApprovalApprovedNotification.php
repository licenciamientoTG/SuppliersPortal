<?php

namespace App\Notifications;

use App\Models\QuotationSummary;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuotationApprovalApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(public QuotationSummary $summary) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Cotización aprobada - '.($this->summary->rfq?->folio ?? 'RFQ'))
            ->view('emails.notifications.quotation-approval-approved', [
                'name'             => $notifiable->first_name ?? $notifiable->name,
                'rfqFolio'         => $this->summary->rfq?->folio ?? 'N/A',
                'requisitionFolio' => $this->summary->requisition?->folio ?? 'N/A',
                'supplier'         => $this->summary->selectedSupplier?->company_name ?? 'N/A',
                'total'            => '$'.number_format((float) $this->summary->total, 2),
                'url'              => route('purchase-orders.index'),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'quotation_approval_approved',
            'summary_id' => $this->summary->id,
            'rfq_folio' => $this->summary->rfq?->folio,
            'url' => route('purchase-orders.index'),
            'message' => 'La cotización de la RFQ '.($this->summary->rfq?->folio ?? 'N/A').' fue aprobada.',
        ];
    }
}
