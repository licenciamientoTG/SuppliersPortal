# RC-04 · Modificaciones, adendas y cancelaciones de OC — Plan (Paso 0)

> **Nota sobre esta versión del documento**: la especificación original de RC-04 se
> pegó en una sesión anterior y el texto literal (tabla de columnas exacta, reglas de
> negocio palabra por palabra) no sobrevivió la compactación del contexto de esa
> conversación. Lo que se conservó es la estructura (rol y objetivo, prerrequisitos,
> columnas, filtros, reglas de negocio, formato de salida, consideraciones técnicas,
> criterios de aceptación, preguntas abiertas — mismo molde que RC-01/RC-02/RC-03) y
> el nombre del reporte: "Modificaciones, adendas y cancelaciones de OC". Este plan se
> construyó investigando directamente el código (controladores, modelos, migraciones)
> para averiguar qué eventos de modificación/cancelación existen hoy y qué tan
> auditables son. Si tienes el texto original a la mano, lo ideal es volver a pegarlo
> para verificar que la tabla de columnas de la sección 1 coincide con lo pedido;
> mientras tanto, la sección 1 es mi propuesta basada en lo que el sistema puede dar.

## 0. Resumen ejecutivo

El hallazgo que cambia todo el alcance: **una OC regular (de requisición o contrato)
no se puede editar después de creada.** `PurchaseOrderController` no tiene método
`update`/`edit`; solo existen `approve`, `reject`, `cancel`, `reactivate`. Por lo
tanto "modificación de OC" (cambio de precio, cantidad o proveedor sobre una OC ya
emitida) **no es un evento que pueda ocurrir hoy en la aplicación** para OC
regulares — no es un hueco de datos, es una capacidad que no existe en el sistema.

Para **OCD (compra directa)** sí existe un ciclo real de modificación post-emisión:
`ISSUED → RETURNED → edición → reenvío a aprobación → ISSUED` otra vez
(`DirectPurchaseOrderController::return()`/`update()`). Ese ciclo registra el
**evento** (quién devolvió, cuándo, por qué, quién reaprobó) pero **no guarda una
fotografía antes/después** de los campos que cambiaron (precio, cantidad,
proveedor, total). Ver hallazgos 2.1–2.3 para el detalle y hallazgo 2.4 sobre por
qué `budget_commitments` tampoco sirve como sustituto de esa fotografía.

Las **cancelaciones** (OC y OCD) y los **rechazos** (OC) sí están completamente
auditados hoy: quién, cuándo y el motivo en texto libre, ya en columnas dedicadas.

## 1. Tabla de mapeo (propuesta de columnas)

| # | Columna propuesta | Fuente | Estado |
|---|---|---|---|
| 1 | Tipo (OC / OCD) | `purchase_orders` / `odc_direct_purchase_orders` | ✅ Disponible |
| 2 | Folio | `folio` en ambas tablas | ✅ Disponible |
| 3 | Tipo de evento (Cancelación / Rechazo / Devolución a revisión / Reemisión tras revisión / Reactivación) | Ver hallazgo 2.1–2.4 — cada tipo tiene su propia fuente | ⚠️ Parcial, depende del evento |
| 4 | Fecha del evento | `cancelled_at` / `rejected_at` / `returned_at` / `approved_at` (en `odc_direct_purchase_order_approvals`) / n/a para reactivación | ⚠️ Parcial — reactivación no tiene columna dedicada (hallazgo 2.4) |
| 5 | Usuario responsable del evento | `cancelled_by` / `rejected_by` / `returned_by` / `approver_user_id` | ⚠️ Parcial — reactivación no lo registra |
| 6 | Motivo / comentarios | `cancellation_reason` / comentarios de rechazo (`purchase_order_approvals.comments`) / `odc_direct_purchase_order_approvals.comments` | ✅ Disponible para cancelación, rechazo y devolución a revisión |
| 7 | Proveedor | `supplier_id`/`supplier_name` en ambas tablas (valor **actual**, no histórico) | ✅ Disponible como valor actual; ❌ no hay proveedor "antes" si cambió en una edición de OCD |
| 8 | Monto total (actual) | `total` en ambas tablas | ✅ Disponible como valor actual |
| 9 | Monto total **antes** de la modificación | — | ❌ No existe (hallazgo 2.2 y 2.4) |
| 10 | Delta de monto (antes vs. después) | — | ❌ No existe — depende de la columna 9 |
| 11 | Campo(s) que cambiaron (precio / cantidad / proveedor / otro) | — | ❌ No existe — ninguna tabla guarda un diff de campos |
| 12 | Centro de costo | `requisition_items.cost_center_id` (OC) / `odc_direct_purchase_order_items.cost_center_id` (OCD) | ✅ Disponible (mismo patrón que RC-02/RC-03) |
| 13 | Comprador | `created_by` en ambas tablas | ✅ Disponible |
| 14 | Presupuesto liberado/afectado por el evento | `budget_commitments` (liberación en cancelación) | ⚠️ Parcial — confiable solo para cancelación; no para el ciclo de edición de OCD (hallazgo 2.4) |

