# Email institucional de recuperación de contraseña — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reemplazar el correo de recuperación de contraseña por defecto de Laravel (en inglés, sin branding) con un email HTML institucional en español con el logo de TotalGas.

**Architecture:** Se crea una clase `ResetPasswordNotification` que extiende la de Laravel, sobreescribiendo `toMail()` para usar una vista Blade propia. El modelo `User` sobreescribe `sendPasswordResetNotification()` para despachar la nueva notificación. Todo el HTML usa inline CSS para compatibilidad con Gmail, Outlook y Apple Mail.

**Tech Stack:** Laravel 11, PHP 8.x, Blade (inline CSS), PHPUnit/Pest vía `php artisan test`

---

## Mapa de archivos

| Acción | Archivo | Responsabilidad |
|--------|---------|----------------|
| Crear | `tests/Feature/Auth/PasswordResetNotificationTest.php` | Tests de la notificación |
| Crear | `resources/views/emails/auth/reset-password.blade.php` | Template HTML del email |
| Crear | `app/Notifications/ResetPasswordNotification.php` | Notificación custom con asunto en español y vista propia |
| Modificar | `app/Models/User.php` | Override de `sendPasswordResetNotification()` |

---

## Task 1: Escribir los tests (TDD — empezar en rojo)

**Files:**
- Create: `tests/Feature/Auth/PasswordResetNotificationTest.php`

- [ ] **Step 1: Crear el archivo de tests**

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_dispatches_custom_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_reset_notification_has_spanish_subject(): void
    {
        $user = User::factory()->create();
        $notification = new ResetPasswordNotification('fake-token-123');

        $mail = $notification->toMail($user);

        $this->assertEquals(
            'Recuperación de contraseña — Portal de Proveedores',
            $mail->subject
        );
    }

    public function test_reset_notification_uses_custom_blade_view(): void
    {
        $user = User::factory()->create();
        $notification = new ResetPasswordNotification('fake-token-123');

        $mail = $notification->toMail($user);

        $this->assertEquals('emails.auth.reset-password', $mail->view[0]);
    }

    public function test_reset_notification_passes_url_to_view(): void
    {
        $user = User::factory()->create();
        $notification = new ResetPasswordNotification('fake-token-123');

        $mail = $notification->toMail($user);

        $this->assertArrayHasKey('url', $mail->view[1]);
        $this->assertStringContainsString('fake-token-123', $mail->view[1]['url']);
    }
}
```

- [ ] **Step 2: Ejecutar los tests y verificar que fallan**

```bash
php artisan test tests/Feature/Auth/PasswordResetNotificationTest.php
```

Salida esperada: 4 tests en FAIL con errores como `Class "App\Notifications\ResetPasswordNotification" not found`.

---

## Task 2: Crear el template Blade del email

**Files:**
- Create: `resources/views/emails/auth/reset-password.blade.php`

- [ ] **Step 1: Crear el directorio y el archivo Blade**

Crear `resources/views/emails/auth/reset-password.blade.php` con el siguiente contenido completo. Todos los estilos son inline CSS — no usar clases externas ni `<style>` en `<head>` (los clientes de email los ignoran).

```blade
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperación de contraseña</title>
</head>
<body style="margin:0;padding:0;background-color:#eef2f7;font-family:Arial,Helvetica,sans-serif;">

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
                                 style="max-height:52px;max-width:180px;display:block;margin:0 auto;"
                                 onerror="this.style.display='none';document.getElementById('logo-fallback').style.display='block'">
                            <div id="logo-fallback"
                                 style="display:none;font-weight:800;font-size:22px;color:#188ae2;letter-spacing:2px;">
                                TotalGas
                            </div>
                            <div style="color:#888888;font-size:11px;margin-top:6px;letter-spacing:1.5px;text-transform:uppercase;">
                                Portal de Proveedores
                            </div>
                        </td>
                    </tr>

                    {{-- Banda de título --}}
                    <tr>
                        <td style="background:linear-gradient(135deg,#188ae2 0%,#0f5f9e 100%);padding:16px 32px;text-align:center;">
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
                                <strong>60 minutos</strong>.
                            </p>

                            {{-- Botón CTA --}}
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding:4px 0 28px;">
                                        <a href="{{ $url }}"
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
```

- [ ] **Step 2: Verificar que la vista existe (sin ejecutar tests aún)**

```bash
php artisan view:cache
```

Salida esperada: sin errores de compilación.

---

## Task 3: Crear la clase ResetPasswordNotification

**Files:**
- Create: `app/Notifications/ResetPasswordNotification.php`

- [ ] **Step 1: Crear la clase**

```php
<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = $this->resetUrl($notifiable);

        return (new MailMessage)
            ->subject('Recuperación de contraseña — Portal de Proveedores')
            ->view('emails.auth.reset-password', ['url' => $url]);
    }
}
```

- [ ] **Step 2: Ejecutar los tests y verificar que pasan**

```bash
php artisan test tests/Feature/Auth/PasswordResetNotificationTest.php
```

Salida esperada:
```
PASS  Tests\Feature\Auth\PasswordResetNotificationTest
✓ password reset dispatches custom notification
✓ reset notification has spanish subject
✓ reset notification uses custom blade view
✓ reset notification passes url to view
```

Si el test `test_password_reset_dispatches_custom_notification` falla todavía, es porque falta el paso siguiente (Task 4). Los otros 3 deben pasar ya.

- [ ] **Step 3: Commit de Notification + Blade view**

```bash
git add app/Notifications/ResetPasswordNotification.php resources/views/emails/auth/reset-password.blade.php tests/Feature/Auth/PasswordResetNotificationTest.php
git commit -m "feat: custom password reset notification with institutional HTML email"
```

---

## Task 4: Conectar la notificación al modelo User

**Files:**
- Modify: `app/Models/User.php`

- [ ] **Step 1: Agregar el método `sendPasswordResetNotification` en User**

En `app/Models/User.php`, agregar el import y el método al final de la clase, antes del cierre `}`:

Import a agregar después de los imports existentes:
```php
use App\Notifications\ResetPasswordNotification;
```

Método a agregar al final de la clase:
```php
public function sendPasswordResetNotification($token): void
{
    $this->notify(new ResetPasswordNotification($token));
}
```

El archivo resultante debe verse así en la sección de imports y al final:

```php
<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;  // <-- agregar
use Illuminate\Database\Eloquent\Factories\HasFactory;
// ... resto de imports existentes sin cambios ...

