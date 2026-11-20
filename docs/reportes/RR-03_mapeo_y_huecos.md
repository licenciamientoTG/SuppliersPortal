# RR-03 · Excepciones de flujo — Paso 0 (mapeo y huecos)

Fecha: 2026-10-26
Estado: PLAN — sin código. Pendiente de aprobación.

## 1. Objetivo del reporte (recordatorio)

Detectar y clasificar automáticamente, con un código estable, cada documento que rompió la
secuencia requisición → OC → recepción → factura, y dar seguimiento hasta su regularización
con evidencia y responsable. Dominio C (Recepción y devengo), Fase F1, frecuencia semanal,
consumidor Contraloría.

Códigos de excepción (fijos, nunca se renumeran):
- EXC-01 factura sin recepción
- EXC-02 recepción sin OC
- EXC-03 OC directa fuera de la lista de tipos de gasto autorizados
- EXC-04 factura con fecha anterior a la OC
- EXC-05 recepción posterior a la vigencia de la OC

## 2. Tabla de mapeo (campo requerido → tabla.campo real | NO EXISTE)

| Campo requerido | Tabla.campo real | Estado |
|---|---|---|
| OC regular | `purchase_orders` (`App\Models\PurchaseOrder`) | Existe |
| OC directa | `odc_direct_purchase_orders` (`App\Models\DirectPurchaseOrder`) | Existe (tabla separada, sin tipo unificador) |
| Fecha de emisión de OC/OCD | `purchase_orders.issued_at` / `odc_direct_purchase_orders.issued_at` | Existe |
| Vigencia de OC/OCD | — | **NO EXISTE** como campo de negocio. Único proxy: `getAutoCloseDeadline()` (OC: `issued_at`+10 días; OCD: `submitted_at`+7 días) — es fecha de cierre por inactividad, no vigencia real |
| Usuario que originó OC/OCD | `purchase_orders.created_by` / `odc_direct_purchase_orders.created_by` | Existe |
| Tipos de gasto autorizados para OCD | — | **NO EXISTE.** Ya confirmado como hueco abierto en RC-02 (`OCD-01`). Lo que existe es `odc_direct_purchase_order_items.expense_category_id` (cuenta de gasto del catálogo presupuestal normal, no un catálogo de "autorizados") |
| Enlace OC directa → contrato/autorización | — | **NO EXISTE.** Solo `justification` (texto libre). El enlace a contrato (`enabling_reference`) solo existe para OC por CONTRATO vía `requisition_items.contract_id` |
| Recepción | `receptions` (`App\Models\Reception`) | Existe |
| Recepción → OC/OCD | `receptions.receivable_type` / `receivable_id` (morphs, NO nullable) | Existe, pero sin FK real (polimórfica sobre 2 tablas) |
| Usuario que originó la recepción | `receptions.received_by` | Existe (es quien recibe, no un "creador" separado) |
| Factura | `supplier_invoices` (`App\Models\SupplierInvoice`) | Existe |
| Factura → OC/OCD | `supplier_invoices.receivable_type` / `receivable_id` (nullableMorphs) | Existe |
| Factura → Recepción | — (indirecto, vía `financial_provisions`) | Existe la cadena, no hay FK directa factura→recepción |
| Fecha de factura (CFDI) | `supplier_invoices.issued_at` | Existe |
| Usuario que originó la factura | `supplier_invoices.uploaded_by` (interno) / `uploaded_by_supplier_id` (proveedor) | Existe (dos columnas según `uploaded_origin`) |
| Tabla de excepciones con flujo de estatus | — | **NO EXISTE.** Construcción nueva: `flow_exceptions` |
| Evidencia de regularización | — | **NO EXISTE** sistema polimórfico reutilizable. Patrón del repo: tabla dedicada por módulo (ej. `reception_attachments`). Construcción nueva: `flow_exception_evidences`. La columna `regularization_evidence` del reporte es una liga calculada a `route('flow-exceptions.evidence.download', $evidence->id)`, no un campo almacenado |
| Responsable asignado / cierre | — | Construcción nueva, campos en `flow_exceptions` |
| exception_name (nombre de negocio del código) | — | **NO EXISTE tabla catálogo.** Se resuelve con un `match()`/arreglo de constantes en `FlowException::NAMES`, igual que el patrón de `CfdiRejectionCode` — no requiere tabla, son 5 valores fijos |
| document_type / document_folio (columna de liga) | `flow_exceptions.document_type` (string plano) + `flow_exceptions.document_id` | El "folio" visible no es un campo propio: se resuelve en el servicio del reporte consultando el folio real según `document_type` (`purchase_orders.folio`/`odc_direct_purchase_orders.folio`/`receptions.folio`/`supplier_invoices.uuid`) — **a confirmar en Paso 0 de implementación si cada una de esas 4 tablas tiene folio legible** (pendiente de verificar `purchase_orders.folio` y `odc_direct_purchase_orders.folio`, no confirmados en esta exploración) |
| closed_by (nombre a mostrar) | `flow_exceptions.closed_by` (FK users) | Se resuelve el nombre vía relación `closedBy()`, no se persiste texto duplicado |
| originated_by cuando el documento lo originó un proveedor (no un usuario interno) | `supplier_invoices.uploaded_by_supplier_id` | **Hueco real:** `flow_exceptions.originated_by` solo puede ser FK a `users`. Para EXC-01/EXC-04 (facturas), cuando `uploaded_origin = ORIGIN_SUPPLIER`, no hay usuario interno que la originó. Se requiere una columna adicional `originated_by_supplier_id` (FK nullable a `suppliers`) en `flow_exceptions`, mutuamente excluyente con `originated_by` |

