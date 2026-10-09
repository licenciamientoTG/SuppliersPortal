# RC-01 · Mapeo de esquema y huecos (Paso 0 y plan)

Fecha: 2026-10-09 · Rama: `feat/rc01-requisition-pipeline`

Este documento cumple el Paso 0 y la entrega J.1 (PLAN, sin código) del prompt de RC-01
(`docs/reportes/prompts_reportes_portal_proveedores.md`). Los nombres del prompt eran SUPUESTOS;
aquí se fija a qué tabla y campo real corresponde cada uno. **No se programa nada hasta que el
plan se apruebe.**

## 1. Cómo funciona hoy el flujo de la requisición

- **Estatus** (`App\Enum\RequisitionStatus`, columna `requisitions.status`):
  `DRAFT` → `PENDING` (validación de Compras) → `IN_QUOTATION` → `QUOTED` → `IN_APPROVAL`
  (autorización de la cotización) → `COMPLETED` (OC emitida). Laterales: `PAUSED` (esperando
  catálogo; al reactivarse regresa a `DRAFT`), `APPROVED`, `PENDING_BUDGET_ADJUSTMENT`,
  `REJECTED` (devuelta al requisitor, puede editarse y reenviarse) y `CANCELLED`.
- **Historial de estatus**: `requisition_status_histories` (desde la migración del 2026-08-31).
  Lo llena `App\Observers\RequisitionObserver`: un evento `CREATED` al crear y un `STATUS_CHANGED`
  (`from_status`, `to_status`, `occurred_at`, `user_id`) cada vez que cambia `status` por Eloquent.
  El comando `reports:backfill-requisition-history` reconstruye eventos aproximados para
  requisiciones anteriores, a partir de fechas reales (`validated_at`, envío de RFQ, aprobación de
  cotización, emisión de OC y recepción).
- **Autorización de la cotización** (lo único con aprobador individual):
  - `quotation_summaries.current_approver_user_id` + `approval_status = 'pending'`: es exactamente
    lo que usa la bandeja de autorizaciones (`QuotationApprovalController`, con delegaciones vía
    `ApprovalDelegationService::accessiblePrincipalIds`).
  - `cost_center_approval_steps` (`step_order`, `principal_user_id`, `status`, `acted_at`): cadena
    de pasos por centro de costo. **Se borra y se vuelve a crear** cada vez que se reinicia la
    autorización (`CostCenterApprovalFlowService::initialize`), así que solo guarda la última ronda.
  - `approval_decisions` (`assigned_principal_user_id`, `acted_by_user_id`, `action`, `acted_at`):
    bitácora que solo crece; conserva todas las rondas.
- Las etapas `PENDING`, `IN_QUOTATION`, `QUOTED` y `APPROVED` no tienen una persona asignada:
  las atiende la cola de Compras (rol `buyer`).

## 2. Tabla de mapeo (campo requerido → tabla.campo real)

| Campo del prompt | Real | Estado |
|---|---|---|
| requisition_folio | `requisitions.folio` | Existe |
| created_at | `requisitions.created_at` | Existe |
| requester_name | `requisitions.requested_by` → `users.name` | Existe |
| cost_center | `requisition_items.cost_center_id` → `cost_centers` (puede haber varios por requisición) | Existe (lista) |
| estimated_amount | — | **NO EXISTE**: la requisición no lleva precio. `requisition_items.unit_price` solo se llena en compras por contrato (0 de 2 partidas en dev). Lo más cercano: `quotation_summaries.total` (monto adjudicado) |
| is_repse | — | **NO EXISTE** en la requisición. Derivables: partidas de categoría de gasto de servicios (`ExpenseCategory::isService()`, código `SER`, lo que ya usa la validación REPSE en recepciones) o proveedor adjudicado con `suppliers.provides_specialized_services` |
| current_status | `requisitions.status` | Existe |
| current_step_name | Último evento de `requisition_status_histories` (`to_status`) traducido a nombre de etapa | Existe desde 2026-08-31 |
| pending_approver | `IN_APPROVAL`: `quotation_summaries.current_approver_user_id` (pendientes); demás etapas: "Compras (cola)" o el requisitor | Parcial (sin persona fuera de la autorización) |
| hours_in_current_step | `now − occurred_at` del último evento | Existe desde 2026-08-31 |
| total_cycle_hours | Σ de los intervalos entre eventos | Existe desde 2026-08-31 |
| outcome_reason | `requisitions.rejection_reason` / `cancellation_reason` | Existe |
| po_folio | `purchase_orders.folio` (`requisition_id`) | Existe |
| step_order / step_name | Orden de los eventos de `requisition_status_histories` | Existe |
| approver | `requisition_status_histories.user_id` (quien movió el estatus); en autorización, `approval_decisions` | Existe |
| entered_at / exited_at | `occurred_at` del evento / `occurred_at` del siguiente | Existe (contiguos por construcción) |
| action | `to_status` del evento de salida (`REJECTED`, `CANCELLED`…) o `approval_decisions.action` | Existe |
| step_hours | `exited_at` (o ahora) − `entered_at` | Calculada |

