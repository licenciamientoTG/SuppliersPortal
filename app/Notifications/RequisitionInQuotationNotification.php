<?php

namespace App\Notifications;

use App\Models\Requisition;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class RequisitionInQuotationNotification extends Notification
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
            ->subject('Requisición EN COTIZACIÓN - ' . $this->requisition->folio)
            ->view('emails.requisitions.in-quotation', [
                'name'        => $notifiable->first_name ?? $notifiable->name,
                'folio'       => $this->requisition->folio,
                'costCenter'  => $this->requisition->costCenter?->name,
                'itemsCount'  => $this->requisition->items->count(),
                'validatedAt' => now()->format('d/m/Y H:i'),
                'url'         => $url,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'requisition_in_quotation',
            'requisition_id' => $this->requisition->id,
            'requisition_folio' => $this->requisition->folio,
            'validated_by_name' => Auth::user()->name,
            'url' => route('requisitions.show', $this->requisition->id),
            'message' => 'Tu requisición ' . $this->requisition->folio . ' ha sido validada y se procederá con la cotización.',
        ];
    }
}
