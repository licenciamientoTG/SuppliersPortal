@extends('emails.layout')

@section('title', 'Retroalimentacion de requisicion')
@section('heading')Retroalimentacion de Compras - {{ $requisition->folio }}@endsection

@section('content')
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 14px;">
        Hola{{ $requisition->requester?->name ? ' ' . $requisition->requester->name : '' }}, el departamento de Compras
        envio retroalimentacion para tu requisicion <strong>{{ $requisition->folio }}</strong>.
    </p>

    <x-emails.callout type="info" title="Mensaje de Compras">
        {{ $feedback->message }}
    </x-emails.callout>

    @include('emails.partials.details', [
        'detailsTitle' => 'Detalles de la requisicion',
        'rows' => [
            'Folio' => $requisition->folio,
            'Comprador' => $buyer->name,
            'Centro de costo' => $requisition->costCenter?->name,
            'Fecha de envio' => $feedback->sent_at?->format('d/m/Y H:i'),
        ],
    ])

    @include('emails.partials.button', ['url' => $url, 'label' => 'Ver requisicion'])

    <p style="font-size:13px;color:#555555;line-height:1.7;margin:24px 0 0;">
        Revisa la retroalimentacion y realiza los ajustes necesarios si aplica.
    </p>
@endsection
