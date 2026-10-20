# RC-02 · Órdenes de compra emitidas — Plan (Paso 0)

Fecha: 2026-10-19, actualizado 2026-10-20. Este documento cumple la entrega 1 (PLAN, sin código)
del prompt de RC-02. Los nombres de tabla/campo del prompt original eran SUPUESTOS; aquí se fija
a qué corresponde cada uno en el esquema real.

**Actualización 2026-10-20:** entre el Paso 0 original y hoy se construyeron dos features que
cierran 2 de los huecos documentados abajo — tipo de cambio en Compras (commit `02264af`) y acuse
automático del proveedor (commit `a6c4fc3`), ambos en `main` y ya desplegados en producción. El
resto del documento (incluyendo §1, §2, §4 y §7) se deja igual como registro histórico de lo que
se encontró en el Paso 0, y se agregan notas "**Resuelto 2026-10-20**" en cada punto que cambió,
en vez de reescribir el documento completo.

## 1. Tabla de mapeo

| Campo del prompt | Tabla.campo real | Estado |
|---|---|---|
| po_folio | `purchase_orders.folio` (OC) · `odc_direct_purchase_orders.folio` (OCD) | Existe |
| company_name | OC: `requisitions.company_id` (vía `purchase_orders.requisition_id`). OCD: no hay `company_id` en la cabecera; se deriva del centro de costo de sus renglones (`DirectPurchaseOrder::primaryCompanyId()`, ya existe y se reutiliza) | Existe, rutas distintas OC vs OCD |
| supplier_name / supplier_rfc | `suppliers.company_name` / `suppliers.rfc` | Existe |
| origin (REQUISICION/DIRECTA/CONTRATO) | OCD → tabla `odc_direct_purchase_orders` = **DIRECTA**. OC → `purchase_orders.source_type`: `'contract'` = **CONTRATO**, cualquier otro valor (incluido `null`) = **REQUISICION** (default del flujo normal cotización→OC) | Existe |
| po_type (CERRADA/ABIERTA) | **No existe.** Ya se confirmó en RC-03 (y lo reconfirma el usuario aquí): el negocio de TotalGas no tiene el concepto de "OC abierta" (monto máximo consumible en varias entregas). Toda OC/OCD es por partidas de cantidad fija | **NO EXISTE** — mismo hallazgo que RC-03 |
| currency / exchange_rate | `currency` existe en ambas cabeceras (default MXN). **Resuelto 2026-10-20:** OC: `purchase_orders.subtotal/total` ya vienen convertidos a MXN desde `QuotationSummary` (vía `ExchangeRate::convertToMxn()` sobre cada `RfqResponse`, commit `02264af`) — `currency` en OC es solo informativo de la cotización ganadora. OCD: `odc_direct_purchase_orders.exchange_rate` existe (capturado una vez al crear, `1.0` si `currency='MXN'`), los montos de cabecera quedan en moneda original | **Existe** (ver §2.1) |
| subtotal | `purchase_orders.subtotal` / `odc_direct_purchase_orders.subtotal` | Existe |
| transferred_taxes | `iva_amount` en ambas cabeceras | Existe (solo IVA) |
| withholdings (retenciones) | **No existe.** Ningún modelo de Compras ni de Facturación registra retenciones; es un hallazgo ya documentado en el backlog general (`FAC-01`: el parser de CFDI tampoco las extrae) | **NO EXISTE** |
| total | `purchase_orders.total` / `odc_direct_purchase_orders.total` (ya es subtotal + iva_amount, sin retenciones porque no existen) | Existe |
| total_mxn | **Resuelto 2026-10-20:** OC → igual a `total` (ya es MXN). OCD → `DirectPurchaseOrder::convertItemAmountToMxn($total)` = `total × exchange_rate` | **Existe** (derivable, sin columna nueva) |
| cost_center / budget_line (cabecera) | OC: vía `requisition_items` de sus renglones (`purchase_order_items.requisition_item_id`). OCD: directo en cada renglón (`odc_direct_purchase_order_items.cost_center_id`). Si hay más de un centro/cuenta entre los renglones de la misma orden, se muestra "Varios" (regla del prompt, no inventada) | Existe, agregado por orden |
| buyer_name | `created_by` en ambas cabeceras → `User` (relación `creator()`, ya existe en ambos modelos) | Existe |
| status | `status` en ambas cabeceras; `getStatusLabel()`/`getStatusBadgeClass()` ya existen en ambos modelos — se reutilizan, no se duplica el mapeo estatus→etiqueta | Existe |
| sent_at / supplier_ack_at / hours_to_ack | **Resuelto 2026-10-20:** `sent_at` = `issued_at` (ya existía). `supplier_ack_at` = `supplier_acknowledged_at` (columna nueva, commit `a6c4fc3`; se marca sola la primera vez que el proveedor ve la orden en el portal, vía `markAcknowledgedBySupplier()` en ambos modelos). `hours_to_ack` = diferencia en horas entre ambas, `null` si aún no hay acuse. Órdenes emitidas antes del `a6c4fc3` quedan con `supplier_ack_at = null` para siempre (no se simula retroactivo) | **Existe** (derivable, sin columna nueva adicional) |
| valid_from / valid_to | Mismo hallazgo que RC-03: no existe como campo de negocio separado. Lo más cercano es `getAutoCloseDeadline()` propio de cada tipo (`issued_at`+10 días en OC, `submitted_at`+7 días en OCD), ya reutilizado en RC-03 | Parcial (derivable, no se inventa un campo nuevo) |
| direct_expense_type | **No existe.** `odc_direct_purchase_order_items` tiene `expense_category_id` (la cuenta de gasto normal del catálogo presupuestal), pero no hay ningún catálogo separado de "tipos de gasto autorizados para compra directa" | **NO EXISTE** — coincide con el hallazgo abierto `OCD-01` del backlog general |
| enabling_reference | OC por contrato: `requisition_items.contract_id` (vía el mismo join que cost_center), ya capturado por `ContractRequisitionForm`/`ContractPurchaseOrderService`. **OCD: no existe ninguna liga a contrato o autorización** — `DirectPurchaseOrder` solo tiene `justification` (texto libre, no estructurado) | Existe solo para OC por contrato; NO EXISTE para OCD |

