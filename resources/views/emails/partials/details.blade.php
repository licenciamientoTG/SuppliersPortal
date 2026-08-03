{{--
    Caja de datos reutilizable.
    Parámetros:
      - $detailsTitle (string)  Título de la caja (ej. "Detalles de la OC")
      - $rows (array)           Pares etiqueta => valor. Los valores null o '' se omiten.
--}}
@php
    $__rows = array_filter($rows ?? [], fn ($v) => ! is_null($v) && $v !== '');
@endphp

@if (! empty($__rows))
    <table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 8px;">
        <tr>
            <td style="background-color:#f6f8fb;border:1px solid #e3e9f2;border-left:4px solid #A9CA48;border-radius:8px;padding:20px 22px;">
                <p style="font-size:11px;color:#0d2b5e;margin:0 0 14px;letter-spacing:1px;text-transform:uppercase;font-weight:700;">
                    {{ $detailsTitle ?? 'Detalles' }}
                </p>
                <table width="100%" cellpadding="0" cellspacing="0">
                    @foreach ($__rows as $label => $value)
                        <tr>
                            <td style="font-size:13px;color:#888888;padding:0 0 8px;width:165px;vertical-align:top;">{{ $label }}</td>
                            <td style="font-size:13px;color:#222222;padding:0 0 8px;font-weight:600;word-break:break-word;">{{ $value }}</td>
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>
@endif
