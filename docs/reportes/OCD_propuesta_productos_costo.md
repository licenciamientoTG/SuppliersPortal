# OCD para productos de costo (caso aditivo para gasolina)

Fecha: 2026-11-04 · Base `main` f4911dd · Autor: Claude · Revisión estática, solo lectura (no se ejecutó nada contra la BD).
**V** = VERIFICADO en código (archivo:línea) · **I** = INFERENCIA.

---

## 1. Diagnóstico

### Cómo funciona hoy

- **El producto no se marca como "gasto" o "costo"; se clasifica por subcuenta.**
  - Se liga a una o varias subcuentas (`product_service_subaccount`). Si tiene varias, se elige la del departamento del solicitante (`product_department_subaccount`).
  - Cada subcuenta apunta a una cédula y a una cuenta presupuestal heredadas, `legacy_budget_cedula_id` y `legacy_expense_category_id` (**V** `ProductBudgetClassificationService.php:13-75`).
- **Ya existen campos parecidos, pero ninguno distingue costo de gasto:**
  - `products_services.is_inventoriable`: se captura en el catálogo, pero ningún flujo de compra lo lee (**V** solo aparece en `ProductServiceController.php:226,349` y en las vistas del catálogo).
  - `product_type`: es un enum que solo admite `PRODUCTO` o `SERVICIO` (**V** `2025_10_15_112441_create_products_services_table.php:39`).
  - `accounts.is_fixed_asset` y `subaccounts.is_fixed_asset`: son informativos (**V** `ProductBudgetClassificationService.php:74`).
  - `account_category` y `subaccount_category`: son texto libre y hoy están vacíos (**V** migración `2026_07_08_130000`, líneas 163 y 190).
- **Ya existe un catálogo contable real:** `ledger_accounts`, sincronizado con OneGoal (**V** `LedgerAccount.php`). Hoy solo lo usan los grupos de impuestos (**V** `TaxGroupController.php:91`), y ninguna compra lo usa.
- **El tipo de presupuesto es del centro de costo, no del producto:** `ANNUAL` o `FREE_CONSUMPTION` (**V** `SaveCostCenterRequest.php:31`).
  - Si el centro es FREE_CONSUMPTION, la OCD no valida saldo (**V** `DirectPurchaseOrderController.php:771-773`).
  - Aun así, se registra un `BudgetCommitment` sin tocar la distribución mensual (**V** `BudgetAllocationService.php:348-354`). Ese compromiso se consume y se libera igual que los demás (**V** `:473`, `:550`, `:565-571`).
- **Hay un hueco relevante en FREE_CONSUMPTION:**
  - Al crear el centro se exigen `global_amount`, `validity_date` y una justificación (**V** `SaveCostCenterRequest.php:32-50`).
  - Pero **nada en el sistema aplica ese monto ni esa vigencia.** `canCommitFreeConsumption()` y `getAvailableFreeConsumption()` no tienen quién las llame, y además leen `BudgetMovement`, no `BudgetCommitment` (**V** `CostCenter.php:298-343`; sin usos fuera del modelo).
- **Presupuesto a lo largo del ciclo de la OCD:**
  - Al enviar (alta, reenvío o edición de una devuelta): se valida y se reserva (**V** `DirectPurchaseOrderController.php:600-627`).
  - Al emitir: se compromete (`commitOrder`).
  - Con la entrega y la recepción: se consume en proporción a lo recibido (`consumeOrder`; el bug H-04 ya está corregido en f4911dd).
  - Al rechazar, devolver, cancelar o cerrar por inactividad: se libera.
  - Al reactivar: se vuelve a reservar sin validar saldo (ver los informes previos).

### Dónde falla exactamente un producto sin clasificación

Falla en cuatro puntos, en este orden:

