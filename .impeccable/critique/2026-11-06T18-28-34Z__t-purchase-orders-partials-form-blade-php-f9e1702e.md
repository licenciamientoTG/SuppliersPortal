---
target: resources/views/direct-purchase-orders/partials/_form.blade.php
total_score: 33
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 0
target_identity: "file:C:\\Users\\alfo.ochoa\\Documents\\Desarrollo\\SuppliersPortal\\resources\\views\\direct-purchase-orders\\partials\\_form.blade.php"
target_fingerprint: "sha256:eb08a8bc644a021f6fbc9d570212690a8355d24a4fdfeb31aeff30a4e5bd4c4d"
target_path: "C:\\Users\\alfo.ochoa\\Documents\\Desarrollo\\SuppliersPortal\\resources\\views\\direct-purchase-orders\\partials\\_form.blade.php"
timestamp: 2026-11-06T18-28-34Z
slug: t-purchase-orders-partials-form-blade-php-f9e1702e
---
# Critique: formulario OCD (_form.blade.php) — 4.ª pasada

Método: revisión en un solo contexto (sin sub-agentes ni navegador); detector CLI 0 hallazgos.

## Heurísticas (33/40, Bueno)
1 Estado del sistema 4 · 2 Lenguaje 4 · 3 Control 3 · 4 Consistencia 3 · 5 Prevención 4 · 6 Reconocimiento 3 · 7 Flexibilidad 3 · 8 Estética 3 · 9 Recuperación 3 · 10 Ayuda 3

## Especificidad
El resumen (límite MXN con barra, mes de aplicación, saldo por cédula) y el saldo por partida hacen el formulario propio del dominio. Detector: 0 hallazgos.

## Prioridades
- [P2] En celular (<992px) total, límite, presupuesto y Guardar quedan al final. Fix: barra inferior fija. ($impeccable adapt)
- [P2] Errores del servidor repetidos hasta 4 veces (flash SweetAlert, alerta layout zircos:234, alerta edit.blade:23, inline). Fix: quitar duplicado y una sola línea que lleve al primer error. ($impeccable clarify)
- [P2] "Error al cargar productos" (_form:994) sin reintento. Fix: mensaje con botón Reintentar. ($impeccable harden)
- [P3] Mayúsculas mezcladas en etiquetas ("Condiciones de Pago", "Días Entrega"). ($impeccable clarify)
- [P3] No se explica que días de entrega cambian el mes de presupuesto. ($impeccable clarify)

## Personas
- Frecuente: Duplicar resuelve; faltan atajos de teclado.
- Nuevo: ayuda de orden clara; confunde la lista de errores repetida.
- Celular: tarjetas OK; total y Guardar fuera de vista.

## Menores
- Corte de tarjetas a 1200px y sticky top:90px sin verificar en pantalla.
- flash.blade.php inserta errores como HTML.
