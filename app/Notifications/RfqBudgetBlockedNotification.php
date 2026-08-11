<?php

namespace App\Notifications;

use App\Models\RfqBudgetBlockedNotice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RfqBudgetBlockedNotification extends Notification
{
    use Queueable;

    public function __construct(public RfqBudgetBlockedNotice $notice) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Presupuesto pendiente de revisar - '.$this->notice->requisition->folio)
            ->view('emails.notifications.rfq-budget-blocked', [
                'notice' => $this->notice,
                'requester' => $notifiable,
                'url' => route('requisitions.show', $this->notice->requisition_id),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'rfq_budget_blocked',
            'rfq_id' => $this->notice->rfq_id,
            'requisition_id' => $this->notice->requisition_id,
            'requisition_folio' => $this->notice->requisition->folio,
            'supplier_name' => $this->notice->supplier->company_name,
            'url' => route('requisitions.show', $this->notice->requisition_id),
            'message' => 'Compras informó que no hay presupuesto disponible para continuar con la requisición '.$this->notice->requisition->folio.'.',
        ];
    }
}
