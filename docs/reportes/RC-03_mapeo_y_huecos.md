# RC-03 · Órdenes de compra abiertas y backlog por recibir — Plan (Paso 0)

Fecha: 2026-10-19. Este documento cumple la entrega 1 (PLAN, sin código) del prompt de RC-03.
Los nombres de tabla del prompt original eran SUPUESTOS; aquí se fija a qué corresponde cada
uno en el esquema real.

## 1. Tabla de mapeo

| Campo del prompt | Tabla.campo real | Estado |
|---|---|---|
| po_folio / po_date | `purchase_orders.folio` / `.issued_at` (OC) · `odc_direct_purchase_orders.folio` / `.issued_at` (OCD) | Existe |
| supplier_name | `suppliers.company_name` | Existe |
| po_line / item_description | `purchase_order_items.description` (OC) · `odc_direct_purchase_order_items.description` (OCD) | Existe |
| budget_line (centro + cuenta + subcuenta) | OC: `purchase_order_items.requisition_item_id` → `requisition_items.cost_center_id/expense_category_id/budget_cedula_id`. OCD: están **directo** en `odc_direct_purchase_order_items` | Existe, pero con rutas distintas OC vs OCD |
| ordered_qty | `quantity` en ambos items | Existe |
| received_qty | `quantity_received` en ambos items — **ya es un contador acumulado**, no hay que sumar `reception_items` a mano | Existe |
| pending_qty | `quantity − quantity_received` — ya existe como accessor (`getQuantityPendingAttribute()`) en ambos modelos | Existe |
| unit_price | `unit_price` en ambos items | Existe |
| pending_amount (sin IVA) | `unit_price × quantity_pending`, usando la base `subtotal` (ver §4, decisión importante) | Existe, con una decisión de criterio pendiente |
| promised_date | **No hay columna dedicada.** Derivable: `issued_at + estimated_delivery_days` (columna que YA existe en la cabecera de OC y OCD) — nulo si falta `issued_at` o `estimated_delivery_days` | Parcial (derivable, no se simula si falta el insumo) |
| days_late | Calculado desde `promised_date` derivado | Parcial (depende de lo anterior) |
| po_valid_to / vigencia | **No existe** como concepto de negocio separado. Lo más cercano: `PurchaseOrder::getAutoCloseDeadline()` = `issued_at + 10 días` (`INACTIVITY_DAYS`), ya usado por el cierre automático | NO EXISTE tal cual lo pide el prompt — ver pregunta abierta 1 |
| is_expired | Calculable si se resuelve `po_valid_to` | Depende de la pregunta abierta 1 |
| open_po_max_amount / consumed / balance / consumed_pct | **NO EXISTE NADA.** No hay ningún concepto de "orden de compra abierta" (monto máximo consumible en varias entregas, sin cantidad fija) en todo el código — toda OC/OCD aquí es por partidas con cantidad fija | **NO EXISTE** — requiere construir el control primero (ver §5) |
| is_zombie | Calculable con datos existentes (ver §4) | Existe la base; falta decidir el criterio exacto |

## 2. Hallazgos importantes que cambian el alcance

### 2.1 El cierre automático por inactividad NO cubre las OC "zombi" que este reporte busca

`app/Console/Commands/CloseInactivePurchaseOrders.php` cierra automáticamente OC con
`status = 'ISSUED'` sin actividad en `INACTIVITY_DAYS` (10 días) — pero **filtra exactamente por
ese estatus**. En cuanto una OC recibe aunque sea una entrega parcial, su estatus cambia a
`PARTIALLY_RECEIVED` y **sale para siempre del alcance de ese cierre automático**, sin importar
cuánto tiempo lleve detenida después de esa última entrega parcial. Es decir: hoy el sistema ya
cierra las OC completamente abandonadas (0 % recibido), pero **no tiene ningún control para las
que se quedaron a medias** — justo el hueco que RC-03 debe exponer. Esto confirma que el reporte
sí aporta valor real y no es redundante con la automatización existente.

