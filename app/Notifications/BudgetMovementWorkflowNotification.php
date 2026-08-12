<?php

namespace App\Notifications;

use App\Models\BudgetMovement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BudgetMovementWorkflowNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly BudgetMovement $movement, public readonly string $message) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Movimiento presupuestal #'.$this->movement->id)->line($this->message)->action('Ver solicitud', route('budget_movements.show', $this->movement));
    }

    public function toArray(object $notifiable): array
    {
        return ['type' => 'budget_movement_workflow', 'budget_movement_id' => $this->movement->id, 'url' => route('budget_movements.show', $this->movement), 'message' => $this->message];
    }
}