1. **El buscador de productos de la OCD no lo muestra.** Los productos sin clasificación completa o fuera del perfil presupuestal del usuario se descartan (**V** `ProductServiceController.php:665-681` y `:725-729`). Este es el primer muro: el aditivo ni siquiera se puede elegir.
2. **Si se forzara el envío, falla la validación del formulario.**
   - `prepareForValidation` lanza "El producto no tiene subcuenta presupuestal asignada" (**V** `SaveDirectPurchaseOrderRequest.php:37-42`; el texto sale de `ProductBudgetClassificationService.php:26`).
   - Además, las reglas exigen `expense_category_id` y `budget_cedula_id` en cada partida (**V** `SaveDirectPurchaseOrderRequest.php`, reglas `items.*`).
3. **La base de datos tampoco lo acepta.** `odc_direct_purchase_order_items.expense_category_id` y `budget_commitments.expense_category_id` son NOT NULL con llave foránea (**V** `2026_01_28_224435...items_table.php:23-25`; `2026_01_28_224506...commitments_table.php:36`). `budget_cedula_id` sí acepta nulos (**V** `2026_07_08_132000...php:21-23`).
4. **El servicio de presupuesto supone que toda partida tiene cuenta.** Agrupa y compromete cada partida usando `(int) expense_category_id` (**V** `BudgetAllocationService.php:820-850`).

### Hallazgos previos que afectan este caso

| # | Estado | Relevancia |
|---|---|---|
| H-01 (aprobar sin pasos por centro de costo) | **Corregido** en f4911dd | Necesario para las dos propuestas: sin él, una OCD aprobada por jerarquía no se podía emitir. |
| H-03 (`issued_at`) | **Corregido** en f4911dd | Necesario para que estas OCD aparezcan en RC-02. |
| H-04 (consumo con partidas precargadas) | **Corregido** en f4911dd | Necesario para que el consumo cuadre en la recepción. |
| **H-23** (la provisión lee `cost_center_id` de la cabecera) | **Abierto** (**V** `FinancialProvisionService.php:186-188`; esa columna ya no existe en la cabecera, ver `DirectPurchaseOrderPerItemCostCenterTest`) | Toda provisión de OCD queda con `cost_center_id = null`. **Hay que corregirlo antes de cualquiera de las dos propuestas**, porque Contabilidad necesita saber a qué centro u operación cargar el costo. |
| H-11 (límite del aprobador en moneda original) | Abierto | Solo importa si el aditivo se compra en USD. |

---

## 2. Propuesta A: "Centro de costo de consumo controlado" (cambio mínimo: datos y configuración, más un control pequeño)

**Idea:** no tocar el modelo de partidas. El aditivo se compra contra un centro de costo dedicado de tipo FREE_CONSUMPTION y una subcuenta propia del catálogo presupuestal, por ejemplo "Costo – Aditivos de combustible". El control lo pone un tope global que hoy existe en los datos pero no se aplica.

**Flujo:** el mismo de cualquier OCD.

- La partida trae producto, cuenta, cédula y el centro "Aditivos" de esa empresa.
- No se valida saldo mensual, pero sí el tope global y la vigencia, que hoy no se aplican.
- El compromiso y el consumo se registran como trazabilidad; no descuentan de ninguna distribución mensual.

**Cambios:**

| Capa | Cambio | Archivos |
|---|---|---|
| Datos (Contabilidad + Presupuestos) | Una cuenta y una subcuenta "Costo – Aditivos", marcadas `account_category = 'COSTO'`, con su cédula heredada. Ligar el producto a esa subcuenta. Agregar la subcuenta al perfil presupuestal de los compradores o solicitantes autorizados. | Pantallas existentes de catálogo de cuentas, productos y perfiles. **Sin migraciones.** |
| Datos | Un centro de costo FREE_CONSUMPTION por empresa (por ejemplo "COSTO-ADITIVOS"), con responsable = gerente de Combustibles u Operaciones, `global_amount` = tope anual o trimestral y `validity_date`. Asignarlo a los usuarios que compran. | Alta de centros existente. |
| Código (pequeño) | Aplicar `global_amount` y `validity_date` de FREE_CONSUMPTION en `validateBudgetAvailability`, sumando `BudgetCommitment` COMMITTED + consumido del centro. Hoy `getAvailableFreeConsumption()` lee `BudgetMovement` y nadie la llama. | `DirectPurchaseOrderController.php:767-791`, `CostCenter.php:298-343` |
| Código | Corregir H-23: tomar el centro de costo de las partidas (uno solo, o null si son mixtas). | `FinancialProvisionService.php:184-197` |
| Reportes | Ninguno se rompe. El aditivo aparece en "Presupuesto vs real" como consumo de un centro sin presupuesto anual. Conviene un filtro o una etiqueta por `account_category = 'COSTO'` (**I**). | `BudgetVsActualReport.php` (opcional) |

