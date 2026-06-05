@extends('emails.layout')

@section('title', 'Requisición reactivada')
@section('heading')¡Hola{{ $name ? ' '.$name : '' }}!@endsection

@section('content')
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 14px;">
        Tu requisición que estaba pausada ha sido <strong>reactivada automáticamente</strong>
        porque el producto solicitado fue aprobado en el catálogo.
    </p>
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 24px;">
        A continuación encontrará el detalle:
    </p>

    @include('emails.partials.details', [
        'detailsTitle' => 'Detalles de la reactivación',
        'rows' => [
            'Folio'              => $folio,
            'Producto aprobado'  => $productCode,
            'Descripción'        => $productDescription,
            'Estado actual'      => 'Pendiente de Validación',
        ],
    ])

    <x-emails.callout type="success">
        Tu requisición seguirá ahora el flujo normal de validación.
    </x-emails.callout>

    @include('emails.partials.button', ['url' => $url, 'label' => 'Ver requisición'])

    <p style="font-size:13px;color:#555555;line-height:1.7;margin:24px 0 0;">
        ¡Gracias por tu paciencia!
    </p>
@endsection
