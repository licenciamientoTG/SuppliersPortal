@extends('emails.layout')

@section('title', 'Nueva OC Directa para revisión')
@section('heading')¡Hola{{ $name ? ' '.$name : '' }}!@endsection

@section('content')
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 14px;">
        Se ha generado una <strong>nueva Orden de Compra Directa</strong> que requiere tu aprobación
        en el <strong>Portal de Proveedores de TotalGas</strong>.
    </p>
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 24px;">
        A continuación encontrará los detalles:
    </p>

    @include('emails.partials.details', [
        'detailsTitle' => 'Detalles de la OCD',
        'rows' => [
            'Folio'                  => $folio,
            'Proveedor'              => $supplier,
            'Centro de costo'        => $costCenter,
            'Monto total'            => $total,
            'Rol autorizador'        => $authorizerRole,
            'Límite aplicado'        => $limit,
            'Solicitante'            => $requester,
            'Justificación'          => $justification,
        ],
    ])

    <x-emails.callout type="warning" title="Nota importante">
        Tienes un plazo de <strong>7 días naturales</strong> para revisar y dictaminar esta solicitud.
    </x-emails.callout>

    @include('emails.partials.button', ['url' => $url, 'label' => 'Ver orden de compra'])

    <p style="font-size:13px;color:#555555;line-height:1.7;margin:24px 0 0;">
        Gracias por tu gestión.
    </p>
@endsection