## 3. Prerrequisitos del Paso 0

| Prerrequisito | Estado | Impacto |
|---|---|---|
| Historial de pasos con entrada y salida | **Parcial** | Existe desde el 2026-08-31 y solo cuando el cambio pasa por Eloquent (hoy así es en todos los flujos). Antes de esa fecha no hay tiempos por paso reales; el backfill solo da hitos aproximados. Las rondas anteriores de autorización solo quedan en `approval_decisions`. |
| Marca REPSE en la requisición | **No existe** | Se puede derivar (ver pregunta 2). No se simula. |
| Motivo obligatorio en rechazo/cancelación | **Existe** | Validado en los 4 caminos: cancelar por el requisitor (`RequisitionController`, requerido), rechazo de Compras (`RequisitionWorkflowController::reject`, mín. 10), devolución desde el asistente de cotización (`QuotationWizard`, mín. 20) y cancelación por Compras (`RequisitionWorkflowController::cancel`, requerido). En dev hay 0 requisiciones sin motivo; falta revisarlo en producción con el script de verificación. |

**Datos de desarrollo:** `dev_suppliersPortalDB` tiene 1 requisición y ningún evento de historial, así que
no sirve para validar tiempos ni volumen. La validación real necesita una consulta de solo lectura en producción.

## 4. Diseño propuesto

**Etapas = intervalos entre eventos de estatus.** Cada evento de `requisition_status_histories` abre una
etapa que termina en el siguiente evento. Así Σ step_hours = total_cycle_hours por construcción
(regla F del prompt). La etapa `DRAFT` inicial es "Captura".

| Estatus | Etapa | Pendiente con |
|---|---|---|
| DRAFT | Captura (o "Corrección del requisitor" si viene de pausa o rechazo) | Requisitor |
| PENDING | Validación de Compras | Compras (cola) |
| PAUSED | Pausada por catálogo | Compras / catálogo |
| APPROVED, IN_QUOTATION | Cotización | Compras (cola) |
| QUOTED | Adjudicación | Compras (cola) |
| IN_APPROVAL | Autorización de cotización | `current_approver_user_id` de las cotizaciones pendientes |
| PENDING_BUDGET_ADJUSTMENT | Ajuste presupuestal | Responsable del centro de costo |
| COMPLETED, CANCELLED | Fin del ciclo (el reloj se detiene) | — |
| REJECTED | Devuelta al requisitor; detiene el reloj salvo que se reenvíe | Requisitor |

- Dentro de una etapa de autorización, el detalle lista las decisiones de `approval_decisions` (quién,
  cuándo, acción). Sus horas se muestran como información y no se suman aparte, para no romper el cuadre.
- **Una sola fuente**: `App\Reports\Purchasing\RequisitionTimeline` arma las etapas de una o varias
  requisiciones. La usan la tabla, el detalle `/{id}/pasos`, las exportaciones y la prueba de cuadre.
  No se toca el reporte genérico "Trazabilidad por requisitor" (`ReportingService`).

### Archivos

| Archivo | Qué hace |
|---|---|
| `app/Reports/Purchasing/RequisitionTimeline.php` | Etapas e intervalos por requisición (fuente única) |
| `app/Reports/Purchasing/RequisitionPipelineReport.php` | Filas, filtros, alcance, tarjetas y resumen por etapa/aprobador |
| `app/Reports/Purchasing/RequisitionPipelineExport.php` | Excel/CSV con `ReportSheet` (igual que RP-01/RP-03) |
| `app/Http/Requests/RequisitionPipelineReportRequest.php` | Parámetros de la sección D |
| `app/Http/Controllers/RequisitionPipelineReportController.php` | `index`, `data`, `export/{format}`, `{id}/pasos` |
| `routes/web.php` | `GET /reportes/compras/pipeline-requisiciones` (+ `/data`, `/export/{format}`, `/{requisition}/pasos`) |
| `resources/views/reports/requisition-pipeline/*` | Filtros, tarjetas, tabla DataTables server-side con fila expandible, gráfico de horas por etapa |
| Permisos | `reportes.requisition_pipeline.ver` / `.exportar` (spatie; en producción con bloque tinker aditivo) |
| Catálogo de reportes | Liga en el catálogo como RP-01/RP-02 |
| `tests/Feature/RequisitionPipelineReportTest.php` | Ya existe y prueba el reporte genérico; se renombra el nuevo a `RequisitionPipelineRc01Test.php` |
| `docs/reportes/sql/rc01_verificacion.sql` | Cuadre Σ etapas = ciclo, motivos y aprobador vs. bandeja |