## 3. Estado de los prerrequisitos del Paso 0 original

| Prerrequisito | Estado | Impacto |
|---|---|---|
| Datos de OC, recepciones y facturas con fechas confiables | **Existe** | Ninguno — se puede construir EXC-01, EXC-02 (parcial), EXC-04 con datos reales |
| Lista de tipos de gasto autorizados para OC directa (de RC-02) | **NO EXISTE** | EXC-03 no se puede calcular como "fuera de lista" porque no hay lista. Mismo hueco ya aceptado por RC-02 (ver §4) |
| Tabla de excepciones con flujo de estatus | **NO EXISTE** | Es la construcción principal de este reporte — migración aditiva nueva |

## 4. Hallazgos que afectan el alcance de cada código de excepción

**EXC-01 (factura sin recepción).** El vínculo factura→recepción no es directo: pasa por
`FinancialProvision` (creada al registrar una `Reception`, con `reception_id` único). Una
factura con `status = UPLOADED` y `financial_provision_id IS NULL` normalmente significa que
no existe ninguna `FinancialProvision` para la OC/OCD referenciada, es decir, nunca se
registró recepción. Pero esto también ocurre en el curso normal de una factura recién subida
que todavía no ha sido conciliada (ventana de minutos/horas). **Se requiere un umbral de
antigüedad** (propongo: factura con más de 48 horas en `UPLOADED` sin `financial_provision_id`
y sin ninguna `Reception` registrada contra su `receivable_type`/`receivable_id`), igual al
criterio que RC-02 ya usa para "acuse de OC pendiente".

**EXC-02 (recepción sin OC).** El esquema hace imposible `receivable_id IS NULL` en
`receptions` (es `morphs()`, no `nullableMorphs()`). El caso real de negocio es una
`Reception` cuyo `receivable_id` apunta a una OC/OCD que: (a) está soft-deleted, o (b) nunca
alcanzó el estado que habilita recepción. Falta confirmar si existe algún camino (API, race
condition) que permita crear una `Reception` sin pasar por `canBeReceived()` de la OC/OCD —
si no existe ningún camino así, EXC-02 solo detectaría el caso (a), que debería ser rarísimo
en operación normal. Pregunta abierta para Contraloría: ¿interesa igual detectar el caso (a)
aunque sea raro, o se retira EXC-02 del alcance v1 por no tener casos reales que mostrar?

