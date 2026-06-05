@extends('emails.layout')

@section('title', 'Nuevo proveedor registrado')
@section('heading', 'Nuevo proveedor registrado')

@section('content')
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 14px;">
        Hola{{ $name ? ' '.$name : '' }}, se registró un nuevo proveedor en el
        <strong>Portal de Proveedores de TotalGas</strong> y su alta quedó lista
        para revisión por Compras.
    </p>
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 24px;">
        A continuación encontrará los datos del proveedor:
    </p>

    @include('emails.partials.details', [
        'detailsTitle' => 'Datos del proveedor',
        'rows' => [
            'Razón social'      => $companyName ?: '-',
            'RFC'               => $rfc ?: '-',
            'Contacto'          => $contact ?: '-',
            'Correo'            => $email ?: '-',
            'Tipo de proveedor' => $supplierType ?: '-',
        ],
    ])

    @include('emails.partials.button', ['url' => $url, 'label' => 'Revisar proveedor'])

    <p style="font-size:13px;color:#555555;line-height:1.7;margin:24px 0 0;">
        Ingrese al portal para revisar su expediente y continuar con el proceso de alta.
    </p>
@endsection