**Migraciones:** ninguna obligatoria. Índice opcional, si la medición lo pide:
`requisition_status_histories (requisition_id, occurred_at)` ya existe; faltaría
`quotation_summaries (current_approver_user_id, approval_status)`.

### SQL borrador (consulta principal, SQL Server)

```sql
WITH ev AS (
    SELECT h.requisition_id, h.to_status, h.occurred_at, h.user_id,
           LEAD(h.occurred_at) OVER (PARTITION BY h.requisition_id ORDER BY h.occurred_at, h.id) AS exited_at,
           ROW_NUMBER() OVER (PARTITION BY h.requisition_id ORDER BY h.occurred_at DESC, h.id DESC) AS rn_desc
    FROM requisition_status_histories h
    WHERE h.event_type IN ('CREATED', 'STATUS_CHANGED')
), steps AS (
    SELECT ev.*,
           DATEDIFF(minute, ev.occurred_at,
               COALESCE(ev.exited_at,
                   CASE WHEN ev.to_status IN ('COMPLETED', 'CANCELLED', 'REJECTED') THEN ev.occurred_at ELSE @now END)
           ) / 60.0 AS step_hours
    FROM ev
)
SELECT r.id, r.folio, r.created_at, u.name AS requester_name, r.status,
       cur.to_status AS current_step, cur.occurred_at AS step_entered_at,
       CASE WHEN r.status IN ('COMPLETED', 'CANCELLED', 'REJECTED') THEN 0
            ELSE DATEDIFF(minute, cur.occurred_at, @now) / 60.0 END AS hours_in_current_step,
       tot.total_cycle_hours,
       COALESCE(r.rejection_reason, r.cancellation_reason) AS outcome_reason
FROM requisitions r
JOIN users u ON u.id = r.requested_by
JOIN steps cur ON cur.requisition_id = r.id AND cur.rn_desc = 1
JOIN (SELECT requisition_id, SUM(step_hours) AS total_cycle_hours FROM steps GROUP BY requisition_id) tot
     ON tot.requisition_id = r.id
WHERE r.deleted_at IS NULL
  AND r.company_id IN (@companies)                 -- alcance en servidor
  AND r.created_at >= @date_from AND r.created_at < DATEADD(day, 1, @date_to)
  AND NOT (r.status = 'DRAFT' AND NOT EXISTS (     -- excluye borradores nunca enviados
        SELECT 1 FROM requisition_status_histories x
        WHERE x.requisition_id = r.id AND x.to_status = 'PENDING'))
ORDER BY hours_in_current_step DESC;
```

Centros de costo, aprobador pendiente, monto y OC se agregan con subconsultas por `requisition_id`.
Todo con bindings (Query Builder), sin concatenar filtros.

## 5. Bugs detectados (corregidos a petición del usuario, rama `fix/requisition-cancel-and-approval-history`)

1. **Restricción de cancelación que nunca se aplica.** `RequisitionWorkflowController::cancel`
   compara `$requisition->status === RequisitionStatus::APPROVED->value` (y `DRAFT->value`). El
   accesor devuelve el enum, no el texto, así que la comparación siempre es falsa: cualquiera que pueda
   ver la requisición cancela una requisición aprobada o un borrador ajeno sin ser administrador.
2. **Se pierde la cadena de autorización anterior.** `CostCenterApprovalFlowService::initialize` borra
   los `cost_center_approval_steps` al reiniciar. El prompt pide que una devolución genere entradas
   nuevas sin sobrescribir; `approval_decisions` sí lo cumple y es la que se usará.

## 6. Preguntas abiertas (el usuario aprobó todos los supuestos el 2026-10-09)

