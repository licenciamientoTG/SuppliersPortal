# OCD (Órdenes de Compra Directas): estados, reglas de negocio y casos

Autor: Claude · Fecha: 2026-11-04 · Base: `main` 80f1255 · Solo lectura de código (no se ejecutó nada contra la BD).

## 1. Resumen

- Modelo `app/Models/DirectPurchaseOrder.php`, tabla `odc_direct_purchase_orders`, folio `OCD-AAAA-NNNN`.
- Flujo real: **Crear → PENDING_APPROVAL → (aprobación de uno o varios pasos) → ISSUED → entrega del proveedor → DELIVERED_PENDING_RECEPTION → recepción → PARTIALLY_RECEIVED / RECEIVED**. Salidas laterales: REJECTED, RETURNED (corregir y reenviar), CANCELLED y CLOSED_BY_INACTIVITY (7 días sin decisión).
- El presupuesto se **reserva** al enviar a aprobación, se **compromete** al emitir, se **consume** proporcionalmente con cada recepción y se **libera** al rechazar, devolver, cancelar o cerrar por inactividad.
- `DRAFT` y `APPROVED` existen en el catálogo pero el flujo normal no los usa: `store()` crea directo en PENDING_APPROVAL y la última aprobación pasa directo a ISSUED.
- Cobertura de pruebas débil: hay pruebas de trazas presupuestales, delegación, recepción y acuse del proveedor, pero **no hay pruebas** de rechazar, devolver, cancelar, reactivar, cierre por inactividad ni del flujo multipaso de OCD.

## 2. Estados

| Estado | Significado | Cómo se llega | Desde | Archivo:línea |
|---|---|---|---|---|
| DRAFT | Borrador | `update()` de una OCD que no estaba RETURNED | DRAFT | `DirectPurchaseOrderController.php:508` |
| PENDING_APPROVAL | Esperando autorizador | `store()`, `submit()`, `update()` de RETURNED, `reactivateDirect()` | (nuevo), DRAFT, RETURNED, CLOSED_BY_INACTIVITY | `DirectPurchaseOrderController.php:117,175,508`; `PurchaseOrderController.php:263` |
| APPROVED | Aprobada (sin uso real) | Nunca se asigna en el flujo actual | — | modelo, catálogo |
| ISSUED | Emitida al proveedor | Última aprobación (`approve()` → `advance()` sin pasos pendientes) | PENDING_APPROVAL | `DirectPurchaseOrderController.php:213` |
| REJECTED | Rechazada (final) | `reject()` | PENDING_APPROVAL | `DirectPurchaseOrderController.php:277` |
| RETURNED | Devuelta para corrección | `return()` | PENDING_APPROVAL, ISSUED (sin recepciones) | `DirectPurchaseOrderController.php:357` |
| DELIVERED_PENDING_RECEPTION | Proveedor registró entrega; la estación tiene 3 días hábiles para capturar | `SupplierDeliveryController::store()` | ISSUED, PARTIALLY_RECEIVED | `SupplierDeliveryController.php:170` |
| PARTIALLY_RECEIVED | Recepción parcial | `ReceptionService::receive()` | ISSUED, PARTIALLY_RECEIVED, DELIVERED_PENDING_RECEPTION | `ReceptionService.php:219-245` |
| RECEIVED | Recibida completa | `ReceptionService::receive()` | igual que arriba | `ReceptionService.php:219-245` |
| CANCELLED | Cancelada | `cancelDirect()` | cualquiera salvo CANCELLED/RECEIVED/PAID | `PurchaseOrderController.php:35-83` |
| CLOSED_BY_INACTIVITY | Cerrada por no decidirse en 7 días | comando `purchase-orders:close-inactive` | PENDING_APPROVAL | `CloseInactivePurchaseOrders.php` (`closeDirectPurchaseOrder`) |
| PAID | Solo aparece en la validación de cancelar; ninguna ruta lo asigna a OCD | — | — | `PurchaseOrderController.php:57` |

## 3. Transiciones

