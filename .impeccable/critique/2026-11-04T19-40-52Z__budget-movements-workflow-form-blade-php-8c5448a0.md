---
target: formulario de movimiento presupuestal
total_score: 25
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 3
target_identity: "file:C:\\Users\\alfo.ochoa\\Documents\\Desarrollo\\SuppliersPortal\\resources\\views\\budget_movements\\workflow\\_form.blade.php"
target_fingerprint: "sha256:9870338276c87ce66377e5a38fc032fb44adfbdf5ec6e82028a7d1a8c73a82ec"
target_path: "C:\\Users\\alfo.ochoa\\Documents\\Desarrollo\\SuppliersPortal\\resources\\views\\budget_movements\\workflow\\_form.blade.php"
timestamp: 2026-11-04T19-40-52Z
slug: budget-movements-workflow-form-blade-php-8c5448a0
---
⚠️ DEGRADED: single-context (sin subagentes; navegador no usado porque la BD local no tiene datos para renderizar el formulario)

## Puntuación (Nielsen)
| # | Heurística | Puntos | Hallazgo clave |
|---|---|---|---|
| 1 | Visibilidad del estado | 3 | Vista previa de saldo y badges; no se ve a quién llega la solicitud |
| 2 | Lenguaje del usuario | 3 | "Fecha" y "Mes" conviven sin explicar la diferencia |
| 3 | Control y libertad | 2 | Cancelar descarta todo sin avisar; no hay resumen antes de enviar |
| 4 | Consistencia | 2 | Numeración 1 / Soporte / 2 / 3; el monto cambia de lugar según el tipo |
| 5 | Prevención de errores | 2 | Se puede enviar con monto mayor al disponible; tipo preseleccionado; año libre |
| 6 | Reconocer vs. recordar | 3 | Badges ayudan; el responsable del origen se carga pero no se muestra |
| 7 | Flexibilidad y eficiencia | 2 | Sin atajos; tipo de movimiento escondido en un select |
| 8 | Estética y minimalismo | 3 | Limpio; hover y logo girando sobran |
| 9 | Recuperación de errores | 3 | Errores por campo y sin pérdida de datos |
| 10 | Ayuda | 2 | Sin contador en justificación; sin explicar el flujo de aprobación |
| **Total** | | **25/40** | Aceptable |

## Problemas prioritarios
1. [P1] Se puede enviar una reducción/transferencia que excede el disponible (solo aviso amarillo). Bloquear o pedir confirmación explícita.
2. [P1] Etiquetas sin `for`/`id`: Select2, mes, monto, archivo y justificación sin nombre accesible; clic en la etiqueta no enfoca.
3. [P1] Orden y numeración: "Soporte" va antes de los datos y sin número; el monto vive en lugares distintos según el tipo. Orden propuesto: 1 Tipo → 2 Partidas → 3 Monto → 4 Justificación y soporte → Resumen.
4. [P2] Tipo de movimiento como select con valor por defecto: cambiar a tarjetas seleccionables con descripción corta.
5. [P2] Sin resumen ni ruta de aprobación antes de enviar: mostrar "Transferirás $X de A a B; lo valida <responsable> y luego Dirección General".

## Observaciones menores
- Año fiscal como número libre: select con año actual y siguiente.
- Contador de caracteres en la justificación (10–1000).
- Avisar al salir con cambios sin enviar.
- Quitar hover translateY de la vista previa y la rotación infinita del logo.
- Mostrar el nombre del archivo elegido y, al editar, los ya adjuntos.
