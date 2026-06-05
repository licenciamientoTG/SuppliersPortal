@extends('emails.layout')

@section('title', 'Requisición en cotización')
@section('heading')¡Buenas noticias{{ $name ? ', '.$name : '' }}!@endsection

@section('content')
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 14px;">
        Tu requisición con folio <strong>{{ $folio }}</strong> fue
        <strong>validada</strong> por el departamento de Compras del
        <strong>Portal de Proveedores de TotalGas</strong>.
    </p>
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 24px;">
        El departamento de Compras procederá a solicitar cotizaciones a los proveedores
        para los productos y servicios solicitados.
    </p>

    @include('emails.partials.details', [
        'detailsTitle' => 'Detalles de la requisición',
        'rows' => [
            'Folio'                => $folio,
            'Centro de costo'      => $costCenter,
            'Partidas'             => $itemsCount.' producto(s)/servicio(s)',
            'Fecha de validación'  => $validatedAt,
        ],
    ])

    <x-emails.callout type="info">
        <strong>Estado actual:</strong> En Cotización
    </x-emails.callout>

    @include('emails.partials.button', ['url' => $url, 'label' => 'Ver requisición'])

    <p style="font-size:13px;color:#555555;line-height:1.7;margin:24px 0 0;">
        Le notificaremos cuando se reciban las cotizaciones y se proceda con la compra.
    </p>
@endsection