**Qué control sustituye al presupuesto:**
- **Quién aprueba:** el responsable del centro "Aditivos" (flujo por centro de costo, **V** `CostCenterApprovalFlowService.php:104-133`), con escalamiento si su límite no alcanza.
- **Tope:** `global_amount` por vigencia, más el tope de 250,000 MXN por OCD.
- **Trazabilidad:** BudgetCommitment, ApprovalDecision, recepción y provisión.
- Además, la justificación de 100 caracteres sigue siendo obligatoria.

**Provisión, factura y contabilidad:**
- La provisión y la conciliación funcionan igual que hoy.
- La clasificación contable real (cuenta de costo o inventario) **no existe en el portal**. Contabilidad la deduce de la subcuenta "Costo – Aditivos" (**I**).

**Partidas mixtas:** funcionan sin cambios.
- Cada partida lleva su propio centro: una de gasto en el centro de la estación y una de aditivo en el centro "Aditivos".
- La aprobación genera un paso por cada responsable (**V** `CostCenterApprovalFlowService.php:118-128`).

**Riesgos:**
- **Disfraza un costo como "presupuesto"** dentro del catálogo presupuestal, lo que contamina los reportes de gasto si no se filtran.
- **Se pierde a qué estación se cargó el aditivo** (el centro es único por empresa), salvo que se cree un centro por estación (**I**).
- Mientras no se aplique `global_amount`, el control queda solo en la aprobación.

**Esfuerzo:** **bajo**. Unos 2 días de código y pruebas, más la configuración de datos con Contabilidad.

**Pruebas necesarias:**
- Una OCD con el producto de aditivo pasa sin saldo mensual.
- Una OCD que excede `global_amount` o está fuera de vigencia se bloquea con un mensaje claro.
- El ciclo de emitir, recibir y consumir cuadra.
- La provisión lleva el centro de costo de las partidas (H-23).
- Una OCD mixta genera dos pasos de aprobación.

---

## 3. Propuesta B: "Partidas de costo" como naturaleza propia (estructural)

**Idea:** reconocer en el modelo que hay compras que **no son gasto presupuestable**. El producto declara su naturaleza y su cuenta contable real (en `ledger_accounts`, de OneGoal). Las partidas de costo no pasan por el presupuesto y tienen su propio control de autorización y de precio.

**Flujo:** los mismos estados de OCD, pero en las partidas de costo:

- **Al enviar:** no se valida saldo ni se reserva. Se valida el **precio contra referencia**: `estimated_price` del producto o un convenio de precios, con tolerancia.
- **Al emitir:** no se registra un BudgetCommitment. El compromiso queda en un registro propio o simplemente en la OCD emitida (**I**, a decidir).
- **Al recibir:** sin consumo presupuestal. La provisión lleva la **cuenta contable** de la partida.
- **Al cancelar o devolver:** no hay nada que liberar.

**Cambios:**

