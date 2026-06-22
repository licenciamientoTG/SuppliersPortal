# Diseño: Cotización parcial de RFQ + RFQ complementario

**Fecha:** 2026-06-22
**Estado:** Aprobado
**Archivo disparador:** `resources/views/supplier/rfq-detail.blade.php`

## Objetivo

Permitir que un proveedor envíe su cotización final aunque no tenga producto para
cotizar todas las partidas, marcando explícitamente cuáles quedan sin cotizar, y dar
al comprador una forma clara de resolver esas partidas faltantes generando un RFQ
complementario. No se genera ninguna ODC automática como parte de este cambio.

## Contexto del sistema actual

Supplier Portal en Laravel 12. Flujo RFQ:

- `rfqs`: una RFQ → una requisición + un `quotation_group` (conjunto de partidas) +
  varios proveedores (pivote `rfq_suppliers`). Ya existe el campo `supersedes_rfq_id`
  para trazabilidad entre RFQs.
- `rfq_responses`: una fila por `(rfq_id, supplier_id, requisition_item_id)`, con
  constraint único `unique_response_per_item`. Campo `status` enum
  DRAFT/SUBMITTED/SELECTED/REJECTED. **`unit_price` es NOT NULL** y hoy no hay forma de
  marcar "no cotizada".
- `quotation_groups` + `quotation_group_items` (pivote a `requisition_items`): definen
  las partidas de una RFQ.

Lado proveedor (`SupplierPortalController@saveQuotation`): el borrador descarta
partidas sin `unit_price`; el envío final exige precio en todas (front
`validateFieldsBeforeSubmit` + backend `unit_price required`, `delivery_days required`).

Lado comprador (`RfqComparisonController@index` → `rfq.comparison.index`): muestra
respuestas SUBMITTED/SELECTED/REJECTED. La adjudicación (`select`) suma todas las
respuestas SUBMITTED del proveedor → crea `QuotationSummary` → flujo de aprobación →
ODC. La ODC se genera en `QuotationApprovalController@generatePurchaseOrder`, que trae
**todas** las respuestas de `(rfq, supplier)` y crea las partidas de la orden.

## Decisiones de diseño

1. **Representación de "no cotizada":** columna booleana nueva `not_available` en
   `rfq_responses` (no inferir por valor numérico, no mezclar con el enum `status`).
2. **RFQ complementario:** modal dedicado en la vista de comparativa (no reusar el
   wizard).
3. **Definición de "partida faltante":** partidas que **ningún** proveedor de la RFQ
   cotizó (todos marcaron no disponible o no respondieron). El comprador puede
   deseleccionar antes de generar.
4. **Mínimo de partidas cotizadas en el envío del proveedor:** se permite enviar con
   **cero** partidas cotizadas (señal explícita "no puedo surtir"). No se bloquea por
   este motivo.
5. **Estado del RFQ complementario:** se crea y **envía de inmediato** (`status='SENT'`,
   `sent_at=now()`), con fecha límite elegida en el modal. (El email a proveedores
   sigue siendo un TODO en el sistema, igual que en `sendRFQ`/`createRFQs`; aquí solo se
   registra el log de notificación, consistente con esos flujos.)

## Semántica de una partida "no disponible"

Una partida marcada como no disponible se guarda como una fila real de `rfq_responses`:

- `not_available = true`
- `unit_price = 0`, `quantity = 0`, `subtotal = 0`, `iva_amount = 0`, `total = 0`
- `status` sigue el ciclo normal (DRAFT en borrador, SUBMITTED al enviar)

Esto distingue limpiamente "no cotizada" de "cotizada en $0 real" (que tendría
`not_available = false` y `unit_price = 0`).

## Cambios por componente

### 1. Modelo de datos

**Migración nueva** `..._add_not_available_to_rfq_responses_table.php`:

- Agrega `boolean('not_available')->default(false)` después de `meets_specs`.
- Default explícito `false` para que las filas existentes queden como cotizadas.
- `down()` elimina la columna.

**`app/Models/RfqResponse.php`:**

- Agregar `not_available` a `$fillable`.
- Cast `'not_available' => 'boolean'`.
- Scopes auxiliares:
  - `scopeQuoted($q)` → `where('not_available', false)`
  - `scopeNotAvailable($q)` → `where('not_available', true)`

### 2. Lado proveedor — `resources/views/supplier/rfq-detail.blade.php`

- En cada partida (`.quotation-item`), un toggle **"No puedo cotizar esta partida"**.
  Al activarlo:
  - se limpian y deshabilitan precio unitario, cantidad, IVA, días de entrega;
  - la tarjeta se atenúa visualmente y muestra un badge "No disponible";
  - se setea un input hidden `items[i][not_available] = 1` (al desactivar, `0`).
- El panel de resumen lateral muestra esas partidas como "No disponible" (no suman a
  subtotal/IVA/total) y el contador muestra "X de Y cotizadas".
- **Modal de confirmación de envío** (reusa la función `confirmAction` existente):
  cuando hay partidas no disponibles, lista cuántas y cuáles antes de permitir el
  envío. Texto: *"Estás a punto de enviar tu cotización sin incluir N partida(s):
  [lista]. El comprador será notificado de esta falta de producto. ¿Deseas continuar?"*
- `validateFieldsBeforeSubmit`: ya **no** exige precio en todas las partidas; solo en
  las que **no** están marcadas como no disponibles. No bloquea aunque todas estén
  marcadas como no disponibles (decisión 4).

