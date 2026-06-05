<?php

namespace App\Notifications;

use App\Models\Requisition;
use App\Models\RequisitionFeedback;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RequisitionFeedbackNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Requisition $requisition,
        public RequisitionFeedback $feedback,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'requisition_feedback',
            'requisition_id' => $this->requisition->id,
            'requisition_folio' => $this->requisition->folio,
            'feedback_id' => $this->feedback->id,
            'buyer_name' => $this->feedback->buyer?->name ?? 'Compras',
            'sent_at' => $this->feedback->sent_at?->toIso8601String(),
            'url' => route('requisitions.show', $this->requisition->id),
            'message' => 'Compras envio retroalimentacion para la requisicion ' . $this->requisition->folio . '.',
        ];
    }
}