| # | De | A | Disparador | Quién puede | Precondiciones | Efectos secundarios | Archivo:línea |
|---|---|---|---|---|---|---|---|
| T1 | (nuevo) | PENDING_APPROVAL | `store()` | Usuario con módulo `purchase_orders` | Request válido (RN-01..RN-06); hay presupuesto o excepción aprobada; se resuelve un aprobador | Folio, `application_month`, tipo de cambio, `submitted_at`; `prepareApprovalFlow` (flujo por CC o autorizador por jerarquía); **reserva** presupuesto; notifica aprobador + delegados | `DirectPurchaseOrderController.php:117`, `:594` |
| T2 | DRAFT/RETURNED | PENDING_APPROVAL | `submit()` | Solo el creador | ≥1 partida | `prepareApprovalFlow`, notificación | `:175` |
| T3 | RETURNED | PENDING_APPROVAL | `update()` | Creador (FormRequest `authorize`) | Estado DRAFT/RETURNED y `created_by` = usuario | Recrea partidas y documentos; `submitted_at = now`; `prepareApprovalFlow` (libera reserva anterior y reserva de nuevo) | `:484`; `SaveDirectPurchaseOrderRequest.php:63` |
| T4 | DRAFT | DRAFT | `update()` | Creador | igual | Recrea partidas, no reserva | `:508` |
| T5 | PENDING_APPROVAL | PENDING_APPROVAL (siguiente paso) | `approve()` con pasos pendientes | Aprobador asignado o su delegado (`isApproverFor`) | Bloqueo de fila; estado PENDING_APPROVAL | Marca el paso APPROVED, asigna el siguiente responsable, notifica | `:213`; `CostCenterApprovalFlowService.php:54` |
| T6 | PENDING_APPROVAL | ISSUED | `approve()` último paso | Aprobador o delegado | igual | Reserva si no hay compromiso COMMITTED; `commitOrder`; hook `syncCommitmentTrace`; notifica compradores y proveedor | `:213`; `DirectPurchaseOrder::booted` |
| T7 | PENDING_APPROVAL | REJECTED | `reject()` | Aprobador o delegado | Comentario 50–500 caracteres | Libera presupuesto; notifica creador y compradores | `:277` |
| T8 | PENDING_APPROVAL | RETURNED | `return()` | Aprobador o delegado | Comentario | Libera presupuesto; notifica creador | `:357` |
| T9 | ISSUED | RETURNED | `return()` | Superadmin o `approved_by` | Sin recepciones | Hook `releaseTrace` + liberación | `:357`; `booted` |
| T10 | ISSUED/PARTIALLY_RECEIVED | DELIVERED_PENDING_RECEPTION | Proveedor registra entrega | Proveedor dueño de la OCD (guard `supplier`) | Remisión PDF/JPG/PNG ≤10 MB; fecha no futura; la estación no tiene entregas pendientes de captura | Evidencia, `reception_deadline_at` = +3 días hábiles, jobs de alerta día 0/2/3; hook `consumeOrder` (consume 0 si no hay recepción) | `SupplierDeliveryController.php:112-195` |
| T11 | ISSUED/PARTIALLY_RECEIVED/DELIVERED_PENDING_RECEPTION | PARTIALLY_RECEIVED o RECEIVED | Captura de recepción | Rol receptor, o el solicitante de la orden | Ubicación asignada, activa y sin bloqueo; cantidades ≤ pendiente; remisión obligatoria | Solo suman las cantidades **conformes**; `consumeOrder` proporcional; provisión financiera; notificaciones | `ReceptionService.php:35-140` |
| T12 | casi cualquiera | CANCELLED | `cancelDirect()` | Comprador, superadmin, `approved_by` o aprobador asignado | Motivo ≥10 caracteres; estado ≠ CANCELLED/RECEIVED/PAID | Libera presupuesto | `PurchaseOrderController.php:35-83` |
| T13 | PENDING_APPROVAL | CLOSED_BY_INACTIVITY | Cron diario | Sistema | `submitted_at` ≤ ahora − 7 días | Libera presupuesto, `assigned_approver_id = null`, notifica aprobador y creador. Aviso preventivo a los 4 días (una sola vez) | `CloseInactivePurchaseOrders.php` |
| T14 | CLOSED_BY_INACTIVITY | PENDING_APPROVAL | `reactivateDirect()` | Solo usuarios con id 2, 3 o 20 (lista fija en código) | — | Reserva presupuesto **sin** validar disponibilidad ni reconstruir el flujo de aprobación | `PurchaseOrderController.php:28,234,263` |

## 4. Reglas de negocio

