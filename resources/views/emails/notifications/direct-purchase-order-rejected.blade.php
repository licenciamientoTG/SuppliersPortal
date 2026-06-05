@extends('emails.layout')

@section('title', 'Orden de compra rechazada')
@section('heading')Orden de compra rechazada — {{ $folio }}@endsection

@section('content')
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 14px;">
        Estimado(a){{ $name ? ' '.$name : '' }}, le informamos que la siguiente Orden de Compra Directa
        ha sido <strong>rechazada</strong>.
    </p>

    @include('emails.partials.details', [
        'detailsTitle' => 'Detalles de la OC',
        'rows' => [
            'Folio'           => $folio,
            'Monto total'     => $total,
            'Proveedor'       => $supplier,
            'Centro de costo' => $costCenter,
            'Solicitado por'  => $requester,
        ],
    ])

    <x-emails.callout type="danger" title="Motivo del rechazo">{{ $reason }}</x-emails.callout>

    @include('emails.partials.button', ['url' => $url, 'label' => 'Ver orden de compra'])

    <p style="font-size:13px;color:#555555;line-height:1.7;margin:24px 0 0;">
        Si tiene alguna duda, contacte al aprobador.
    </p>
@endsection
