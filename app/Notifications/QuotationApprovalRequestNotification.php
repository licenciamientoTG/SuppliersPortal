<?php

namespace App\Notifications;

use App\Models\QuotationSummary;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuotationApprovalRequestNotification extends Notification
{
    use Queueable;

    public function __construct(
        public QuotationSummary $summary,
        public bool $escalated = false,
        public ?User $delegatedFor = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('approvals.quotations.index');
        $subjectPrefix = $this->escalated ? 'Escalación de aprobación' : 'Nueva aprobación de cotización';

        return (new MailMessage)
            ->subject($subjectPrefix.' - '.($this->summary->rfq?->folio ?? 'RFQ'))
            ->view('emails.notifications.quotation-approval-request', [
                'name'             => $notifiable->first_name ?? $notifiable->name,
                'escalated'        => $this->escalated,
                'rfqFolio'         => $this->summary->rfq?->folio ?? 'N/A',
                'requisitionFolio' => $this->summary->requisition?->folio ?? 'N/A',
                'supplier'         => $this->summary->selectedSupplier?->company_name ?? 'N/A',
                'total'            => '$'.number_format((float) $this->summary->total, 2),
                'url'              => $url,
                'delegatedFor'     => $this->delegatedFor?->name,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'quotation_approval_request',
            'summary_id' => $this->summary->id,
            'rfq_id' => $this->summary->rfq_id,
            'rfq_folio' => $this->summary->rfq?->folio,
            'requisition_folio' => $this->summary->requisition?->folio,
            'total' => (float) $this->summary->total,
            'escalated' => $this->escalated,
            'delegated_for_user_id' => $this->delegatedFor?->id,
            'url' => route('approvals.quotations.index'),
            'message' => ($this->delegatedFor ? 'Delegada por '.$this->delegatedFor->name.': ' : '')
                .'Cotización pendiente de aprobación para la RFQ '.($this->summary->rfq?->folio ?? 'N/A').'.',
        ];
    }
}
