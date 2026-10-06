# RP-01 · Mapeo de esquema y huecos (Paso 0)

Fecha: 2026-10-05 · Rama: `feat/rp01-budget-position`

Este documento cumple el Paso 0 y la primera parte del entregable J.1 del prompt de RP-01
(`docs/reportes/prompts_reportes_portal_proveedores.md`). Los nombres del prompt eran SUPUESTOS;
aquí se fija a qué tabla y campo real corresponde cada uno.

## 1. Cómo funciona hoy el presupuesto

- **Renglón presupuestal** = una fila de `budget_monthly_distributions`: presupuesto anual
  (`annual_budgets`, uno por centro de costo y ejercicio) + mes + cuenta de gasto
  (`expense_categories`) + subcuenta (`budget_cedulas`).
- Cada renglón lleva tres contadores: `assigned_amount` (vigente), `committed_amount`
  (apartado, aún no recibido) y `consumed_amount` (recibido).
- `budget_commitments` es el libro por documento: qué documento aparta cuánto en qué renglón
  (`quotation_summary_id`, `purchase_order_id` o `direct_purchase_order_id`), con
  `committed_amount`, `consumed_amount` y `status` (`COMMITTED`, `RECEIVED`, `RELEASED`).
- Toda la lógica vive en `App\Services\BudgetAllocationService`:
  - `reserveQuotationSummary`: al aprobar la cotización, aparta presupuesto (aquí nace el "Reservado").
  - `transferQuotationSummaryToPurchaseOrder`: al emitir la OC, el mismo compromiso pasa a la OC.
  - `commitOrder` / `reserveDirectPurchaseOrder`: comprometen OC y OC directas.
  - `consumeOrder`: por cada recepción (también parcial) mueve de comprometido a consumido.
  - `releaseOrder` / `releaseQuotationSummary` / `releaseDirectPurchaseOrder`: cancelaciones.
- **El bloqueo** (el "guardián" del prompt) es `BudgetAllocationService::checkAvailability()`,
  usado en aprobación de cotización, adjudicación de RFQ, OC directa, requisición por contrato y
  alertas RP-03. El disponible sale de `BudgetMonthlyDistribution::getAvailableAmount()`
  = `max(0, asignado − consumido − comprometido)`.
- **La requisición no aparta presupuesto.** Una requisición aprobada sin cotización no toca
  ningún contador.

## 2. Tabla de mapeo

| Campo del prompt | Tabla.campo real | Estado |
|---|---|---|
| company_rfc / company_name | `companies.rfc` (nullable) / `companies.name` | Existe |
| cost_center_code / name | `cost_centers.code` / `cost_centers.name` | Existe |
| budget_line_code / name | `expense_categories.code/name` + `budget_cedulas.name` (la subcuenta no tiene código) | Existe (renglón = cuenta + subcuenta) |
| accounting_account | — | **NO EXISTE**: ni `expense_categories` ni `budget_cedulas` se ligan a `ledger_accounts` |
| responsible_name | `cost_centers.responsible_user_id` → `users.name` | Existe |
| fiscal_year / period_month | `annual_budgets.fiscal_year` / `budget_monthly_distributions.month` | Existe |
| authorized_amount | `budget_distribution_baselines.original_amount` | Parcial: solo renglones con base capturada (RP-02) |
| increases / decreases | `budget_movements` + `budget_movement_details` aprobados | Existe (se reutiliza la reconstrucción de RP-02) |
| current_budget | `budget_monthly_distributions.assigned_amount` | Existe (los movimientos aprobados ya lo modifican) |
| reserved | `budget_commitments` con `quotation_summary_id` y estatus `COMMITTED` | Existe (ver pregunta 1) |
| committed | `budget_commitments` con `purchase_order_id` o `direct_purchase_order_id`, estatus `COMMITTED`: `committed_amount − consumed_amount` | Existe |
| accrued (devengado) | `budget_monthly_distributions.consumed_amount` (= Σ `budget_commitments.consumed_amount`) | Existe, solo por recepción (ver pregunta 3) |
| paid | — | **NO EXISTE**: el portal no registra pagos |
| available | `assigned − committed − consumed` | Existe |
| progress_pct, traffic_light, projected_close | calculadas | Por construir (bloque 2) |
| cancelled_po_amount | `budget_commitments` con estatus `RELEASED` | Existe |
| budget_ledger_entries | `budget_commitments` cumple ese papel | No se crea tabla nueva |
| budget_position_snapshots | — | No existe (bloque 5) |
| fn_budget_position | — | Se crea como clase PHP `BudgetPositionService` (puntos 3–5) |

