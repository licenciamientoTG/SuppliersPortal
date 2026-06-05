@extends('emails.layout')

@section('title', 'Orden de compra devuelta para corrección')
@section('heading', 'Orden de compra devuelta para corrección')

@section('content')
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 14px;">
        Hola{{ $name ? ' '.$name : '' }}, tu Orden de Compra Directa ha sido <strong>devuelta</strong>
        por el aprobador para que realices las correcciones indicadas.
    </p>

    @include('emails.partials.details', [
        'detailsTitle' => 'Detalles de la OC',
        'rows' => [
            'Folio'       => $folio,
            'Monto total' => $total,
            'Proveedor'   => $supplier,
        ],
    ])

    <x-emails.callout type="warning" title="Instrucciones del aprobador">{{ $instructions }}</x-emails.callout>

    @include('emails.partials.button', ['url' => $url, 'label' => 'Editar orden de compra'])

    <p style="font-size:13px;color:#555555;line-height:1.7;margin:24px 0 0;">
        Por favor realiza las correcciones y vuelve a enviar la OC para aprobación.
    </p>
@endsection