| Capa | Cambio | Archivos |
|---|---|---|
| Migraciones | `products_services`: agregar `budget_treatment` (`BUDGETED` o `COST`, por defecto `BUDGETED`) y `ledger_account_id`, que puede ser nulo. Usar una columna nueva en vez de ampliar el enum `product_type`, porque en SQL Server un enum es una restricción CHECK y cambiarlo cuesta más. | nueva migración |
| Migraciones | `odc_direct_purchase_order_items`: agregar `budget_treatment` y `ledger_account_id`, y volver nulos `expense_category_id` y `budget_cedula_id`. En SQL Server hay que soltar y recrear la llave foránea; es aditivo, sin pérdida de datos. | nueva migración |
| Catálogo | En el alta o edición de un producto, si es COST: se exige cuenta contable y no subcuenta presupuestal. El buscador de la OCD deja de descartarlo. | `SaveProductServiceRequest.php`, `ProductServiceController.php:665-760`, vistas `products_services/*` |
| Captura OCD | En `prepareForValidation`, si el producto es COST: no se resuelve clasificación presupuestal y se copia `ledger_account_id`. Las reglas exigen cuenta y cédula solo si la partida es BUDGETED. Se agrega la validación de precio contra referencia. | `SaveDirectPurchaseOrderRequest.php` |
| Presupuesto | `getOrderBudgetLines` y `ensureBudgetAvailability` omiten las partidas COST. Con eso, reservar, comprometer, consumir y liberar las ignoran solas. | `BudgetAllocationService.php:818-850`, `DirectPurchaseOrderController.php:635-664` |
| Aprobación | Un paso adicional "Autorizador de costos" (un rol con límite, en la matriz de autorizadores), además de los pasos por centro de costo de las partidas de gasto. | `CostCenterApprovalFlowService.php:104-133`, `AuthorizerRole` |
| Provisión | Corregir H-23 por partida. Agregar `ledger_account_id` a la provisión, o una tabla de detalle por cuenta. | `FinancialProvisionService.php`, migración |
| Vistas y reportes | La vista de la OCD muestra "Costo – cuenta X" en vez de la cédula. Los reportes de presupuesto excluyen COST de forma explícita. RC-02 y el backlog (RC-03) agregan la columna "Naturaleza". | `show-direct.blade.php`, `OpenPurchaseOrderBacklogReport.php:97-122`, `IssuedPurchaseOrdersReport.php` |

**Qué control sustituye al presupuesto:**
1. **Precio:** contra un precio de referencia o un convenio, con tolerancia.
2. **Autorizador de costos:** un rol específico con límite propio.
3. **Tope por OCD** y, si negocio lo pide, un tope mensual por producto.
4. **Trazabilidad:** ApprovalDecision, recepción, provisión con cuenta contable y factura.

**Provisión, factura y contabilidad:** es la única de las dos propuestas que deja en el portal la **cuenta contable real** por partida, de modo que puede llegar a OneGoal sin que Contabilidad la deduzca.

**Partidas mixtas:** sí, de forma nativa.
- Las partidas de gasto siguen el presupuesto con sus pasos por centro de costo.
- Las de costo agregan el paso del autorizador de costos.
- La provisión se separa por cuenta.

**Riesgos:**
- Toca el núcleo de presupuesto y aprobación, que ya tuvo bugs (H-01, H-04).
- Muchos lugares hacen `(int) expense_category_id`; si queda nulo, se vuelve 0 en silencio (**V** `BudgetAllocationService.php:836`; **I** que hay más).
- Hay que revisar todos los reportes.

**Esfuerzo:** **alto**, unas 2 a 3 semanas con pruebas.

**Pruebas necesarias:**
- Una OCD solo de costo, de principio a fin, sin ningún BudgetCommitment.
- Una OCD mixta: reserva solo la parte de gasto y aprueba con los dos tipos de paso.
- La validación de precio: dentro y fuera de la tolerancia.
- La provisión con cuenta contable.
- Los reportes de presupuesto no cambian con partidas COST.
- Una regresión completa de una OCD de gasto.

---

## 4. Comparación y recomendación

| Criterio | A: centro de consumo controlado | B: partidas de costo |
|---|---|---|
| Tiempo para comprar el aditivo | Días | Semanas |
| Cambios de modelo | Ninguno | Migraciones en productos, partidas y provisión |
| Control que sustituye al presupuesto | Responsable del centro + tope global y vigencia | Autorizador de costos + precio de referencia + tope |
| Clasificación contable real | No: se deduce de la subcuenta | Sí: `ledger_account_id` por partida |
| Pureza de los reportes de presupuesto | Se contaminan si no se filtran | Limpios |
| Partidas mixtas | Sí, separando centros | Sí, de forma nativa |
| Riesgo de regresión | Bajo | Medio a alto |
| Escala a otros costos (combustible, lubricantes, materia prima) | Mal: un centro por cada concepto | Bien |