## 2. Hallazgos importantes que cambian el alcance

### 2.1 Las OC regulares no tienen ninguna ruta de edición en la aplicación

`PurchaseOrderController` expone `cancel`, `cancelDirect`, `approve`, `reject`,
`reactivate`, `reactivateDirect`, `appendSupplierNote`, `show*`, `*Pdf`,
`datatable*` — no hay `update`/`edit` para `PurchaseOrder`. El estatus
`purchase_order_approvals.action` solo admite `APPROVED | REJECTED` (migración
`2026_07_23_160001_create_purchase_order_approvals_table.php`); no existe un
estatus `RETURNED` ni `DRAFT` para OC regulares. Una vez con `issued_at`, la única
salida de una OC es `CANCELLED` o quedar cerrada por inactividad (reactivable).

**Consecuencia para el reporte**: la fila "Modificaciones de OC (no OCD)" en el
universo de RC-04 está vacía por diseño del sistema, no por falta de datos. Si el
negocio considera que "cancelar + emitir una OC nueva" cuenta como una adenda de
facto, eso es una definición de negocio que hay que encadenar manualmente (ver
pregunta abierta §7.1) — el sistema no liga la OC cancelada con la que la
sustituye.

### 2.2 El ciclo de edición de OCD (ISSUED → RETURNED → edición → reaprobación) audita el evento, no el contenido

`DirectPurchaseOrder::canBeReturnedToRevision()` permite devolver una OCD
`ISSUED` a `RETURNED` solo si no tiene recepciones registradas
(`app/Models/DirectPurchaseOrder.php:483-486`).
`DirectPurchaseOrderController::return()` exige `comments` (motivo obligatorio),
libera el presupuesto (`releaseDirectPurchaseOrder`), y registra en
`odc_direct_purchase_order_approvals` una fila `action='RETURNED'` con
`approver_user_id`, `comments`, `approved_at`. Columnas de cabecera:
`returned_by`, `returned_at`.

Después de eso, `DirectPurchaseOrderController::update()` (el camino
`$wasReturned`) permite editar los campos de la OCD y sus partidas — proveedor,
precios, cantidades, moneda — y **sobrescribe** los valores existentes sin guardar
una copia del estado anterior en ningún lado. Al reenviarse, vuelve a
`PENDING_APPROVAL` y sigue el flujo de aprobación normal hasta `ISSUED` otra vez,
dejando una fila `action='APPROVED'` en la misma tabla de aprobaciones.

**Lo que sí se puede reconstruir hoy**: que la OCD se devolvió, quién la devolvió,
cuándo, por qué (el texto de `comments`), y cuándo volvió a quedar `ISSUED` y quién
la reaprobó — un timeline de eventos.

**Lo que no se puede reconstruir hoy**: qué cambió exactamente (precio, cantidad,
proveedor) ni cuál era el monto antes de la edición. No hay snapshot de
`odc_direct_purchase_orders`/`odc_direct_purchase_order_items` en el momento de la
devolución.

