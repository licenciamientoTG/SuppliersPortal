@extends('emails.layout')

@section('title', $heading)
@section('heading', $heading)

@section('content')
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 14px;">
        Hola{{ $name ? ' '.$name : '' }}, {{ $intro }}
    </p>

    @include('emails.partials.details', [
        'detailsTitle' => 'Detalles',
        'rows' => $details,
    ])

    @include('emails.partials.button', ['url' => $url, 'label' => $buttonLabel])
@endsection
