<?php

namespace App\Notifications;

use App\Models\SupplierDocumentRequirement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupplierDocumentRenewalNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly SupplierDocumentRequirement $requirement, private readonly int $milestoneDays) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $type = $this->requirement->documentType;
        $expired = $this->milestoneDays <= 0;

        return (new MailMessage)
            ->subject($expired ? 'Acceso limitado por documento vencido' : 'Renovacion documental pendiente')
            ->greeting('Hola, '.$notifiable->name)
            ->line($expired ? "El documento {$type->name} vencio y tu acceso a los modulos fue limitado." : "El documento {$type->name} vence en {$this->milestoneDays} dia(s).")
            ->action('Ir a mis documentos', route('supplier.documents.index'))
            ->line('Carga una nueva version; el acceso se restablecera cuando sea aprobada.');
    }

    public function toArray(object $notifiable): array
    {
        $expired = $this->milestoneDays <= 0;

        return ['type' => 'supplier_document_renewal', 'requirement_id' => $this->requirement->id, 'document_type' => $this->requirement->documentType->name, 'milestone_days' => $this->milestoneDays, 'url' => route('supplier.documents.index'), 'message' => $expired ? 'Tu acceso fue limitado por un documento vencido.' : "Tu documento vence en {$this->milestoneDays} dia(s)."];
    }
}