**EXC-03 (OCD fuera de tipos de gasto autorizados).** El prompt original de RR-03 asume que
`direct_expense_type` ya existe "de RC-02" — **no es así**, está confirmado como hueco abierto
en `docs/reportes/RC-02_mapeo_y_huecos.md` (`OCD-01`). RC-02 decidió (2026-10-21, §7 punto 2)
tratar el 100% de las OCD como excepción mostrada en un aviso de cabecera, no como listado
individual, mientras no exista el catálogo real. Propongo que RR-03 siga el mismo criterio:
EXC-03 = cada OCD emitida cuenta 1:1 como fila de excepción hasta que exista el catálogo de
tipos autorizados (construcción futura, fuera de este reporte).

**EXC-04 (factura con fecha anterior a la OC).** Comparación directa:
`supplier_invoices.issued_at < purchase_orders.issued_at` (o el de `odc_direct_purchase_orders`
según `receivable_type`). Sin huecos de dato — se puede construir tal cual.

**EXC-05 (recepción posterior a la vigencia de la OC).** No existe una "vigencia" de negocio
real (ya confirmado 3 veces en reportes anteriores: RC-02, RC-03, RR-01). El único proxy
disponible es `getAutoCloseDeadline()` (fecha de cierre automático por inactividad: OC
`issued_at`+10 días, OCD `submitted_at`+7 días). Usar este proxy mezclaría dos conceptos
distintos (vigencia de negocio vs. inactividad administrativa) y podría generar falsos
positivos (una OC con recepción parcial legítima más allá de 10 días naturales no es una
"excepción de vigencia", es una OC de ciclo largo). Pregunta abierta: ¿se usa el proxy de
`getAutoCloseDeadline()` con esa advertencia explícita en el reporte, o se retira EXC-05 del
alcance v1 hasta que exista un campo real de vigencia?

**Advertencia explícita (hallazgo de revisión con codex):** la Regla de trabajo #3 del prompt
original dice textualmente "Si un prerrequisito no existe, NO simules el dato (ni ceros, ni
NULL silenciosos, ni valores fijos)". Usar `getAutoCloseDeadline()` como sustituto de
"vigencia" en EXC-05 roza esa prohibición: no es un dato simulado (es un campo real), pero sí
es un concepto distinto siendo usado para representar uno que no existe. Lo mismo aplica, en
menor medida, a EXC-03 (contar el 100% de OCD como excepción no es simular un dato, pero
tampoco es evaluar "fuera de lista" porque no hay lista). **Por esto ambas preguntas (EXC-03 y
EXC-05) requieren tu aprobación explícita antes de programar, no solo un supuesto por
default** — ver sección 8.

## 5. Catálogo de permisos y alcance (sin huecos, patrón ya establecido)

- Permisos: `reportes.flow_exceptions.ver` y `reportes.flow_exceptions.exportar`, vía
  `Permission::findOrCreate()` en `RolePermissionSeeder.php` (nunca correr el seeder completo
  en producción).
- Alcance por empresa: mismo patrón que RC-02/RC-03/RC-04/RF-01 — `FULL_SCOPE_ROLES` +
  `visibleCompanyIds()`/`visibleCostCenters()` acotado a `$user->companies()`.
- Cerrar excepciones: según el prompt, solo Contraloría o el responsable asignado — esto
  requiere un permiso adicional de acción, p. ej. `reportes.flow_exceptions.cerrar` y
  `reportes.flow_exceptions.asignar` (siguiendo el precedente ya existente de
  `reportes.budget_alerts.excepcion.solicitar`/`.aprobar`). **Corrección (hallazgo de
  revisión):** el permiso por sí solo no basta — "Contraloría O el responsable asignado"
  significa que el responsable asignado puede cerrar SU PROPIA excepción aunque no tenga el
  permiso general `.cerrar`. La autorización real es: `$user->can('reportes.flow_exceptions.cerrar')
  OR $flowException->assigned_to === $user->id`. Esto se valida en el controlador/policy, no
  solo con el middleware `can:`.
- Cada exportación se registra en bitácora (usuario, fecha/hora, filtros, número de filas),
  igual que los demás reportes — vía el mismo mecanismo de auditoría que ya usa
  `OpenPurchaseOrderBacklogReportController::export()` (`activity()->log()`). **Agregado tras
  revisión: esto se había omitido del plan inicial.**