class User extends Authenticatable
{
    // ... todo el contenido existente sin cambios ...

    public function sendPasswordResetNotification($token): void  // <-- agregar al final
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
```

- [ ] **Step 2: Ejecutar todos los tests de autenticación**

```bash
php artisan test tests/Feature/Auth/
```

Salida esperada: todos los tests pasan (los existentes + los 4 nuevos).

- [ ] **Step 3: Ejecutar la suite completa para detectar regresiones**

```bash
php artisan test
```

Salida esperada: todos los tests pasan sin nuevos fallos.

- [ ] **Step 4: Commit final**

```bash
git add app/Models/User.php
git commit -m "feat: wire ResetPasswordNotification to User model"
```

---

## Task 5: Prueba manual en QA

- [ ] **Step 1: Limpiar la caché de vistas**

```bash
php artisan view:clear
php artisan config:clear
```

- [ ] **Step 2: Ir a `/forgot-password` en el navegador**

Ingresar el email de un usuario real del ambiente de QA y enviar el formulario.

- [ ] **Step 3: Verificar el email recibido**

Abrir la bandeja del email ingresado. El correo debe:
- ✅ Llegar con asunto: `Recuperación de contraseña — Portal de Proveedores`
- ✅ Mostrar el logo `logo_TotalGas_hor_azul.png` en la franja blanca superior
- ✅ Mostrar la banda azul degradada con el título
- ✅ Tener el botón "Restablecer contraseña" funcional
- ✅ Mostrar la URL de fallback
- ✅ Footer con `© 2025 TotalGas S.A. de C.V.` y `no-reply@totalgas.com`

- [ ] **Step 4: Verificar que el enlace de reset funciona**

Hacer clic en el botón "Restablecer contraseña" y confirmar que llega a la pantalla de nueva contraseña.
