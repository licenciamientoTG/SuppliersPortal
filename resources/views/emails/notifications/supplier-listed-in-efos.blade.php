@extends('emails.layout')

@section('title', 'Alerta EFOS: proveedores activos identificados')
@section('heading', 'Alerta EFOS')

@section('content')
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 14px;">
        Hola{{ $name ? ' '.$name : '' }}, se identificaron <strong>{{ count($suppliers) }}</strong>
        proveedor(es) activo(s) en la lista EFOS del SAT.
    </p>

    <x-emails.callout type="danger">
        No se debe facturar a estos proveedores hasta revisar su situación fiscal.
    </x-emails.callout>

    @include('emails.partials.details', [
        'detailsTitle' => 'Proveedores identificados',
        'rows' => collect($suppliers)->mapWithKeys(fn (array $supplier) => [
            $supplier['name'] => 'RFC '.$supplier['rfc'],
        ])->all(),
    ])

    @include('emails.partials.button', ['url' => $url, 'label' => 'Revisar lista EFOS'])
@endsection
