@extends('emails.layout')

@section('title', 'Nuevo producto o servicio solicitado')
@section('heading')¡Hola{{ $name ? ' '.$name : '' }}!@endsection

@section('content')
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 14px;">
        Se registró una nueva solicitud de alta de producto o servicio en el catálogo del
        <strong>Portal de Proveedores de TotalGas</strong>.
    </p>
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 24px;">
        A continuación encontrará los detalles:
    </p>

    @include('emails.partials.details', [
        'detailsTitle' => 'Detalles de la solicitud',
        'rows' => [
            'Código'          => $code,
            'Descripción'     => $description,
            'Tipo'            => $type,
            'Solicitado por'  => $requestedBy,
            'Centro de costo' => $costCenter,
            'Compañía'        => $company,
        ],
    ])

    @include('emails.partials.button', ['url' => $url, 'label' => 'Revisar solicitud'])
@endsection
