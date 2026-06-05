@extends('emails.layout')

@section('title', 'Alerta de inactividad — OC Directa')
@section('heading')¡Hola{{ $name ? ' '.$name : '' }}!@endsection

@section('content')
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 14px;">
        La siguiente Orden de Compra Directa será <strong>cerrada automáticamente por inactividad
        en 3 días</strong> si no es aprobada.
    </p>

    @include('emails.partials.details', [
        'detailsTitle' => 'Detalles de la OCD',
        'rows' => [
            'Folio'                       => $folio,
            'Proveedor'                   => $supplier,
            'Centro de costo'             => $costCenter,
            'Monto total'                 => $total,
            'Solicitante'                 => $requester,
            'Enviada a aprobación'        => $submittedAt,
            'Fecha límite de aprobación'  => $deadline,
        ],
    ])

    <x-emails.callout type="warning">
        Si la OCD no es aprobada antes del <strong>{{ $deadline }}</strong>, será cerrada
        automáticamente y el presupuesto no será comprometido.
    </x-emails.callout>

    @include('emails.partials.button', ['url' => $url, 'label' => 'Revisar orden de compra'])

    <p style="font-size:13px;color:#555555;line-height:1.7;margin:24px 0 0;">
        Por favor, tome acción antes de que venza el plazo.
    </p>
@endsection
