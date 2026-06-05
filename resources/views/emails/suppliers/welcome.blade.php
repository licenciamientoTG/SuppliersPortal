@extends('emails.layout')

@section('title', 'Bienvenido al Portal de Proveedores')
@section('heading')¡Le damos la bienvenida{{ $name ? ', '.$name : '' }}!@endsection

@section('content')
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 14px;">
        Nos complace informarle que su registro como proveedor en el
        <strong>Portal de Proveedores de TotalGas</strong> se completó correctamente.
        Es un gusto contar con usted como parte de nuestra cadena de suministro.
    </p>
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 24px;">
        A continuación encontrará sus datos de acceso para ingresar al portal:
    </p>

    @include('emails.partials.details', [
        'detailsTitle' => 'Datos de acceso',
        'rows' => [
            'Usuario'    => $email,
            'Contraseña' => $password,
        ],
    ])

    <x-emails.callout type="warning">
        Por su seguridad, le recomendamos cambiar esta contraseña la primera vez que inicie sesión.
    </x-emails.callout>

    @include('emails.partials.button', ['url' => $url, 'label' => 'Ingresar al portal'])

    <p style="font-size:13px;color:#555555;line-height:1.7;margin:24px 0 0;">
        Como siguiente paso, le invitamos a cargar su documentación en la sección
        <strong>Documentación</strong> del menú lateral para completar su alta.
    </p>
    <p style="font-size:12px;color:#999999;margin:18px 0 0;text-align:center;line-height:1.6;">
        ¿Tiene dudas? Escríbanos a
        <a href="mailto:soporte@totalgas.com" style="color:#1a4b96;text-decoration:none;">soporte@totalgas.com</a>
    </p>
@endsection
