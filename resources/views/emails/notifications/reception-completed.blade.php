@extends('emails.layout')

@section('title', 'Recepción registrada')
@section('heading')Recepción {{ $statusLabel }} — {{ $orderFolio }}@endsection

@section('content')
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 14px;">
        Hola{{ $name ? ' '.$name : '' }}, se ha registrado una <strong>recepción {{ $statusLabel }}</strong>
        para la orden <strong>{{ $orderFolio }}</strong>.
    </p>

    @include('emails.partials.details', [
        'detailsTitle' => 'Detalles de la recepción',
        'rows' => array_filter([
            'Folio recepción'         => $receptionFolio,
            'Orden de compra'         => $orderFolio,
            'Proveedor'               => $supplier,
            'Punto de entrega'        => $deliveryPoint,
            'Recibió'                 => $receiver,
            'Fecha de recepción'      => $receivedAt,
            'Estado de la orden'      => $orderStatus,
            'Referencia del proveedor'=> $deliveryReference,
        ]),
    ])

    @unless ($isComplete)
        <x-emails.callout type="warning">
            La orden aún tiene partidas pendientes de recepción. Se esperan entregas adicionales del proveedor.
        </x-emails.callout>
    @endunless

    @include('emails.partials.button', ['url' => $url, 'label' => 'Ver comprobante de recepción'])
@endsection
