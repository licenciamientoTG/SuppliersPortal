@extends('emails.layout')

@section('title', 'Discrepancia entre provisión y factura')
@section('heading', 'Discrepancia entre provisión y factura')

@section('content')
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 14px;">
        La provisión de la recepción <strong>{{ $receptionFolio }}</strong> presenta una diferencia
        contra la factura registrada.
    </p>

    @include('emails.partials.details', [
        'detailsTitle' => 'Comparativo',
        'rows' => [
            'Provisión'  => $provisionAmount,
            'Factura'    => $invoiceAmount,
            'Diferencia' => $differenceAmount,
        ],
    ])

    <x-emails.callout type="danger">
        Se detectó una diferencia entre el monto provisionado y el monto facturado. Revise la conciliación.
    </x-emails.callout>

    @include('emails.partials.button', ['url' => $url, 'label' => 'Revisar discrepancia'])
@endsection