## 3. Estado de los prerrequisitos del Paso 0

| Prerrequisito | Estado | Impacto |
|---|---|---|
| Catálogo de renglones con cuenta contable | Parcial | Sin columna de cuenta contable hasta ligar el catálogo `ledger_accounts` (punto 19) |
| Presupuesto autorizado por empresa + centro + renglón + mes | Existe | — |
| Lógica de consumo y bloqueo | Existe | Se encapsula en `BudgetPositionService` sin cambiar reglas |
| Movimientos presupuestales | Existe | Autorizado original depende de la base capturada (`SIN_BASE` si falta) |
| Registro de pagos | **No existe** | "Pagado" se muestra como **no disponible** (NULL explícito), nunca 0. Mientras tanto, lo recibido queda en Devengado |

## 4. Identidad de cuadre con los datos reales

Por renglón:

```
Reservado + Comprometido + Devengado + Disponible + Sin conciliar = Vigente
```

- `Pagado` no participa mientras no existan pagos (queda dentro de Devengado).
- `Sin conciliar` = contador `committed_amount` del renglón − Σ compromisos abiertos de
  `budget_commitments`. Debe ser 0; si no lo es, hay datos históricos que se movieron sin
  compromiso por documento. **Se muestra, no se esconde.**
- `Disponible` puede ser negativo (sobregiro con excepción autorizada). El bloqueo sigue usando
  `max(0, disponible)` como hoy.

## 5. Posibles bugs detectados (no corregidos, regla 8)

1. **Cancelar una OC con recepción parcial.** `releaseLine()` libera el `committed_amount`
   completo del compromiso, aunque una parte ya se consumió. Ejemplo: OC de 1,000 recibida al
   40 %: el contador comprometido está en 600 y se intentan liberar 1,000, así que
   `releaseCommitment()` regresa `false` y la cancelación lanza una excepción. Debería liberar
   `committed_amount − consumed_amount`.
2. **Mensaje con codificación rota** en `releaseCommittedQuotationSummaryCommitments()`:
   `"cÃ©dula"`.

## 6. Preguntas abiertas para Contraloría (con el supuesto que se aplica)

1. **¿"Reservado" es la cotización aprobada que aún no tiene OC?** El prompt dice "requisiciones
   aprobadas sin OC", pero en el portal la requisición no aparta presupuesto.
   SUPUESTO: Reservado = cotización aprobada sin OC.
2. **¿"Pagado" se muestra como "no disponible" hasta que Tesorería registre pagos en el portal?**
   SUPUESTO: sí; lo recibido queda en Devengado.
3. **¿Devengado se reconoce con la recepción?** Hoy solo la recepción mueve el importe; la
   factura no. SUPUESTO: con la recepción, al precio de la OC.
4. **¿El presupuesto se controla sin IVA?** El prompt supone que sí, pero hoy se compromete el
   `total` de la partida (con IVA). SUPUESTO: se respeta lo que hace el portal (con IVA) y no
   se cambia la regla sin autorización.
5. **¿Los centros de costo de consumo libre (`FREE_CONSUMPTION`) aparecen en el reporte?**
   No tienen renglones presupuestales. SUPUESTO: no aparecen en RP-01.

## 7. Implementado (puntos 3 a 5 del plan)

- `App\Services\BudgetPositionService`
  - `positions($filters)`: posición por renglón de presupuestos `APROBADO`. Filtros: `company_ids`,
    `fiscal_year`, `months`, `cost_center_ids`, `expense_category_ids`, `budget_cedula_ids`.
  - `fromDistributions($distributions)`: el mismo cálculo para renglones ya cargados (lo usa el bloqueo).
  - Devuelve por renglón: `current_budget`, `reserved`, `committed`, `accrued`, `paid` (siempre `null`),
    `available` (puede ser negativo), `unreconciled` y `released` (informativo, no suma).
- `BudgetMonthlyDistribution::getBalanceAmount()`: única fórmula del saldo;
  `getAvailableAmount()` es `max(0, saldo)`.
- `BudgetAllocationService::checkAvailability()` toma el disponible de `BudgetPositionService`
  (mismo resultado que antes: cada subcuenta aporta `max(0, disponible)`).
- Pruebas: `tests/Feature/BudgetPositionServiceTest.php` (cuadre con 20 combinaciones de filtros,
  ningún compromiso en dos montos, bloqueo = reporte, sobregiro y descuadres visibles).

