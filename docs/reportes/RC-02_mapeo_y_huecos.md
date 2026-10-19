# RC-02 · Órdenes de compra emitidas — Plan (Paso 0)

Fecha: 2026-10-19. Este documento cumple la entrega 1 (PLAN, sin código) del prompt de RC-02.
Los nombres de tabla/campo del prompt original eran SUPUESTOS; aquí se fija a qué corresponde
cada uno en el esquema real.

## 1. Tabla de mapeo

| Campo del prompt | Tabla.campo real | Estado |
|---|---|---|
| po_folio | `purchase_orders.folio` (OC) · `odc_direct_purchase_orders.folio` (OCD) | Existe |
| company_name | OC: `requisitions.company_id` (vía `purchase_orders.requisition_id`). OCD: no hay `company_id` en la cabecera; se deriva del centro de costo de sus renglones (`DirectPurchaseOrder::primaryCompanyId()`, ya existe y se reutiliza) | Existe, rutas distintas OC vs OCD |
| supplier_name / supplier_rfc | `suppliers.company_name` / `suppliers.rfc` | Existe |
| origin (REQUISICION/DIRECTA/CONTRATO) | OCD → tabla `odc_direct_purchase_orders` = **DIRECTA**. OC → `purchase_orders.source_type`: `'contract'` = **CONTRATO**, cualquier otro valor (incluido `null`) = **REQUISICION** (default del flujo normal cotización→OC) | Existe |
| po_type (CERRADA/ABIERTA) | **No existe.** Ya se confirmó en RC-03 (y lo reconfirma el usuario aquí): el negocio de TotalGas no tiene el concepto de "OC abierta" (monto máximo consumible en varias entregas). Toda OC/OCD es por partidas de cantidad fija | **NO EXISTE** — mismo hallazgo que RC-03 |
| currency / exchange_rate | `currency` existe en la cabecera (default MXN). **`exchange_rate` no existe en ningún lugar** — confirmado en RC-03 §2.3: no hay conversión de moneda en Compras ni Presupuesto | Parcial — ver hallazgo 2.1 |
| subtotal | `purchase_orders.subtotal` / `odc_direct_purchase_orders.subtotal` | Existe |
| transferred_taxes | `iva_amount` en ambas cabeceras | Existe (solo IVA) |
| withholdings (retenciones) | **No existe.** Ningún modelo de Compras ni de Facturación registra retenciones; es un hallazgo ya documentado en el backlog general (`FAC-01`: el parser de CFDI tampoco las extrae) | **NO EXISTE** |
| total | `purchase_orders.total` / `odc_direct_purchase_orders.total` (ya es subtotal + iva_amount, sin retenciones porque no existen) | Existe |
| total_mxn | **No existe** (depende de `exchange_rate`, que no existe) | **NO EXISTE** |
| cost_center / budget_line (cabecera) | OC: vía `requisition_items` de sus renglones (`purchase_order_items.requisition_item_id`). OCD: directo en cada renglón (`odc_direct_purchase_order_items.cost_center_id`). Si hay más de un centro/cuenta entre los renglones de la misma orden, se muestra "Varios" (regla del prompt, no inventada) | Existe, agregado por orden |
| buyer_name | `created_by` en ambas cabeceras → `User` (relación `creator()`, ya existe en ambos modelos) | Existe |
| status | `status` en ambas cabeceras; `getStatusLabel()`/`getStatusBadgeClass()` ya existen en ambos modelos — se reutilizan, no se duplica el mapeo estatus→etiqueta | Existe |
| sent_at / supplier_ack_at / hours_to_ack | **No existe.** No hay ningún campo ni tabla que registre que el proveedor "vio" o "acusó" la OC — el proveedor solo interactúa subiendo evidencia de entrega (`SupplierDeliveryEvidence`) o facturando, no hay paso de acuse | **NO EXISTE** |
| valid_from / valid_to | Mismo hallazgo que RC-03: no existe como campo de negocio separado. Lo más cercano es `getAutoCloseDeadline()` propio de cada tipo (`issued_at`+10 días en OC, `submitted_at`+7 días en OCD), ya reutilizado en RC-03 | Parcial (derivable, no se inventa un campo nuevo) |
| direct_expense_type | **No existe.** `odc_direct_purchase_order_items` tiene `expense_category_id` (la cuenta de gasto normal del catálogo presupuestal), pero no hay ningún catálogo separado de "tipos de gasto autorizados para compra directa" | **NO EXISTE** — coincide con el hallazgo abierto `OCD-01` del backlog general |
| enabling_reference | OC por contrato: `requisition_items.contract_id` (vía el mismo join que cost_center), ya capturado por `ContractRequisitionForm`/`ContractPurchaseOrderService`. **OCD: no existe ninguna liga a contrato o autorización** — `DirectPurchaseOrder` solo tiene `justification` (texto libre, no estructurado) | Existe solo para OC por contrato; NO EXISTE para OCD |