### 3. Lado proveedor — backend `app/Http/Controllers/SupplierPortalController.php`

- `validateQuotationData`:
  - Agregar regla `items.*.not_available => 'nullable|boolean'`.
  - Cambiar `items.*.unit_price`, `items.*.quantity`, `items.*.iva_rate` y
    `items.*.delivery_days` a condicionales con `required_unless:items.*.not_available,1`
    (Laravel alinea el wildcard por índice). `delivery_days` mantiene además su
    condición de envío solo cuando la partida sí se cotiza.
  - El filtro de borrador (`action === 'save_draft'`) conserva una partida si tiene
    `unit_price` **o** `not_available == 1` (para que la marca persista en borrador).
- `calculateItemTotals`: si `not_available`, retornar todos los montos en 0.
- `saveQuotationItem`: persistir `not_available` y, cuando es `true`, montos en 0.
- La validación vive en backend (no solo front), evitando bypass.

### 4. Lado comprador — `resources/views/rfq/comparison/index.blade.php` + `RfqComparisonController`

**`RfqComparisonController@index`:**

- Calcular por proveedor el conteo `cotizadas / total` (partidas con respuesta
  `not_available = false` sobre el total de partidas de la RFQ).
- Calcular el conjunto de **partidas que nadie cotizó**: partidas de la RFQ para las
  que ningún proveedor tiene una respuesta con `not_available = false`.
- Pasar ambos a la vista.

**Vista `rfq.comparison.index`:**

- Badge **"Producto no disponible"** en las partidas que un proveedor marcó así,
  visualmente distinto de un `$0` real.
- Resumen por proveedor: **"X de Y partidas cotizadas"**.
- Botón **"Generar RFQ con partidas faltantes"**, visible solo si existen partidas que
  nadie cotizó.
- **Modal** del botón: lista de esas partidas (checkboxes, preseleccionadas) +
  multiselect de proveedores aprobados + fecha límite + mensaje opcional. Envía a la
  acción nueva (sección 5).

**Guardas para que la ODC nunca incluya partidas no disponibles:**

- `RfqComparisonController@select`: la suma de totales del proveedor filtra
  `where('not_available', false)`.
- `RfqComparisonController@buildSupplierDiagnostics`: contar/sumar solo respuestas con
  `not_available = false` (una respuesta no disponible no cuenta como "cotización
  enviada").
- `QuotationApprovalController@generatePurchaseOrder` (≈ línea 205): añadir
  `->where('not_available', false)` al traer `$winningResponses`, de modo que la orden
  de compra nunca incluya por error una partida marcada como no disponible.

### 5. Generación del RFQ complementario

**Nueva acción** `RfqComparisonController@generateComplementaryRfq(Request $request, Rfq $rfq)`
**+ ruta** en `routes/web.php` (grupo de comprador, p. ej.
`rfq.comparison.generate-complementary`).

Validación:

- `item_ids`: array, cada `requisition_item_id` pertenece a la requisición de la RFQ de
  origen y está entre las partidas "que nadie cotizó".
- `supplier_ids`: array min 1, cada uno existe y está aprobado.
- `response_deadline`: requerido, fecha futura.
- `message`: opcional.

Lógica (en transacción):

1. Crear un `QuotationGroup` nuevo para la requisición de origen, nombre
   "Complemento de {folio origen}", con las `requisition_items` seleccionadas
   (poblando `quotation_group_items`).
2. Crear `Rfq` con `quotation_group_id` del grupo nuevo, `requisition_id`,
   `supersedes_rfq_id = $rfq->id`, `status='SENT'`, `sent_at=now()`,
   `response_deadline`, `message`, `created_by`/`updated_by`.
3. Adjuntar proveedores al pivote `rfq_suppliers` con `invited_at`.
4. Registrar log de notificación (consistente con `sendRFQ`/`createRFQs`).

No se modifica la lógica de ODC existente más allá de la guarda de la sección 4.

## Archivos afectados

| Archivo | Cambio |
|---|---|
| `database/migrations/..._add_not_available_to_rfq_responses_table.php` | **nuevo** — columna `not_available` |
| `app/Models/RfqResponse.php` | fillable, cast, scopes `quoted`/`notAvailable` |
| `resources/views/supplier/rfq-detail.blade.php` | toggle "no disponible", JS, modal de confirmación, validación front |
| `app/Http/Controllers/SupplierPortalController.php` | validación condicional, guardado con flag |
| `resources/views/rfq/comparison/index.blade.php` | badges, resumen "X de Y", botón + modal |
| `app/Http/Controllers/RfqComparisonController.php` | datos en `index`, `generateComplementaryRfq`, guardas en `select`/`buildSupplierDiagnostics` |
| `app/Http/Controllers/QuotationApprovalController.php` | excluir `not_available` en `generatePurchaseOrder` |
| `routes/web.php` | ruta del RFQ complementario |

## UX y mensajes

- Todo texto visible para proveedor y comprador en español, claro, sin jerga técnica.
- Mantener el estilo "ligero pero claro" del resto del portal de compras (cards,
  badges, SweetAlert `confirmAction`).

## Fuera de alcance

- Envío real de correos a proveedores (sigue siendo TODO en el sistema).
- Cambios a la lógica de adjudicación/ODC más allá de la guarda `not_available`.
- Refactors no relacionados.