## 6. Arquitectura propuesta (archivos a crear/modificar)

Siguiendo el patrón de `OpenPurchaseOrderBacklogReport` (RC-04) y el precedente de flujo de
decisión de `BudgetException`:

**Migraciones (aditivas):**
- `database/migrations/<fecha>_create_flow_exceptions_table.php`:
  - `id`, `exception_code` (CHAR(6), ej. `EXC-01`), `document_type` (string plano, no morph de
    Eloquent — mismo patrón que `BudgetException`), `document_id` (unsignedBigInteger),
    `company_id` (persistido directo, igual que RF-01), `supplier_id` (FK nullable),
    `amount_mxn` (DECIMAL(18,2)), `originated_by` (FK nullable a `users`), `originated_at`
    (datetime), `detected_at` (datetime), `status` (`ABIERTA`/`EN_REGULARIZACION`/`CERRADA`),
    `assigned_to` (FK nullable a `users`), `closed_at` (datetime nullable), `closed_by` (FK
    nullable a `users`), timestamps.
  - Índice único `(exception_code, document_type, document_id)` para idempotencia del job.
  - Índice `(status, detected_at)`.
- `database/migrations/<fecha>_create_flow_exception_evidences_table.php`:
  - `id`, `flow_exception_id` (FK), `path`, `original_name`, `uploaded_by` (FK), timestamps.
    (Patrón de `ReceptionAttachment`, sin polimorfismo real porque solo tiene un padre.)

**Catálogo de códigos:** constantes en el modelo (igual que `CfdiRejectionCode` en RF-01), no
tabla aparte — son solo 5 códigos fijos y estables, no un catálogo editable por el usuario.

**Modelos:**
- `app/Models/FlowException.php` — constantes `EXC_01`.."EXC_05", `STATUS_ABIERTA`, etc.
- `app/Models/FlowExceptionEvidence.php`.

**Servicio de detección:**
- `app/Services/FlowExceptionDetector.php` — un método público por código (`detectExc01()` …
  `detectExc05()`) + `detectAll(): int` (retorna cuántas excepciones nuevas insertó), usando
  `insertOrIgnore`/`firstOrCreate` sobre el índice único para la idempotencia.

**Job y scheduler:**
- `app/Jobs/FlowExceptionsDetectionJob.php`, programado diario en `routes/console.php` (o
  donde ya estén los demás schedules del repo — hay que confirmarlo al implementar).

**Reporte:**
- `app/Reports/Purchasing/FlowExceptionsReport.php` (sigue el patrón `build(User, array):
  array` + `visibleCompanyIds()`).
- `app/Reports/Purchasing/FlowExceptionsExport.php` (vía `ReportSheet`).

**HTTP:**
- `app/Http/Requests/FlowExceptionsReportRequest.php`.
- `app/Http/Controllers/FlowExceptionsReportController.php` (`index`/`data`/`export`).
- `app/Http/Controllers/FlowExceptionActionController.php` (un único método `update`, acción
  determinada por el campo `action` del body: `asignar`/`cerrar` — con validación de
  evidencia+responsable obligatorios para cerrar). **Corrección (hallazgo de revisión): el
  prompt original define un solo endpoint `PATCH /control/excepciones-flujo/{id}` para ambas
  acciones (sección H), no dos rutas separadas. Se respeta ese contrato tal cual, sin
  justificación para dividirlo.**
- Rutas en `routes/web.php`: `GET /reportes/control/excepciones-flujo` (+`/data`, `/export`),
  `PATCH /control/excepciones-flujo/{id}` (body: `{action: 'asignar'|'cerrar', ...}`).

