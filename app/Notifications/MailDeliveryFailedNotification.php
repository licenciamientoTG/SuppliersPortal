<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MailDeliveryFailedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $operation,
        private readonly ?string $reference = null,
        private readonly ?string $url = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $reference = $this->reference ? " ({$this->reference})" : '';

        return [
            'type' => 'mail_delivery_failed',
            'operation' => $this->operation,
            'reference' => $this->reference,
            'url' => $this->url,
            'message' => "No se pudo enviar el correo {$this->operation}{$reference}. La operación principal se completó; revisa la configuración SMTP.",
        ];
    }
}
