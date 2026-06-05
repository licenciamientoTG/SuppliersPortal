<?php

namespace App\Mail;

use App\Models\Requisition;
use App\Models\RequisitionFeedback;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RequisitionFeedbackMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Requisition $requisition,
        public RequisitionFeedback $feedback,
        public User $buyer,
        public string $url,
    ) {}

    public function build(): static
    {
        return $this
            ->subject('Retroalimentacion de Compras - ' . $this->requisition->folio)
            ->view('emails.notifications.requisition-feedback');
    }
}
