# RR-01 · Recepciones validadas y su evidencia — Plan (Paso 0)

Spec original: `docs/reportes/prompts_reportes_portal_proveedores.md` (REPORTE #9, líneas 1490-1671).

## 0. Resumen ejecutivo

El spec de RR-01 asume un flujo de recepción con **dos roles separados** (quien recibe y quien valida) y **evidencia obligatoria por categoría con hash verificable**. Al inspeccionar el código real, **ninguna de las dos premisas existe hoy**:

- No hay un paso de "validación" distinto de "recibir". `Reception` solo tiene `received_by`/`received_at`; no hay `validated_by`/`validated_at`. Registrar la recepción ya dispara el consumo de presupuesto y la provisión financiera — es un solo acto, no dos.
- No hay control que impida guardar una recepción sin evidencia. Hoy solo se exige foto cuando una partida es `NO_CONFORME`; una recepción 100% conforme puede guardarse sin remisión y sin fotos.
- No existe ningún hash (SHA-256 ni de ningún tipo) en ningún punto de carga de archivos de todo el repo (ni en recepciones, ni en adjuntos de requisición, ni en evidencia de entrega del proveedor).
- No existe el tipo `HITO` (hito de servicio) en ningún lugar del sistema. El tipo BIEN/SERVICIO solo es derivable para OCD (vía categoría de gasto `SER`); las OC regulares no tienen categoría de gasto en sus ítems, así que para OC el tipo sería "no determinable".

Esto no es un reporte que se pueda construir "tal cual" el spec — antes hay que decidir qué controles de negocio se construyen de verdad (versionado de evidencia con hash, por ejemplo) y qué partes del spec se reportan con su hueco explícito en vez de simularse. Las decisiones están en la sección 7; quedan pendientes de tu confirmación antes de tocar código.

## 1. Tabla de mapeo (campo requerido del spec → realidad)

| Campo del spec | Tabla.campo real | Estado |
|---|---|---|
| reception_folio | `receptions.folio` | Existe |
| po_folio | `receptions.receivable_type/receivable_id` → `purchase_orders.folio` / `odc_direct_purchase_orders.folio` (morph) | Existe |
| supplier_name | vía `receivable->supplier` | Existe |
| reception_type (BIEN/SERVICIO/HITO) | NO EXISTE como columna | **Hueco** — derivable parcial (ver §2.1) |
| reception_date | `receptions.received_at` | Existe |
| location | `receptions.receiving_location_id` → `receiving_locations` (con `company_id`, útil para alcance) | Existe |
| received_by | `receptions.received_by` → `users` | Existe |
| validated_by / validated_at | NO EXISTE | **Hueco real** (ver §2.2) |
| sod_flag | calculable: `received_by` = solicitante de la requisición de la OC/OCD | Calculable solo en esa variante; la variante "recibió = validó" no aplica porque no hay validador |
| evidence_required (catálogo por categoría) | NO EXISTE (`reception_evidence_rules`) | **Hueco** — hoy la única regla "dura" es: NO_CONFORME exige foto; nada más es obligatorio |
| evidence_count | `receptions.remission_path` (0 o 1) + `Σ reception_items.photos` (array JSON, sin metadata) | Existe pero sin estructura ni metadata por archivo |
| evidence_complete | NO EXISTE como cálculo | **Hueco** — no hay catálogo contra el cual comparar |
| po_line / quantity_received | `reception_items.quantity_received` (morph a `purchase_order_items`/`odc_direct_purchase_order_items`) | Existe |
| file_name / mime_type / file_size | NO EXISTE — `remission_path` y `photos` son solo rutas de string, sin metadata | **Hueco** |
| sha256 / hash_origin | NO EXISTE en ningún punto del repo | **Hueco real, construcción nueva** |
| uploaded_at / uploaded_by (por archivo) | NO EXISTE — implícito = `received_by`/`received_at` de la recepción (mismo acto) | **Hueco parcial** |
| hash_verified bajo demanda | NO EXISTE | **Hueco real, construcción nueva** |

## 2. Hallazgos importantes que cambian el alcance

### 2.1 El tipo BIEN/SERVICIO/HITO — CORREGIDO 2026-10-20: sí es 100% determinable para ambos tipos de orden

- Corrección a la primera versión de este plan: busqué `expense_category_id` directo en `PurchaseOrderItem` y no está ahí, pero **sí es accesible por la cadena correcta**: `PurchaseOrderItem → requisitionItem() → RequisitionItem` (que tiene `expense_category_id` y `product_service_id` propios) `→ expenseCategory()`. Cada renglón de OC viene de un renglón de requisición que ya trae su categoría de gasto.
- `DirectPurchaseOrderItem` (OCD) tiene `expense_category_id` directo en el ítem — más simple todavía.
- `ExpenseCategory::isService()` ya existe (se usa en la validación de REPSE) y aplica igual para ambos: `isService() → SERVICIO`, si no → `BIEN`.
- `HITO` (hito de servicio, entrega parcial contractual) **no existe en ningún lugar del sistema** — ni como estado, ni como concepto de negocio. No se puede derivar ni simular sin inventar datos. Se omite del catálogo de tipos; solo `BIEN`/`SERVICIO`, ambos 100% calculables, sin hueco.

### 2.2 No existe un segundo rol de "validación" — recibir y validar son el mismo acto, por el mismo usuario

- `ReceptionService::receive()` es transaccional y de un solo paso: crea la cabecera, las líneas, actualiza el estado de la orden, consume presupuesto (`BudgetAllocationService::consumeOrder`) y genera la provisión financiera (`FinancialProvisionService::createForReception`) — todo en la misma llamada, por el mismo `$receiver`.
- No hay botón "Validar", no hay endpoint, no hay columna para un segundo actor. El criterio de aceptación del spec ("el botón Validar se deshabilita si falta evidencia exigida") describe un control que no existe.
- Consecuencia para el reporte: **toda recepción registrada ES la "recepción validada"** del spec (no hay un subconjunto de recepciones sin validar que excluir) — `validated_by` = `received_by`, `validated_at` = `received_at`, siempre, con eso documentado como limitación conocida, no como dato nuevo.
- Consecuencia para el `sod_flag`: la variante "recibió = validó" sería **siempre verdadera** (no aporta nada, es trivial por construcción) — se omite. La variante que sí aporta es "quien recibió es el mismo que solicitó la requisición de esa orden" (comparando `received_by` contra `requisitions.requested_by` de la OC, o `created_by` de la OCD) — esa sí es una señal real de segregación de funciones y es la que se implementa.

### 2.3 Control de evidencia obligatoria — RESUELTO 2026-10-20, construido antes del reporte

Decisión del usuario: la evidencia obligatoria es el **archivo de remisión, por evento** (no por partida — las fotos de no conformidad por partida siguen igual que antes, sin cambios). Se avisa al usuario que sin esa evidencia no se procesará el pago al proveedor. Las recepciones ya existentes quedan exceptuadas (no se tocan datos históricos; la regla solo aplica a partir de ahora).

Implementado (rama `feat/rr01-receptions-evidence`, commit `de7acae`, con TDD):
- `ReceptionController::store()` y `::storeDirect()`: `remission_file` pasó de `nullable` a `required`, con mensaje `"Debes adjuntar el archivo de remisión. Sin esta evidencia, la recepción no podrá usarse para procesar el pago al proveedor."`.
- Vista `receptions/create.blade.php`: el campo ya no dice "(opcional)", lleva asterisco de obligatorio, atributo HTML `required`, y la validación JS de cliente (`validateRemissionFile()`) ya no deja pasar sin archivo (mismo mensaje).
- `reception_evidence_rules` (catálogo de evidencia exigida por categoría bien/servicio) **no se construyó** — el usuario decidió que la regla es una sola (remisión siempre, sin distinción por tipo), así que no hace falta un catálogo, es una validación fija.
- Pruebas nuevas en `tests/Feature/ReceptionFlowTest.php`: rechazo sin remisión y aceptación con remisión, para OC regular y para OCD (4 pruebas), TDD estricto (RED confirmado por la razón correcta, luego GREEN). Se agregó también un helper `createRegularOrder()` (no existía ninguna prueba HTTP de recepción de OC regular hasta ahora, solo de OCD).
- Suite completa verificada: mismas 36 fallas preexistentes, cero regresiones (677 passed, antes 674).

### 2.4 No existe hash en ningún punto de carga de archivos del repo — hay que construirlo desde cero

- Revisé los tres puntos de carga de archivos existentes (`RequisitionAttachment`, recepciones `remission_path`/`photos`, `SupplierDeliveryEvidence`): ninguno calcula ni almacena un hash.
- Para dar `sha256`/`hash_verified` de verdad hay que: (1) normalizar remisión + fotos en una tabla propia `reception_attachments` (hoy son columnas sueltas: una string y un JSON sin metadata), calculando SHA-256 al guardar; (2) un backfill que calcule el hash de los archivos ya existentes en disco y los marque `hash_origin = BACKFILL` (el spec ya anticipa este caso: "hash calculado a posteriori, no prueba integridad previa"); (3) un endpoint de verificación bajo demanda que relea el archivo del disco y compare.
- Esto es una migración de datos real (mover `remission_path`+`photos` a filas de una tabla nueva) — no es solo agregar una columna. Ver alcance propuesto en §4.

### 2.5 Alcance por empresa — sí hay una fuente limpia

- `receiving_locations.company_id` existe (migración `2026_07_07_000001_add_company_id_to_receiving_locations.php`). El alcance por empresa del reporte se resuelve filtrando por la empresa de `reception.receivingLocation`, igual de limpio que en RC-01/02/03/04 (que usan centros de costo). No hay hueco aquí.

### 2.6 Inmutabilidad — ya se cumple, sin necesidad de control nuevo

- No hay ninguna ruta de edición ni borrado de `Reception`, `ReceptionItem`, ni de sus archivos (`routes/web.php` solo expone `create`/`store`/`show`/`remission.download` para recepciones). El criterio "los adjuntos no tienen endpoint de edición ni borrado" ya se cumple hoy, de fábrica. Nada que construir aquí, solo documentarlo como ya-cumplido en el reporte/criterios de aceptación.

## 3. Estado de los prerrequisitos del Paso 0 (resumen)

| Prerrequisito del spec | Estado |
|---|---|
| Recepciones con receptor, validador, ubicación y tipo | Parcial: receptor y ubicación sí; validador NO existe (es el mismo receptor); tipo solo derivable para OCD |
| Adjuntos con usuario y fecha de carga; hash si no existe, con backfill | NO EXISTE — hay que construirlo (tabla nueva + SHA-256 + backfill) |
| Catálogo de evidencia exigida por categoría | NO EXISTE |

## 4. Qué se puede entregar hoy vs. qué requiere construir el control primero

**Se puede entregar hoy (sin construir nada nuevo más que el reporte):**
- Folio, OC/OCD, proveedor, fecha, ubicación (con empresa), receptor, cantidad recibida por renglón, conformidad por renglón.
- `sod_flag` real (receptor = solicitante de la requisición/creador de la OCD).
- Conteo de evidencia actual (0/1 remisión + N fotos) sin metadata de archivo.

**Requiere construir antes (decisión de alcance, ver §7):**
- Tabla `reception_attachments` (normaliza remisión + fotos, con `file_name`, `mime_type`, `file_size`, `sha256`, `hash_origin`, `uploaded_at`) + backfill de los archivos existentes en disco.
- Endpoint de verificación de hash bajo demanda (`POST /reportes/recepciones/evidencia/{attachment}/verificar-hash`).
- (Opcional, mayor alcance) Catálogo `reception_evidence_rules` + validación real en `ReceptionController` que rechace guardar sin la evidencia exigida — esto modifica el flujo de recepción existente, no solo el reporte.
- `reception_type` derivado (SERVICIO/BIEN/NO_DETERMINABLE) — trivial de calcular, no requiere migración.

**Fuera de alcance, no se simula:**
- `HITO` como tipo de recepción — no existe el concepto en el sistema.
- `validated_by` distinto de `received_by` — no existe un segundo rol; se reporta como el mismo dato, documentado.

## 5. Arquitectura propuesta (si se aprueba construir evidencia con hash)

- Migración aditiva `create_reception_attachments_table`: `reception_id` (FK), `attachable_type` (`REMISSION`/`PHOTO`), `reception_item_id` (nullable, solo para fotos), `disk`, `path`, `original_name`, `mime_type`, `size`, `sha256`, `hash_origin` (`EN_CARGA`/`BACKFILL`), `uploaded_by`, `uploaded_at`.
- Migración de datos (comando `artisan`, no en la migración de schema): lee `receptions.remission_path` y `reception_items.photos` existentes, calcula SHA-256 de cada archivo en disco, crea las filas con `hash_origin=BACKFILL`, `uploaded_by=received_by` de la recepción padre.
- `ReceptionService::receive()` se ajusta para escribir en `reception_attachments` en vez de las columnas sueltas (con `hash_origin=EN_CARGA`), calculando el hash al momento de `store()`. Las columnas viejas (`remission_path`, `photos`) se mantienen sin usar por compatibilidad de lectura histórica, o se migran — a decidir contigo (no borro datos sin autorización).
- `App\Reports\Purchasing\ReceptionsEvidenceReport` (+ Export), mismo patrón que RC-01..04: `build(User, params)` → `{rows, kpis}`, `matches()` para filtros, `isVisible()`/scope por empresa vía `receiving_locations.company_id`.
- Controlador, FormRequest, rutas `reportes/recepciones/evidencia` (+ `/data`, `/export`, `/{attachment}/verificar-hash`), vista Blade, permisos `reportes.receptions_evidence.ver`/`.exportar` — mismo patrón de siempre.

## 6. SQL propuesto (borrador de la consulta principal, antes de encapsular en el servicio)

```sql
SELECT
    r.id, r.folio AS reception_folio, r.receivable_type, r.receivable_id,
    r.received_at, r.received_by, r.notes, r.delivery_reference,
    rl.company_id, rl.name AS location_name,
    u.name AS received_by_name
FROM receptions r
JOIN receiving_locations rl ON rl.id = r.receiving_location_id
JOIN users u ON u.id = r.received_by
WHERE rl.company_id IN (:company_ids)
  AND r.received_at BETWEEN :date_from AND :date_to
ORDER BY r.received_at DESC;
```
El tipo (OC/OCD), proveedor, folio de OC y partidas se resuelven en PHP por renglón vía el `morphTo()` ya existente (igual que en RC-02/03/04), para evitar un `UNION` frágil entre dos esquemas de ítems distintos.

## 7. Preguntas abiertas — necesito tu decisión antes de escribir código

1. **Evidencia obligatoria**: ¿reporto solo la realidad de hoy (ninguna recepción conforme exige evidencia) o construyo primero el catálogo `reception_evidence_rules` + el rechazo real en el backend? Esto último cambia el flujo de recepción, no solo agrega un reporte.
2. **Hash SHA-256**: ¿construyo la tabla `reception_attachments` + backfill + verificación bajo demanda (alcance real, nuevo), o el reporte sale sin hash por ahora (solo nombre/tipo/fecha de los archivos actuales, marcados "sin hash"), y el hash se deja para después?
3. **Columnas viejas (`receptions.remission_path`, `reception_items.photos`)**: si construimos `reception_attachments`, ¿migro los datos existentes a la tabla nueva y dejo de usar las columnas viejas, o las dejo intactas y la tabla nueva solo aplica a partir de hoy (datos viejos sin hash, sin reportar)?
4. **`reception_type` para OC regular**: ¿lo reporto como `NO_DETERMINABLE` (honesto, sin inventar), o prefieres que agregue categoría de gasto a los ítems de OC regular primero (cambio de alcance mayor, afecta todo el módulo de Compras, no solo este reporte)?

Quedo en espera de tus respuestas antes de pasar a código (deliverable 2).
