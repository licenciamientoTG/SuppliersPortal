@extends('emails.layout')

@section('title', 'Nueva requisición para cotizar')
@section('heading')¡Hola{{ $name ? ' '.$name : '' }}!@endsection

@section('content')
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 14px;">
        Se ha recibido una <strong>nueva requisición</strong> que requiere tu atención en el
        <strong>Portal de Proveedores de TotalGas</strong>.
    </p>
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 24px;">
        A continuación encontrará los detalles:
    </p>

    @include('emails.partials.details', [
        'detailsTitle' => 'Detalles de la requisición',
        'rows' => [
            'Folio'            => $folio,
            'Solicitante'      => $requester,
            'Departamento'     => $department,
            'Centro de costo'  => $costCenter,
            'Compañía'         => $company,
            'Partidas'         => $itemsCount.' producto(s)/servicio(s)',
            'Fecha requerida'  => $requiredDate,
            'Descripción'      => $description,
        ],
    ])

    @include('emails.partials.button', ['url' => $url, 'label' => 'Ver requisición'])

    <p style="font-size:13px;color:#555555;line-height:1.7;margin:24px 0 0;">
        Por favor, revise la requisición y proceda con el proceso de cotización.
    </p>
@endsection