### 2.2 Discrepancia entre lo que Contraloría confirmó por escrito y lo que el código hace (IVA)

`docs/reportes/RP-01_mapeo_y_huecos.md` (pregunta 4, **confirmada por Contraloría el 2026-10-09**)
dice: *"¿El presupuesto se controla sin IVA? El prompt supone que sí, pero hoy se compromete el
`total` de la partida (con IVA). SUPUESTO: se respeta lo que hace el portal (con IVA)."*

Verificado directamente en `App\Services\BudgetAllocationService::getOrderBudgetLines()`
(líneas ~816-905): el compromiso presupuestal se construye con **`$item->subtotal`** (sin IVA),
tanto para OC regulares como para OCD, y el avance de consumo (`receptionSourceItems()`) también
usa `subtotal`. **El código nunca ha comprometido con IVA** — la respuesta que Contraloría
confirmó por escrito no coincide con lo que el sistema realmente hace desde antes de esa
confirmación.

Esto importa directamente para RC-03 porque el criterio de aceptación pide que
`Σ pending_amount_mxn = Σ committed de RP-01` al centavo. Esa identidad **solo es alcanzable si
RC-03 calcula su importe pendiente también sobre `subtotal` (sin IVA)**. Por regla 8 del prompt
(no corregir en silencio), no estoy tocando `BudgetAllocationService`; solo lo reporto como
hallazgo — ver pregunta abierta 2.

### 2.3 Sin conversión de moneda en ningún punto de Compras o Presupuesto

`currency` existe en la cabecera de OC/OCD (default `MXN`) pero **no en los items**, y no hay
ningún servicio que convierta a MXN usando `exchange_rates` (la tabla y el sync existen, pero
nada en Compras ni Presupuesto los usa — `BudgetAllocationService` toma `subtotal` tal cual, sin
tocar `currency`). Si hoy existe alguna OC en moneda distinta a MXN, su compromiso presupuestal
ya está "mal convertido" (en realidad, no convertido) desde antes de este reporte — ver pregunta
abierta 3.

## 3. Estado de los prerrequisitos del Paso 0

| Prerrequisito | Estado | Impacto |
|---|---|---|
| Cantidad recibida por renglón de OC | Existe (`quantity_received`, acumulado directo en el item, no hay que sumar recepciones) | — |
| Fecha prometida por renglón o por OC | Parcial (derivable de `issued_at + estimated_delivery_days`, nulo si falta el insumo) | "Días de atraso" solo se calcula para OC con esos dos datos; el resto se marca "Sin fecha prometida", no se simula |
| Para OC abiertas: monto máximo y consumo acumulado | **No existe** | Esa sección del reporte no se puede entregar sin construir el concepto de OC abierta desde cero |
| Función de posición presupuestal de RP-01 | Existe (`BudgetPositionService`), pero construida sobre `subtotal` (sin IVA) | RC-03 debe usar el mismo criterio para poder cuadrar |

## 4. Qué se puede entregar hoy vs. qué requiere construir el control primero

**Se puede entregar hoy** (con datos reales, sin simular nada):
- Tabla de renglones de OC y OCD con saldo pendiente de recibir (cantidad y monto, sin IVA).
- Fecha prometida y días de atraso, solo para órdenes con `issued_at` y `estimated_delivery_days`
  capturados; el resto se muestra como "Sin fecha prometida" (no como atraso 0).
- Marca de "OC zombi": sin actividad de recepción reciente y con saldo pendiente — llena
  exactamente el hueco que el cierre automático no cubre (§2.1).
- Subtotales por proveedor y empresa, filtros, exportación, permisos, alcance por empresa.
- Conciliación automática contra el cubo Comprometido de RP-01 (usando el mismo criterio sin IVA).

