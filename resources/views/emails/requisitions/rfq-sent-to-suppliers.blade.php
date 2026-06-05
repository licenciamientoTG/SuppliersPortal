@extends('emails.layout')

@section('title', 'Solicitud de cotización enviada')
@section('heading', 'Solicitud de cotización enviada')

@section('content')
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 14px;">
        Hola{{ $name ? ' '.$name : '' }}, se envió una solicitud de cotización a
        <strong>{{ $suppliersCount }} proveedor(es)</strong> para tu requisición
        <strong>{{ $requisitionFolio }}</strong> en el
        <strong>Portal de Proveedores de TotalGas</strong>.
    </p>
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 24px;">
        A continuación encontrará los detalles de la solicitud de cotización (RFQ):
    </p>

    @include('emails.partials.details', [
        'detailsTitle' => 'Detalles de la RFQ',
        'rows' => [
            'Folio RFQ'                  => $rfqFolio,
            'Requisición'                => $requisitionFolio,
            'Grupo de cotización'        => $quotationGroup,
            'Proveedores invitados'      => $suppliersList,
            'Fecha límite de respuesta'  => $responseDeadline,
            'Fecha de envío'             => $sentAt,
        ],
    ])

    @include('emails.partials.button', ['url' => $url, 'label' => 'Ver requisición'])

    <p style="font-size:13px;color:#555555;line-height:1.7;margin:24px 0 0;">
        Le notificaremos cuando los proveedores envíen sus cotizaciones.
    </p>
@endsection
