<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Portal de Proveedores')</title>
</head>
<body style="margin:0;padding:0;background-color:#eef2f7;font-family:Arial,Helvetica,sans-serif;">

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
                                        <img src="{{ isset($message) ? $message->embed(public_path('images/logos/logo_TotalGas_hor.png')) : asset('images/logos/logo_TotalGas_hor.png') }}"
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
                                @yield('heading')
                            </h1>

                            @yield('content')
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
