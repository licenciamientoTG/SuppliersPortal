@extends('emails.layout')

@section('title', 'Cotización aprobada')
@section('heading', 'Cotización aprobada')

@section('content')
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 14px;">
        Hola{{ $name ? ' '.$name : '' }}, la cotización adjudicada fue <strong>aprobada</strong>
        y ya puede continuar su ciclo operativo.
    </p>

    @include('emails.partials.details', [
        'detailsTitle' => 'Detalles de la cotización',
        'rows' => [
            'RFQ'                  => $rfqFolio,
            'Requisición'          => $requisitionFolio,
            'Proveedor adjudicado' => $supplier,
            'Monto total con IVA'  => $total,
        ],
    ])

    <x-emails.callout type="success">
        La cotización fue aprobada correctamente.
    </x-emails.callout>

    @include('emails.partials.button', ['url' => $url, 'label' => 'Ver órdenes de compra'])
@endsection