## 8. Columnas calculadas (2026-10-06)

`positions()` agrega por renglón (el bloqueo, que usa `fromDistributions()`, no paga estas consultas):

| Columna | Cálculo |
|---|---|
| `authorized_amount` | Σ `budget_distribution_baselines.original_amount` de la línea; `null` si no hay base (`SIN_BASE`), nunca 0 |
| `increases` / `decreases` | Σ montos positivos / negativos de `budget_movement_details` de movimientos `APROBADO` |
| `budget_difference` | autorizado + ampliaciones − reducciones − vigente (misma conciliación de RP-02) |
| `baseline_status` | `CONCILIA`, `DIFERENCIA` o `SIN_BASE` |
| `exercised_total` | comprometido + devengado (no incluye reservado; pagado no existe) |
| `consumed_total` | vigente − disponible (= reservado + comprometido + devengado + sin conciliar), mismo criterio que el bloqueo |
| `progress_pct` | consumido / vigente, 4 decimales; `null` si el vigente es 0 |
| `traffic_light` | `VERDE` < 80 %, `AMARILLO` 80–99.99 %, `ROJO` ≥ 100 %; umbrales en `config/budget_position.php` (`BUDGET_POSITION_YELLOW`, `BUDGET_POSITION_RED`). Vigente 0 con consumo = `ROJO` |

- La llave de línea es centro + ejercicio + mes + cuenta + subcuenta. Si una línea tiene varias
  distribuciones, autorizado y movimientos se asignan a la primera (menor id) para no duplicarlos.
- `summarize($positions)`: subtotales y totales sumando montos y recalculando % y semáforo (no promedia).
  El autorizado del grupo es `null` si algún renglón no tiene base; `lines_without_baseline` dice cuántos.
- `accumulated($filters, $periodMonth, $today)`: vista `ACU` por centro + cuenta + subcuenta (meses 1 al
  mes elegido) con `projected_close` = consumido acumulado + promedio de ejercido de los últimos 3 meses
  cerrados × meses restantes. Con menos historia usa los meses cerrados que haya
  (`projection_basis_months`); sin meses cerrados la proyección es `null`.

## 9. Pantalla (2026-10-06)

- Ruta: `GET /reportes/presupuesto/ejercido` (`budget-vs-actual-reports.index`), con endpoints de
  datos (`.data`) y de detalle por monto (`.detail`). Enlazada desde el catálogo de reportes solicitados.
- Clase `App\Reports\Budget\BudgetVsActualReport` sobre `BudgetPositionService`; parámetros en
  `BudgetVsActualReportRequest` (ejercicio, mes, vista MES/ACU, empresas, centros, cuentas,
  responsable, OC canceladas). Por omisión: ejercicio y mes en curso, vista del mes.
- Pantalla: tarjetas KPI (vigente, consumido, disponible, % avance, renglones en rojo), tabla con
  subtotales por centro y por empresa más total general, semáforo, proyección de cierre y barra
  apilada al 100 % del vigente. Clic en Reservado, Comprometido, Devengado u OC canceladas abre
  los documentos (OC, OCD o requisición de la cotización) con liga al documento.
- Pagado se muestra como "N/D"; "Sin base" cuando el renglón no tiene autorizado capturado.
- Permisos `reportes.budget_vs_actual.ver` y `.exportar` en `RolePermissionSeeder`
  (accounting y general_director: ver y exportar; department_head y report_viewer: ver).
  **En cada ambiente hay que correr `php artisan db:seed --class=RolePermissionSeeder`.**
- Alcance (en servidor): superadmin ve todo; accounting y general_director ven todo, o solo sus
  empresas asignadas (`company_user`) si tienen; los demás, solo centros donde son responsables.
- Verificado en solo lectura contra SQL Server (`dev_suppliersPortalDB`): consultas correctas,
  identidad en cero y detalle = fila. La base de desarrollo tiene muy pocos renglones de 2026, así
  que el tiempo de respuesta con un año de operación real sigue pendiente de medir.

### Pendiente

- Exportación Excel (hoja resumen + detalle por documento) y CSV, con bitácora de exportaciones.
- Fecha de corte histórica (`as_of_date`) y foto diaria `budget_position_snapshots` (job 23:55).
- Rango de fechas `date_from`/`date_to` (hoy el periodo se elige por ejercicio y mes).
- Cuenta contable: sigue sin existir la liga renglón → catálogo contable.
- PDF ejecutivo por centro de costo (opcional en la especificación).
