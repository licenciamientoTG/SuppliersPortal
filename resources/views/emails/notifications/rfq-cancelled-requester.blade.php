@extends('emails.layout')

@section('title', 'Solicitud de cotización cancelada')
@section('heading', 'Solicitud de cotización cancelada')

@section('content')
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 14px;">
        Hola{{ $name ? ' '.$name : '' }}, la solicitud de cotización <strong>{{ $rfqFolio }}</strong>
        relacionada con tu requisición <strong>{{ $requisitionFolio }}</strong> ha sido cancelada.
    </p>

    @include('emails.partials.details', [
        'detailsTitle' => 'Detalles',
        'rows' => [
            'Folio RFQ'             => $rfqFolio,
            'Requisición'           => $requisitionFolio,
            'Grupo'                 => $group,
            'Cancelada por'         => $cancelledBy,
            'Fecha de cancelación'  => $cancelledAt,
        ],
    ])

    @if (!empty($reason))
        <x-emails.callout type="warning" title="Motivo de la cancelación">{{ $reason }}</x-emails.callout>
    @endif

    @include('emails.partials.button', ['url' => $url, 'label' => 'Ver requisición'])

    <p style="font-size:13px;color:#555555;line-height:1.7;margin:24px 0 0;">
        El departamento de Compras tomará las acciones correspondientes.
    </p>
@endsection
