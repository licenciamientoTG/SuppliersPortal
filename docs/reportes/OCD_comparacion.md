# OCD: comparación Claude vs Codex

Fecha: 2026-11-04 · Base `main` 80f1255 · Fuentes: `OCD_estados_reglas_claude.md` (13 huecos) y `OCD_estados_reglas_codex.md` (22 huecos). Ninguno de los dos ejecutó pruebas ni tocó la BD. Claude verificó en el código los hallazgos críticos de Codex (marcados ✔).

## 1. En qué coinciden

- **Estados:** los mismos 11 estados, más `PAID`, que solo aparece en la validación de cancelar. Los dos detectan que `DRAFT` y `APPROVED` no se usan en el flujo real.
- **Flujo principal:** crear → `PENDING_APPROVAL` → aprobación por centro de costo o por jerarquía, con delegados → `ISSUED` → entrega del proveedor → `DELIVERED_PENDING_RECEPTION` → `PARTIALLY_RECEIVED` / `RECEIVED`.
- **Salidas del flujo:** `REJECTED`, `RETURNED` (también desde `ISSUED` sin recepciones), `CANCELLED` y `CLOSED_BY_INACTIVITY` a los 7 días.
- **Presupuesto:** se reserva al enviar, se compromete al emitir, se consume con la recepción y se libera al rechazar, devolver, cancelar o cerrar.
- **Huecos que encontramos los dos:**

| Tema | Claude | Codex |
|---|---|---|
| Al reactivar, la OCD queda sin aprobador y nadie puede aprobarla | H01 | H-02 |
| Reactivar depende de IDs fijos (2, 3, 20) | H02 | H-22 |
| El límite del aprobador se compara con el total en USD/EUR, no en MXN | H03 | H-11 |
| La recepción no es atómica con el consumo presupuestal ni la provisión | H07 | H-05 |
| Hay estados muertos (`DRAFT`, `APPROVED`, `PAID`) | H09 | H-14 |
| Faltan pruebas y algunas están desactualizadas | H13 | H-13 |
| Cancelar permite estados amplios | H05 | H-22 |

## 2. Lo que solo encontró Codex (más grave primero)

| Codex | Hallazgo | ¿Verificado por Claude? | Impacto |
|---|---|---|---|
| **H-01** ✅ CORREGIDO | **Las OCD con aprobador por jerarquía (sin flujo por centro de costo) no se pueden aprobar.** `approve()` siempre llama a `advance()`, que hace `firstOrFail()` sobre los pasos. Sin pasos, lanza una excepción y el usuario ve "Error al aprobar la OCD: No query results…". | ✔ `DirectPurchaseOrderController.php:239`, `CostCenterApprovalFlowService.php:56-59` | **Crítico:** bloquea la emisión. Explica por qué `ApprovalDelegationTest` está entre las 36 pruebas que fallan. |
| **H-03** ✅ CORREGIDO | **Al emitir una OCD nunca se guarda `issued_at`.** Solo se llenan `approved_by` y `approved_at`. | ✔ Ningún código asigna `issued_at` a una OCD | **Alto:** el reporte RC-02 de OC emitidas filtra `whereNotNull('issued_at')`, así que las OCD no aparecen. También falla la antigüedad en la lista de entregas del proveedor. |
| **H-04** ✅ CORREGIDO | **El consumo presupuestal de la recepción usa cantidades viejas.** `storeDirect` precarga `items`, el servicio incrementa otras instancias y `consumeOrder` usa `loadMissing` sobre las ya cargadas. | ✔ `ReceptionController.php:347`, `BudgetAllocationService.php:629` | **Alto:** cada recepción consume según el avance anterior, y en la recepción final queda la última parte sin consumir. Conviene confirmarlo con una prueba. |
| H-08 | Guardar una factura no valida el estado de la OCD. Solo el selector filtra, así que manipulando la petición se podría facturar una OCD cancelada o rechazada. | ✔ `SupplierInvoiceController.php:68` solo revisa el proveedor | Medio |
| H-09 | La conciliación de facturas no compara la moneda, y el RFC receptor se valida contra cualquier empresa activa en lugar de la empresa de la OCD | No verificado | Medio |
| H-10 | La recepción acepta un `receiving_location_id` distinto al de la OCD, sin validar que esa ubicación esté activa ni bloqueada | No verificado | Medio |
| H-06, H-07, H-20 | Riesgos de concurrencia: rechazo con un aprobador anterior, devolución, cierre o entrega sin bloqueo de fila, y saldos presupuestales sin bloqueo | No verificado (son teóricos) | Medio a bajo |
| H-12 | Los pasos de aprobación se quedan en `PENDING` cuando la OCD se rechaza o se devuelve, y no se avisa al siguiente responsable cuando la orden avanza | No verificado | Medio |
| H-15 | Una devolución hecha por un delegado no guarda la `ApprovalDecision` con el titular, y cancelar no avisa a nadie | No verificado | Bajo |
| H-16 | El formulario filtra empresas y ubicaciones activas, pero el backend solo valida que existan | No verificado | Bajo |
| H-17 | Al reenviar no se limpia `inactivity_warning_sent_at`, así que la OCD puede cerrarse sin una nueva alerta | No verificado | Medio |
| H-18 | Una recepción no conforme quita el bloqueo de la estación y detiene las alertas, aunque falte reponer | No verificado | Medio |
| H-19 | La vista de impacto presupuestal muestra el monto comprometido original sin restar lo consumido | No verificado | Bajo |
| H-21 | Si la transacción se revierte, los archivos de cotización ya borrados no se recuperan | No verificado | Bajo |

