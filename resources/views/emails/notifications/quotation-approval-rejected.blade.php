@extends('emails.layout')

@section('title', 'Cotización rechazada')
@section('heading', 'Cotización rechazada')

@section('content')
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 14px;">
        Hola{{ $name ? ' '.$name : '' }}, la cotización adjudicada fue <strong>rechazada</strong>
        y regresó a evaluación.
    </p>

    @include('emails.partials.details', [
        'detailsTitle' => 'Detalles de la cotización',
        'rows' => [
            'RFQ'         => $rfqFolio,
            'Requisición' => $requisitionFolio,
        ],
    ])

    <x-emails.callout type="danger" title="Motivo">{{ $reason }}</x-emails.callout>

    @include('emails.partials.button', ['url' => $url, 'label' => 'Revisar comparativo'])
@endsection