**Vista:** `resources/views/reports/flow-exceptions/index.blade.php` — tarjetas por código con
conteo e importe abierto por código (sección G), tabla con acciones "Asignar"/"Cerrar con
evidencia", gráfico de tendencia semanal (reutilizar librería de gráficos si el repo ya tiene
una; si no, confirmar antes de agregar dependencia). Sigue las convenciones comunes de salida
ya usadas por los demás reportes: DataTables en modo server-side con paginación 25/50/100,
encabezado fijo, primera columna congelada, panel de filtros con botón "Limpiar filtros".
**Exportación:** XLSX con celdas tipadas (moneda, fecha, no texto), CSV UTF-8 con BOM,
escritura en streaming vía `ReportSheet`, y exportaciones de más de 50 000 filas encoladas
como job con notificación al usuario — mismo patrón ya implementado en los reportes previos,
sin construcción nueva.

**Permisos:** agregar al `RolePermissionSeeder.php` el bloque de los 4 permisos nuevos
(`.ver`, `.exportar`, `.asignar`, `.cerrar`), otorgados a `superadmin`/`general_director` (los
4) y lectura a `accounting`/`report_viewer` según corresponda.

## 7. SQL propuesto (borrador) de la consulta principal

```sql
-- Vista simplificada de la tabla ya poblada por el job de detección.
SELECT
    fe.id,
    fe.exception_code,
    fe.document_type,
    fe.document_id,
    fe.company_id,
    fe.supplier_id,
    s.business_name AS supplier_name,
    fe.amount_mxn,
    fe.originated_by,
    u1.name AS originated_by_name,
    fe.originated_at,
    fe.detected_at,
    fe.status,
    fe.assigned_to,
    u2.name AS assigned_to_name,
    DATEDIFF(day, fe.detected_at, COALESCE(fe.closed_at, GETDATE())) AS days_open,
    fe.closed_at,
    fe.closed_by
FROM flow_exceptions fe
LEFT JOIN suppliers s ON s.id = fe.supplier_id
LEFT JOIN users u1 ON u1.id = fe.originated_by
LEFT JOIN users u2 ON u2.id = fe.assigned_to
WHERE fe.company_id IN (:company_ids)
  AND fe.detected_at BETWEEN :date_from AND :date_to
  AND (:exception_codes_is_empty = 1 OR fe.exception_code IN (:exception_codes))
  AND (:status_is_empty = 1 OR fe.status IN (:status_list))
  AND (:originated_by_is_empty = 1 OR fe.originated_by = :originated_by)
  AND fe.amount_mxn > :amount_greater_than
ORDER BY fe.amount_mxn DESC;
```

Detección (ejemplo EXC-01, simplificado — bindings omitidos por brevedad):

```sql
SELECT si.id, si.receivable_type, si.receivable_id, si.supplier_id, si.total, si.uploaded_by,
       si.created_at
FROM supplier_invoices si
WHERE si.status = 'UPLOADED'
  AND si.financial_provision_id IS NULL
  AND si.created_at < DATEADD(hour, -48, GETDATE())
  AND NOT EXISTS (
      SELECT 1 FROM financial_provisions fp
      WHERE fp.receivable_type = si.receivable_type
        AND fp.receivable_id = si.receivable_id
  );
```

## 8. Preguntas abiertas para Contraloría/Contabilidad (con supuesto a aplicar si no hay respuesta)

1. **EXC-01 — umbral de antigüedad.** ¿48 horas desde la carga de la factura sin
   `FinancialProvision` es razonable, o debe ser otro valor? **Supuesto si no hay respuesta:
   48 horas**, igual al criterio ya usado para acuse de OC en RC-02.
2. **EXC-02 — ¿vale la pena construirlo en v1?** El esquema casi no permite el caso real
   (recepción sin OC), solo detecta recepciones contra OC/OCD soft-deleted. **Supuesto: se
   incluye en v1 pero se documenta como "edge case raro"**; si en la primera corrida real
   arroja 0 filas durante varias semanas, se evalúa retirarlo.
3. **EXC-03 — ¿toda OCD cuenta como excepción (igual que RC-02) hasta que exista el catálogo
   de tipos autorizados, o se prefiere no mostrar nada hasta tener el catálogo real?**
   **Supuesto: se incluye con el mismo criterio de RC-02** (100% de OCD, aviso explícito de
   que es una medida temporal sin catálogo real).
