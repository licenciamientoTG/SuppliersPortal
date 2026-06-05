@extends('emails.layout')

@section('title', 'Provisión pendiente de factura')
@section('heading', 'Provisión pendiente de factura')

@section('content')
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 14px;">
        Se generó una provisión financiera derivada de una recepción en el
        <strong>Portal de Proveedores de TotalGas</strong>, pendiente de conciliar contra factura.
    </p>

    @include('emails.partials.details', [
        'detailsTitle' => 'Detalles de la provisión',
        'rows' => [
            'Recepción'          => $receptionFolio,
            'Monto provisionado' => $amount,
        ],
    ])

    @include('emails.partials.button', ['url' => $url, 'label' => 'Ver provisiones'])
@endsection