### 2.3 Cancelaciones y rechazos sí están completamente auditados, en ambos tipos de orden

La migración `2026_10_16_000001_add_cancellation_audit_to_purchase_orders.php`
agregó `cancelled_by`, `cancelled_at`, `cancellation_reason` tanto a
`purchase_orders` como a `odc_direct_purchase_orders`, y
`PurchaseOrderController::cancelOrder()` (método privado compartido por `cancel` y
`cancelDirect`) exige `reason` (mínimo 10 caracteres), libera presupuesto, y llena
esas tres columnas de forma atómica dentro de una transacción con bloqueo
(`lockForUpdate`). El rechazo de OC (`reject()`) exige `comments` (mínimo 50
caracteres) y llena `rejected_by`/`rejected_at` además de registrar la fila en
`purchase_order_approvals`. Ambos eventos son 100% confiables como fuente para
RC-04 sin construir nada nuevo.

### 2.4 `budget_commitments` no sirve como sustituto del snapshot antes/después

Investigué si el historial de `budget_commitments` podría usarse como proxy para
reconstruir el monto "antes" de una edición de OCD (cada fila tiene su propio
`committed_amount` y pasa por `COMMITTED → RELEASED` en el ciclo
devolución/reenvío). No sirve: `BudgetAllocationService::syncCommitmentTrace()`
(usado cuando una OCD vuelve a `ISSUED` tras una revisión) busca compromisos
existentes que ya calzan con la misma combinación centro de
costo/categoría/mes/cédula (`findCommitmentsForLine`) y **solo les cambia el
estatus** de `RELEASED`/`COMMITTED` — nunca actualiza `committed_amount` al nuevo
total tras la edición. Si el usuario cambió el precio o la cantidad durante la
revisión, el compromiso revivido queda con el monto **viejo**, no el nuevo.

Esto es un hallazgo colateral de integridad presupuestal (el monto comprometido
puede quedar desfasado del total real de la OCD tras una edición en revisión),
independiente de RC-04. Lo marco aquí porque descarta esa tabla como fuente para
el reporte, pero **no lo estoy arreglando como parte de este plan** — si quieres
que se registre como pendiente aparte lo agrego al backlog de Compras
(`docs/pendientes_2026-08-28.json`); ver pregunta abierta §7.4.

## 3. Estado de los prerrequisitos del Paso 0

| Prerrequisito | Estado |
|---|---|
| Identificar qué eventos de "modificación" existen para OC | ✅ Ninguno — ver 2.1 |
| Identificar qué eventos de "modificación" existen para OCD | ✅ Ciclo RETURNED→edición→reaprobación — ver 2.2 |
| Snapshot antes/después de precio/cantidad/proveedor (OCD) | ❌ No existe — requiere construir un control nuevo |
| Auditoría de cancelación (OC y OCD) | ✅ Completa — ver 2.3 |
| Auditoría de rechazo (OC) | ✅ Completa — ver 2.3 |
| Auditoría de reactivación por inactividad | ⚠️ Parcial — no hay `reactivated_by`/`reactivated_at`; solo se infiere de que `closed_at` volvió a `null` |
| Alcance por usuario/empresa/centro de costo | ✅ Reutilizable — mismo patrón `visibleCostCenters()`/`FULL_SCOPE_ROLES` que RC-01/RC-02/RC-03 |
| Conversión a MXN | ✅ Resuelto en ambas tablas (mismo mecanismo que documentó RC-02) |

## 4. Qué se puede entregar hoy vs. qué requiere construir el control primero

**Se puede entregar hoy, sin tocar el esquema** (una fila por evento, usando las
fuentes de las secciones 2.1/2.3):
- Cancelaciones de OC y OCD: folio, fecha, quién, motivo, monto liberado.
- Rechazos de OC: folio, fecha, quién, motivo.
- Devoluciones a revisión de OCD (`RETURNED`): folio, fecha, quién, motivo.
- Reemisión de esa misma OCD tras la revisión: folio, fecha de reemisión, quién
  reaprobó.