| RN | Regla | Dónde | ¿Prueba? |
|---|---|---|---|
| RN-01 | Proveedor, empresa, ubicación de recepción y ≥1 partida son obligatorios | `SaveDirectPurchaseOrderRequest::rules` | Parcial (`DirectPurchaseOrderOptionalQuotationTest`) |
| RN-02 | Justificación de 100 a 2000 caracteres | idem | No |
| RN-03 | Cada partida: producto, descripción ≤500, cantidad y precio 0.01–999,999.99, CC, cuenta, cédula, IVA 0/8/16 | idem | Parcial |
| RN-04 | El usuario solo puede usar subcuentas a las que tiene acceso presupuestal | `prepareForValidation` (`BudgetAccessService`) | No |
| RN-05 | Cotización opcional (PDF/imagen ≤5 MB); hasta 5 documentos de soporte ≤5 MB | idem | Sí (cotización opcional) |
| RN-06 | Moneda MXN/USD/EUR; tipo de cambio congelado al crear/editar | `store`/`update` (`ExchangeRate::rateToMxn`) | Indirecta (RC-02) |
| RN-07 | Mes de aplicación = hoy + días estimados de entrega (default: promedio del proveedor o 30) | `store`/`update` | No |
| RN-08 | Debe haber presupuesto disponible; se tolera el exceso cubierto por una `BudgetException` aprobada | `ensureBudgetAvailability` | No (para OCD) |
| RN-09 | Aprobador: flujo por centro de costo si existe; si no, jefe en jerarquía cuyo límite cubra el total | `prepareApprovalFlow`, `CostCenterApprovalFlowService`, `AuthorizerResolutionService` | Parcial (`ApprovalDelegationTest`) |
| RN-10 | Un delegado activo puede aprobar en nombre del titular; no se propaga a segundo nivel | `ApprovalDelegationService::canAct` | Sí |
| RN-11 | Solo el creador edita o reenvía, y solo en DRAFT/RETURNED | FormRequest `authorize`, `submit()`, `edit()` | No |
| RN-12 | Rechazar exige comentario de 50 a 500 caracteres | `reject()` | No |
| RN-13 | Devolver una OCD emitida solo si no tiene recepciones, por superadmin o quien la aprobó | `return()` | No |
| RN-14 | Cancelar exige motivo ≥10 y no aplica a CANCELLED/RECEIVED/PAID | `cancelDirect()` | No |
| RN-15 | 7 días en PENDING_APPROVAL → cierre automático; aviso a los 4 días | comando de inactividad | No |
| RN-16 | Solo 3 usuarios fijos pueden reactivar | `REACTIVATION_ALLOWED_USER_IDS` | No |
| RN-17 | El proveedor registra entrega con remisión; la estación tiene 3 días hábiles; si una estación tiene entregas sin capturar, se bloquean nuevas entregas en ella | `SupplierDeliveryController::store` | Parcial (`ReceptionFlowTest` plazo/alertas) |
| RN-18 | La recepción no puede exceder lo pendiente; lo no conforme no cuenta | `ReceptionService` | Sí |
| RN-19 | Una OCD sin remisión no se puede recibir | `ReceptionController` | Sí |
| RN-20 | El presupuesto se consume en proporción a lo recibido, de forma acumulativa | `BudgetAllocationService::consumeOrder` | Parcial (`BudgetAllocationPerItemTest`) |
| RN-21 | Reenviar tras devolución deja un solo compromiso abierto y por el monto nuevo | `syncCommitmentTrace`, `releaseTrace` | Sí (`DirectPurchaseOrderBudgetTraceTest`) |
| RN-22 | PDF solo en ISSUED, PARTIALLY_RECEIVED, RECEIVED o DELIVERED_PENDING_RECEPTION | `PurchaseOrderController` PDF | Sí (`PurchaseOrderPdfViewTest`) |
| RN-23 | Facturas solo para OCD en ISSUED, PARTIALLY_RECEIVED, RECEIVED o DELIVERED_PENDING_RECEPTION | `SupplierInvoiceController:119`, `FinanceInvoiceController:133` | Parcial (tolerancia de facturas) |
| RN-24 | Advertencia REPSE si la OCD tiene partidas de servicio y el REPSE del proveedor está vencido o vence en ≤30 días (no bloquea) | `ReceptionService::validateRepseIfService` | No |