## 2. Hallazgos importantes que cambian el alcance

### 2.1 Sin tipo de cambio ni retenciones en ningún lugar del sistema

Hallazgo original del Paso 0 (2026-10-19), antes de construir el feature de tipo de cambio.

Ya documentado en RC-03 (§2.3) para el tipo de cambio; aquí se confirma que tampoco existen
retenciones en ningún modelo de Compras ni de Facturación (coincide con el hallazgo abierto
`FAC-01` del backlog general: el parser de CFDI tampoco las extrae). **No se puede entregar
`total_mxn` ni `withholdings` hoy.** El reporte se entrega en moneda de origen (`currency`,
`subtotal`, `transferred_taxes` = `iva_amount`, `total`), con una columna `currency` visible en
cada fila para que quien lea el reporte sepa que no todo está en la misma moneda si algún día
existieran OC en moneda distinta a MXN (hoy, por el hallazgo de RC-03 §2.3, se asume que no).

**Resuelto 2026-10-20 (tipo de cambio):** ya existe (commit `02264af`, ver §1). El reporte SÍ
puede entregar `total_mxn`: para OC es el mismo `total` (la conversión ya ocurrió al adjudicar la
cotización); para OCD es `total × exchange_rate` capturado en cabecera. **`withholdings` sigue sin
existir** — se mantiene fuera de alcance, es `FAC-01`, no cambia con este resuelto.

### 2.2 La compra directa (OCD) no tiene catálogo de causales ni liga a contrato/autorización

