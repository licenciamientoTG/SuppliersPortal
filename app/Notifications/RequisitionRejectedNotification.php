<?php

namespace App\Notifications;

use App\Models\Requisition;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class RequisitionRejectedNotification extends Notification
{
    use Queueable;

    public Requisition $requisition;

    public function __construct(Requisition $requisition)
    {
        $this->requisition = $requisition;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('requisitions.show', $this->requisition->id);

        return (new MailMessage)
            ->subject('Requisición RECHAZADA - ' . $this->requisition->folio)
            ->view('emails.notifications.requisition-rejected', [
                'name'       => $notifiable->first_name ?? $notifiable->name,
                'folio'      => $this->requisition->folio,
                'reason'     => $this->requisition->rejection_reason,
                'department' => $this->requisition->department->name,
                'costCenter' => $this->requisition->costCenter->name,
                'rejectedAt' => $this->requisition->rejected_at->format('d/m/Y H:i'),
                'url'        => $url,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'requisition_rejected',
            'requisition_id' => $this->requisition->id,
            'requisition_folio' => $this->requisition->folio,
            'rejected_by_name' => Auth::user()->name,
            'url' => route('requisitions.show', $this->requisition->id),
            'message' => 'Tu requisición ' . $this->requisition->folio . ' fue rechazada: ' . $this->requisition->rejection_reason,
        ];
    }
}
