<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupplierAccountReviewedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly bool $approved,
        private readonly ?string $notes = null,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->approved ? 'Alta de proveedor aprobada' : 'Alta de proveedor rechazada')
            ->greeting('Hola, '.$notifiable->name)
            ->line(
                $this->approved
                    ? 'Compras aprobó tu alta. Ya puedes ingresar a los módulos habilitados del Portal de Proveedores.'
                    : 'Compras rechazó tu alta como proveedor.'
            );

        if ($this->notes) {
            $mail->line('Observaciones: '.$this->notes);
        }

        return $mail->action(
            $this->approved ? 'Ir al portal' : 'Ver mi expediente',
            $this->approved ? route('supplier.dashboard') : route('supplier.documents.index'),
        );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->approved ? 'supplier_account_approved' : 'supplier_account_rejected',
            'url' => $this->approved ? route('supplier.dashboard') : route('supplier.documents.index'),
            'message' => $this->approved
                ? 'Compras aprobó tu alta como proveedor.'
                : 'Compras rechazó tu alta como proveedor.'.($this->notes ? ' Observaciones: '.$this->notes : ''),
        ];
    }
}