Esto es exactamente el hallazgo abierto `OCD-01` del backlog general de Compras ("Orden de Compra
Directa sin tope de monto ni causales"), con decisión pendiente de Contadora + Dirección. El
criterio de aceptación del prompt ("Toda OC directa muestra el tipo de gasto autorizado y el
contrato o autorización que la habilitó") **no es alcanzable hoy** porque ese control no existe
en el sistema, no porque falte reportarlo. Por la regla 3 (no simular), la sección "Excepciones"
de este reporte mostrará **el 100% de las OCD como excepción** hasta que ese control se construya
— lo cual es honesto, no un error del reporte.

### 2.3 No hay acuse del proveedor

Hallazgo original del Paso 0 (2026-10-19), antes de construir el feature de acuse automático.

No existe ningún registro de que el proveedor haya visto o confirmado la OC. El proveedor solo
deja huella al subir evidencia de entrega o facturar. `sent_at`, `supplier_ack_at` y
`hours_to_ack` se omiten del reporte (no se simulan con la fecha de emisión ni con la de entrega,
que son conceptos distintos).

**Resuelto 2026-10-20:** ya existe (commit `a6c4fc3`, ver §1). `supplier_acknowledged_at` se marca
sola la primera vez que el proveedor ve la orden en el portal (listado o detalle de pendientes de
entrega). El reporte puede mostrar `sent_at`/`supplier_ack_at`/`hours_to_ack` y un KPI "% sin acuse
> 48h" sobre órdenes emitidas con más de 48 h de antigüedad y `supplier_ack_at` nulo. Órdenes
emitidas antes del `a6c4fc3` siempre tendrán `supplier_ack_at = null` (no hubo captura retroactiva,
decisión ya tomada al construir el feature) — el reporte debe filtrar el KPI de % sin acuse a
órdenes emitidas después de esa fecha para no contaminarlo con huecos históricos que nunca se van
a llenar; se deja como pregunta abierta nueva (§7.5).

### 2.4 "OC abierta" (po_type) no existe — mismo hallazgo que RC-03

Se omite la columna `po_type` del reporte por la misma razón ya confirmada en RC-03: el negocio
de TotalGas no opera con OC de monto máximo consumible en varias entregas.

## 3. Estado de los prerrequisitos del Paso 0

| Prerrequisito | Estado | Impacto |
|---|---|---|
| Origen de la OC (requisición/directa/contrato) y tipo (cerrada/abierta) | Origen: existe (derivable). Tipo cerrada/abierta: no existe el concepto | El reporte no incluye `po_type` |
| Fecha de envío al proveedor y fecha de acuse | **Resuelto 2026-10-20** — existe (`issued_at` / `supplier_acknowledged_at`) | El reporte SÍ incluye `sent_at`/`supplier_ack_at`/`hours_to_ack` y el KPI "% sin acuse > 48h" (acotado a órdenes posteriores al despliegue del feature, ver §2.3) |
| Catálogo de tipos de gasto habilitados para OC directa + referencia que la habilita | No existe para OCD (sí existe parcialmente para OC por contrato, vía `requisition_items.contract_id`) | Toda OCD aparece en "Excepciones" hasta que se construya el catálogo (decisión de negocio, no de este reporte) |
| Asignación de renglón presupuestal por renglón de OC | Existe en ambos tipos (rutas distintas, ver §1) | — |

## 4. Qué se puede entregar hoy vs. qué requiere decisión de negocio primero

**Se puede entregar hoy** (con datos reales, sin simular nada) — **actualizado 2026-10-20**:
- Universo de OC y OCD emitidas (`issued_at IS NOT NULL`), con folio, empresa, proveedor y RFC,
  origen (REQUISICION/DIRECTA/CONTRATO), moneda, subtotal, IVA, total, **`total_mxn`**, centro de
  costo/cuenta (o "Varios"), comprador, estatus y vigencia (mismo criterio que RC-03).
- % de OC directas en conteo e importe (criterio de negocio principal del reporte), con gráfico
  de tendencia mensual (últimos 12 meses) — calculado sobre `total_mxn` para que sea comparable
  entre OC/OCD en distinta moneda.
- **`sent_at` / `supplier_ack_at` / `hours_to_ack`** por orden, y KPI "% sin acuse > 48h" (acotado
  a órdenes emitidas después del despliegue del acuse automático, ver §2.3 y pregunta §7.5).
- Sección "Excepciones": toda OCD (por el hallazgo §2.2) y toda OC/OCD sin renglón presupuestal
  asignado (validación que hoy, por diseño del alta, debería devolver 0 filas — se muestra para
  comprobarlo, no se oculta).
- Detalle expandible por renglón (producto, cantidad, precio, importe).
- Subtotales por origen, por comprador y total general (en `total_mxn`); exportación Excel/CSV con
  los mismos filtros de pantalla.

**Requiere decisión de negocio antes de poder entregarse como el prompt lo pide:**
- Retenciones (`withholdings`): no existen en ningún lugar del sistema; quedan fuera de alcance
  de este reporte igual que de todo el módulo de Facturación hoy. No es una decisión de este
  reporte, es un hallazgo del sistema completo (`FAC-01`). (Sin cambio — `total_mxn` y tipo de
  cambio ya se resolvieron el 2026-10-20 y se movieron arriba.)
- Catálogo de causales/tipo de gasto para OCD y su liga a contrato/autorización: es exactamente
  `OCD-01` del backlog general, pendiente de Contadora + Dirección. Este reporte **expone** el
  hueco (vía la sección Excepciones), no lo resuelve.

## 5. Arquitectura propuesta

Mismo patrón que RC-01/RC-03 (`app/Reports/Purchasing/`), sin migraciones nuevas: todo lo que se
entrega hoy sale de columnas y relaciones ya existentes. Alcance por usuario: **corregido
2026-10-20** — no `scopeVisibleTo()` (ver §7.4), sino el mismo `visibleCostCenters()` por
`FULL_SCOPE_ROLES` que ya usa `OpenPurchaseOrderBacklogReport` (RC-03), para un solo criterio
entre los 3 reportes de Compras.

- `App\Reports\Purchasing\IssuedPurchaseOrdersReport` — construye las filas (una por OC/OCD
  emitida, con `total_mxn` y `hours_to_ack` calculados en PHP, no en SQL), subtotales por
  origen/comprador, KPIs (conteo e importe % directas sobre `total_mxn`, importe total, % sin
  acuse > 48h), serie mensual de % directas (12 meses) y la sección de excepciones.
- `App\Reports\Purchasing\IssuedPurchaseOrdersExport` — Excel/CSV, mismo patrón que
  `RequisitionPipelineExport`/`OpenPurchaseOrderBacklogExport` (usa `App\Reports\Support\ReportSheet`).
- `App\Http\Requests\IssuedPurchaseOrdersReportRequest` — valida `company_ids`, `date_from`,
  `date_to`, `origin`, `supplier_id`, `buyer_id`, `status`.
- `App\Http\Controllers\IssuedPurchaseOrdersReportController` — `index`/`data`/`export`, mismo
  patrón que los reportes anteriores.
- Permisos nuevos: `reportes.purchase_orders_issued.ver` y `.exportar` (bloque tinker aditivo en
  producción, igual que los demás — no correr el seeder completo).
- Rutas: `GET /reportes/compras/oc-emitidas` (+ `/data`, `/export/{format}`).
- Vista: `resources/views/reports/issued-purchase-orders/index.blade.php`, mismo estilo de
  filtros + tabla que RC-01/RC-03 (sin DataTables plugin, fetch+render propio).
- **Sin job de cola**: mismo razonamiento que RC-03 (volumen de un año muy por debajo de 50,000
  filas).

No se elimina ninguna columna del prompt original "en silencio": `po_type` y
`direct_expense_type` siguen sin aparecer en pantalla ni exportación por las razones de §2.2/§2.4;
`withholdings` sigue fuera por `FAC-01` (§2.1). `exchange_rate`, `total_mxn`, `sent_at`,
`supplier_ack_at` y `hours_to_ack` **sí se incluyen** desde la actualización 2026-10-20 (§2.1, §2.3).

## 6. SQL propuesto (borrador, antes de encapsular en el servicio)

```sql
-- OC regulares (REQUISICION / CONTRATO según source_type)
SELECT
    CASE WHEN po.source_type = 'contract' THEN 'CONTRATO' ELSE 'REQUISICION' END AS origin,
    po.folio, po.issued_at, po.supplier_acknowledged_at, r.company_id,
    s.company_name AS supplier_name, s.rfc,
    po.currency, po.subtotal, po.iva_amount, po.total, po.status, po.created_by,
    ri.contract_id AS enabling_contract_id
FROM purchase_orders po
JOIN requisitions r ON r.id = po.requisition_id
JOIN suppliers s ON s.id = po.supplier_id
LEFT JOIN purchase_order_items poi ON poi.purchase_order_id = po.id
LEFT JOIN requisition_items ri ON ri.id = poi.requisition_item_id
WHERE po.issued_at IS NOT NULL AND po.deleted_at IS NULL

UNION ALL

-- OC directas (siempre DIRECTA)
SELECT
    'DIRECTA' AS origin,
    dpo.folio, dpo.issued_at, dpo.supplier_acknowledged_at, NULL AS company_id,
    s.company_name, s.rfc,
    dpo.currency, dpo.subtotal, dpo.iva_amount, dpo.total, dpo.status, dpo.created_by,
    NULL AS enabling_contract_id
FROM odc_direct_purchase_orders dpo
JOIN suppliers s ON s.id = dpo.supplier_id
WHERE dpo.issued_at IS NOT NULL AND dpo.deleted_at IS NULL
```

`company_id` de OCD y el centro de costo/cuenta agregado ("Varios") se resuelven en PHP a partir
de los renglones, reutilizando Eloquent (`primaryCompanyId()`/relaciones), no en el SQL crudo —
igual que RC-03. `total_mxn` también se calcula en PHP por fila: para OC es `total` directo; para
OCD es `total × odc_direct_purchase_orders.exchange_rate` (columna ya en la cabecera, no se trae
en este SELECT por brevedad pero sí formará parte de la consulta real). `hours_to_ack` se deriva
en PHP de `issued_at`/`supplier_acknowledged_at`. Este borrador solo valida que el universo y el
% de directas son alcanzables.

## 7. Preguntas abiertas (necesito tu respuesta o la de Contraloría/Compras antes de programar)

1. ~~**Tipo de cambio / `total_mxn`:**~~ **Resuelta 2026-10-20** — ya existe el feature (commit
   `02264af`), `total_mxn` se incluye en el reporte (ver §1, §2.1).
2. ~~**Sección "Excepciones" mostrando el 100% de las OCD:**~~ **Confirmada 2026-10-21 por el
   usuario** — aviso de cabecera (no listar cada OCD individualmente como excepción). Pendiente:
   el código hoy SÍ lista cada OCD en `exceptions` (ver §1/§5); falta cambiar la vista/export para
   mostrar el aviso de cabecera en vez de listarlas una por una antes de integrar a `main`.
3. ~~**Estatus por default:**~~ **Confirmada 2026-10-21 por el usuario** — multi-select libre, sin
   excluir nada por default. Ya implementado así.
4. **Comprador (`buyer_id`) para alcance de compradores:** el prompt dice "compradores ven solo
   sus OC si así lo define la matriz de roles actual". **Corrección 2026-10-20 a la SUPUESTO
   original:** `PurchaseOrder::scopeVisibleTo()` / `DirectPurchaseOrder::scopeVisibleTo()` NO es
   el criterio correcto para este reporte — ese scope es el de las pantallas de detalle (creador o
   aprobador asignado de una orden puntual), y con él `accounting`/`general_director` no verían
   casi nada (no son creadores ni aprobadores de OC). RC-01 y RC-03 ya resolvieron exactamente este
   problema con su propio `visibleRequisitions()`/`visibleCostCenters()` por `FULL_SCOPE_ROLES`
   (`superadmin`, `general_director`, `accounting`, `buyer` ven todo acotado a `company_user`;
   jefes de departamento ven solo los centros de costo donde son responsables). RC-02 sigue el
   mismo patrón (`visibleCostCenters()` reutilizado literal de `OpenPurchaseOrderBacklogReport`, no
   `scopeVisibleTo()`), para mantener un solo criterio de alcance entre los 3 reportes de Compras.
5. ~~**(Nueva, 2026-10-20) KPI "% sin acuse > 48h":**~~ **Confirmada 2026-10-21 por el usuario**
   — corte en `ACK_TRACKING_SINCE = 2026-10-20` y umbral de 48 h, tal cual ya implementado.
