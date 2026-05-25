<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = $this->resetUrl($notifiable);

        $mail = new MailMessage();
        $mail->subject('Recuperación de contraseña — Portal de Proveedores');
        $mail->view = ['emails.auth.reset-password', ['url' => $url]];

        return $mail;
    }
}