- Monto **actual** de la orden en cada fila (no el histórico).

**Requiere construir el control primero, no se puede simular** (regla 3 de la
especificación — nada de ceros o `NULL` disfrazados):
- Monto/precio/cantidad/proveedor **antes** de una edición de OCD en revisión, y
  por lo tanto el delta y "qué campo cambió". Para esto se necesitaría, como
  mínimo, un listener/observer que, en el momento exacto de `RETURNED` (antes de
  que `update()` sobrescriba nada), guarde una copia de la cabecera y las partidas
  de la OCD en una tabla nueva (p. ej. `direct_purchase_order_revisions` +
  `..._revision_items`). **Esto solo puede empezar a capturar hacia adelante**: no
  hay forma de reconstruir retroactivamente ediciones de OCD que ya ocurrieron
  antes de que este control exista — hay que reportarlo como hueco, no rellenarlo.
- Auditoría de quién/cuándo reactivó una OC/OCD cerrada por inactividad (columnas
  `reactivated_by`/`reactivated_at` no existen hoy).
- Cualquier intento de "modificación de OC regular": no aplica, no existe la
  ruta (2.1) — no es algo que se pueda construir sin antes decidir si el negocio
  quiere habilitar edición de OC regulares (fuera del alcance de un reporte).

## 5. Arquitectura propuesta

Mismo patrón que RC-01/RC-02/RC-03 para mantener consistencia:

- `app/Reports/Purchasing/PurchaseOrderAmendmentsReport.php` — construye filas de
  **eventos** (no una fila por orden): cada cancelación, cada rechazo, cada
  devolución-a-revisión y cada reemisión posterior es su propia fila, con
  `order_type`, `event_type`, `folio`, `occurred_at`, `actor_name`, `reason`,
  `total_mxn_at_event` (el valor actual de la orden, explícitamente etiquetado
  como "no histórico" en la UI/exportación si el evento no es una cancelación).
- Reutiliza `IssuedPurchaseOrdersReport::FULL_SCOPE_ROLES` y el patrón
  `visibleCostCenters()`/`visibleCompanyIds` tal cual — mismo criterio de alcance
  ya corregido en RC-02 (orphans solo visibles dentro de las empresas asignadas).
- `app/Reports/Purchasing/PurchaseOrderAmendmentsExport.php` vía
  `App\Reports\Support\ReportSheet` (cuidando el gotcha de RC-02: columnas de
  fecha necesitan callable distinto para csv vs. xlsx).
- Ruta: `GET /reportes/compras/modificaciones-oc` (+ `/data`, `/export`).
- Permisos: `reportes.po_amendments.ver` / `.exportar` (bloque `tinker` aditivo en
  producción, igual que RC-02 — nunca correr `RolePermissionSeeder` completo).
- La cabecera de la vista/export debe decir explícitamente, igual que RC-02 hizo
  con las OCD y causales: "el monto mostrado en devoluciones/reemisiones es el
  valor actual de la orden, no el valor antes del cambio — ese dato no se captura
  hoy" (hasta que, si se aprueba, se construya el snapshot de la sección 4).

## 6. SQL propuesto (borrador, antes de encapsular en el servicio)

