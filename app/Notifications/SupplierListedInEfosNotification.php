<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupplierListedInEfosNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<int, array{id: int, name: string, rfc: string}>  $suppliers
     */
    public function __construct(public readonly array $suppliers) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Alerta EFOS: proveedores activos identificados')
            ->greeting('Hola '.($notifiable->name ?? '').',')
            ->line($this->message())
            ->line($this->supplierList())
            ->action('Revisar lista EFOS', route('sat_efos_69b.index'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'suppliers_listed_in_efos',
            'suppliers' => $this->suppliers,
            'message' => $this->message(),
            'url' => route('sat_efos_69b.index'),
        ];
    }

    private function message(): string
    {
        $count = count($this->suppliers);

        return "Se identificaron {$count} proveedor(es) activo(s) en la lista EFOS del SAT.";
    }

    private function supplierList(): string
    {
        return collect($this->suppliers)
            ->map(fn (array $supplier) => "{$supplier['name']} (RFC {$supplier['rfc']})")
            ->implode("\n");
    }
}