**Requiere decisión o control nuevo antes de poder entregarse:**
- Toda la sección de "OC abiertas" (monto máximo/consumido/saldo/% de agotamiento) — no existe el
  concepto en el sistema. Ver pregunta abierta 4: puede que simplemente no aplique a cómo compra
  TotalGas hoy, y haya que **quitar esa sección del reporte**, no construirla.
- "Vigencia de la OC" / `is_expired` tal como lo pide el prompt (concepto de negocio separado del
  cierre automático) — propongo mapearlo al mismo plazo que ya usa el sistema (`issued_at + 10
  días`) en vez de inventar un campo nuevo; ver pregunta abierta 1.

## 5. Arquitectura propuesta

Mismo patrón que RP-01 (`app/Reports/Budget/`) y RC-01 (`app/Reports/Purchasing/`), este va en
`app/Reports/Purchasing/` junto a RC-01.

- **Sin migraciones nuevas** (todo lo que se entrega hoy sale de columnas que ya existen).
- `App\Reports\Purchasing\OpenPurchaseOrderBacklogReport` — construye las filas (una por renglón
  de OC/OCD con `quantity_pending > 0`), subtotales, KPIs (importe pendiente total, # atrasados,
  # zombis, presupuesto atrapado en zombis), y el método de conciliación contra
  `BudgetPositionService` para la prueba de cuadre.
- `App\Reports\Purchasing\OpenPurchaseOrderBacklogExport` — Excel/CSV, mismo patrón que
  `BudgetVsActualExport`/`RequisitionPipelineExport` (usa `App\Reports\Support\ReportSheet`).
- `App\Http\Requests\OpenPurchaseOrderBacklogReportRequest` — valida `as_of_date`, `company_ids`,
  `supplier_id`, `min_age_days`, `only_expired`, `zombie_days`.
- `App\Http\Controllers\OpenPurchaseOrderBacklogReportController` — `index`/`data`/`export`,
  mismo patrón que los otros dos reportes.
- Permisos nuevos: `reportes.open_po_backlog.ver` y `.exportar` (bloque tinker aditivo en
  producción, igual que los demás — no correr el seeder completo).
- Rutas: `GET /reportes/compras/oc-abiertas` (+ `/data`, `/export/{format}`).
- Vista: `resources/views/reports/open-po-backlog/index.blade.php`, mismo estilo DataTables +
  filtros que RC-01/RP-01.
- **Sin job de cola**: incluso con un año completo de operación, el volumen esperado de renglones
  de OC con saldo pendiente está muy por debajo de 50,000 filas (fuera de alcance explícito del
  prompt), así que Excel/CSV se generan igual que los otros dos reportes (en memoria, streaming).

## 6. SQL propuesto (borrador, antes de encapsular en el servicio)

Un `UNION ALL` entre OC regular y OCD, ya filtrado a renglones con saldo:

```sql
-- OC regulares
SELECT
    'OC' AS tipo,
    po.folio, po.issued_at AS fecha, s.company_name AS proveedor,
    poi.description AS renglon,
    ri.cost_center_id, ri.expense_category_id, ri.budget_cedula_id,
    poi.quantity AS cantidad_ordenada,
    poi.quantity_received AS cantidad_recibida,
    (poi.quantity - poi.quantity_received) AS cantidad_pendiente,
    poi.unit_price,
    ROUND(poi.unit_price * (poi.quantity - poi.quantity_received), 2) AS importe_pendiente, -- sin IVA
    po.issued_at, po.estimated_delivery_days
FROM purchase_order_items poi
JOIN purchase_orders po ON po.id = poi.purchase_order_id
JOIN suppliers s ON s.id = po.supplier_id
LEFT JOIN requisition_items ri ON ri.id = poi.requisition_item_id
WHERE po.status NOT IN ('CANCELLED', 'REJECTED', 'CLOSED_BY_INACTIVITY', 'PENDING_APPROVAL')
  AND po.deleted_at IS NULL
  AND (poi.quantity - poi.quantity_received) > 0

UNION ALL

-- OC directas (OCD)
SELECT
    'OCD' AS tipo,
    dpo.folio, dpo.issued_at AS fecha, s.company_name AS proveedor,
    dpoi.description AS renglon,
    dpoi.cost_center_id, dpoi.expense_category_id, dpoi.budget_cedula_id,
    dpoi.quantity, dpoi.quantity_received,
    (dpoi.quantity - dpoi.quantity_received),
    dpoi.unit_price,
    ROUND(dpoi.unit_price * (dpoi.quantity - dpoi.quantity_received), 2),
    dpoi.issued_at, dpoi.estimated_delivery_days
FROM odc_direct_purchase_order_items dpoi
JOIN odc_direct_purchase_orders dpo ON dpo.id = dpoi.direct_purchase_order_id
JOIN suppliers s ON s.id = dpo.supplier_id
WHERE dpo.status NOT IN ('CANCELLED', 'REJECTED', 'RETURNED', 'CLOSED_BY_INACTIVITY', 'DRAFT', 'PENDING_APPROVAL')
  AND dpo.deleted_at IS NULL
  AND (dpoi.quantity - dpoi.quantity_received) > 0
```

`days_late` y `is_expired` se calculan en PHP sobre `issued_at + estimated_delivery_days`
(nulo si falta cualquiera de los dos), no en el SQL, para no repetir la lógica. "Zombi" se decide
igual: última fecha de recepción del renglón (`MAX(reception_items.created_at)` por línea, vía la
relación polimórfica) comparada contra `zombie_days` — si nunca recibió nada, se usa `issued_at`.

La implementación real en el servicio reutilizará Eloquent (como RP-01/RC-01), no SQL crudo; este
borrador es solo para validar que la identidad de cuadre es alcanzable antes de programar.

## 7. Preguntas abiertas (necesito tu respuesta o la de Contraloría/Compras antes de programar)

1. **Vigencia de la OC (`po_valid_to`/`is_expired`):** ¿es lo mismo que el plazo de cierre
   automático (10 días desde `issued_at`), o es un concepto de negocio distinto que hoy no existe?
   SUPUESTO si no hay respuesta: es lo mismo (reuso `getAutoCloseDeadline()`, no invento un campo).
2. **IVA en el compromiso presupuestal (§2.2):** el código siempre ha comprometido sin IVA,
   aunque Contraloría confirmó por escrito "con IVA" en RP-01. ¿Confirman que en realidad siempre
   fue sin IVA (y hay que corregir esa nota), o el sistema tiene un bug que debería ir a
   Contraloría para decidir si se corrige (fuera del alcance de RC-03)? SUPUESTO si no hay
   respuesta: RC-03 usa sin IVA (así cuadra con RP-01 hoy); dejo la discrepancia documentada, sin
   tocar `BudgetAllocationService`.
3. **Moneda distinta a MXN (§2.3):** ¿existen en la práctica OC/OCD en moneda distinta a MXN?
   SUPUESTO si no hay respuesta: no, se ignora tipo de cambio por completo (igual que ya hace el
   presupuesto hoy).
4. **OC abiertas:** ¿Compras realmente emite órdenes de compra de monto máximo consumible en
   varias entregas (sin cantidad fija), o esa sección del prompt no aplica a cómo compra TotalGas
   hoy? SUPUESTO si no hay respuesta: no aplica, se quita esa sección del reporte y queda
   documentada como fuera de alcance (no se simula un control que no existe).
5. **Días de gracia antes de marcar atraso:** el prompt ya trae el supuesto (0 días) — lo acepto
   salvo que digas lo contrario.
6. **Umbral de "zombi" (`zombie_days`, default 90 según el prompt):** ¿90 días sin recepción te
   parece razonable para este negocio, o lo bajamos/subimos? Dato de contexto: el cierre
   automático normal ya actúa a los 10 días para OC sin ninguna actividad.
