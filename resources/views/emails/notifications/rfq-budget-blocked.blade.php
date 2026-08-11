@extends('emails.layout')

@section('title', 'Presupuesto pendiente de revisar')
@section('heading')Presupuesto pendiente de revisar - {{ $notice->requisition->folio }}@endsection

@section('content')
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 14px;">
        Hola{{ $requester->name ? ' '.$requester->name : '' }}, Compras no puede continuar con la adjudicación de tu requisición.
    </p>

    <x-emails.callout type="warning" title="Acción requerida">
        {{ $notice->message }}
    </x-emails.callout>

    @if($notice->note)
        <x-emails.callout type="info" title="Nota de Compras">{{ $notice->note }}</x-emails.callout>
    @endif

    @include('emails.partials.details', [
        'detailsTitle' => 'Detalle de la cotización',
        'rows' => [
            'Requisición' => $notice->requisition->folio,
            'Grupo' => $notice->rfq->quotationGroup?->name ?? $notice->rfq->folio,
            'Proveedor de referencia' => $notice->supplier->company_name,
            'Comprador' => $notice->buyer->name,
            'Fecha de aviso' => $notice->notified_at?->format('d/m/Y H:i'),
        ],
    ])

    @include('emails.partials.button', ['url' => $url, 'label' => 'Ver requisición'])
@endsection
