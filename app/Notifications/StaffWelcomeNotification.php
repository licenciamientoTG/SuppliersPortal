<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffWelcomeNotification extends Notification
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
            ->view('emails.notifications.staff-welcome', [
                'name'     => $notifiable->first_name ?? $notifiable->name,
                'email'    => $notifiable->email,
                'password' => $this->plainPassword,
                'url'      => route('login'),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'staff_welcome',
            'url' => route('dashboard'),
            'message' => 'Tu cuenta del portal fue creada y ya puedes ingresar con las credenciales enviadas por correo.',
        ];
    }
}
