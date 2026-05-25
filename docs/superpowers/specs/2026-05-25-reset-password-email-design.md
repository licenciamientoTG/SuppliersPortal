# Diseño: Email HTML institucional — Recuperación de contraseña

**Fecha:** 2026-05-25
**Módulo:** Acceso base / Autenticación
**Estado:** Aprobado

---

## Problema

El correo de recuperación de contraseña usa la notificación por defecto de Laravel, que llega en inglés, sin logo ni formato institucional de TotalGas.

## Decisiones de diseño

| Decisión | Opción elegida | Razón |
|----------|---------------|-------|
| Estilo general | Institucional (opción B) | Header azul degradado, estructura corporativa clara |
| Logo | `logo_TotalGas_hor_azul.png` | Logo horizontal azul, colores reales sobre fondo blanco |
| Idioma | Español | Audiencia de proveedores en México |
| Tecnología | Custom Notification + Blade view | Más limpio y mantenible que sobrescribir vendor |

---

## Arquitectura

### Archivos a crear

1. **`app/Notifications/ResetPasswordNotification.php`**
   - Extiende `Illuminate\Auth\Notifications\ResetPassword`
   - Sobreescribe `toMail()` para retornar un `MailMessage` con la vista custom
   - Pasa el `$url` del reset al Blade template

2. **`resources/views/emails/auth/reset-password.blade.php`**
   - Template HTML con inline CSS (compatible con Gmail, Outlook, Apple Mail)
   - No usa Markdown de Laravel ni el layout `vendor/notifications/email.blade.php`

3. **Modificación en `app/Models/User.php`**
   - Sobrescribir `sendPasswordResetNotification(string $token)` para usar la nueva notificación

### Flujo de datos

```
Password::sendResetLink()
  → User::sendPasswordResetNotification($token)
    → new ResetPasswordNotification($token)
      → toMail($notifiable)
        → MailMessage con view('emails.auth.reset-password', ['url' => $url])
          → SMTP Gmail → Destinatario
```

---

## Estructura visual del email

```
┌─────────────────────────────────────────────┐
│  [Franja blanca]                            │
│     [logo_TotalGas_hor_azul.png]            │
│     Portal de Proveedores                  │
│  ━━━━━━━━━━━━━━━━ (línea azul #188ae2) ━━━  │
├─────────────────────────────────────────────┤
│  [Banda degradada azul #188ae2 → #0f5f9e]  │
│     Recuperación de contraseña             │
├─────────────────────────────────────────────┤
│  [Cuerpo blanco]                            │
│  Hola,                                      │
│  Recibimos una solicitud para restablecer   │
│  la contraseña de tu cuenta...              │
│                                             │
│       [ Restablecer contraseña ]            │
│         (botón azul #188ae2)                │
│                                             │
│  [Caja gris — fallback URL]                │
│  Si el botón no funciona, usa: https://...  │
│                                             │
│  Si no solicitaste esto, ignora este correo │
├─────────────────────────────────────────────┤
│  [Footer gris #f4f6f9]                      │
│  © 2025 TotalGas S.A. de C.V.  |  no-reply │
└─────────────────────────────────────────────┘
```

---

## Especificación del template HTML

### Colores (todos inline CSS)
- Azul principal: `#188ae2`
- Azul oscuro (degradado): `#0f5f9e`
- Texto principal: `#313a46`
- Texto secundario: `#555555`
- Texto muted: `#aaaaaa`
- Fondo email: `#eef2f7`
- Fondo footer: `#f4f6f9`
- Fondo fallback URL: `#f8f9fa`

### Logo
- **Archivo:** `public/images/logos/logo_TotalGas_hor_azul.png`
- **URL en template:** `{{ asset('images/logos/logo_TotalGas_hor_azul.png') }}`
- **Dimensiones máximas:** `max-height: 52px; max-width: 200px`
- **Fallback si no carga:** texto "TotalGas" en azul `#188ae2`

### Botón CTA
- Implementar como `<a>` con estilos inline (no `<button>`)
- Fondo `#188ae2`, texto blanco, `border-radius: 5px`, padding `12px 32px`
- El `href` recibe `{{ $url }}` desde la notificación

### Contenido de texto
- **Asunto:** `Recuperación de contraseña — Portal de Proveedores`
- **Saludo:** `Hola,`
- **Cuerpo:** Explicación de la solicitud + instrucción del botón + vigencia (60 min)
- **Fallback URL:** Mostrar el `$url` completo dentro de caja gris
- **Aviso:** Si no solicitaste esto, ignora este mensaje
- **Footer:** `© {{ date('Y') }} TotalGas S.A. de C.V.` + `no-reply@totalgas.com`

---

## Consideraciones técnicas

- **Inline CSS obligatorio:** Los clientes de email no soportan `<style>` externo ni clases CSS; todos los estilos van en atributo `style=""`.
- **Tablas para layout del footer:** Usar `<table>` para alinear columnas izquierda/derecha en el footer (compatibilidad con Outlook).
- **Ancho fijo:** `max-width: 600px` centrado con `margin: 0 auto`.
- **Imagen con fallback:** El `<img>` del logo incluye `alt="TotalGas"` y un `<div>` de texto oculto que se muestra si la imagen falla (via `onerror`).
- **La URL del token** la genera el método `createUrlUsing` heredado de `ResetPassword` o el closure registrado en `AppServiceProvider`.

---

## Archivos NO modificados

- `routes/auth.php` — sin cambios
- `app/Http/Controllers/Auth/PasswordResetLinkController.php` — sin cambios
- `config/mail.php` — sin cambios
- Ningún template de vendor publicado

---

## Prueba manual post-implementación

1. Ir a `/forgot-password`
2. Ingresar email de un usuario existente
3. Revisar bandeja de entrada — el email debe llegar en español con el logo y formato institucional
4. Verificar que el botón "Restablecer contraseña" redirige correctamente al flujo de reset
