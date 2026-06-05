@extends('emails.layout')

@section('title', 'Requisición enviada a Compras')
@section('heading')¡Hola{{ $name ? ' '.$name : '' }}!@endsection

@section('content')
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 14px;">
        Tu requisición ha sido <strong>enviada exitosamente</strong> al departamento de
        Compras del <strong>Portal de Proveedores de TotalGas</strong>.
    </p>
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 24px;">
        A continuación encontrará el resumen de su requisición:
    </p>

    @include('emails.partials.details', [
        'detailsTitle' => 'Detalles de la requisición',
        'rows' => [
            'Folio'           => $folio,
            'Centro de costo' => $costCenter,
            'Departamento'    => $department,
            'Partidas'        => $itemsCount.' producto(s)/servicio(s)',
            'Fecha requerida' => $requiredDate ?: 'No especificada',
            'Fecha de envío'  => $submittedAt,
        ],
    ])

    <x-emails.callout type="info">
        <strong>Estado actual:</strong> Pendiente de Cotización
    </x-emails.callout>

    @include('emails.partials.button', ['url' => $url, 'label' => 'Ver requisición'])

    <p style="font-size:13px;color:#555555;line-height:1.7;margin:24px 0 0;">
        <strong>Próximos pasos:</strong> revisión por el departamento de Compras,
        proceso de cotización con proveedores y aprobación final.
        Le mantendremos informado sobre el progreso de su requisición.
    </p>
@endsection
