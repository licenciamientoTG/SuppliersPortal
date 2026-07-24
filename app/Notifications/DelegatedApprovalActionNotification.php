<?php

namespace App\Notifications;

use App\Models\ApprovalDecision;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DelegatedApprovalActionNotification extends Notification
{
    use Queueable;

    public function __construct(
        private ApprovalDecision $decision,
        private Model $approvable
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $actor = $this->decision->actor()->first()?->name ?? 'Tu delegado';
        $folio = $this->approvable->folio
            ?? $this->approvable->rfq?->folio
            ?? '#'.$this->approvable->getKey();

        return (new MailMessage)
            ->subject("Autorización {$this->decision->action} por tu delegado")
            ->greeting('Hola, '.$notifiable->name)
            ->line("{$actor} resolvió en tu representación el pendiente {$folio}.")
            ->line('Acción: '.$this->decision->action.'.')
            ->action('Ver mis autorizaciones', route('authorizations.index'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'delegated_approval_action',
            'decision_id' => $this->decision->id,
            'message' => ($this->decision->actor()->first()?->name ?? 'Un delegado')
                .' resolvió una autorización en tu representación.',
            'url' => route('authorizations.index'),
        ];
    }
}
