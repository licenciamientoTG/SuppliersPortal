# Diseño: Cotización manual del comprador en nombre de un proveedor

**Fecha:** 2026-06-30
**Estado:** Aprobado
**Archivo disparador:** `resources/views/livewire/rfq/quotation-wizard.blade.php` (Paso 3) /
`resources/views/rfq/wizard-steps/step-3-suppliers.blade.php`

## Objetivo

Permitir que el **comprador** capture directamente, dentro del Paso 3 del wizard de
cotización, el precio y condiciones de un proveedor — sin que ese proveedor tenga que
entrar al portal — para los casos en que: (a) el comprador ya conoce el precio, (b)
recibió la cotización por correo/teléfono, o (c) el proveedor ni siquiera está dado de
alta en el portal. El mecanismo debe ser el mismo sin importar si el proveedor está
registrado o no, y debe priorizar la simplicidad para el comprador por encima de
flexibilidad adicional.

## Contexto del sistema actual

Supplier Portal en Laravel. Arquitectura real de RFQ (confirmada en código, no inferida):

- `rfqs`: **un `Rfq` por grupo de cotización** (`quotation_group_id`) o por partida
  individual (`requisition_item_id`). `status` enum
  DRAFT/SENT/RECEIVED/EVALUATED/COMPLETED/CANCELLED/REJECTED. Campo `source`
  (`portal`/`external`) ya existe pero es a nivel de RFQ completa, no por proveedor.
- `rfq_suppliers` (pivote `Rfq` ↔ `Supplier`, M:M): `invited_at`, `responded_at`,
  `quotation_pdf_path`, `notes`. **El estado de avance por proveedor vive aquí**, no en
  `rfqs.status`.
- `rfq_responses`: una fila por `(rfq_id, supplier_id, requisition_item_id)`. Campos
  relevantes: `unit_price`, `quantity`, `iva_rate`, `currency`, `delivery_days`,
  `payment_terms`, `warranty_terms`, `brand`, `model`, `specifications`, `notes`,
  `attachment_path`, `not_available`, `status` (DRAFT/SUBMITTED/SELECTED/REJECTED),
  `submitted_at`, `quotation_date`, `validity_days`.
