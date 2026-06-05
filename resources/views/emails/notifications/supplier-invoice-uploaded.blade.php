@extends('emails.layout')

@section('title', 'Factura cargada por proveedor')
@section('heading', 'Factura cargada por proveedor')

@section('content')
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 14px;">
        El proveedor <strong>{{ $supplierName }}</strong> cargó una factura en el
        <strong>Portal de Proveedores de TotalGas</strong>.
    </p>

    @include('emails.partials.details', [
        'detailsTitle' => 'Detalles de la factura',
        'rows' => [
            'Proveedor' => $supplierName,
            'UUID'      => $uuid,
            'Total'     => $total,
        ],
    ])

    @include('emails.partials.button', ['url' => $url, 'label' => 'Ver facturas'])
@endsection
