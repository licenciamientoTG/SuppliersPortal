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