```sql
-- Cancelaciones (OC + OCD)
SELECT 'OC' AS order_type, 'CANCELLED' AS event_type, po.folio, po.cancelled_at AS occurred_at,
       u.name AS actor_name, po.cancellation_reason AS reason, po.total AS total_at_event
FROM purchase_orders po
JOIN users u ON u.id = po.cancelled_by
WHERE po.cancelled_at IS NOT NULL
UNION ALL
SELECT 'OCD', 'CANCELLED', odc.folio, odc.cancelled_at,
       u.name, odc.cancellation_reason, odc.total
FROM odc_direct_purchase_orders odc
JOIN users u ON u.id = odc.cancelled_by
WHERE odc.cancelled_at IS NOT NULL;

-- Rechazos (solo OC regular)
SELECT 'OC' AS order_type, 'REJECTED' AS event_type, po.folio, po.rejected_at AS occurred_at,
       u.name AS actor_name, poa.comments AS reason, po.total AS total_at_event
FROM purchase_orders po
JOIN users u ON u.id = po.rejected_by
LEFT JOIN purchase_order_approvals poa
       ON poa.purchase_order_id = po.id AND poa.action = 'REJECTED'
WHERE po.rejected_at IS NOT NULL;

-- Devoluciones a revisión + reemisiones (solo OCD)
SELECT 'OCD' AS order_type, 'RETURNED_TO_REVISION' AS event_type, odc.folio,
       odca.approved_at AS occurred_at, u.name AS actor_name, odca.comments AS reason,
       odc.total AS total_at_event
FROM odc_direct_purchase_order_approvals odca
JOIN odc_direct_purchase_orders odc ON odc.id = odca.direct_purchase_order_id
JOIN users u ON u.id = odca.approver_user_id
WHERE odca.action = 'RETURNED';
```

El resto (reemisión posterior a una devolución, y el diff de campos) depende de
las decisiones de las secciones 4 y 7.

## 7. Preguntas abiertas — resueltas 2026-10-20

1. **Columnas exactas del spec original**: no se recuperó el texto; se sigue con
   la propuesta de la sección 1.
2. **"Modificación de OC regular" vía cancelación + nueva OC**: **no** se
   encadena automáticamente. Cada cancelación es su propia fila, sin inferir qué
   OC la sustituyó.
3. **Snapshot antes/después para OCD** (tabla de revisiones): **no** se construye
   por ahora. RC-04 se entrega primero solo con lo disponible hoy (cancelaciones,
   rechazos, devoluciones a revisión como evento, sin diff de campos ni delta de
   monto). Se evalúa como mejora futura.
4. **Hallazgo colateral de `budget_commitments`** (sección 2.4): se **corrige
   ahora**, como parte de este mismo trabajo — `syncCommitmentTrace()` debe
   actualizar `committed_amount` al monto nuevo cuando revive un compromiso tras
   una edición en revisión, no solo cambiar el estatus.
5. **Reactivaciones**: **fuera de alcance** de RC-04 por ahora; no hay
   quién/cuándo/por qué auditado. Se podrían incluir después si se agregan
   `reactivated_by`/`reactivated_at`.

## 8. Alcance final para el código (deliverable 2)

Con las respuestas de la sección 7, el reporte entrega una fila **por evento**,
de estos cuatro tipos únicamente:
- `CANCELLED` (OC y OCD) — fuente: columnas de cancelación (sección 2.3).
- `REJECTED` (solo OC) — fuente: columnas de rechazo + `purchase_order_approvals`
  (sección 2.3).
- `RETURNED_TO_REVISION` (solo OCD) — fuente:
  `odc_direct_purchase_order_approvals` acción `RETURNED` (sección 2.2).
- `REISSUED_AFTER_REVISION` (solo OCD) — fuente: siguiente acción `APPROVED` en
  `odc_direct_purchase_order_approvals` después de un `RETURNED` para la misma
  OCD, con `issued_at` posterior a ese `RETURNED`.

Ningún evento incluye diff de campos ni monto "antes" — solo el monto actual de
la orden, etiquetado como tal en la UI/exportación.

Además, como parte de este trabajo (pregunta 4): corregir
`BudgetAllocationService::syncCommitmentTrace()` para que, al revivir un
compromiso existente, también actualice `committed_amount` al monto vigente de
la línea en ese momento (hoy solo cambia el estatus `RELEASED`→`COMMITTED` y deja
el monto viejo). Esto se hace con TDD, igual que el resto del reporte.

**Siguiente paso**: branch `feat/rc04-purchase-order-amendments`, TDD, mismo
patrón que RC-02 (servicio + export + controller + request + rutas + vista +
permisos `reportes.po_amendments.ver`/`.exportar`), más la corrección de
`syncCommitmentTrace()` con su propia prueba de regresión.