1. **Importe estimado:** la requisición no tiene precio. SUPUESTO: mostrar el monto adjudicado
   (`quotation_summaries.total` aprobado o pendiente) y "Sin cotizar" antes de la cotización; el filtro
   por rango de importe aplica sobre ese monto.
2. **Marca REPSE:** SUPUESTO: "Sí" si alguna partida es de la categoría de servicios (`SER`), el mismo
   criterio que la validación REPSE en recepciones.
3. **Requisiciones anteriores al 2026-08-31:** SUPUESTO: aparecen con el estatus actual, pero sin horas
   por etapa ni ciclo ("Sin historial"), y quedan fuera de promedios. No se usan los eventos del backfill
   para medir horas porque sus fronteras son aproximadas.
4. **"Todas menos CERRADA":** no existe ese estatus. SUPUESTO: por defecto se excluyen `COMPLETED` y `CANCELLED`.
5. **Rechazadas:** SUPUESTO: `REJECTED` detiene el reloj; si el requisitor la reenvía, el tiempo de
   corrección cuenta como etapa y el ciclo continúa.
6. **Alcance:** SUPUESTO: Compras (`buyer`), Contraloría (`accounting`), `general_director` y `superadmin` ven
   todas las requisiciones de sus empresas asignadas; un jefe de departamento ve solo las de los centros
   de costo de los que es responsable (`cost_centers.responsible_user_id`) o de su departamento
   (`departments.manager_user_id`).
7. **Horas naturales u hábiles:** SUPUESTO del prompt: naturales.

## 7. Qué se entrega hoy y qué requiere construir algo primero

- **Se puede entregar:** tabla, detalle por etapa, filtros, tarjetas, exportaciones, permisos, alcance y
  cuadre Σ etapas = ciclo para requisiciones creadas desde el 2026-08-31.
- **Requiere decisión o control nuevo:** importe estimado real (habría que capturar precio estimado en
  la requisición), marca REPSE propia de la requisición y tiempos de requisiciones anteriores al 2026-08-31.
- **Pendiente común:** medir el tiempo de respuesta con volumen real en SQL Server (meta < 3 s).

## 8. Implementado (2026-10-09)

- `App\Reports\Purchasing\RequisitionTimeline`: etapas por requisición (fuente única). Horas redondeadas
  a 1 decimal por etapa; el ciclo es la suma de esas horas, así que Σ etapas = ciclo siempre.
  Usa solo eventos `CREATED` y `STATUS_CHANGED` (no los del backfill). Las requisiciones creadas antes
  del 2026-08-31 quedan "Sin historial".
- `App\Reports\Purchasing\RequisitionPipelineReport`: alcance, filtros, filas, tarjetas y resumen por etapa
  y por persona que resolvió la etapa.
  - "Antigüedad mayor a N días" se mide desde la creación de la requisición.
  - Orden por horas en la etapa actual (lo más atorado arriba).
- `App\Reports\Purchasing\RequisitionPipelineExport`: Excel con hojas Requisiciones, Etapas, Resumen por etapa
  y Resumen por persona; CSV con las requisiciones. Cada exportación queda en `activity_log` (log `reportes`).
- `RequisitionPipelineReportController` + `RequisitionPipelineReportRequest`; rutas
  `/reportes/compras/pipeline-requisiciones` (+ `/data`, `/export/{format}`, `/{id}/pasos`).
- Vista `resources/views/reports/requisition-pipeline/index.blade.php`: misma tabla con paginación en servidor
  que RP-01/RP-03 (no DataTables), fila expandible con las etapas y barras de horas promedio por etapa.
- Permisos `reportes.requisition_pipeline.ver` / `.exportar` en `RolePermissionSeeder`: ver y exportar para
  `buyer`, `accounting` y `general_director`; solo ver para `department_head` y `report_viewer`.
- Catálogo: RC-01 liga al reporte nuevo.
- Pruebas: `tests/Feature/RequisitionPipelineRc01Test.php` (cuadre, borradores, aprobador = bandeja, sin
  historial, REPSE, filtros combinados = exportación, 403 y alcance).
- Verificación: `docs/reportes/sql/rc01_verificacion.sql` (6 consultas que deben dar 0 filas; corren en
  SQL Server de desarrollo).

### Pendiente

- Correr `rc01_verificacion.sql` en producción, sobre todo la consulta 3 (cambios de estatus que no quedaron
  en el historial).
- Medir el tiempo de respuesta con volumen real (meta < 3 s). En desarrollo: 1 requisición, 213 ms.
- Exportaciones de más de 50 000 filas encoladas: fuera de alcance, igual que RP-01.
