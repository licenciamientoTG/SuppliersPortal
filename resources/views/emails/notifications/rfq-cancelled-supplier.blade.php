@extends('emails.layout')

@section('title', 'Solicitud de cotización cancelada')
@section('heading', 'Solicitud de cotización cancelada')

@section('content')
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 14px;">
        Estimado proveedor, le informamos que la solicitud de cotización
        <strong>{{ $rfqFolio }}</strong> ha sido <strong>cancelada</strong>.
    </p>

    @include('emails.partials.details', [
        'detailsTitle' => 'Detalles de la RFQ cancelada',
        'rows' => [
            'Folio'                => $rfqFolio,
            'Grupo'                => $group,
            'Fecha de cancelación' => $cancelledAt,
        ],
    ])

    @if (!empty($reason))
        <x-emails.callout type="warning" title="Motivo de la cancelación">{{ $reason }}</x-emails.callout>
    @endif

    <x-emails.callout type="info">
        No es necesario que envíe una cotización para esta solicitud.
    </x-emails.callout>

    @include('emails.partials.button', ['url' => $url, 'label' => 'Ir al portal'])

    <p style="font-size:13px;color:#555555;line-height:1.7;margin:24px 0 0;">
        Agradecemos su tiempo e interés. Esperamos seguir trabajando juntos en futuras oportunidades.
    </p>
@endsection
