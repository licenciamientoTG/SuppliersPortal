@extends('emails.layout')

@section('title', 'Nueva solicitud de cotización')
@section('heading', 'Nueva solicitud de cotización')

@section('content')
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 14px;">
        Estimado proveedor, le invitamos a participar en una nueva solicitud de cotización
        en el <strong>Portal de Proveedores de TotalGas</strong>.
    </p>
    <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 24px;">
        A continuación encontrará los detalles de la solicitud:
    </p>

    @include('emails.partials.details', [
        'detailsTitle' => 'Detalles de la solicitud',
        'rows' => [
            'Folio'               => $folio,
            'Grupo'               => $group,
            'Productos/Servicios' => $itemsCount.' partida(s)',
            'Fecha límite'        => $deadline.' ('.$daysLeft.' días)',
        ],
    ])

    @if (!empty($supplierMessage))
        <x-emails.callout type="info" title="Mensaje adicional">{{ $supplierMessage }}</x-emails.callout>
    @endif

    @include('emails.partials.button', ['url' => $url, 'label' => 'Acceder al portal y cotizar'])

    <table width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0 0;">
        <tr>
            <td style="background-color:#f6f8fb;border:1px solid #e3e9f2;border-radius:8px;padding:18px 22px;">
                <p style="font-size:11px;color:#0d2b5e;margin:0 0 12px;letter-spacing:1px;text-transform:uppercase;font-weight:700;">
                    Instrucciones
                </p>
                <p style="font-size:13px;color:#555555;line-height:1.8;margin:0;">
                    1. Ingrese al Portal de Proveedores con sus credenciales.<br>
                    2. Revise el detalle de los productos solicitados.<br>
                    3. Ingrese sus precios y condiciones comerciales.<br>
                    4. Envíe su cotización antes de la fecha límite.
                </p>
            </td>
        </tr>
    </table>

    <x-emails.callout type="warning" title="Importante">
        Las cotizaciones recibidas después de la fecha límite no serán consideradas.
    </x-emails.callout>
@endsection
