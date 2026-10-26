# RF-01 · Bitácora de validación de CFDI — Plan (Paso 0)

Spec original: `docs/reportes/prompts_reportes_portal_proveedores.md` (REPORTE #12, líneas 2024-2200).

## 0. Resumen ejecutivo

El spec de RF-01 asume una **bitácora por intento** (aceptado o rechazado, con código de rechazo estable, regla incumplida y mensaje al proveedor) de la que hoy **no existe nada**:

- El motor de validación actual (`InvoiceService::validateInvoiceData()`) solo escribe en base de datos **cuando la factura se acepta**. Si cualquiera de las 7 reglas de hoy (RFC emisor, RFC receptor, RFC emisor = proveedor, EFOS 69-B, total > 0, UUID duplicado, XML corrupto) rechaza la carga, se lanza una excepción y **no queda ningún rastro en BD** — nunca se crea ni actualiza ninguna fila. No es que "solo se guarde lo aceptado"; los intentos fallidos simplemente se pierden.
- No existe ningún catálogo de código de rechazo. Hoy todo motivo de rechazo es texto libre en `supplier_invoices.rejection_reason`, escrito por `SupplierInvoice::markRejected()` (dos orígenes: rechazo manual de Finanzas con texto tecleado a mano, y el rechazo automático por tolerancia de $0.05 con un string interpolado). No hay código estable ni referencia a una "regla" separada del mensaje.
- Sí existe índice único por UUID en `supplier_invoices` y una validación de aplicación que bloquea reintentos del mismo UUID — eso ya cumple la mitad del criterio de aceptación ("recargar el mismo UUID no genera un segundo registro de factura"), pero la otra mitad ("sí debe generar un nuevo intento en la bitácora") no existe porque la bitácora no existe.
- `cfdi_type` (tipo de comprobante), `sat_status` (estatus SAT en línea) y `xml_sha256` no se calculan en ningún punto del sistema hoy — son construcción 100% nueva, no datos que falte exponer.

No hay nada que "ya exista con otro nombre" para la bitácora en sí: hay que construirla desde cero, tal como el propio spec anticipa en su Paso 0. Las decisiones de alcance están en la sección 7.

## 1. Tabla de mapeo (campo requerido del spec → realidad)

| Campo del spec | Tabla.campo real | Estado |
|---|---|---|
| attempted_at | NO EXISTE — hoy solo `supplier_invoices.created_at` para lo aceptado | **Hueco real** — tabla nueva `cfdi_validation_logs` |
| uuid | `supplier_invoices.uuid` (solo para lo aceptado); en el XML vía `CfdiXmlParser` para cualquier intento | Existe para lo aceptado; para intentos rechazados hay que capturarlo del XML parseado antes de persistir |
| issuer_rfc / issuer_name | `supplier_invoices.issuer_rfc`; `issuer_name` **NO EXISTE** en ningún lado (ni se extrae del XML hoy) | Parcial — `issuer_name` es construcción nueva en `CfdiXmlParser` |
| receiver_company | NO EXISTE columna `company_id`. Se valida por `receiver_rfc` contra `companies.rfc` pero nunca se persiste cuál empresa es | **Hueco** — resolver por JOIN o persistir `company_id` (ver §7.3) |
| cfdi_type | NO EXISTE — `CfdiXmlParser` no extrae `TipoDeComprobante` | **Hueco real, construcción nueva** |
| total / currency | `supplier_invoices.total` / `.currency` (solo para lo aceptado); en el XML para cualquier intento | Existe para lo aceptado; derivable del XML parseado para intentos rechazados |
| result | NO EXISTE como tal — hoy es implícito por la existencia (o no) de la fila y por `status` (`UPLOADED`/`LINKED`/`REJECTED`) | **Hueco** — nueva columna `result` en la bitácora |
| rejection_code | NO EXISTE — no hay catálogo de códigos | **Hueco real, construcción nueva** |
| rule_description | NO EXISTE — el texto libre de `rejection_reason` mezcla regla y mensaje en un solo string | **Hueco real** |
| supplier_message | Parcial: `supplier_invoices.rejection_reason` (texto libre, ya se muestra al proveedor desde commit cb1bd54) | **Hueco** — separar de `rule_description`, estructurar por catálogo |
| attempt_no | NO EXISTE — no hay fila por intento, no hay nada que numerar | **Hueco real**, calculable una vez que exista la bitácora (`ROW_NUMBER() OVER (PARTITION BY uuid ORDER BY attempted_at)`) |
| channel | `supplier_invoices.uploaded_origin` (`'supplier'`/`'finance'`, constantes `ORIGIN_SUPPLIER`/`ORIGIN_FINANCE`) — mismo concepto, nombre distinto | Existe, solo para lo aceptado |
| user_name | `supplier_invoices.uploaded_by` (Finanzas) / `.uploaded_by_supplier_id` (proveedor) → `users`/`suppliers` | Existe, solo para lo aceptado; para intentos rechazados hay que capturar el actor autenticado en el momento del intento |
| sat_status | NO EXISTE — no hay consulta al webservice del SAT en ningún punto del repo | **NO EXISTE, fuera de alcance v1** (ver §4) |
| xml_sha256 | NO EXISTE — ningún punto de carga de archivos del repo calcula hash (confirmado también en `docs/reportes/RR-01_mapeo_y_huecos.md:67`) | **Hueco real, construcción nueva, opcional (Op) en el spec** |

## 2. Hallazgos importantes que cambian el alcance

### 2.1 Los intentos rechazados hoy no dejan ningún rastro — ni siquiera en el log técnico

`InvoiceService::validateInvoiceData()` (`app/Services/InvoiceService.php:114-164`) corre **antes** de `DB::transaction()`/`SupplierInvoice::create()`. Si cualquiera de sus 7 reglas falla, lanza `ValidationException::withMessages(...)` que el controlador re-lanza directo (`SupplierInvoiceController.php:84-85`) — no hay `Log::error` ni nada persistido, solo el mensaje de vuelta al formulario. Mismo patrón en las validaciones de forma dentro de `CfdiXmlParser::parse()` (XML corrupto, UUID/RFC/Total ausentes en el nodo), que lanza `RuntimeException` capturada y convertida a `ValidationException`.

El único caso que hoy "rechaza" dejando rastro es el rechazo automático por tolerancia de $0.05 (`FinancialProvisionService::rejectForAmountMismatch`), pero ocurre **después** de que la factura ya fue aceptada y creada — es un `UPDATE` de la fila existente a `status=REJECTED`, no un segundo intento. Ese UUID solo tiene una fila en `supplier_invoices`, nunca dos.

**Consecuencia para RF-01:** para loguear los 7 motivos de rechazo que ya existen hoy, hay que instrumentar el punto exacto donde cada uno lanza su excepción — tanto en `InvoiceService::validateInvoiceData()` como en `CfdiXmlParser::parse()` — para escribir en `cfdi_validation_logs` antes de relanzar. No es un cambio de regla de negocio (sección 8 de las reglas de trabajo del spec lo prohíbe sin pedirlo explícitamente), es solo agregar el registro sin tocar qué se acepta o rechaza.

### 2.2 No hay catálogo de motivos — todo es texto libre interpolado

No existe ninguna clase `RejectionReason`, enum, ni tabla de catálogo en el repo. Confirmado por búsqueda exhaustiva. Lo que existe:

```php
// app/Models/SupplierInvoice.php:82-89
public function markRejected(string $reason): void
{
    $this->update([
        'status' => self::STATUS_REJECTED,
        'rejected_at' => now(),
        'rejection_reason' => $reason,
    ]);
}
```

Dos llamadores: rechazo manual de Finanzas (texto tecleado a mano, validado solo `string|min:10|max:1000`, sin catálogo) y el rechazo automático por tolerancia (string interpolado con los montos). Ninguno de los dos tiene código estable ni separa "regla incumplida" de "mensaje al proveedor" — es un solo campo de texto.

**Consecuencia para RF-01:** construir `cfdi_rejection_codes` (código, descripción de regla, mensaje al proveedor) desde cero, con un código fijo por cada una de las 7 reglas que ya existen hoy (p. ej. `RFC_EMISOR_AUSENTE`, `RFC_NO_COINCIDE`, `EFOS_69B`, `RFC_RECEPTOR_INVALIDO`, `TOTAL_INVALIDO`, `UUID_DUPLICADO`, `XML_MALFORMADO`), más uno para el rechazo por tolerancia de importe (`TOLERANCIA_IMPORTE_EXCEDIDA`) si se decide que ese evento también debe aparecer en la bitácora (ver §2.3).

### 2.3 El rechazo por tolerancia de $0.05 vive en otro flujo, no en la carga — decisión de alcance necesaria

El spec define RF-01 como "cada intento de **carga**" de un CFDI. El rechazo por tolerancia (`FinancialProvisionService::rejectForAmountMismatch`, feature cb1bd54) no ocurre al cargar: ocurre en la conciliación posterior, sobre una factura que YA pasó la validación de carga y ya tiene fila en `supplier_invoices`. Es un evento distinto, con su propio registro (`rejection_reason` en la misma fila de `supplier_invoices`).

Dos caminos: (a) RF-01 solo cubre los rechazos en el momento de la carga (las 7 reglas de `validateInvoiceData`/`CfdiXmlParser`), y el rechazo por tolerancia se reporta aparte (ya tiene su propio rastro en `supplier_invoices.rejection_reason`); o (b) se amplía el alcance para que la conciliación también escriba una fila en `cfdi_validation_logs` cuando rechaza por tolerancia, unificando "toda razón de rechazo de una factura" en una sola bitácora. Ver pregunta abierta §7.1.

### 2.4 `company_id` nunca se persiste — solo se valida

```php
// app/Services/InvoiceService.php:141-151
$activeCompanyRfcs = Company::query()->where('is_active', true)->whereNotNull('rfc')
    ->pluck('rfc')->map(fn (string $rfc) => strtoupper(trim($rfc)));
if ($activeCompanyRfcs->isNotEmpty() && ! $activeCompanyRfcs->contains($data['receiver_rfc'])) {
    throw ValidationException::withMessages([...]);
}
```

Esto valida que el RFC receptor esté en la lista de empresas activas, pero nunca guarda cuál empresa es. Para el filtro obligatorio `company_ids` del spec (línea 2114, alcance por empresa del usuario) hace falta resolver `receiver_company` ya sea por `JOIN` contra `companies.rfc` en cada consulta, o persistiendo `company_id` directo en `cfdi_validation_logs` al momento de escribir cada intento (recomendado: evita un join de texto en cada consulta del reporte y es el mismo patrón de alcance por empresa que usan RC-01..04).

### 2.5 `cfdi_type`, `sat_status`, `xml_sha256` — ninguno existe, construcción nueva en 3 frentes distintos

- `cfdi_type` (I/E/P/N/T): `CfdiXmlParser` no extrae `TipoDeComprobante` del nodo del XML. Agregarlo es trivial (mismo patrón que ya extrae RFC/UUID/Total).
- `sat_status` (Vigente/Cancelado/No encontrado): requiere consultar el webservice de verificación de CFDI del SAT (o un servicio de terceros) en tiempo real o por job — no existe ninguna integración de ese tipo en el repo. Es una pieza grande fuera del alcance de "solo registrar la bitácora"; el spec ya la marca opcional (`Op`).
- `xml_sha256`: ningún punto de carga de archivos del repo calcula hash (confirmado también al investigar RR-01). Es trivial de calcular (`hash('sha256', $xmlContent)`) al momento de recibir el archivo, para cualquier intento (aceptado o rechazado).

## 3. Estado de los prerrequisitos del Paso 0 (resumen)

| Prerrequisito del spec | Estado |
|---|---|
| Motor de validación CFDI existente | **Existe, parcial**: RFC emisor/receptor, EFOS 69-B, total>0, UUID duplicado, estructura XML. Faltan: tipo de comprobante, estatus SAT, vigencia/plazo, hash |
| Bitácora POR INTENTO | **NO EXISTE** — los rechazos de hoy no dejan ninguna fila en ninguna tabla |
| Catálogo de códigos de rechazo con regla y mensaje | **NO EXISTE** — solo texto libre interpolado en `supplier_invoices.rejection_reason` |
| Índice único por UUID en facturas | **Existe**: `supplier_invoices.uuid` unique + validación de aplicación que bloquea el reintento antes del insert |

## 4. Qué se puede entregar hoy vs. qué requiere construir el control primero

**Se puede entregar hoy (sin construir nada nuevo más que el reporte), una vez exista la bitácora:**
- `attempted_at`, `uuid`, `issuer_rfc`, `total`, `currency`, `channel` (= `uploaded_origin`), `user_name`, `result`, `attempt_no` — para cualquier intento, aceptado o rechazado, instrumentando los 7 puntos de rechazo ya existentes.
- `rejection_code`/`rule_description`/`supplier_message` para esas mismas 7 reglas, una vez exista el catálogo `cfdi_rejection_codes` (es mapear 1 a 1 cada excepción existente a un código fijo — no es inventar reglas nuevas).
- Resúmenes por código de rechazo, por proveedor (tasa de rechazo), por regla — cálculo directo sobre la bitácora nueva.

**Requiere construir antes (decisión de alcance, ver §7):**
- Tabla `cfdi_validation_logs` (nueva).
- Tabla/seed `cfdi_rejection_codes` (catálogo, confirmado por Contabilidad/Fiscal antes de liberarse, como exige la regla 9 del spec).
- Instrumentar los 7 puntos de rechazo actuales (`InvoiceService::validateInvoiceData()` y `CfdiXmlParser::parse()`) para escribir en la bitácora antes de relanzar la excepción.
- `cfdi_type` en `CfdiXmlParser` (trivial, agregar extracción de `TipoDeComprobante`).
- `xml_sha256` al recibir el archivo (trivial, hash del contenido ya leído).
- `issuer_name` en `CfdiXmlParser` (trivial, el nodo `Emisor` del XML ya trae `Nombre`).

**Fuera de alcance v1, no se simula:**
- `sat_status` — requiere integración con el SAT (o terceros) que no existe en el repo; se reporta como columna vacía/"No disponible", nunca inventado.
- Vigencia/plazo de registro del CFDI contra una fecha límite — no hay ninguna regla de "factura vencida" hoy; el spec no la pide como regla nueva (no está en la sección F de reglas de negocio), solo se documenta que no aplica.

## 5. Arquitectura propuesta

- Migración aditiva `create_cfdi_rejection_codes_table`: `code` (string, PK natural o unique), `rule_description`, `supplier_message`, `active` (boolean, para desactivar un código sin borrar histórico), timestamps. Seed inicial con los 7 códigos mapeados 1 a 1 a las reglas existentes (pendiente confirmación de Contabilidad/Fiscal, regla 9 del spec).
- Migración aditiva `create_cfdi_validation_logs_table`: `attempted_at`, `uuid` (nullable — un XML corrupto puede no tener UUID legible), `issuer_rfc`, `issuer_name` (nullable), `receiver_rfc`, `company_id` (FK nullable → `companies`, resuelto en el momento de validar), `cfdi_type` (nullable), `total`, `currency`, `result` (`ACEPTADO`/`RECHAZADO`), `rejection_code` (FK nullable → `cfdi_rejection_codes.code`), `channel` (`PORTAL_PROVEEDOR`/`INTERNO`), `supplier_id` (FK nullable, resuelto del actor autenticado), `user_id` (FK nullable → `users`, cuando el actor es Finanzas), `supplier_invoice_id` (FK nullable → `supplier_invoices`, solo si `result=ACEPTADO`, para trazar hacia la factura real), `xml_sha256` (nullable).
- `App\Services\InvoiceService::validateInvoiceData()` y `CfdiXmlParser::parse()` se ajustan para, en cada punto de rechazo, escribir la fila en `cfdi_validation_logs` con su `rejection_code` antes de relanzar la excepción (ningún cambio a qué se acepta o rechaza, solo se agrega el registro). En el camino de éxito (`InvoiceService::upload()`), se agrega una fila con `result=ACEPTADO` y `supplier_invoice_id` apuntando a la factura recién creada, dentro de la misma transacción.
- `App\Reports\Fiscal\CfdiValidationLogReport` (+ Export), mismo patrón que RC-01..04/RR-01: `build(User, params)` → `{rows, kpis}`, `matches()` para filtros, scope por empresa vía `company_id` y scope por proveedor (vista del proveedor: solo sus propios intentos).
- Controlador, FormRequest, rutas `reportes/fiscal/bitacora-cfdi` (+ `/data`, `/export`) para Fiscal/Cuentas por pagar, y `proveedor/mis-cargas` (vista acotada, solo lectura) para el proveedor — mismo patrón de siempre.
- Permisos `reportes.cfdi_validation_log.ver` / `.exportar`, agregados a `RolePermissionSeeder.php` siguiendo la convención real (`reportes.purchase_orders_issued.ver`, `reportes.receptions_evidence.ver`, etc.).

## 6. SQL propuesto (borrador de la consulta principal, antes de encapsular en el servicio)

```sql
SELECT
    l.id, l.attempted_at, l.uuid, l.issuer_rfc, l.issuer_name,
    l.company_id, c.name AS receiver_company, l.cfdi_type, l.total, l.currency,
    l.result, l.rejection_code, rc.rule_description, rc.supplier_message,
    l.channel, COALESCE(u.name, s.company_name) AS user_name,
    ROW_NUMBER() OVER (PARTITION BY l.uuid ORDER BY l.attempted_at) AS attempt_no
FROM cfdi_validation_logs l
LEFT JOIN companies c ON c.id = l.company_id
LEFT JOIN cfdi_rejection_codes rc ON rc.code = l.rejection_code
LEFT JOIN users u ON u.id = l.user_id
LEFT JOIN suppliers s ON s.id = l.supplier_id
WHERE l.company_id IN (:company_ids)
  AND l.attempted_at BETWEEN :date_from AND :date_to
  AND (:supplier_id IS NULL OR l.supplier_id = :supplier_id)
ORDER BY l.attempted_at DESC;
```
`attempt_no` se calcula en la consulta (no se persiste), para que nunca quede desfasado si se reconstruye el histórico. La vista del proveedor agrega `WHERE l.supplier_id = :current_supplier_id` fijo, sin excepción.

## 7. Preguntas abiertas — RESUELTAS 2026-10-26

Decisiones del usuario:

1. **Alcance del rechazo por tolerancia de $0.05**: SÍ entra en la bitácora. `FinancialProvisionService::rejectForAmountMismatch()` también escribe una fila en `cfdi_validation_logs` (`result=RECHAZADO`, `rejection_code=TOLERANCIA_IMPORTE_EXCEDIDA`, `supplier_invoice_id` apuntando a la factura ya existente), además de lo que ya hace hoy en `supplier_invoices.rejection_reason`. Se agrega un 8º código al catálogo.
2. **Catálogo `cfdi_rejection_codes`**: se aprueban los 7 códigos propuestos en §2.2 (+ el 8º de tolerancia del punto 1) tal como están, sin esperar una ronda adicional de nombres de Contabilidad/Fiscal.
3. **`company_id`**: se persiste directo en `cfdi_validation_logs` al momento de escribir cada intento (no por JOIN en cada consulta).
4. **`sat_status`**: queda fuera de alcance v1. Columna del spec marcada "No disponible" / sin dato, no simulada.
5. **Backfill**: el reporte arranca en blanco desde el despliegue. No se backfillea ninguna fila `ACEPTADO` para `supplier_invoices` ya existentes.

## 8. Catálogo `cfdi_rejection_codes` — códigos a sembrar

| code | rule_description | supplier_message (resumen) | origen en código |
|---|---|---|---|
| `RFC_EMISOR_AUSENTE` | El XML no contiene el RFC del emisor | El XML no contiene el RFC del emisor. | `InvoiceService::validateInvoiceData()` línea 116-120 |
| `RFC_RECEPTOR_AUSENTE` | El XML no contiene el RFC del receptor | El XML no contiene el RFC del receptor. | ídem línea 122-126 |
| `RFC_NO_COINCIDE` | El RFC emisor del XML no coincide con el RFC del proveedor autenticado | El RFC emisor del XML no coincide con el RFC del proveedor. | ídem línea 128-132 |
| `EFOS_69B` | El proveedor aparece en el listado 69-B del SAT (Definitivo/Presunto) | Tu RFC aparece en el listado de contribuyentes {estatus} del SAT (69-B). | ídem línea 134-139 |
| `RFC_RECEPTOR_INVALIDO` | El RFC receptor no corresponde a ninguna empresa activa de TotalGas | El RFC receptor del XML no corresponde a una empresa válida. | ídem línea 141-151 |
| `TOTAL_INVALIDO` | El total del CFDI debe ser mayor a cero | El total de la factura debe ser mayor a cero. | ídem línea 153-157 |
| `UUID_DUPLICADO` | El UUID de este CFDI ya fue registrado previamente | El UUID de esta factura ya fue registrado. | ídem línea 159-163 |
| `XML_MALFORMADO` | El archivo XML no se pudo leer o no contiene la estructura mínima de un CFDI (UUID/RFC/Total) | El archivo XML no es válido o está incompleto. | `CfdiXmlParser::parse()` |
| `TOLERANCIA_IMPORTE_EXCEDIDA` | El importe facturado excede la tolerancia de $0.05 contra la OC/recepción | El importe facturado difiere del esperado fuera de tolerancia; favor de emitir una factura corregida. | `FinancialProvisionService::rejectForAmountMismatch()` |

Pasa a código (deliverable 2): TDD estricto, rama `feat/rf01-cfdi-validation-log`, commits pequeños, suite completa contra la línea base antes de integrar.
