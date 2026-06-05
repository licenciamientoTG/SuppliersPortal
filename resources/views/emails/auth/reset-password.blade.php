@extends('emails.layout')

@section('title', 'Recuperación de contraseña')
@section('heading', 'Recuperación de contraseña')

@section('content')
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 14px;">
        Hola, hemos recibido una solicitud para <strong>restablecer la contraseña</strong>
        asociada a tu cuenta en el <strong>Portal de Proveedores de TotalGas</strong>.
    </p>
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 24px;">
        Haz clic en el siguiente botón para continuar. Este enlace es válido por
        <strong>{{ config('auth.passwords.users.expire') }} minutos</strong>.
    </p>

    @include('emails.partials.button', ['url' => $url, 'label' => 'Restablecer contraseña'])

    <x-emails.callout type="warning">
        Si no solicitaste este cambio, puedes ignorar este mensaje. Tu contraseña permanecerá sin cambios.
    </x-emails.callout>
@endsection
