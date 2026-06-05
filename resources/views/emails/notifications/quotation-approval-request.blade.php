@extends('emails.layout')

@section('title', 'Aprobación de cotización pendiente')
@section('heading'){{ $escalated ? 'Escalación de aprobación' : 'Nueva aprobación de cotización' }}@endsection

@section('content')
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 14px;">
        Hola{{ $name ? ' '.$name : '' }}, tienes una cotización adjudicada pendiente de autorización
        en el <strong>Portal de Proveedores de TotalGas</strong>.
    </p>

    @if ($escalated)
        <x-emails.callout type="warning">
            Esta solicitud fue <strong>escalada</strong> para tu autorización.
        </x-emails.callout>
    @endif

    @include('emails.partials.details', [
        'detailsTitle' => 'Detalles de la cotización',
        'rows' => [
            'RFQ'                  => $rfqFolio,
            'Requisición'          => $requisitionFolio,
            'Proveedor adjudicado' => $supplier,
            'Monto total con IVA'  => $total,
        ],
    ])

    @include('emails.partials.button', ['url' => $url, 'label' => 'Revisar aprobación'])

    <p style="font-size:13px;color:#555555;line-height:1.7;margin:24px 0 0;">
        Gracias por tu revisión.
    </p>
@endsection
