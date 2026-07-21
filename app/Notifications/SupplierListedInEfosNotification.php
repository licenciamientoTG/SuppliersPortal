<?php

namespace App\Notifications;

use App\Models\Supplier;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupplierListedInEfosNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Supplier $supplier) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Alerta EFOS: proveedor activo identificado')
            ->greeting('Hola '.($notifiable->name ?? '').',')
            ->line('El proveedor activo '.$this->supplier->company_name.' (RFC '.$this->supplier->rfc.') fue identificado en la lista EFOS del SAT.')
            ->action('Revisar proveedor', route('admin.review.suppliers.show', $this->supplier->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'supplier_listed_in_efos',
            'supplier_id' => $this->supplier->id,
            'supplier_name' => $this->supplier->company_name,
            'supplier_rfc' => $this->supplier->rfc,
            'message' => 'El proveedor activo '.$this->supplier->company_name.' fue identificado en la lista EFOS del SAT.',
            'url' => route('admin.review.suppliers.show', $this->supplier->id),
        ];
    }
}
