@extends('emails.layout')

@section('title', 'Orden de compra emitida')
@section('heading', 'Orden de compra emitida')

@section('content')
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 14px;">
        Estimado(a){{ $greetingName ? ' '.$greetingName : '' }}, le informamos que se ha
        <strong>emitido</strong> una nueva Orden de Compra derivada de un contrato comercial.
    </p>

    @include('emails.partials.details', [
        'detailsTitle' => 'Detalles de la OC',
        'rows' => [
            'Folio' => $folio,
            'Monto total' => $total,
            'Condiciones de pago' => $paymentTerms,
        ],
    ])

    <x-emails.callout type="success">
        Puede consultar el detalle completo y registrar sus entregas desde el portal de proveedores.
    </x-emails.callout>

    @include('emails.partials.button', ['url' => $url, 'label' => 'Ver orden de compra'])

    <p style="font-size:13px;color:#555555;line-height:1.7;margin:24px 0 0;">
        Si tiene alguna duda, por favor contacte al solicitante: <strong>{{ $requester }}</strong>.
        Gracias por ser nuestro socio comercial.
    </p>
@endsection