## 3. Lo que solo encontró Claude

| Claude | Hallazgo |
|---|---|
| H04 | No se bloquea la **autoaprobación**: el creador puede ser el aprobador resuelto o el delegado de su propio aprobador. |
| H06 | Cancelar, reactivar y descargar el PDF todavía usan `abort(422)`, así que el usuario ve la pantalla de error en lugar de un mensaje. |
| H08 | Una sola entrega sin capturar bloquea a **todos** los proveedores de esa estación. |
| H10 | `update()` valida `company_id` pero no lo guarda. |
| H11 | El usuario ve los mensajes técnicos de las excepciones (`$e->getMessage()`). |
| H12 | Si el correo falla (cuota de Gmail), el aviso preventivo queda marcado como enviado y no se reintenta. |

## 4. Diferencias de enfoque

- **Codex** fue más profundo: llegó a facturación, conciliación, concurrencia y archivos, y marca cada punto como VERIFICADO o INFERENCIA. Su documento ocupa 74 KB, es más difícil de leer y casi no da prioridades.
- **Claude** fue más corto y priorizado por severidad. Se concentró en el flujo de estados y en los permisos, pero **se le escaparon los tres bugs más graves** (H-01, H-03 y H-04 de Codex).
- **Conclusión:** el informe de Codex es la mejor fuente de verdad. El de Claude sirve como resumen ejecutivo y aporta la autoaprobación y el bloqueo por estación.

## 5. Prioridad sugerida (los dos informes combinados)

1. ✅ ~~**Aprobar OCD sin pasos de flujo por centro de costo**~~ — corregido 2026-11-04. (Codex H-01). Es crítico y bloquea la operación.
2. ✅ ~~**Guardar `issued_at` al emitir**~~ — corregido 2026-11-04 (comando de relleno incluido). y rellenar las OCD ya emitidas (Codex H-03).
3. ✅ ~~**Consumo presupuestal en la recepción**~~ — corregido 2026-11-04 con prueba. (Codex H-04). Primero hay que reproducirlo con una prueba.
4. **Reactivación:** volver a ejecutar `prepareApprovalFlow` y cambiar los IDs fijos por un permiso (Claude H01/H02, Codex H-02).
5. **Límite del aprobador en MXN** (Claude H03, Codex H-11).
6. **Validar el estado de la OCD al guardar una factura** (Codex H-08).
7. **Bloquear la autoaprobación** (Claude H04).
8. **Sustituir `abort(422)` por mensajes claros** (Claude H06).
9. **Pruebas de rechazo, devolución, cancelación, inactividad y flujo multipaso de OCD.**

## 6. Estado de las correcciones (2026-11-04)

| Hallazgo | Corrección | Prueba |
|---|---|---|
| Codex H-01 | `DirectPurchaseOrderController::approve()` solo llama a `advance()` si hay pasos PENDING; si no, aprueba en un paso y registra la `ApprovalDecision`. `CostCenterApprovalFlowService::initialize()` marca SUPERSEDED los pasos de la ronda anterior cuando la nueva ronda va por jerarquía (corrige en parte Codex H-12). | `ApprovalDelegationTest`: delegado aprueba OCD; cambio de flujo CC → jerarquía |
| Codex H-03 | `approve()` asigna `issued_at`. Comando `direct-purchase-orders:backfill-issued-at` (`--dry-run`) llena `issued_at = approved_at` en ISSUED, DELIVERED_PENDING_RECEPTION, PARTIALLY_RECEIVED, RECEIVED, y en CANCELLED/RETURNED que se emitieron. No toca `updated_at`. | `BackfillDirectPurchaseOrderIssuedAtTest` |
| Codex H-04 | `ReceptionService::receive()` recarga `items` antes de `consumeOrder()` (aplica también a OC estándar). | `DirectPurchaseOrderBudgetTraceTest::test_full_reception_with_preloaded_items_consumes_the_whole_commitment` |
| Hallado durante la corrección | Una excepción en el aviso a compradores/proveedor (post-commit) o en el aviso de acción delegada (`afterCommit`) se mostraba como "Error al aprobar la OCD" aunque ya estaba aprobada. Ambos quedan en try/catch con `Log::error`. | `ApprovalDelegationTest` (`assertSessionHasNoErrors`) |

Revisión de Codex: `OCD_fix_revision_codex.md` — primera versión rechazada, segunda **aprobada con observaciones** (bajas, de cobertura). Suite completa: 35 fallidas / 760 pasan; las 35 son las mismas preexistentes.

Siguen abiertos: el resto de la lista de la sección 5 (puntos 4 a 9) y los hallazgos de Codex H-02, H-05 a H-22.