4. **EXC-05 — ¿se usa el proxy de `getAutoCloseDeadline()` (cierre por inactividad) como
   "vigencia", con la advertencia de que no es un campo de negocio real, o se retira del
   alcance v1?** **Supuesto: se incluye con advertencia explícita en la UI/export** (columna
   con nota "proxy: cierre por inactividad, no vigencia contractual"), dado que es el único
   dato disponible y el prompt original pide expresamente este tipo de excepción.
5. **Cierre de excepciones — ¿"Contraloría o el responsable asignado" se traduce a los roles
   `superadmin`+`general_director`+el usuario en `assigned_to`, o hay un rol específico de
   "Contraloría" que no existe hoy en el catálogo de roles?** **Supuesto: se usa
   `FULL_SCOPE_ROLES` (superadmin, general_director, accounting) + el usuario asignado**,
   mismo patrón que otros reportes; si existe un rol "Contraloría" separado, decirlo para
   crearlo.
6. **Evidencia de regularización — ¿basta con un archivo adjunto simple (igual que
   `ReceptionAttachment`), o se requiere también un comentario/justificación de texto como en
   `BudgetException.decision_comment`?** **Corrección tras revisión: la especificación
   original (sección F) solo exige "evidencia adjunta y responsable" para cerrar — nada de
   comentario obligatorio. Supuesto correcto: solo archivo adjunto + responsable asignado son
   obligatorios; el comentario es un campo opcional**, no un requisito de cierre (mi plan
   original agregaba una regla de negocio no solicitada — gracias a la revisión con codex por
   detectarlo).
7. **Scheduler del job diario — ¿a qué hora debe correr `FlowExceptionsDetectionJob`?**
   **Supuesto: 02:00 AM hora `America/Ciudad_Juarez`**, para no competir con el cierre de
   operaciones del día; se confirma la hora exacta del cron en el servidor al implementar
   ([[cron-servidor-www-data]]).
8. **Autoría de documentos originados por el proveedor — ¿se agrega `originated_by_supplier_id`
   como columna adicional mutuamente excluyente con `originated_by` (ver tabla de mapeo §2),
   o se prefiere otro diseño?** **Supuesto: se agrega la columna**, ya que una factura puede
   originarla el proveedor (`uploaded_origin = ORIGIN_SUPPLIER`) y el reporte debe mostrar
   "Usuario que la originó" en ambos casos (campo obligatorio de la especificación, sección B).
9. **Valores por defecto de filtros y corte de zona horaria — ¿confirmar que el filtro de
   estatus por defecto es `ABIERTA`+`EN_REGULARIZACION`, el rango de fecha de detección por
   defecto son los últimos 90 días, y que `date_to` se interpreta a las 23:59:59
   `America/Ciudad_Juarez` (igual que el resto del catálogo)?** **Supuesto: se aplican los 3
   valores tal cual los fija la especificación (secciones A y D), sin desviación** — esto no
   es una pregunta real sino una confirmación de que el plan los implementará exactamente así
   (quedó ambiguo en el borrador de SQL de la sección 7 y se corrige aquí).

## 9. Lo que se puede entregar hoy vs. lo que requiere construir el control primero

**Se puede entregar con datos reales (sin huecos de dato):**
- EXC-01 (con umbral de antigüedad a confirmar).
- EXC-04 (comparación directa de fechas).
- El job de detección, la tabla `flow_exceptions`, el flujo de asignación/cierre con
  evidencia, el reporte completo con filtros, permisos y exportación.

**Requiere decisión explícita antes de programar (ver preguntas 2-4 arriba):**
- EXC-02 (caso real casi inexistente por diseño del esquema).
- EXC-03 (no existe catálogo de tipos de gasto autorizados — mismo hueco que RC-02).
- EXC-05 (no existe vigencia real de OC — solo proxy de inactividad).

Ningún código se simulará con datos falsos; si no hay respuesta a las preguntas 2-4, se
aplican los supuestos documentados arriba y se deja constancia explícita en el reporte de que
son criterios temporales mientras no existan los controles reales (catálogo de tipos
autorizados, campo de vigencia de OC).