## 5. Casos y resultados esperados

| # | Caso | Resultado esperado | ¿Prueba? |
|---|---|---|---|
| C01 | Crear OCD válida con presupuesto | PENDING_APPROVAL, folio OCD-AAAA-NNNN, reserva creada, aprobador notificado | Parcial |
| C02 | Crear OCD con justificación de 99 caracteres | Error de validación en español, no se crea | No |
| C03 | Crear OCD que excede presupuesto sin excepción | Error, no se crea ni reserva | No |
| C04 | Crear OCD que excede presupuesto con excepción aprobada | Se crea y reserva | No |
| C05 | Usuario sin empleado/jefe ligado crea OCD | Error claro "no se encontró empleado…", sin OCD huérfana | No |
| C06 | Partida con subcuenta a la que no tengo acceso | Error de validación | No |
| C07 | Aprobador aprueba (flujo de 1 paso) | ISSUED, compromiso COMMITTED, notifica proveedor y compradores | Parcial |
| C08 | Flujo de 2 pasos: primer aprobador aprueba | Sigue PENDING_APPROVAL, `assigned_approver_id` = segundo | No |
| C09 | Delegado aprueba por el titular | Igual que C07, con actor y titular registrados | Sí |
| C10 | Usuario ajeno intenta aprobar | 403 | No |
| C11 | Rechazar con 49 caracteres | Error de validación | No |
| C12 | Rechazar válido | REJECTED, presupuesto liberado, creador notificado | No |
| C13 | Devolver desde PENDING_APPROVAL | RETURNED, presupuesto liberado | No |
| C14 | Creador corrige y reenvía una devuelta | PENDING_APPROVAL, un solo compromiso, monto nuevo | Sí (traza) |
| C15 | Otro usuario intenta editar OCD ajena | 403 (FormRequest) | No |
| C16 | Editar OCD en PENDING_APPROVAL | 403 | No |
| C17 | Devolver OCD emitida sin recepciones (por quien aprobó) | RETURNED, compromiso liberado | Parcial (traza) |
| C18 | Devolver OCD emitida con recepciones | Rechazado con mensaje | No |
| C19 | Cancelar OCD emitida con motivo ≥10 | CANCELLED, presupuesto liberado | No |
| C20 | Cancelar OCD RECEIVED | Bloqueado (hoy con `abort(422)` en lugar de un mensaje) | No |
| C21 | OCD 7 días sin decisión, cron | CLOSED_BY_INACTIVITY, liberada, notificados | No |
| C22 | OCD a los 4 días | Un solo aviso preventivo (`inactivity_warning_sent_at`) | No |
| C23 | Cron con `--dry-run` | No cambia nada | No |
| C24 | Usuario 2/3/20 reactiva | PENDING_APPROVAL, reserva | No |
| C25 | Otro usuario intenta reactivar | 403 | No |
| C26 | Proveedor registra entrega de OCD emitida | DELIVERED_PENDING_RECEPTION, plazo +3 días hábiles, jobs de alerta | Parcial |
| C27 | Proveedor registra entrega en estación con pendientes | Error "Esta estación tiene entregas pendientes de captura…" | No |
| C28 | Proveedor intenta entregar OCD de otro proveedor | 403 | No |
| C29 | Recepción parcial conforme | PARTIALLY_RECEIVED, consumo proporcional | Parcial |
| C30 | Recepción con partida no conforme | No suma cantidad; la OCD no llega a RECEIVED | Sí |
| C31 | Recepción mayor a lo pendiente | Rechazada | Sí |
| C32 | Recepción final | RECEIVED, `received_by/at`, consumo total | Parcial |
| C33 | Descargar PDF de OCD en PENDING_APPROVAL | Bloqueado | Sí |
| C34 | OCD en USD cuyo total en MXN supera el límite del aprobador | Debe escalar al nivel que cubra el monto en MXN (ver H03) | No |

## 6. Huecos, inconsistencias y riesgos

