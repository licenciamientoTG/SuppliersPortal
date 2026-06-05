{{--
    Caja de aviso/estado con color.
    Props:
      - type:  info | success | warning | danger
      - title: (opcional) encabezado de la caja
--}}
@props(['type' => 'info', 'title' => null])

@php
    $palette = [
        'info'    => ['bg' => '#eaf1fb', 'border' => '#cfe0f5', 'color' => '#0d2b5e'],
        'success' => ['bg' => '#eaf7ec', 'border' => '#bfe3c6', 'color' => '#1e6b34'],
        'warning' => ['bg' => '#fff7e6', 'border' => '#ffe2a8', 'color' => '#9a6b00'],
        'danger'  => ['bg' => '#fdecec', 'border' => '#f5c2c2', 'color' => '#9b1c1c'],
    ];
    $c = $palette[$type] ?? $palette['info'];
@endphp

<table width="100%" cellpadding="0" cellspacing="0" style="margin:14px 0 24px;">
    <tr>
        <td style="background-color:{{ $c['bg'] }};border:1px solid {{ $c['border'] }};border-radius:6px;padding:12px 16px;">
            @if ($title)
                <p style="font-size:11px;color:{{ $c['color'] }};margin:0 0 6px;letter-spacing:0.5px;text-transform:uppercase;font-weight:700;">
                    {{ $title }}
                </p>
            @endif
            <p style="font-size:13px;color:{{ $c['color'] }};margin:0;line-height:1.6;">
                {{ $slot }}
            </p>
        </td>
    </tr>
</table>
