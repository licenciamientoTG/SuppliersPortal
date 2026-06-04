<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo proveedor registrado</title>
</head>
<body style="margin:0;padding:0;background-color:#eef2f7;font-family:Arial,Helvetica,sans-serif;">

    @php($safeUrl = (str_starts_with($url, 'https://') || str_starts_with($url, 'http://')) ? $url : '#')

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#eef2f7;padding:32px 16px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0"
                       style="max-width:600px;width:100%;background-color:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 2px 14px rgba(13,43,94,0.12);">

                    {{-- Header: banda azul marino con logo sobre contenedor blanco --}}
                    <tr>
                        <td style="background-color:#0d2b5e;background:linear-gradient(135deg,#0d2b5e 0%,#1a4b96 100%);padding:28px 32px 24px;text-align:center;border-bottom:4px solid #A9CA48;">
                            <table cellpadding="0" cellspacing="0" align="center">
                                <tr>
                                    <td style="background-color:#ffffff;border-radius:8px;padding:12px 22px;">
                                        <img src="{{ asset('images/logos/logo_TotalGas_hor.png') }}"
                                             alt="TotalGas"
                                             width="190"
                                             style="max-height:48px;max-width:190px;display:block;margin:0 auto;">
                                    </td>
                                </tr>
                            </table>
                            <div style="color:#cdd8ec;font-size:11px;margin-top:14px;letter-spacing:2px;text-transform:uppercase;">
                                Portal de Proveedores
                            </div>
                        </td>
                    </tr>

                    {{-- Cuerpo --}}
                    <tr>
                        <td style="padding:32px 32px 8px;">
                            <h1 style="font-size:20px;color:#0d2b5e;margin:0 0 16px;font-weight:700;">
                                Nuevo proveedor registrado
                            </h1>
                            <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 14px;">
                                Hola{{ $name ? ' '.$name : '' }}, se registró un nuevo proveedor en el
                                <strong>Portal de Proveedores de TotalGas</strong> y su alta quedó lista
                                para revisión por Compras.
                            </p>
                            <p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 24px;">
                                A continuación encontrará los datos del proveedor:
                            </p>

                            {{-- Caja de datos del proveedor --}}
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 8px;">
                                <tr>
                                    <td style="background-color:#f6f8fb;border:1px solid #e3e9f2;border-left:4px solid #A9CA48;border-radius:8px;padding:20px 22px;">
                                        <p style="font-size:11px;color:#0d2b5e;margin:0 0 14px;letter-spacing:1px;text-transform:uppercase;font-weight:700;">
                                            Datos del proveedor
                                        </p>
                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="font-size:13px;color:#888888;padding:0 0 8px;width:140px;vertical-align:top;">Razón social</td>
                                                <td style="font-size:13px;color:#222222;padding:0 0 8px;font-weight:600;">{{ $companyName ?: '-' }}</td>
                                            </tr>
                                            <tr>
                                                <td style="font-size:13px;color:#888888;padding:0 0 8px;vertical-align:top;">RFC</td>
                                                <td style="font-size:13px;color:#222222;padding:0 0 8px;font-weight:600;">{{ $rfc ?: '-' }}</td>
                                            </tr>
                                            <tr>
                                                <td style="font-size:13px;color:#888888;padding:0 0 8px;vertical-align:top;">Contacto</td>
                                                <td style="font-size:13px;color:#222222;padding:0 0 8px;font-weight:600;">{{ $contact ?: '-' }}</td>
                                            </tr>
                                            <tr>
                                                <td style="font-size:13px;color:#888888;padding:0 0 8px;vertical-align:top;">Correo</td>
                                                <td style="font-size:13px;color:#222222;padding:0 0 8px;font-weight:600;word-break:break-all;">{{ $email ?: '-' }}</td>
                                            </tr>
                                            <tr>
                                                <td style="font-size:13px;color:#888888;padding:0;vertical-align:top;">Tipo de proveedor</td>
                                                <td style="font-size:13px;color:#222222;padding:0;font-weight:600;">{{ $supplierType ?: '-' }}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            {{-- Botón CTA --}}
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding:24px 0 24px;">
                                        <a href="{{ $safeUrl }}"
                                           style="display:inline-block;background-color:#1a4b96;color:#ffffff;text-decoration:none;padding:13px 40px;border-radius:6px;font-size:14px;font-weight:700;letter-spacing:0.5px;">
                                            Revisar proveedor
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            {{-- Fallback URL --}}
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

                            <p style="font-size:13px;color:#555555;line-height:1.7;margin:24px 0 8px;">
                                Ingrese al portal para revisar su expediente y continuar con el proceso de alta.
                            </p>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background-color:#0d2b5e;padding:18px 32px;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="font-size:11px;color:#aebfd9;">
                                        &copy; {{ date('Y') }} TotalGas S.A. de C.V.
                                    </td>
                                    <td align="right" style="font-size:11px;color:#aebfd9;">
                                        Litros exactos siempre.
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