- `suppliers`: `company_name`, `rfc` (único), `email` (único, **no nulo** hoy),
  `password` (**no nulo** hoy), `postal_code`, `contact_person`, `tax_regimes`,
  `approval_status`, `is_active`. `scopeApproved()` exige `approval_status='approved'
  AND is_active=true` ([Supplier.php:239-244](file:///home/a8a/Documentos/Desarrollo/SuppliersPortal/app/Models/Supplier.php#L239)).
  El selector de proveedores del Paso 3 usa exactamente este scope
  ([step-3-suppliers.blade.php:129](file:///home/a8a/Documentos/Desarrollo/SuppliersPortal/resources/views/rfq/wizard-steps/step-3-suppliers.blade.php#L129)).

**Cómo responde hoy un proveedor** (`SupplierPortalController.php`):

- Al enviar (`action='submit'`), se llama:
  - `updateRfqPivot($rfq, $supplierId)` ([:267-273](file:///home/a8a/Documentos/Desarrollo/SuppliersPortal/app/Http/Controllers/SupplierPortalController.php#L267)) → marca `responded_at = now()` en el pivote **solo de ese proveedor**.
  - `checkRfqCompletion($rfq)` ([:278-292](file:///home/a8a/Documentos/Desarrollo/SuppliersPortal/app/Http/Controllers/SupplierPortalController.php#L278)) → compara
    `suppliers()->count()` vs `suppliers()->whereNotNull('responded_at')->count()`; el
    `rfqs.status` solo pasa a `RECEIVED` cuando **todos** los proveedores invitados
    respondieron.
- Es decir: con 3 proveedores invitados, el primero en responder solo actualiza su
  propio pivote; el `Rfq.status` sigue `SENT` hasta que el último responde.

**`determineCurrentStep()`** ([QuotationWizard.php:94-99](file:///home/a8a/Documentos/Desarrollo/SuppliersPortal/app/Livewire/Rfq/QuotationWizard.php#L94)):
consulta `$this->requisition->rfqs()->whereIn('status', ['RECEIVED','EVALUATED'])->exists()`
**a nivel de toda la requisición**, no por grupo. Si **cualquier** grupo llega a
`RECEIVED`, el wizard completo salta al Paso 5, aunque otros grupos sigan sin enviar.
Esto ya puede pasar hoy con flujos 100% portal (raro), pero con cotización manual se
vuelve mucho más probable (ver Decisión 7).

**`wizardDatatable()`** ([RfqController.php:226-290](file:///home/a8a/Documentos/Desarrollo/SuppliersPortal/app/Http/Controllers/RfqController.php#L226)):
una fila por `Rfq` (grupo); la columna `status_badge` muestra directamente
`rfqs.status` ([:275-290](file:///home/a8a/Documentos/Desarrollo/SuppliersPortal/app/Http/Controllers/RfqController.php#L275)), sin lógica por proveedor.

## Decisiones de diseño

1. **Punto de entrada único:** Paso 3 del wizard. Cada tarjeta de grupo gana un botón
   `+ Cotización manual` que abre un modal de pantalla completa (overlay, sin navegar
   fuera del wizard).
2. **Mismo mecanismo para cualquier proveedor**, registrado o no — el comprador siempre
   puede capturar la cotización él mismo en vez de esperar al portal.
3. **Proveedor externo (no registrado):** registro ligero y reutilizable. Requeridos:
   `company_name` (razón social), `rfc`, `postal_code`. Opcionales: `tax_regimes`
   (régimen fiscal), `contact_person`, `email`, `phone_number`. Queda marcado
   `is_external = true`, sin acceso de login (`is_active = false`, `password = null`).
4. **Reutilizar el mecanismo de "respuesta de proveedor" ya existente** — no se crean
   modelos ni tablas nuevas. La captura manual produce exactamente los mismos efectos
   que una respuesta del portal: `rfq_suppliers.responded_at` + filas `RfqResponse`
   `status=SUBMITTED`. Esto evita lógica paralela y hace que toda regla existente
   (comparativo, adjudicación, presupuesto) funcione sin cambios.
5. **Auditoría:** `rfq_responses` gana `entry_source` (`supplier_portal` |
   `buyer_manual`) y `entered_by`, para que el comparativo del Paso 5 muestre
   transparencia de origen.
6. **Adjunto opcional** de evidencia (correo/PDF recibido), reutilizando el campo
   `attachment_path` que ya existe en `rfq_responses`.
7. **Ajuste a `determineCurrentStep()`:** debe exigir que **todos** los RFQs activos de
   la requisición estén en `RECEIVED`/`EVALUATED` antes de saltar al Paso 5 (hoy basta
   con uno). Se incluye en este diseño porque la cotización manual hace este salto
   prematuro mucho más probable (un grupo de un solo proveedor manual llega a
   `RECEIVED` al instante).
8. **Sin botón de "enviar" para proveedores manuales:** como ya quedan con
   `responded_at` y `RfqResponse` desde el momento de la captura, el Paso 4 no necesita
   excluirlos explícitamente — el mismo cálculo de completitud (`checkRfqCompletion`)
   ya deja el grupo en `RECEIVED` automáticamente si todos sus proveedores son
   manuales, y la tabla del Paso 4 ya no muestra botón de envío para RFQs que no están
   en `DRAFT`. No se requiere lógica especial de exclusión.

## Modelo de datos

### Migración 1 — `..._add_external_fields_to_suppliers_table.php`

- `is_external` boolean, default `false`.
- Alterar `email` a **nullable** (MySQL permite múltiples `NULL` en un índice único, no
  rompe el `unique()` existente).
- Alterar `password` a **nullable**.

### Migración 2 — `..._add_manual_entry_fields_to_rfq_responses_table.php`

- `entry_source` enum(`supplier_portal`, `buyer_manual`), default `supplier_portal`.
- `entered_by` `unsignedBigInteger` nullable, FK a `users`.

No se requieren cambios de esquema en `rfqs` ni `rfq_suppliers`.

## Cambios por componente

### 1. `app/Models/Supplier.php`

- `$fillable`: agregar `is_external`.
- `$casts`: `is_external => boolean`.
- Nuevo scope `scopeExternal($query)` → `where('is_external', true)`.

### 2. `app/Models/RfqResponse.php`

- `$fillable`: agregar `entry_source`, `entered_by`.
- Nueva relación `enteredBy(): BelongsTo` → `User`.

### 3. `app/Models/Rfq.php`

- Extraer la lógica de `SupplierPortalController::checkRfqCompletion()`
  ([:278-292](file:///home/a8a/Documentos/Desarrollo/SuppliersPortal/app/Http/Controllers/SupplierPortalController.php#L278))
  a un método público nuevo `refreshCompletionStatus(): void` en este modelo (mismo
  cálculo: `suppliers()->count()` vs `suppliers()->whereNotNull('responded_at')->count()`,
  `status = RECEIVED` si todos respondieron). `SupplierPortalController` pasa a llamar
  `$rfq->refreshCompletionStatus()` en vez de su copia privada, para no duplicar la
  regla entre el flujo del proveedor y el del comprador.

### 4. `app/Livewire/Rfq/QuotationWizard.php`

**Nuevas propiedades públicas:**

```php
public $showManualQuoteModal = false;
public $manualQuoteGroupId = null;
public $manualQuoteSupplierId = null; // null = crear proveedor externo nuevo
public $manualQuoteNewSupplier = [
    'company_name' => '', 'rfc' => '', 'postal_code' => '',
    'tax_regimes' => null, 'contact_person' => '', 'email' => '', 'phone_number' => '',
];
public $manualQuoteItems = []; // [requisition_item_id => [unit_price, iva_rate, currency, delivery_days, payment_terms, warranty_terms, brand, model, specifications, not_available]]
public $manualQuoteNotes = '';
public $manualQuoteQuotationDate = null;
public $manualQuoteValidityDays = null;
public $manualQuoteAttachment = null; // Livewire WithFileUploads
```

**Nuevos métodos:**

- `openManualQuoteModal($quotationGroupId)`: precarga `$manualQuoteItems` con las
  partidas del grupo (vacías) y resetea el resto del estado del modal.
- `saveManualQuote()`:
  1. Valida (mismas reglas que `SupplierPortalController::validateQuotationData`:
     `unit_price`/`iva_rate` requeridos si la partida no es `not_available`; si es
     proveedor nuevo, `company_name`/`rfc`/`postal_code` requeridos).
  2. Si `manualQuoteSupplierId` es null: busca `Supplier` por `rfc`. Si existe, agrega
     error sugiriendo seleccionarlo de la lista en vez de crear duplicado. Si no
     existe, crea el `Supplier` con `is_external=true`, `is_active=false`,
     `approval_status='approved'`, `password=null`.
  3. Busca el `Rfq` activo del grupo (`Rfq::where('requisition_id', ...)
     ->where('quotation_group_id', ...)->active()->first()`); si no existe, lo crea
     con `status='DRAFT'` (mismo patrón que `createNewRfq()`,
     [QuotationWizard.php:366-381](file:///home/a8a/Documentos/Desarrollo/SuppliersPortal/app/Livewire/Rfq/QuotationWizard.php#L366)).
  4. Adjunta/sincroniza el proveedor en `rfq_suppliers` con `invited_at=now()`,
     `responded_at=now()` (usa `syncWithoutDetaching` para no afectar a otros
     proveedores ya invitados).
  5. Crea/actualiza una fila `RfqResponse` por partida del grupo con los datos
     capturados: `status='SUBMITTED'`, `submitted_at=now()`,
     `entry_source='buyer_manual'`, `entered_by=Auth::id()`,
     `quotation_date`/`validity_days` del formulario.
  6. Llama `$rfq->refreshCompletionStatus()`.
  7. Cierra el modal, recarga `loadSuppliersData()`, flash de éxito
     ("✅ Cotización de {proveedor} capturada para el grupo {grupo}").

### 5. `determineCurrentStep()` (mismo archivo, [:94-99](file:///home/a8a/Documentos/Desarrollo/SuppliersPortal/app/Livewire/Rfq/QuotationWizard.php#L94))

Cambiar de "existe alguna RFQ activa en RECEIVED/EVALUATED" a "**todas** las RFQs
activas de la requisición están en RECEIVED/EVALUATED":

```php
$activeRfqs = $this->requisition->rfqs()->active()->get(); // scope ya existente, Rfq.php:383
if ($activeRfqs->isNotEmpty() && $activeRfqs->every(fn ($r) => in_array($r->status, ['RECEIVED', 'EVALUATED']))) {
    return 5;
}
```

### 6. `resources/views/rfq/wizard-steps/step-3-suppliers.blade.php`

- Dentro del `@foreach` de grupos, junto al selector de proveedores: botón
  `+ Cotización manual` (`wire:click="openManualQuoteModal({{ $group->id }})"`).
- Debajo del Select2, listar los proveedores ya capturados manualmente para ese grupo
  con badge `✅ Cotización capturada — {{ $supplier->company_name }}`
  (`{{ $supplier->is_external ? 'Proveedor externo' : '' }}`), distinto del badge
  `⏳ Pendiente de enviar` de los proveedores de portal seleccionados.
- Modal nuevo `modal-fullscreen`, incluido una vez al final de la vista, controlado por
  `$showManualQuoteModal`, con:
  1. Buscador de proveedor existente (registrados + externos ya creados) o
     `+ Nuevo proveedor externo` (form corto inline: razón social, RFC, código postal,
     régimen fiscal/contacto/email/teléfono opcionales).
  2. Tabla con una fila por partida del grupo: precio unitario, IVA, moneda, días de
     entrega, condiciones de pago, garantía, marca/modelo/especificaciones
     (opcionales), checkbox "No disponible".
  3. Fecha de cotización, vigencia (días), notas generales, adjunto opcional.
  4. Botón Guardar (`wire:click="saveManualQuote"`, `wire:loading.attr="disabled"`).

### 7. `app/Http/Controllers/RfqController.php::wizardDatatable()`

Sin cambios de lógica: una vez que `refreshCompletionStatus()` deja el `Rfq` en
`RECEIVED`, la tabla ya lo muestra con el badge "Con Respuestas"
([:280](file:///home/a8a/Documentos/Desarrollo/SuppliersPortal/app/Http/Controllers/RfqController.php#L280)) automáticamente. Confirmar en la fase de
implementación que la columna `action` ya condiciona el botón de envío solo a
`status='DRAFT'` (no se vio el código completo de esa columna en este diseño).

### 8. `app/Http/Controllers/RfqComparisonController.php` + `resources/views/rfq/comparison/index.blade.php`

- Donde se listan las `rfqResponses`, agregar badge `📝 Capturada manualmente` cuando
  `entry_source === 'buyer_manual'`, con tooltip de quién la capturó
  (`enteredBy->name`) y fecha.
- Mostrar ícono de adjunto si `attachment_path` existe (reutilizar lo que ya hace para
  proveedores de portal).
- Sin cambios en la lógica de selección/adjudicación/presupuesto — una `RfqResponse`
  manual participa exactamente igual que una de portal.

## Validaciones

- Por partida: `unit_price` e `iva_rate` requeridos si no está marcada `not_available`
  (misma regla que `SupplierPortalController::validateQuotationData`).
- Proveedor externo nuevo: `company_name`, `rfc` (formato válido, 12-13 caracteres) y
  `postal_code` (5 dígitos) requeridos. `rfc` único en `suppliers` — si ya existe, se
  sugiere seleccionarlo en vez de fallar con error genérico.
- No se puede guardar el modal sin proveedor seleccionado/creado ni sin al menos una
  partida con datos (o marcada no disponible).

## Casos borde

- **Proveedor manual + invitación de portal simultánea:** si el proveedor ya tenía
  invitación de portal pendiente para ese grupo y el comprador decide capturarla
  manualmente, se avisa (mismo patrón de alerta que ya existe al reabrir un grupo con
  RFQ enviada) que esto sobreescribe esa invitación con la respuesta capturada.
- **Edición posterior:** reabrir el mismo modal para ese proveedor permite editar las
  filas ya guardadas — actualiza el `RfqResponse` existente, no duplica.
- **Grupo 100% manual:** su único `Rfq` pasa de `DRAFT` a `RECEIVED` en el mismo guardado
  (todos los invitados ya respondieron) — nunca aparece como pendiente de envío en el
  Paso 4.
- **Grupo mixto (manual + portal):** el `Rfq` permanece en su ciclo normal
  (`DRAFT`→`SENT`) hasta que los proveedores de portal también respondan; el proveedor
  manual ya cuenta como respondido desde el inicio.
- **Proveedor externo reutilizado:** queda disponible en el buscador para futuras
  requisiciones sin volver a capturar sus datos fiscales.
- **RFC duplicado al crear proveedor externo:** se sugiere el proveedor existente en
  vez de bloquear con error genérico de validación.

## Archivos afectados

| Archivo | Cambio |
|---|---|
| `database/migrations/..._add_external_fields_to_suppliers_table.php` | **nuevo** — `is_external`, `email`/`password` nullable |
| `database/migrations/..._add_manual_entry_fields_to_rfq_responses_table.php` | **nuevo** — `entry_source`, `entered_by` |
| `app/Models/Supplier.php` | fillable, cast, scope `external()` |
| `app/Models/RfqResponse.php` | fillable, relación `enteredBy()` |
| `app/Models/Rfq.php` | nuevo método público `refreshCompletionStatus()` |
| `app/Http/Controllers/SupplierPortalController.php` | `checkRfqCompletion()` delega en `Rfq::refreshCompletionStatus()` |
| `app/Livewire/Rfq/QuotationWizard.php` | propiedades y métodos del modal de cotización manual, ajuste a `determineCurrentStep()` |
| `resources/views/rfq/wizard-steps/step-3-suppliers.blade.php` | botón "+ Cotización manual", badges, modal `modal-fullscreen` |
| `app/Http/Controllers/RfqComparisonController.php` | datos de `entry_source`/`entered_by` para la vista |
| `resources/views/rfq/comparison/index.blade.php` | badge "Capturada manualmente", ícono de adjunto |

## UX y mensajes

- Todo texto visible para el comprador en español, claro, sin jerga técnica.
- Mantener el estilo "ligero pero claro" del resto del portal (cards, badges,
  SweetAlert para confirmaciones de sobreescritura).
- El modal de captura manual reutiliza los mismos labels/orden de campos que el
  formulario del proveedor en el portal, para que el comprador reconozca el patrón.

## Fuera de alcance

- Envío real de notificación/correo al momento de "enviar" RFQ (sigue siendo TODO
  general del sistema, no relacionado a este cambio).
- Proceso de validación documental/KYC completo para proveedores externos — si más
  adelante se quiere "promover" uno a proveedor completo, es un flujo separado no
  cubierto aquí.
- Refactors no relacionados a otros módulos.