**Recomendación:**
- **A ahora, B solo si se confirma recurrencia.**
- **A desbloquea la compra en días y con control real**, siempre que se apliquen dos cambios pequeños: el tope `global_amount` (hoy existe en los datos pero nadie lo aplica) y la corrección de H-23.
- **B solo vale la pena** si Contabilidad confirma que habrá **más productos de costo**, además del aditivo, y que necesita la cuenta contable en el portal. Si es un caso aislado, B es sobreingeniería.
- **Antes de cualquiera de las dos, conviene corregir H-23.**

---

## 5. ¿Es la OCD el vehículo correcto?

**Probablemente no a largo plazo (I).** Un aditivo que se mezcla con la gasolina es **costo de ventas o inventario de combustible**, no un gasto operativo. Hay cuatro razones:

1. **La OCD fue diseñada para lo contrario.** Está pensada para compras puntuales de gasto, con justificación de 100 caracteres, un tope de 250,000 MXN y el uso de la vía de requisición para montos mayores (**V** `SaveDirectPurchaseOrderRequest.php`, mensaje de `withValidator`). Una compra recurrente de insumo de operación obliga a justificar lo mismo cada vez.
2. **El portal no tiene inventario.** `is_inventoriable` no se usa en ningún flujo de compra (**V**). Si el aditivo se controla por litros mezclados, el registro real vive en el ERP (OneGoal) o en el control volumétrico de las estaciones, no aquí (**I**).
3. **El precio debería estar pactado.** El sistema ya tiene **convenios de precios** (`ContractType::CONVENIO`, "solo pacta precios…", **V** `app/Enum/ContractType.php:22`). Una compra recurrente a un mismo proveedor encaja mejor ahí. Sin embargo, ese flujo también compromete presupuesto (**V** `ContractPurchaseOrderService.php:119-121`), así que hereda el mismo problema.
4. **El tope de 250,000 MXN por OCD** puede quedarse corto si el aditivo se compra por pipa o por volumen trimestral (**I**). En ese caso se forzaría a partir compras para no rebasarlo, lo cual es un riesgo de control.

**Conclusión:**
- **Si la compra es ocasional:** OCD con la propuesta A.
- **Si es recurrente o de volumen alto:** debería ir por un convenio de precios más un flujo de costo, que es la propuesta B aplicada también a contratos, o directamente por el ERP y quedar fuera del portal.

---

## 6. Preguntas abiertas para negocio y contabilidad

1. **¿A qué cuenta contable va el aditivo?** Puede ser costo de ventas, inventario de combustibles u otra. ¿Existe ya en OneGoal (`ledger_accounts`)?
2. **¿Se maneja como inventario?** Por ejemplo, entrada al almacén o tanque y salida por mezcla. Si es así, ¿quién lleva ese control hoy y en qué sistema?
3. **¿Con qué frecuencia y por qué montos se compra?** Por ejemplo, mensual o por pipa. ¿Puede rebasar 250,000 MXN por compra?
4. **¿Quién debe aprobar estas compras y con qué límite?** Por ejemplo, el gerente de Combustibles, Operaciones o el Director.
5. **¿Siempre se compra al mismo proveedor?** ¿Conviene un convenio de precios?
6. **¿El costo se carga por estación o por empresa?** Con la propuesta A, ¿basta un centro "Aditivos" por empresa o hace falta uno por estación?
7. **¿Hay otros productos con el mismo problema?** Por ejemplo, combustible, lubricantes o materia prima. La respuesta decide entre A y B.
8. **¿Contabilidad acepta deducir la cuenta contable de la subcuenta?** Esa es la propuesta A. ¿O necesita que el portal la mande explícita, como en la propuesta B?
9. **¿Se compra en USD?** Si es así, primero hay que resolver H-11 (límite del aprobador en moneda original).
