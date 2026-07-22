@extends('emails.layout')

@section('title', 'Alerta EFOS: proveedores desactivados')
@section('heading', 'Alerta EFOS')

@section('content')
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 14px;">
        Hola{{ $name ? ' '.$name : '' }}, se desactivaron <strong>{{ count($suppliers) }}</strong>
        proveedor(es) por aparecer en la lista EFOS del SAT.
    </p>

    <x-emails.callout type="danger">
        Los proveedores fueron desactivados preventivamente. No se les debe facturar hasta revisar su situación fiscal.
    </x-emails.callout>

    @include('emails.partials.details', [
        'detailsTitle' => 'Proveedores desactivados',
        'rows' => collect($suppliers)->mapWithKeys(fn (array $supplier) => [
            $supplier['name'].' (RFC '.$supplier['rfc'].')' => 'Estatus EFOS: '.($supplier['situation'] ?? 'No identificado'),
        ])->all(),
    ])

    @include('emails.partials.button', ['url' => $url, 'label' => 'Revisar lista EFOS'])
@endsection
