@extends('emails.layout')

@section('title', 'RFQ vencida')
@section('heading', 'RFQ vencida')
@section('content')
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 14px;">
        Hola{{ $name ? ' '.$name : '' }}, la siguiente solicitud de cotización ya venció y requiere seguimiento:
    </p>

    <p>RFQ <strong>{{ $rfq->folio }}</strong><br>
        Requisición <strong>{{ $requisition?->folio ?? '—' }}</strong><br>
        Fecha límite <strong>{{ $rfq->response_deadline?->format('d/m/Y H:i') ?? '—' }}</strong>
    </p>

    @include('emails.partials.button', ['url' => $url, 'label' => 'Ver RFQ'])
@endsection
