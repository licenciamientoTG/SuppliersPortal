@extends('emails.layout')

@section('title', 'Requisición rechazada')
@section('heading')Requisición rechazada — {{ $folio }}@endsection

@section('content')
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 14px;">
        Hola{{ $name ? ' '.$name : '' }}, tu requisición con folio <strong>{{ $folio }}</strong>
        fue rechazada por el departamento de Compras.
    </p>

    @if (!empty($reason))
        <x-emails.callout type="danger" title="Motivo del rechazo">{{ $reason }}</x-emails.callout>
    @endif

    @include('emails.partials.details', [
        'detailsTitle' => 'Detalles de la requisición',
        'rows' => [
            'Folio'             => $folio,
            'Departamento'      => $department,
            'Centro de costo'   => $costCenter,
            'Fecha del rechazo' => $rejectedAt,
        ],
    ])

    @include('emails.partials.button', ['url' => $url, 'label' => 'Revisar y corregir'])

    <p style="font-size:13px;color:#555555;line-height:1.7;margin:24px 0 0;">
        Si considera que esto es un error, contacte a su superior para más información.
    </p>
@endsection
