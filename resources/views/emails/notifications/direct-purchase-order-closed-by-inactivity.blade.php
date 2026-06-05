@extends('emails.layout')

@section('title', 'OC Directa cerrada por inactividad')
@section('heading')¡Hola{{ $name ? ' '.$name : '' }}!@endsection

@section('content')
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 14px;">
        La siguiente Orden de Compra Directa ha sido <strong>cerrada automáticamente por inactividad</strong>
        al superar los {{ $inactivityDays }} días naturales sin ser aprobada.
    </p>

    @include('emails.partials.details', [
        'detailsTitle' => 'Detalles de la OCD',
        'rows' => [
            'Folio'                 => $folio,
            'Proveedor'             => $supplier,
            'Centro de costo'       => $costCenter,
            'Monto total'           => $total,
            'Solicitante'           => $requester,
            'Enviada a aprobación'  => $submittedAt,
            'Cerrada el'            => $closedAt,
        ],
    ])

    <x-emails.callout type="danger" title="Efectos del cierre">
        La OCD ya no puede ser aprobada. El proveedor no puede cargar remisiones contra esta OC.
        Si el producto/servicio sigue siendo necesario, debe generarse una nueva requisición.
    </x-emails.callout>

    @include('emails.partials.button', ['url' => $url, 'label' => 'Ver orden de compra'])

    <p style="font-size:13px;color:#555555;line-height:1.7;margin:24px 0 0;">
        Este es un mensaje automático del sistema.
    </p>
@endsection
