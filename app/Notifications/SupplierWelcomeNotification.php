<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupplierWelcomeNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $plainPassword) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Bienvenido al Portal de Proveedores TotalGas')
            ->view('emails.suppliers.welcome', [
                'name'     => $notifiable->first_name ?: $notifiable->name,
                'email'    => $notifiable->email,
                'password' => $this->plainPassword,
                'url'      => route('login'),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'supplier_welcome',
            'url'     => route('dashboard'),
            'message' => 'Tu cuenta de proveedor fue creada. Revisa tu correo con los datos de acceso.',
        ];
    }
}
