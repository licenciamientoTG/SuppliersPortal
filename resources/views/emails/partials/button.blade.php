{{--
    Botón CTA + enlace de respaldo.
    Parámetros:
      - $url (string)    Destino del botón.
      - $label (string)  Texto del botón.
--}}
@php($__safeUrl = ($url && (str_starts_with($url, 'https://') || str_starts_with($url, 'http://'))) ? $url : '#')

<table width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td align="center" style="padding:4px 0 24px;">
            <a href="{{ $__safeUrl }}"
               style="display:inline-block;background-color:#1a4b96;color:#ffffff;text-decoration:none;padding:13px 40px;border-radius:6px;font-size:14px;font-weight:700;letter-spacing:0.5px;">
                {{ $label ?? 'Ver detalle' }}
            </a>
        </td>
    </tr>
</table>

<table width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td style="background-color:#f8f9fa;border-radius:4px;padding:14px 16px;">
            <p style="font-size:11px;color:#888888;margin:0 0 6px;line-height:1.5;">
                Si el botón no funciona, copie y pegue el siguiente enlace en su navegador:
            </p>
            <p style="font-size:11px;color:#1a4b96;margin:0;word-break:break-all;line-height:1.5;">
                {{ $url }}
            </p>
        </td>
    </tr>
</table>
