<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupplierDocumentFileCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly bool $supplierApproved)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->supplierApproved
            ? 'Tu expediente documental está completo y tu cuenta se encuentra activa.'
            : 'Tu expediente documental está completo. Compras realizará la aprobación final de tu alta.';

        return (new MailMessage)
            ->subject('Expediente documental completo')
            ->greeting('Hola, '.$notifiable->name)
            ->line($message)
            ->action('Ver mi expediente', route('supplier.documents.index'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'supplier_document_file_completed',
            'url' => route('supplier.documents.index'),
            'message' => $this->supplierApproved
                ? 'Tu expediente documental está completo y tu cuenta está activa.'
                : 'Tu expediente documental está completo; falta la aprobación final de Compras.',
        ];
    }
}
