@extends('emails.layout')

@section('title', 'Bienvenido al Portal de Proveedores')
@section('heading')¡Hola{{ $name ? ' '.$name : '' }}!@endsection

@section('content')
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 14px;">
        Tu cuenta de acceso al <strong>Portal de Proveedores de TotalGas</strong> ha sido creada.
    </p>
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 24px;">
        A continuación encontrará sus datos de acceso:
    </p>

    @include('emails.partials.details', [
        'detailsTitle' => 'Datos de acceso',
        'rows' => [
            'Usuario'     => $email,
            'Contraseña'  => $password,
        ],
    ])

    <x-emails.callout type="warning">
        Por su seguridad, le recomendamos cambiar esta contraseña la primera vez que inicie sesión.
    </x-emails.callout>

    @include('emails.partials.button', ['url' => $url, 'label' => 'Iniciar sesión'])
@endsection
