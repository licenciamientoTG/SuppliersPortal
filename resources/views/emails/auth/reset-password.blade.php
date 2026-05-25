<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperación de contraseña</title>
</head>
<body style="margin:0;padding:0;background-color:#eef2f7;font-family:Arial,Helvetica,sans-serif;">

    @php($safeUrl = str_starts_with($url, 'https://') || str_starts_with($url, 'http://') ? $url : '#')

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#eef2f7;padding:32px 16px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0"
                       style="max-width:600px;width:100%;background-color:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.10);">

                    {{-- Header: franja blanca con logo --}}
                    <tr>
                        <td style="background-color:#ffffff;padding:24px 32px 20px;text-align:center;border-bottom:3px solid #188ae2;">
                            <img src="{{ asset('images/logos/logo_TotalGas_hor_azul.png') }}"
                                 alt="TotalGas"
                                 width="180"
                                 style="max-height:52px;max-width:180px;display:block;margin:0 auto;">
                            <div style="color:#888888;font-size:11px;margin-top:6px;letter-spacing:1.5px;text-transform:uppercase;">
                                Portal de Proveedores
                            </div>
                        </td>
                    </tr>

                    {{-- Banda de título --}}
                    <tr>
                        <td style="background-color:#188ae2;background:linear-gradient(135deg,#188ae2 0%,#0f5f9e 100%);padding:16px 32px;text-align:center;">
                            <span style="color:#ffffff;font-size:15px;font-weight:600;letter-spacing:0.5px;">
                                Recuperación de contraseña
                            </span>
                        </td>
                    </tr>

                    {{-- Cuerpo --}}
                    <tr>
                        <td style="padding:32px 32px 24px;">
                            <p style="font-size:14px;color:#313a46;margin:0 0 12px;">Hola,</p>
                            <p style="font-size:13px;color:#555555;line-height:1.7;margin:0 0 16px;">
                                Hemos recibido una solicitud para <strong>restablecer la contraseña</strong>
                                asociada a tu cuenta en el Portal de Proveedores de TotalGas.
                            </p>
                            <p style="font-size:13px;color:#555555;line-height:1.7;margin:0 0 28px;">
                                Haz clic en el siguiente botón para continuar. Este enlace es válido por
                                <strong>{{ config('auth.passwords.users.expire') }} minutos</strong>.
                            </p>

                            {{-- Botón CTA --}}
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding:4px 0 28px;">
                                        <a href="{{ $safeUrl }}"
                                           style="display:inline-block;background-color:#188ae2;color:#ffffff;text-decoration:none;padding:12px 36px;border-radius:5px;font-size:14px;font-weight:700;letter-spacing:0.5px;">
                                            Restablecer contraseña
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            {{-- Fallback URL --}}
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="background-color:#f8f9fa;border-radius:4px;padding:14px 16px;">
                                        <p style="font-size:11px;color:#888888;margin:0 0 6px;line-height:1.5;">
                                            Si el botón no funciona, copia y pega el siguiente enlace en tu navegador:
                                        </p>
                                        <p style="font-size:11px;color:#188ae2;margin:0;word-break:break-all;line-height:1.5;">
                                            {{ $url }}
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            {{-- Aviso de seguridad --}}
                            <p style="font-size:11px;color:#aaaaaa;margin:24px 0 0;text-align:center;line-height:1.6;">
                                Si no solicitaste este cambio, puedes ignorar este mensaje.<br>
                                Tu contraseña permanecerá sin cambios.
                            </p>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background-color:#f4f6f9;border-top:1px solid #e8ecf0;padding:16px 32px;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="font-size:11px;color:#aaaaaa;">
                                        © {{ date('Y') }} TotalGas S.A. de C.V.
                                    </td>
                                    <td align="right" style="font-size:11px;color:#aaaaaa;">
                                        no-reply@totalgas.com
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>