## 2. Hallazgos importantes que cambian el alcance

### 2.1 Sin tipo de cambio ni retenciones en ningún lugar del sistema

Ya documentado en RC-03 (§2.3) para el tipo de cambio; aquí se confirma que tampoco existen
retenciones en ningún modelo de Compras ni de Facturación (coincide con el hallazgo abierto
`FAC-01` del backlog general: el parser de CFDI tampoco las extrae). **No se puede entregar
`total_mxn` ni `withholdings` hoy.** El reporte se entrega en moneda de origen (`currency`,
`subtotal`, `transferred_taxes` = `iva_amount`, `total`), con una columna `currency` visible en
cada fila para que quien lea el reporte sepa que no todo está en la misma moneda si algún día
existieran OC en moneda distinta a MXN (hoy, por el hallazgo de RC-03 §2.3, se asume que no).

### 2.2 La compra directa (OCD) no tiene catálogo de causales ni liga a contrato/autorización

Esto es exactamente el hallazgo abierto `OCD-01` del backlog general de Compras ("Orden de Compra
Directa sin tope de monto ni causales"), con decisión pendiente de Contadora + Dirección. El
criterio de aceptación del prompt ("Toda OC directa muestra el tipo de gasto autorizado y el
contrato o autorización que la habilitó") **no es alcanzable hoy** porque ese control no existe
en el sistema, no porque falte reportarlo. Por la regla 3 (no simular), la sección "Excepciones"
de este reporte mostrará **el 100% de las OCD como excepción** hasta que ese control se construya
— lo cual es honesto, no un error del reporte.

### 2.3 No hay acuse del proveedor

No existe ningún registro de que el proveedor haya visto o confirmado la OC. El proveedor solo
deja huella al subir evidencia de entrega o facturar. `sent_at`, `supplier_ack_at` y
`hours_to_ack` se omiten del reporte (no se simulan con la fecha de emisión ni con la de entrega,
que son conceptos distintos).

### 2.4 "OC abierta" (po_type) no existe — mismo hallazgo que RC-03

Se omite la columna `po_type` del reporte por la misma razón ya confirmada en RC-03: el negocio
de TotalGas no opera con OC de monto máximo consumible en varias entregas.

## 3. Estado de los prerrequisitos del Paso 0

| Prerrequisito | Estado | Impacto |
|---|---|---|
| Origen de la OC (requisición/directa/contrato) y tipo (cerrada/abierta) | Origen: existe (derivable). Tipo cerrada/abierta: no existe el concepto | El reporte no incluye `po_type` |
| Fecha de envío al proveedor y fecha de acuse | No existe | El reporte no incluye `sent_at`/`supplier_ack_at`/`hours_to_ack` ni el KPI "% sin acuse > 48h" |
| Catálogo de tipos de gasto habilitados para OC directa + referencia que la habilita | No existe para OCD (sí existe parcialmente para OC por contrato, vía `requisition_items.contract_id`) | Toda OCD aparece en "Excepciones" hasta que se construya el catálogo (decisión de negocio, no de este reporte) |
| Asignación de renglón presupuestal por renglón de OC | Existe en ambos tipos (rutas distintas, ver §1) | — |

## 4. Qué se puede entregar hoy vs. qué requiere decisión de negocio primero

**Se puede entregar hoy** (con datos reales, sin simular nada):
- Universo de OC y OCD emitidas (`issued_at IS NOT NULL`), con folio, empresa, proveedor y RFC,
  origen (REQUISICION/DIRECTA/CONTRATO), moneda, subtotal, IVA, total, centro de costo/cuenta
  (o "Varios"), comprador, estatus y vigencia (mismo criterio que RC-03).
- % de OC directas en conteo e importe (criterio de negocio principal del reporte), con gráfico
  de tendencia mensual (últimos 12 meses).
- Sección "Excepciones": toda OCD (por el hallazgo §2.2) y toda OC/OCD sin renglón presupuestal
  asignado (validación que hoy, por diseño del alta, debería devolver 0 filas — se muestra para
  comprobarlo, no se oculta).
- Detalle expandible por renglón (producto, cantidad, precio, importe).
- Subtotales por origen, por comprador y total general; exportación Excel/CSV con los mismos
  filtros de pantalla.

**Requiere decisión de negocio antes de poder entregarse como el prompt lo pide:**
- `total_mxn` y tipo de cambio: no aplican mientras no exista conversión de moneda en el sistema
  (mismo hallazgo que RC-03 §2.3). Pregunta abierta 1.
- Retenciones (`withholdings`): no existen en ningún lugar del sistema; quedan fuera de alcance
  de este reporte igual que de todo el módulo de Facturación hoy. No es una decisión de este
  reporte, es un hallazgo del sistema completo (`FAC-01`).
- Acuse del proveedor: no existe el control; si se quiere medir "horas hasta acuse" habría que
  construir esa captura primero (fuera de alcance de este reporte).
- Catálogo de causales/tipo de gasto para OCD y su liga a contrato/autorización: es exactamente
  `OCD-01` del backlog general, pendiente de Contadora + Dirección. Este reporte **expone** el
  hueco (vía la sección Excepciones), no lo resuelve.

## 5. Arquitectura propuesta

Mismo patrón que RC-01/RC-03 (`app/Reports/Purchasing/`), sin migraciones nuevas: todo lo que se
entrega hoy sale de columnas y relaciones ya existentes (incluyendo `scopeVisibleTo()`, que ya
usa la pantalla real de OC/OCD — se reutiliza tal cual para el alcance por usuario, en vez de
reconstruirlo).

- `App\Reports\Purchasing\IssuedPurchaseOrdersReport` — construye las filas (una por OC/OCD
  emitida), subtotales por origen/comprador, KPIs (conteo e importe % directas, importe total),
  serie mensual de % directas (12 meses) y la sección de excepciones.
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

No se elimina ninguna columna del prompt original "en silencio": `po_type`, `exchange_rate`,
`total_mxn`, `withholdings`, `sent_at`, `supplier_ack_at`, `hours_to_ack` y `direct_expense_type`
simplemente no aparecen en la pantalla ni en la exportación, con la razón documentada arriba.

## 6. SQL propuesto (borrador, antes de encapsular en el servicio)

```sql
-- OC regulares (REQUISICION / CONTRATO según source_type)
SELECT
    CASE WHEN po.source_type = 'contract' THEN 'CONTRATO' ELSE 'REQUISICION' END AS origin,
    po.folio, po.issued_at, r.company_id, s.company_name AS supplier_name, s.rfc,
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
    dpo.folio, dpo.issued_at, NULL AS company_id, s.company_name, s.rfc,
    dpo.currency, dpo.subtotal, dpo.iva_amount, dpo.total, dpo.status, dpo.created_by,
    NULL AS enabling_contract_id
FROM odc_direct_purchase_orders dpo
JOIN suppliers s ON s.id = dpo.supplier_id
WHERE dpo.issued_at IS NOT NULL AND dpo.deleted_at IS NULL
```

`company_id` de OCD y el centro de costo/cuenta agregado ("Varios") se resuelven en PHP a partir
de los renglones, reutilizando Eloquent (`primaryCompanyId()`/relaciones), no en el SQL crudo —
igual que RC-03. Este borrador solo valida que el universo y el % de directas son alcanzables.

## 7. Preguntas abiertas (necesito tu respuesta o la de Contraloría/Compras antes de programar)

1. **Tipo de cambio / `total_mxn`:** mismo hallazgo que RC-03 §2.3. SUPUESTO si no hay respuesta:
   no aplica (no hay OC en moneda distinta a MXN en la práctica); se omite `total_mxn`.
2. **Sección "Excepciones" mostrando el 100% de las OCD:** ¿de acuerdo en que el reporte lo
   exponga así (honesto pero puede verse "alarmante" al no haber filtro para ocultarlo), o
   prefieres que el reporte simplemente aclare en un aviso de cabecera que esa validación no
   aplica a OCD hasta que exista el catálogo de causales, en vez de listarlas una por una como
   excepción? SUPUESTO si no hay respuesta: aviso de cabecera (no listar cada OCD individualmente
   como excepción, para no generar ruido de algo que no es un error operativo sino un hueco de
   control ya conocido y fuera del alcance de este reporte).
3. **Estatus por default:** el prompt sugiere "todos menos BORRADOR". En OC regular no existe un
   estatus "borrador" (el filtro real ya es `issued_at IS NOT NULL`); en OCD sí existe `DRAFT` pero
   queda excluido por el mismo filtro de `issued_at`. ¿De acuerdo en que el filtro de estatus en
   pantalla sea un multi-select libre (por default: todos los estatus que puede tener una orden ya
   emitida) en vez de intentar replicar un "BORRADOR" que no aplica aquí? SUPUESTO si no hay
   respuesta: sí.
4. **Comprador (`buyer_id`) para alcance de compradores:** el prompt dice "compradores ven solo
   sus OC si así lo define la matriz de roles actual". Ya reviso `PurchaseOrder::scopeVisibleTo()`
   / `DirectPurchaseOrder::scopeVisibleTo()`: con el rol `buyer` ven **todo** su alcance de
   empresas, no solo las que ellos crearon. SUPUESTO: reutilizo ese criterio tal cual (ya es la
   fuente única de verdad de la pantalla real de OC), no invento un alcance más restrictivo solo
   para este reporte.
