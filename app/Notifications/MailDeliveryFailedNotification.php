<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MailDeliveryFailedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $operation,
        private readonly ?string $folio = null,
        private readonly ?int $orderId = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $reference = $this->folio ? " para la orden {$this->folio}" : '';

        return [
            'type' => 'mail_delivery_failed',
            'operation' => $this->operation,
            'order_id' => $this->orderId,
            'folio' => $this->folio,
            'url' => $this->orderId ? route('direct-purchase-orders.show', $this->orderId) : null,
            'message' => "No se pudo enviar el correo {$this->operation}{$reference}. La operación principal se completó; revisa la configuración SMTP.",
        ];
    }
}
