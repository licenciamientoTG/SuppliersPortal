@extends('emails.layout')

@section('title', 'Orden de compra aprobada')
@section('heading', 'Orden de compra aprobada')

@section('content')
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 14px;">
        Estimado(a){{ $greetingName ? ' '.$greetingName : '' }}, le informamos que se ha
        <strong>aprobado</strong> una nueva Orden de Compra.
    </p>

    @include('emails.partials.details', [
        'detailsTitle' => 'Detalles de la OC',
        'rows' => [
            'Folio'                => $folio,
            'Monto total'          => $total,
            'Condiciones de pago'  => $paymentTerms,
        ],
    ])

    <x-emails.callout type="success">
        Puede consultar el detalle completo y descargar el documento desde el portal de proveedores.
    </x-emails.callout>

    @include('emails.partials.button', ['url' => $url, 'label' => 'Ver orden de compra'])

    <p style="font-size:13px;color:#555555;line-height:1.7;margin:24px 0 0;">
        Si tiene alguna duda, por favor contacte al solicitante: <strong>{{ $requester }}</strong>.
        Gracias por ser nuestro socio comercial.
    </p>
@endsection