| # | Severidad | Hallazgo | Evidencia |
|---|---|---|---|
| H01 | Alta | **La reactivación no revalida presupuesto ni reconstruye el flujo de aprobación.** `reactivateDirect` reserva directamente y deja `assigned_approver_id = null` (el cron lo borró). La OCD queda en PENDING_APPROVAL sin nadie que pueda aprobarla (el mensaje dice "Asigna un aprobador" pero no hay pantalla para eso), y puede sobre-comprometer presupuesto. | `PurchaseOrderController.php:263-289`; `CloseInactivePurchaseOrders` pone `assigned_approver_id => null` |
| H02 | Alta | **Lista fija de IDs (2, 3, 20) para reactivar.** Depende de los IDs de la BD de producción; en otro ambiente o si cambian las personas, la regla se rompe en silencio. Debería ser un permiso. | `PurchaseOrderController.php:28` |
| H03 | Alta (verificar) | **El límite del aprobador se compara con el total en la moneda de la OCD, no en MXN.** `resolveForDirectPurchaseOrder` usa `(float) $ocd->total`; una OCD de 10,000 USD se compara como si fueran 10,000 MXN. | `AuthorizerResolutionService.php:40`; `CostCenterApprovalFlowService.php:104` |
| H04 | Media | **Posible autoaprobación:** no hay ninguna validación de que el aprobador resuelto (o el delegado) sea distinto del creador. Si el creador es el gerente del CC con límite suficiente, o es delegado de su propio aprobador, puede aprobar su propia OCD. | `approve()` solo usa `isApproverFor` |
| H05 | Media | `cancelDirect` permite cancelar OCD en DELIVERED_PENDING_RECEPTION o PARTIALLY_RECEIVED (ya con mercancía o consumo). Libera presupuesto aunque ya haya consumo y facturas posibles. Falta definir la regla. | `PurchaseOrderController.php:57` |
| H06 | Media | Cancelar y PDF siguen usando `abort(422)` → pantalla "422 Unprocessable Content" en lugar de un mensaje (mismo problema que se corrigió en movimientos presupuestales). | `PurchaseOrderController.php:57`, PDF |
| H07 | Media | `consumeOrder` y la provisión financiera corren **fuera** de la transacción de recepción: si fallan, la recepción queda guardada sin consumo presupuestal. | `ReceptionService.php` (después del `DB::transaction`) |
| H08 | Media | El bloqueo por estación (RN-17) es por ubicación completa: una entrega sin capturar de cualquier proveedor impide que **otros** proveedores registren entregas en esa estación. | `SupplierDeliveryController.php:140` |
| H09 | Baja | Estados muertos: DRAFT solo es alcanzable si ya existía; APPROVED y PAID nunca se asignan a OCD. Confunde filtros, reportes y validaciones (`submit()` desde DRAFT es casi inaccesible). | `store()`, `approve()` |
| H10 | Baja | `update()` valida `company_id` pero no lo guarda; cambiar de empresa al corregir no tiene efecto. Además sobrescribe `created_by` con el usuario actual (inocuo hoy porque solo el creador puede editar). | `DirectPurchaseOrderController.php:496-512` |
| H11 | Baja | Los errores de `store`/`update` muestran `$e->getMessage()` técnico al usuario. | `:528` |
| H12 | Baja | El aviso preventivo usa `INACTIVITY_DAYS - 3` y depende de que el cron corra diario; si no corre un día, el aviso y el cierre pueden llegar el mismo día. Además, si el correo falla (cuota de Gmail), `inactivity_warning_sent_at` ya quedó marcado y no se reintenta. | `CloseInactivePurchaseOrders.php` |
| H13 | Proceso | Sin pruebas para rechazar, devolver, cancelar, reactivar, cierre por inactividad, flujo multipaso ni permisos de edición (ver columnas "¿Prueba?"). | `tests/Feature` |

## 7. Actualización 2026-11-04: correcciones aplicadas

La comparación con Codex (`OCD_comparacion.md`) reveló tres bugs que este informe no detectó. Ya están corregidos:

- **Aprobación por jerarquía bloqueada** (Codex H-01): T6 ahora funciona también sin pasos de flujo por centro de costo; al reenviar por jerarquía, los pasos viejos quedan SUPERSEDED.
- **`issued_at` no se guardaba** (Codex H-03): T6 asigna `issued_at`; el histórico se rellena con `direct-purchase-orders:backfill-issued-at`.
- **Consumo con partidas desactualizadas** (Codex H-04): T11 recarga las partidas antes de consumir; RN-20 queda cubierta con prueba.

Los huecos H01–H13 de la sección 6 siguen abiertos.
