# Prompts para Claude Code · Reportes del Portal de Proveedores (TotalGas)

## Antes de usar estos prompts

1. **Nombres de tablas y campos = SUPUESTOS.** Cada prompt obliga a Claude Code a mapear contra el esquema real (Paso 0) antes de programar.
2. **Varios "reportes" son en realidad controles o módulos que probablemente no existen todavía** (versionado de OC, pólizas, tesorería/pagos, REP, REPSE, descarga masiva SAT, segregación de funciones). Cada prompt obliga al agente a detenerse y reportar el hueco en vez de simular datos.
3. **Inconsistencias de fase en el Excel:** RP-03 y RF-02 (F1) dependen de la bitácora RA-01 (F2); RR-02 y RT-02 (F1) piden cuadrar contra pólizas/contabilidad (RK-01/RK-02, F2); RT-01 (F1) necesita bloqueos que viven en RM-01 y RF-05 (F2). Recomendación: adelantar a F1 la tabla y el servicio de RA-01 y la función centralizada de bloqueos.
4. **Reglas fiscales (RF-*, RM-*)** requieren visto bueno de Contabilidad/Fiscal antes de construirse. Sin PAC, el portal no puede timbrar el CFDI de retenciones (RF-04): solo registrar su UUID.
5. **Orden sugerido:** RA-01 (tabla/servicio) → RP-02 → RP-01 → RC-01/RC-02/RC-03 → RR-01 → RF-01 → RF-02 → RR-03 → RC-04 → RR-02 → RT-02 → RT-03 → RT-01; después F2 y F3.

## Cómo usarlos en Claude Code

Copia la carpeta `prompts/` a `docs/reportes/` del repositorio y en Claude Code escribe, por ejemplo: `Lee docs/reportes/RP-01_budget_vs_actual.md y ejecútalo`. El agente entregará primero el PLAN y se detendrá.

================================================
REPORTE #1: RP-01 · Presupuesto vs. ejercido por departamento y renglón
================================================

# REPORTE RP-01: Presupuesto vs. ejercido por departamento y renglón

## A) ROL Y OBJETIVO

Actúa como desarrollador senior full-stack en el Portal de Proveedores (Módulo de Compras) de TotalGas, con experiencia en reporting financiero para Contabilidad, Contraloría y Tesorería en México (CFDI 4.0, SAT, REPSE).

**Objetivo medible:** Mostrar, por empresa + centro de costo + renglón + periodo, el presupuesto vigente descompuesto en cinco cubos mutuamente excluyentes (Reservado, Comprometido, Devengado, Pagado, Disponible) cuya suma es exactamente el presupuesto vigente, usando la MISMA fuente de cálculo que el motor de bloqueo de requisiciones.

### Contexto técnico del proyecto
- Aplicación: Portal de Proveedores / Módulo de Compras de TotalGas (grupo gasolinero con 36 estaciones, Cd. Juárez, Chih.). Ya cubre requisiciones, cotizaciones, órdenes de compra, recepciones, control presupuestal y validación CFDI/SAT (RFC, listado 69-B). Cada centro de costo ya tiene responsable asignado.
- Stack: Laravel 12, PHP 8.3, SQL Server 2019, Blade + Bootstrap 5 + jQuery. Si el repositorio ya usa Livewire, DataTables, Laravel Excel u OpenSpout, spatie/laravel-permission o dompdf/snappy, REUTILÍZALOS. No agregues dependencias nuevas sin justificarlas en el plan.
- Zona horaria de negocio: `America/Ciudad_Juarez`. Toda "fecha de corte" se interpreta a las 23:59:59 de esa zona.
- Moneda funcional: MXN. Los nombres de tablas y campos de este documento son SUPUESTOS: el esquema real del repositorio manda.

### Reglas de trabajo (obligatorias)
1. Antes de escribir código, inspecciona migraciones, modelos Eloquent, vistas/funciones SQL, servicios y jobs existentes. Construye una tabla de mapeo: `campo requerido → tabla.campo real | NO EXISTE`.
2. No crees tablas paralelas para datos que ya existen con otro nombre. Si falta un dato, propón en el plan la migración mínima y aditiva, y espera aprobación.
3. Si un prerrequisito no existe, NO simules el dato (ni ceros, ni NULL silenciosos, ni valores fijos). Repórtalo y separa en el plan "lo que se puede entregar hoy" de "lo que requiere construir el control primero". Un reporte que cuadra con datos falsos es peor que no tener reporte.
4. Montos en `DECIMAL(18,2)`; tipos de cambio en `DECIMAL(18,6)`; nunca `FLOAT`. Redondea a 2 decimales a nivel de renglón y después suma; usa el mismo criterio que los demás reportes con los que este debe cuadrar.
5. Consultas siempre parametrizadas (bindings). Prohibido concatenar filtros en SQL.
6. El reporte no consulta en línea los linked servers de las estaciones; usa las tablas ya consolidadas en el servidor del portal.
7. Una sola fuente de verdad por cálculo: si otro reporte o el motor de control usa el mismo cálculo, encapsúlalo (vista, función de tabla en línea o clase de servicio) y reutilízalo. No dupliques fórmulas.
8. No cambies reglas de negocio existentes salvo lo que este documento pida explícitamente. Si detectas un bug, repórtalo en el plan en lugar de corregirlo en silencio.
9. Reglas fiscales (tasas, claves SAT, plazos): van en catálogos parametrizables, nunca en código, y requieren confirmación de Contabilidad/Fiscal antes de liberarse.

### Paso 0: prerrequisitos que debes verificar antes de programar

- Catálogo de renglones presupuestales con cuenta contable asociada.
- Presupuesto autorizado por empresa + centro de costo + renglón + ejercicio/mes.
- Lógica actual de consumo presupuestal y del bloqueo de requisiciones (localízala; ya se corrigió el consumo en recepciones parciales). Este reporte debe usar esa misma lógica refactorizada, no una segunda.
- Movimientos presupuestales (ampliaciones, reducciones, traspasos). Si no existen, el presupuesto vigente = autorizado y se marca el hueco (ver RP-02).
- Registro de pagos a proveedores. Si el portal no registra pagos, el cubo Pagado no se puede calcular: repórtalo, no lo pongas en cero.

Si alguno no existe, repórtalo en el plan con su impacto; no lo sustituyas con datos simulados.

## B) NOMBRE Y PROPÓSITO DEL REPORTE

- ID: `RP-01` · Dominio: A. Presupuesto y control del compromiso · Fase: F1
- Nombre técnico: `rpt_budget_vs_actual`
- Nombre de negocio: Presupuesto vs. ejercido por departamento y renglón
- Frecuencia: Tiempo real, con corte diario histórico
- Consumidor principal: Contraloría, jefes de departamento, Dirección de Finanzas
- Granularidad (una fila =): Una fila por empresa + centro de costo + renglón presupuestal + periodo (mes y acumulado del ejercicio).

**Pregunta de negocio que responde / decisión que habilita:** El saldo realmente disponible antes de comprometer un peso más. Contesta "¿puedo autorizar esta compra?" con un número, no con criterio. Debe ser la misma consulta que usa el motor de bloqueo de la requisición: el reporte y el control no pueden contradecirse nunca.

**Requerimiento original de Contabilidad y Finanzas (campos mínimos):**

  • Empresa (RFC) · Centro de costo · Clave y nombre del renglón · Cuenta contable asociada
  • Presupuesto autorizado · Ampliaciones · Reducciones · Presupuesto vigente
  • Reservado (requisiciones aprobadas sin OC) · Comprometido (OC vigentes sin recibir) · Devengado (recibido o facturado sin pagar) · Pagado
  • Ejercido total · Disponible · % de avance · Semáforo de consumo · Proyección de cierre

## C) COLUMNAS / CAMPOS

Origen = SUPUESTO de nombre; mapea al esquema real en el Paso 0. O = obligatoria, Op = opcional.

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| company_rfc | NVARCHAR(13) | companies.rfc |  | texto | O |
| company_name | NVARCHAR(200) | companies.name |  | texto | O |
| cost_center_code / cost_center_name | NVARCHAR | cost_centers.code / name |  | texto | O |
| budget_line_code / budget_line_name | NVARCHAR | budget_lines.code / name |  | texto | O |
| accounting_account | NVARCHAR(30) | budget_lines.accounting_account_code |  | texto | O |
| responsible_name | NVARCHAR(150) | cost_centers.responsible_user_id → users.name |  | texto | Op |
| fiscal_year / period_month | INT / TINYINT | budgets |  | número | O |
| period_scope | CHAR(3) | calculada | 'MES' o 'ACU' (acumulado del ejercicio al mes) | texto | O |
| authorized_amount | DECIMAL(18,2) | budgets.authorized_amount |  | moneda MXN | O |
| increases | DECIMAL(18,2) | budget_movements | Σ ampliaciones + traspasos entrantes autorizados | moneda | O |
| decreases | DECIMAL(18,2) | budget_movements | Σ reducciones + traspasos salientes autorizados | moneda | O |
| current_budget | DECIMAL(18,2) | calculada | authorized_amount + increases − decreases | moneda | O |
| reserved | DECIMAL(18,2) | budget_ledger_entries (bucket='RESERVED') | Σ requisiciones aprobadas aún no convertidas en OC | moneda | O |
| committed | DECIMAL(18,2) | budget_ledger_entries (bucket='COMMITTED') | Σ OC vigentes no recibidas (cantidad pendiente × precio OC) | moneda | O |
| accrued | DECIMAL(18,2) | budget_ledger_entries (bucket='ACCRUED') | Σ recibido o facturado aún no pagado | moneda | O |
| paid | DECIMAL(18,2) | budget_ledger_entries (bucket='PAID') | Σ pagado | moneda | O |
| exercised_total | DECIMAL(18,2) | calculada | committed + accrued + paid (SUPUESTO, ver dudas) | moneda | O |
| consumed_total | DECIMAL(18,2) | calculada | reserved + committed + accrued + paid | moneda | O |
| available | DECIMAL(18,2) | calculada | current_budget − consumed_total | moneda (negativo en rojo) | O |
| progress_pct | DECIMAL(9,4) | calculada | consumed_total / NULLIF(current_budget,0) | % | O |
| traffic_light | VARCHAR(10) | calculada | VERDE < 80 %; AMARILLO 80–99.99 %; ROJO ≥ 100 % (umbrales parametrizables) | badge de color | O |
| projected_close | DECIMAL(18,2) | calculada | consumed_total_acu + (promedio mensual de committed+accrued+paid de los últimos 3 meses cerrados × meses restantes del ejercicio) | moneda | O |
| cancelled_po_amount | DECIMAL(18,2) | purchase_orders (status=CANCELADA) | informativo, solo si el parámetro lo pide; NUNCA suma en cubos | moneda | Op |

## D) FILTROS Y PARÁMETROS

**Filtros fijos (siempre aplicados):**

- Solo presupuestos en estatus AUTORIZADO.
- Documentos cancelados nunca suman en ningún cubo.
- Importes en MXN, base sin IVA acreditable (SUPUESTO, ver dudas).

**Parámetros de entrada (según el Excel: Empresa o grupo de empresas, centro de costo, renglón, ejercicio, mes, rango de fechas, responsable, con o sin OC canceladas.):**

| Parámetro | Tipo | Valor por defecto | O/Op |
|---|---|---|---|
| company_ids (empresa o grupo) | array<int> | empresas del usuario | O |
| fiscal_year | int | ejercicio en curso | O |
| period_month | int 1–12 | mes en curso | O |
| date_from / date_to | date | vacío (usa mes/ejercicio) | Op |
| cost_center_ids | array<int> | todos | Op |
| budget_line_ids | array<int> | todos | Op |
| responsible_user_id | int | todos | Op |
| include_cancelled_po | bool | false | Op |
| as_of_date (corte histórico) | date | hoy = tiempo real | Op |

## E) AGRUPACIONES, ORDEN Y GRANULARIDAD

- Grano: empresa + centro de costo + renglón + periodo, con dos vistas del mismo renglón: MES y ACUMULADO.
- GROUP BY company, cost_center, budget_line, fiscal_year, period_month.
- ORDER BY company_name, cost_center_code, budget_line_code.
- Subtotales por centro de costo y por empresa; total general. Los subtotales se calculan sumando cubos, no recalculando el porcentaje por promedio.

## F) REGLAS DE NEGOCIO Y CÁLCULOS

- Recomendación de diseño: si el consumo presupuestal hoy se calcula con joins sobre requisiciones/OC/recepciones/facturas, propón migrarlo a un libro mayor presupuestal (`budget_ledger_entries`) donde cada evento del documento (aprobar requisición, emitir OC, recibir, facturar, pagar, cancelar) mueve el importe de un cubo al siguiente con un asiento negativo y uno positivo. Así la identidad de cubos se cumple por construcción y nunca un importe está en dos cubos.
- Crear una función de tabla en línea `dbo.fn_budget_position(@company_id, @fiscal_year, @as_of)` (o equivalente) que devuelva los cubos por renglón. La usan este reporte Y el guardián de la requisición (`RequisitionBudgetGuard` o como se llame hoy). El reporte y el control no pueden contradecirse.
- Identidad obligatoria: reserved + committed + accrued + paid + available = current_budget en cualquier combinación de filtros.
- Recepción parcial: solo la porción recibida pasa de Comprometido a Devengado; el saldo pendiente sigue en Comprometido.
- Diferencia precio OC vs precio factura: el devengado se reconoce al precio OC en la recepción y se ajusta a precio factura al vincular el CFDI (SUPUESTO).
- Moneda extranjera: se compromete al tipo de cambio de la OC; al pagar, la diferencia cambiaria se registra en el cubo Pagado del mismo renglón (SUPUESTO).
- Corte diario histórico: job programado a las 23:55 (America/Ciudad_Juarez) que guarda la foto en `budget_position_snapshots`. Si `as_of_date` < hoy, se lee el snapshot; si es hoy, se consulta en línea.
- Proyección de cierre: lineal por promedio de 3 meses cerrados; si hay menos de 3 meses de historia, usar los disponibles e indicarlo.

## G) FORMATO DE SALIDA

- Tarjetas KPI arriba: Presupuesto vigente, Consumido, Disponible, % de avance, número de renglones en ROJO.
- Barra apilada horizontal por renglón con los cinco cubos (misma escala), útil para ver de un vistazo dónde está el dinero.
- Tabla principal con columna de semáforo; clic en un cubo abre el detalle de documentos que lo componen (drill-down con folio y liga al documento).
- Excel con una hoja resumen y una hoja de detalle por documento. PDF ejecutivo opcional por centro de costo.

Convenciones comunes de salida:
- Pantalla: tabla con DataTables en modo server-side (paginación en servidor de 25/50/100 filas), encabezado fijo y primera columna congelada, panel de filtros arriba con los parámetros de la sección D y botón "Limpiar filtros".
- Excel (.xlsx) con celdas tipadas de verdad (moneda `$#,##0.00`, fechas `dd/mm/yyyy`, porcentaje `0.0%`), nunca números como texto; encabezado con nombre del reporte, empresa(s), filtros aplicados, fecha/hora de generación y usuario. Escritura en streaming (chunks), sin cargar todo en memoria.
- CSV UTF-8 con BOM y separador coma.
- Exportaciones de más de 50 000 filas se encolan como job y se notifica al usuario cuando el archivo está listo.
- La exportación respeta exactamente los filtros activos en pantalla.

## H) CONSIDERACIONES TÉCNICAS

**Tablas/vistas probables (SUPUESTO):** companies, cost_centers, budget_lines, budgets, budget_movements, budget_ledger_entries (nueva si no existe), budget_position_snapshots (nueva), requisitions, purchase_orders, receptions, invoices, payments.

**Índices recomendados (valida contra los existentes antes de crear):**

- budget_ledger_entries (company_id, fiscal_year, period_month, cost_center_id, budget_line_id) INCLUDE (bucket, amount_mxn).
- budget_ledger_entries (source_document_type, source_document_id) para drill-down.
- budget_position_snapshots (as_of_date, company_id) INCLUDE de todos los cubos.

**Seguridad y permisos:**

- Permisos granulares: `reportes.budget_vs_actual.ver` y `reportes.budget_vs_actual.exportar` (crea los permisos con el mecanismo que ya use el repo).
- Alcance por empresa: el usuario solo ve las empresas que tiene asignadas; el filtro se aplica en el servidor (scope/policy), nunca solo en la UI.
- Cada exportación se registra en bitácora: usuario, fecha/hora, filtros, número de filas.

- Jefes de departamento: solo centros de costo donde son responsables (usa la relación responsable ↔ centro de costo existente).

**Endpoints y componentes:**

- GET /reportes/presupuesto/ejercido → HTML; GET /reportes/presupuesto/ejercido/data → JSON para DataTables; GET …/export?format=xlsx|csv|pdf.
- GET /reportes/presupuesto/ejercido/detalle?bucket=&budget_line_id= → documentos del cubo.
- Clase `App\Reports\Budget\BudgetVsActualReport` que consume `fn_budget_position`.

## I) CRITERIOS DE ACEPTACIÓN (Definition of Done)

Criterio de negocio original: _Reservado + Comprometido + Devengado + Pagado + Disponible = Presupuesto vigente, en cualquier combinación de filtros. Ningún importe aparece en dos cubos a la vez._

- [ ] Prueba automatizada: para 20 combinaciones aleatorias de filtros, reserved + committed + accrued + paid + available = current_budget al centavo.
- [ ] Ningún documento aparece en más de un cubo (consulta de verificación devuelve 0 filas).
- [ ] El guardián de la requisición y el reporte usan la misma función; una prueba demuestra que el disponible que muestra el reporte es exactamente el que usa el bloqueo.
- [ ] El snapshot de un día pasado no cambia aunque después se modifiquen documentos.
- [ ] Todos los filtros de la sección D funcionan combinados y la exportación reproduce exactamente la vista filtrada.
- [ ] Usuarios sin permiso reciben 403 y ningún usuario ve empresas fuera de su alcance.
- [ ] La consulta principal responde en menos de 3 s con el volumen de un año de operación (o se justifica el snapshot/job).

**Preguntas abiertas (aplica el SUPUESTO indicado si no hay respuesta):**

- ¿'Ejercido total' incluye el Reservado? SUPUESTO: no; ejercido = comprometido + devengado + pagado, y se muestra aparte el consumido total.
- ¿El presupuesto se controla sin IVA? SUPUESTO: sí, sin IVA acreditable; el IVA no acreditable sí consume presupuesto.
- ¿Devengado se reconoce con la recepción o con la factura? SUPUESTO: con lo primero que ocurra.

## J) ENTREGABLE ESPERADO

Trabaja en tres entregas y DETENTE al terminar la primera:

1. **PLAN (sin código).**
   - Tabla de mapeo de esquema (campo requerido → tabla.campo real | NO EXISTE).
   - Estado de cada prerrequisito del Paso 0: existe / parcial / no existe, con su impacto en el reporte.
   - Arquitectura: archivos a crear o modificar (migraciones, vista o función SQL, clase de servicio del reporte, FormRequest de parámetros, controlador, rutas, vista Blade/JS, clase de exportación, policy/permisos, jobs y scheduler si aplica).
   - SQL propuesto (borrador) de la consulta principal.
   - Preguntas abiertas para Contraloría/Contabilidad/Fiscal, cada una con el supuesto que aplicarás si no hay respuesta.
   - Espera mi aprobación antes de continuar.
2. **CÓDIGO.** Implementa exactamente lo aprobado: migraciones aditivas, consulta encapsulada, servicio, endpoint, vista, exportaciones, permisos, jobs. Commits pequeños y descriptivos.
3. **PRUEBAS Y VALIDACIÓN.**
   - Pruebas de feature (Pest o PHPUnit, lo que use el repo) con datos semilla que ejerzan cada regla de negocio y cada criterio de aceptación de la sección I.
   - Pruebas de permisos: sin permiso → 403; alcance por empresa/proveedor respetado.
   - Script SQL de verificación que demuestre las identidades de cuadre del reporte.
   - Plan de ejecución en SQL Server con volumen realista; reporta tiempos y los índices creados.
   - Lista explícita de lo que quedó pendiente o fuera de alcance.

------------------------------------------------

================================================
REPORTE #2: RP-02 · Movimientos y traspasos presupuestales
================================================

# REPORTE RP-02: Movimientos y traspasos presupuestales

## A) ROL Y OBJETIVO

Actúa como desarrollador senior full-stack en el Portal de Proveedores (Módulo de Compras) de TotalGas, con experiencia en reporting financiero para Contabilidad, Contraloría y Tesorería en México (CFDI 4.0, SAT, REPSE).

**Objetivo medible:** Listar cada ampliación, reducción y traspaso presupuestal con su cadena de autorización, de modo que el presupuesto vigente de cualquier renglón se reconstruya exactamente desde el original más sus movimientos.

### Contexto técnico del proyecto
- Aplicación: Portal de Proveedores / Módulo de Compras de TotalGas (grupo gasolinero con 36 estaciones, Cd. Juárez, Chih.). Ya cubre requisiciones, cotizaciones, órdenes de compra, recepciones, control presupuestal y validación CFDI/SAT (RFC, listado 69-B). Cada centro de costo ya tiene responsable asignado.
- Stack: Laravel 12, PHP 8.3, SQL Server 2019, Blade + Bootstrap 5 + jQuery. Si el repositorio ya usa Livewire, DataTables, Laravel Excel u OpenSpout, spatie/laravel-permission o dompdf/snappy, REUTILÍZALOS. No agregues dependencias nuevas sin justificarlas en el plan.
- Zona horaria de negocio: `America/Ciudad_Juarez`. Toda "fecha de corte" se interpreta a las 23:59:59 de esa zona.
- Moneda funcional: MXN. Los nombres de tablas y campos de este documento son SUPUESTOS: el esquema real del repositorio manda.

### Reglas de trabajo (obligatorias)
1. Antes de escribir código, inspecciona migraciones, modelos Eloquent, vistas/funciones SQL, servicios y jobs existentes. Construye una tabla de mapeo: `campo requerido → tabla.campo real | NO EXISTE`.
2. No crees tablas paralelas para datos que ya existen con otro nombre. Si falta un dato, propón en el plan la migración mínima y aditiva, y espera aprobación.
3. Si un prerrequisito no existe, NO simules el dato (ni ceros, ni NULL silenciosos, ni valores fijos). Repórtalo y separa en el plan "lo que se puede entregar hoy" de "lo que requiere construir el control primero". Un reporte que cuadra con datos falsos es peor que no tener reporte.
4. Montos en `DECIMAL(18,2)`; tipos de cambio en `DECIMAL(18,6)`; nunca `FLOAT`. Redondea a 2 decimales a nivel de renglón y después suma; usa el mismo criterio que los demás reportes con los que este debe cuadrar.
5. Consultas siempre parametrizadas (bindings). Prohibido concatenar filtros en SQL.
6. El reporte no consulta en línea los linked servers de las estaciones; usa las tablas ya consolidadas en el servidor del portal.
7. Una sola fuente de verdad por cálculo: si otro reporte o el motor de control usa el mismo cálculo, encapsúlalo (vista, función de tabla en línea o clase de servicio) y reutilízalo. No dupliques fórmulas.
8. No cambies reglas de negocio existentes salvo lo que este documento pida explícitamente. Si detectas un bug, repórtalo en el plan en lugar de corregirlo en silencio.
9. Reglas fiscales (tasas, claves SAT, plazos): van en catálogos parametrizables, nunca en código, y requieren confirmación de Contabilidad/Fiscal antes de liberarse.

### Paso 0: prerrequisitos que debes verificar antes de programar

- Tabla de movimientos presupuestales con tipo, origen, destino, motivo, solicitante, autorizador, nivel y soporte. Si hoy los ajustes se hacen editando el importe del presupuesto directamente, el reporte es imposible: el plan debe proponer la captura de movimientos como funcionalidad previa.
- Matriz de niveles de autorización (quién puede autorizar qué monto y qué tipo de traspaso).
- Almacenamiento de documentos soporte (adjuntos).

Si alguno no existe, repórtalo en el plan con su impacto; no lo sustituyas con datos simulados.

## B) NOMBRE Y PROPÓSITO DEL REPORTE

- ID: `RP-02` · Dominio: A. Presupuesto y control del compromiso · Fase: F1
- Nombre técnico: `rpt_budget_movements`
- Nombre de negocio: Movimientos y traspasos presupuestales
- Frecuencia: Bajo demanda
- Consumidor principal: Contraloría, Dirección de Finanzas
- Granularidad (una fila =): Una fila por movimiento de presupuesto: ampliación, reducción o traspaso.

**Pregunta de negocio que responde / decisión que habilita:** La historia de por qué el presupuesto de hoy no es el que se aprobó en enero. Sin esto, la variación contra el presupuesto original es imposible de explicar en el comité y los traspasos se vuelven la válvula de escape silenciosa del sobregiro.

**Requerimiento original de Contabilidad y Finanzas (campos mínimos):**

  • Folio · Fecha · Tipo de movimiento · Importe
  • Centro de costo y renglón origen · Centro de costo y renglón destino
  • Motivo · Solicitante · Autorizador · Nivel de autorización aplicado · Documento soporte

## C) COLUMNAS / CAMPOS

Origen = SUPUESTO de nombre; mapea al esquema real en el Paso 0. O = obligatoria, Op = opcional.

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| movement_folio | NVARCHAR(30) | budget_movements.folio |  | texto | O |
| movement_date | DATE | budget_movements.authorized_at | fecha de efecto = fecha de autorización | fecha | O |
| movement_type | VARCHAR(12) | budget_movements.type | AMPLIACION / REDUCCION / TRASPASO | texto | O |
| amount | DECIMAL(18,2) | budget_movements.amount |  | moneda MXN | O |
| origin_company / origin_cost_center / origin_budget_line | NVARCHAR | FKs origen | vacío en ampliación | texto | O |
| target_company / target_cost_center / target_budget_line | NVARCHAR | FKs destino | vacío en reducción | texto | O |
| reason | NVARCHAR(500) | budget_movements.reason |  | texto | O |
| requested_by | NVARCHAR(150) | users |  | texto | O |
| authorized_by | NVARCHAR(150) | users |  | texto | O |
| authorization_level_applied | VARCHAR(20) | budget_movements.auth_level |  | texto | O |
| authorization_level_required | VARCHAR(20) | calculada | según matriz: traspaso entre centros de costo o empresas distintas → DIRECCION | texto | O |
| level_violation | BIT | calculada | 1 si nivel aplicado < nivel requerido | ícono rojo | O |
| cross_company | BIT | calculada | origin_company ≠ target_company | sí/no | O |
| support_document | NVARCHAR(300) | attachments | liga de descarga | enlace | O |
| status | VARCHAR(15) | budget_movements.status | SOLICITADO / AUTORIZADO / RECHAZADO | texto | O |

## D) FILTROS Y PARÁMETROS

**Filtros fijos (siempre aplicados):**

- Por defecto solo movimientos AUTORIZADOS (los solicitados/rechazados se ven con el filtro de estatus).

**Parámetros de entrada (según el Excel: Empresa, periodo, tipo, centro de costo, autorizador, importe mayor a.):**

| Parámetro | Tipo | Valor por defecto | O/Op |
|---|---|---|---|
| company_ids | array<int> | empresas del usuario | O |
| date_from / date_to | date | 1 de enero del ejercicio → hoy | O |
| movement_type | enum | todos | Op |
| cost_center_ids (origen o destino) | array<int> | todos | Op |
| authorized_by | int | todos | Op |
| amount_greater_than | decimal | 0 | Op |
| only_level_violations | bool | false | Op |

## E) AGRUPACIONES, ORDEN Y GRANULARIDAD

- Transaccional: una fila por movimiento.
- ORDER BY movement_date, movement_folio.
- Totales por tipo de movimiento. Segunda pestaña 'Reconstrucción': por renglón, original + Σ movimientos = vigente, con diferencia (debe ser 0).

## F) REGLAS DE NEGOCIO Y CÁLCULOS

- Traspaso = un solo registro que resta del origen y suma al destino en la misma transacción; nunca dos registros sueltos.
- Todo traspaso entre centros de costo distintos o empresas distintas exige nivel DIRECCION; si el aplicado es menor, `level_violation = 1` y se resalta.
- Reconstrucción: vigente(renglón) = autorizado_original + Σ ampliaciones + Σ traspasos entrantes − Σ reducciones − Σ traspasos salientes. Debe coincidir con `current_budget` de RP-01.
- Un movimiento autorizado es inmutable; una corrección se hace con un movimiento inverso.

## G) FORMATO DE SALIDA

- Tabla con chips de tipo de movimiento; ícono de alerta en violaciones de nivel.
- Pestaña de reconstrucción por renglón con diferencia resaltada si ≠ 0.
- Excel con ambas hojas.

Convenciones comunes de salida:
- Pantalla: tabla con DataTables en modo server-side (paginación en servidor de 25/50/100 filas), encabezado fijo y primera columna congelada, panel de filtros arriba con los parámetros de la sección D y botón "Limpiar filtros".
- Excel (.xlsx) con celdas tipadas de verdad (moneda `$#,##0.00`, fechas `dd/mm/yyyy`, porcentaje `0.0%`), nunca números como texto; encabezado con nombre del reporte, empresa(s), filtros aplicados, fecha/hora de generación y usuario. Escritura en streaming (chunks), sin cargar todo en memoria.
- CSV UTF-8 con BOM y separador coma.
- Exportaciones de más de 50 000 filas se encolan como job y se notifica al usuario cuando el archivo está listo.
- La exportación respeta exactamente los filtros activos en pantalla.

## H) CONSIDERACIONES TÉCNICAS

**Tablas/vistas probables (SUPUESTO):** budget_movements, budgets, budget_lines, cost_centers, companies, users, approval_levels, attachments.

**Índices recomendados (valida contra los existentes antes de crear):**

- budget_movements (company_id, authorized_at) INCLUDE (type, amount, status).
- budget_movements (origin_budget_line_id), (target_budget_line_id).

**Seguridad y permisos:**

- Permisos granulares: `reportes.budget_movements.ver` y `reportes.budget_movements.exportar` (crea los permisos con el mecanismo que ya use el repo).
- Alcance por empresa: el usuario solo ve las empresas que tiene asignadas; el filtro se aplica en el servidor (scope/policy), nunca solo en la UI.
- Cada exportación se registra en bitácora: usuario, fecha/hora, filtros, número de filas.

- Solo Contraloría y Dirección de Finanzas por defecto.

**Endpoints y componentes:**

- GET /reportes/presupuesto/movimientos (+ /data, /export).
- Clase `App\Reports\Budget\BudgetMovementsReport`; la reconstrucción reutiliza la misma función de presupuesto vigente de RP-01.

## I) CRITERIOS DE ACEPTACIÓN (Definition of Done)

Criterio de negocio original: _El presupuesto vigente de cualquier renglón se reconstruye exactamente sumando el original más sus movimientos. Todo traspaso entre centros de costo o empresas distintas exhibe autorización de nivel Dirección._

- [ ] Para todos los renglones del ejercicio, la reconstrucción coincide al centavo con el presupuesto vigente de RP-01.
- [ ] Todo traspaso entre centros de costo o empresas distintas tiene autorizador de nivel Dirección; si no, aparece marcado como violación.
- [ ] No existe endpoint que permita editar o borrar un movimiento autorizado.
- [ ] Todos los filtros de la sección D funcionan combinados y la exportación reproduce exactamente la vista filtrada.
- [ ] Usuarios sin permiso reciben 403 y ningún usuario ve empresas fuera de su alcance.
- [ ] La consulta principal responde en menos de 3 s con el volumen de un año de operación (o se justifica el snapshot/job).

**Preguntas abiertas (aplica el SUPUESTO indicado si no hay respuesta):**

- ¿Traspasos entre empresas distintas están permitidos o deben bloquearse? SUPUESTO: permitidos solo con nivel Dirección.

## J) ENTREGABLE ESPERADO

Trabaja en tres entregas y DETENTE al terminar la primera:

1. **PLAN (sin código).**
   - Tabla de mapeo de esquema (campo requerido → tabla.campo real | NO EXISTE).
   - Estado de cada prerrequisito del Paso 0: existe / parcial / no existe, con su impacto en el reporte.
   - Arquitectura: archivos a crear o modificar (migraciones, vista o función SQL, clase de servicio del reporte, FormRequest de parámetros, controlador, rutas, vista Blade/JS, clase de exportación, policy/permisos, jobs y scheduler si aplica).
   - SQL propuesto (borrador) de la consulta principal.
   - Preguntas abiertas para Contraloría/Contabilidad/Fiscal, cada una con el supuesto que aplicarás si no hay respuesta.
   - Espera mi aprobación antes de continuar.
2. **CÓDIGO.** Implementa exactamente lo aprobado: migraciones aditivas, consulta encapsulada, servicio, endpoint, vista, exportaciones, permisos, jobs. Commits pequeños y descriptivos.
3. **PRUEBAS Y VALIDACIÓN.**
   - Pruebas de feature (Pest o PHPUnit, lo que use el repo) con datos semilla que ejerzan cada regla de negocio y cada criterio de aceptación de la sección I.
   - Pruebas de permisos: sin permiso → 403; alcance por empresa/proveedor respetado.
   - Script SQL de verificación que demuestre las identidades de cuadre del reporte.
   - Plan de ejecución en SQL Server con volumen realista; reporta tiempos y los índices creados.
   - Lista explícita de lo que quedó pendiente o fuera de alcance.

------------------------------------------------

================================================
REPORTE #3: RP-03 · Alertas de agotamiento, sobregiro y excepciones autorizadas
================================================

# REPORTE RP-03: Alertas de agotamiento, sobregiro y excepciones autorizadas

## A) ROL Y OBJETIVO

Actúa como desarrollador senior full-stack en el Portal de Proveedores (Módulo de Compras) de TotalGas, con experiencia en reporting financiero para Contabilidad, Contraloría y Tesorería en México (CFDI 4.0, SAT, REPSE).

**Objetivo medible:** Avisar antes del sobregiro: listar los renglones que cruzan umbrales de consumo, estimar el mes de agotamiento y exhibir cada excepción de compra sin presupuesto con su autorizador.

### Contexto técnico del proyecto
- Aplicación: Portal de Proveedores / Módulo de Compras de TotalGas (grupo gasolinero con 36 estaciones, Cd. Juárez, Chih.). Ya cubre requisiciones, cotizaciones, órdenes de compra, recepciones, control presupuestal y validación CFDI/SAT (RFC, listado 69-B). Cada centro de costo ya tiene responsable asignado.
- Stack: Laravel 12, PHP 8.3, SQL Server 2019, Blade + Bootstrap 5 + jQuery. Si el repositorio ya usa Livewire, DataTables, Laravel Excel u OpenSpout, spatie/laravel-permission o dompdf/snappy, REUTILÍZALOS. No agregues dependencias nuevas sin justificarlas en el plan.
- Zona horaria de negocio: `America/Ciudad_Juarez`. Toda "fecha de corte" se interpreta a las 23:59:59 de esa zona.
- Moneda funcional: MXN. Los nombres de tablas y campos de este documento son SUPUESTOS: el esquema real del repositorio manda.

### Reglas de trabajo (obligatorias)
1. Antes de escribir código, inspecciona migraciones, modelos Eloquent, vistas/funciones SQL, servicios y jobs existentes. Construye una tabla de mapeo: `campo requerido → tabla.campo real | NO EXISTE`.
2. No crees tablas paralelas para datos que ya existen con otro nombre. Si falta un dato, propón en el plan la migración mínima y aditiva, y espera aprobación.
3. Si un prerrequisito no existe, NO simules el dato (ni ceros, ni NULL silenciosos, ni valores fijos). Repórtalo y separa en el plan "lo que se puede entregar hoy" de "lo que requiere construir el control primero". Un reporte que cuadra con datos falsos es peor que no tener reporte.
4. Montos en `DECIMAL(18,2)`; tipos de cambio en `DECIMAL(18,6)`; nunca `FLOAT`. Redondea a 2 decimales a nivel de renglón y después suma; usa el mismo criterio que los demás reportes con los que este debe cuadrar.
5. Consultas siempre parametrizadas (bindings). Prohibido concatenar filtros en SQL.
6. El reporte no consulta en línea los linked servers de las estaciones; usa las tablas ya consolidadas en el servidor del portal.
7. Una sola fuente de verdad por cálculo: si otro reporte o el motor de control usa el mismo cálculo, encapsúlalo (vista, función de tabla en línea o clase de servicio) y reutilízalo. No dupliques fórmulas.
8. No cambies reglas de negocio existentes salvo lo que este documento pida explícitamente. Si detectas un bug, repórtalo en el plan en lugar de corregirlo en silencio.
9. Reglas fiscales (tasas, claves SAT, plazos): van en catálogos parametrizables, nunca en código, y requieren confirmación de Contabilidad/Fiscal antes de liberarse.

### Paso 0: prerrequisitos que debes verificar antes de programar

- Función de posición presupuestal de RP-01 (fuente única).
- Bitácora de overrides (RA-01) con tipo SOBREGIRO_PRESUPUESTAL. ATENCIÓN: RA-01 está en F2 y este reporte en F1; si la bitácora no existe, el plan debe proponer crear primero su tabla y el servicio de registro (solo la parte mínima).
- Documentos en trámite: requisiciones y OC en flujo de autorización con importe.
- Infraestructura de correo operativa (SMTP Office 365 ya configurado en el servidor de aplicaciones).

Si alguno no existe, repórtalo en el plan con su impacto; no lo sustituyas con datos simulados.

## B) NOMBRE Y PROPÓSITO DEL REPORTE

- ID: `RP-03` · Dominio: A. Presupuesto y control del compromiso · Fase: F1
- Nombre técnico: `rpt_budget_alerts`
- Nombre de negocio: Alertas de agotamiento, sobregiro y excepciones autorizadas
- Frecuencia: Batch diario + disparo por evento
- Consumidor principal: Contraloría, jefes de departamento
- Granularidad (una fila =): Una fila por renglón presupuestal en riesgo o con excepción aplicada.

**Pregunta de negocio que responde / decisión que habilita:** Aviso antes del sobregiro, no en el cierre. Y el listado auditable de cada vez que se decidió comprar sin presupuesto, con nombre del que lo autorizó.

**Requerimiento original de Contabilidad y Finanzas (campos mínimos):**

  • Renglón · % consumido · Disponible · Ritmo de gasto de los últimos 3 meses · Mes proyectado de agotamiento
  • Documentos en trámite que provocarían el sobregiro
  • Excepciones autorizadas: folio, importe, autorizador, motivo, fecha

## C) COLUMNAS / CAMPOS

Origen = SUPUESTO de nombre; mapea al esquema real en el Paso 0. O = obligatoria, Op = opcional.

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| company_name | NVARCHAR | companies |  | texto | O |
| cost_center / budget_line | NVARCHAR | catálogos |  | texto | O |
| responsible_name | NVARCHAR | cost_centers → users |  | texto | O |
| current_budget | DECIMAL(18,2) | fn_budget_position |  | moneda | O |
| consumed_total | DECIMAL(18,2) | fn_budget_position | R+C+D+P | moneda | O |
| consumed_pct | DECIMAL(9,4) | calculada | consumed_total / NULLIF(current_budget,0) | % | O |
| available | DECIMAL(18,2) | fn_budget_position |  | moneda | O |
| burn_rate_3m | DECIMAL(18,2) | budget_ledger_entries | (Σ committed+accrued+paid registrados en los 3 meses cerrados previos) / 3 | moneda/mes | O |
| months_to_exhaustion | DECIMAL(9,2) | calculada | available / NULLIF(burn_rate_3m,0); NULL si burn_rate = 0 | número 1 decimal | O |
| projected_exhaustion_month | DATE | calculada | primer día del mes = hoy + months_to_exhaustion | mmm-yyyy | O |
| pending_docs_count / pending_docs_amount | INT / DECIMAL(18,2) | requisitions, purchase_orders en autorización | Σ importe de documentos en trámite | moneda | O |
| overdraft_if_approved | DECIMAL(18,2) | calculada | available − pending_docs_amount (negativo = sobregiro) | moneda en rojo | O |
| alert_level | TINYINT | calculada | máximo umbral cruzado (80/90/100) | badge | O |
| exception_folio / exception_amount / exception_authorizer / exception_reason / exception_at | varios | override_logs (type='SOBREGIRO_PRESUPUESTAL') |  | texto/moneda/fecha-hora | O en sección de excepciones |

**Detalle expandible (segundo nivel):**

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| pending_document_folio | NVARCHAR | requisitions/purchase_orders | documentos en trámite que provocarían el sobregiro | liga | O |

## D) FILTROS Y PARÁMETROS

**Filtros fijos (siempre aplicados):**

- Solo renglones con consumed_pct ≥ umbral mínimo seleccionado, o con al menos una excepción en el periodo.

**Parámetros de entrada (según el Excel: Umbral de consumo (80 %, 90 %, 100 %), empresa, centro de costo, responsable.):**

| Parámetro | Tipo | Valor por defecto | O/Op |
|---|---|---|---|
| threshold | enum 80/90/100 | 80 | O |
| company_ids | array<int> | empresas del usuario | O |
| cost_center_ids | array<int> | todos | Op |
| responsible_user_id | int | todos | Op |
| exceptions_date_from / to | date | ejercicio en curso | Op |

## E) AGRUPACIONES, ORDEN Y GRANULARIDAD

- Dos secciones: (1) Renglones en riesgo, ORDER BY overdraft_if_approved ASC, consumed_pct DESC; (2) Excepciones autorizadas, ORDER BY exception_at DESC.
- Totales: número de renglones por nivel de alerta; importe total de excepciones.

## F) REGLAS DE NEGOCIO Y CÁLCULOS

- Disparo por evento: al aprobar/emitir una requisición u OC, recalcular el renglón con `fn_budget_position`; si cruza un umbral, disparar el evento `BudgetThresholdCrossed` y notificar al responsable y a Contraloría.
- Batch diario (06:00 America/Ciudad_Juarez): recorre todos los renglones y envía un resumen por responsable.
- Deduplicación: una notificación por renglón + umbral + mes (tabla `budget_alert_notifications` con índice único).
- Cada excepción de sobregiro debe tener autorizador, motivo y sello de tiempo; se lee de la bitácora RA-01, no de otra tabla.

## G) FORMATO DE SALIDA

- Tarjetas: renglones en 80 %, 90 %, ≥100 %; importe de excepciones del periodo.
- Tabla de riesgo con barra de consumo y columna de mes proyectado de agotamiento.
- Tabla de excepciones con liga a la evidencia.

Convenciones comunes de salida:
- Pantalla: tabla con DataTables en modo server-side (paginación en servidor de 25/50/100 filas), encabezado fijo y primera columna congelada, panel de filtros arriba con los parámetros de la sección D y botón "Limpiar filtros".
- Excel (.xlsx) con celdas tipadas de verdad (moneda `$#,##0.00`, fechas `dd/mm/yyyy`, porcentaje `0.0%`), nunca números como texto; encabezado con nombre del reporte, empresa(s), filtros aplicados, fecha/hora de generación y usuario. Escritura en streaming (chunks), sin cargar todo en memoria.
- CSV UTF-8 con BOM y separador coma.
- Exportaciones de más de 50 000 filas se encolan como job y se notifica al usuario cuando el archivo está listo.
- La exportación respeta exactamente los filtros activos en pantalla.

## H) CONSIDERACIONES TÉCNICAS

**Tablas/vistas probables (SUPUESTO):** fn_budget_position (RP-01), budget_ledger_entries, requisitions, purchase_orders, override_logs (RA-01), budget_alert_notifications (nueva).

**Índices recomendados (valida contra los existentes antes de crear):**

- override_logs (override_type, occurred_at) INCLUDE (amount, authorizer_id).
- budget_alert_notifications UNIQUE (budget_line_id, cost_center_id, threshold, year_month).

**Seguridad y permisos:**

- Permisos granulares: `reportes.budget_alerts.ver` y `reportes.budget_alerts.exportar` (crea los permisos con el mecanismo que ya use el repo).
- Alcance por empresa: el usuario solo ve las empresas que tiene asignadas; el filtro se aplica en el servidor (scope/policy), nunca solo en la UI.
- Cada exportación se registra en bitácora: usuario, fecha/hora, filtros, número de filas.

- Jefes de departamento ven solo sus centros de costo.

**Endpoints y componentes:**

- GET /reportes/presupuesto/alertas (+ /data, /export).
- Job `BudgetAlertsDailyJob` en el scheduler; listener de `BudgetThresholdCrossed`.

## I) CRITERIOS DE ACEPTACIÓN (Definition of Done)

Criterio de negocio original: _Ninguna excepción existe sin autorizador, motivo capturado y sello de tiempo. El reporte cuadra contra la bitácora RA-01._

- [ ] Ninguna excepción listada carece de autorizador, motivo o sello de tiempo.
- [ ] El número e importe de excepciones de sobregiro del periodo es idéntico al de RA-01 filtrado por ese tipo.
- [ ] Una prueba demuestra que aprobar una requisición que cruza el 90 % genera exactamente una notificación, y aprobar otra en el mismo mes no genera una segunda del mismo umbral.
- [ ] Todos los filtros de la sección D funcionan combinados y la exportación reproduce exactamente la vista filtrada.
- [ ] Usuarios sin permiso reciben 403 y ningún usuario ve empresas fuera de su alcance.
- [ ] La consulta principal responde en menos de 3 s con el volumen de un año de operación (o se justifica el snapshot/job).

**Preguntas abiertas (aplica el SUPUESTO indicado si no hay respuesta):**

- ¿Destinatarios de la alerta? SUPUESTO: responsable del centro de costo + buzón de Contraloría.

## J) ENTREGABLE ESPERADO

Trabaja en tres entregas y DETENTE al terminar la primera:

1. **PLAN (sin código).**
   - Tabla de mapeo de esquema (campo requerido → tabla.campo real | NO EXISTE).
   - Estado de cada prerrequisito del Paso 0: existe / parcial / no existe, con su impacto en el reporte.
   - Arquitectura: archivos a crear o modificar (migraciones, vista o función SQL, clase de servicio del reporte, FormRequest de parámetros, controlador, rutas, vista Blade/JS, clase de exportación, policy/permisos, jobs y scheduler si aplica).
   - SQL propuesto (borrador) de la consulta principal.
   - Preguntas abiertas para Contraloría/Contabilidad/Fiscal, cada una con el supuesto que aplicarás si no hay respuesta.
   - Espera mi aprobación antes de continuar.
2. **CÓDIGO.** Implementa exactamente lo aprobado: migraciones aditivas, consulta encapsulada, servicio, endpoint, vista, exportaciones, permisos, jobs. Commits pequeños y descriptivos.
3. **PRUEBAS Y VALIDACIÓN.**
   - Pruebas de feature (Pest o PHPUnit, lo que use el repo) con datos semilla que ejerzan cada regla de negocio y cada criterio de aceptación de la sección I.
   - Pruebas de permisos: sin permiso → 403; alcance por empresa/proveedor respetado.
   - Script SQL de verificación que demuestre las identidades de cuadre del reporte.
   - Plan de ejecución en SQL Server con volumen realista; reporta tiempos y los índices creados.
   - Lista explícita de lo que quedó pendiente o fuera de alcance.

------------------------------------------------

================================================
REPORTE #4: RC-01 · Pipeline de requisiciones y tiempos de ciclo
================================================

# REPORTE RC-01: Pipeline de requisiciones y tiempos de ciclo

## A) ROL Y OBJETIVO

Actúa como desarrollador senior full-stack en el Portal de Proveedores (Módulo de Compras) de TotalGas, con experiencia en reporting financiero para Contabilidad, Contraloría y Tesorería en México (CFDI 4.0, SAT, REPSE).

**Objetivo medible:** Mostrar dónde está detenida cada requisición, con quién y cuántas horas lleva ahí, de modo que el tiempo total de ciclo sea exactamente la suma de los tiempos por paso.

### Contexto técnico del proyecto
- Aplicación: Portal de Proveedores / Módulo de Compras de TotalGas (grupo gasolinero con 36 estaciones, Cd. Juárez, Chih.). Ya cubre requisiciones, cotizaciones, órdenes de compra, recepciones, control presupuestal y validación CFDI/SAT (RFC, listado 69-B). Cada centro de costo ya tiene responsable asignado.
- Stack: Laravel 12, PHP 8.3, SQL Server 2019, Blade + Bootstrap 5 + jQuery. Si el repositorio ya usa Livewire, DataTables, Laravel Excel u OpenSpout, spatie/laravel-permission o dompdf/snappy, REUTILÍZALOS. No agregues dependencias nuevas sin justificarlas en el plan.
- Zona horaria de negocio: `America/Ciudad_Juarez`. Toda "fecha de corte" se interpreta a las 23:59:59 de esa zona.
- Moneda funcional: MXN. Los nombres de tablas y campos de este documento son SUPUESTOS: el esquema real del repositorio manda.

### Reglas de trabajo (obligatorias)
1. Antes de escribir código, inspecciona migraciones, modelos Eloquent, vistas/funciones SQL, servicios y jobs existentes. Construye una tabla de mapeo: `campo requerido → tabla.campo real | NO EXISTE`.
2. No crees tablas paralelas para datos que ya existen con otro nombre. Si falta un dato, propón en el plan la migración mínima y aditiva, y espera aprobación.
3. Si un prerrequisito no existe, NO simules el dato (ni ceros, ni NULL silenciosos, ni valores fijos). Repórtalo y separa en el plan "lo que se puede entregar hoy" de "lo que requiere construir el control primero". Un reporte que cuadra con datos falsos es peor que no tener reporte.
4. Montos en `DECIMAL(18,2)`; tipos de cambio en `DECIMAL(18,6)`; nunca `FLOAT`. Redondea a 2 decimales a nivel de renglón y después suma; usa el mismo criterio que los demás reportes con los que este debe cuadrar.
5. Consultas siempre parametrizadas (bindings). Prohibido concatenar filtros en SQL.
6. El reporte no consulta en línea los linked servers de las estaciones; usa las tablas ya consolidadas en el servidor del portal.
7. Una sola fuente de verdad por cálculo: si otro reporte o el motor de control usa el mismo cálculo, encapsúlalo (vista, función de tabla en línea o clase de servicio) y reutilízalo. No dupliques fórmulas.
8. No cambies reglas de negocio existentes salvo lo que este documento pida explícitamente. Si detectas un bug, repórtalo en el plan en lugar de corregirlo en silencio.
9. Reglas fiscales (tasas, claves SAT, plazos): van en catálogos parametrizables, nunca en código, y requieren confirmación de Contabilidad/Fiscal antes de liberarse.

### Paso 0: prerrequisitos que debes verificar antes de programar

- Historial de pasos de autorización con fecha/hora de entrada y salida por paso (no solo el estatus actual). Si solo se guarda el estatus vigente, el tiempo por paso no se puede reconstruir: el plan debe proponer registrar el historial desde ahora.
- Marca REPSE en la requisición.
- Motivo obligatorio en rechazo/cancelación (verifica si la validación existe).

Si alguno no existe, repórtalo en el plan con su impacto; no lo sustituyas con datos simulados.

## B) NOMBRE Y PROPÓSITO DEL REPORTE

- ID: `RC-01` · Dominio: B. Requisiciones, autorizaciones y órdenes de compra · Fase: F1
- Nombre técnico: `rpt_requisition_pipeline`
- Nombre de negocio: Pipeline de requisiciones y tiempos de ciclo
- Frecuencia: Diaria
- Consumidor principal: Compras, Contraloría, jefes de departamento
- Granularidad (una fila =): Una fila por requisición, con detalle expandible por paso de autorización.

**Pregunta de negocio que responde / decisión que habilita:** Dónde se atoran las compras y con quién. Convierte "Compras no responde" en un dato verificable: el paso, el nombre y las horas transcurridas.

**Requerimiento original de Contabilidad y Finanzas (campos mínimos):**

  • Folio · Fecha · Solicitante · Centro de costo · Importe estimado · Marca REPSE
  • Estatus actual · Paso en el que está detenida · Aprobador pendiente · Horas en el paso actual
  • Tiempo total de ciclo · Motivo de rechazo o cancelación

## C) COLUMNAS / CAMPOS

Origen = SUPUESTO de nombre; mapea al esquema real en el Paso 0. O = obligatoria, Op = opcional.

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| requisition_folio | NVARCHAR(30) | requisitions.folio |  | texto con liga | O |
| created_at | DATETIME2 | requisitions.created_at |  | fecha-hora | O |
| requester_name | NVARCHAR(150) | users |  | texto | O |
| cost_center | NVARCHAR | cost_centers |  | texto | O |
| estimated_amount | DECIMAL(18,2) | Σ requisition_items |  | moneda | O |
| is_repse | BIT | requisitions.is_repse |  | sí/no | O |
| current_status | VARCHAR(20) | requisitions.status |  | badge | O |
| current_step_name | NVARCHAR(100) | approval_step_logs (último abierto) |  | texto | O |
| pending_approver | NVARCHAR(150) | approval_step_logs.assigned_user_id |  | texto | O |
| hours_in_current_step | DECIMAL(9,1) | calculada | DATEDIFF(minute, entered_at, now)/60.0 | número | O |
| total_cycle_hours | DECIMAL(9,1) | calculada | Σ horas de todos los pasos (hasta cierre o ahora) | número | O |
| outcome_reason | NVARCHAR(500) | requisitions.rejection_reason / cancellation_reason | obligatorio si RECHAZADA/CANCELADA | texto | O |
| po_folio | NVARCHAR(30) | purchase_orders | OC generada, si existe | liga | Op |

**Detalle expandible (segundo nivel):**

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| step_order | INT | approval_step_logs |  | número | O |
| step_name / approver | NVARCHAR | approval_step_logs |  | texto | O |
| entered_at / exited_at | DATETIME2 | approval_step_logs |  | fecha-hora | O |
| action | VARCHAR(15) | approval_step_logs | APROBADO / RECHAZADO / DEVUELTO | texto | O |
| step_hours | DECIMAL(9,1) | calculada | DATEDIFF(minute, entered_at, COALESCE(exited_at, now))/60.0 | número | O |

## D) FILTROS Y PARÁMETROS

**Filtros fijos (siempre aplicados):**

- Excluye borradores nunca enviados.

**Parámetros de entrada (según el Excel: Estatus, aprobador, antigüedad mayor a N días, centro de costo, rango de importe.):**

| Parámetro | Tipo | Valor por defecto | O/Op |
|---|---|---|---|
| status | multi-enum | todas menos CERRADA | Op |
| pending_approver_id | int | todos | Op |
| older_than_days | int | 0 | Op |
| cost_center_ids | array<int> | todos | Op |
| amount_from / amount_to | decimal | vacío | Op |
| company_ids | array<int> | empresas del usuario | O |
| date_from / date_to (creación) | date | últimos 90 días | O |

## E) AGRUPACIONES, ORDEN Y GRANULARIDAD

- Una fila por requisición con detalle expandible por paso.
- ORDER BY hours_in_current_step DESC por defecto (lo más atorado arriba).
- Vista resumen: horas promedio y mediana por paso y por aprobador.

## F) REGLAS DE NEGOCIO Y CÁLCULOS

- Los intervalos de los pasos son contiguos: exited_at de un paso = entered_at del siguiente. El tiempo entre la creación y el primer envío se registra como paso 'Captura'. Así Σ step_hours = total_cycle_hours por construcción.
- Horas naturales (SUPUESTO). Si se pide horas hábiles, parametrizar calendario laboral en una segunda versión.
- Una requisición devuelta y reenviada genera nuevas entradas de paso, no sobrescribe las anteriores.
- Rechazo o cancelación sin motivo = error de validación en el formulario (si no existe, agregar la validación y reportarlo en el plan).

## G) FORMATO DE SALIDA

- Tarjetas: requisiciones abiertas, horas promedio en paso actual, top 5 aprobadores con más pendientes.
- Tabla con fila expandible (timeline de pasos).
- Gráfico de barras: horas promedio por paso.

Convenciones comunes de salida:
- Pantalla: tabla con DataTables en modo server-side (paginación en servidor de 25/50/100 filas), encabezado fijo y primera columna congelada, panel de filtros arriba con los parámetros de la sección D y botón "Limpiar filtros".
- Excel (.xlsx) con celdas tipadas de verdad (moneda `$#,##0.00`, fechas `dd/mm/yyyy`, porcentaje `0.0%`), nunca números como texto; encabezado con nombre del reporte, empresa(s), filtros aplicados, fecha/hora de generación y usuario. Escritura en streaming (chunks), sin cargar todo en memoria.
- CSV UTF-8 con BOM y separador coma.
- Exportaciones de más de 50 000 filas se encolan como job y se notifica al usuario cuando el archivo está listo.
- La exportación respeta exactamente los filtros activos en pantalla.

## H) CONSIDERACIONES TÉCNICAS

**Tablas/vistas probables (SUPUESTO):** requisitions, requisition_items, approval_step_logs (o la tabla de historial existente), users, cost_centers, purchase_orders.

**Índices recomendados (valida contra los existentes antes de crear):**

- approval_step_logs (requisition_id, step_order).
- approval_step_logs (assigned_user_id) WHERE exited_at IS NULL (índice filtrado para pendientes).

**Seguridad y permisos:**

- Permisos granulares: `reportes.requisition_pipeline.ver` y `reportes.requisition_pipeline.exportar` (crea los permisos con el mecanismo que ya use el repo).
- Alcance por empresa: el usuario solo ve las empresas que tiene asignadas; el filtro se aplica en el servidor (scope/policy), nunca solo en la UI.
- Cada exportación se registra en bitácora: usuario, fecha/hora, filtros, número de filas.

- Un jefe de departamento ve solo sus centros de costo; Compras y Contraloría ven todo.

**Endpoints y componentes:**

- GET /reportes/compras/pipeline-requisiciones (+ /data, /export, /{id}/pasos).

## I) CRITERIOS DE ACEPTACIÓN (Definition of Done)

Criterio de negocio original: _El tiempo de ciclo es exactamente la suma de los tiempos por paso. Toda requisición rechazada o cancelada tiene motivo obligatorio._

- [ ] Para toda requisición, total_cycle_hours = Σ step_hours (consulta de verificación con 0 diferencias).
- [ ] No existe requisición RECHAZADA o CANCELADA sin motivo.
- [ ] El aprobador pendiente coincide con quien ve la requisición en su bandeja.
- [ ] Todos los filtros de la sección D funcionan combinados y la exportación reproduce exactamente la vista filtrada.
- [ ] Usuarios sin permiso reciben 403 y ningún usuario ve empresas fuera de su alcance.
- [ ] La consulta principal responde en menos de 3 s con el volumen de un año de operación (o se justifica el snapshot/job).

**Preguntas abiertas (aplica el SUPUESTO indicado si no hay respuesta):**

- ¿Horas naturales u hábiles? SUPUESTO: naturales.

## J) ENTREGABLE ESPERADO

Trabaja en tres entregas y DETENTE al terminar la primera:

1. **PLAN (sin código).**
   - Tabla de mapeo de esquema (campo requerido → tabla.campo real | NO EXISTE).
   - Estado de cada prerrequisito del Paso 0: existe / parcial / no existe, con su impacto en el reporte.
   - Arquitectura: archivos a crear o modificar (migraciones, vista o función SQL, clase de servicio del reporte, FormRequest de parámetros, controlador, rutas, vista Blade/JS, clase de exportación, policy/permisos, jobs y scheduler si aplica).
   - SQL propuesto (borrador) de la consulta principal.
   - Preguntas abiertas para Contraloría/Contabilidad/Fiscal, cada una con el supuesto que aplicarás si no hay respuesta.
   - Espera mi aprobación antes de continuar.
2. **CÓDIGO.** Implementa exactamente lo aprobado: migraciones aditivas, consulta encapsulada, servicio, endpoint, vista, exportaciones, permisos, jobs. Commits pequeños y descriptivos.
3. **PRUEBAS Y VALIDACIÓN.**
   - Pruebas de feature (Pest o PHPUnit, lo que use el repo) con datos semilla que ejerzan cada regla de negocio y cada criterio de aceptación de la sección I.
   - Pruebas de permisos: sin permiso → 403; alcance por empresa/proveedor respetado.
   - Script SQL de verificación que demuestre las identidades de cuadre del reporte.
   - Plan de ejecución en SQL Server con volumen realista; reporta tiempos y los índices creados.
   - Lista explícita de lo que quedó pendiente o fuera de alcance.

------------------------------------------------

================================================
REPORTE #5: RC-02 · Órdenes de compra emitidas
================================================

# REPORTE RC-02: Órdenes de compra emitidas

## A) ROL Y OBJETIVO

Actúa como desarrollador senior full-stack en el Portal de Proveedores (Módulo de Compras) de TotalGas, con experiencia en reporting financiero para Contabilidad, Contraloría y Tesorería en México (CFDI 4.0, SAT, REPSE).

**Objetivo medible:** Listar el universo de OC emitidas en el periodo con su origen, importes fiscales y acuse del proveedor, y medir la proporción de OC directas (conteo e importe).

### Contexto técnico del proyecto
- Aplicación: Portal de Proveedores / Módulo de Compras de TotalGas (grupo gasolinero con 36 estaciones, Cd. Juárez, Chih.). Ya cubre requisiciones, cotizaciones, órdenes de compra, recepciones, control presupuestal y validación CFDI/SAT (RFC, listado 69-B). Cada centro de costo ya tiene responsable asignado.
- Stack: Laravel 12, PHP 8.3, SQL Server 2019, Blade + Bootstrap 5 + jQuery. Si el repositorio ya usa Livewire, DataTables, Laravel Excel u OpenSpout, spatie/laravel-permission o dompdf/snappy, REUTILÍZALOS. No agregues dependencias nuevas sin justificarlas en el plan.
- Zona horaria de negocio: `America/Ciudad_Juarez`. Toda "fecha de corte" se interpreta a las 23:59:59 de esa zona.
- Moneda funcional: MXN. Los nombres de tablas y campos de este documento son SUPUESTOS: el esquema real del repositorio manda.

### Reglas de trabajo (obligatorias)
1. Antes de escribir código, inspecciona migraciones, modelos Eloquent, vistas/funciones SQL, servicios y jobs existentes. Construye una tabla de mapeo: `campo requerido → tabla.campo real | NO EXISTE`.
2. No crees tablas paralelas para datos que ya existen con otro nombre. Si falta un dato, propón en el plan la migración mínima y aditiva, y espera aprobación.
3. Si un prerrequisito no existe, NO simules el dato (ni ceros, ni NULL silenciosos, ni valores fijos). Repórtalo y separa en el plan "lo que se puede entregar hoy" de "lo que requiere construir el control primero". Un reporte que cuadra con datos falsos es peor que no tener reporte.
4. Montos en `DECIMAL(18,2)`; tipos de cambio en `DECIMAL(18,6)`; nunca `FLOAT`. Redondea a 2 decimales a nivel de renglón y después suma; usa el mismo criterio que los demás reportes con los que este debe cuadrar.
5. Consultas siempre parametrizadas (bindings). Prohibido concatenar filtros en SQL.
6. El reporte no consulta en línea los linked servers de las estaciones; usa las tablas ya consolidadas en el servidor del portal.
7. Una sola fuente de verdad por cálculo: si otro reporte o el motor de control usa el mismo cálculo, encapsúlalo (vista, función de tabla en línea o clase de servicio) y reutilízalo. No dupliques fórmulas.
8. No cambies reglas de negocio existentes salvo lo que este documento pida explícitamente. Si detectas un bug, repórtalo en el plan en lugar de corregirlo en silencio.
9. Reglas fiscales (tasas, claves SAT, plazos): van en catálogos parametrizables, nunca en código, y requieren confirmación de Contabilidad/Fiscal antes de liberarse.

### Paso 0: prerrequisitos que debes verificar antes de programar

- Campo de origen de la OC (requisición / directa / contrato) y tipo (cerrada / abierta).
- Fecha de envío al proveedor y fecha de acuse del proveedor.
- Catálogo de tipos de gasto habilitados para OC directa y referencia al contrato/autorización que la habilita.
- Asignación de renglón presupuestal por renglón de OC.

Si alguno no existe, repórtalo en el plan con su impacto; no lo sustituyas con datos simulados.

## B) NOMBRE Y PROPÓSITO DEL REPORTE

- ID: `RC-02` · Dominio: B. Requisiciones, autorizaciones y órdenes de compra · Fase: F1
- Nombre técnico: `rpt_purchase_orders_issued`
- Nombre de negocio: Órdenes de compra emitidas
- Frecuencia: Diaria y bajo demanda
- Consumidor principal: Compras, Contraloría
- Granularidad (una fila =): Una fila por orden de compra, con detalle por renglón.

**Pregunta de negocio que responde / decisión que habilita:** El universo del compromiso del periodo y, sobre todo, la proporción de OC directas. Si esa proporción crece, el control por requisición se está vaciando por la puerta de atrás y hay que revisar qué tipos de gasto están habilitados para el flujo directo.

**Requerimiento original de Contabilidad y Finanzas (campos mínimos):**

  • Folio · Empresa · Proveedor y RFC · Origen (requisición / directa / contrato) · Tipo (cerrada / abierta)
  • Moneda y tipo de cambio · Subtotal · Impuestos trasladados · Retenciones · Total
  • Centro de costo y renglón · Comprador responsable · Estatus
  • Fecha de envío al proveedor · Fecha de acuse del proveedor · Vigencia

## C) COLUMNAS / CAMPOS

Origen = SUPUESTO de nombre; mapea al esquema real en el Paso 0. O = obligatoria, Op = opcional.

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| po_folio | NVARCHAR(30) | purchase_orders.folio |  | liga | O |
| company_name | NVARCHAR | companies |  | texto | O |
| supplier_name / supplier_rfc | NVARCHAR | suppliers |  | texto | O |
| origin | VARCHAR(12) | purchase_orders.origin | REQUISICION / DIRECTA / CONTRATO | badge | O |
| po_type | VARCHAR(10) | purchase_orders.type | CERRADA / ABIERTA | texto | O |
| currency / exchange_rate | CHAR(3) / DECIMAL(18,6) | purchase_orders |  | texto / 4 decimales | O |
| subtotal | DECIMAL(18,2) | purchase_orders.subtotal |  | moneda origen | O |
| transferred_taxes | DECIMAL(18,2) | purchase_orders.tax_amount |  | moneda origen | O |
| withholdings | DECIMAL(18,2) | purchase_orders.withholding_amount |  | moneda origen | O |
| total | DECIMAL(18,2) | calculada | subtotal + transferred_taxes − withholdings | moneda origen | O |
| total_mxn | DECIMAL(18,2) | calculada | total × exchange_rate | moneda MXN | O |
| cost_center / budget_line | NVARCHAR | purchase_order_items | en encabezado: 'Varios' si hay más de uno | texto | O |
| buyer_name | NVARCHAR | users |  | texto | O |
| status | VARCHAR(15) | purchase_orders.status |  | badge | O |
| sent_at / supplier_ack_at | DATETIME2 | purchase_orders |  | fecha-hora | O |
| hours_to_ack | DECIMAL(9,1) | calculada | DATEDIFF(minute, sent_at, supplier_ack_at)/60.0 | número | Op |
| valid_from / valid_to | DATE | purchase_orders |  | fecha | O |
| direct_expense_type | NVARCHAR | direct_po_expense_types | solo OC directas | texto | O si DIRECTA |
| enabling_reference | NVARCHAR | contracts / authorizations | contrato o autorización que habilitó la OC directa | liga | O si DIRECTA |

**Detalle expandible (segundo nivel):**

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| po_line / item / cost_center / budget_line / quantity / unit_price / line_subtotal | varios | purchase_order_items |  |  | O |

## D) FILTROS Y PARÁMETROS

**Filtros fijos (siempre aplicados):**

- Incluye todas las OC emitidas (no borradores). Las canceladas aparecen con su estatus pero no suman en KPIs de compromiso.

**Parámetros de entrada (según el Excel: Origen, proveedor, comprador, empresa, periodo, estatus, con o sin acuse.):**

| Parámetro | Tipo | Valor por defecto | O/Op |
|---|---|---|---|
| company_ids | array<int> | empresas del usuario | O |
| date_from / date_to (emisión) | date | mes en curso | O |
| origin | multi-enum | todos | Op |
| supplier_id | int | todos | Op |
| buyer_id | int | todos | Op |
| status | multi-enum | todos menos BORRADOR | Op |
| ack_filter | enum CON_ACUSE/SIN_ACUSE/TODAS | TODAS | Op |

## E) AGRUPACIONES, ORDEN Y GRANULARIDAD

- Una fila por OC, detalle por renglón.
- ORDER BY issued_at DESC.
- Totales en MXN por origen, por comprador y general.

## F) REGLAS DE NEGOCIO Y CÁLCULOS

- % OC directas = OC DIRECTA / total OC no canceladas, en conteo y en importe MXN; se muestran ambos.
- Toda OC DIRECTA debe tener direct_expense_type y enabling_reference; las que no, se listan en una sección 'Excepciones' y cuentan para RR-03.
- Ninguna OC sin renglón presupuestal: consulta de validación; si devuelve filas, se muestran como excepción, no se ocultan.
- Tipo de cambio: el de la OC al emitirse (SUPUESTO).

## G) FORMATO DE SALIDA

- Tarjetas: número de OC, importe MXN, % directas (conteo / importe), % sin acuse > 48 h.
- Gráfico de líneas mensual de % de OC directas (últimos 12 meses) para ver la tendencia.
- Tabla con fila expandible por renglón.

Convenciones comunes de salida:
- Pantalla: tabla con DataTables en modo server-side (paginación en servidor de 25/50/100 filas), encabezado fijo y primera columna congelada, panel de filtros arriba con los parámetros de la sección D y botón "Limpiar filtros".
- Excel (.xlsx) con celdas tipadas de verdad (moneda `$#,##0.00`, fechas `dd/mm/yyyy`, porcentaje `0.0%`), nunca números como texto; encabezado con nombre del reporte, empresa(s), filtros aplicados, fecha/hora de generación y usuario. Escritura en streaming (chunks), sin cargar todo en memoria.
- CSV UTF-8 con BOM y separador coma.
- Exportaciones de más de 50 000 filas se encolan como job y se notifica al usuario cuando el archivo está listo.
- La exportación respeta exactamente los filtros activos en pantalla.

## H) CONSIDERACIONES TÉCNICAS

**Tablas/vistas probables (SUPUESTO):** purchase_orders, purchase_order_items, suppliers, companies, users, cost_centers, budget_lines, contracts, direct_po_expense_types.

**Índices recomendados (valida contra los existentes antes de crear):**

- purchase_orders (company_id, issued_at) INCLUDE (origin, status, total, currency, exchange_rate, supplier_id, buyer_id).

**Seguridad y permisos:**

- Permisos granulares: `reportes.purchase_orders_issued.ver` y `reportes.purchase_orders_issued.exportar` (crea los permisos con el mecanismo que ya use el repo).
- Alcance por empresa: el usuario solo ve las empresas que tiene asignadas; el filtro se aplica en el servidor (scope/policy), nunca solo en la UI.
- Cada exportación se registra en bitácora: usuario, fecha/hora, filtros, número de filas.

- Compras ve todo su alcance; compradores ven solo sus OC si así lo define la matriz de roles actual.

**Endpoints y componentes:**

- GET /reportes/compras/oc-emitidas (+ /data, /export).

## I) CRITERIOS DE ACEPTACIÓN (Definition of Done)

Criterio de negocio original: _Toda OC directa muestra el tipo de gasto autorizado y el contrato o autorización que la habilitó. Ninguna OC existe sin renglón presupuestal asignado._

- [ ] Toda OC directa muestra tipo de gasto autorizado y el contrato o autorización que la habilitó; las que no, aparecen en Excepciones.
- [ ] La consulta 'OC sin renglón presupuestal' devuelve 0 filas o las exhibe como excepción.
- [ ] El total MXN del reporte coincide con Σ total × TC de las OC del periodo.
- [ ] Todos los filtros de la sección D funcionan combinados y la exportación reproduce exactamente la vista filtrada.
- [ ] Usuarios sin permiso reciben 403 y ningún usuario ve empresas fuera de su alcance.
- [ ] La consulta principal responde en menos de 3 s con el volumen de un año de operación (o se justifica el snapshot/job).

**Preguntas abiertas (aplica el SUPUESTO indicado si no hay respuesta):**

- ¿Qué tipos de gasto están habilitados para OC directa? SUPUESTO: catálogo administrable por Contraloría.

## J) ENTREGABLE ESPERADO

Trabaja en tres entregas y DETENTE al terminar la primera:

1. **PLAN (sin código).**
   - Tabla de mapeo de esquema (campo requerido → tabla.campo real | NO EXISTE).
   - Estado de cada prerrequisito del Paso 0: existe / parcial / no existe, con su impacto en el reporte.
   - Arquitectura: archivos a crear o modificar (migraciones, vista o función SQL, clase de servicio del reporte, FormRequest de parámetros, controlador, rutas, vista Blade/JS, clase de exportación, policy/permisos, jobs y scheduler si aplica).
   - SQL propuesto (borrador) de la consulta principal.
   - Preguntas abiertas para Contraloría/Contabilidad/Fiscal, cada una con el supuesto que aplicarás si no hay respuesta.
   - Espera mi aprobación antes de continuar.
2. **CÓDIGO.** Implementa exactamente lo aprobado: migraciones aditivas, consulta encapsulada, servicio, endpoint, vista, exportaciones, permisos, jobs. Commits pequeños y descriptivos.
3. **PRUEBAS Y VALIDACIÓN.**
   - Pruebas de feature (Pest o PHPUnit, lo que use el repo) con datos semilla que ejerzan cada regla de negocio y cada criterio de aceptación de la sección I.
   - Pruebas de permisos: sin permiso → 403; alcance por empresa/proveedor respetado.
   - Script SQL de verificación que demuestre las identidades de cuadre del reporte.
   - Plan de ejecución en SQL Server con volumen realista; reporta tiempos y los índices creados.
   - Lista explícita de lo que quedó pendiente o fuera de alcance.

------------------------------------------------

================================================
REPORTE #6: RC-03 · Órdenes de compra abiertas y backlog por recibir
================================================

# REPORTE RC-03: Órdenes de compra abiertas y backlog por recibir

## A) ROL Y OBJETIVO

Actúa como desarrollador senior full-stack en el Portal de Proveedores (Módulo de Compras) de TotalGas, con experiencia en reporting financiero para Contabilidad, Contraloría y Tesorería en México (CFDI 4.0, SAT, REPSE).

**Objetivo medible:** Listar cada renglón de OC con saldo por recibir, sus días de atraso y el saldo de OC abiertas, de modo que el importe pendiente sea idéntico al cubo Comprometido de RP-01 al mismo corte.

### Contexto técnico del proyecto
- Aplicación: Portal de Proveedores / Módulo de Compras de TotalGas (grupo gasolinero con 36 estaciones, Cd. Juárez, Chih.). Ya cubre requisiciones, cotizaciones, órdenes de compra, recepciones, control presupuestal y validación CFDI/SAT (RFC, listado 69-B). Cada centro de costo ya tiene responsable asignado.
- Stack: Laravel 12, PHP 8.3, SQL Server 2019, Blade + Bootstrap 5 + jQuery. Si el repositorio ya usa Livewire, DataTables, Laravel Excel u OpenSpout, spatie/laravel-permission o dompdf/snappy, REUTILÍZALOS. No agregues dependencias nuevas sin justificarlas en el plan.
- Zona horaria de negocio: `America/Ciudad_Juarez`. Toda "fecha de corte" se interpreta a las 23:59:59 de esa zona.
- Moneda funcional: MXN. Los nombres de tablas y campos de este documento son SUPUESTOS: el esquema real del repositorio manda.

### Reglas de trabajo (obligatorias)
1. Antes de escribir código, inspecciona migraciones, modelos Eloquent, vistas/funciones SQL, servicios y jobs existentes. Construye una tabla de mapeo: `campo requerido → tabla.campo real | NO EXISTE`.
2. No crees tablas paralelas para datos que ya existen con otro nombre. Si falta un dato, propón en el plan la migración mínima y aditiva, y espera aprobación.
3. Si un prerrequisito no existe, NO simules el dato (ni ceros, ni NULL silenciosos, ni valores fijos). Repórtalo y separa en el plan "lo que se puede entregar hoy" de "lo que requiere construir el control primero". Un reporte que cuadra con datos falsos es peor que no tener reporte.
4. Montos en `DECIMAL(18,2)`; tipos de cambio en `DECIMAL(18,6)`; nunca `FLOAT`. Redondea a 2 decimales a nivel de renglón y después suma; usa el mismo criterio que los demás reportes con los que este debe cuadrar.
5. Consultas siempre parametrizadas (bindings). Prohibido concatenar filtros en SQL.
6. El reporte no consulta en línea los linked servers de las estaciones; usa las tablas ya consolidadas en el servidor del portal.
7. Una sola fuente de verdad por cálculo: si otro reporte o el motor de control usa el mismo cálculo, encapsúlalo (vista, función de tabla en línea o clase de servicio) y reutilízalo. No dupliques fórmulas.
8. No cambies reglas de negocio existentes salvo lo que este documento pida explícitamente. Si detectas un bug, repórtalo en el plan en lugar de corregirlo en silencio.
9. Reglas fiscales (tasas, claves SAT, plazos): van en catálogos parametrizables, nunca en código, y requieren confirmación de Contabilidad/Fiscal antes de liberarse.

### Paso 0: prerrequisitos que debes verificar antes de programar

- Cantidad recibida por renglón de OC (acumulada desde recepciones validadas).
- Fecha prometida por renglón (o por OC).
- Para OC abiertas: monto máximo y consumo acumulado.
- Función de posición presupuestal de RP-01 (para la conciliación).

Si alguno no existe, repórtalo en el plan con su impacto; no lo sustituyas con datos simulados.

## B) NOMBRE Y PROPÓSITO DEL REPORTE

- ID: `RC-03` · Dominio: B. Requisiciones, autorizaciones y órdenes de compra · Fase: F1
- Nombre técnico: `rpt_open_po_backlog`
- Nombre de negocio: Órdenes de compra abiertas y backlog por recibir
- Frecuencia: Semanal
- Consumidor principal: Compras, Contraloría, Tesorería
- Granularidad (una fila =): Una fila por renglón de OC con saldo pendiente de recibir.

**Pregunta de negocio que responde / decisión que habilita:** El compromiso vivo que todavía no llega: es flujo de efectivo futuro y presupuesto ya apartado. También expone las OC zombis que hay que cerrar para liberar presupuesto atrapado.

**Requerimiento original de Contabilidad y Finanzas (campos mínimos):**

  • Folio OC · Fecha · Proveedor · Renglón · Cantidad ordenada, recibida y pendiente · Importe pendiente
  • Fecha prometida · Días de atraso · Vigencia de la OC
  • En OC abiertas: monto máximo, consumido, saldo y % de agotamiento

## C) COLUMNAS / CAMPOS

Origen = SUPUESTO de nombre; mapea al esquema real en el Paso 0. O = obligatoria, Op = opcional.

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| po_folio / po_date | NVARCHAR / DATE | purchase_orders |  | liga / fecha | O |
| supplier_name | NVARCHAR | suppliers |  | texto | O |
| po_line / item_description | INT / NVARCHAR | purchase_order_items |  | texto | O |
| budget_line | NVARCHAR | budget_lines |  | texto | O |
| ordered_qty | DECIMAL(18,4) | purchase_order_items.quantity |  | número | O |
| received_qty | DECIMAL(18,4) | Σ reception_items validadas ≤ corte |  | número | O |
| pending_qty | DECIMAL(18,4) | calculada | ordered_qty − received_qty | número | O |
| unit_price | DECIMAL(18,6) | purchase_order_items |  | moneda | O |
| pending_amount_mxn | DECIMAL(18,2) | calculada | ROUND(pending_qty × unit_price × exchange_rate, 2), sin IVA | moneda | O |
| promised_date | DATE | purchase_order_items.promised_date |  | fecha | O |
| days_late | INT | calculada | MAX(0, DATEDIFF(day, promised_date, as_of)) | número (rojo > 0) | O |
| po_valid_to | DATE | purchase_orders.valid_to |  | fecha | O |
| is_expired | BIT | calculada | as_of > po_valid_to | badge | O |
| open_po_max_amount / consumed / balance / consumed_pct | DECIMAL | purchase_orders (tipo ABIERTA) | balance = max − consumido; pct = consumido/max | moneda / % | O en abiertas |
| is_zombie | BIT | calculada | vencida o sin recepción en N días (param, default 90) y con saldo | badge | O |

## D) FILTROS Y PARÁMETROS

**Filtros fijos (siempre aplicados):**

- Solo OC no canceladas ni cerradas con pending_qty > 0 (o saldo > 0 en abiertas).

**Parámetros de entrada (según el Excel: Antigüedad, proveedor, OC vencidas, OC abiertas por agotarse, empresa.):**

| Parámetro | Tipo | Valor por defecto | O/Op |
|---|---|---|---|
| as_of_date | date | hoy | O |
| company_ids | array<int> | empresas del usuario | O |
| supplier_id | int | todos | Op |
| min_age_days | int | 0 | Op |
| only_expired | bool | false | Op |
| open_po_near_exhaustion_pct | decimal | 90 % | Op |
| zombie_days | int | 90 | Op |

## E) AGRUPACIONES, ORDEN Y GRANULARIDAD

- Una fila por renglón de OC con saldo.
- ORDER BY days_late DESC, pending_amount_mxn DESC.
- Subtotales por proveedor y por empresa; total general.

## F) REGLAS DE NEGOCIO Y CÁLCULOS

- El importe pendiente se calcula con la misma regla y redondeo que el cubo Comprometido de RP-01 (idealmente leyendo el mismo libro mayor). Si difieren, el plan debe explicar por qué y corregir la fuente, no el reporte.
- Recepciones no validadas no descuentan cantidad pendiente.
- OC zombi: candidata a cierre para liberar presupuesto; el reporte solo la marca, no la cierra.

## G) FORMATO DE SALIDA

- Tarjetas: importe pendiente total, # renglones atrasados, # OC zombis y presupuesto atrapado en ellas.
- Tabla con filtro rápido 'Solo atrasados' y 'Solo zombis'.
- Excel para Tesorería con columnas de flujo esperado.

Convenciones comunes de salida:
- Pantalla: tabla con DataTables en modo server-side (paginación en servidor de 25/50/100 filas), encabezado fijo y primera columna congelada, panel de filtros arriba con los parámetros de la sección D y botón "Limpiar filtros".
- Excel (.xlsx) con celdas tipadas de verdad (moneda `$#,##0.00`, fechas `dd/mm/yyyy`, porcentaje `0.0%`), nunca números como texto; encabezado con nombre del reporte, empresa(s), filtros aplicados, fecha/hora de generación y usuario. Escritura en streaming (chunks), sin cargar todo en memoria.
- CSV UTF-8 con BOM y separador coma.
- Exportaciones de más de 50 000 filas se encolan como job y se notifica al usuario cuando el archivo está listo.
- La exportación respeta exactamente los filtros activos en pantalla.

## H) CONSIDERACIONES TÉCNICAS

**Tablas/vistas probables (SUPUESTO):** purchase_orders, purchase_order_items, reception_items, receptions, suppliers, budget_lines, fn_budget_position.

**Índices recomendados (valida contra los existentes antes de crear):**

- reception_items (purchase_order_item_id) INCLUDE (quantity) WHERE validated = 1.
- purchase_order_items (purchase_order_id) INCLUDE (quantity, unit_price, promised_date).

**Seguridad y permisos:**

- Permisos granulares: `reportes.open_po_backlog.ver` y `reportes.open_po_backlog.exportar` (crea los permisos con el mecanismo que ya use el repo).
- Alcance por empresa: el usuario solo ve las empresas que tiene asignadas; el filtro se aplica en el servidor (scope/policy), nunca solo en la UI.
- Cada exportación se registra en bitácora: usuario, fecha/hora, filtros, número de filas.

- Tesorería: solo lectura y exportación.

**Endpoints y componentes:**

- GET /reportes/compras/oc-abiertas (+ /data, /export).

## I) CRITERIOS DE ACEPTACIÓN (Definition of Done)

Criterio de negocio original: _El importe pendiente de este reporte es idéntico al cubo "Comprometido" de RP-01 al mismo corte._

- [ ] Al mismo corte y filtros, Σ pending_amount_mxn = Σ committed de RP-01 (prueba automatizada al centavo).
- [ ] Una recepción parcial reduce exactamente la cantidad recibida y nada más.
- [ ] Todos los filtros de la sección D funcionan combinados y la exportación reproduce exactamente la vista filtrada.
- [ ] Usuarios sin permiso reciben 403 y ningún usuario ve empresas fuera de su alcance.
- [ ] La consulta principal responde en menos de 3 s con el volumen de un año de operación (o se justifica el snapshot/job).

**Preguntas abiertas (aplica el SUPUESTO indicado si no hay respuesta):**

- ¿Días de gracia antes de marcar atraso? SUPUESTO: 0.

## J) ENTREGABLE ESPERADO

Trabaja en tres entregas y DETENTE al terminar la primera:

1. **PLAN (sin código).**
   - Tabla de mapeo de esquema (campo requerido → tabla.campo real | NO EXISTE).
   - Estado de cada prerrequisito del Paso 0: existe / parcial / no existe, con su impacto en el reporte.
   - Arquitectura: archivos a crear o modificar (migraciones, vista o función SQL, clase de servicio del reporte, FormRequest de parámetros, controlador, rutas, vista Blade/JS, clase de exportación, policy/permisos, jobs y scheduler si aplica).
   - SQL propuesto (borrador) de la consulta principal.
   - Preguntas abiertas para Contraloría/Contabilidad/Fiscal, cada una con el supuesto que aplicarás si no hay respuesta.
   - Espera mi aprobación antes de continuar.
2. **CÓDIGO.** Implementa exactamente lo aprobado: migraciones aditivas, consulta encapsulada, servicio, endpoint, vista, exportaciones, permisos, jobs. Commits pequeños y descriptivos.
3. **PRUEBAS Y VALIDACIÓN.**
   - Pruebas de feature (Pest o PHPUnit, lo que use el repo) con datos semilla que ejerzan cada regla de negocio y cada criterio de aceptación de la sección I.
   - Pruebas de permisos: sin permiso → 403; alcance por empresa/proveedor respetado.
   - Script SQL de verificación que demuestre las identidades de cuadre del reporte.
   - Plan de ejecución en SQL Server con volumen realista; reporta tiempos y los índices creados.
   - Lista explícita de lo que quedó pendiente o fuera de alcance.

------------------------------------------------

================================================
REPORTE #7: RC-04 · Modificaciones, adendas y cancelaciones de OC
================================================

# REPORTE RC-04: Modificaciones, adendas y cancelaciones de OC

## A) ROL Y OBJETIVO

Actúa como desarrollador senior full-stack en el Portal de Proveedores (Módulo de Compras) de TotalGas, con experiencia en reporting financiero para Contabilidad, Contraloría y Tesorería en México (CFDI 4.0, SAT, REPSE).

**Objetivo medible:** Mostrar cada versión modificada de una OC con el campo cambiado, valor anterior y nuevo, delta en importe y quién re-aprobó, garantizando que ningún cambio de precio, cantidad o proveedor ocurra sin generar versión.

### Contexto técnico del proyecto
- Aplicación: Portal de Proveedores / Módulo de Compras de TotalGas (grupo gasolinero con 36 estaciones, Cd. Juárez, Chih.). Ya cubre requisiciones, cotizaciones, órdenes de compra, recepciones, control presupuestal y validación CFDI/SAT (RFC, listado 69-B). Cada centro de costo ya tiene responsable asignado.
- Stack: Laravel 12, PHP 8.3, SQL Server 2019, Blade + Bootstrap 5 + jQuery. Si el repositorio ya usa Livewire, DataTables, Laravel Excel u OpenSpout, spatie/laravel-permission o dompdf/snappy, REUTILÍZALOS. No agregues dependencias nuevas sin justificarlas en el plan.
- Zona horaria de negocio: `America/Ciudad_Juarez`. Toda "fecha de corte" se interpreta a las 23:59:59 de esa zona.
- Moneda funcional: MXN. Los nombres de tablas y campos de este documento son SUPUESTOS: el esquema real del repositorio manda.

### Reglas de trabajo (obligatorias)
1. Antes de escribir código, inspecciona migraciones, modelos Eloquent, vistas/funciones SQL, servicios y jobs existentes. Construye una tabla de mapeo: `campo requerido → tabla.campo real | NO EXISTE`.
2. No crees tablas paralelas para datos que ya existen con otro nombre. Si falta un dato, propón en el plan la migración mínima y aditiva, y espera aprobación.
3. Si un prerrequisito no existe, NO simules el dato (ni ceros, ni NULL silenciosos, ni valores fijos). Repórtalo y separa en el plan "lo que se puede entregar hoy" de "lo que requiere construir el control primero". Un reporte que cuadra con datos falsos es peor que no tener reporte.
4. Montos en `DECIMAL(18,2)`; tipos de cambio en `DECIMAL(18,6)`; nunca `FLOAT`. Redondea a 2 decimales a nivel de renglón y después suma; usa el mismo criterio que los demás reportes con los que este debe cuadrar.
5. Consultas siempre parametrizadas (bindings). Prohibido concatenar filtros en SQL.
6. El reporte no consulta en línea los linked servers de las estaciones; usa las tablas ya consolidadas en el servidor del portal.
7. Una sola fuente de verdad por cálculo: si otro reporte o el motor de control usa el mismo cálculo, encapsúlalo (vista, función de tabla en línea o clase de servicio) y reutilízalo. No dupliques fórmulas.
8. No cambies reglas de negocio existentes salvo lo que este documento pida explícitamente. Si detectas un bug, repórtalo en el plan en lugar de corregirlo en silencio.
9. Reglas fiscales (tasas, claves SAT, plazos): van en catálogos parametrizables, nunca en código, y requieren confirmación de Contabilidad/Fiscal antes de liberarse.

### Paso 0: prerrequisitos que debes verificar antes de programar

- Versionado de OC (tabla de versiones o auditoría por campo). Es MUY probable que no exista: en ese caso el reporte depende de construir el versionado primero (observer en el modelo de OC que genere versión antes de guardar + regla de re-aprobación por tolerancia). Inclúyelo en el plan como sub-entregable y espera aprobación.
- Tolerancia de re-aprobación parametrizable (% y/o monto).
- Flujo de aprobación reutilizable para re-aprobar.

Si alguno no existe, repórtalo en el plan con su impacto; no lo sustituyas con datos simulados.

## B) NOMBRE Y PROPÓSITO DEL REPORTE

- ID: `RC-04` · Dominio: B. Requisiciones, autorizaciones y órdenes de compra · Fase: F1
- Nombre técnico: `rpt_po_amendments`
- Nombre de negocio: Modificaciones, adendas y cancelaciones de OC
- Frecuencia: Bajo demanda
- Consumidor principal: Contraloría, auditoría interna
- Granularidad (una fila =): Una fila por versión modificada de una orden de compra.

**Pregunta de negocio que responde / decisión que habilita:** Detección de la práctica clásica de aprobar barato y subir después. Todo aumento posterior a la autorización queda con nombre, monto y fecha.

**Requerimiento original de Contabilidad y Finanzas (campos mínimos):**

  • Folio · Versión · Campo modificado · Valor anterior · Valor nuevo
  • Delta en importe y en % · Motivo · Usuario que modificó
  • ¿Requirió re-aprobación? · Quién re-aprobó · Fecha

## C) COLUMNAS / CAMPOS

Origen = SUPUESTO de nombre; mapea al esquema real en el Paso 0. O = obligatoria, Op = opcional.

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| po_folio | NVARCHAR | purchase_orders |  | liga | O |
| version_no | INT | purchase_order_versions.version |  | número | O |
| changed_at | DATETIME2 | purchase_order_versions.created_at |  | fecha-hora | O |
| changed_by | NVARCHAR | users |  | texto | O |
| change_type | VARCHAR(15) | calculada | PRECIO / CANTIDAD / PROVEEDOR / FECHA / MONEDA / CANCELACION / OTRO | badge | O |
| field_changed | NVARCHAR(100) | purchase_order_version_changes.field |  | texto | O |
| old_value / new_value | NVARCHAR(500) | purchase_order_version_changes |  | texto | O |
| amount_before / amount_after | DECIMAL(18,2) | versiones | total MXN de la OC antes y después | moneda | O |
| delta_amount | DECIMAL(18,2) | calculada | amount_after − amount_before | moneda (+ rojo) | O |
| delta_pct | DECIMAL(9,4) | calculada | delta_amount / NULLIF(amount_before,0) | % | O |
| reason | NVARCHAR(500) | purchase_order_versions.reason | obligatorio | texto | O |
| required_reapproval | BIT | calculada | delta_pct > tolerancia o cambio de proveedor | sí/no | O |
| reapproved_by / reapproved_at | NVARCHAR / DATETIME2 | approvals |  | texto / fecha-hora | O si required |
| is_effective | BIT | purchase_order_versions.effective | 0 mientras espera re-aprobación | badge | O |
| supplier_name | NVARCHAR | suppliers |  | texto | O |

## D) FILTROS Y PARÁMETROS

**Filtros fijos (siempre aplicados):**

- Solo OC con al menos una versión posterior a la autorización original.

**Parámetros de entrada (según el Excel: Delta mayor a X %, usuario, proveedor, periodo, tipo de cambio.):**

| Parámetro | Tipo | Valor por defecto | O/Op |
|---|---|---|---|
| delta_pct_greater_than | decimal | 0 | Op |
| changed_by | int | todos | Op |
| supplier_id | int | todos | Op |
| date_from / date_to | date | mes en curso | O |
| change_type | multi-enum | todos | Op |
| company_ids | array<int> | empresas del usuario | O |

## E) AGRUPACIONES, ORDEN Y GRANULARIDAD

- Una fila por versión (con sub-filas por campo modificado).
- ORDER BY delta_amount DESC.
- Totales: Σ delta positivo, # versiones que requirieron re-aprobación, # pendientes.

## F) REGLAS DE NEGOCIO Y CÁLCULOS

- Cualquier cambio de precio, cantidad, proveedor, moneda o fecha de entrega posterior a la autorización genera versión ANTES de persistir (observer `updating`).
- Si delta_pct > tolerancia (default 5 %) o cambia el proveedor, la versión queda con is_effective = 0 y la OC sigue operando con la versión anterior hasta la re-aprobación.
- Cambios acumulados: la tolerancia se evalúa contra el importe de la última versión APROBADA, no contra la inmediata anterior (evita subir de 4 % en 4 %).
- Cancelación = versión con change_type CANCELACION y motivo obligatorio.

## G) FORMATO DE SALIDA

- Tabla con diff visual (anterior tachado → nuevo).
- Tarjetas: Σ incrementos post-autorización, top 5 usuarios que más modifican.
- Excel con hoja de versiones y hoja de campos.

Convenciones comunes de salida:
- Pantalla: tabla con DataTables en modo server-side (paginación en servidor de 25/50/100 filas), encabezado fijo y primera columna congelada, panel de filtros arriba con los parámetros de la sección D y botón "Limpiar filtros".
- Excel (.xlsx) con celdas tipadas de verdad (moneda `$#,##0.00`, fechas `dd/mm/yyyy`, porcentaje `0.0%`), nunca números como texto; encabezado con nombre del reporte, empresa(s), filtros aplicados, fecha/hora de generación y usuario. Escritura en streaming (chunks), sin cargar todo en memoria.
- CSV UTF-8 con BOM y separador coma.
- Exportaciones de más de 50 000 filas se encolan como job y se notifica al usuario cuando el archivo está listo.
- La exportación respeta exactamente los filtros activos en pantalla.

## H) CONSIDERACIONES TÉCNICAS

**Tablas/vistas probables (SUPUESTO):** purchase_orders, purchase_order_versions (nueva si no existe), purchase_order_version_changes (nueva), approvals, users, suppliers.

**Índices recomendados (valida contra los existentes antes de crear):**

- purchase_order_versions (purchase_order_id, version).
- purchase_order_versions (created_at) INCLUDE (delta_amount, required_reapproval).

**Seguridad y permisos:**

- Permisos granulares: `reportes.po_amendments.ver` y `reportes.po_amendments.exportar` (crea los permisos con el mecanismo que ya use el repo).
- Alcance por empresa: el usuario solo ve las empresas que tiene asignadas; el filtro se aplica en el servidor (scope/policy), nunca solo en la UI.
- Cada exportación se registra en bitácora: usuario, fecha/hora, filtros, número de filas.

- Solo Contraloría y auditoría interna. Nadie puede editar ni borrar versiones.

**Endpoints y componentes:**

- GET /reportes/compras/modificaciones-oc (+ /data, /export).

## I) CRITERIOS DE ACEPTACIÓN (Definition of Done)

Criterio de negocio original: _Ningún cambio de precio, cantidad o proveedor se guarda sin generar versión. Los cambios que superan la tolerancia no surten efecto hasta la re-aprobación._

- [ ] Prueba: editar precio, cantidad o proveedor de una OC autorizada genera versión; no hay ruta que lo evite (incluye actualizaciones masivas/queries directas del código revisadas).
- [ ] Un cambio sobre tolerancia no surte efecto (la OC sigue con la versión anterior) hasta la re-aprobación.
- [ ] El delta acumulado se evalúa contra la última versión aprobada.
- [ ] Todos los filtros de la sección D funcionan combinados y la exportación reproduce exactamente la vista filtrada.
- [ ] Usuarios sin permiso reciben 403 y ningún usuario ve empresas fuera de su alcance.
- [ ] La consulta principal responde en menos de 3 s con el volumen de un año de operación (o se justifica el snapshot/job).

**Preguntas abiertas (aplica el SUPUESTO indicado si no hay respuesta):**

- El Excel pide filtrar por 'tipo de cambio': ¿se refiere al tipo de modificación o al tipo de cambio de moneda? SUPUESTO: tipo de modificación (change_type); además se incluye MONEDA como un tipo.

## J) ENTREGABLE ESPERADO

Trabaja en tres entregas y DETENTE al terminar la primera:

1. **PLAN (sin código).**
   - Tabla de mapeo de esquema (campo requerido → tabla.campo real | NO EXISTE).
   - Estado de cada prerrequisito del Paso 0: existe / parcial / no existe, con su impacto en el reporte.
   - Arquitectura: archivos a crear o modificar (migraciones, vista o función SQL, clase de servicio del reporte, FormRequest de parámetros, controlador, rutas, vista Blade/JS, clase de exportación, policy/permisos, jobs y scheduler si aplica).
   - SQL propuesto (borrador) de la consulta principal.
   - Preguntas abiertas para Contraloría/Contabilidad/Fiscal, cada una con el supuesto que aplicarás si no hay respuesta.
   - Espera mi aprobación antes de continuar.
2. **CÓDIGO.** Implementa exactamente lo aprobado: migraciones aditivas, consulta encapsulada, servicio, endpoint, vista, exportaciones, permisos, jobs. Commits pequeños y descriptivos.
3. **PRUEBAS Y VALIDACIÓN.**
   - Pruebas de feature (Pest o PHPUnit, lo que use el repo) con datos semilla que ejerzan cada regla de negocio y cada criterio de aceptación de la sección I.
   - Pruebas de permisos: sin permiso → 403; alcance por empresa/proveedor respetado.
   - Script SQL de verificación que demuestre las identidades de cuadre del reporte.
   - Plan de ejecución en SQL Server con volumen realista; reporta tiempos y los índices creados.
   - Lista explícita de lo que quedó pendiente o fuera de alcance.

------------------------------------------------

================================================
REPORTE #8: RC-05 · Concentración de gasto y detección de fraccionamiento
================================================

# REPORTE RC-05: Concentración de gasto y detección de fraccionamiento

## A) ROL Y OBJETIVO

Actúa como desarrollador senior full-stack en el Portal de Proveedores (Módulo de Compras) de TotalGas, con experiencia en reporting financiero para Contabilidad, Contraloría y Tesorería en México (CFDI 4.0, SAT, REPSE).

**Objetivo medible:** Cuantificar la dependencia por proveedor y detectar compras fraccionadas para evadir niveles de autorización, con una regla parametrizable y trazable hasta las OC que la generaron.

### Contexto técnico del proyecto
- Aplicación: Portal de Proveedores / Módulo de Compras de TotalGas (grupo gasolinero con 36 estaciones, Cd. Juárez, Chih.). Ya cubre requisiciones, cotizaciones, órdenes de compra, recepciones, control presupuestal y validación CFDI/SAT (RFC, listado 69-B). Cada centro de costo ya tiene responsable asignado.
- Stack: Laravel 12, PHP 8.3, SQL Server 2019, Blade + Bootstrap 5 + jQuery. Si el repositorio ya usa Livewire, DataTables, Laravel Excel u OpenSpout, spatie/laravel-permission o dompdf/snappy, REUTILÍZALOS. No agregues dependencias nuevas sin justificarlas en el plan.
- Zona horaria de negocio: `America/Ciudad_Juarez`. Toda "fecha de corte" se interpreta a las 23:59:59 de esa zona.
- Moneda funcional: MXN. Los nombres de tablas y campos de este documento son SUPUESTOS: el esquema real del repositorio manda.

### Reglas de trabajo (obligatorias)
1. Antes de escribir código, inspecciona migraciones, modelos Eloquent, vistas/funciones SQL, servicios y jobs existentes. Construye una tabla de mapeo: `campo requerido → tabla.campo real | NO EXISTE`.
2. No crees tablas paralelas para datos que ya existen con otro nombre. Si falta un dato, propón en el plan la migración mínima y aditiva, y espera aprobación.
3. Si un prerrequisito no existe, NO simules el dato (ni ceros, ni NULL silenciosos, ni valores fijos). Repórtalo y separa en el plan "lo que se puede entregar hoy" de "lo que requiere construir el control primero". Un reporte que cuadra con datos falsos es peor que no tener reporte.
4. Montos en `DECIMAL(18,2)`; tipos de cambio en `DECIMAL(18,6)`; nunca `FLOAT`. Redondea a 2 decimales a nivel de renglón y después suma; usa el mismo criterio que los demás reportes con los que este debe cuadrar.
5. Consultas siempre parametrizadas (bindings). Prohibido concatenar filtros en SQL.
6. El reporte no consulta en línea los linked servers de las estaciones; usa las tablas ya consolidadas en el servidor del portal.
7. Una sola fuente de verdad por cálculo: si otro reporte o el motor de control usa el mismo cálculo, encapsúlalo (vista, función de tabla en línea o clase de servicio) y reutilízalo. No dupliques fórmulas.
8. No cambies reglas de negocio existentes salvo lo que este documento pida explícitamente. Si detectas un bug, repórtalo en el plan en lugar de corregirlo en silencio.
9. Reglas fiscales (tasas, claves SAT, plazos): van en catálogos parametrizables, nunca en código, y requieren confirmación de Contabilidad/Fiscal antes de liberarse.

### Paso 0: prerrequisitos que debes verificar antes de programar

- Familia de producto/servicio por renglón de OC (o por artículo).
- Matriz de umbrales de autorización por monto, parametrizada (no en código).
- Fecha de alta del proveedor o de su primera OC.

Si alguno no existe, repórtalo en el plan con su impacto; no lo sustituyas con datos simulados.

## B) NOMBRE Y PROPÓSITO DEL REPORTE

- ID: `RC-05` · Dominio: B. Requisiciones, autorizaciones y órdenes de compra · Fase: F2
- Nombre técnico: `rpt_spend_concentration_splitting`
- Nombre de negocio: Concentración de gasto y detección de fraccionamiento
- Frecuencia: Mensual
- Consumidor principal: Contraloría, Dirección
- Granularidad (una fila =): Una fila por proveedor, más vistas por comprador y por familia de producto o servicio.

**Pregunta de negocio que responde / decisión que habilita:** Dos riesgos en un mismo reporte: dependencia excesiva de un proveedor y compras partidas deliberadamente para no subir de nivel de autorización.

**Requerimiento original de Contabilidad y Finanzas (campos mínimos):**

  • Proveedor · RFC · Monto del periodo · % del gasto de la empresa y del centro de costo
  • Número de OC · Ticket promedio · Antigüedad de la relación · Comprador que más le asigna
  • Operaciones que quedaron justo por debajo de un umbral de autorización
  • Compras al mismo proveedor y familia dentro de una ventana de N días

## C) COLUMNAS / CAMPOS

Origen = SUPUESTO de nombre; mapea al esquema real en el Paso 0. O = obligatoria, Op = opcional.

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| supplier_name / supplier_rfc | NVARCHAR | suppliers |  | texto | O |
| period_amount_mxn | DECIMAL(18,2) | Σ OC no canceladas del periodo | subtotal MXN | moneda | O |
| pct_of_company_spend | DECIMAL(9,4) | calculada | period_amount / Σ gasto de la empresa | % | O |
| top_cost_center / pct_of_cost_center | NVARCHAR / DECIMAL | calculada | centro de costo con mayor gasto y su % | texto / % | O |
| po_count | INT | calculada |  | número | O |
| avg_ticket | DECIMAL(18,2) | calculada | period_amount / po_count | moneda | O |
| relationship_age_months | INT | calculada | meses desde la primera OC | número | O |
| top_buyer / top_buyer_pct | NVARCHAR / DECIMAL | calculada | comprador que más le asigna y % de las OC del proveedor | texto / % | O |
| near_threshold_count / near_threshold_amount | INT / DECIMAL | calculada | OC con importe en [umbral × (1 − margen), umbral) | número / moneda | O |
| split_clusters_count / split_amount | INT / DECIMAL | calculada | grupos detectados como posible fraccionamiento | número / moneda | O |
| hhi_company | DECIMAL(9,4) | calculada (a nivel empresa) | Σ (participación de cada proveedor)² | índice | Op |

**Detalle expandible (segundo nivel):**

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| cluster_id | INT | calculada |  | número | O |
| supplier / family / buyer | NVARCHAR |  | criterio de agrupación usado | texto | O |
| window_start / window_end | DATE | calculada |  | fecha | O |
| po_folios | NVARCHAR(MAX) | calculada | lista de OC del grupo (ligas) | texto | O |
| cluster_amount / threshold_exceeded | DECIMAL | calculada | Σ del grupo y umbral que habría requerido | moneda | O |

## D) FILTROS Y PARÁMETROS

**Filtros fijos (siempre aplicados):**

- OC no canceladas; importes sin IVA en MXN.

**Parámetros de entrada (según el Excel: Periodo, empresa, familia de producto o servicio, umbral, comprador.):**

| Parámetro | Tipo | Valor por defecto | O/Op |
|---|---|---|---|
| date_from / date_to | date | mes anterior cerrado | O |
| company_ids | array<int> | empresas del usuario | O |
| family_ids | array<int> | todas | Op |
| buyer_id | int | todos | Op |
| near_threshold_margin_pct | decimal | 10 % | O |
| split_window_days | int | 30 | O |
| split_group_by | enum PROVEEDOR+FAMILIA / +CENTRO_COSTO / +SOLICITANTE | PROVEEDOR+FAMILIA | O |

## E) AGRUPACIONES, ORDEN Y GRANULARIDAD

- Vista 1: una fila por proveedor, ORDER BY period_amount DESC.
- Vista 2: por comprador (mismas métricas).
- Vista 3: por familia.
- Vista 4: grupos de fraccionamiento con detalle de OC.

## F) REGLAS DE NEGOCIO Y CÁLCULOS

- Fraccionamiento: grupo de ≥ 2 OC del mismo criterio de agrupación dentro de una ventana móvil de N días, cada una por debajo del umbral de autorización y cuya suma alcanza o supera ese umbral.
- Implementación: funciones de ventana en SQL Server (SUM() OVER (PARTITION BY … ORDER BY issued_at RANGE…) no admite rango de fechas: usa auto-join por ventana o CROSS APPLY con índice adecuado). Documenta la elección en el plan.
- Cada grupo detectado se guarda en `splitting_detections` con las OC que lo componen para que sea trazable y no cambie al re-ejecutar (ejecución mensual con id de corrida).
- Los umbrales se leen de la matriz de autorización vigente a la fecha de cada OC.

## G) FORMATO DE SALIDA

- Pareto de proveedores (barras + % acumulado).
- Tarjetas: HHI, # grupos de fraccionamiento, importe involucrado.
- Tabla de grupos con ligas a cada OC.

Convenciones comunes de salida:
- Pantalla: tabla con DataTables en modo server-side (paginación en servidor de 25/50/100 filas), encabezado fijo y primera columna congelada, panel de filtros arriba con los parámetros de la sección D y botón "Limpiar filtros".
- Excel (.xlsx) con celdas tipadas de verdad (moneda `$#,##0.00`, fechas `dd/mm/yyyy`, porcentaje `0.0%`), nunca números como texto; encabezado con nombre del reporte, empresa(s), filtros aplicados, fecha/hora de generación y usuario. Escritura en streaming (chunks), sin cargar todo en memoria.
- CSV UTF-8 con BOM y separador coma.
- Exportaciones de más de 50 000 filas se encolan como job y se notifica al usuario cuando el archivo está listo.
- La exportación respeta exactamente los filtros activos en pantalla.

## H) CONSIDERACIONES TÉCNICAS

**Tablas/vistas probables (SUPUESTO):** purchase_orders, purchase_order_items, suppliers, product_families, users, approval_thresholds, splitting_detections (nueva).

**Índices recomendados (valida contra los existentes antes de crear):**

- purchase_orders (supplier_id, issued_at) INCLUDE (subtotal_mxn, buyer_id, cost_center_id).
- purchase_order_items (family_id).

**Seguridad y permisos:**

- Permisos granulares: `reportes.spend_concentration_splitting.ver` y `reportes.spend_concentration_splitting.exportar` (crea los permisos con el mecanismo que ya use el repo).
- Alcance por empresa: el usuario solo ve las empresas que tiene asignadas; el filtro se aplica en el servidor (scope/policy), nunca solo en la UI.
- Cada exportación se registra en bitácora: usuario, fecha/hora, filtros, número de filas.

- Solo Contraloría y Dirección.

**Endpoints y componentes:**

- GET /reportes/compras/concentracion (+ /data, /export).
- Job mensual `SplittingDetectionJob` con parámetros guardados por corrida.

## I) CRITERIOS DE ACEPTACIÓN (Definition of Done)

Criterio de negocio original: _La regla de fraccionamiento es parametrizable (ventana en días, umbral, criterio de agrupación) y cada coincidencia es trazable hasta las OC que la generaron._

- [ ] Los parámetros de la regla (ventana, umbral, agrupación) se cambian desde la UI sin tocar código.
- [ ] Cada coincidencia lleva a las OC exactas que la generaron.
- [ ] Prueba con datos semilla: 3 OC de 40 000 al mismo proveedor y familia en 10 días con umbral de 100 000 generan un grupo; las mismas OC separadas 40 días no.
- [ ] Todos los filtros de la sección D funcionan combinados y la exportación reproduce exactamente la vista filtrada.
- [ ] Usuarios sin permiso reciben 403 y ningún usuario ve empresas fuera de su alcance.
- [ ] La consulta principal responde en menos de 3 s con el volumen de un año de operación (o se justifica el snapshot/job).

**Preguntas abiertas (aplica el SUPUESTO indicado si no hay respuesta):**

- ¿Umbral de concentración 'excesiva'? SUPUESTO: resaltar proveedores con > 20 % del gasto de la empresa.

## J) ENTREGABLE ESPERADO

Trabaja en tres entregas y DETENTE al terminar la primera:

1. **PLAN (sin código).**
   - Tabla de mapeo de esquema (campo requerido → tabla.campo real | NO EXISTE).
   - Estado de cada prerrequisito del Paso 0: existe / parcial / no existe, con su impacto en el reporte.
   - Arquitectura: archivos a crear o modificar (migraciones, vista o función SQL, clase de servicio del reporte, FormRequest de parámetros, controlador, rutas, vista Blade/JS, clase de exportación, policy/permisos, jobs y scheduler si aplica).
   - SQL propuesto (borrador) de la consulta principal.
   - Preguntas abiertas para Contraloría/Contabilidad/Fiscal, cada una con el supuesto que aplicarás si no hay respuesta.
   - Espera mi aprobación antes de continuar.
2. **CÓDIGO.** Implementa exactamente lo aprobado: migraciones aditivas, consulta encapsulada, servicio, endpoint, vista, exportaciones, permisos, jobs. Commits pequeños y descriptivos.
3. **PRUEBAS Y VALIDACIÓN.**
   - Pruebas de feature (Pest o PHPUnit, lo que use el repo) con datos semilla que ejerzan cada regla de negocio y cada criterio de aceptación de la sección I.
   - Pruebas de permisos: sin permiso → 403; alcance por empresa/proveedor respetado.
   - Script SQL de verificación que demuestre las identidades de cuadre del reporte.
   - Plan de ejecución en SQL Server con volumen realista; reporta tiempos y los índices creados.
   - Lista explícita de lo que quedó pendiente o fuera de alcance.

------------------------------------------------

================================================
REPORTE #9: RR-01 · Recepciones validadas y su evidencia
================================================

# REPORTE RR-01: Recepciones validadas y su evidencia

## A) ROL Y OBJETIVO

Actúa como desarrollador senior full-stack en el Portal de Proveedores (Módulo de Compras) de TotalGas, con experiencia en reporting financiero para Contabilidad, Contraloría y Tesorería en México (CFDI 4.0, SAT, REPSE).

**Objetivo medible:** Listar cada recepción validada con su evidencia (archivos con hash verificable), quién recibió y quién validó, garantizando que no exista recepción validada sin la evidencia mínima de su categoría.

### Contexto técnico del proyecto
- Aplicación: Portal de Proveedores / Módulo de Compras de TotalGas (grupo gasolinero con 36 estaciones, Cd. Juárez, Chih.). Ya cubre requisiciones, cotizaciones, órdenes de compra, recepciones, control presupuestal y validación CFDI/SAT (RFC, listado 69-B). Cada centro de costo ya tiene responsable asignado.
- Stack: Laravel 12, PHP 8.3, SQL Server 2019, Blade + Bootstrap 5 + jQuery. Si el repositorio ya usa Livewire, DataTables, Laravel Excel u OpenSpout, spatie/laravel-permission o dompdf/snappy, REUTILÍZALOS. No agregues dependencias nuevas sin justificarlas en el plan.
- Zona horaria de negocio: `America/Ciudad_Juarez`. Toda "fecha de corte" se interpreta a las 23:59:59 de esa zona.
- Moneda funcional: MXN. Los nombres de tablas y campos de este documento son SUPUESTOS: el esquema real del repositorio manda.

### Reglas de trabajo (obligatorias)
1. Antes de escribir código, inspecciona migraciones, modelos Eloquent, vistas/funciones SQL, servicios y jobs existentes. Construye una tabla de mapeo: `campo requerido → tabla.campo real | NO EXISTE`.
2. No crees tablas paralelas para datos que ya existen con otro nombre. Si falta un dato, propón en el plan la migración mínima y aditiva, y espera aprobación.
3. Si un prerrequisito no existe, NO simules el dato (ni ceros, ni NULL silenciosos, ni valores fijos). Repórtalo y separa en el plan "lo que se puede entregar hoy" de "lo que requiere construir el control primero". Un reporte que cuadra con datos falsos es peor que no tener reporte.
4. Montos en `DECIMAL(18,2)`; tipos de cambio en `DECIMAL(18,6)`; nunca `FLOAT`. Redondea a 2 decimales a nivel de renglón y después suma; usa el mismo criterio que los demás reportes con los que este debe cuadrar.
5. Consultas siempre parametrizadas (bindings). Prohibido concatenar filtros en SQL.
6. El reporte no consulta en línea los linked servers de las estaciones; usa las tablas ya consolidadas en el servidor del portal.
7. Una sola fuente de verdad por cálculo: si otro reporte o el motor de control usa el mismo cálculo, encapsúlalo (vista, función de tabla en línea o clase de servicio) y reutilízalo. No dupliques fórmulas.
8. No cambies reglas de negocio existentes salvo lo que este documento pida explícitamente. Si detectas un bug, repórtalo en el plan en lugar de corregirlo en silencio.
9. Reglas fiscales (tasas, claves SAT, plazos): van en catálogos parametrizables, nunca en código, y requieren confirmación de Contabilidad/Fiscal antes de liberarse.

### Paso 0: prerrequisitos que debes verificar antes de programar

- Recepciones con receptor, validador, ubicación/estación y tipo (bien / servicio / hito).
- Adjuntos de recepción con usuario y fecha de carga. Si no hay hash, el plan debe proponer calcular SHA-256 al cargar y un backfill que marque los históricos como 'hash calculado a posteriori' (no prueba integridad previa).
- Catálogo de evidencia exigida por categoría (p. ej. servicio → acta firmada; bien → foto + remisión).

Si alguno no existe, repórtalo en el plan con su impacto; no lo sustituyas con datos simulados.

## B) NOMBRE Y PROPÓSITO DEL REPORTE

- ID: `RR-01` · Dominio: C. Recepción y devengo · Fase: F1
- Nombre técnico: `rpt_receptions_evidence`
- Nombre de negocio: Recepciones validadas y su evidencia
- Frecuencia: Diaria
- Consumidor principal: Contraloría, auditoría, Cuentas por pagar
- Granularidad (una fila =): Una fila por evento de recepción, con sus archivos de evidencia.

**Pregunta de negocio que responde / decisión que habilita:** La prueba material del gasto, que es lo primero que pide una revisión y lo que hoy vive disperso en correos y mensajes. También separa al que recibe del que solicita.

**Requerimiento original de Contabilidad y Finanzas (campos mínimos):**

  • Folio de recepción · OC · Proveedor · Tipo (bien / servicio / hito) · Fecha
  • Cantidad recibida por renglón · Quién recibió · Quién validó · Estación o ubicación
  • Archivos adjuntos: nombre, tipo, hash, fecha y hora de carga, usuario

## C) COLUMNAS / CAMPOS

Origen = SUPUESTO de nombre; mapea al esquema real en el Paso 0. O = obligatoria, Op = opcional.

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| reception_folio | NVARCHAR | receptions.folio |  | liga | O |
| po_folio | NVARCHAR | purchase_orders |  | liga | O |
| supplier_name | NVARCHAR | suppliers |  | texto | O |
| reception_type | VARCHAR(10) | receptions.type | BIEN / SERVICIO / HITO | badge | O |
| reception_date | DATETIME2 | receptions.received_at |  | fecha-hora | O |
| location | NVARCHAR | stations / locations | estación o ubicación | texto | O |
| received_by / validated_by | NVARCHAR | users |  | texto | O |
| validated_at | DATETIME2 | receptions.validated_at |  | fecha-hora | O |
| sod_flag | BIT | calculada | 1 si received_by = validated_by o received_by = solicitante de la requisición | ícono rojo | O |
| evidence_required | NVARCHAR | reception_evidence_rules | tipos exigidos por categoría | texto | O |
| evidence_count | INT | Σ reception_attachments |  | número | O |
| evidence_complete | BIT | calculada | todos los tipos exigidos presentes | badge | O |

**Detalle expandible (segundo nivel):**

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| po_line / quantity_received | INT / DECIMAL(18,4) | reception_items |  | número | O |
| file_name / mime_type / file_size | NVARCHAR / INT | reception_attachments |  | texto | O |
| sha256 | CHAR(64) | reception_attachments.sha256 | calculado al cargar | texto monoespaciado | O |
| hash_origin | VARCHAR(12) | reception_attachments | EN_CARGA / BACKFILL | texto | O |
| uploaded_at / uploaded_by | DATETIME2 / NVARCHAR | reception_attachments |  | fecha-hora | O |
| hash_verified | BIT | calculada bajo demanda | recalcula SHA-256 del archivo y compara | ícono | Op |

## D) FILTROS Y PARÁMETROS

**Filtros fijos (siempre aplicados):**

- Solo recepciones VALIDADAS (con el filtro 'con o sin evidencia completa').

**Parámetros de entrada (según el Excel: Empresa, ubicación, tipo, validador, periodo, con o sin evidencia completa.):**

| Parámetro | Tipo | Valor por defecto | O/Op |
|---|---|---|---|
| company_ids | array<int> | empresas del usuario | O |
| location_ids | array<int> | todas | Op |
| reception_type | multi-enum | todos | Op |
| validated_by | int | todos | Op |
| date_from / date_to | date | mes en curso | O |
| evidence_filter | enum COMPLETA/INCOMPLETA/TODAS | TODAS | Op |

## E) AGRUPACIONES, ORDEN Y GRANULARIDAD

- Una fila por evento de recepción, detalle por renglón y por archivo.
- ORDER BY reception_date DESC.
- Totales: # recepciones, % con evidencia completa, # con conflicto de segregación.

## F) REGLAS DE NEGOCIO Y CÁLCULOS

- Control (si no existe, parte del plan): el botón Validar se deshabilita y el backend rechaza la validación si falta algún tipo de evidencia exigido.
- Inmutabilidad: los adjuntos no tienen endpoint de edición ni borrado; el disco de almacenamiento se usa en modo append-only desde la app.
- Verificación de hash bajo demanda por archivo y en lote (job semanal que reporta discrepancias).

## G) FORMATO DE SALIDA

- Tarjetas: % con evidencia completa, # conflictos de segregación.
- Tabla con miniaturas/íconos de evidencia y botón 'Verificar hash'.
- Excel con hoja de recepciones y hoja de archivos con hash.

Convenciones comunes de salida:
- Pantalla: tabla con DataTables en modo server-side (paginación en servidor de 25/50/100 filas), encabezado fijo y primera columna congelada, panel de filtros arriba con los parámetros de la sección D y botón "Limpiar filtros".
- Excel (.xlsx) con celdas tipadas de verdad (moneda `$#,##0.00`, fechas `dd/mm/yyyy`, porcentaje `0.0%`), nunca números como texto; encabezado con nombre del reporte, empresa(s), filtros aplicados, fecha/hora de generación y usuario. Escritura en streaming (chunks), sin cargar todo en memoria.
- CSV UTF-8 con BOM y separador coma.
- Exportaciones de más de 50 000 filas se encolan como job y se notifica al usuario cuando el archivo está listo.
- La exportación respeta exactamente los filtros activos en pantalla.

## H) CONSIDERACIONES TÉCNICAS

**Tablas/vistas probables (SUPUESTO):** receptions, reception_items, reception_attachments, reception_evidence_rules (nueva si no existe), purchase_orders, suppliers, stations, users, requisitions.

**Índices recomendados (valida contra los existentes antes de crear):**

- receptions (company_id, validated_at) INCLUDE (type, location_id).
- reception_attachments (reception_id).

**Seguridad y permisos:**

- Permisos granulares: `reportes.receptions_evidence.ver` y `reportes.receptions_evidence.exportar` (crea los permisos con el mecanismo que ya use el repo).
- Alcance por empresa: el usuario solo ve las empresas que tiene asignadas; el filtro se aplica en el servidor (scope/policy), nunca solo en la UI.
- Cada exportación se registra en bitácora: usuario, fecha/hora, filtros, número de filas.

- Auditoría: puede descargar archivos; Cuentas por pagar: solo ver.

**Endpoints y componentes:**

- GET /reportes/recepciones/evidencia (+ /data, /export).
- POST /reportes/recepciones/evidencia/{attachment}/verificar-hash.

## I) CRITERIOS DE ACEPTACIÓN (Definition of Done)

Criterio de negocio original: _No se puede validar una recepción sin al menos un archivo de evidencia del tipo exigido por la categoría. Los archivos son inmutables y con hash verificable._

- [ ] El backend rechaza validar una recepción sin la evidencia exigida por su categoría (prueba automatizada).
- [ ] No existe ruta para modificar o borrar un adjunto; el hash almacenado coincide con el recalculado para todos los archivos de prueba.
- [ ] Los casos donde recibe el mismo que solicita o valida el mismo que recibe se marcan.
- [ ] Todos los filtros de la sección D funcionan combinados y la exportación reproduce exactamente la vista filtrada.
- [ ] Usuarios sin permiso reciben 403 y ningún usuario ve empresas fuera de su alcance.
- [ ] La consulta principal responde en menos de 3 s con el volumen de un año de operación (o se justifica el snapshot/job).

**Preguntas abiertas (aplica el SUPUESTO indicado si no hay respuesta):**

- ¿Evidencia exigida por categoría? SUPUESTO: BIEN = remisión o foto; SERVICIO = acta/entregable; HITO = acta firmada.

## J) ENTREGABLE ESPERADO

Trabaja en tres entregas y DETENTE al terminar la primera:

1. **PLAN (sin código).**
   - Tabla de mapeo de esquema (campo requerido → tabla.campo real | NO EXISTE).
   - Estado de cada prerrequisito del Paso 0: existe / parcial / no existe, con su impacto en el reporte.
   - Arquitectura: archivos a crear o modificar (migraciones, vista o función SQL, clase de servicio del reporte, FormRequest de parámetros, controlador, rutas, vista Blade/JS, clase de exportación, policy/permisos, jobs y scheduler si aplica).
   - SQL propuesto (borrador) de la consulta principal.
   - Preguntas abiertas para Contraloría/Contabilidad/Fiscal, cada una con el supuesto que aplicarás si no hay respuesta.
   - Espera mi aprobación antes de continuar.
2. **CÓDIGO.** Implementa exactamente lo aprobado: migraciones aditivas, consulta encapsulada, servicio, endpoint, vista, exportaciones, permisos, jobs. Commits pequeños y descriptivos.
3. **PRUEBAS Y VALIDACIÓN.**
   - Pruebas de feature (Pest o PHPUnit, lo que use el repo) con datos semilla que ejerzan cada regla de negocio y cada criterio de aceptación de la sección I.
   - Pruebas de permisos: sin permiso → 403; alcance por empresa/proveedor respetado.
   - Script SQL de verificación que demuestre las identidades de cuadre del reporte.
   - Plan de ejecución en SQL Server con volumen realista; reporta tiempos y los índices creados.
   - Lista explícita de lo que quedó pendiente o fuera de alcance.

------------------------------------------------

================================================
REPORTE #10: RR-02 · Recepciones pendientes de facturar (provisión de cierre)
================================================

# REPORTE RR-02: Recepciones pendientes de facturar (provisión de cierre)

## A) ROL Y OBJETIVO

Actúa como desarrollador senior full-stack en el Portal de Proveedores (Módulo de Compras) de TotalGas, con experiencia en reporting financiero para Contabilidad, Contraloría y Tesorería en México (CFDI 4.0, SAT, REPSE).

**Objetivo medible:** Calcular, renglón por renglón, la provisión de gasto devengado no facturado a una fecha de corte, con cuenta contable y centro de costo, congelada al cierre para que la reversa del mes siguiente sea exactamente por el mismo importe.

### Contexto técnico del proyecto
- Aplicación: Portal de Proveedores / Módulo de Compras de TotalGas (grupo gasolinero con 36 estaciones, Cd. Juárez, Chih.). Ya cubre requisiciones, cotizaciones, órdenes de compra, recepciones, control presupuestal y validación CFDI/SAT (RFC, listado 69-B). Cada centro de costo ya tiene responsable asignado.
- Stack: Laravel 12, PHP 8.3, SQL Server 2019, Blade + Bootstrap 5 + jQuery. Si el repositorio ya usa Livewire, DataTables, Laravel Excel u OpenSpout, spatie/laravel-permission o dompdf/snappy, REUTILÍZALOS. No agregues dependencias nuevas sin justificarlas en el plan.
- Zona horaria de negocio: `America/Ciudad_Juarez`. Toda "fecha de corte" se interpreta a las 23:59:59 de esa zona.
- Moneda funcional: MXN. Los nombres de tablas y campos de este documento son SUPUESTOS: el esquema real del repositorio manda.

### Reglas de trabajo (obligatorias)
1. Antes de escribir código, inspecciona migraciones, modelos Eloquent, vistas/funciones SQL, servicios y jobs existentes. Construye una tabla de mapeo: `campo requerido → tabla.campo real | NO EXISTE`.
2. No crees tablas paralelas para datos que ya existen con otro nombre. Si falta un dato, propón en el plan la migración mínima y aditiva, y espera aprobación.
3. Si un prerrequisito no existe, NO simules el dato (ni ceros, ni NULL silenciosos, ni valores fijos). Repórtalo y separa en el plan "lo que se puede entregar hoy" de "lo que requiere construir el control primero". Un reporte que cuadra con datos falsos es peor que no tener reporte.
4. Montos en `DECIMAL(18,2)`; tipos de cambio en `DECIMAL(18,6)`; nunca `FLOAT`. Redondea a 2 decimales a nivel de renglón y después suma; usa el mismo criterio que los demás reportes con los que este debe cuadrar.
5. Consultas siempre parametrizadas (bindings). Prohibido concatenar filtros en SQL.
6. El reporte no consulta en línea los linked servers de las estaciones; usa las tablas ya consolidadas en el servidor del portal.
7. Una sola fuente de verdad por cálculo: si otro reporte o el motor de control usa el mismo cálculo, encapsúlalo (vista, función de tabla en línea o clase de servicio) y reutilízalo. No dupliques fórmulas.
8. No cambies reglas de negocio existentes salvo lo que este documento pida explícitamente. Si detectas un bug, repórtalo en el plan en lugar de corregirlo en silencio.
9. Reglas fiscales (tasas, claves SAT, plazos): van en catálogos parametrizables, nunca en código, y requieren confirmación de Contabilidad/Fiscal antes de liberarse.

### Paso 0: prerrequisitos que debes verificar antes de programar

- Vínculo renglón de recepción ↔ CFDI (cantidad facturada por renglón).
- Cuenta contable de gasto por renglón presupuestal o por familia.
- Tipo de cambio de cierre (FIX publicado en el DOF) cargado en una tabla de tipos de cambio.
- Generación de pólizas (RK-01, fase F2). ATENCIÓN: el criterio pide que el total sea igual a la póliza; en F1 entrega el cálculo congelado y el layout de póliza exportable, y la póliza automática cuando exista RK-01.

Si alguno no existe, repórtalo en el plan con su impacto; no lo sustituyas con datos simulados.

## B) NOMBRE Y PROPÓSITO DEL REPORTE

- ID: `RR-02` · Dominio: C. Recepción y devengo · Fase: F1
- Nombre técnico: `rpt_accrual_provision`
- Nombre de negocio: Recepciones pendientes de facturar (provisión de cierre)
- Frecuencia: Cierre mensual y bajo demanda
- Consumidor principal: Contabilidad, Contraloría
- Granularidad (una fila =): Una fila por renglón recibido y validado sin CFDI vinculado a la fecha de corte.

**Pregunta de negocio que responde / decisión que habilita:** El cálculo automático de la provisión de gasto devengado no facturado. Es la diferencia entre un cierre por estimación y un cierre soportado renglón por renglón, con el detalle listo para defender ante auditoría.

**Requerimiento original de Contabilidad y Finanzas (campos mínimos):**

  • OC · Renglón · Proveedor · Fecha de recepción · Días transcurridos
  • Cantidad y precio de OC · Importe a provisionar sin IVA · Moneda y tipo de cambio del corte
  • Cuenta contable de gasto · Centro de costo · Renglón presupuestal

## C) COLUMNAS / CAMPOS

Origen = SUPUESTO de nombre; mapea al esquema real en el Paso 0. O = obligatoria, Op = opcional.

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| po_folio / po_line | NVARCHAR / INT | purchase_orders / items |  | liga | O |
| supplier_name / supplier_rfc | NVARCHAR | suppliers |  | texto | O |
| reception_folio / reception_date | NVARCHAR / DATE | receptions |  | fecha | O |
| days_elapsed | INT | calculada | DATEDIFF(day, reception_date, cut_date) | número | O |
| qty_received | DECIMAL(18,4) | reception_items | validadas ≤ corte | número | O |
| qty_invoiced | DECIMAL(18,4) | invoice_item_links | con CFDI registrado ≤ corte | número | O |
| qty_to_provision | DECIMAL(18,4) | calculada | qty_received − qty_invoiced | número | O |
| po_unit_price | DECIMAL(18,6) | purchase_order_items |  | moneda origen | O |
| currency | CHAR(3) | purchase_orders |  | texto | O |
| cut_exchange_rate | DECIMAL(18,6) | exchange_rates (FIX, fecha de corte) | 1 si MXN | 4 decimales | O |
| provision_amount_orig | DECIMAL(18,2) | calculada | ROUND(qty_to_provision × po_unit_price, 2), sin IVA | moneda origen | O |
| provision_amount_mxn | DECIMAL(18,2) | calculada | ROUND(provision_amount_orig × cut_exchange_rate, 2) | moneda MXN | O |
| expense_account | NVARCHAR | budget_lines.accounting_account_code |  | texto | O |
| cost_center / budget_line | NVARCHAR | catálogos |  | texto | O |

## D) FILTROS Y PARÁMETROS

**Filtros fijos (siempre aplicados):**

- Solo renglones con recepción VALIDADA y qty_to_provision > 0 a la fecha de corte.
- Importe sin IVA.

**Parámetros de entrada (según el Excel: Fecha de corte, empresa, cuenta contable, antigüedad, proveedor.):**

| Parámetro | Tipo | Valor por defecto | O/Op |
|---|---|---|---|
| cut_date | date | último día del mes anterior | O |
| company_ids | array<int> | empresas del usuario | O |
| expense_account | multi | todas | Op |
| min_days_elapsed | int | 0 | Op |
| supplier_id | int | todos | Op |

## E) AGRUPACIONES, ORDEN Y GRANULARIDAD

- Una fila por renglón recibido sin CFDI al corte.
- ORDER BY expense_account, cost_center, supplier.
- Subtotales por cuenta contable y centro de costo (así se arma la póliza); total general.

## F) REGLAS DE NEGOCIO Y CÁLCULOS

- El cálculo 'as of' considera solo eventos con fecha ≤ corte (recepción validada y CFDI registrado), sin importar lo que ocurra después.
- Cierre: acción 'Congelar provisión' que guarda la corrida en `accrual_runs` + `accrual_run_lines` (inmutable). Recalcular un mes ya congelado está prohibido; solo se consulta.
- Póliza del mes: cargo a cuentas de gasto por centro de costo / abono a 'Proveedores por facturar' (cuenta parametrizable). Póliza de reversa el día 1 del mes siguiente por el mismo importe, generada desde la misma corrida, no recalculada.
- Moneda extranjera al TC FIX del día de corte (SUPUESTO; Contabilidad confirma).

## G) FORMATO DE SALIDA

- Tarjetas: provisión total MXN, # renglones, antigüedad promedio.
- Tabla con subtotales por cuenta.
- Botón 'Congelar provisión' (solo Contabilidad) y 'Descargar layout de póliza'.
- Excel con hoja de detalle y hoja resumen por cuenta/centro de costo.

Convenciones comunes de salida:
- Pantalla: tabla con DataTables en modo server-side (paginación en servidor de 25/50/100 filas), encabezado fijo y primera columna congelada, panel de filtros arriba con los parámetros de la sección D y botón "Limpiar filtros".
- Excel (.xlsx) con celdas tipadas de verdad (moneda `$#,##0.00`, fechas `dd/mm/yyyy`, porcentaje `0.0%`), nunca números como texto; encabezado con nombre del reporte, empresa(s), filtros aplicados, fecha/hora de generación y usuario. Escritura en streaming (chunks), sin cargar todo en memoria.
- CSV UTF-8 con BOM y separador coma.
- Exportaciones de más de 50 000 filas se encolan como job y se notifica al usuario cuando el archivo está listo.
- La exportación respeta exactamente los filtros activos en pantalla.

## H) CONSIDERACIONES TÉCNICAS

**Tablas/vistas probables (SUPUESTO):** receptions, reception_items, invoice_item_links, invoices, purchase_orders, purchase_order_items, exchange_rates, budget_lines, accrual_runs (nueva), accrual_run_lines (nueva).

**Índices recomendados (valida contra los existentes antes de crear):**

- reception_items (validated_at) INCLUDE (purchase_order_item_id, quantity).
- invoice_item_links (reception_item_id) INCLUDE (quantity, registered_at).

**Seguridad y permisos:**

- Permisos granulares: `reportes.accrual_provision.ver` y `reportes.accrual_provision.exportar` (crea los permisos con el mecanismo que ya use el repo).
- Alcance por empresa: el usuario solo ve las empresas que tiene asignadas; el filtro se aplica en el servidor (scope/policy), nunca solo en la UI.
- Cada exportación se registra en bitácora: usuario, fecha/hora, filtros, número de filas.

- Congelar corrida: solo rol Contabilidad con permiso `reportes.provision.congelar`.

**Endpoints y componentes:**

- GET /reportes/cierre/provision (+ /data, /export).
- POST /reportes/cierre/provision/congelar.
- GET /reportes/cierre/provision/{run}/layout-poliza.

## I) CRITERIOS DE ACEPTACIÓN (Definition of Done)

Criterio de negocio original: _El total al corte es exactamente el importe de la póliza de provisión generada, y la póliza del mes siguiente la reversa por el mismo importe._

- [ ] El total de la corrida congelada es exactamente el total del layout/póliza de provisión y de la póliza de reversa.
- [ ] Re-ejecutar el reporte para un corte pasado ya congelado devuelve la corrida guardada, idéntica.
- [ ] Un renglón recibido y facturado parcialmente provisiona solo la diferencia.
- [ ] Todos los filtros de la sección D funcionan combinados y la exportación reproduce exactamente la vista filtrada.
- [ ] Usuarios sin permiso reciben 403 y ningún usuario ve empresas fuera de su alcance.
- [ ] La consulta principal responde en menos de 3 s con el volumen de un año de operación (o se justifica el snapshot/job).

**Preguntas abiertas (aplica el SUPUESTO indicado si no hay respuesta):**

- ¿Cuenta de abono de la provisión? SUPUESTO: 'Proveedores por facturar / acreedores diversos', parametrizable por empresa.

## J) ENTREGABLE ESPERADO

Trabaja en tres entregas y DETENTE al terminar la primera:

1. **PLAN (sin código).**
   - Tabla de mapeo de esquema (campo requerido → tabla.campo real | NO EXISTE).
   - Estado de cada prerrequisito del Paso 0: existe / parcial / no existe, con su impacto en el reporte.
   - Arquitectura: archivos a crear o modificar (migraciones, vista o función SQL, clase de servicio del reporte, FormRequest de parámetros, controlador, rutas, vista Blade/JS, clase de exportación, policy/permisos, jobs y scheduler si aplica).
   - SQL propuesto (borrador) de la consulta principal.
   - Preguntas abiertas para Contraloría/Contabilidad/Fiscal, cada una con el supuesto que aplicarás si no hay respuesta.
   - Espera mi aprobación antes de continuar.
2. **CÓDIGO.** Implementa exactamente lo aprobado: migraciones aditivas, consulta encapsulada, servicio, endpoint, vista, exportaciones, permisos, jobs. Commits pequeños y descriptivos.
3. **PRUEBAS Y VALIDACIÓN.**
   - Pruebas de feature (Pest o PHPUnit, lo que use el repo) con datos semilla que ejerzan cada regla de negocio y cada criterio de aceptación de la sección I.
   - Pruebas de permisos: sin permiso → 403; alcance por empresa/proveedor respetado.
   - Script SQL de verificación que demuestre las identidades de cuadre del reporte.
   - Plan de ejecución en SQL Server con volumen realista; reporta tiempos y los índices creados.
   - Lista explícita de lo que quedó pendiente o fuera de alcance.

------------------------------------------------

================================================
REPORTE #11: RR-03 · Excepciones de flujo
================================================

# REPORTE RR-03: Excepciones de flujo

## A) ROL Y OBJETIVO

Actúa como desarrollador senior full-stack en el Portal de Proveedores (Módulo de Compras) de TotalGas, con experiencia en reporting financiero para Contabilidad, Contraloría y Tesorería en México (CFDI 4.0, SAT, REPSE).

**Objetivo medible:** Detectar y clasificar automáticamente, con un código estable, cada documento que rompió la secuencia requisición → OC → recepción → factura, y dar seguimiento hasta su regularización con evidencia y responsable.

### Contexto técnico del proyecto
- Aplicación: Portal de Proveedores / Módulo de Compras de TotalGas (grupo gasolinero con 36 estaciones, Cd. Juárez, Chih.). Ya cubre requisiciones, cotizaciones, órdenes de compra, recepciones, control presupuestal y validación CFDI/SAT (RFC, listado 69-B). Cada centro de costo ya tiene responsable asignado.
- Stack: Laravel 12, PHP 8.3, SQL Server 2019, Blade + Bootstrap 5 + jQuery. Si el repositorio ya usa Livewire, DataTables, Laravel Excel u OpenSpout, spatie/laravel-permission o dompdf/snappy, REUTILÍZALOS. No agregues dependencias nuevas sin justificarlas en el plan.
- Zona horaria de negocio: `America/Ciudad_Juarez`. Toda "fecha de corte" se interpreta a las 23:59:59 de esa zona.
- Moneda funcional: MXN. Los nombres de tablas y campos de este documento son SUPUESTOS: el esquema real del repositorio manda.

### Reglas de trabajo (obligatorias)
1. Antes de escribir código, inspecciona migraciones, modelos Eloquent, vistas/funciones SQL, servicios y jobs existentes. Construye una tabla de mapeo: `campo requerido → tabla.campo real | NO EXISTE`.
2. No crees tablas paralelas para datos que ya existen con otro nombre. Si falta un dato, propón en el plan la migración mínima y aditiva, y espera aprobación.
3. Si un prerrequisito no existe, NO simules el dato (ni ceros, ni NULL silenciosos, ni valores fijos). Repórtalo y separa en el plan "lo que se puede entregar hoy" de "lo que requiere construir el control primero". Un reporte que cuadra con datos falsos es peor que no tener reporte.
4. Montos en `DECIMAL(18,2)`; tipos de cambio en `DECIMAL(18,6)`; nunca `FLOAT`. Redondea a 2 decimales a nivel de renglón y después suma; usa el mismo criterio que los demás reportes con los que este debe cuadrar.
5. Consultas siempre parametrizadas (bindings). Prohibido concatenar filtros en SQL.
6. El reporte no consulta en línea los linked servers de las estaciones; usa las tablas ya consolidadas en el servidor del portal.
7. Una sola fuente de verdad por cálculo: si otro reporte o el motor de control usa el mismo cálculo, encapsúlalo (vista, función de tabla en línea o clase de servicio) y reutilízalo. No dupliques fórmulas.
8. No cambies reglas de negocio existentes salvo lo que este documento pida explícitamente. Si detectas un bug, repórtalo en el plan en lugar de corregirlo en silencio.
9. Reglas fiscales (tasas, claves SAT, plazos): van en catálogos parametrizables, nunca en código, y requieren confirmación de Contabilidad/Fiscal antes de liberarse.

### Paso 0: prerrequisitos que debes verificar antes de programar

- Datos de OC, recepciones y facturas con fechas confiables.
- Lista de tipos de gasto autorizados para OC directa (de RC-02).
- Tabla de excepciones con flujo de estatus (probablemente no existe: inclúyela en el plan).

Si alguno no existe, repórtalo en el plan con su impacto; no lo sustituyas con datos simulados.

## B) NOMBRE Y PROPÓSITO DEL REPORTE

- ID: `RR-03` · Dominio: C. Recepción y devengo · Fase: F1
- Nombre técnico: `rpt_flow_exceptions`
- Nombre de negocio: Excepciones de flujo
- Frecuencia: Semanal
- Consumidor principal: Contraloría
- Granularidad (una fila =): Una fila por documento que rompió la secuencia esperada del proceso.

**Pregunta de negocio que responde / decisión que habilita:** El termómetro de qué tan real es el proceso. Si este reporte crece, el portal se está usando para capturar hechos consumados en lugar de para controlar antes de comprometer.

**Requerimiento original de Contabilidad y Finanzas (campos mínimos):**

  • Tipo de excepción: factura sin recepción, recepción sin OC, OC directa fuera de la lista autorizada, factura con fecha anterior a la OC, recepción posterior a la vigencia
  • Documento · Proveedor · Importe · Usuario que la originó · Fecha
  • Estatus de regularización · Responsable asignado

## C) COLUMNAS / CAMPOS

Origen = SUPUESTO de nombre; mapea al esquema real en el Paso 0. O = obligatoria, Op = opcional.

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| exception_code | CHAR(6) | flow_exceptions.code | EXC-01 factura sin recepción · EXC-02 recepción sin OC · EXC-03 OC directa fuera de lista · EXC-04 factura con fecha anterior a la OC · EXC-05 recepción posterior a la vigencia de la OC | texto | O |
| exception_name | NVARCHAR | catálogo |  | texto | O |
| document_type / document_folio | VARCHAR / NVARCHAR | flow_exceptions |  | liga | O |
| supplier_name | NVARCHAR | suppliers |  | texto | O |
| amount_mxn | DECIMAL(18,2) | documento |  | moneda | O |
| originated_by / originated_at | NVARCHAR / DATETIME2 | documento | usuario que creó el documento | texto / fecha | O |
| detected_at | DATETIME2 | flow_exceptions |  | fecha-hora | O |
| status | VARCHAR(20) | flow_exceptions | ABIERTA / EN_REGULARIZACION / CERRADA | badge | O |
| assigned_to | NVARCHAR | users | responsable de regularizar | texto | O |
| days_open | INT | calculada | DATEDIFF(day, detected_at, COALESCE(closed_at, hoy)) | número | O |
| regularization_evidence | NVARCHAR | attachments |  | liga | O para cerrar |
| closed_at / closed_by | DATETIME2 / NVARCHAR | flow_exceptions |  | fecha / texto | O para cerrar |

## D) FILTROS Y PARÁMETROS

**Filtros fijos (siempre aplicados):**

- Por defecto, excepciones ABIERTAS y EN_REGULARIZACION.

**Parámetros de entrada (según el Excel: Tipo de excepción, empresa, periodo, importe, usuario.):**

| Parámetro | Tipo | Valor por defecto | O/Op |
|---|---|---|---|
| exception_code | multi-enum | todos | Op |
| company_ids | array<int> | empresas del usuario | O |
| date_from / date_to (detección) | date | últimos 90 días | O |
| amount_greater_than | decimal | 0 | Op |
| originated_by | int | todos | Op |
| status | multi-enum | abiertas | Op |

## E) AGRUPACIONES, ORDEN Y GRANULARIDAD

- Una fila por documento en excepción.
- ORDER BY amount_mxn DESC.
- Resumen por código de excepción y tendencia semanal de excepciones nuevas.

## F) REGLAS DE NEGOCIO Y CÁLCULOS

- Detección idempotente (job diario): índice único (code, document_type, document_id) para no duplicar al re-ejecutar.
- Los códigos EXC-01…EXC-05 son estables: nunca se reutilizan ni se renumeran; nuevos tipos toman el siguiente número.
- Cerrar requiere evidencia adjunta y responsable; sin ambos, el backend rechaza el cierre.
- Una excepción cerrada no se reabre por re-detección; si la causa persiste en un documento nuevo, es otra excepción.

## G) FORMATO DE SALIDA

- Tarjetas por código con conteo e importe abierto.
- Gráfico de tendencia semanal (si crece, el portal se usa para capturar hechos consumados).
- Tabla con acción 'Asignar' y 'Cerrar con evidencia'.

Convenciones comunes de salida:
- Pantalla: tabla con DataTables en modo server-side (paginación en servidor de 25/50/100 filas), encabezado fijo y primera columna congelada, panel de filtros arriba con los parámetros de la sección D y botón "Limpiar filtros".
- Excel (.xlsx) con celdas tipadas de verdad (moneda `$#,##0.00`, fechas `dd/mm/yyyy`, porcentaje `0.0%`), nunca números como texto; encabezado con nombre del reporte, empresa(s), filtros aplicados, fecha/hora de generación y usuario. Escritura en streaming (chunks), sin cargar todo en memoria.
- CSV UTF-8 con BOM y separador coma.
- Exportaciones de más de 50 000 filas se encolan como job y se notifica al usuario cuando el archivo está listo.
- La exportación respeta exactamente los filtros activos en pantalla.

## H) CONSIDERACIONES TÉCNICAS

**Tablas/vistas probables (SUPUESTO):** flow_exceptions (nueva), invoices, receptions, purchase_orders, direct_po_expense_types, suppliers, users, attachments.

**Índices recomendados (valida contra los existentes antes de crear):**

- flow_exceptions UNIQUE (code, document_type, document_id).
- flow_exceptions (status, detected_at) INCLUDE (amount_mxn, code).

**Seguridad y permisos:**

- Permisos granulares: `reportes.flow_exceptions.ver` y `reportes.flow_exceptions.exportar` (crea los permisos con el mecanismo que ya use el repo).
- Alcance por empresa: el usuario solo ve las empresas que tiene asignadas; el filtro se aplica en el servidor (scope/policy), nunca solo en la UI.
- Cada exportación se registra en bitácora: usuario, fecha/hora, filtros, número de filas.

- Cerrar excepciones: solo Contraloría o el responsable asignado.

**Endpoints y componentes:**

- GET /reportes/control/excepciones-flujo (+ /data, /export).
- PATCH /control/excepciones-flujo/{id} (asignar / cerrar).
- Job `FlowExceptionsDetectionJob`.

## I) CRITERIOS DE ACEPTACIÓN (Definition of Done)

Criterio de negocio original: _Toda excepción se clasifica automáticamente con un código estable y solo se cierra con evidencia de regularización y responsable._

- [ ] Cada tipo de excepción tiene una prueba con datos semilla que la detecta con su código exacto.
- [ ] Re-ejecutar la detección no duplica excepciones.
- [ ] No es posible cerrar sin evidencia y responsable.
- [ ] Todos los filtros de la sección D funcionan combinados y la exportación reproduce exactamente la vista filtrada.
- [ ] Usuarios sin permiso reciben 403 y ningún usuario ve empresas fuera de su alcance.
- [ ] La consulta principal responde en menos de 3 s con el volumen de un año de operación (o se justifica el snapshot/job).

## J) ENTREGABLE ESPERADO

Trabaja en tres entregas y DETENTE al terminar la primera:

1. **PLAN (sin código).**
   - Tabla de mapeo de esquema (campo requerido → tabla.campo real | NO EXISTE).
   - Estado de cada prerrequisito del Paso 0: existe / parcial / no existe, con su impacto en el reporte.
   - Arquitectura: archivos a crear o modificar (migraciones, vista o función SQL, clase de servicio del reporte, FormRequest de parámetros, controlador, rutas, vista Blade/JS, clase de exportación, policy/permisos, jobs y scheduler si aplica).
   - SQL propuesto (borrador) de la consulta principal.
   - Preguntas abiertas para Contraloría/Contabilidad/Fiscal, cada una con el supuesto que aplicarás si no hay respuesta.
   - Espera mi aprobación antes de continuar.
2. **CÓDIGO.** Implementa exactamente lo aprobado: migraciones aditivas, consulta encapsulada, servicio, endpoint, vista, exportaciones, permisos, jobs. Commits pequeños y descriptivos.
3. **PRUEBAS Y VALIDACIÓN.**
   - Pruebas de feature (Pest o PHPUnit, lo que use el repo) con datos semilla que ejerzan cada regla de negocio y cada criterio de aceptación de la sección I.
   - Pruebas de permisos: sin permiso → 403; alcance por empresa/proveedor respetado.
   - Script SQL de verificación que demuestre las identidades de cuadre del reporte.
   - Plan de ejecución en SQL Server con volumen realista; reporta tiempos y los índices creados.
   - Lista explícita de lo que quedó pendiente o fuera de alcance.

------------------------------------------------

================================================
REPORTE #12: RF-01 · Bitácora de validación de CFDI
================================================

# REPORTE RF-01: Bitácora de validación de CFDI

## A) ROL Y OBJETIVO

Actúa como desarrollador senior full-stack en el Portal de Proveedores (Módulo de Compras) de TotalGas, con experiencia en reporting financiero para Contabilidad, Contraloría y Tesorería en México (CFDI 4.0, SAT, REPSE).

**Objetivo medible:** Registrar y mostrar cada intento de carga de CFDI con su resultado, código de rechazo estable, regla incumplida y mensaje entendible para el proveedor, sin que recargar un UUID genere una segunda factura.

### Contexto técnico del proyecto
- Aplicación: Portal de Proveedores / Módulo de Compras de TotalGas (grupo gasolinero con 36 estaciones, Cd. Juárez, Chih.). Ya cubre requisiciones, cotizaciones, órdenes de compra, recepciones, control presupuestal y validación CFDI/SAT (RFC, listado 69-B). Cada centro de costo ya tiene responsable asignado.
- Stack: Laravel 12, PHP 8.3, SQL Server 2019, Blade + Bootstrap 5 + jQuery. Si el repositorio ya usa Livewire, DataTables, Laravel Excel u OpenSpout, spatie/laravel-permission o dompdf/snappy, REUTILÍZALOS. No agregues dependencias nuevas sin justificarlas en el plan.
- Zona horaria de negocio: `America/Ciudad_Juarez`. Toda "fecha de corte" se interpreta a las 23:59:59 de esa zona.
- Moneda funcional: MXN. Los nombres de tablas y campos de este documento son SUPUESTOS: el esquema real del repositorio manda.

### Reglas de trabajo (obligatorias)
1. Antes de escribir código, inspecciona migraciones, modelos Eloquent, vistas/funciones SQL, servicios y jobs existentes. Construye una tabla de mapeo: `campo requerido → tabla.campo real | NO EXISTE`.
2. No crees tablas paralelas para datos que ya existen con otro nombre. Si falta un dato, propón en el plan la migración mínima y aditiva, y espera aprobación.
3. Si un prerrequisito no existe, NO simules el dato (ni ceros, ni NULL silenciosos, ni valores fijos). Repórtalo y separa en el plan "lo que se puede entregar hoy" de "lo que requiere construir el control primero". Un reporte que cuadra con datos falsos es peor que no tener reporte.
4. Montos en `DECIMAL(18,2)`; tipos de cambio en `DECIMAL(18,6)`; nunca `FLOAT`. Redondea a 2 decimales a nivel de renglón y después suma; usa el mismo criterio que los demás reportes con los que este debe cuadrar.
5. Consultas siempre parametrizadas (bindings). Prohibido concatenar filtros en SQL.
6. El reporte no consulta en línea los linked servers de las estaciones; usa las tablas ya consolidadas en el servidor del portal.
7. Una sola fuente de verdad por cálculo: si otro reporte o el motor de control usa el mismo cálculo, encapsúlalo (vista, función de tabla en línea o clase de servicio) y reutilízalo. No dupliques fórmulas.
8. No cambies reglas de negocio existentes salvo lo que este documento pida explícitamente. Si detectas un bug, repórtalo en el plan en lugar de corregirlo en silencio.
9. Reglas fiscales (tasas, claves SAT, plazos): van en catálogos parametrizables, nunca en código, y requieren confirmación de Contabilidad/Fiscal antes de liberarse.

### Paso 0: prerrequisitos que debes verificar antes de programar

- Motor de validación CFDI existente (RFC, estructura, listado 69-B, etc.). Localízalo: la bitácora debe escribirse desde ahí.
- Bitácora POR INTENTO (no solo el resultado final). Si hoy solo se guarda la factura aceptada, crea `cfdi_validation_logs`.
- Catálogo de códigos de rechazo estables con regla y mensaje al proveedor. Las reglas fiscales deben estar confirmadas por Contabilidad.
- Índice único por UUID en facturas.

Si alguno no existe, repórtalo en el plan con su impacto; no lo sustituyas con datos simulados.

## B) NOMBRE Y PROPÓSITO DEL REPORTE

- ID: `RF-01` · Dominio: D. Validación fiscal, IVA y complementos de pago · Fase: F1
- Nombre técnico: `rpt_cfdi_validation_log`
- Nombre de negocio: Bitácora de validación de CFDI
- Frecuencia: Tiempo real
- Consumidor principal: Fiscal, Cuentas por pagar, proveedor
- Granularidad (una fila =): Una fila por intento de carga de un CFDI.

**Pregunta de negocio que responde / decisión que habilita:** Por qué se rechazan las facturas y a quién se le rechazan más. Sirve para corregir al proveedor con un dato concreto, para sostener el rechazo cuando reclama y para medir si el propio motor de validación está mal calibrado.

**Requerimiento original de Contabilidad y Finanzas (campos mínimos):**

  • Fecha y hora · UUID · RFC emisor · Empresa receptora · Tipo de comprobante · Total
  • Resultado (aceptado / rechazado) · Código de rechazo · Regla de negocio incumplida
  • Mensaje devuelto al proveedor · Número de intento · Canal o usuario

## C) COLUMNAS / CAMPOS

Origen = SUPUESTO de nombre; mapea al esquema real en el Paso 0. O = obligatoria, Op = opcional.

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| attempted_at | DATETIME2 | cfdi_validation_logs |  | fecha-hora | O |
| uuid | CHAR(36) | XML (TimbreFiscalDigital) | en mayúsculas | texto monoespaciado | O |
| issuer_rfc / issuer_name | NVARCHAR | XML Emisor |  | texto | O |
| receiver_company | NVARCHAR | companies (por RFC receptor) |  | texto | O |
| cfdi_type | CHAR(1) | XML TipoDeComprobante | I / E / P / N / T | texto | O |
| total / currency | DECIMAL(18,2) / CHAR(3) | XML |  | moneda | O |
| result | VARCHAR(10) | cfdi_validation_logs | ACEPTADO / RECHAZADO | badge | O |
| rejection_code | VARCHAR(10) | cfdi_rejection_codes |  | texto | O si RECHAZADO |
| rule_description | NVARCHAR(300) | cfdi_rejection_codes | regla de negocio incumplida | texto | O si RECHAZADO |
| supplier_message | NVARCHAR(500) | cfdi_rejection_codes | mensaje mostrado al proveedor | texto | O si RECHAZADO |
| attempt_no | INT | calculada | ROW_NUMBER() OVER (PARTITION BY uuid ORDER BY attempted_at) | número | O |
| channel | VARCHAR(15) | cfdi_validation_logs | PORTAL_PROVEEDOR / INTERNO / API | texto | O |
| user_name | NVARCHAR | users | quién cargó | texto | O |
| sat_status | VARCHAR(15) | consulta de estatus SAT | Vigente / Cancelado / No encontrado | texto | Op |
| xml_sha256 | CHAR(64) | calculada al cargar |  | texto | Op |

## D) FILTROS Y PARÁMETROS

**Filtros fijos (siempre aplicados):**

- Todos los intentos (aceptados y rechazados).

**Parámetros de entrada (según el Excel: Resultado, código de rechazo, proveedor, periodo, empresa.):**

| Parámetro | Tipo | Valor por defecto | O/Op |
|---|---|---|---|
| result | enum | todos | Op |
| rejection_code | multi | todos | Op |
| supplier_id | int | todos | Op |
| date_from / date_to | date | últimos 30 días | O |
| company_ids | array<int> | empresas del usuario | O |

## E) AGRUPACIONES, ORDEN Y GRANULARIDAD

- Una fila por intento.
- ORDER BY attempted_at DESC.
- Resúmenes: por código de rechazo, por proveedor (tasa de rechazo), por regla.

## F) REGLAS DE NEGOCIO Y CÁLCULOS

- Tasa de rechazo en primera carga = UUID cuyo intento 1 fue RECHAZADO / UUID distintos cargados.
- Reintentos: el mismo UUID puede tener N intentos en la bitácora, pero solo un registro en `invoices` (índice único + manejo de la excepción de duplicado con mensaje claro).
- Códigos estables: se agregan, nunca se renumeran; cada uno referencia su regla en el catálogo.
- Si una regla rechaza de más (p. ej. > 30 % de los intentos de un código en una semana), resaltar como posible mala calibración.

## G) FORMATO DE SALIDA

- Tarjetas: intentos, % rechazo en primera carga, top código, reintentos promedio.
- Gráfico de barras por código de rechazo.
- Vista para el proveedor: solo sus intentos, con el mensaje amigable.

Convenciones comunes de salida:
- Pantalla: tabla con DataTables en modo server-side (paginación en servidor de 25/50/100 filas), encabezado fijo y primera columna congelada, panel de filtros arriba con los parámetros de la sección D y botón "Limpiar filtros".
- Excel (.xlsx) con celdas tipadas de verdad (moneda `$#,##0.00`, fechas `dd/mm/yyyy`, porcentaje `0.0%`), nunca números como texto; encabezado con nombre del reporte, empresa(s), filtros aplicados, fecha/hora de generación y usuario. Escritura en streaming (chunks), sin cargar todo en memoria.
- CSV UTF-8 con BOM y separador coma.
- Exportaciones de más de 50 000 filas se encolan como job y se notifica al usuario cuando el archivo está listo.
- La exportación respeta exactamente los filtros activos en pantalla.

## H) CONSIDERACIONES TÉCNICAS

**Tablas/vistas probables (SUPUESTO):** cfdi_validation_logs (nueva si no existe), cfdi_rejection_codes (catálogo), invoices, suppliers, companies, users.

**Índices recomendados (valida contra los existentes antes de crear):**

- cfdi_validation_logs (attempted_at) INCLUDE (result, rejection_code, supplier_id, company_id).
- cfdi_validation_logs (uuid, attempted_at).
- invoices UNIQUE (uuid).

**Seguridad y permisos:**

- Permisos granulares: `reportes.cfdi_validation_log.ver` y `reportes.cfdi_validation_log.exportar` (crea los permisos con el mecanismo que ya use el repo).
- Alcance por empresa: el usuario solo ve las empresas que tiene asignadas; el filtro se aplica en el servidor (scope/policy), nunca solo en la UI.
- Cada exportación se registra en bitácora: usuario, fecha/hora, filtros, número de filas.

- Usuario proveedor: solo sus intentos (scope por supplier_id aplicado en servidor).
- Fiscal y Cuentas por pagar: todo su alcance.

**Endpoints y componentes:**

- GET /reportes/fiscal/bitacora-cfdi (+ /data, /export).
- GET /proveedor/mis-cargas (vista del proveedor).

## I) CRITERIOS DE ACEPTACIÓN (Definition of Done)

Criterio de negocio original: _Cada rechazo trae código estable, referencia a la regla y mensaje entendible para el proveedor. Recargar el mismo UUID no genera un segundo registro de factura._

- [ ] Cada rechazo tiene código estable, referencia a la regla y mensaje entendible para el proveedor.
- [ ] Recargar el mismo UUID genera un nuevo intento en la bitácora y NINGÚN registro adicional de factura.
- [ ] Un proveedor no puede ver intentos de otro proveedor (prueba de permisos).
- [ ] Todos los filtros de la sección D funcionan combinados y la exportación reproduce exactamente la vista filtrada.
- [ ] Usuarios sin permiso reciben 403 y ningún usuario ve empresas fuera de su alcance.
- [ ] La consulta principal responde en menos de 3 s con el volumen de un año de operación (o se justifica el snapshot/job).

**Preguntas abiertas (aplica el SUPUESTO indicado si no hay respuesta):**

- Reglas de validación fiscal: pendientes de visto bueno de Contabilidad antes de liberar.

## J) ENTREGABLE ESPERADO

Trabaja en tres entregas y DETENTE al terminar la primera:

1. **PLAN (sin código).**
   - Tabla de mapeo de esquema (campo requerido → tabla.campo real | NO EXISTE).
   - Estado de cada prerrequisito del Paso 0: existe / parcial / no existe, con su impacto en el reporte.
   - Arquitectura: archivos a crear o modificar (migraciones, vista o función SQL, clase de servicio del reporte, FormRequest de parámetros, controlador, rutas, vista Blade/JS, clase de exportación, policy/permisos, jobs y scheduler si aplica).
   - SQL propuesto (borrador) de la consulta principal.
   - Preguntas abiertas para Contraloría/Contabilidad/Fiscal, cada una con el supuesto que aplicarás si no hay respuesta.
   - Espera mi aprobación antes de continuar.
2. **CÓDIGO.** Implementa exactamente lo aprobado: migraciones aditivas, consulta encapsulada, servicio, endpoint, vista, exportaciones, permisos, jobs. Commits pequeños y descriptivos.
3. **PRUEBAS Y VALIDACIÓN.**
   - Pruebas de feature (Pest o PHPUnit, lo que use el repo) con datos semilla que ejerzan cada regla de negocio y cada criterio de aceptación de la sección I.
   - Pruebas de permisos: sin permiso → 403; alcance por empresa/proveedor respetado.
   - Script SQL de verificación que demuestre las identidades de cuadre del reporte.
   - Plan de ejecución en SQL Server con volumen realista; reporta tiempos y los índices creados.
   - Lista explícita de lo que quedó pendiente o fuera de alcance.

------------------------------------------------

================================================
REPORTE #13: RF-02 · Resultado del cotejo triple y desviaciones
================================================

# REPORTE RF-02: Resultado del cotejo triple y desviaciones

## A) ROL Y OBJETIVO

Actúa como desarrollador senior full-stack en el Portal de Proveedores (Módulo de Compras) de TotalGas, con experiencia en reporting financiero para Contabilidad, Contraloría y Tesorería en México (CFDI 4.0, SAT, REPSE).

**Objetivo medible:** Mostrar para cada factura cotejada contra OC y recepción la desviación en precio y cantidad (importe y %), la tolerancia aplicada y quién liberó cada desviación, garantizando que nada fuera de tolerancia llegue a cuentas por pagar sin autorización registrada.

### Contexto técnico del proyecto
- Aplicación: Portal de Proveedores / Módulo de Compras de TotalGas (grupo gasolinero con 36 estaciones, Cd. Juárez, Chih.). Ya cubre requisiciones, cotizaciones, órdenes de compra, recepciones, control presupuestal y validación CFDI/SAT (RFC, listado 69-B). Cada centro de costo ya tiene responsable asignado.
- Stack: Laravel 12, PHP 8.3, SQL Server 2019, Blade + Bootstrap 5 + jQuery. Si el repositorio ya usa Livewire, DataTables, Laravel Excel u OpenSpout, spatie/laravel-permission o dompdf/snappy, REUTILÍZALOS. No agregues dependencias nuevas sin justificarlas en el plan.
- Zona horaria de negocio: `America/Ciudad_Juarez`. Toda "fecha de corte" se interpreta a las 23:59:59 de esa zona.
- Moneda funcional: MXN. Los nombres de tablas y campos de este documento son SUPUESTOS: el esquema real del repositorio manda.

### Reglas de trabajo (obligatorias)
1. Antes de escribir código, inspecciona migraciones, modelos Eloquent, vistas/funciones SQL, servicios y jobs existentes. Construye una tabla de mapeo: `campo requerido → tabla.campo real | NO EXISTE`.
2. No crees tablas paralelas para datos que ya existen con otro nombre. Si falta un dato, propón en el plan la migración mínima y aditiva, y espera aprobación.
3. Si un prerrequisito no existe, NO simules el dato (ni ceros, ni NULL silenciosos, ni valores fijos). Repórtalo y separa en el plan "lo que se puede entregar hoy" de "lo que requiere construir el control primero". Un reporte que cuadra con datos falsos es peor que no tener reporte.
4. Montos en `DECIMAL(18,2)`; tipos de cambio en `DECIMAL(18,6)`; nunca `FLOAT`. Redondea a 2 decimales a nivel de renglón y después suma; usa el mismo criterio que los demás reportes con los que este debe cuadrar.
5. Consultas siempre parametrizadas (bindings). Prohibido concatenar filtros en SQL.
6. El reporte no consulta en línea los linked servers de las estaciones; usa las tablas ya consolidadas en el servidor del portal.
7. Una sola fuente de verdad por cálculo: si otro reporte o el motor de control usa el mismo cálculo, encapsúlalo (vista, función de tabla en línea o clase de servicio) y reutilízalo. No dupliques fórmulas.
8. No cambies reglas de negocio existentes salvo lo que este documento pida explícitamente. Si detectas un bug, repórtalo en el plan en lugar de corregirlo en silencio.
9. Reglas fiscales (tasas, claves SAT, plazos): van en catálogos parametrizables, nunca en código, y requieren confirmación de Contabilidad/Fiscal antes de liberarse.

### Paso 0: prerrequisitos que debes verificar antes de programar

- Relación renglón de CFDI ↔ renglón de OC ↔ renglón de recepción. OJO: los conceptos del CFDI rara vez coinciden 1:1 con los renglones de OC. Verifica cómo se vincula hoy; si no existe asignación por renglón, el cotejo solo puede hacerse a nivel encabezado y hay que decirlo.
- Tolerancias parametrizadas (%, monto absoluto; por categoría o proveedor).
- Bitácora de overrides (RA-01) para las liberaciones. RA-01 está en F2: adelanta su tabla y servicio mínimo.

Si alguno no existe, repórtalo en el plan con su impacto; no lo sustituyas con datos simulados.

## B) NOMBRE Y PROPÓSITO DEL REPORTE

- ID: `RF-02` · Dominio: D. Validación fiscal, IVA y complementos de pago · Fase: F1
- Nombre técnico: `rpt_three_way_match`
- Nombre de negocio: Resultado del cotejo triple y desviaciones
- Frecuencia: Diaria
- Consumidor principal: Compras, Contraloría, Cuentas por pagar
- Granularidad (una fila =): Una fila por factura cotejada, con detalle por renglón.

**Pregunta de negocio que responde / decisión que habilita:** El sobreprecio y la sobrefacturación cuantificados en pesos, no percibidos. Y el listado de quién está liberando desviaciones y por cuánto.

**Requerimiento original de Contabilidad y Finanzas (campos mínimos):**

  • UUID · OC · Recepción · Renglón
  • Precio de OC vs. precio facturado · Cantidad recibida vs. facturada · Importe y % de desviación
  • Tolerancia aplicada · Resultado (dentro de tolerancia / bloqueada / liberada con autorización)
  • Usuario que autorizó el desvío y motivo

## C) COLUMNAS / CAMPOS

Origen = SUPUESTO de nombre; mapea al esquema real en el Paso 0. O = obligatoria, Op = opcional.

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| uuid / invoice_date | CHAR(36) / DATE | invoices |  | texto / fecha | O |
| supplier_name / buyer_name | NVARCHAR | suppliers / users |  | texto | O |
| po_folio / reception_folio | NVARCHAR | purchase_orders / receptions |  | liga | O |
| line | INT | three_way_match_lines |  | número | O |
| po_unit_price / invoiced_unit_price | DECIMAL(18,6) | OC / CFDI |  | moneda | O |
| price_diff / price_diff_pct | DECIMAL | calculada | invoiced − po; diff / NULLIF(po,0) | moneda / % | O |
| received_qty / invoiced_qty | DECIMAL(18,4) | recepción / CFDI |  | número | O |
| qty_diff | DECIMAL(18,4) | calculada | invoiced_qty − received_qty | número | O |
| expected_amount | DECIMAL(18,2) | calculada | po_unit_price × MIN(received_qty, invoiced_qty) | moneda | O |
| invoiced_amount | DECIMAL(18,2) | CFDI | importe del concepto sin impuestos | moneda | O |
| deviation_amount / deviation_pct | DECIMAL | calculada | invoiced_amount − expected_amount; / NULLIF(expected_amount,0) | moneda / % | O |
| tolerance_applied | NVARCHAR | match_tolerances | % y monto aplicados | texto | O |
| match_result | VARCHAR(20) | calculada | DENTRO_TOLERANCIA / BLOQUEADA / LIBERADA_CON_AUTORIZACION | badge | O |
| released_by / release_reason / released_at | varios | override_logs (type='DESVIACION_TOLERANCIA') |  | texto / fecha | O si LIBERADA |

## D) FILTROS Y PARÁMETROS

**Filtros fijos (siempre aplicados):**

- Facturas tipo I con OC asociada.

**Parámetros de entrada (según el Excel: Resultado, proveedor, comprador, desviación mayor a X, periodo.):**

| Parámetro | Tipo | Valor por defecto | O/Op |
|---|---|---|---|
| match_result | multi-enum | todos | Op |
| supplier_id / buyer_id | int | todos | Op |
| deviation_greater_than (monto o %) | decimal | 0 | Op |
| date_from / date_to | date | mes en curso | O |
| company_ids | array<int> | empresas del usuario | O |

## E) AGRUPACIONES, ORDEN Y GRANULARIDAD

- Una fila por factura, detalle por renglón.
- ORDER BY deviation_amount DESC.
- Totales: Σ desviación bloqueada, Σ desviación liberada por autorizador.

## F) REGLAS DE NEGOCIO Y CÁLCULOS

- Comparación en la moneda de la OC; si la moneda de la factura difiere, BLOQUEADA automáticamente.
- Resultado: dentro de tolerancia si |deviation_pct| ≤ tol_% Y |deviation_amount| ≤ tol_monto (ambas condiciones, SUPUESTO).
- Desviaciones a favor de la empresa (facturan menos) no bloquean, pero se reportan.
- La cuenta por pagar solo se crea si el resultado es DENTRO_TOLERANCIA o LIBERADA_CON_AUTORIZACION con registro en RA-01; esto se valida en el backend, no solo en el reporte.

## G) FORMATO DE SALIDA

- Tarjetas: Σ sobreprecio detectado, Σ liberado, # bloqueadas.
- Ranking de autorizadores por monto liberado.
- Tabla con detalle expandible por renglón.

Convenciones comunes de salida:
- Pantalla: tabla con DataTables en modo server-side (paginación en servidor de 25/50/100 filas), encabezado fijo y primera columna congelada, panel de filtros arriba con los parámetros de la sección D y botón "Limpiar filtros".
- Excel (.xlsx) con celdas tipadas de verdad (moneda `$#,##0.00`, fechas `dd/mm/yyyy`, porcentaje `0.0%`), nunca números como texto; encabezado con nombre del reporte, empresa(s), filtros aplicados, fecha/hora de generación y usuario. Escritura en streaming (chunks), sin cargar todo en memoria.
- CSV UTF-8 con BOM y separador coma.
- Exportaciones de más de 50 000 filas se encolan como job y se notifica al usuario cuando el archivo está listo.
- La exportación respeta exactamente los filtros activos en pantalla.

## H) CONSIDERACIONES TÉCNICAS

**Tablas/vistas probables (SUPUESTO):** invoices, invoice_items, purchase_order_items, reception_items, three_way_match_lines (nueva si no existe), match_tolerances, override_logs, suppliers, users.

**Índices recomendados (valida contra los existentes antes de crear):**

- three_way_match_lines (invoice_id).
- three_way_match_lines (match_result, matched_at) INCLUDE (deviation_amount).

**Seguridad y permisos:**

- Permisos granulares: `reportes.three_way_match.ver` y `reportes.three_way_match.exportar` (crea los permisos con el mecanismo que ya use el repo).
- Alcance por empresa: el usuario solo ve las empresas que tiene asignadas; el filtro se aplica en el servidor (scope/policy), nunca solo en la UI.
- Cada exportación se registra en bitácora: usuario, fecha/hora, filtros, número de filas.

- Liberar desviaciones: permiso específico y nunca el mismo usuario que capturó la OC.

**Endpoints y componentes:**

- GET /reportes/fiscal/cotejo-triple (+ /data, /export).

## I) CRITERIOS DE ACEPTACIÓN (Definition of Done)

Criterio de negocio original: _Ninguna factura llega a cuentas por pagar con desviación fuera de tolerancia sin registro de autorización asociado._

- [ ] Consulta de verificación: 0 cuentas por pagar con desviación fuera de tolerancia sin registro de autorización en RA-01.
- [ ] Prueba con datos semilla para cada resultado (dentro, bloqueada, liberada).
- [ ] La desviación en pesos del reporte es la suma exacta de las desviaciones por renglón.
- [ ] Todos los filtros de la sección D funcionan combinados y la exportación reproduce exactamente la vista filtrada.
- [ ] Usuarios sin permiso reciben 403 y ningún usuario ve empresas fuera de su alcance.
- [ ] La consulta principal responde en menos de 3 s con el volumen de un año de operación (o se justifica el snapshot/job).

**Preguntas abiertas (aplica el SUPUESTO indicado si no hay respuesta):**

- ¿Tolerancias por defecto? SUPUESTO: 2 % y $500 MXN por renglón, parametrizables.

## J) ENTREGABLE ESPERADO

Trabaja en tres entregas y DETENTE al terminar la primera:

1. **PLAN (sin código).**
   - Tabla de mapeo de esquema (campo requerido → tabla.campo real | NO EXISTE).
   - Estado de cada prerrequisito del Paso 0: existe / parcial / no existe, con su impacto en el reporte.
   - Arquitectura: archivos a crear o modificar (migraciones, vista o función SQL, clase de servicio del reporte, FormRequest de parámetros, controlador, rutas, vista Blade/JS, clase de exportación, policy/permisos, jobs y scheduler si aplica).
   - SQL propuesto (borrador) de la consulta principal.
   - Preguntas abiertas para Contraloría/Contabilidad/Fiscal, cada una con el supuesto que aplicarás si no hay respuesta.
   - Espera mi aprobación antes de continuar.
2. **CÓDIGO.** Implementa exactamente lo aprobado: migraciones aditivas, consulta encapsulada, servicio, endpoint, vista, exportaciones, permisos, jobs. Commits pequeños y descriptivos.
3. **PRUEBAS Y VALIDACIÓN.**
   - Pruebas de feature (Pest o PHPUnit, lo que use el repo) con datos semilla que ejerzan cada regla de negocio y cada criterio de aceptación de la sección I.
   - Pruebas de permisos: sin permiso → 403; alcance por empresa/proveedor respetado.
   - Script SQL de verificación que demuestre las identidades de cuadre del reporte.
   - Plan de ejecución en SQL Server con volumen realista; reporta tiempos y los índices creados.
   - Lista explícita de lo que quedó pendiente o fuera de alcance.

------------------------------------------------

================================================
REPORTE #14: RF-03 · IVA acreditable y conciliación para la DIOT
================================================

# REPORTE RF-03: IVA acreditable y conciliación para la DIOT

## A) ROL Y OBJETIVO

Actúa como desarrollador senior full-stack en el Portal de Proveedores (Módulo de Compras) de TotalGas, con experiencia en reporting financiero para Contabilidad, Contraloría y Tesorería en México (CFDI 4.0, SAT, REPSE).

**Objetivo medible:** Calcular por proveedor y tipo de operación el IVA acreditable del mes bajo flujo de efectivo, separar lo pendiente por PPD sin REP, generar el archivo de la DIOT en el formato vigente y conciliar el acreditable contra la cuenta contable.

### Contexto técnico del proyecto
- Aplicación: Portal de Proveedores / Módulo de Compras de TotalGas (grupo gasolinero con 36 estaciones, Cd. Juárez, Chih.). Ya cubre requisiciones, cotizaciones, órdenes de compra, recepciones, control presupuestal y validación CFDI/SAT (RFC, listado 69-B). Cada centro de costo ya tiene responsable asignado.
- Stack: Laravel 12, PHP 8.3, SQL Server 2019, Blade + Bootstrap 5 + jQuery. Si el repositorio ya usa Livewire, DataTables, Laravel Excel u OpenSpout, spatie/laravel-permission o dompdf/snappy, REUTILÍZALOS. No agregues dependencias nuevas sin justificarlas en el plan.
- Zona horaria de negocio: `America/Ciudad_Juarez`. Toda "fecha de corte" se interpreta a las 23:59:59 de esa zona.
- Moneda funcional: MXN. Los nombres de tablas y campos de este documento son SUPUESTOS: el esquema real del repositorio manda.

### Reglas de trabajo (obligatorias)
1. Antes de escribir código, inspecciona migraciones, modelos Eloquent, vistas/funciones SQL, servicios y jobs existentes. Construye una tabla de mapeo: `campo requerido → tabla.campo real | NO EXISTE`.
2. No crees tablas paralelas para datos que ya existen con otro nombre. Si falta un dato, propón en el plan la migración mínima y aditiva, y espera aprobación.
3. Si un prerrequisito no existe, NO simules el dato (ni ceros, ni NULL silenciosos, ni valores fijos). Repórtalo y separa en el plan "lo que se puede entregar hoy" de "lo que requiere construir el control primero". Un reporte que cuadra con datos falsos es peor que no tener reporte.
4. Montos en `DECIMAL(18,2)`; tipos de cambio en `DECIMAL(18,6)`; nunca `FLOAT`. Redondea a 2 decimales a nivel de renglón y después suma; usa el mismo criterio que los demás reportes con los que este debe cuadrar.
5. Consultas siempre parametrizadas (bindings). Prohibido concatenar filtros en SQL.
6. El reporte no consulta en línea los linked servers de las estaciones; usa las tablas ya consolidadas en el servidor del portal.
7. Una sola fuente de verdad por cálculo: si otro reporte o el motor de control usa el mismo cálculo, encapsúlalo (vista, función de tabla en línea o clase de servicio) y reutilízalo. No dupliques fórmulas.
8. No cambies reglas de negocio existentes salvo lo que este documento pida explícitamente. Si detectas un bug, repórtalo en el plan en lugar de corregirlo en silencio.
9. Reglas fiscales (tasas, claves SAT, plazos): van en catálogos parametrizables, nunca en código, y requieren confirmación de Contabilidad/Fiscal antes de liberarse.

### Paso 0: prerrequisitos que debes verificar antes de programar

- Pagos a proveedores registrados con fecha real (el IVA se acredita al pago, art. 5 LIVA).
- REP recibidos y vinculados a facturas PPD (ver RF-05).
- Bases e impuestos por tasa desde el XML (Traslados por concepto: 16 %, 8 % estímulo región fronteriza norte, 0 %, exento; ObjetoImp 01 = no objeto).
- Tipo de tercero y tipo de operación por proveedor (catálogo).
- Saldo de la cuenta de IVA acreditable desde contabilidad (RK-02) para la conciliación.
- Formato vigente de la DIOT: ha tenido cambios recientes; NO lo codifiques de memoria. Obtén el layout y las validaciones oficiales vigentes y que Fiscal los confirme.

Si alguno no existe, repórtalo en el plan con su impacto; no lo sustituyas con datos simulados.

## B) NOMBRE Y PROPÓSITO DEL REPORTE

- ID: `RF-03` · Dominio: D. Validación fiscal, IVA y complementos de pago · Fase: F2
- Nombre técnico: `rpt_vat_creditable_diot`
- Nombre de negocio: IVA acreditable y conciliación para la DIOT
- Frecuencia: Mensual
- Consumidor principal: Fiscal
- Granularidad (una fila =): Una fila por proveedor (RFC) y tipo de operación, con detalle a nivel CFDI.

**Pregunta de negocio que responde / decisión que habilita:** La informativa armada desde el origen y, más valioso aún, la conciliación entre el IVA que el portal considera acreditable y el que está en la balanza. Hoy ese amarre se hace a mano y es justo donde nacen las diferencias.

**Requerimiento original de Contabilidad y Finanzas (campos mínimos):**

  • RFC · Razón social · Tipo de tercero (nacional, extranjero, global) · Tipo de operación
  • Base gravada por tasa: 16 %, 8 % región fronteriza, 0 %, exento, no objeto
  • IVA trasladado · IVA efectivamente pagado en el mes · IVA retenido
  • IVA acreditable del periodo · IVA pendiente de acreditar (PPD sin REP)

## C) COLUMNAS / CAMPOS

Origen = SUPUESTO de nombre; mapea al esquema real en el Paso 0. O = obligatoria, Op = opcional.

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| supplier_rfc / supplier_name | NVARCHAR | suppliers |  | texto | O |
| third_party_type | VARCHAR(10) | suppliers.diot_third_party_type | nacional / extranjero / global | texto | O |
| operation_type | VARCHAR(10) | catálogo | servicios profesionales / arrendamiento / otros (claves según layout vigente) | texto | O |
| base_16 / base_8 / base_0 / base_exempt / base_not_object | DECIMAL(18,2) | XML traslados × proporción pagada |  | moneda | O |
| vat_transferred | DECIMAL(18,2) | XML | IVA trasladado de las facturas pagadas en el mes (proporcional) | moneda | O |
| vat_paid_in_month | DECIMAL(18,2) | payments + REP | IVA efectivamente pagado en el mes | moneda | O |
| vat_withheld | DECIMAL(18,2) | XML retenciones IVA |  | moneda | O |
| vat_creditable | DECIMAL(18,2) | calculada | vat_paid_in_month excluyendo IVA no acreditable (tratamiento del retenido: ver dudas) | moneda | O |
| vat_pending_ppd | DECIMAL(18,2) | calculada | IVA de pagos a PPD sin REP recibido | moneda | O |

**Detalle expandible (segundo nivel):**

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| uuid / payment_method (PUE/PPD) / payment_date / paid_ratio / rep_uuid | varios | invoices, payments, payment_complements | paid_ratio = pago aplicado / total del CFDI |  | O |

## D) FILTROS Y PARÁMETROS

**Filtros fijos (siempre aplicados):**

- CFDI tipo I y E vigentes pagados en el mes (flujo de efectivo).

**Parámetros de entrada (según el Excel: Mes, empresa, tasa, con o sin REP, tipo de operación, proveedor.):**

| Parámetro | Tipo | Valor por defecto | O/Op |
|---|---|---|---|
| year_month | yyyy-mm | mes anterior | O |
| company_ids | array<int> | empresa del usuario | O |
| rate | multi | todas | Op |
| rep_filter | CON_REP / SIN_REP / TODOS | TODOS | Op |
| operation_type | multi | todos | Op |
| supplier_id | int | todos | Op |

## E) AGRUPACIONES, ORDEN Y GRANULARIDAD

- Una fila por RFC + tipo de operación, detalle a nivel CFDI y pago.
- ORDER BY supplier_rfc.
- Totales por tasa y generales; pestaña de conciliación contra la cuenta contable.

## F) REGLAS DE NEGOCIO Y CÁLCULOS

- PUE: el IVA se acredita en el mes del pago. PPD: solo en proporción al pago y con REP; sin REP va a vat_pending_ppd.
- Pagos parciales: base e IVA proporcionales al importe pagado / total del CFDI.
- Notas de crédito (tipo E) disminuyen base e IVA del mes en que se aplican (coordinar con RF-06).
- IVA no acreditable (gasto no deducible, catálogo) se excluye y se muestra aparte.
- Conciliación: vat_creditable total vs saldo del mes de la cuenta de IVA acreditable; diferencia explicada partida por partida o marcada.
- Archivo DIOT generado desde un layout configurable (tabla de campos y validaciones), no desde código fijo.

## G) FORMATO DE SALIDA

- Tarjetas: IVA acreditable del mes, IVA pendiente por falta de REP, diferencia contra contabilidad.
- Tabla por proveedor con detalle CFDI.
- Botón 'Generar archivo DIOT' (solo Fiscal) y descarga de Excel de papel de trabajo.

Convenciones comunes de salida:
- Pantalla: tabla con DataTables en modo server-side (paginación en servidor de 25/50/100 filas), encabezado fijo y primera columna congelada, panel de filtros arriba con los parámetros de la sección D y botón "Limpiar filtros".
- Excel (.xlsx) con celdas tipadas de verdad (moneda `$#,##0.00`, fechas `dd/mm/yyyy`, porcentaje `0.0%`), nunca números como texto; encabezado con nombre del reporte, empresa(s), filtros aplicados, fecha/hora de generación y usuario. Escritura en streaming (chunks), sin cargar todo en memoria.
- CSV UTF-8 con BOM y separador coma.
- Exportaciones de más de 50 000 filas se encolan como job y se notifica al usuario cuando el archivo está listo.
- La exportación respeta exactamente los filtros activos en pantalla.

## H) CONSIDERACIONES TÉCNICAS

**Tablas/vistas probables (SUPUESTO):** invoices, invoice_taxes, payments, payment_applications, payment_complements, suppliers, diot_layouts (nueva), accounting_balances (de RK-02).

**Índices recomendados (valida contra los existentes antes de crear):**

- payment_applications (payment_date) INCLUDE (invoice_id, amount).
- invoice_taxes (invoice_id) INCLUDE (tax, rate, base, amount).

**Seguridad y permisos:**

- Permisos granulares: `reportes.vat_creditable_diot.ver` y `reportes.vat_creditable_diot.exportar` (crea los permisos con el mecanismo que ya use el repo).
- Alcance por empresa: el usuario solo ve las empresas que tiene asignadas; el filtro se aplica en el servidor (scope/policy), nunca solo en la UI.
- Cada exportación se registra en bitácora: usuario, fecha/hora, filtros, número de filas.

- Solo Fiscal.

**Endpoints y componentes:**

- GET /reportes/fiscal/iva-diot (+ /data, /export).
- POST /reportes/fiscal/iva-diot/archivo.

## I) CRITERIOS DE ACEPTACIÓN (Definition of Done)

Criterio de negocio original: _El IVA acreditable del reporte cuadra contra la cuenta contable de IVA acreditable del mes, y el archivo de salida cumple el formato y las validaciones vigentes de la declaración informativa al momento de liberar._

- [ ] El IVA acreditable del reporte cuadra contra la cuenta contable de IVA acreditable del mes (o la diferencia está clasificada).
- [ ] El archivo pasa las validaciones del formato vigente al momento de liberar (evidencia: prueba con el validador oficial o revisión de Fiscal).
- [ ] Una factura PPD pagada sin REP no suma a acreditable y sí a pendiente.
- [ ] Todos los filtros de la sección D funcionan combinados y la exportación reproduce exactamente la vista filtrada.
- [ ] Usuarios sin permiso reciben 403 y ningún usuario ve empresas fuera de su alcance.
- [ ] La consulta principal responde en menos de 3 s con el volumen de un año de operación (o se justifica el snapshot/job).

**Preguntas abiertas (aplica el SUPUESTO indicado si no hay respuesta):**

- ¿El IVA retenido se resta del acreditable o se presenta separado? SUPUESTO: se presenta separado; el acreditable es el IVA trasladado pagado; Fiscal confirma.
- Reglas fiscales pendientes de visto bueno de Contabilidad antes de construir.

## J) ENTREGABLE ESPERADO

Trabaja en tres entregas y DETENTE al terminar la primera:

1. **PLAN (sin código).**
   - Tabla de mapeo de esquema (campo requerido → tabla.campo real | NO EXISTE).
   - Estado de cada prerrequisito del Paso 0: existe / parcial / no existe, con su impacto en el reporte.
   - Arquitectura: archivos a crear o modificar (migraciones, vista o función SQL, clase de servicio del reporte, FormRequest de parámetros, controlador, rutas, vista Blade/JS, clase de exportación, policy/permisos, jobs y scheduler si aplica).
   - SQL propuesto (borrador) de la consulta principal.
   - Preguntas abiertas para Contraloría/Contabilidad/Fiscal, cada una con el supuesto que aplicarás si no hay respuesta.
   - Espera mi aprobación antes de continuar.
2. **CÓDIGO.** Implementa exactamente lo aprobado: migraciones aditivas, consulta encapsulada, servicio, endpoint, vista, exportaciones, permisos, jobs. Commits pequeños y descriptivos.
3. **PRUEBAS Y VALIDACIÓN.**
   - Pruebas de feature (Pest o PHPUnit, lo que use el repo) con datos semilla que ejerzan cada regla de negocio y cada criterio de aceptación de la sección I.
   - Pruebas de permisos: sin permiso → 403; alcance por empresa/proveedor respetado.
   - Script SQL de verificación que demuestre las identidades de cuadre del reporte.
   - Plan de ejecución en SQL Server con volumen realista; reporta tiempos y los índices creados.
   - Lista explícita de lo que quedó pendiente o fuera de alcance.

------------------------------------------------

================================================
REPORTE #15: RF-04 · Retenciones efectuadas y su entero
================================================

# REPORTE RF-04: Retenciones efectuadas y su entero

## A) ROL Y OBJETIVO

Actúa como desarrollador senior full-stack en el Portal de Proveedores (Módulo de Compras) de TotalGas, con experiencia en reporting financiero para Contabilidad, Contraloría y Tesorería en México (CFDI 4.0, SAT, REPSE).

**Objetivo medible:** Comparar por CFDI la retención esperada (según tipo de persona y concepto) contra la del XML, y dar seguimiento a su entero y al CFDI de retenciones, bloqueando las facturas con diferencias.

### Contexto técnico del proyecto
- Aplicación: Portal de Proveedores / Módulo de Compras de TotalGas (grupo gasolinero con 36 estaciones, Cd. Juárez, Chih.). Ya cubre requisiciones, cotizaciones, órdenes de compra, recepciones, control presupuestal y validación CFDI/SAT (RFC, listado 69-B). Cada centro de costo ya tiene responsable asignado.
- Stack: Laravel 12, PHP 8.3, SQL Server 2019, Blade + Bootstrap 5 + jQuery. Si el repositorio ya usa Livewire, DataTables, Laravel Excel u OpenSpout, spatie/laravel-permission o dompdf/snappy, REUTILÍZALOS. No agregues dependencias nuevas sin justificarlas en el plan.
- Zona horaria de negocio: `America/Ciudad_Juarez`. Toda "fecha de corte" se interpreta a las 23:59:59 de esa zona.
- Moneda funcional: MXN. Los nombres de tablas y campos de este documento son SUPUESTOS: el esquema real del repositorio manda.

### Reglas de trabajo (obligatorias)
1. Antes de escribir código, inspecciona migraciones, modelos Eloquent, vistas/funciones SQL, servicios y jobs existentes. Construye una tabla de mapeo: `campo requerido → tabla.campo real | NO EXISTE`.
2. No crees tablas paralelas para datos que ya existen con otro nombre. Si falta un dato, propón en el plan la migración mínima y aditiva, y espera aprobación.
3. Si un prerrequisito no existe, NO simules el dato (ni ceros, ni NULL silenciosos, ni valores fijos). Repórtalo y separa en el plan "lo que se puede entregar hoy" de "lo que requiere construir el control primero". Un reporte que cuadra con datos falsos es peor que no tener reporte.
4. Montos en `DECIMAL(18,2)`; tipos de cambio en `DECIMAL(18,6)`; nunca `FLOAT`. Redondea a 2 decimales a nivel de renglón y después suma; usa el mismo criterio que los demás reportes con los que este debe cuadrar.
5. Consultas siempre parametrizadas (bindings). Prohibido concatenar filtros en SQL.
6. El reporte no consulta en línea los linked servers de las estaciones; usa las tablas ya consolidadas en el servidor del portal.
7. Una sola fuente de verdad por cálculo: si otro reporte o el motor de control usa el mismo cálculo, encapsúlalo (vista, función de tabla en línea o clase de servicio) y reutilízalo. No dupliques fórmulas.
8. No cambies reglas de negocio existentes salvo lo que este documento pida explícitamente. Si detectas un bug, repórtalo en el plan en lugar de corregirlo en silencio.
9. Reglas fiscales (tasas, claves SAT, plazos): van en catálogos parametrizables, nunca en código, y requieren confirmación de Contabilidad/Fiscal antes de liberarse.

### Paso 0: prerrequisitos que debes verificar antes de programar

- Catálogo de reglas de retención parametrizable: tipo de persona / régimen × concepto → tasas de ISR e IVA. Las tasas las confirma Fiscal; no las codifiques.
- Registro de pagos (la retención se causa al pagar).
- Registro del entero: mes, declaración, número de operación.
- CFDI de retenciones: el portal NO lo puede timbrar (no hay PAC contratado). Solo se registra el UUID del CFDI de retenciones emitido en otro sistema.

Si alguno no existe, repórtalo en el plan con su impacto; no lo sustituyas con datos simulados.

## B) NOMBRE Y PROPÓSITO DEL REPORTE

- ID: `RF-04` · Dominio: D. Validación fiscal, IVA y complementos de pago · Fase: F2
- Nombre técnico: `rpt_withholdings`
- Nombre de negocio: Retenciones efectuadas y su entero
- Frecuencia: Mensual
- Consumidor principal: Fiscal, Contabilidad
- Granularidad (una fila =): Una fila por CFDI con retención, con agregado por tipo de retención y proveedor.

**Pregunta de negocio que responde / decisión que habilita:** Control de que se retuvo lo que correspondía y se enteró lo que se retuvo. Una retención omitida vuelve no deducible el gasto; una retención no enterada es un crédito fiscal con actualización y recargos.

**Requerimiento original de Contabilidad y Finanzas (campos mínimos):**

  • UUID · Proveedor · Tipo de persona · Concepto · Base
  • Retención de ISR (honorarios, arrendamiento, autotransporte) · Retención de IVA (dos terceras partes, 6 %)
  • Importe retenido · Mes de causación · Mes de entero · Declaración donde se enteró
  • CFDI de retenciones emitido, cuando aplique

## C) COLUMNAS / CAMPOS

Origen = SUPUESTO de nombre; mapea al esquema real en el Paso 0. O = obligatoria, Op = opcional.

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| uuid | CHAR(36) | invoices |  | texto | O |
| supplier_name / supplier_rfc | NVARCHAR | suppliers |  | texto | O |
| person_type / tax_regime | VARCHAR | suppliers | PF / PM; régimen fiscal | texto | O |
| concept | NVARCHAR | withholding_rules | honorarios / arrendamiento / autotransporte / servicios con personal / otro | texto | O |
| base | DECIMAL(18,2) | XML |  | moneda | O |
| isr_withheld_xml / isr_expected | DECIMAL(18,2) | XML / calculada | expected = ROUND(base × tasa ISR del catálogo, 2) | moneda | O |
| vat_withheld_xml / vat_expected | DECIMAL(18,2) | XML / calculada | expected = ROUND(base × tasa retención IVA del catálogo, 2) | moneda | O |
| isr_diff / vat_diff | DECIMAL(18,2) | calculada | xml − expected | moneda (≠0 en rojo) | O |
| total_withheld | DECIMAL(18,2) | calculada | isr + iva del XML | moneda | O |
| accrual_month | CHAR(7) | payments | mes en que se pagó la factura | yyyy-mm | O |
| remittance_month / declaration_ref | CHAR(7) / NVARCHAR | tax_remittances |  | texto | O |
| withholding_cfdi_uuid | CHAR(36) | tax_remittances | registrado manualmente o importado | texto | Op |
| status | VARCHAR(15) | calculada | ENTERADA / PENDIENTE_ENTERO / CON_DIFERENCIA / BLOQUEADA | badge | O |

## D) FILTROS Y PARÁMETROS

**Filtros fijos (siempre aplicados):**

- CFDI con al menos una retención esperada o en XML.

**Parámetros de entrada (según el Excel: Tipo de retención, periodo, proveedor, tipo de persona.):**

| Parámetro | Tipo | Valor por defecto | O/Op |
|---|---|---|---|
| withholding_type | ISR / IVA / ambas | ambas | Op |
| year_month | yyyy-mm | mes anterior | O |
| supplier_id | int | todos | Op |
| person_type | enum | todos | Op |
| company_ids | array<int> | empresas del usuario | O |

## E) AGRUPACIONES, ORDEN Y GRANULARIDAD

- Una fila por CFDI con retención.
- Agregado por tipo de retención y por proveedor.
- ORDER BY status (diferencias primero), supplier.

## F) REGLAS DE NEGOCIO Y CÁLCULOS

- Referencia de tasas usuales (SUPUESTO, a confirmar por Fiscal y cargar en catálogo): ISR 10 % honorarios y arrendamiento de personas físicas; ISR 1.25 % RESICO persona física; IVA dos terceras partes en honorarios y arrendamiento de PF; IVA 4 % autotransporte terrestre de carga; IVA 6 % en servicios con puesta a disposición de personal. El Excel menciona 'ISR autotransporte': Fiscal debe confirmar el fundamento.
- Al validar el CFDI, el motor calcula la retención esperada y, si difiere de la del XML, bloquea la factura con mensaje explícito (código de RF-01).
- Retención causada en el mes de pago; debe enterarse en la declaración de ese mes (plazo parametrizable).

## G) FORMATO DE SALIDA

- Tarjetas: retenido del mes, pendiente de enterar, # facturas con diferencia.
- Tabla con estatus y columnas de diferencia resaltadas.
- Excel de papel de trabajo para la declaración.

Convenciones comunes de salida:
- Pantalla: tabla con DataTables en modo server-side (paginación en servidor de 25/50/100 filas), encabezado fijo y primera columna congelada, panel de filtros arriba con los parámetros de la sección D y botón "Limpiar filtros".
- Excel (.xlsx) con celdas tipadas de verdad (moneda `$#,##0.00`, fechas `dd/mm/yyyy`, porcentaje `0.0%`), nunca números como texto; encabezado con nombre del reporte, empresa(s), filtros aplicados, fecha/hora de generación y usuario. Escritura en streaming (chunks), sin cargar todo en memoria.
- CSV UTF-8 con BOM y separador coma.
- Exportaciones de más de 50 000 filas se encolan como job y se notifica al usuario cuando el archivo está listo.
- La exportación respeta exactamente los filtros activos en pantalla.

## H) CONSIDERACIONES TÉCNICAS

**Tablas/vistas probables (SUPUESTO):** invoices, invoice_taxes, suppliers, withholding_rules (nueva), payments, tax_remittances (nueva).

**Índices recomendados (valida contra los existentes antes de crear):**

- invoice_taxes (tax_type, withheld) INCLUDE (invoice_id, amount).
- tax_remittances (company_id, year_month).

**Seguridad y permisos:**

- Permisos granulares: `reportes.withholdings.ver` y `reportes.withholdings.exportar` (crea los permisos con el mecanismo que ya use el repo).
- Alcance por empresa: el usuario solo ve las empresas que tiene asignadas; el filtro se aplica en el servidor (scope/policy), nunca solo en la UI.
- Cada exportación se registra en bitácora: usuario, fecha/hora, filtros, número de filas.

- Solo Fiscal y Contabilidad.

**Endpoints y componentes:**

- GET /reportes/fiscal/retenciones (+ /data, /export).

## I) CRITERIOS DE ACEPTACIÓN (Definition of Done)

Criterio de negocio original: _El motor calcula la retención esperada según tipo de proveedor y concepto y la contrasta contra el XML. Las diferencias bloquean la factura con mensaje explícito._

- [ ] Toda factura con diferencia entre retención esperada y XML queda bloqueada con mensaje explícito.
- [ ] Σ retenciones causadas del mes = Σ enteradas + Σ pendientes.
- [ ] Ninguna tasa está escrita en código (revisión y prueba cambiando el catálogo).
- [ ] Todos los filtros de la sección D funcionan combinados y la exportación reproduce exactamente la vista filtrada.
- [ ] Usuarios sin permiso reciben 403 y ningún usuario ve empresas fuera de su alcance.
- [ ] La consulta principal responde en menos de 3 s con el volumen de un año de operación (o se justifica el snapshot/job).

**Preguntas abiertas (aplica el SUPUESTO indicado si no hay respuesta):**

- Reglas y tasas pendientes de visto bueno de Contabilidad/Fiscal.

## J) ENTREGABLE ESPERADO

Trabaja en tres entregas y DETENTE al terminar la primera:

1. **PLAN (sin código).**
   - Tabla de mapeo de esquema (campo requerido → tabla.campo real | NO EXISTE).
   - Estado de cada prerrequisito del Paso 0: existe / parcial / no existe, con su impacto en el reporte.
   - Arquitectura: archivos a crear o modificar (migraciones, vista o función SQL, clase de servicio del reporte, FormRequest de parámetros, controlador, rutas, vista Blade/JS, clase de exportación, policy/permisos, jobs y scheduler si aplica).
   - SQL propuesto (borrador) de la consulta principal.
   - Preguntas abiertas para Contraloría/Contabilidad/Fiscal, cada una con el supuesto que aplicarás si no hay respuesta.
   - Espera mi aprobación antes de continuar.
2. **CÓDIGO.** Implementa exactamente lo aprobado: migraciones aditivas, consulta encapsulada, servicio, endpoint, vista, exportaciones, permisos, jobs. Commits pequeños y descriptivos.
3. **PRUEBAS Y VALIDACIÓN.**
   - Pruebas de feature (Pest o PHPUnit, lo que use el repo) con datos semilla que ejerzan cada regla de negocio y cada criterio de aceptación de la sección I.
   - Pruebas de permisos: sin permiso → 403; alcance por empresa/proveedor respetado.
   - Script SQL de verificación que demuestre las identidades de cuadre del reporte.
   - Plan de ejecución en SQL Server con volumen realista; reporta tiempos y los índices creados.
   - Lista explícita de lo que quedó pendiente o fuera de alcance.

------------------------------------------------

================================================
REPORTE #16: RF-05 · Control de complementos de pago (REP)
================================================

# REPORTE RF-05: Control de complementos de pago (REP)

## A) ROL Y OBJETIVO

Actúa como desarrollador senior full-stack en el Portal de Proveedores (Módulo de Compras) de TotalGas, con experiencia en reporting financiero para Contabilidad, Contraloría y Tesorería en México (CFDI 4.0, SAT, REPSE).

**Objetivo medible:** Controlar por cada pago aplicado a una factura PPD si el proveedor emitió el REP en plazo, conciliarlo contra el pago real y escalar automáticamente el semáforo del proveedor al vencer.

### Contexto técnico del proyecto
- Aplicación: Portal de Proveedores / Módulo de Compras de TotalGas (grupo gasolinero con 36 estaciones, Cd. Juárez, Chih.). Ya cubre requisiciones, cotizaciones, órdenes de compra, recepciones, control presupuestal y validación CFDI/SAT (RFC, listado 69-B). Cada centro de costo ya tiene responsable asignado.
- Stack: Laravel 12, PHP 8.3, SQL Server 2019, Blade + Bootstrap 5 + jQuery. Si el repositorio ya usa Livewire, DataTables, Laravel Excel u OpenSpout, spatie/laravel-permission o dompdf/snappy, REUTILÍZALOS. No agregues dependencias nuevas sin justificarlas en el plan.
- Zona horaria de negocio: `America/Ciudad_Juarez`. Toda "fecha de corte" se interpreta a las 23:59:59 de esa zona.
- Moneda funcional: MXN. Los nombres de tablas y campos de este documento son SUPUESTOS: el esquema real del repositorio manda.

### Reglas de trabajo (obligatorias)
1. Antes de escribir código, inspecciona migraciones, modelos Eloquent, vistas/funciones SQL, servicios y jobs existentes. Construye una tabla de mapeo: `campo requerido → tabla.campo real | NO EXISTE`.
2. No crees tablas paralelas para datos que ya existen con otro nombre. Si falta un dato, propón en el plan la migración mínima y aditiva, y espera aprobación.
3. Si un prerrequisito no existe, NO simules el dato (ni ceros, ni NULL silenciosos, ni valores fijos). Repórtalo y separa en el plan "lo que se puede entregar hoy" de "lo que requiere construir el control primero". Un reporte que cuadra con datos falsos es peor que no tener reporte.
4. Montos en `DECIMAL(18,2)`; tipos de cambio en `DECIMAL(18,6)`; nunca `FLOAT`. Redondea a 2 decimales a nivel de renglón y después suma; usa el mismo criterio que los demás reportes con los que este debe cuadrar.
5. Consultas siempre parametrizadas (bindings). Prohibido concatenar filtros en SQL.
6. El reporte no consulta en línea los linked servers de las estaciones; usa las tablas ya consolidadas en el servidor del portal.
7. Una sola fuente de verdad por cálculo: si otro reporte o el motor de control usa el mismo cálculo, encapsúlalo (vista, función de tabla en línea o clase de servicio) y reutilízalo. No dupliques fórmulas.
8. No cambies reglas de negocio existentes salvo lo que este documento pida explícitamente. Si detectas un bug, repórtalo en el plan en lugar de corregirlo en silencio.
9. Reglas fiscales (tasas, claves SAT, plazos): van en catálogos parametrizables, nunca en código, y requieren confirmación de Contabilidad/Fiscal antes de liberarse.

### Paso 0: prerrequisitos que debes verificar antes de programar

- Pagos de Tesorería registrados (fecha, monto, forma de pago, moneda) y aplicados a facturas PPD. Si Tesorería no registra pagos en el portal, este reporte no es posible.
- Carga y validación de REP (CFDI tipo P, complemento Pagos 2.0) con DoctoRelacionado.
- Semáforo de proveedores (RM-01) para aplicar el efecto.

Si alguno no existe, repórtalo en el plan con su impacto; no lo sustituyas con datos simulados.

## B) NOMBRE Y PROPÓSITO DEL REPORTE

- ID: `RF-05` · Dominio: D. Validación fiscal, IVA y complementos de pago · Fase: F2
- Nombre técnico: `rpt_payment_complements`
- Nombre de negocio: Control de complementos de pago (REP)
- Frecuencia: Semanal, con corte duro al inicio de cada mes
- Consumidor principal: Fiscal, Cuentas por pagar
- Granularidad (una fila =): Una fila por pago aplicado a una factura con método PPD.

**Pregunta de negocio que responde / decisión que habilita:** El control del IVA que todavía no puedes acreditar y la lista exacta de a quién reclamárselo. El plazo legal para que el proveedor emita el complemento vence el quinto día natural del mes siguiente a aquel en que se recibió el pago.

**Requerimiento original de Contabilidad y Finanzas (campos mínimos):**

  • UUID de la factura · Fecha y monto del pago · Número de parcialidad · Saldo insoluto
  • UUID del REP · Fecha de emisión del REP · Días transcurridos · Estatus (en plazo / por vencer / vencido)
  • Diferencia entre lo pagado por Tesorería y lo declarado en el REP
  • Efecto en el semáforo del proveedor

## C) COLUMNAS / CAMPOS

Origen = SUPUESTO de nombre; mapea al esquema real en el Paso 0. O = obligatoria, Op = opcional.

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| invoice_uuid | CHAR(36) | invoices |  | texto | O |
| supplier_name | NVARCHAR | suppliers |  | texto | O |
| payment_date / payment_amount / payment_currency / payment_form | varios | payments |  | fecha / moneda | O |
| installment_no | INT | REP NumParcialidad |  | número | O si hay REP |
| previous_balance / paid_amount / outstanding_balance | DECIMAL(18,2) | REP ImpSaldoAnt / ImpPagado / ImpSaldoInsoluto |  | moneda | O si hay REP |
| rep_uuid / rep_issue_date | CHAR(36) / DATE | payment_complements |  | texto / fecha | O si hay REP |
| due_date | DATE | calculada | día 5 natural del mes siguiente al mes del pago (parametrizable) | fecha | O |
| days_elapsed | INT | calculada | DATEDIFF(day, payment_date, COALESCE(rep_received_at, hoy)) | número | O |
| status | VARCHAR(15) | calculada | RECIBIDO / RECIBIDO_FUERA_PLAZO / EN_PLAZO / POR_VENCER (≤ N días) / VENCIDO | badge | O |
| amount_diff | DECIMAL(18,2) | calculada | payment_amount − ImpPagado del REP | moneda | O si hay REP |
| date_diff / form_diff / currency_diff | BIT | calculada | FechaPago, FormaDePagoP, MonedaP del REP ≠ pago real | ícono | O si hay REP |
| supplier_light_effect | VARCHAR(10) | RM-01 | color resultante por esta regla | badge | O |

## D) FILTROS Y PARÁMETROS

**Filtros fijos (siempre aplicados):**

- Solo pagos aplicados a facturas con MetodoPago = PPD.

**Parámetros de entrada (según el Excel: Vencidos, por vencer, proveedor, mes de pago, empresa.):**

| Parámetro | Tipo | Valor por defecto | O/Op |
|---|---|---|---|
| status | multi-enum | VENCIDO + POR_VENCER | Op |
| supplier_id | int | todos | Op |
| payment_month | yyyy-mm | mes anterior | O |
| company_ids | array<int> | empresas del usuario | O |
| due_soon_days | int | 3 | Op |

## E) AGRUPACIONES, ORDEN Y GRANULARIDAD

- Una fila por pago aplicado a factura PPD.
- ORDER BY status (vencidos primero), due_date.
- Resumen por proveedor: # pagos sin REP e IVA no acreditable por esa causa.

## F) REGLAS DE NEGOCIO Y CÁLCULOS

- Plazo: el complemento debe emitirse a más tardar el quinto día natural del mes siguiente al pago (dato del requerimiento; Fiscal confirma y queda en parámetro).
- Conciliación: un REP concilia si coinciden monto (con tolerancia de redondeo de $0.01 × parcialidades), fecha de pago, forma de pago y moneda; si no, CON_DIFERENCIA y se notifica.
- Corte duro: job el día 6 de cada mes a las 00:05 que marca VENCIDO todo lo pendiente del mes anterior y escala el semáforo en RM-01 sin intervención manual.
- El IVA de pagos sin REP alimenta vat_pending_ppd de RF-03 (misma consulta).

## G) FORMATO DE SALIDA

- Tarjetas: pagos sin REP, IVA en riesgo, # vencidos, # proveedores escalados este mes.
- Tabla con acción 'Reenviar recordatorio al proveedor'.
- Vista del proveedor: sus pagos pendientes de REP.

Convenciones comunes de salida:
- Pantalla: tabla con DataTables en modo server-side (paginación en servidor de 25/50/100 filas), encabezado fijo y primera columna congelada, panel de filtros arriba con los parámetros de la sección D y botón "Limpiar filtros".
- Excel (.xlsx) con celdas tipadas de verdad (moneda `$#,##0.00`, fechas `dd/mm/yyyy`, porcentaje `0.0%`), nunca números como texto; encabezado con nombre del reporte, empresa(s), filtros aplicados, fecha/hora de generación y usuario. Escritura en streaming (chunks), sin cargar todo en memoria.
- CSV UTF-8 con BOM y separador coma.
- Exportaciones de más de 50 000 filas se encolan como job y se notifica al usuario cuando el archivo está listo.
- La exportación respeta exactamente los filtros activos en pantalla.

## H) CONSIDERACIONES TÉCNICAS

**Tablas/vistas probables (SUPUESTO):** payments, payment_applications, invoices, payment_complements, payment_complement_documents, suppliers, supplier_compliance (RM-01).

**Índices recomendados (valida contra los existentes antes de crear):**

- payment_applications (invoice_id, payment_id).
- payment_complement_documents (related_invoice_uuid).

**Seguridad y permisos:**

- Permisos granulares: `reportes.payment_complements.ver` y `reportes.payment_complements.exportar` (crea los permisos con el mecanismo que ya use el repo).
- Alcance por empresa: el usuario solo ve las empresas que tiene asignadas; el filtro se aplica en el servidor (scope/policy), nunca solo en la UI.
- Cada exportación se registra en bitácora: usuario, fecha/hora, filtros, número de filas.

- Proveedor: solo sus pagos.

**Endpoints y componentes:**

- GET /reportes/fiscal/complementos-pago (+ /data, /export).
- Job `RepDeadlineJob` (día 6).

## I) CRITERIOS DE ACEPTACIÓN (Definition of Done)

Criterio de negocio original: _El sistema concilia el REP contra el pago real (monto, fecha, forma de pago, moneda) y escala el semáforo automáticamente al vencer el plazo, sin intervención manual._

- [ ] El REP se concilia automáticamente contra el pago real (monto, fecha, forma, moneda).
- [ ] Al vencer el plazo el semáforo escala sin intervención manual (prueba con fecha simulada).
- [ ] El IVA pendiente por falta de REP es idéntico en este reporte y en RF-03.
- [ ] Todos los filtros de la sección D funcionan combinados y la exportación reproduce exactamente la vista filtrada.
- [ ] Usuarios sin permiso reciben 403 y ningún usuario ve empresas fuera de su alcance.
- [ ] La consulta principal responde en menos de 3 s con el volumen de un año de operación (o se justifica el snapshot/job).

## J) ENTREGABLE ESPERADO

Trabaja en tres entregas y DETENTE al terminar la primera:

1. **PLAN (sin código).**
   - Tabla de mapeo de esquema (campo requerido → tabla.campo real | NO EXISTE).
   - Estado de cada prerrequisito del Paso 0: existe / parcial / no existe, con su impacto en el reporte.
   - Arquitectura: archivos a crear o modificar (migraciones, vista o función SQL, clase de servicio del reporte, FormRequest de parámetros, controlador, rutas, vista Blade/JS, clase de exportación, policy/permisos, jobs y scheduler si aplica).
   - SQL propuesto (borrador) de la consulta principal.
   - Preguntas abiertas para Contraloría/Contabilidad/Fiscal, cada una con el supuesto que aplicarás si no hay respuesta.
   - Espera mi aprobación antes de continuar.
2. **CÓDIGO.** Implementa exactamente lo aprobado: migraciones aditivas, consulta encapsulada, servicio, endpoint, vista, exportaciones, permisos, jobs. Commits pequeños y descriptivos.
3. **PRUEBAS Y VALIDACIÓN.**
   - Pruebas de feature (Pest o PHPUnit, lo que use el repo) con datos semilla que ejerzan cada regla de negocio y cada criterio de aceptación de la sección I.
   - Pruebas de permisos: sin permiso → 403; alcance por empresa/proveedor respetado.
   - Script SQL de verificación que demuestre las identidades de cuadre del reporte.
   - Plan de ejecución en SQL Server con volumen realista; reporta tiempos y los índices creados.
   - Lista explícita de lo que quedó pendiente o fuera de alcance.

------------------------------------------------

================================================
REPORTE #17: RF-06 · CFDI de egreso, anticipos y ajustes
================================================

# REPORTE RF-06: CFDI de egreso, anticipos y ajustes

## A) ROL Y OBJETIVO

Actúa como desarrollador senior full-stack en el Portal de Proveedores (Módulo de Compras) de TotalGas, con experiencia en reporting financiero para Contabilidad, Contraloría y Tesorería en México (CFDI 4.0, SAT, REPSE).

**Objetivo medible:** Controlar notas de crédito y anticipos con saldo rastreable, y garantizar que cada egreso ajuste simultáneamente la cuenta por pagar, la póliza y el consumo presupuestal.

### Contexto técnico del proyecto
- Aplicación: Portal de Proveedores / Módulo de Compras de TotalGas (grupo gasolinero con 36 estaciones, Cd. Juárez, Chih.). Ya cubre requisiciones, cotizaciones, órdenes de compra, recepciones, control presupuestal y validación CFDI/SAT (RFC, listado 69-B). Cada centro de costo ya tiene responsable asignado.
- Stack: Laravel 12, PHP 8.3, SQL Server 2019, Blade + Bootstrap 5 + jQuery. Si el repositorio ya usa Livewire, DataTables, Laravel Excel u OpenSpout, spatie/laravel-permission o dompdf/snappy, REUTILÍZALOS. No agregues dependencias nuevas sin justificarlas en el plan.
- Zona horaria de negocio: `America/Ciudad_Juarez`. Toda "fecha de corte" se interpreta a las 23:59:59 de esa zona.
- Moneda funcional: MXN. Los nombres de tablas y campos de este documento son SUPUESTOS: el esquema real del repositorio manda.

### Reglas de trabajo (obligatorias)
1. Antes de escribir código, inspecciona migraciones, modelos Eloquent, vistas/funciones SQL, servicios y jobs existentes. Construye una tabla de mapeo: `campo requerido → tabla.campo real | NO EXISTE`.
2. No crees tablas paralelas para datos que ya existen con otro nombre. Si falta un dato, propón en el plan la migración mínima y aditiva, y espera aprobación.
3. Si un prerrequisito no existe, NO simules el dato (ni ceros, ni NULL silenciosos, ni valores fijos). Repórtalo y separa en el plan "lo que se puede entregar hoy" de "lo que requiere construir el control primero". Un reporte que cuadra con datos falsos es peor que no tener reporte.
4. Montos en `DECIMAL(18,2)`; tipos de cambio en `DECIMAL(18,6)`; nunca `FLOAT`. Redondea a 2 decimales a nivel de renglón y después suma; usa el mismo criterio que los demás reportes con los que este debe cuadrar.
5. Consultas siempre parametrizadas (bindings). Prohibido concatenar filtros en SQL.
6. El reporte no consulta en línea los linked servers de las estaciones; usa las tablas ya consolidadas en el servidor del portal.
7. Una sola fuente de verdad por cálculo: si otro reporte o el motor de control usa el mismo cálculo, encapsúlalo (vista, función de tabla en línea o clase de servicio) y reutilízalo. No dupliques fórmulas.
8. No cambies reglas de negocio existentes salvo lo que este documento pida explícitamente. Si detectas un bug, repórtalo en el plan en lugar de corregirlo en silencio.
9. Reglas fiscales (tasas, claves SAT, plazos): van en catálogos parametrizables, nunca en código, y requieren confirmación de Contabilidad/Fiscal antes de liberarse.

### Paso 0: prerrequisitos que debes verificar antes de programar

- Carga de CFDI tipo E con CfdiRelacionados y TipoRelacion.
- Registro de anticipos entregados (pago sin factura previa o CFDI de anticipo) con saldo.
- Procedimiento de anticipos que usa la empresa según la guía de llenado del SAT (Fiscal confirma cuál).
- Pólizas (RK-01) y libro mayor presupuestal (RP-01) para registrar los efectos.

Si alguno no existe, repórtalo en el plan con su impacto; no lo sustituyas con datos simulados.

## B) NOMBRE Y PROPÓSITO DEL REPORTE

- ID: `RF-06` · Dominio: D. Validación fiscal, IVA y complementos de pago · Fase: F2
- Nombre técnico: `rpt_credit_notes_advances`
- Nombre de negocio: CFDI de egreso, anticipos y ajustes
- Frecuencia: Mensual
- Consumidor principal: Cuentas por pagar, Contabilidad
- Granularidad (una fila =): Una fila por nota de crédito o por anticipo entregado.

**Pregunta de negocio que responde / decisión que habilita:** El control del dinero entregado por adelantado y de los saldos a favor. Los anticipos sin aplicar son el punto ciego más común: se pagan, se contabilizan y nadie los persigue hasta que aparecen en una conciliación un año después.

**Requerimiento original de Contabilidad y Finanzas (campos mínimos):**

  • UUID del egreso · UUID(s) relacionado(s) · Tipo de relación · Motivo (devolución, descuento, bonificación, aplicación de anticipo)
  • Importe · IVA · Saldo del anticipo antes y después
  • Efecto en la cuenta por pagar, en la póliza y en el consumo presupuestal

## C) COLUMNAS / CAMPOS

Origen = SUPUESTO de nombre; mapea al esquema real en el Paso 0. O = obligatoria, Op = opcional.

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| record_type | VARCHAR(15) | calculada | NOTA_CREDITO / ANTICIPO | badge | O |
| egress_uuid | CHAR(36) | invoices (tipo E) |  | texto | O |
| related_uuids | NVARCHAR(MAX) | cfdi_relations |  | texto | O |
| relation_type | CHAR(2) | cfdi_relations.type | clave c_TipoRelacion | texto | O |
| reason | VARCHAR(20) | calculada / captura | DEVOLUCION / DESCUENTO / BONIFICACION / APLICACION_ANTICIPO | texto | O |
| supplier_name | NVARCHAR | suppliers |  | texto | O |
| document_date | DATE |  |  | fecha | O |
| amount / vat | DECIMAL(18,2) | XML |  | moneda | O |
| advance_balance_before / applied / advance_balance_after | DECIMAL(18,2) | supplier_advances | after = before − applied | moneda | O en anticipos |
| advance_age_days | INT | calculada | días desde la entrega del anticipo | número | O en anticipos |
| ap_effect_posted / journal_entry_folio / budget_effect_posted | BIT / NVARCHAR / BIT | ledger | efecto aplicado en CxP, póliza y presupuesto | ícono / liga | O |

## D) FILTROS Y PARÁMETROS

**Filtros fijos (siempre aplicados):**

- Notas de crédito vigentes y anticipos con cualquier saldo.

**Parámetros de entrada (según el Excel: Tipo, proveedor, periodo, anticipos sin aplicar, antigüedad del anticipo.):**

| Parámetro | Tipo | Valor por defecto | O/Op |
|---|---|---|---|
| record_type | enum | ambos | Op |
| supplier_id | int | todos | Op |
| date_from / date_to | date | mes anterior | O |
| only_unapplied_advances | bool | false | Op |
| advance_age_greater_than | int | 0 | Op |
| company_ids | array<int> | empresas del usuario | O |

## E) AGRUPACIONES, ORDEN Y GRANULARIDAD

- Una fila por nota de crédito o por anticipo entregado.
- ORDER BY record_type, advance_age_days DESC.
- Totales: saldo de anticipos por aplicar por proveedor y por antigüedad (0-30, 31-90, >90).

## F) REGLAS DE NEGOCIO Y CÁLCULOS

- Aplicar una nota de crédito ejecuta en UNA transacción de base de datos: reducción de CxP, póliza y movimiento en el libro mayor presupuestal. Si cualquiera falla, rollback total.
- El saldo de un anticipo nunca puede ser negativo (CHECK constraint).
- Anticipos sin aplicar > 90 días se resaltan y alimentan RA-03.

## G) FORMATO DE SALIDA

- Tarjetas: saldo de anticipos sin aplicar, # anticipos > 90 días, notas de crédito del mes.
- Tabla con indicadores de los tres efectos (CxP, póliza, presupuesto).

Convenciones comunes de salida:
- Pantalla: tabla con DataTables en modo server-side (paginación en servidor de 25/50/100 filas), encabezado fijo y primera columna congelada, panel de filtros arriba con los parámetros de la sección D y botón "Limpiar filtros".
- Excel (.xlsx) con celdas tipadas de verdad (moneda `$#,##0.00`, fechas `dd/mm/yyyy`, porcentaje `0.0%`), nunca números como texto; encabezado con nombre del reporte, empresa(s), filtros aplicados, fecha/hora de generación y usuario. Escritura en streaming (chunks), sin cargar todo en memoria.
- CSV UTF-8 con BOM y separador coma.
- Exportaciones de más de 50 000 filas se encolan como job y se notifica al usuario cuando el archivo está listo.
- La exportación respeta exactamente los filtros activos en pantalla.

## H) CONSIDERACIONES TÉCNICAS

**Tablas/vistas probables (SUPUESTO):** invoices, cfdi_relations, supplier_advances (nueva si no existe), advance_applications, journal_entries (RK-01), budget_ledger_entries (RP-01).

**Índices recomendados (valida contra los existentes antes de crear):**

- cfdi_relations (related_uuid).
- supplier_advances (supplier_id) WHERE balance > 0.

**Seguridad y permisos:**

- Permisos granulares: `reportes.credit_notes_advances.ver` y `reportes.credit_notes_advances.exportar` (crea los permisos con el mecanismo que ya use el repo).
- Alcance por empresa: el usuario solo ve las empresas que tiene asignadas; el filtro se aplica en el servidor (scope/policy), nunca solo en la UI.
- Cada exportación se registra en bitácora: usuario, fecha/hora, filtros, número de filas.

- Cuentas por pagar y Contabilidad.

**Endpoints y componentes:**

- GET /reportes/cxp/egresos-anticipos (+ /data, /export).

## I) CRITERIOS DE ACEPTACIÓN (Definition of Done)

Criterio de negocio original: _Ningún anticipo queda sin saldo rastreable. Toda nota de crédito ajusta simultáneamente la cuenta por pagar, la póliza y el consumo presupuestal._

- [ ] Ningún anticipo sin saldo rastreable (Σ entregado − Σ aplicado = saldo, por anticipo).
- [ ] Prueba: aplicar una nota de crédito afecta CxP, póliza y presupuesto; forzar un error en cualquiera deja los tres sin cambio.
- [ ] Todos los filtros de la sección D funcionan combinados y la exportación reproduce exactamente la vista filtrada.
- [ ] Usuarios sin permiso reciben 403 y ningún usuario ve empresas fuera de su alcance.
- [ ] La consulta principal responde en menos de 3 s con el volumen de un año de operación (o se justifica el snapshot/job).

**Preguntas abiertas (aplica el SUPUESTO indicado si no hay respuesta):**

- ¿Qué procedimiento de anticipos usa la empresa? SUPUESTO: CFDI de anticipo + factura con relación 07 + egreso de aplicación; Fiscal confirma.

## J) ENTREGABLE ESPERADO

Trabaja en tres entregas y DETENTE al terminar la primera:

1. **PLAN (sin código).**
   - Tabla de mapeo de esquema (campo requerido → tabla.campo real | NO EXISTE).
   - Estado de cada prerrequisito del Paso 0: existe / parcial / no existe, con su impacto en el reporte.
   - Arquitectura: archivos a crear o modificar (migraciones, vista o función SQL, clase de servicio del reporte, FormRequest de parámetros, controlador, rutas, vista Blade/JS, clase de exportación, policy/permisos, jobs y scheduler si aplica).
   - SQL propuesto (borrador) de la consulta principal.
   - Preguntas abiertas para Contraloría/Contabilidad/Fiscal, cada una con el supuesto que aplicarás si no hay respuesta.
   - Espera mi aprobación antes de continuar.
2. **CÓDIGO.** Implementa exactamente lo aprobado: migraciones aditivas, consulta encapsulada, servicio, endpoint, vista, exportaciones, permisos, jobs. Commits pequeños y descriptivos.
3. **PRUEBAS Y VALIDACIÓN.**
   - Pruebas de feature (Pest o PHPUnit, lo que use el repo) con datos semilla que ejerzan cada regla de negocio y cada criterio de aceptación de la sección I.
   - Pruebas de permisos: sin permiso → 403; alcance por empresa/proveedor respetado.
   - Script SQL de verificación que demuestre las identidades de cuadre del reporte.
   - Plan de ejecución en SQL Server con volumen realista; reporta tiempos y los índices creados.
   - Lista explícita de lo que quedó pendiente o fuera de alcance.

------------------------------------------------

================================================
REPORTE #18: RF-07 · Conciliación del portal contra el repositorio del SAT
================================================

# REPORTE RF-07: Conciliación del portal contra el repositorio del SAT

## A) ROL Y OBJETIVO

Actúa como desarrollador senior full-stack en el Portal de Proveedores (Módulo de Compras) de TotalGas, con experiencia en reporting financiero para Contabilidad, Contraloría y Tesorería en México (CFDI 4.0, SAT, REPSE).

**Objetivo medible:** Conciliar mensualmente los CFDI recibidos según la descarga masiva del SAT contra lo registrado en el portal, clasificando el 100 % de las diferencias sin categoría 'otros' y cuantificando el importe expuesto.

### Contexto técnico del proyecto
- Aplicación: Portal de Proveedores / Módulo de Compras de TotalGas (grupo gasolinero con 36 estaciones, Cd. Juárez, Chih.). Ya cubre requisiciones, cotizaciones, órdenes de compra, recepciones, control presupuestal y validación CFDI/SAT (RFC, listado 69-B). Cada centro de costo ya tiene responsable asignado.
- Stack: Laravel 12, PHP 8.3, SQL Server 2019, Blade + Bootstrap 5 + jQuery. Si el repositorio ya usa Livewire, DataTables, Laravel Excel u OpenSpout, spatie/laravel-permission o dompdf/snappy, REUTILÍZALOS. No agregues dependencias nuevas sin justificarlas en el plan.
- Zona horaria de negocio: `America/Ciudad_Juarez`. Toda "fecha de corte" se interpreta a las 23:59:59 de esa zona.
- Moneda funcional: MXN. Los nombres de tablas y campos de este documento son SUPUESTOS: el esquema real del repositorio manda.

### Reglas de trabajo (obligatorias)
1. Antes de escribir código, inspecciona migraciones, modelos Eloquent, vistas/funciones SQL, servicios y jobs existentes. Construye una tabla de mapeo: `campo requerido → tabla.campo real | NO EXISTE`.
2. No crees tablas paralelas para datos que ya existen con otro nombre. Si falta un dato, propón en el plan la migración mínima y aditiva, y espera aprobación.
3. Si un prerrequisito no existe, NO simules el dato (ni ceros, ni NULL silenciosos, ni valores fijos). Repórtalo y separa en el plan "lo que se puede entregar hoy" de "lo que requiere construir el control primero". Un reporte que cuadra con datos falsos es peor que no tener reporte.
4. Montos en `DECIMAL(18,2)`; tipos de cambio en `DECIMAL(18,6)`; nunca `FLOAT`. Redondea a 2 decimales a nivel de renglón y después suma; usa el mismo criterio que los demás reportes con los que este debe cuadrar.
5. Consultas siempre parametrizadas (bindings). Prohibido concatenar filtros en SQL.
6. El reporte no consulta en línea los linked servers de las estaciones; usa las tablas ya consolidadas en el servidor del portal.
7. Una sola fuente de verdad por cálculo: si otro reporte o el motor de control usa el mismo cálculo, encapsúlalo (vista, función de tabla en línea o clase de servicio) y reutilízalo. No dupliques fórmulas.
8. No cambies reglas de negocio existentes salvo lo que este documento pida explícitamente. Si detectas un bug, repórtalo en el plan en lugar de corregirlo en silencio.
9. Reglas fiscales (tasas, claves SAT, plazos): van en catálogos parametrizables, nunca en código, y requieren confirmación de Contabilidad/Fiscal antes de liberarse.

### Paso 0: prerrequisitos que debes verificar antes de programar

- Descarga masiva de CFDI recibidos vía web service del SAT con la e.firma de CADA empresa. Decisión crítica de seguridad: dónde y cómo se custodia la e.firma (cifrada, fuera del webroot, acceso restringido). Sin esta decisión no se construye.
- Consulta de estatus (vigente/cancelado) y fecha de cancelación.
- Etapa de cada factura en el portal (registrada, provisionada, pagada).

Si alguno no existe, repórtalo en el plan con su impacto; no lo sustituyas con datos simulados.

## B) NOMBRE Y PROPÓSITO DEL REPORTE

- ID: `RF-07` · Dominio: D. Validación fiscal, IVA y complementos de pago · Fase: F3
- Nombre técnico: `rpt_sat_reconciliation`
- Nombre de negocio: Conciliación del portal contra el repositorio del SAT
- Frecuencia: Mensual
- Consumidor principal: Fiscal, Contraloría
- Granularidad (una fila =): Una fila por UUID con diferencia entre lo descargado del SAT y lo registrado en el portal.

**Pregunta de negocio que responde / decisión que habilita:** Certeza de que lo deducido efectivamente existe y sigue vigente. Detecta la factura que el proveedor canceló después de que ya se provisionó o se pagó, que suele ser el hallazgo más caro de una revisión.

**Requerimiento original de Contabilidad y Finanzas (campos mínimos):**

  • UUID · RFC emisor · Fecha · Total · Estatus en el SAT · Estatus en el portal
  • Tipo de diferencia: en el SAT y no en el portal, en el portal y no en el SAT, cancelado después de provisionar, cancelado después de pagar, duplicado
  • Importe expuesto · Acción sugerida · Responsable

## C) COLUMNAS / CAMPOS

Origen = SUPUESTO de nombre; mapea al esquema real en el Paso 0. O = obligatoria, Op = opcional.

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| uuid | CHAR(36) | sat_downloads / invoices |  | texto | O |
| issuer_rfc / issuer_name | NVARCHAR | metadata SAT |  | texto | O |
| receiver_company | NVARCHAR | companies |  | texto | O |
| cfdi_date / total | DATE / DECIMAL(18,2) | metadata SAT |  | fecha / moneda | O |
| sat_status / sat_cancelled_at | VARCHAR / DATETIME2 | metadata SAT |  | texto / fecha | O |
| portal_status / portal_stage | VARCHAR | invoices | REGISTRADA / PROVISIONADA / PAGADA | texto | O |
| difference_type | VARCHAR(25) | calculada | SAT_NO_PORTAL / PORTAL_NO_SAT / CANCELADO_POST_PROVISION / CANCELADO_POST_PAGO / DUPLICADO / FUERA_DE_ALCANCE (con regla) | badge | O |
| exposed_amount | DECIMAL(18,2) | calculada | importe en riesgo (IVA acreditado + gasto deducido o pagado) | moneda | O |
| suggested_action | NVARCHAR | catálogo por tipo |  | texto | O |
| responsible | NVARCHAR | users |  | texto | O |

## D) FILTROS Y PARÁMETROS

**Filtros fijos (siempre aplicados):**

- CFDI recibidos del mes por las empresas del grupo.

**Parámetros de entrada (según el Excel: Tipo de diferencia, empresa, mes, importe.):**

| Parámetro | Tipo | Valor por defecto | O/Op |
|---|---|---|---|
| difference_type | multi | todos | Op |
| company_ids | array<int> | empresas del usuario | O |
| year_month | yyyy-mm | mes anterior | O |
| amount_greater_than | decimal | 0 | Op |

## E) AGRUPACIONES, ORDEN Y GRANULARIDAD

- Una fila por UUID con diferencia.
- ORDER BY exposed_amount DESC.
- Resumen por tipo de diferencia: conteo e importe.

## F) REGLAS DE NEGOCIO Y CÁLCULOS

- Riesgo real: el grupo recibe muchos CFDI que no pasan por el portal (p. ej. compras de combustible, servicios fijos). Sin reglas explícitas de exclusión, 'SAT_NO_PORTAL' será enorme e inútil. Define un catálogo de reglas de alcance (por RFC emisor, uso CFDI, tipo) y clasifica esos casos como FUERA_DE_ALCANCE con la regla que aplicó; eso sí es una clasificación, no un 'otros'.
- Precedencia de clasificación (un UUID recibe un solo tipo): DUPLICADO > CANCELADO_POST_PAGO > CANCELADO_POST_PROVISION > PORTAL_NO_SAT > SAT_NO_PORTAL > FUERA_DE_ALCANCE.
- Si un UUID no encaja en ninguna regla, el job falla y alerta; nunca lo manda a una categoría genérica.
- Se conservan los paquetes descargados con su hash como evidencia.

## G) FORMATO DE SALIDA

- Tarjetas por tipo con importe expuesto.
- Tabla con acción 'Asignar responsable' y ligas al documento del portal.
- Excel para Fiscal.

Convenciones comunes de salida:
- Pantalla: tabla con DataTables en modo server-side (paginación en servidor de 25/50/100 filas), encabezado fijo y primera columna congelada, panel de filtros arriba con los parámetros de la sección D y botón "Limpiar filtros".
- Excel (.xlsx) con celdas tipadas de verdad (moneda `$#,##0.00`, fechas `dd/mm/yyyy`, porcentaje `0.0%`), nunca números como texto; encabezado con nombre del reporte, empresa(s), filtros aplicados, fecha/hora de generación y usuario. Escritura en streaming (chunks), sin cargar todo en memoria.
- CSV UTF-8 con BOM y separador coma.
- Exportaciones de más de 50 000 filas se encolan como job y se notifica al usuario cuando el archivo está listo.
- La exportación respeta exactamente los filtros activos en pantalla.

## H) CONSIDERACIONES TÉCNICAS

**Tablas/vistas probables (SUPUESTO):** sat_download_requests, sat_downloaded_cfdi (nuevas), invoices, payments, scope_rules (nueva), companies.

**Índices recomendados (valida contra los existentes antes de crear):**

- sat_downloaded_cfdi UNIQUE (uuid).
- sat_downloaded_cfdi (receiver_rfc, cfdi_date).

**Seguridad y permisos:**

- Permisos granulares: `reportes.sat_reconciliation.ver` y `reportes.sat_reconciliation.exportar` (crea los permisos con el mecanismo que ya use el repo).
- Alcance por empresa: el usuario solo ve las empresas que tiene asignadas; el filtro se aplica en el servidor (scope/policy), nunca solo en la UI.
- Cada exportación se registra en bitácora: usuario, fecha/hora, filtros, número de filas.

- Solo Fiscal y Contraloría. Acceso a la e.firma: ningún usuario de la app; solo el job con credenciales del servidor.

**Endpoints y componentes:**

- GET /reportes/fiscal/conciliacion-sat (+ /data, /export).
- Job mensual de solicitud y descarga (asíncrono: solicitud → verificación → descarga de paquetes).

## I) CRITERIOS DE ACEPTACIÓN (Definition of Done)

Criterio de negocio original: _La conciliación corre sobre la descarga masiva de comprobantes recibidos del periodo y clasifica el 100 % de las diferencias. No se admite una categoría "otros"._

- [ ] El 100 % de las diferencias del mes tiene un tipo; no existe la categoría 'otros'.
- [ ] La conciliación corre sobre la descarga masiva del periodo y conserva los paquetes con hash.
- [ ] Una factura cancelada después de pagada aparece como CANCELADO_POST_PAGO con su importe expuesto.
- [ ] Todos los filtros de la sección D funcionan combinados y la exportación reproduce exactamente la vista filtrada.
- [ ] Usuarios sin permiso reciben 403 y ningún usuario ve empresas fuera de su alcance.
- [ ] La consulta principal responde en menos de 3 s con el volumen de un año de operación (o se justifica el snapshot/job).

**Preguntas abiertas (aplica el SUPUESTO indicado si no hay respuesta):**

- ¿Qué emisores/tipos quedan fuera del alcance del portal? Sin esta lista el reporte no es usable.

## J) ENTREGABLE ESPERADO

Trabaja en tres entregas y DETENTE al terminar la primera:

1. **PLAN (sin código).**
   - Tabla de mapeo de esquema (campo requerido → tabla.campo real | NO EXISTE).
   - Estado de cada prerrequisito del Paso 0: existe / parcial / no existe, con su impacto en el reporte.
   - Arquitectura: archivos a crear o modificar (migraciones, vista o función SQL, clase de servicio del reporte, FormRequest de parámetros, controlador, rutas, vista Blade/JS, clase de exportación, policy/permisos, jobs y scheduler si aplica).
   - SQL propuesto (borrador) de la consulta principal.
   - Preguntas abiertas para Contraloría/Contabilidad/Fiscal, cada una con el supuesto que aplicarás si no hay respuesta.
   - Espera mi aprobación antes de continuar.
2. **CÓDIGO.** Implementa exactamente lo aprobado: migraciones aditivas, consulta encapsulada, servicio, endpoint, vista, exportaciones, permisos, jobs. Commits pequeños y descriptivos.
3. **PRUEBAS Y VALIDACIÓN.**
   - Pruebas de feature (Pest o PHPUnit, lo que use el repo) con datos semilla que ejerzan cada regla de negocio y cada criterio de aceptación de la sección I.
   - Pruebas de permisos: sin permiso → 403; alcance por empresa/proveedor respetado.
   - Script SQL de verificación que demuestre las identidades de cuadre del reporte.
   - Plan de ejecución en SQL Server con volumen realista; reporta tiempos y los índices creados.
   - Lista explícita de lo que quedó pendiente o fuera de alcance.

------------------------------------------------

================================================
REPORTE #19: RT-01 · Programación semanal de pagos por vencimiento
================================================

# REPORTE RT-01: Programación semanal de pagos por vencimiento

## A) ROL Y OBJETIVO

Actúa como desarrollador senior full-stack en el Portal de Proveedores (Módulo de Compras) de TotalGas, con experiencia en reporting financiero para Contabilidad, Contraloría y Tesorería en México (CFDI 4.0, SAT, REPSE).

**Objetivo medible:** Generar la propuesta semanal de pagos por vencimiento, lista para autorizar y para producir el layout bancario, con la garantía verificable de que ningún documento con bloqueo activo aparece como propuesto y que el total autorizado = layout = acuse de dispersión.

### Contexto técnico del proyecto
- Aplicación: Portal de Proveedores / Módulo de Compras de TotalGas (grupo gasolinero con 36 estaciones, Cd. Juárez, Chih.). Ya cubre requisiciones, cotizaciones, órdenes de compra, recepciones, control presupuestal y validación CFDI/SAT (RFC, listado 69-B). Cada centro de costo ya tiene responsable asignado.
- Stack: Laravel 12, PHP 8.3, SQL Server 2019, Blade + Bootstrap 5 + jQuery. Si el repositorio ya usa Livewire, DataTables, Laravel Excel u OpenSpout, spatie/laravel-permission o dompdf/snappy, REUTILÍZALOS. No agregues dependencias nuevas sin justificarlas en el plan.
- Zona horaria de negocio: `America/Ciudad_Juarez`. Toda "fecha de corte" se interpreta a las 23:59:59 de esa zona.
- Moneda funcional: MXN. Los nombres de tablas y campos de este documento son SUPUESTOS: el esquema real del repositorio manda.

### Reglas de trabajo (obligatorias)
1. Antes de escribir código, inspecciona migraciones, modelos Eloquent, vistas/funciones SQL, servicios y jobs existentes. Construye una tabla de mapeo: `campo requerido → tabla.campo real | NO EXISTE`.
2. No crees tablas paralelas para datos que ya existen con otro nombre. Si falta un dato, propón en el plan la migración mínima y aditiva, y espera aprobación.
3. Si un prerrequisito no existe, NO simules el dato (ni ceros, ni NULL silenciosos, ni valores fijos). Repórtalo y separa en el plan "lo que se puede entregar hoy" de "lo que requiere construir el control primero". Un reporte que cuadra con datos falsos es peor que no tener reporte.
4. Montos en `DECIMAL(18,2)`; tipos de cambio en `DECIMAL(18,6)`; nunca `FLOAT`. Redondea a 2 decimales a nivel de renglón y después suma; usa el mismo criterio que los demás reportes con los que este debe cuadrar.
5. Consultas siempre parametrizadas (bindings). Prohibido concatenar filtros en SQL.
6. El reporte no consulta en línea los linked servers de las estaciones; usa las tablas ya consolidadas en el servidor del portal.
7. Una sola fuente de verdad por cálculo: si otro reporte o el motor de control usa el mismo cálculo, encapsúlalo (vista, función de tabla en línea o clase de servicio) y reutilízalo. No dupliques fórmulas.
8. No cambies reglas de negocio existentes salvo lo que este documento pida explícitamente. Si detectas un bug, repórtalo en el plan en lugar de corregirlo en silencio.
9. Reglas fiscales (tasas, claves SAT, plazos): van en catálogos parametrizables, nunca en código, y requieren confirmación de Contabilidad/Fiscal antes de liberarse.

### Paso 0: prerrequisitos que debes verificar antes de programar

- Cuentas por pagar con fecha de vencimiento (condiciones de crédito por proveedor).
- Cuentas bancarias de proveedores con CLABE validada (dígito verificador) y evidencia de titularidad.
- Una función ÚNICA de bloqueos por documento (fiscal, cotejo triple, semáforo, disputa, REP vencido, etc.). Si hoy los bloqueos están dispersos, el plan debe centralizarlos primero.
- Formato de layout de cada banco que usa Tesorería y forma de importar el acuse de dispersión.
- Nota: el semáforo RM-01 y el control de REP (RF-05) son de F2; en F1 solo aplican los bloqueos que ya existan.

Si alguno no existe, repórtalo en el plan con su impacto; no lo sustituyas con datos simulados.

## B) NOMBRE Y PROPÓSITO DEL REPORTE

- ID: `RT-01` · Dominio: E. Cuentas por pagar y tesorería · Fase: F1
- Nombre técnico: `rpt_weekly_payment_schedule`
- Nombre de negocio: Programación semanal de pagos por vencimiento
- Frecuencia: Semanal, con día y hora de corte fijos
- Consumidor principal: Tesorería, Dirección de Finanzas
- Granularidad (una fila =): Una fila por documento propuesto a pago, agrupado por semana, empresa y banco.

**Pregunta de negocio que responde / decisión que habilita:** La propuesta de pago lista para autorizar y para generar el layout bancario, con la garantía de que nada bloqueado se coló. Es el reporte que sustituye la negociación semanal por correo y hojas sueltas.

**Requerimiento original de Contabilidad y Finanzas (campos mínimos):**

  • Proveedor · Banco y CLABE validada · UUID · Folio de OC · Importe · Moneda
  • Fecha de vencimiento · Días de atraso o de anticipo · Prioridad · Descuento por pronto pago
  • Bloqueos activos y su causa · Totales por empresa, por banco y de la semana

## C) COLUMNAS / CAMPOS

Origen = SUPUESTO de nombre; mapea al esquema real en el Paso 0. O = obligatoria, Op = opcional.

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| week_label | CHAR(8) | calculada | año-semana ISO del pago propuesto | texto | O |
| company_name | NVARCHAR | companies |  | texto | O |
| supplier_name / supplier_rfc | NVARCHAR | suppliers |  | texto | O |
| bank_name | NVARCHAR | supplier_bank_accounts |  | texto | O |
| clabe_masked | CHAR(18) | supplier_bank_accounts.clabe | en pantalla solo últimos 4; completa solo en el layout | texto | O |
| clabe_valid | BIT | calculada | 18 dígitos + dígito verificador (pesos 3,7,1 módulo 10) | ícono | O |
| uuid / po_folio | CHAR(36) / NVARCHAR | invoices / purchase_orders |  | texto | O |
| amount / currency / amount_mxn | DECIMAL / CHAR(3) | accounts_payable | saldo por pagar del documento | moneda | O |
| due_date | DATE | accounts_payable.due_date |  | fecha | O |
| days_to_due | INT | calculada | DATEDIFF(day, cut_date, due_date); negativo = atraso | número (rojo < 0) | O |
| priority | TINYINT | calculada / captura | 1 vencido, 2 vence en la semana, 3 con descuento pronto pago, 4 resto; ajustable por Tesorería con motivo | número | O |
| early_payment_discount / discount_deadline | DECIMAL / DATE | supplier_terms |  | moneda / fecha | Op |
| net_to_pay | DECIMAL(18,2) | calculada | amount − descuento aplicable si se paga antes del límite | moneda | O |
| active_blocks | NVARCHAR | fn_payable_blocks | lista de bloqueos y su causa | texto | O |
| proposal_status | VARCHAR(15) | payment_batch_items | PROPUESTO / AUTORIZADO / ENVIADO / DISPERSADO / RECHAZADO_BANCO | badge | O |
| batch_id | INT | payment_batches |  | número | O |

## D) FILTROS Y PARÁMETROS

**Filtros fijos (siempre aplicados):**

- Documentos con saldo > 0.
- Si el parámetro 'solo sin bloqueo' está activo (default), cero documentos con bloqueo.

**Parámetros de entrada (según el Excel: Semana, empresa, banco, proveedor, solo documentos sin bloqueo, rango de importe.):**

| Parámetro | Tipo | Valor por defecto | O/Op |
|---|---|---|---|
| week (ISO) | yyyy-Www | semana siguiente al corte | O |
| company_ids | array<int> | empresas del usuario | O |
| bank | multi | todos | Op |
| supplier_id | int | todos | Op |
| only_unblocked | bool | true | O |
| amount_from / amount_to | decimal | vacío | Op |

## E) AGRUPACIONES, ORDEN Y GRANULARIDAD

- Una fila por documento propuesto, agrupado por semana → empresa → banco.
- ORDER BY priority, due_date.
- Totales por empresa, por banco y de la semana.

## F) REGLAS DE NEGOCIO Y CÁLCULOS

- Corte fijo parametrizable (SUPUESTO: jueves 12:00 America/Ciudad_Juarez). Después del corte la propuesta de la semana se congela.
- `fn_payable_blocks(@ap_id)` es la única fuente de bloqueos; la usan el reporte, la autorización y el generador de layout. Al autorizar y al generar el layout se re-evalúa: si apareció un bloqueo, el documento sale del lote con motivo.
- Segregación: quien propone ≠ quien autoriza ≠ quien da de alta o cambia cuentas bancarias.
- Totales de control: Σ autorizado = Σ layout = Σ acuse; se guarda el hash SHA-256 del archivo de layout y el acuse importado.
- Cambio de CLABE de un proveedor en los últimos N días (default 5) → bloqueo automático hasta validación (control antifraude).

## G) FORMATO DE SALIDA

- Tarjetas: total propuesto, total bloqueado (fuera de la propuesta), descuentos por pronto pago disponibles.
- Tabla agrupada con checkboxes para autorizar por lote.
- Acciones: Autorizar, Generar layout, Importar acuse. Excel de la propuesta.

Convenciones comunes de salida:
- Pantalla: tabla con DataTables en modo server-side (paginación en servidor de 25/50/100 filas), encabezado fijo y primera columna congelada, panel de filtros arriba con los parámetros de la sección D y botón "Limpiar filtros".
- Excel (.xlsx) con celdas tipadas de verdad (moneda `$#,##0.00`, fechas `dd/mm/yyyy`, porcentaje `0.0%`), nunca números como texto; encabezado con nombre del reporte, empresa(s), filtros aplicados, fecha/hora de generación y usuario. Escritura en streaming (chunks), sin cargar todo en memoria.
- CSV UTF-8 con BOM y separador coma.
- Exportaciones de más de 50 000 filas se encolan como job y se notifica al usuario cuando el archivo está listo.
- La exportación respeta exactamente los filtros activos en pantalla.

## H) CONSIDERACIONES TÉCNICAS

**Tablas/vistas probables (SUPUESTO):** accounts_payable, invoices, suppliers, supplier_bank_accounts, supplier_terms, fn_payable_blocks (nueva), payment_batches, payment_batch_items (nuevas), bank_layouts.

**Índices recomendados (valida contra los existentes antes de crear):**

- accounts_payable (company_id, due_date) INCLUDE (balance, supplier_id) WHERE balance > 0.
- payment_batch_items (batch_id).

**Seguridad y permisos:**

- Permisos granulares: `reportes.weekly_payment_schedule.ver` y `reportes.weekly_payment_schedule.exportar` (crea los permisos con el mecanismo que ya use el repo).
- Alcance por empresa: el usuario solo ve las empresas que tiene asignadas; el filtro se aplica en el servidor (scope/policy), nunca solo en la UI.
- Cada exportación se registra en bitácora: usuario, fecha/hora, filtros, número de filas.

- Proponer: Cuentas por pagar. Autorizar: Dirección de Finanzas. Layout y acuse: Tesorería. CLABE completa visible solo para el rol que genera el layout.

**Endpoints y componentes:**

- GET /tesoreria/programacion-pagos (+ /data, /export).
- POST /tesoreria/lotes/{id}/autorizar | /layout | /acuse.

## I) CRITERIOS DE ACEPTACIÓN (Definition of Done)

Criterio de negocio original: _Una cuenta por pagar con cualquier bloqueo activo no puede aparecer como propuesta. El total autorizado coincide exactamente con el layout enviado al banco y con el acuse de dispersión._

- [ ] Prueba: un documento con cualquier bloqueo activo no aparece como propuesto ni puede entrar a un lote, aunque se manipule la petición.
- [ ] Σ autorizado = Σ layout = Σ acuse de dispersión, verificado automáticamente al importar el acuse.
- [ ] CLABE inválida o recién cambiada impide proponer el pago.
- [ ] Todos los filtros de la sección D funcionan combinados y la exportación reproduce exactamente la vista filtrada.
- [ ] Usuarios sin permiso reciben 403 y ningún usuario ve empresas fuera de su alcance.
- [ ] La consulta principal responde en menos de 3 s con el volumen de un año de operación (o se justifica el snapshot/job).

**Preguntas abiertas (aplica el SUPUESTO indicado si no hay respuesta):**

- ¿Bancos y formatos de layout? SUPUESTO: se implementa uno primero y el resto con la misma interfaz `BankLayoutGenerator`.

## J) ENTREGABLE ESPERADO

Trabaja en tres entregas y DETENTE al terminar la primera:

1. **PLAN (sin código).**
   - Tabla de mapeo de esquema (campo requerido → tabla.campo real | NO EXISTE).
   - Estado de cada prerrequisito del Paso 0: existe / parcial / no existe, con su impacto en el reporte.
   - Arquitectura: archivos a crear o modificar (migraciones, vista o función SQL, clase de servicio del reporte, FormRequest de parámetros, controlador, rutas, vista Blade/JS, clase de exportación, policy/permisos, jobs y scheduler si aplica).
   - SQL propuesto (borrador) de la consulta principal.
   - Preguntas abiertas para Contraloría/Contabilidad/Fiscal, cada una con el supuesto que aplicarás si no hay respuesta.
   - Espera mi aprobación antes de continuar.
2. **CÓDIGO.** Implementa exactamente lo aprobado: migraciones aditivas, consulta encapsulada, servicio, endpoint, vista, exportaciones, permisos, jobs. Commits pequeños y descriptivos.
3. **PRUEBAS Y VALIDACIÓN.**
   - Pruebas de feature (Pest o PHPUnit, lo que use el repo) con datos semilla que ejerzan cada regla de negocio y cada criterio de aceptación de la sección I.
   - Pruebas de permisos: sin permiso → 403; alcance por empresa/proveedor respetado.
   - Script SQL de verificación que demuestre las identidades de cuadre del reporte.
   - Plan de ejecución en SQL Server con volumen realista; reporta tiempos y los índices creados.
   - Lista explícita de lo que quedó pendiente o fuera de alcance.

------------------------------------------------

================================================
REPORTE #20: RT-02 · Antigüedad de saldos por proveedor
================================================

# REPORTE RT-02: Antigüedad de saldos por proveedor

## A) ROL Y OBJETIVO

Actúa como desarrollador senior full-stack en el Portal de Proveedores (Módulo de Compras) de TotalGas, con experiencia en reporting financiero para Contabilidad, Contraloría y Tesorería en México (CFDI 4.0, SAT, REPSE).

**Objetivo medible:** Mostrar el pasivo con proveedores a una fecha de corte en cubos de antigüedad, en moneda original y MXN, con documentos en disputa y bloqueados, cuadrando contra la cuenta de proveedores de contabilidad.

### Contexto técnico del proyecto
- Aplicación: Portal de Proveedores / Módulo de Compras de TotalGas (grupo gasolinero con 36 estaciones, Cd. Juárez, Chih.). Ya cubre requisiciones, cotizaciones, órdenes de compra, recepciones, control presupuestal y validación CFDI/SAT (RFC, listado 69-B). Cada centro de costo ya tiene responsable asignado.
- Stack: Laravel 12, PHP 8.3, SQL Server 2019, Blade + Bootstrap 5 + jQuery. Si el repositorio ya usa Livewire, DataTables, Laravel Excel u OpenSpout, spatie/laravel-permission o dompdf/snappy, REUTILÍZALOS. No agregues dependencias nuevas sin justificarlas en el plan.
- Zona horaria de negocio: `America/Ciudad_Juarez`. Toda "fecha de corte" se interpreta a las 23:59:59 de esa zona.
- Moneda funcional: MXN. Los nombres de tablas y campos de este documento son SUPUESTOS: el esquema real del repositorio manda.

### Reglas de trabajo (obligatorias)
1. Antes de escribir código, inspecciona migraciones, modelos Eloquent, vistas/funciones SQL, servicios y jobs existentes. Construye una tabla de mapeo: `campo requerido → tabla.campo real | NO EXISTE`.
2. No crees tablas paralelas para datos que ya existen con otro nombre. Si falta un dato, propón en el plan la migración mínima y aditiva, y espera aprobación.
3. Si un prerrequisito no existe, NO simules el dato (ni ceros, ni NULL silenciosos, ni valores fijos). Repórtalo y separa en el plan "lo que se puede entregar hoy" de "lo que requiere construir el control primero". Un reporte que cuadra con datos falsos es peor que no tener reporte.
4. Montos en `DECIMAL(18,2)`; tipos de cambio en `DECIMAL(18,6)`; nunca `FLOAT`. Redondea a 2 decimales a nivel de renglón y después suma; usa el mismo criterio que los demás reportes con los que este debe cuadrar.
5. Consultas siempre parametrizadas (bindings). Prohibido concatenar filtros en SQL.
6. El reporte no consulta en línea los linked servers de las estaciones; usa las tablas ya consolidadas en el servidor del portal.
7. Una sola fuente de verdad por cálculo: si otro reporte o el motor de control usa el mismo cálculo, encapsúlalo (vista, función de tabla en línea o clase de servicio) y reutilízalo. No dupliques fórmulas.
8. No cambies reglas de negocio existentes salvo lo que este documento pida explícitamente. Si detectas un bug, repórtalo en el plan en lugar de corregirlo en silencio.
9. Reglas fiscales (tasas, claves SAT, plazos): van en catálogos parametrizables, nunca en código, y requieren confirmación de Contabilidad/Fiscal antes de liberarse.

### Paso 0: prerrequisitos que debes verificar antes de programar

- Saldo por documento calculable 'a la fecha' (facturas − pagos − notas de crédito aplicadas, con fechas).
- Días de crédito pactados por proveedor.
- Marca de disputa por documento.
- Tipo de cambio FIX por fecha.
- Saldo contable de la cuenta de proveedores (RK-02, F2). En F1 entrega un export de conciliación para cuadre manual.

Si alguno no existe, repórtalo en el plan con su impacto; no lo sustituyas con datos simulados.

## B) NOMBRE Y PROPÓSITO DEL REPORTE

- ID: `RT-02` · Dominio: E. Cuentas por pagar y tesorería · Fase: F1
- Nombre técnico: `rpt_ap_aging`
- Nombre de negocio: Antigüedad de saldos por proveedor
- Frecuencia: Semanal y al cierre mensual
- Consumidor principal: Tesorería, Contabilidad, Contraloría
- Granularidad (una fila =): Una fila por proveedor con cubos de antigüedad, con detalle a nivel documento.

**Pregunta de negocio que responde / decisión que habilita:** La foto del pasivo real con proveedores y del riesgo de suspensión de suministro. Alimenta además la revelación de pasivos en los estados financieros.

**Requerimiento original de Contabilidad y Finanzas (campos mínimos):**

  • Proveedor · RFC · Saldo total · Por vencer · 1-30 · 31-60 · 61-90 · más de 90 días
  • Moneda · Saldo en moneda extranjera y su equivalente en pesos
  • Documentos en disputa · Documentos bloqueados · Días de crédito pactados

## C) COLUMNAS / CAMPOS

Origen = SUPUESTO de nombre; mapea al esquema real en el Paso 0. O = obligatoria, Op = opcional.

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| supplier_name / supplier_rfc | NVARCHAR | suppliers |  | texto | O |
| currency | CHAR(3) | invoices |  | texto | O |
| total_balance_orig | DECIMAL(18,2) | calculada | Σ saldos al corte | moneda origen | O |
| total_balance_mxn | DECIMAL(18,2) | calculada | Σ saldo × TC FIX del corte | moneda MXN | O |
| not_due | DECIMAL(18,2) | calculada | due_date ≥ corte | moneda | O |
| b_1_30 / b_31_60 / b_61_90 / b_90_plus | DECIMAL(18,2) | calculada | por días vencidos = corte − due_date | moneda | O |
| disputed_amount / blocked_amount | DECIMAL(18,2) | calculada | informativos (ya incluidos en los cubos) | moneda | O |
| credit_days | INT | supplier_terms |  | número | O |

**Detalle expandible (segundo nivel):**

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| uuid / invoice_date / due_date / days_overdue / balance_orig / balance_mxn / is_disputed / is_blocked | varios | accounts_payable |  |  | O |

## D) FILTROS Y PARÁMETROS

**Filtros fijos (siempre aplicados):**

- Documentos con saldo ≠ 0 a la fecha de corte.

**Parámetros de entrada (según el Excel: Empresa, moneda, antigüedad, solo vencidos, proveedor, importe.):**

| Parámetro | Tipo | Valor por defecto | O/Op |
|---|---|---|---|
| cut_date | date | hoy | O |
| company_ids | array<int> | empresas del usuario | O |
| currency | multi | todas | Op |
| aging_basis | VENCIMIENTO / FECHA_FACTURA | VENCIMIENTO | Op |
| only_overdue | bool | false | Op |
| supplier_id | int | todos | Op |
| amount_greater_than | decimal | 0 | Op |

## E) AGRUPACIONES, ORDEN Y GRANULARIDAD

- Una fila por proveedor (y moneda) con cubos, detalle por documento.
- ORDER BY total_balance_mxn DESC.
- Totales por cubo y general, en MXN.

## F) REGLAS DE NEGOCIO Y CÁLCULOS

- Saldo 'as of': se recalcula con movimientos ≤ corte (no se usa el saldo actual de la tabla).
- not_due + Σ cubos = total_balance en cada fila (identidad).
- Revaluación de moneda extranjera al FIX del corte; la diferencia contra el TC histórico se muestra en una columna informativa.
- Clase `ApBalanceService::asOf($cutDate)` reutilizada por RT-03 y RT-04.

## G) FORMATO DE SALIDA

- Barra apilada de cubos por proveedor (top 20).
- Tabla con detalle expandible por documento.
- Excel con hoja resumen, detalle y conciliación.

Convenciones comunes de salida:
- Pantalla: tabla con DataTables en modo server-side (paginación en servidor de 25/50/100 filas), encabezado fijo y primera columna congelada, panel de filtros arriba con los parámetros de la sección D y botón "Limpiar filtros".
- Excel (.xlsx) con celdas tipadas de verdad (moneda `$#,##0.00`, fechas `dd/mm/yyyy`, porcentaje `0.0%`), nunca números como texto; encabezado con nombre del reporte, empresa(s), filtros aplicados, fecha/hora de generación y usuario. Escritura en streaming (chunks), sin cargar todo en memoria.
- CSV UTF-8 con BOM y separador coma.
- Exportaciones de más de 50 000 filas se encolan como job y se notifica al usuario cuando el archivo está listo.
- La exportación respeta exactamente los filtros activos en pantalla.

## H) CONSIDERACIONES TÉCNICAS

**Tablas/vistas probables (SUPUESTO):** accounts_payable, invoices, payment_applications, credit_note_applications, suppliers, supplier_terms, exchange_rates.

**Índices recomendados (valida contra los existentes antes de crear):**

- payment_applications (invoice_id, applied_at) INCLUDE (amount).
- invoices (company_id, due_date) INCLUDE (total, currency, supplier_id).

**Seguridad y permisos:**

- Permisos granulares: `reportes.ap_aging.ver` y `reportes.ap_aging.exportar` (crea los permisos con el mecanismo que ya use el repo).
- Alcance por empresa: el usuario solo ve las empresas que tiene asignadas; el filtro se aplica en el servidor (scope/policy), nunca solo en la UI.
- Cada exportación se registra en bitácora: usuario, fecha/hora, filtros, número de filas.

- Tesorería, Contabilidad, Contraloría: lectura.

**Endpoints y componentes:**

- GET /reportes/cxp/antiguedad (+ /data, /export).

## I) CRITERIOS DE ACEPTACIÓN (Definition of Done)

Criterio de negocio original: _El saldo total cuadra contra el saldo de la cuenta de proveedores en contabilidad a la misma fecha de corte; cualquier diferencia se explica partida por partida._

- [ ] not_due + cubos = saldo total en cada fila y en totales.
- [ ] El saldo total cuadra contra la cuenta de proveedores de contabilidad al mismo corte o la diferencia se explica partida por partida (en F1 vía export de conciliación).
- [ ] Consultar un corte pasado da el mismo resultado aunque después haya pagos.
- [ ] Todos los filtros de la sección D funcionan combinados y la exportación reproduce exactamente la vista filtrada.
- [ ] Usuarios sin permiso reciben 403 y ningún usuario ve empresas fuera de su alcance.
- [ ] La consulta principal responde en menos de 3 s con el volumen de un año de operación (o se justifica el snapshot/job).

**Preguntas abiertas (aplica el SUPUESTO indicado si no hay respuesta):**

- ¿Antigüedad por vencimiento o por fecha de factura? SUPUESTO: vencimiento, con opción.

## J) ENTREGABLE ESPERADO

Trabaja en tres entregas y DETENTE al terminar la primera:

1. **PLAN (sin código).**
   - Tabla de mapeo de esquema (campo requerido → tabla.campo real | NO EXISTE).
   - Estado de cada prerrequisito del Paso 0: existe / parcial / no existe, con su impacto en el reporte.
   - Arquitectura: archivos a crear o modificar (migraciones, vista o función SQL, clase de servicio del reporte, FormRequest de parámetros, controlador, rutas, vista Blade/JS, clase de exportación, policy/permisos, jobs y scheduler si aplica).
   - SQL propuesto (borrador) de la consulta principal.
   - Preguntas abiertas para Contraloría/Contabilidad/Fiscal, cada una con el supuesto que aplicarás si no hay respuesta.
   - Espera mi aprobación antes de continuar.
2. **CÓDIGO.** Implementa exactamente lo aprobado: migraciones aditivas, consulta encapsulada, servicio, endpoint, vista, exportaciones, permisos, jobs. Commits pequeños y descriptivos.
3. **PRUEBAS Y VALIDACIÓN.**
   - Pruebas de feature (Pest o PHPUnit, lo que use el repo) con datos semilla que ejerzan cada regla de negocio y cada criterio de aceptación de la sección I.
   - Pruebas de permisos: sin permiso → 403; alcance por empresa/proveedor respetado.
   - Script SQL de verificación que demuestre las identidades de cuadre del reporte.
   - Plan de ejecución en SQL Server con volumen realista; reporta tiempos y los índices creados.
   - Lista explícita de lo que quedó pendiente o fuera de alcance.

------------------------------------------------

================================================
REPORTE #21: RT-03 · Estado de cuenta de proveedor
================================================

# REPORTE RT-03: Estado de cuenta de proveedor

## A) ROL Y OBJETIVO

Actúa como desarrollador senior full-stack en el Portal de Proveedores (Módulo de Compras) de TotalGas, con experiencia en reporting financiero para Contabilidad, Contraloría y Tesorería en México (CFDI 4.0, SAT, REPSE).

**Objetivo medible:** Dar a cada proveedor, desde el portal, su estado de cuenta por empresa con saldo acumulado, estatus y fecha estimada de pago, idéntico al saldo de RT-02 a la misma fecha, con envío automático mensual.

### Contexto técnico del proyecto
- Aplicación: Portal de Proveedores / Módulo de Compras de TotalGas (grupo gasolinero con 36 estaciones, Cd. Juárez, Chih.). Ya cubre requisiciones, cotizaciones, órdenes de compra, recepciones, control presupuestal y validación CFDI/SAT (RFC, listado 69-B). Cada centro de costo ya tiene responsable asignado.
- Stack: Laravel 12, PHP 8.3, SQL Server 2019, Blade + Bootstrap 5 + jQuery. Si el repositorio ya usa Livewire, DataTables, Laravel Excel u OpenSpout, spatie/laravel-permission o dompdf/snappy, REUTILÍZALOS. No agregues dependencias nuevas sin justificarlas en el plan.
- Zona horaria de negocio: `America/Ciudad_Juarez`. Toda "fecha de corte" se interpreta a las 23:59:59 de esa zona.
- Moneda funcional: MXN. Los nombres de tablas y campos de este documento son SUPUESTOS: el esquema real del repositorio manda.

### Reglas de trabajo (obligatorias)
1. Antes de escribir código, inspecciona migraciones, modelos Eloquent, vistas/funciones SQL, servicios y jobs existentes. Construye una tabla de mapeo: `campo requerido → tabla.campo real | NO EXISTE`.
2. No crees tablas paralelas para datos que ya existen con otro nombre. Si falta un dato, propón en el plan la migración mínima y aditiva, y espera aprobación.
3. Si un prerrequisito no existe, NO simules el dato (ni ceros, ni NULL silenciosos, ni valores fijos). Repórtalo y separa en el plan "lo que se puede entregar hoy" de "lo que requiere construir el control primero". Un reporte que cuadra con datos falsos es peor que no tener reporte.
4. Montos en `DECIMAL(18,2)`; tipos de cambio en `DECIMAL(18,6)`; nunca `FLOAT`. Redondea a 2 decimales a nivel de renglón y después suma; usa el mismo criterio que los demás reportes con los que este debe cuadrar.
5. Consultas siempre parametrizadas (bindings). Prohibido concatenar filtros en SQL.
6. El reporte no consulta en línea los linked servers de las estaciones; usa las tablas ya consolidadas en el servidor del portal.
7. Una sola fuente de verdad por cálculo: si otro reporte o el motor de control usa el mismo cálculo, encapsúlalo (vista, función de tabla en línea o clase de servicio) y reutilízalo. No dupliques fórmulas.
8. No cambies reglas de negocio existentes salvo lo que este documento pida explícitamente. Si detectas un bug, repórtalo en el plan en lugar de corregirlo en silencio.
9. Reglas fiscales (tasas, claves SAT, plazos): van en catálogos parametrizables, nunca en código, y requieren confirmación de Contabilidad/Fiscal antes de liberarse.

### Paso 0: prerrequisitos que debes verificar antes de programar

- Servicio de saldos de RT-02 (`ApBalanceService`).
- Acceso de proveedores al portal con relación usuario ↔ proveedor ↔ empresas con las que opera.
- Fecha estimada de pago desde la programación de RT-01 (si no existe, se muestra la fecha de vencimiento como estimada y se indica).
- Correo saliente operativo.

Si alguno no existe, repórtalo en el plan con su impacto; no lo sustituyas con datos simulados.

## B) NOMBRE Y PROPÓSITO DEL REPORTE

- ID: `RT-03` · Dominio: E. Cuentas por pagar y tesorería · Fase: F1
- Nombre técnico: `rpt_supplier_statement`
- Nombre de negocio: Estado de cuenta de proveedor
- Frecuencia: Bajo demanda y envío automático mensual
- Consumidor principal: Proveedor, Cuentas por pagar
- Granularidad (una fila =): Una fila por movimiento (factura, nota de crédito, anticipo, pago) de un proveedor y una empresa.

**Pregunta de negocio que responde / decisión que habilita:** Que el proveedor se conteste solo "¿cuándo me pagan?" desde el portal. Reduce la carga operativa de cuentas por pagar y elimina la discusión de saldos, porque ambas partes ven exactamente el mismo documento.

**Requerimiento original de Contabilidad y Finanzas (campos mínimos):**

  • Fecha · Tipo de documento · UUID o folio · Referencia de OC
  • Cargo · Abono · Saldo acumulado · Fecha de vencimiento
  • Estatus · Motivo si está bloqueado o en disputa · Fecha estimada de pago

## C) COLUMNAS / CAMPOS

Origen = SUPUESTO de nombre; mapea al esquema real en el Paso 0. O = obligatoria, Op = opcional.

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| movement_date | DATE | movimientos |  | fecha | O |
| document_type | VARCHAR(15) | calculada | FACTURA / NOTA_CREDITO / ANTICIPO / PAGO | texto | O |
| uuid_or_folio | NVARCHAR |  |  | texto | O |
| po_reference | NVARCHAR | purchase_orders |  | texto | Op |
| charge / credit | DECIMAL(18,2) | calculada | factura = cargo; pago/NC/anticipo aplicado = abono | moneda | O |
| running_balance | DECIMAL(18,2) | calculada | SUM(charge − credit) OVER (ORDER BY movement_date, id ROWS UNBOUNDED PRECEDING) desde saldo inicial | moneda | O |
| due_date | DATE | accounts_payable |  | fecha | O |
| status | VARCHAR(15) | calculada | PENDIENTE / PROGRAMADA / PAGADA / BLOQUEADA / EN_DISPUTA | badge | O |
| supplier_facing_reason | NVARCHAR(300) | catálogo de mensajes | motivo en lenguaje para el proveedor; NUNCA notas internas | texto | O si bloqueada |
| estimated_payment_date | DATE | payment_batch_items / due_date |  | fecha | O |

## D) FILTROS Y PARÁMETROS

**Filtros fijos (siempre aplicados):**

- Un proveedor y una empresa por estado de cuenta.

**Parámetros de entrada (según el Excel: Proveedor, empresa, periodo, solo saldos abiertos.):**

| Parámetro | Tipo | Valor por defecto | O/Op |
|---|---|---|---|
| supplier_id | int | el del usuario proveedor (forzado) | O |
| company_id | int | primera con la que opera | O |
| date_from / date_to | date | últimos 6 meses | O |
| only_open | bool | false | Op |

## E) AGRUPACIONES, ORDEN Y GRANULARIDAD

- Una fila por movimiento.
- ORDER BY movement_date, id.
- Saldo inicial, totales de cargos/abonos y saldo final.

## F) REGLAS DE NEGOCIO Y CÁLCULOS

- Saldo inicial = saldo de RT-02 al día anterior a date_from; saldo final = saldo de RT-02 a date_to.
- Los motivos de bloqueo se traducen con un catálogo de mensajes para el proveedor; los comentarios internos jamás se exponen.
- Envío automático: job el día 3 de cada mes que genera PDF por proveedor-empresa con saldo o movimientos y lo envía al contacto registrado; bitácora de envío (fecha, destinatario, resultado).

## G) FORMATO DE SALIDA

- Vista del proveedor con tarjeta 'Próximo pago estimado' arriba.
- PDF con membrete de la empresa receptora.
- Vista interna para Cuentas por pagar con selector de proveedor.

Convenciones comunes de salida:
- Pantalla: tabla con DataTables en modo server-side (paginación en servidor de 25/50/100 filas), encabezado fijo y primera columna congelada, panel de filtros arriba con los parámetros de la sección D y botón "Limpiar filtros".
- Excel (.xlsx) con celdas tipadas de verdad (moneda `$#,##0.00`, fechas `dd/mm/yyyy`, porcentaje `0.0%`), nunca números como texto; encabezado con nombre del reporte, empresa(s), filtros aplicados, fecha/hora de generación y usuario. Escritura en streaming (chunks), sin cargar todo en memoria.
- CSV UTF-8 con BOM y separador coma.
- Exportaciones de más de 50 000 filas se encolan como job y se notifica al usuario cuando el archivo está listo.
- La exportación respeta exactamente los filtros activos en pantalla.

## H) CONSIDERACIONES TÉCNICAS

**Tablas/vistas probables (SUPUESTO):** accounts_payable, invoices, payment_applications, credit_note_applications, supplier_advances, payment_batch_items, supplier_users, supplier_message_catalog, statement_deliveries (nueva).

**Índices recomendados (valida contra los existentes antes de crear):**

- Los de RT-02; statement_deliveries (supplier_id, period).

**Seguridad y permisos:**

- Permisos granulares: `reportes.supplier_statement.ver` y `reportes.supplier_statement.exportar` (crea los permisos con el mecanismo que ya use el repo).
- Alcance por empresa: el usuario solo ve las empresas que tiene asignadas; el filtro se aplica en el servidor (scope/policy), nunca solo en la UI.
- Cada exportación se registra en bitácora: usuario, fecha/hora, filtros, número de filas.

- Usuario proveedor: SOLO su supplier_id y SOLO las empresas con las que opera; el filtro se fuerza en el servidor ignorando parámetros manipulados.

**Endpoints y componentes:**

- GET /proveedor/estado-de-cuenta (+ /pdf).
- GET /cxp/estados-de-cuenta/{supplier} (interno).
- Job `MonthlyStatementsJob`.

## I) CRITERIOS DE ACEPTACIÓN (Definition of Done)

Criterio de negocio original: _El saldo final es idéntico al del proveedor en RT-02 a la misma fecha. El proveedor ve únicamente su información y solo de las empresas con las que opera._

- [ ] El saldo final es idéntico al del proveedor en RT-02 a la misma fecha (prueba automatizada).
- [ ] Un proveedor que manipula supplier_id o company_id en la URL recibe 403.
- [ ] Ningún texto interno aparece en la vista o PDF del proveedor.
- [ ] Todos los filtros de la sección D funcionan combinados y la exportación reproduce exactamente la vista filtrada.
- [ ] Usuarios sin permiso reciben 403 y ningún usuario ve empresas fuera de su alcance.
- [ ] La consulta principal responde en menos de 3 s con el volumen de un año de operación (o se justifica el snapshot/job).

## J) ENTREGABLE ESPERADO

Trabaja en tres entregas y DETENTE al terminar la primera:

1. **PLAN (sin código).**
   - Tabla de mapeo de esquema (campo requerido → tabla.campo real | NO EXISTE).
   - Estado de cada prerrequisito del Paso 0: existe / parcial / no existe, con su impacto en el reporte.
   - Arquitectura: archivos a crear o modificar (migraciones, vista o función SQL, clase de servicio del reporte, FormRequest de parámetros, controlador, rutas, vista Blade/JS, clase de exportación, policy/permisos, jobs y scheduler si aplica).
   - SQL propuesto (borrador) de la consulta principal.
   - Preguntas abiertas para Contraloría/Contabilidad/Fiscal, cada una con el supuesto que aplicarás si no hay respuesta.
   - Espera mi aprobación antes de continuar.
2. **CÓDIGO.** Implementa exactamente lo aprobado: migraciones aditivas, consulta encapsulada, servicio, endpoint, vista, exportaciones, permisos, jobs. Commits pequeños y descriptivos.
3. **PRUEBAS Y VALIDACIÓN.**
   - Pruebas de feature (Pest o PHPUnit, lo que use el repo) con datos semilla que ejerzan cada regla de negocio y cada criterio de aceptación de la sección I.
   - Pruebas de permisos: sin permiso → 403; alcance por empresa/proveedor respetado.
   - Script SQL de verificación que demuestre las identidades de cuadre del reporte.
   - Plan de ejecución en SQL Server con volumen realista; reporta tiempos y los índices creados.
   - Lista explícita de lo que quedó pendiente o fuera de alcance.

------------------------------------------------

================================================
REPORTE #22: RT-04 · Proyección de salidas de efectivo a 13 semanas
================================================

# REPORTE RT-04: Proyección de salidas de efectivo a 13 semanas

## A) ROL Y OBJETIVO

Actúa como desarrollador senior full-stack en el Portal de Proveedores (Módulo de Compras) de TotalGas, con experiencia en reporting financiero para Contabilidad, Contraloría y Tesorería en México (CFDI 4.0, SAT, REPSE).

**Objetivo medible:** Proyectar las salidas de efectivo de las próximas 13 semanas por concepto y empresa, distinguiendo siempre obligación cierta (facturada) de compromiso estimado (OC sin factura), con la semana 1 idéntica a la programación autorizada de RT-01.

### Contexto técnico del proyecto
- Aplicación: Portal de Proveedores / Módulo de Compras de TotalGas (grupo gasolinero con 36 estaciones, Cd. Juárez, Chih.). Ya cubre requisiciones, cotizaciones, órdenes de compra, recepciones, control presupuestal y validación CFDI/SAT (RFC, listado 69-B). Cada centro de costo ya tiene responsable asignado.
- Stack: Laravel 12, PHP 8.3, SQL Server 2019, Blade + Bootstrap 5 + jQuery. Si el repositorio ya usa Livewire, DataTables, Laravel Excel u OpenSpout, spatie/laravel-permission o dompdf/snappy, REUTILÍZALOS. No agregues dependencias nuevas sin justificarlas en el plan.
- Zona horaria de negocio: `America/Ciudad_Juarez`. Toda "fecha de corte" se interpreta a las 23:59:59 de esa zona.
- Moneda funcional: MXN. Los nombres de tablas y campos de este documento son SUPUESTOS: el esquema real del repositorio manda.

### Reglas de trabajo (obligatorias)
1. Antes de escribir código, inspecciona migraciones, modelos Eloquent, vistas/funciones SQL, servicios y jobs existentes. Construye una tabla de mapeo: `campo requerido → tabla.campo real | NO EXISTE`.
2. No crees tablas paralelas para datos que ya existen con otro nombre. Si falta un dato, propón en el plan la migración mínima y aditiva, y espera aprobación.
3. Si un prerrequisito no existe, NO simules el dato (ni ceros, ni NULL silenciosos, ni valores fijos). Repórtalo y separa en el plan "lo que se puede entregar hoy" de "lo que requiere construir el control primero". Un reporte que cuadra con datos falsos es peor que no tener reporte.
4. Montos en `DECIMAL(18,2)`; tipos de cambio en `DECIMAL(18,6)`; nunca `FLOAT`. Redondea a 2 decimales a nivel de renglón y después suma; usa el mismo criterio que los demás reportes con los que este debe cuadrar.
5. Consultas siempre parametrizadas (bindings). Prohibido concatenar filtros en SQL.
6. El reporte no consulta en línea los linked servers de las estaciones; usa las tablas ya consolidadas en el servidor del portal.
7. Una sola fuente de verdad por cálculo: si otro reporte o el motor de control usa el mismo cálculo, encapsúlalo (vista, función de tabla en línea o clase de servicio) y reutilízalo. No dupliques fórmulas.
8. No cambies reglas de negocio existentes salvo lo que este documento pida explícitamente. Si detectas un bug, repórtalo en el plan en lugar de corregirlo en silencio.
9. Reglas fiscales (tasas, claves SAT, plazos): van en catálogos parametrizables, nunca en código, y requieren confirmación de Contabilidad/Fiscal antes de liberarse.

### Paso 0: prerrequisitos que debes verificar antes de programar

- RT-01 (programación autorizada), RT-02 (saldos), RC-03 (compromiso por recibir con fecha prometida).
- Catálogo de contratos recurrentes y pagos fijos (renta, servicios).
- Disponible estimado en bancos (captura semanal de Tesorería o importación).

Si alguno no existe, repórtalo en el plan con su impacto; no lo sustituyas con datos simulados.

## B) NOMBRE Y PROPÓSITO DEL REPORTE

- ID: `RT-04` · Dominio: E. Cuentas por pagar y tesorería · Fase: F3
- Nombre técnico: `rpt_13_week_cash_forecast`
- Nombre de negocio: Proyección de salidas de efectivo a 13 semanas
- Frecuencia: Semanal
- Consumidor principal: Dirección de Finanzas, Tesorería
- Granularidad (una fila =): Una fila por semana y concepto, con apertura por empresa.

**Pregunta de negocio que responde / decisión que habilita:** Visibilidad de flujo más allá de la semana en curso, construida con el compromiso ya contraído en el portal en lugar de con estimaciones de cada área.

**Requerimiento original de Contabilidad y Finanzas (campos mínimos):**

  • Semana · Cuentas por pagar vencidas · Por vencer en la semana
  • Compromiso de OC no facturado con fecha esperada de llegada · Contratos recurrentes · Pagos fijos
  • Total proyectado · Disponible estimado · Brecha

## C) COLUMNAS / CAMPOS

Origen = SUPUESTO de nombre; mapea al esquema real en el Paso 0. O = obligatoria, Op = opcional.

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| week_no / week_start | TINYINT / DATE | calculada | 1–13 | número / fecha | O |
| company_name | NVARCHAR | companies |  | texto | O |
| concept | VARCHAR(25) | calculada | CXP_VENCIDA / CXP_POR_VENCER / COMPROMISO_OC / CONTRATO_RECURRENTE / PAGO_FIJO | texto | O |
| certainty | VARCHAR(10) | calculada | CIERTO (facturado) / ESTIMADO | badge | O |
| amount_mxn | DECIMAL(18,2) | según concepto |  | moneda | O |
| total_projected | DECIMAL(18,2) | calculada | Σ conceptos de la semana | moneda | O |
| estimated_available | DECIMAL(18,2) | cash_positions |  | moneda | O |
| gap | DECIMAL(18,2) | calculada | estimated_available − total_projected (acumulado) | moneda (rojo < 0) | O |

## D) FILTROS Y PARÁMETROS

**Filtros fijos (siempre aplicados):**

- Horizonte de 13 semanas desde la semana siguiente al corte de RT-01.

**Parámetros de entrada (según el Excel: Empresa o grupo, escenario (base o conservador), incluir o excluir compromiso no facturado.):**

| Parámetro | Tipo | Valor por defecto | O/Op |
|---|---|---|---|
| company_ids (empresa o grupo) | array<int> | empresas del usuario | O |
| scenario | BASE / CONSERVADOR | BASE | O |
| include_uninvoiced_commitment | bool | true | O |

## E) AGRUPACIONES, ORDEN Y GRANULARIDAD

- Una fila por semana y concepto, con apertura por empresa.
- ORDER BY week_no, concept.
- Total por semana y acumulado.

## F) REGLAS DE NEGOCIO Y CÁLCULOS

- Semana 1 = lote autorizado de RT-01 (no se recalcula).
- CxP vencida no programada: en BASE se reparte según la prioridad histórica; en CONSERVADOR todo en semana 2 (SUPUESTO).
- Compromiso OC: fecha prometida de recepción (RC-03) + días de crédito del proveedor + desfase de facturación (parámetro, default 7 días). En CONSERVADOR desfase 0.
- Moneda extranjera al último FIX disponible.
- Cada cifra conserva su certeza; nunca se suman como una sola sin mostrar el desglose.

## G) FORMATO DE SALIDA

- Gráfico de barras apiladas por semana (cierto vs estimado) con línea de disponible.
- Tabla semana × concepto.
- Excel con hoja por escenario.

Convenciones comunes de salida:
- Pantalla: tabla con DataTables en modo server-side (paginación en servidor de 25/50/100 filas), encabezado fijo y primera columna congelada, panel de filtros arriba con los parámetros de la sección D y botón "Limpiar filtros".
- Excel (.xlsx) con celdas tipadas de verdad (moneda `$#,##0.00`, fechas `dd/mm/yyyy`, porcentaje `0.0%`), nunca números como texto; encabezado con nombre del reporte, empresa(s), filtros aplicados, fecha/hora de generación y usuario. Escritura en streaming (chunks), sin cargar todo en memoria.
- CSV UTF-8 con BOM y separador coma.
- Exportaciones de más de 50 000 filas se encolan como job y se notifica al usuario cuando el archivo está listo.
- La exportación respeta exactamente los filtros activos en pantalla.

## H) CONSIDERACIONES TÉCNICAS

**Tablas/vistas probables (SUPUESTO):** payment_batches (RT-01), ApBalanceService (RT-02), purchase_order_items (RC-03), recurring_contracts, fixed_payments, cash_positions (nuevas si no existen).

**Índices recomendados (valida contra los existentes antes de crear):**

- Reutiliza los de RT-01, RT-02, RC-03.

**Seguridad y permisos:**

- Permisos granulares: `reportes.13_week_cash_forecast.ver` y `reportes.13_week_cash_forecast.exportar` (crea los permisos con el mecanismo que ya use el repo).
- Alcance por empresa: el usuario solo ve las empresas que tiene asignadas; el filtro se aplica en el servidor (scope/policy), nunca solo en la UI.
- Cada exportación se registra en bitácora: usuario, fecha/hora, filtros, número de filas.

- Dirección de Finanzas y Tesorería.

**Endpoints y componentes:**

- GET /tesoreria/flujo-13-semanas (+ /data, /export).

## I) CRITERIOS DE ACEPTACIÓN (Definition of Done)

Criterio de negocio original: _La semana 1 coincide con RT-01 ya autorizado. La proyección distingue siempre obligación cierta (facturada) de compromiso estimado (OC sin factura)._

- [ ] La semana 1 coincide exactamente con el lote autorizado de RT-01.
- [ ] Cada fila indica si es obligación cierta o compromiso estimado.
- [ ] Cambiar de escenario solo modifica los conceptos estimados.
- [ ] Todos los filtros de la sección D funcionan combinados y la exportación reproduce exactamente la vista filtrada.
- [ ] Usuarios sin permiso reciben 403 y ningún usuario ve empresas fuera de su alcance.
- [ ] La consulta principal responde en menos de 3 s con el volumen de un año de operación (o se justifica el snapshot/job).

**Preguntas abiertas (aplica el SUPUESTO indicado si no hay respuesta):**

- ¿Fuente del disponible en bancos? SUPUESTO: captura semanal de Tesorería.

## J) ENTREGABLE ESPERADO

Trabaja en tres entregas y DETENTE al terminar la primera:

1. **PLAN (sin código).**
   - Tabla de mapeo de esquema (campo requerido → tabla.campo real | NO EXISTE).
   - Estado de cada prerrequisito del Paso 0: existe / parcial / no existe, con su impacto en el reporte.
   - Arquitectura: archivos a crear o modificar (migraciones, vista o función SQL, clase de servicio del reporte, FormRequest de parámetros, controlador, rutas, vista Blade/JS, clase de exportación, policy/permisos, jobs y scheduler si aplica).
   - SQL propuesto (borrador) de la consulta principal.
   - Preguntas abiertas para Contraloría/Contabilidad/Fiscal, cada una con el supuesto que aplicarás si no hay respuesta.
   - Espera mi aprobación antes de continuar.
2. **CÓDIGO.** Implementa exactamente lo aprobado: migraciones aditivas, consulta encapsulada, servicio, endpoint, vista, exportaciones, permisos, jobs. Commits pequeños y descriptivos.
3. **PRUEBAS Y VALIDACIÓN.**
   - Pruebas de feature (Pest o PHPUnit, lo que use el repo) con datos semilla que ejerzan cada regla de negocio y cada criterio de aceptación de la sección I.
   - Pruebas de permisos: sin permiso → 403; alcance por empresa/proveedor respetado.
   - Script SQL de verificación que demuestre las identidades de cuadre del reporte.
   - Plan de ejecución en SQL Server con volumen realista; reporta tiempos y los índices creados.
   - Lista explícita de lo que quedó pendiente o fuera de alcance.

------------------------------------------------

================================================
REPORTE #23: RK-01 · Auxiliar de pólizas generadas y archivo de exportación
================================================

# REPORTE RK-01: Auxiliar de pólizas generadas y archivo de exportación

## A) ROL Y OBJETIVO

Actúa como desarrollador senior full-stack en el Portal de Proveedores (Módulo de Compras) de TotalGas, con experiencia en reporting financiero para Contabilidad, Contraloría y Tesorería en México (CFDI 4.0, SAT, REPSE).

**Objetivo medible:** Listar cada cargo y abono generado por el portal con referencia cruzada al CFDI, OC, recepción y pago, controlar su exportación única al sistema contable y permitir navegar del asiento al documento y viceversa.

### Contexto técnico del proyecto
- Aplicación: Portal de Proveedores / Módulo de Compras de TotalGas (grupo gasolinero con 36 estaciones, Cd. Juárez, Chih.). Ya cubre requisiciones, cotizaciones, órdenes de compra, recepciones, control presupuestal y validación CFDI/SAT (RFC, listado 69-B). Cada centro de costo ya tiene responsable asignado.
- Stack: Laravel 12, PHP 8.3, SQL Server 2019, Blade + Bootstrap 5 + jQuery. Si el repositorio ya usa Livewire, DataTables, Laravel Excel u OpenSpout, spatie/laravel-permission o dompdf/snappy, REUTILÍZALOS. No agregues dependencias nuevas sin justificarlas en el plan.
- Zona horaria de negocio: `America/Ciudad_Juarez`. Toda "fecha de corte" se interpreta a las 23:59:59 de esa zona.
- Moneda funcional: MXN. Los nombres de tablas y campos de este documento son SUPUESTOS: el esquema real del repositorio manda.

### Reglas de trabajo (obligatorias)
1. Antes de escribir código, inspecciona migraciones, modelos Eloquent, vistas/funciones SQL, servicios y jobs existentes. Construye una tabla de mapeo: `campo requerido → tabla.campo real | NO EXISTE`.
2. No crees tablas paralelas para datos que ya existen con otro nombre. Si falta un dato, propón en el plan la migración mínima y aditiva, y espera aprobación.
3. Si un prerrequisito no existe, NO simules el dato (ni ceros, ni NULL silenciosos, ni valores fijos). Repórtalo y separa en el plan "lo que se puede entregar hoy" de "lo que requiere construir el control primero". Un reporte que cuadra con datos falsos es peor que no tener reporte.
4. Montos en `DECIMAL(18,2)`; tipos de cambio en `DECIMAL(18,6)`; nunca `FLOAT`. Redondea a 2 decimales a nivel de renglón y después suma; usa el mismo criterio que los demás reportes con los que este debe cuadrar.
5. Consultas siempre parametrizadas (bindings). Prohibido concatenar filtros en SQL.
6. El reporte no consulta en línea los linked servers de las estaciones; usa las tablas ya consolidadas en el servidor del portal.
7. Una sola fuente de verdad por cálculo: si otro reporte o el motor de control usa el mismo cálculo, encapsúlalo (vista, función de tabla en línea o clase de servicio) y reutilízalo. No dupliques fórmulas.
8. No cambies reglas de negocio existentes salvo lo que este documento pida explícitamente. Si detectas un bug, repórtalo en el plan en lugar de corregirlo en silencio.
9. Reglas fiscales (tasas, claves SAT, plazos): van en catálogos parametrizables, nunca en código, y requieren confirmación de Contabilidad/Fiscal antes de liberarse.

### Paso 0: prerrequisitos que debes verificar antes de programar

- Identificar el sistema contable destino y su formato de importación (SUPUESTO: CONTPAQi Contabilidad u otro; confirmar con Contabilidad).
- Catálogo de cuentas y reglas de contabilización por evento (provisión, recepción, factura, pago, nota de crédito).
- Generación de pólizas en el portal: probablemente no existe; es la funcionalidad base de este reporte.

Si alguno no existe, repórtalo en el plan con su impacto; no lo sustituyas con datos simulados.

## B) NOMBRE Y PROPÓSITO DEL REPORTE

- ID: `RK-01` · Dominio: F. Contabilidad e integración · Fase: F2
- Nombre técnico: `rpt_journal_entries_ledger`
- Nombre de negocio: Auxiliar de pólizas generadas y archivo de exportación
- Frecuencia: Diaria y al cierre
- Consumidor principal: Contabilidad
- Granularidad (una fila =): Una fila por movimiento contable (cargo o abono) generado por el portal.

**Pregunta de negocio que responde / decisión que habilita:** Trazabilidad completa entre el asiento y el documento que lo originó, en ambos sentidos. Permite responder "¿de dónde salió este cargo?" en un clic y elimina la recaptura en contabilidad.

**Requerimiento original de Contabilidad y Finanzas (campos mínimos):**

  • Folio de póliza · Tipo (diario, egreso) · Fecha · Concepto
  • Cuenta contable · Centro de costo · Cargo · Abono
  • Referencia cruzada: UUID, folio de OC, folio de recepción, folio de pago
  • Estatus de exportación · Folio devuelto por el sistema contable · Proceso o usuario que la generó

## C) COLUMNAS / CAMPOS

Origen = SUPUESTO de nombre; mapea al esquema real en el Paso 0. O = obligatoria, Op = opcional.

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| entry_folio | NVARCHAR | journal_entries.folio |  | liga | O |
| entry_type | VARCHAR(10) | journal_entries.type | DIARIO / EGRESO / INGRESO | texto | O |
| entry_date / concept | DATE / NVARCHAR | journal_entries |  | fecha / texto | O |
| line_no | INT | journal_entry_lines |  | número | O |
| account_code / account_name | NVARCHAR | accounting_accounts |  | texto | O |
| cost_center | NVARCHAR | cost_centers |  | texto | O |
| debit / credit | DECIMAL(18,2) | journal_entry_lines |  | moneda | O |
| uuid | CHAR(36) | journal_entry_lines.uuid | requerido para contabilidad electrónica cuando aplica | texto | O |
| po_folio / reception_folio / payment_folio | NVARCHAR | referencias |  | ligas | O |
| export_status | VARCHAR(12) | journal_entries | PENDIENTE / EXPORTADA / ERROR | badge | O |
| exported_at / external_folio | DATETIME2 / NVARCHAR | journal_entries | folio devuelto por el sistema contable | fecha / texto | O |
| generated_by | NVARCHAR | journal_entries | proceso (job) o usuario | texto | O |
| is_balanced | BIT | calculada | Σ debit = Σ credit por póliza | ícono | O |

## D) FILTROS Y PARÁMETROS

**Filtros fijos (siempre aplicados):**

- Solo pólizas generadas por el portal.

**Parámetros de entrada (según el Excel: Tipo de póliza, periodo, empresa, estatus de exportación, cuenta contable.):**

| Parámetro | Tipo | Valor por defecto | O/Op |
|---|---|---|---|
| entry_type | multi | todos | Op |
| date_from / date_to | date | mes en curso | O |
| company_ids | array<int> | empresas del usuario | O |
| export_status | multi | todos | Op |
| account_code | multi | todas | Op |

## E) AGRUPACIONES, ORDEN Y GRANULARIDAD

- Una fila por movimiento contable.
- ORDER BY entry_date, entry_folio, line_no.
- Totales de cargos y abonos por póliza y generales.

## F) REGLAS DE NEGOCIO Y CÁLCULOS

- Una póliza desbalanceada no se puede guardar (validación en transacción + prueba).
- Exportación idempotente: una póliza EXPORTADA no vuelve a exportarse; reintentos solo para ERROR; llave única de exportación.
- Las pólizas con CFDI llevan el UUID en el detalle (requisito de contabilidad electrónica).
- Navegación bidireccional: desde el documento (OC, recepción, CFDI, pago) hay liga a sus pólizas.

## G) FORMATO DE SALIDA

- Tabla agrupada por póliza con indicador de cuadre.
- Acción 'Exportar pendientes' (lote) y descarga del archivo generado.
- Excel del auxiliar.

Convenciones comunes de salida:
- Pantalla: tabla con DataTables en modo server-side (paginación en servidor de 25/50/100 filas), encabezado fijo y primera columna congelada, panel de filtros arriba con los parámetros de la sección D y botón "Limpiar filtros".
- Excel (.xlsx) con celdas tipadas de verdad (moneda `$#,##0.00`, fechas `dd/mm/yyyy`, porcentaje `0.0%`), nunca números como texto; encabezado con nombre del reporte, empresa(s), filtros aplicados, fecha/hora de generación y usuario. Escritura en streaming (chunks), sin cargar todo en memoria.
- CSV UTF-8 con BOM y separador coma.
- Exportaciones de más de 50 000 filas se encolan como job y se notifica al usuario cuando el archivo está listo.
- La exportación respeta exactamente los filtros activos en pantalla.

## H) CONSIDERACIONES TÉCNICAS

**Tablas/vistas probables (SUPUESTO):** journal_entries, journal_entry_lines, accounting_accounts, posting_rules, export_batches (nuevas si no existen), invoices, purchase_orders, receptions, payments.

**Índices recomendados (valida contra los existentes antes de crear):**

- journal_entry_lines (journal_entry_id).
- journal_entry_lines (uuid).
- journal_entries (company_id, entry_date, export_status).

**Seguridad y permisos:**

- Permisos granulares: `reportes.journal_entries_ledger.ver` y `reportes.journal_entries_ledger.exportar` (crea los permisos con el mecanismo que ya use el repo).
- Alcance por empresa: el usuario solo ve las empresas que tiene asignadas; el filtro se aplica en el servidor (scope/policy), nunca solo en la UI.
- Cada exportación se registra en bitácora: usuario, fecha/hora, filtros, número de filas.

- Contabilidad: ver y exportar. Nadie edita una póliza exportada; se corrige con póliza de ajuste.

**Endpoints y componentes:**

- GET /contabilidad/auxiliar-polizas (+ /data, /export).
- POST /contabilidad/polizas/exportar.

## I) CRITERIOS DE ACEPTACIÓN (Definition of Done)

Criterio de negocio original: _Toda póliza está cuadrada, no se exporta dos veces y desde cualquier movimiento se navega al CFDI, a la OC y a la evidencia de recepción._

- [ ] Toda póliza está cuadrada (consulta de verificación con 0 filas).
- [ ] Ninguna póliza se exporta dos veces (prueba de doble clic y de reintento).
- [ ] Desde cualquier movimiento se navega al CFDI, a la OC y a la evidencia de recepción.
- [ ] Todos los filtros de la sección D funcionan combinados y la exportación reproduce exactamente la vista filtrada.
- [ ] Usuarios sin permiso reciben 403 y ningún usuario ve empresas fuera de su alcance.
- [ ] La consulta principal responde en menos de 3 s con el volumen de un año de operación (o se justifica el snapshot/job).

**Preguntas abiertas (aplica el SUPUESTO indicado si no hay respuesta):**

- ¿Sistema contable destino y formato? Bloqueante para la exportación.

## J) ENTREGABLE ESPERADO

Trabaja en tres entregas y DETENTE al terminar la primera:

1. **PLAN (sin código).**
   - Tabla de mapeo de esquema (campo requerido → tabla.campo real | NO EXISTE).
   - Estado de cada prerrequisito del Paso 0: existe / parcial / no existe, con su impacto en el reporte.
   - Arquitectura: archivos a crear o modificar (migraciones, vista o función SQL, clase de servicio del reporte, FormRequest de parámetros, controlador, rutas, vista Blade/JS, clase de exportación, policy/permisos, jobs y scheduler si aplica).
   - SQL propuesto (borrador) de la consulta principal.
   - Preguntas abiertas para Contraloría/Contabilidad/Fiscal, cada una con el supuesto que aplicarás si no hay respuesta.
   - Espera mi aprobación antes de continuar.
2. **CÓDIGO.** Implementa exactamente lo aprobado: migraciones aditivas, consulta encapsulada, servicio, endpoint, vista, exportaciones, permisos, jobs. Commits pequeños y descriptivos.
3. **PRUEBAS Y VALIDACIÓN.**
   - Pruebas de feature (Pest o PHPUnit, lo que use el repo) con datos semilla que ejerzan cada regla de negocio y cada criterio de aceptación de la sección I.
   - Pruebas de permisos: sin permiso → 403; alcance por empresa/proveedor respetado.
   - Script SQL de verificación que demuestre las identidades de cuadre del reporte.
   - Plan de ejecución en SQL Server con volumen realista; reporta tiempos y los índices creados.
   - Lista explícita de lo que quedó pendiente o fuera de alcance.

------------------------------------------------

================================================
REPORTE #24: RK-02 · Conciliación del portal contra la contabilidad
================================================

# REPORTE RK-02: Conciliación del portal contra la contabilidad

## A) ROL Y OBJETIVO

Actúa como desarrollador senior full-stack en el Portal de Proveedores (Módulo de Compras) de TotalGas, con experiencia en reporting financiero para Contabilidad, Contraloría y Tesorería en México (CFDI 4.0, SAT, REPSE).

**Objetivo medible:** Conciliar mensualmente el saldo de cada cuenta controlada por el portal contra contabilidad y clasificar el 100 % de la diferencia, exhibiendo las pólizas manuales ajenas al portal con su autor.

### Contexto técnico del proyecto
- Aplicación: Portal de Proveedores / Módulo de Compras de TotalGas (grupo gasolinero con 36 estaciones, Cd. Juárez, Chih.). Ya cubre requisiciones, cotizaciones, órdenes de compra, recepciones, control presupuestal y validación CFDI/SAT (RFC, listado 69-B). Cada centro de costo ya tiene responsable asignado.
- Stack: Laravel 12, PHP 8.3, SQL Server 2019, Blade + Bootstrap 5 + jQuery. Si el repositorio ya usa Livewire, DataTables, Laravel Excel u OpenSpout, spatie/laravel-permission o dompdf/snappy, REUTILÍZALOS. No agregues dependencias nuevas sin justificarlas en el plan.
- Zona horaria de negocio: `America/Ciudad_Juarez`. Toda "fecha de corte" se interpreta a las 23:59:59 de esa zona.
- Moneda funcional: MXN. Los nombres de tablas y campos de este documento son SUPUESTOS: el esquema real del repositorio manda.

### Reglas de trabajo (obligatorias)
1. Antes de escribir código, inspecciona migraciones, modelos Eloquent, vistas/funciones SQL, servicios y jobs existentes. Construye una tabla de mapeo: `campo requerido → tabla.campo real | NO EXISTE`.
2. No crees tablas paralelas para datos que ya existen con otro nombre. Si falta un dato, propón en el plan la migración mínima y aditiva, y espera aprobación.
3. Si un prerrequisito no existe, NO simules el dato (ni ceros, ni NULL silenciosos, ni valores fijos). Repórtalo y separa en el plan "lo que se puede entregar hoy" de "lo que requiere construir el control primero". Un reporte que cuadra con datos falsos es peor que no tener reporte.
4. Montos en `DECIMAL(18,2)`; tipos de cambio en `DECIMAL(18,6)`; nunca `FLOAT`. Redondea a 2 decimales a nivel de renglón y después suma; usa el mismo criterio que los demás reportes con los que este debe cuadrar.
5. Consultas siempre parametrizadas (bindings). Prohibido concatenar filtros en SQL.
6. El reporte no consulta en línea los linked servers de las estaciones; usa las tablas ya consolidadas en el servidor del portal.
7. Una sola fuente de verdad por cálculo: si otro reporte o el motor de control usa el mismo cálculo, encapsúlalo (vista, función de tabla en línea o clase de servicio) y reutilízalo. No dupliques fórmulas.
8. No cambies reglas de negocio existentes salvo lo que este documento pida explícitamente. Si detectas un bug, repórtalo en el plan en lugar de corregirlo en silencio.
9. Reglas fiscales (tasas, claves SAT, plazos): van en catálogos parametrizables, nunca en código, y requieren confirmación de Contabilidad/Fiscal antes de liberarse.

### Paso 0: prerrequisitos que debes verificar antes de programar

- RK-01 con pólizas exportadas y su folio externo.
- Importación del saldo y auxiliar del sistema contable por cuenta (archivo o conexión).
- Catálogo de cuentas controladas por el portal.

Si alguno no existe, repórtalo en el plan con su impacto; no lo sustituyas con datos simulados.

## B) NOMBRE Y PROPÓSITO DEL REPORTE

- ID: `RK-02` · Dominio: F. Contabilidad e integración · Fase: F2
- Nombre técnico: `rpt_portal_vs_accounting`
- Nombre de negocio: Conciliación del portal contra la contabilidad
- Frecuencia: Mensual
- Consumidor principal: Contabilidad, Contraloría
- Granularidad (una fila =): Una fila por diferencia entre el saldo del portal y el saldo contable de una cuenta controlada.

**Pregunta de negocio que responde / decisión que habilita:** El cierre del círculo. Mientras existan asientos de proveedores hechos fuera del portal, este reporte los exhibe uno por uno; cuando la diferencia llegue a cero, el portal es la fuente única de verdad del ciclo de compras.

**Requerimiento original de Contabilidad y Finanzas (campos mínimos):**

  • Cuenta · Empresa · Saldo en portal · Saldo en contabilidad · Diferencia
  • Clasificación: provisión no exportada, póliza manual ajena al portal, documento cancelado, diferencia cambiaria, error de captura
  • Documento y responsable asociados

## C) COLUMNAS / CAMPOS

Origen = SUPUESTO de nombre; mapea al esquema real en el Paso 0. O = obligatoria, Op = opcional.

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| account_code / account_name | NVARCHAR | accounting_accounts |  | texto | O |
| company_name | NVARCHAR | companies |  | texto | O |
| period | CHAR(7) |  |  | yyyy-mm | O |
| portal_balance | DECIMAL(18,2) | journal_entry_lines | Σ al cierre | moneda | O |
| accounting_balance | DECIMAL(18,2) | accounting_balances (importado) |  | moneda | O |
| difference | DECIMAL(18,2) | calculada | portal − contabilidad | moneda | O |
| classification | VARCHAR(25) | calculada | PROVISION_NO_EXPORTADA / POLIZA_MANUAL_AJENA / DOCUMENTO_CANCELADO / DIFERENCIA_CAMBIARIA / ERROR_CAPTURA | badge | O |
| document_ref / responsible / manual_entry_author / manual_entry_folio | NVARCHAR | según clasificación |  | texto | O |
| unclassified_residual | DECIMAL(18,2) | calculada | difference − Σ partidas clasificadas; debe ser 0 | moneda (rojo ≠ 0) | O |

## D) FILTROS Y PARÁMETROS

**Filtros fijos (siempre aplicados):**

- Solo cuentas marcadas como controladas por el portal.

**Parámetros de entrada (según el Excel: Cuenta, empresa, mes, diferencia mayor a.):**

| Parámetro | Tipo | Valor por defecto | O/Op |
|---|---|---|---|
| account_code | multi | todas | Op |
| company_ids | array<int> | empresas del usuario | O |
| year_month | yyyy-mm | mes anterior | O |
| difference_greater_than | decimal | 0.01 | Op |

## E) AGRUPACIONES, ORDEN Y GRANULARIDAD

- Una fila por partida de diferencia, agrupada por cuenta.
- ORDER BY ABS(difference) DESC.

## F) REGLAS DE NEGOCIO Y CÁLCULOS

- Póliza manual ajena: movimiento en el auxiliar contable de una cuenta controlada cuyo folio no existe en las exportaciones del portal.
- Cualquier residual no clasificado es una falla visible (tarjeta roja), nunca una categoría 'otros'.
- Diferencia cambiaria: se calcula con TC del portal vs TC usado en contabilidad por documento.

## G) FORMATO DE SALIDA

- Tarjetas: diferencia total, % clasificado, # pólizas manuales ajenas.
- Tabla por cuenta con partidas expandibles.

Convenciones comunes de salida:
- Pantalla: tabla con DataTables en modo server-side (paginación en servidor de 25/50/100 filas), encabezado fijo y primera columna congelada, panel de filtros arriba con los parámetros de la sección D y botón "Limpiar filtros".
- Excel (.xlsx) con celdas tipadas de verdad (moneda `$#,##0.00`, fechas `dd/mm/yyyy`, porcentaje `0.0%`), nunca números como texto; encabezado con nombre del reporte, empresa(s), filtros aplicados, fecha/hora de generación y usuario. Escritura en streaming (chunks), sin cargar todo en memoria.
- CSV UTF-8 con BOM y separador coma.
- Exportaciones de más de 50 000 filas se encolan como job y se notifica al usuario cuando el archivo está listo.
- La exportación respeta exactamente los filtros activos en pantalla.

## H) CONSIDERACIONES TÉCNICAS

**Tablas/vistas probables (SUPUESTO):** journal_entries, journal_entry_lines, export_batches, accounting_balances, accounting_ledger_imports (nuevas), controlled_accounts.

**Índices recomendados (valida contra los existentes antes de crear):**

- accounting_ledger_imports (company_id, period, account_code).
- export_batches (external_folio).

**Seguridad y permisos:**

- Permisos granulares: `reportes.portal_vs_accounting.ver` y `reportes.portal_vs_accounting.exportar` (crea los permisos con el mecanismo que ya use el repo).
- Alcance por empresa: el usuario solo ve las empresas que tiene asignadas; el filtro se aplica en el servidor (scope/policy), nunca solo en la UI.
- Cada exportación se registra en bitácora: usuario, fecha/hora, filtros, número de filas.

- Contabilidad y Contraloría.

**Endpoints y componentes:**

- GET /contabilidad/conciliacion (+ /data, /export).
- POST /contabilidad/conciliacion/importar-auxiliar.

## I) CRITERIOS DE ACEPTACIÓN (Definition of Done)

Criterio de negocio original: _Se clasifica el 100 % de la diferencia. Las pólizas manuales sobre cuentas controladas por el portal se identifican y se listan con su autor._

- [ ] Se clasifica el 100 % de la diferencia (residual = 0).
- [ ] Las pólizas manuales sobre cuentas controladas se listan con su autor.
- [ ] Todos los filtros de la sección D funcionan combinados y la exportación reproduce exactamente la vista filtrada.
- [ ] Usuarios sin permiso reciben 403 y ningún usuario ve empresas fuera de su alcance.
- [ ] La consulta principal responde en menos de 3 s con el volumen de un año de operación (o se justifica el snapshot/job).

**Preguntas abiertas (aplica el SUPUESTO indicado si no hay respuesta):**

- ¿Cómo se obtiene el auxiliar contable (archivo, vista, API)? Bloqueante.

## J) ENTREGABLE ESPERADO

Trabaja en tres entregas y DETENTE al terminar la primera:

1. **PLAN (sin código).**
   - Tabla de mapeo de esquema (campo requerido → tabla.campo real | NO EXISTE).
   - Estado de cada prerrequisito del Paso 0: existe / parcial / no existe, con su impacto en el reporte.
   - Arquitectura: archivos a crear o modificar (migraciones, vista o función SQL, clase de servicio del reporte, FormRequest de parámetros, controlador, rutas, vista Blade/JS, clase de exportación, policy/permisos, jobs y scheduler si aplica).
   - SQL propuesto (borrador) de la consulta principal.
   - Preguntas abiertas para Contraloría/Contabilidad/Fiscal, cada una con el supuesto que aplicarás si no hay respuesta.
   - Espera mi aprobación antes de continuar.
2. **CÓDIGO.** Implementa exactamente lo aprobado: migraciones aditivas, consulta encapsulada, servicio, endpoint, vista, exportaciones, permisos, jobs. Commits pequeños y descriptivos.
3. **PRUEBAS Y VALIDACIÓN.**
   - Pruebas de feature (Pest o PHPUnit, lo que use el repo) con datos semilla que ejerzan cada regla de negocio y cada criterio de aceptación de la sección I.
   - Pruebas de permisos: sin permiso → 403; alcance por empresa/proveedor respetado.
   - Script SQL de verificación que demuestre las identidades de cuadre del reporte.
   - Plan de ejecución en SQL Server con volumen realista; reporta tiempos y los índices creados.
   - Lista explícita de lo que quedó pendiente o fuera de alcance.

------------------------------------------------

================================================
REPORTE #25: RM-01 · Matriz de cumplimiento y semáforo de proveedores
================================================

# REPORTE RM-01: Matriz de cumplimiento y semáforo de proveedores

## A) ROL Y OBJETIVO

Actúa como desarrollador senior full-stack en el Portal de Proveedores (Módulo de Compras) de TotalGas, con experiencia en reporting financiero para Contabilidad, Contraloría y Tesorería en México (CFDI 4.0, SAT, REPSE).

**Objetivo medible:** Mostrar por proveedor y empresa el semáforo de cumplimiento explicado por una regla identificada, con el saldo expuesto al lado, sin posibilidad de cambiar el color a mano.

### Contexto técnico del proyecto
- Aplicación: Portal de Proveedores / Módulo de Compras de TotalGas (grupo gasolinero con 36 estaciones, Cd. Juárez, Chih.). Ya cubre requisiciones, cotizaciones, órdenes de compra, recepciones, control presupuestal y validación CFDI/SAT (RFC, listado 69-B). Cada centro de costo ya tiene responsable asignado.
- Stack: Laravel 12, PHP 8.3, SQL Server 2019, Blade + Bootstrap 5 + jQuery. Si el repositorio ya usa Livewire, DataTables, Laravel Excel u OpenSpout, spatie/laravel-permission o dompdf/snappy, REUTILÍZALOS. No agregues dependencias nuevas sin justificarlas en el plan.
- Zona horaria de negocio: `America/Ciudad_Juarez`. Toda "fecha de corte" se interpreta a las 23:59:59 de esa zona.
- Moneda funcional: MXN. Los nombres de tablas y campos de este documento son SUPUESTOS: el esquema real del repositorio manda.

### Reglas de trabajo (obligatorias)
1. Antes de escribir código, inspecciona migraciones, modelos Eloquent, vistas/funciones SQL, servicios y jobs existentes. Construye una tabla de mapeo: `campo requerido → tabla.campo real | NO EXISTE`.
2. No crees tablas paralelas para datos que ya existen con otro nombre. Si falta un dato, propón en el plan la migración mínima y aditiva, y espera aprobación.
3. Si un prerrequisito no existe, NO simules el dato (ni ceros, ni NULL silenciosos, ni valores fijos). Repórtalo y separa en el plan "lo que se puede entregar hoy" de "lo que requiere construir el control primero". Un reporte que cuadra con datos falsos es peor que no tener reporte.
4. Montos en `DECIMAL(18,2)`; tipos de cambio en `DECIMAL(18,6)`; nunca `FLOAT`. Redondea a 2 decimales a nivel de renglón y después suma; usa el mismo criterio que los demás reportes con los que este debe cuadrar.
5. Consultas siempre parametrizadas (bindings). Prohibido concatenar filtros en SQL.
6. El reporte no consulta en línea los linked servers de las estaciones; usa las tablas ya consolidadas en el servidor del portal.
7. Una sola fuente de verdad por cálculo: si otro reporte o el motor de control usa el mismo cálculo, encapsúlalo (vista, función de tabla en línea o clase de servicio) y reutilízalo. No dupliques fórmulas.
8. No cambies reglas de negocio existentes salvo lo que este documento pida explícitamente. Si detectas un bug, repórtalo en el plan en lugar de corregirlo en silencio.
9. Reglas fiscales (tasas, claves SAT, plazos): van en catálogos parametrizables, nunca en código, y requieren confirmación de Contabilidad/Fiscal antes de liberarse.

### Paso 0: prerrequisitos que debes verificar antes de programar

- Expediente del proveedor: CSF, opinión 32-D, REPSE, contrato, expediente mensual.
- Validación contra el listado 69-B (ya existe en el portal; reutilízala).
- Motor de reglas de color (probablemente nuevo) y tabla de excepciones temporales con vigencia y autorizador.
- Saldos expuestos: OC comprometidas (RC-03) + CxP (RT-02).

Si alguno no existe, repórtalo en el plan con su impacto; no lo sustituyas con datos simulados.

## B) NOMBRE Y PROPÓSITO DEL REPORTE

- ID: `RM-01` · Dominio: G. Cumplimiento del proveedor y REPSE · Fase: F2
- Nombre técnico: `rpt_supplier_compliance_matrix`
- Nombre de negocio: Matriz de cumplimiento y semáforo de proveedores
- Frecuencia: Tiempo real con recálculo diario
- Consumidor principal: Contraloría, Compras, Fiscal
- Granularidad (una fila =): Una fila por proveedor y por empresa con la que opera.

**Pregunta de negocio que responde / decisión que habilita:** El estado de riesgo de toda la base de proveedores en una vista, con el monto expuesto al lado. Es lo que permite decidir si un bloqueo detiene cinco mil pesos o cinco millones antes de aplicarlo.

**Requerimiento original de Contabilidad y Finanzas (campos mínimos):**

  • RFC · Razón social · Semáforo · Régimen fiscal · Constancia de situación fiscal vigente
  • Opinión de cumplimiento 32-D: folio, fecha, resultado, vigencia
  • Situación en el listado del artículo 69-B · Registro REPSE: folio, vigencia, actividades autorizadas
  • Contrato vigente · Expediente mensual al día · Motivo del color actual · Fecha del último recálculo
  • Operaciones vivas y saldo expuesto

## C) COLUMNAS / CAMPOS

Origen = SUPUESTO de nombre; mapea al esquema real en el Paso 0. O = obligatoria, Op = opcional.

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| supplier_rfc / supplier_name | NVARCHAR | suppliers |  | texto | O |
| company_name | NVARCHAR | companies |  | texto | O |
| light | VARCHAR(8) | supplier_compliance | VERDE / AMARILLO / ROJO / BLOQUEADO | badge | O |
| light_rule_code / light_reason | VARCHAR / NVARCHAR | compliance_rules | regla que determinó el color | texto | O |
| tax_regime | NVARCHAR | suppliers |  | texto | O |
| csf_date / csf_valid | DATE / BIT | supplier_documents |  | fecha / ícono | O |
| opinion_32d_folio / date / result / valid_until | varios | supplier_documents | vigencia parametrizable (Fiscal confirma) | texto / fecha | O |
| sat_69b_status | VARCHAR | listado 69-B |  | badge | O |
| repse_folio / repse_valid_until / repse_activities | varios | supplier_documents |  | texto / fecha | O si aplica |
| contract_valid / monthly_file_up_to_date | BIT | contracts / RM-03 |  | ícono | O |
| last_recalc_at | DATETIME2 | supplier_compliance |  | fecha-hora | O |
| live_ops_count / exposed_amount_mxn | INT / DECIMAL(18,2) | calculada | OC vivas + saldo CxP | número / moneda | O |
| active_exception | NVARCHAR | compliance_exceptions | vigencia, autorizador, motivo | texto | Op |

## D) FILTROS Y PARÁMETROS

**Filtros fijos (siempre aplicados):**

- Proveedores activos.

**Parámetros de entrada (según el Excel: Color, empresa, tipo de proveedor, REPSE sí o no, próximos a vencer.):**

| Parámetro | Tipo | Valor por defecto | O/Op |
|---|---|---|---|
| light | multi | todos | Op |
| company_ids | array<int> | empresas del usuario | O |
| supplier_type | multi | todos | Op |
| is_repse | bool | todos | Op |
| expiring_within_days | int | vacío | Op |

## E) AGRUPACIONES, ORDEN Y GRANULARIDAD

- Una fila por proveedor + empresa.
- ORDER BY severidad del color, exposed_amount DESC.

## F) REGLAS DE NEGOCIO Y CÁLCULOS

- Color = peor resultado de todas las reglas aplicables, con precedencia determinista; cada regla tiene código y descripción.
- No existe endpoint ni permiso para editar el color. Solo se registra una excepción temporal (vigencia, autorizador, motivo) que se registra también en RA-01.
- Recálculo diario (job) + recálculo inmediato al cargar/vencer un documento o al cambiar el 69-B.
- Historial de colores por proveedor (`supplier_compliance_history`) para auditoría.

## G) FORMATO DE SALIDA

- Matriz con íconos por requisito y color general.
- Tarjetas: % en verde, # bloqueados y saldo expuesto en rojo/bloqueado.
- Detalle del proveedor con línea de tiempo de colores.

Convenciones comunes de salida:
- Pantalla: tabla con DataTables en modo server-side (paginación en servidor de 25/50/100 filas), encabezado fijo y primera columna congelada, panel de filtros arriba con los parámetros de la sección D y botón "Limpiar filtros".
- Excel (.xlsx) con celdas tipadas de verdad (moneda `$#,##0.00`, fechas `dd/mm/yyyy`, porcentaje `0.0%`), nunca números como texto; encabezado con nombre del reporte, empresa(s), filtros aplicados, fecha/hora de generación y usuario. Escritura en streaming (chunks), sin cargar todo en memoria.
- CSV UTF-8 con BOM y separador coma.
- Exportaciones de más de 50 000 filas se encolan como job y se notifica al usuario cuando el archivo está listo.
- La exportación respeta exactamente los filtros activos en pantalla.

## H) CONSIDERACIONES TÉCNICAS

**Tablas/vistas probables (SUPUESTO):** suppliers, supplier_documents, supplier_compliance, supplier_compliance_history, compliance_rules, compliance_exceptions (nuevas), contracts, efos/69-B existentes.

**Índices recomendados (valida contra los existentes antes de crear):**

- supplier_compliance (company_id, light) INCLUDE (exposed_amount_mxn).
- supplier_documents (supplier_id, document_type, valid_until).

**Seguridad y permisos:**

- Permisos granulares: `reportes.supplier_compliance_matrix.ver` y `reportes.supplier_compliance_matrix.exportar` (crea los permisos con el mecanismo que ya use el repo).
- Alcance por empresa: el usuario solo ve las empresas que tiene asignadas; el filtro se aplica en el servidor (scope/policy), nunca solo en la UI.
- Cada exportación se registra en bitácora: usuario, fecha/hora, filtros, número de filas.

- Registrar excepciones: solo Contraloría con autorizador distinto al solicitante.

**Endpoints y componentes:**

- GET /reportes/cumplimiento/matriz (+ /data, /export).
- Job `SupplierComplianceRecalcJob`.

## I) CRITERIOS DE ACEPTACIÓN (Definition of Done)

Criterio de negocio original: _El color siempre es explicable por una regla identificada, nunca por criterio del usuario. No se puede cambiar el color a mano: solo registrar una excepción temporal con vigencia y autorizador._

- [ ] Todo color tiene regla identificada.
- [ ] No se puede cambiar el color a mano (prueba de API).
- [ ] Una excepción vencida deja de tener efecto automáticamente.
- [ ] Todos los filtros de la sección D funcionan combinados y la exportación reproduce exactamente la vista filtrada.
- [ ] Usuarios sin permiso reciben 403 y ningún usuario ve empresas fuera de su alcance.
- [ ] La consulta principal responde en menos de 3 s con el volumen de un año de operación (o se justifica el snapshot/job).

**Preguntas abiertas (aplica el SUPUESTO indicado si no hay respuesta):**

- Vigencia de la opinión 32-D para efectos internos: SUPUESTO 30 días naturales; Fiscal confirma.

## J) ENTREGABLE ESPERADO

Trabaja en tres entregas y DETENTE al terminar la primera:

1. **PLAN (sin código).**
   - Tabla de mapeo de esquema (campo requerido → tabla.campo real | NO EXISTE).
   - Estado de cada prerrequisito del Paso 0: existe / parcial / no existe, con su impacto en el reporte.
   - Arquitectura: archivos a crear o modificar (migraciones, vista o función SQL, clase de servicio del reporte, FormRequest de parámetros, controlador, rutas, vista Blade/JS, clase de exportación, policy/permisos, jobs y scheduler si aplica).
   - SQL propuesto (borrador) de la consulta principal.
   - Preguntas abiertas para Contraloría/Contabilidad/Fiscal, cada una con el supuesto que aplicarás si no hay respuesta.
   - Espera mi aprobación antes de continuar.
2. **CÓDIGO.** Implementa exactamente lo aprobado: migraciones aditivas, consulta encapsulada, servicio, endpoint, vista, exportaciones, permisos, jobs. Commits pequeños y descriptivos.
3. **PRUEBAS Y VALIDACIÓN.**
   - Pruebas de feature (Pest o PHPUnit, lo que use el repo) con datos semilla que ejerzan cada regla de negocio y cada criterio de aceptación de la sección I.
   - Pruebas de permisos: sin permiso → 403; alcance por empresa/proveedor respetado.
   - Script SQL de verificación que demuestre las identidades de cuadre del reporte.
   - Plan de ejecución en SQL Server con volumen realista; reporta tiempos y los índices creados.
   - Lista explícita de lo que quedó pendiente o fuera de alcance.

------------------------------------------------

================================================
REPORTE #26: RM-02 · Vencimientos documentales del expediente
================================================

# REPORTE RM-02: Vencimientos documentales del expediente

## A) ROL Y OBJETIVO

Actúa como desarrollador senior full-stack en el Portal de Proveedores (Módulo de Compras) de TotalGas, con experiencia en reporting financiero para Contabilidad, Contraloría y Tesorería en México (CFDI 4.0, SAT, REPSE).

**Objetivo medible:** Convertir los vencimientos del expediente en una agenda con recordatorios automáticos al proveedor y al comprador en cortes definidos, registrando cada notificación.

### Contexto técnico del proyecto
- Aplicación: Portal de Proveedores / Módulo de Compras de TotalGas (grupo gasolinero con 36 estaciones, Cd. Juárez, Chih.). Ya cubre requisiciones, cotizaciones, órdenes de compra, recepciones, control presupuestal y validación CFDI/SAT (RFC, listado 69-B). Cada centro de costo ya tiene responsable asignado.
- Stack: Laravel 12, PHP 8.3, SQL Server 2019, Blade + Bootstrap 5 + jQuery. Si el repositorio ya usa Livewire, DataTables, Laravel Excel u OpenSpout, spatie/laravel-permission o dompdf/snappy, REUTILÍZALOS. No agregues dependencias nuevas sin justificarlas en el plan.
- Zona horaria de negocio: `America/Ciudad_Juarez`. Toda "fecha de corte" se interpreta a las 23:59:59 de esa zona.
- Moneda funcional: MXN. Los nombres de tablas y campos de este documento son SUPUESTOS: el esquema real del repositorio manda.

### Reglas de trabajo (obligatorias)
1. Antes de escribir código, inspecciona migraciones, modelos Eloquent, vistas/funciones SQL, servicios y jobs existentes. Construye una tabla de mapeo: `campo requerido → tabla.campo real | NO EXISTE`.
2. No crees tablas paralelas para datos que ya existen con otro nombre. Si falta un dato, propón en el plan la migración mínima y aditiva, y espera aprobación.
3. Si un prerrequisito no existe, NO simules el dato (ni ceros, ni NULL silenciosos, ni valores fijos). Repórtalo y separa en el plan "lo que se puede entregar hoy" de "lo que requiere construir el control primero". Un reporte que cuadra con datos falsos es peor que no tener reporte.
4. Montos en `DECIMAL(18,2)`; tipos de cambio en `DECIMAL(18,6)`; nunca `FLOAT`. Redondea a 2 decimales a nivel de renglón y después suma; usa el mismo criterio que los demás reportes con los que este debe cuadrar.
5. Consultas siempre parametrizadas (bindings). Prohibido concatenar filtros en SQL.
6. El reporte no consulta en línea los linked servers de las estaciones; usa las tablas ya consolidadas en el servidor del portal.
7. Una sola fuente de verdad por cálculo: si otro reporte o el motor de control usa el mismo cálculo, encapsúlalo (vista, función de tabla en línea o clase de servicio) y reutilízalo. No dupliques fórmulas.
8. No cambies reglas de negocio existentes salvo lo que este documento pida explícitamente. Si detectas un bug, repórtalo en el plan en lugar de corregirlo en silencio.
9. Reglas fiscales (tasas, claves SAT, plazos): van en catálogos parametrizables, nunca en código, y requieren confirmación de Contabilidad/Fiscal antes de liberarse.

### Paso 0: prerrequisitos que debes verificar antes de programar

- Documentos del expediente con fecha de vigencia.
- Contacto del proveedor y comprador responsable.
- Correo saliente operativo.
- Reglas de efecto al vencer (RM-01).

Si alguno no existe, repórtalo en el plan con su impacto; no lo sustituyas con datos simulados.

## B) NOMBRE Y PROPÓSITO DEL REPORTE

- ID: `RM-02` · Dominio: G. Cumplimiento del proveedor y REPSE · Fase: F2
- Nombre técnico: `rpt_supplier_document_expirations`
- Nombre de negocio: Vencimientos documentales del expediente
- Frecuencia: Semanal
- Consumidor principal: Compras, Contraloría
- Granularidad (una fila =): Una fila por documento del expediente con fecha de vigencia.

**Pregunta de negocio que responde / decisión que habilita:** La cobranza documental convertida en agenda, con recordatorios automáticos al proveedor antes de que el bloqueo ocurra. Evita el bloqueo sorpresa justo el día que hay que pagarle.

**Requerimiento original de Contabilidad y Finanzas (campos mínimos):**

  • Proveedor · Tipo de documento · Fecha de emisión · Fecha de vencimiento · Días restantes
  • Estatus · Responsable interno · Recordatorios enviados y fechas
  • Efecto al vencer: amarillo, rojo o bloqueo total

## C) COLUMNAS / CAMPOS

Origen = SUPUESTO de nombre; mapea al esquema real en el Paso 0. O = obligatoria, Op = opcional.

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| supplier_name / supplier_rfc | NVARCHAR | suppliers |  | texto | O |
| company_name | NVARCHAR | companies |  | texto | O |
| document_type | NVARCHAR | supplier_documents |  | texto | O |
| issued_at / expires_at | DATE | supplier_documents |  | fecha | O |
| days_left | INT | calculada | DATEDIFF(day, hoy, expires_at) | número (rojo ≤ 5) | O |
| status | VARCHAR(15) | calculada | VIGENTE / POR_VENCER / VENCIDO / EN_REVISION | badge | O |
| internal_owner | NVARCHAR | users | comprador responsable | texto | O |
| reminders_sent / last_reminder_at | INT / DATETIME2 | document_reminders |  | número / fecha | O |
| effect_on_expiry | VARCHAR(10) | compliance_rules | AMARILLO / ROJO / BLOQUEO_TOTAL | badge | O |

## D) FILTROS Y PARÁMETROS

**Filtros fijos (siempre aplicados):**

- Documentos del expediente vigentes o vencidos de proveedores activos.

**Parámetros de entrada (según el Excel: Días para vencer, tipo de documento, proveedor, empresa.):**

| Parámetro | Tipo | Valor por defecto | O/Op |
|---|---|---|---|
| days_to_expiry | int | 30 | O |
| document_type | multi | todos | Op |
| supplier_id | int | todos | Op |
| company_ids | array<int> | empresas del usuario | O |

## E) AGRUPACIONES, ORDEN Y GRANULARIDAD

- Una fila por documento.
- ORDER BY days_left ASC.

## F) REGLAS DE NEGOCIO Y CÁLCULOS

- Cortes de aviso parametrizables (default 30, 15 y 5 días) a proveedor y comprador.
- Envío idempotente: índice único (supplier_document_id, cutoff_days).
- Cada notificación queda registrada con fecha, destinatario y resultado del envío.

## G) FORMATO DE SALIDA

- Vista calendario/agenda por semana.
- Tabla con historial de recordatorios por documento.

Convenciones comunes de salida:
- Pantalla: tabla con DataTables en modo server-side (paginación en servidor de 25/50/100 filas), encabezado fijo y primera columna congelada, panel de filtros arriba con los parámetros de la sección D y botón "Limpiar filtros".
- Excel (.xlsx) con celdas tipadas de verdad (moneda `$#,##0.00`, fechas `dd/mm/yyyy`, porcentaje `0.0%`), nunca números como texto; encabezado con nombre del reporte, empresa(s), filtros aplicados, fecha/hora de generación y usuario. Escritura en streaming (chunks), sin cargar todo en memoria.
- CSV UTF-8 con BOM y separador coma.
- Exportaciones de más de 50 000 filas se encolan como job y se notifica al usuario cuando el archivo está listo.
- La exportación respeta exactamente los filtros activos en pantalla.

## H) CONSIDERACIONES TÉCNICAS

**Tablas/vistas probables (SUPUESTO):** supplier_documents, document_reminders (nueva), suppliers, users, compliance_rules.

**Índices recomendados (valida contra los existentes antes de crear):**

- supplier_documents (expires_at) INCLUDE (supplier_id, document_type).
- document_reminders UNIQUE (supplier_document_id, cutoff_days).

**Seguridad y permisos:**

- Permisos granulares: `reportes.supplier_document_expirations.ver` y `reportes.supplier_document_expirations.exportar` (crea los permisos con el mecanismo que ya use el repo).
- Alcance por empresa: el usuario solo ve las empresas que tiene asignadas; el filtro se aplica en el servidor (scope/policy), nunca solo en la UI.
- Cada exportación se registra en bitácora: usuario, fecha/hora, filtros, número de filas.

- Compras y Contraloría.

**Endpoints y componentes:**

- GET /reportes/cumplimiento/vencimientos (+ /data, /export).
- Job diario `DocumentRemindersJob`.

## I) CRITERIOS DE ACEPTACIÓN (Definition of Done)

Criterio de negocio original: _El sistema notifica al proveedor y al comprador en los cortes definidos (por ejemplo 30, 15 y 5 días) y registra cada notificación con fecha y destinatario._

- [ ] Notifica en los cortes definidos a proveedor y comprador, registrando fecha y destinatario.
- [ ] Re-ejecutar el job el mismo día no duplica avisos.
- [ ] Todos los filtros de la sección D funcionan combinados y la exportación reproduce exactamente la vista filtrada.
- [ ] Usuarios sin permiso reciben 403 y ningún usuario ve empresas fuera de su alcance.
- [ ] La consulta principal responde en menos de 3 s con el volumen de un año de operación (o se justifica el snapshot/job).

## J) ENTREGABLE ESPERADO

Trabaja en tres entregas y DETENTE al terminar la primera:

1. **PLAN (sin código).**
   - Tabla de mapeo de esquema (campo requerido → tabla.campo real | NO EXISTE).
   - Estado de cada prerrequisito del Paso 0: existe / parcial / no existe, con su impacto en el reporte.
   - Arquitectura: archivos a crear o modificar (migraciones, vista o función SQL, clase de servicio del reporte, FormRequest de parámetros, controlador, rutas, vista Blade/JS, clase de exportación, policy/permisos, jobs y scheduler si aplica).
   - SQL propuesto (borrador) de la consulta principal.
   - Preguntas abiertas para Contraloría/Contabilidad/Fiscal, cada una con el supuesto que aplicarás si no hay respuesta.
   - Espera mi aprobación antes de continuar.
2. **CÓDIGO.** Implementa exactamente lo aprobado: migraciones aditivas, consulta encapsulada, servicio, endpoint, vista, exportaciones, permisos, jobs. Commits pequeños y descriptivos.
3. **PRUEBAS Y VALIDACIÓN.**
   - Pruebas de feature (Pest o PHPUnit, lo que use el repo) con datos semilla que ejerzan cada regla de negocio y cada criterio de aceptación de la sección I.
   - Pruebas de permisos: sin permiso → 403; alcance por empresa/proveedor respetado.
   - Script SQL de verificación que demuestre las identidades de cuadre del reporte.
   - Plan de ejecución en SQL Server con volumen realista; reporta tiempos y los índices creados.
   - Lista explícita de lo que quedó pendiente o fuera de alcance.

------------------------------------------------

================================================
REPORTE #27: RM-03 · Auditoría mensual de proveedores REPSE
================================================

# REPORTE RM-03: Auditoría mensual de proveedores REPSE

## A) ROL Y OBJETIVO

Actúa como desarrollador senior full-stack en el Portal de Proveedores (Módulo de Compras) de TotalGas, con experiencia en reporting financiero para Contabilidad, Contraloría y Tesorería en México (CFDI 4.0, SAT, REPSE).

**Objetivo medible:** Determinar por contrato REPSE y mes si el expediente está completo (nómina, ISR, IMSS, INFONAVIT, IVA), qué falta y desde cuándo, y disparar el semáforo automáticamente.

### Contexto técnico del proyecto
- Aplicación: Portal de Proveedores / Módulo de Compras de TotalGas (grupo gasolinero con 36 estaciones, Cd. Juárez, Chih.). Ya cubre requisiciones, cotizaciones, órdenes de compra, recepciones, control presupuestal y validación CFDI/SAT (RFC, listado 69-B). Cada centro de costo ya tiene responsable asignado.
- Stack: Laravel 12, PHP 8.3, SQL Server 2019, Blade + Bootstrap 5 + jQuery. Si el repositorio ya usa Livewire, DataTables, Laravel Excel u OpenSpout, spatie/laravel-permission o dompdf/snappy, REUTILÍZALOS. No agregues dependencias nuevas sin justificarlas en el plan.
- Zona horaria de negocio: `America/Ciudad_Juarez`. Toda "fecha de corte" se interpreta a las 23:59:59 de esa zona.
- Moneda funcional: MXN. Los nombres de tablas y campos de este documento son SUPUESTOS: el esquema real del repositorio manda.

### Reglas de trabajo (obligatorias)
1. Antes de escribir código, inspecciona migraciones, modelos Eloquent, vistas/funciones SQL, servicios y jobs existentes. Construye una tabla de mapeo: `campo requerido → tabla.campo real | NO EXISTE`.
2. No crees tablas paralelas para datos que ya existen con otro nombre. Si falta un dato, propón en el plan la migración mínima y aditiva, y espera aprobación.
3. Si un prerrequisito no existe, NO simules el dato (ni ceros, ni NULL silenciosos, ni valores fijos). Repórtalo y separa en el plan "lo que se puede entregar hoy" de "lo que requiere construir el control primero". Un reporte que cuadra con datos falsos es peor que no tener reporte.
4. Montos en `DECIMAL(18,2)`; tipos de cambio en `DECIMAL(18,6)`; nunca `FLOAT`. Redondea a 2 decimales a nivel de renglón y después suma; usa el mismo criterio que los demás reportes con los que este debe cuadrar.
5. Consultas siempre parametrizadas (bindings). Prohibido concatenar filtros en SQL.
6. El reporte no consulta en línea los linked servers de las estaciones; usa las tablas ya consolidadas en el servidor del portal.
7. Una sola fuente de verdad por cálculo: si otro reporte o el motor de control usa el mismo cálculo, encapsúlalo (vista, función de tabla en línea o clase de servicio) y reutilízalo. No dupliques fórmulas.
8. No cambies reglas de negocio existentes salvo lo que este documento pida explícitamente. Si detectas un bug, repórtalo en el plan en lugar de corregirlo en silencio.
9. Reglas fiscales (tasas, claves SAT, plazos): van en catálogos parametrizables, nunca en código, y requieren confirmación de Contabilidad/Fiscal antes de liberarse.

### Paso 0: prerrequisitos que debes verificar antes de programar

- Contratos REPSE con estaciones/instalaciones y trabajadores declarados.
- Carga por el proveedor de CFDI de nómina y acuses/pagos mensuales (ISR, IMSS, INFONAVIT, IVA). Probablemente no existe: es la funcionalidad base.
- Motor de semáforo RM-01.
- Tratamiento de datos personales de trabajadores (CURP, NSS, salarios): cifrado en reposo, acceso mínimo y aviso de privacidad.

Si alguno no existe, repórtalo en el plan con su impacto; no lo sustituyas con datos simulados.

## B) NOMBRE Y PROPÓSITO DEL REPORTE

- ID: `RM-03` · Dominio: G. Cumplimiento del proveedor y REPSE · Fase: F3
- Nombre técnico: `rpt_repse_monthly_audit`
- Nombre de negocio: Auditoría mensual de proveedores REPSE
- Frecuencia: Mensual
- Consumidor principal: Contraloría, Fiscal, Recursos Humanos
- Granularidad (una fila =): Una fila por contrato REPSE y mes, con detalle por trabajador asignado.

**Pregunta de negocio que responde / decisión que habilita:** El expediente que sostiene la deducción del ISR y el acreditamiento del IVA en servicios especializados, armado mes a mes en vez de reconstruido a las carreras cuando llega el requerimiento. Es también la base de las informativas cuatrimestrales ante IMSS e INFONAVIT.

**Requerimiento original de Contabilidad y Finanzas (campos mínimos):**

  • Proveedor · Folio REPSE y vigencia · Contrato · Estaciones o instalaciones donde presta el servicio
  • Trabajadores declarados · CFDI de nómina recibidos · Importe de nómina
  • Acuse y pago de retenciones de ISR · Cédulas y pago de IMSS · Pago de INFONAVIT
  • Declaración y pago de IVA del mes cobrado · Fecha límite de entrega
  • Estatus por requisito · Cobertura: trabajadores con nómina recibida entre declarados

## C) COLUMNAS / CAMPOS

Origen = SUPUESTO de nombre; mapea al esquema real en el Paso 0. O = obligatoria, Op = opcional.

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| contract_folio | NVARCHAR | contracts |  | texto | O |
| supplier_name / repse_folio / repse_valid_until | varios | suppliers / supplier_documents |  | texto / fecha | O |
| month | CHAR(7) |  |  | yyyy-mm | O |
| stations | NVARCHAR | contract_locations |  | texto | O |
| declared_workers | INT | repse_worker_rosters |  | número | O |
| payroll_cfdi_received / workers_with_payroll | INT | repse_payroll_cfdi |  | número | O |
| coverage_pct | DECIMAL(9,4) | calculada | workers_with_payroll / NULLIF(declared_workers,0) | % | O |
| payroll_amount | DECIMAL(18,2) | repse_payroll_cfdi |  | moneda | O |
| isr_status / imss_status / infonavit_status / vat_status | VARCHAR(12) | repse_monthly_requirements | COMPLETO / FALTANTE / VENCIDO | badge | O |
| deadline | DATE | parámetro | fecha límite de entrega del mes | fecha | O |
| missing_since | DATE | calculada |  | fecha | O |
| is_complete | BIT | calculada | todos los requisitos COMPLETO y cobertura 100 % | badge | O |

**Detalle expandible (segundo nivel):**

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| worker_name / nss_masked / payroll_uuids / payroll_amount | varios | repse_payroll_cfdi | NSS enmascarado salvo permiso |  | O |

## D) FILTROS Y PARÁMETROS

**Filtros fijos (siempre aplicados):**

- Contratos REPSE vigentes en el mes.

**Parámetros de entrada (según el Excel: Mes, proveedor, contrato, requisito faltante, estación.):**

| Parámetro | Tipo | Valor por defecto | O/Op |
|---|---|---|---|
| month | yyyy-mm | mes anterior | O |
| supplier_id | int | todos | Op |
| contract_id | int | todos | Op |
| missing_requirement | multi | todos | Op |
| station_id | int | todas | Op |

## E) AGRUPACIONES, ORDEN Y GRANULARIDAD

- Una fila por contrato y mes, detalle por trabajador.
- ORDER BY is_complete, missing_since.

## F) REGLAS DE NEGOCIO Y CÁLCULOS

- Un requisito faltante tras la fecha límite dispara el semáforo en RM-01 sin intervención humana.
- Cobertura < 100 % es incumplimiento aunque todos los acuses estén.
- Fecha límite y requisitos exigibles parametrizables; Fiscal/Recursos Humanos confirman.

## G) FORMATO DE SALIDA

- Matriz contrato × requisito con íconos.
- Portal del proveedor: checklist mensual de carga.

Convenciones comunes de salida:
- Pantalla: tabla con DataTables en modo server-side (paginación en servidor de 25/50/100 filas), encabezado fijo y primera columna congelada, panel de filtros arriba con los parámetros de la sección D y botón "Limpiar filtros".
- Excel (.xlsx) con celdas tipadas de verdad (moneda `$#,##0.00`, fechas `dd/mm/yyyy`, porcentaje `0.0%`), nunca números como texto; encabezado con nombre del reporte, empresa(s), filtros aplicados, fecha/hora de generación y usuario. Escritura en streaming (chunks), sin cargar todo en memoria.
- CSV UTF-8 con BOM y separador coma.
- Exportaciones de más de 50 000 filas se encolan como job y se notifica al usuario cuando el archivo está listo.
- La exportación respeta exactamente los filtros activos en pantalla.

## H) CONSIDERACIONES TÉCNICAS

**Tablas/vistas probables (SUPUESTO):** contracts, contract_locations, repse_worker_rosters, repse_payroll_cfdi, repse_monthly_requirements (nuevas), supplier_documents.

**Índices recomendados (valida contra los existentes antes de crear):**

- repse_monthly_requirements (contract_id, month).
- repse_payroll_cfdi (contract_id, month).

**Seguridad y permisos:**

- Permisos granulares: `reportes.repse_monthly_audit.ver` y `reportes.repse_monthly_audit.exportar` (crea los permisos con el mecanismo que ya use el repo).
- Alcance por empresa: el usuario solo ve las empresas que tiene asignadas; el filtro se aplica en el servidor (scope/policy), nunca solo en la UI.
- Cada exportación se registra en bitácora: usuario, fecha/hora, filtros, número de filas.

- Datos de trabajadores solo para Recursos Humanos y Contraloría; NSS/CURP enmascarados en exportaciones salvo permiso explícito.

**Endpoints y componentes:**

- GET /reportes/cumplimiento/repse (+ /data, /export).
- Portal proveedor: POST /proveedor/repse/{contrato}/{mes}/documentos.

## I) CRITERIOS DE ACEPTACIÓN (Definition of Done)

Criterio de negocio original: _Por cada mes y contrato el sistema determina si el expediente está completo y, si no, indica qué falta y desde cuándo. El faltante dispara el semáforo sin intervención humana._

- [ ] Por cada contrato y mes el sistema indica si está completo y, si no, qué falta y desde cuándo.
- [ ] El faltante dispara el semáforo automáticamente (prueba con fecha simulada).
- [ ] Todos los filtros de la sección D funcionan combinados y la exportación reproduce exactamente la vista filtrada.
- [ ] Usuarios sin permiso reciben 403 y ningún usuario ve empresas fuera de su alcance.
- [ ] La consulta principal responde en menos de 3 s con el volumen de un año de operación (o se justifica el snapshot/job).

**Preguntas abiertas (aplica el SUPUESTO indicado si no hay respuesta):**

- Requisitos exigibles exactos y fecha límite: pendiente de Fiscal/RH.

## J) ENTREGABLE ESPERADO

Trabaja en tres entregas y DETENTE al terminar la primera:

1. **PLAN (sin código).**
   - Tabla de mapeo de esquema (campo requerido → tabla.campo real | NO EXISTE).
   - Estado de cada prerrequisito del Paso 0: existe / parcial / no existe, con su impacto en el reporte.
   - Arquitectura: archivos a crear o modificar (migraciones, vista o función SQL, clase de servicio del reporte, FormRequest de parámetros, controlador, rutas, vista Blade/JS, clase de exportación, policy/permisos, jobs y scheduler si aplica).
   - SQL propuesto (borrador) de la consulta principal.
   - Preguntas abiertas para Contraloría/Contabilidad/Fiscal, cada una con el supuesto que aplicarás si no hay respuesta.
   - Espera mi aprobación antes de continuar.
2. **CÓDIGO.** Implementa exactamente lo aprobado: migraciones aditivas, consulta encapsulada, servicio, endpoint, vista, exportaciones, permisos, jobs. Commits pequeños y descriptivos.
3. **PRUEBAS Y VALIDACIÓN.**
   - Pruebas de feature (Pest o PHPUnit, lo que use el repo) con datos semilla que ejerzan cada regla de negocio y cada criterio de aceptación de la sección I.
   - Pruebas de permisos: sin permiso → 403; alcance por empresa/proveedor respetado.
   - Script SQL de verificación que demuestre las identidades de cuadre del reporte.
   - Plan de ejecución en SQL Server con volumen realista; reporta tiempos y los índices creados.
   - Lista explícita de lo que quedó pendiente o fuera de alcance.

------------------------------------------------

================================================
REPORTE #28: RM-04 · Monitoreo del listado 69-B y proveedores bloqueados
================================================

# REPORTE RM-04: Monitoreo del listado 69-B y proveedores bloqueados

## A) ROL Y OBJETIVO

Actúa como desarrollador senior full-stack en el Portal de Proveedores (Módulo de Compras) de TotalGas, con experiencia en reporting financiero para Contabilidad, Contraloría y Tesorería en México (CFDI 4.0, SAT, REPSE).

**Objetivo medible:** Cotejar diariamente el padrón de proveedores contra la publicación oficial vigente del listado 69-B, conservar el historial de cada publicación y cuantificar la exposición histórica (IVA acreditado e ISR deducido) por ejercicio.

### Contexto técnico del proyecto
- Aplicación: Portal de Proveedores / Módulo de Compras de TotalGas (grupo gasolinero con 36 estaciones, Cd. Juárez, Chih.). Ya cubre requisiciones, cotizaciones, órdenes de compra, recepciones, control presupuestal y validación CFDI/SAT (RFC, listado 69-B). Cada centro de costo ya tiene responsable asignado.
- Stack: Laravel 12, PHP 8.3, SQL Server 2019, Blade + Bootstrap 5 + jQuery. Si el repositorio ya usa Livewire, DataTables, Laravel Excel u OpenSpout, spatie/laravel-permission o dompdf/snappy, REUTILÍZALOS. No agregues dependencias nuevas sin justificarlas en el plan.
- Zona horaria de negocio: `America/Ciudad_Juarez`. Toda "fecha de corte" se interpreta a las 23:59:59 de esa zona.
- Moneda funcional: MXN. Los nombres de tablas y campos de este documento son SUPUESTOS: el esquema real del repositorio manda.

### Reglas de trabajo (obligatorias)
1. Antes de escribir código, inspecciona migraciones, modelos Eloquent, vistas/funciones SQL, servicios y jobs existentes. Construye una tabla de mapeo: `campo requerido → tabla.campo real | NO EXISTE`.
2. No crees tablas paralelas para datos que ya existen con otro nombre. Si falta un dato, propón en el plan la migración mínima y aditiva, y espera aprobación.
3. Si un prerrequisito no existe, NO simules el dato (ni ceros, ni NULL silenciosos, ni valores fijos). Repórtalo y separa en el plan "lo que se puede entregar hoy" de "lo que requiere construir el control primero". Un reporte que cuadra con datos falsos es peor que no tener reporte.
4. Montos en `DECIMAL(18,2)`; tipos de cambio en `DECIMAL(18,6)`; nunca `FLOAT`. Redondea a 2 decimales a nivel de renglón y después suma; usa el mismo criterio que los demás reportes con los que este debe cuadrar.
5. Consultas siempre parametrizadas (bindings). Prohibido concatenar filtros en SQL.
6. El reporte no consulta en línea los linked servers de las estaciones; usa las tablas ya consolidadas en el servidor del portal.
7. Una sola fuente de verdad por cálculo: si otro reporte o el motor de control usa el mismo cálculo, encapsúlalo (vista, función de tabla en línea o clase de servicio) y reutilízalo. No dupliques fórmulas.
8. No cambies reglas de negocio existentes salvo lo que este documento pida explícitamente. Si detectas un bug, repórtalo en el plan en lugar de corregirlo en silencio.
9. Reglas fiscales (tasas, claves SAT, plazos): van en catálogos parametrizables, nunca en código, y requieren confirmación de Contabilidad/Fiscal antes de liberarse.

### Paso 0: prerrequisitos que debes verificar antes de programar

- Validación 69-B existente en el portal: localízala y reutiliza la descarga/carga del listado.
- Historial de operaciones por proveedor con importes e IVA por ejercicio.
- Conservación de cada archivo descargado con su hash (evidencia).

Si alguno no existe, repórtalo en el plan con su impacto; no lo sustituyas con datos simulados.

## B) NOMBRE Y PROPÓSITO DEL REPORTE

- ID: `RM-04` · Dominio: G. Cumplimiento del proveedor y REPSE · Fase: F3
- Nombre técnico: `rpt_69b_monitoring`
- Nombre de negocio: Monitoreo del listado 69-B y proveedores bloqueados
- Frecuencia: Diaria
- Consumidor principal: Contraloría, Fiscal, Dirección
- Granularidad (una fila =): Una fila por coincidencia entre un RFC del padrón y una publicación del SAT.

**Pregunta de negocio que responde / decisión que habilita:** La cuantificación inmediata de la exposición cuando un proveedor aparece en el listado, incluyendo operaciones de ejercicios anteriores, que es donde está el riesgo real y no en la factura que estaba por pagarse.

**Requerimiento original de Contabilidad y Finanzas (campos mínimos):**

  • RFC · Razón social · Situación (presunto, definitivo, desvirtuado, sentencia favorable)
  • Fecha de publicación · Número de oficio
  • Operaciones históricas con ese proveedor: número, importe, ejercicios involucrados
  • IVA acreditado e ISR deducido expuestos · Acción tomada · Plazo para corregir

## C) COLUMNAS / CAMPOS

Origen = SUPUESTO de nombre; mapea al esquema real en el Paso 0. O = obligatoria, Op = opcional.

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| supplier_rfc / supplier_name | NVARCHAR | suppliers |  | texto | O |
| situation | VARCHAR(20) | sat_69b_entries | PRESUNTO / DEFINITIVO / DESVIRTUADO / SENTENCIA_FAVORABLE | badge | O |
| publication_date / oficio_number | DATE / NVARCHAR | sat_69b_entries |  | fecha / texto | O |
| first_seen_at | DATETIME2 | sat_69b_matches |  | fecha-hora | O |
| ops_count / ops_amount | INT / DECIMAL(18,2) | invoices | todas las operaciones históricas | número / moneda | O |
| fiscal_years | NVARCHAR | calculada | ejercicios involucrados | texto | O |
| vat_credited_exposed / isr_deducted_exposed | DECIMAL(18,2) | invoices + payments | IVA acreditado y subtotal deducido de operaciones pagadas | moneda | O |
| action_taken | NVARCHAR | sat_69b_matches |  | texto | O |
| correction_deadline | DATE | parámetro | plazo para acreditar materialidad o corregir (Fiscal confirma) | fecha | O |

## D) FILTROS Y PARÁMETROS

**Filtros fijos (siempre aplicados):**

- Solo RFC del padrón que coinciden con alguna publicación.

**Parámetros de entrada (según el Excel: Situación, ejercicio, importe expuesto, empresa.):**

| Parámetro | Tipo | Valor por defecto | O/Op |
|---|---|---|---|
| situation | multi | todas | Op |
| fiscal_year | int | todos | Op |
| exposed_amount_greater_than | decimal | 0 | Op |
| company_ids | array<int> | empresas del usuario | O |

## E) AGRUPACIONES, ORDEN Y GRANULARIDAD

- Una fila por coincidencia RFC ↔ publicación.
- ORDER BY situación (DEFINITIVO primero), exposición DESC.

## F) REGLAS DE NEGOCIO Y CÁLCULOS

- Job diario descarga la publicación oficial vigente, guarda el archivo con hash y fecha, y compara contra la versión anterior (altas y cambios de situación).
- DEFINITIVO → bloqueo inmediato del proveedor (RM-01) y notificación a Contraloría y Dirección.
- La exposición incluye ejercicios anteriores; se desglosa por ejercicio.
- No se borra historial: cada publicación y cada cambio de situación queda registrado.

## G) FORMATO DE SALIDA

- Tarjetas: # coincidencias por situación, exposición total.
- Detalle por proveedor con operaciones por ejercicio.

Convenciones comunes de salida:
- Pantalla: tabla con DataTables en modo server-side (paginación en servidor de 25/50/100 filas), encabezado fijo y primera columna congelada, panel de filtros arriba con los parámetros de la sección D y botón "Limpiar filtros".
- Excel (.xlsx) con celdas tipadas de verdad (moneda `$#,##0.00`, fechas `dd/mm/yyyy`, porcentaje `0.0%`), nunca números como texto; encabezado con nombre del reporte, empresa(s), filtros aplicados, fecha/hora de generación y usuario. Escritura en streaming (chunks), sin cargar todo en memoria.
- CSV UTF-8 con BOM y separador coma.
- Exportaciones de más de 50 000 filas se encolan como job y se notifica al usuario cuando el archivo está listo.
- La exportación respeta exactamente los filtros activos en pantalla.

## H) CONSIDERACIONES TÉCNICAS

**Tablas/vistas probables (SUPUESTO):** sat_69b_downloads, sat_69b_entries, sat_69b_matches (verifica las existentes), suppliers, invoices, payments.

**Índices recomendados (valida contra los existentes antes de crear):**

- sat_69b_entries (rfc, download_id).
- invoices (supplier_id, issued_at) INCLUDE (subtotal, vat, status).

**Seguridad y permisos:**

- Permisos granulares: `reportes.69b_monitoring.ver` y `reportes.69b_monitoring.exportar` (crea los permisos con el mecanismo que ya use el repo).
- Alcance por empresa: el usuario solo ve las empresas que tiene asignadas; el filtro se aplica en el servidor (scope/policy), nunca solo en la UI.
- Cada exportación se registra en bitácora: usuario, fecha/hora, filtros, número de filas.

- Contraloría, Fiscal, Dirección.

**Endpoints y componentes:**

- GET /reportes/cumplimiento/69b (+ /data, /export).
- Job diario `Sat69bSyncJob`.

## I) CRITERIOS DE ACEPTACIÓN (Definition of Done)

Criterio de negocio original: _El cotejo corre contra la publicación oficial vigente al día y conserva el historial de cada publicación. Una coincidencia como definitivo bloquea de inmediato y notifica a Contraloría y Dirección._

- [ ] El cotejo corre contra la publicación vigente del día y conserva el historial.
- [ ] Una coincidencia como DEFINITIVO bloquea y notifica de inmediato (prueba).
- [ ] Todos los filtros de la sección D funcionan combinados y la exportación reproduce exactamente la vista filtrada.
- [ ] Usuarios sin permiso reciben 403 y ningún usuario ve empresas fuera de su alcance.
- [ ] La consulta principal responde en menos de 3 s con el volumen de un año de operación (o se justifica el snapshot/job).

**Preguntas abiertas (aplica el SUPUESTO indicado si no hay respuesta):**

- Plazo de corrección a parametrizar: Fiscal confirma.

## J) ENTREGABLE ESPERADO

Trabaja en tres entregas y DETENTE al terminar la primera:

1. **PLAN (sin código).**
   - Tabla de mapeo de esquema (campo requerido → tabla.campo real | NO EXISTE).
   - Estado de cada prerrequisito del Paso 0: existe / parcial / no existe, con su impacto en el reporte.
   - Arquitectura: archivos a crear o modificar (migraciones, vista o función SQL, clase de servicio del reporte, FormRequest de parámetros, controlador, rutas, vista Blade/JS, clase de exportación, policy/permisos, jobs y scheduler si aplica).
   - SQL propuesto (borrador) de la consulta principal.
   - Preguntas abiertas para Contraloría/Contabilidad/Fiscal, cada una con el supuesto que aplicarás si no hay respuesta.
   - Espera mi aprobación antes de continuar.
2. **CÓDIGO.** Implementa exactamente lo aprobado: migraciones aditivas, consulta encapsulada, servicio, endpoint, vista, exportaciones, permisos, jobs. Commits pequeños y descriptivos.
3. **PRUEBAS Y VALIDACIÓN.**
   - Pruebas de feature (Pest o PHPUnit, lo que use el repo) con datos semilla que ejerzan cada regla de negocio y cada criterio de aceptación de la sección I.
   - Pruebas de permisos: sin permiso → 403; alcance por empresa/proveedor respetado.
   - Script SQL de verificación que demuestre las identidades de cuadre del reporte.
   - Plan de ejecución en SQL Server con volumen realista; reporta tiempos y los índices creados.
   - Lista explícita de lo que quedó pendiente o fuera de alcance.

------------------------------------------------

================================================
REPORTE #29: RA-01 · Bitácora de excepciones, liberaciones y overrides
================================================

# REPORTE RA-01: Bitácora de excepciones, liberaciones y overrides

## A) ROL Y OBJETIVO

Actúa como desarrollador senior full-stack en el Portal de Proveedores (Módulo de Compras) de TotalGas, con experiencia en reporting financiero para Contabilidad, Contraloría y Tesorería en México (CFDI 4.0, SAT, REPSE).

**Objetivo medible:** Registrar y mostrar cada acción que anuló, saltó o relajó un control, con usuario, rol, IP, importe, motivo no genérico, autorizador y evidencia, de forma que ningún control pueda relajarse sin dejar registro.

### Contexto técnico del proyecto
- Aplicación: Portal de Proveedores / Módulo de Compras de TotalGas (grupo gasolinero con 36 estaciones, Cd. Juárez, Chih.). Ya cubre requisiciones, cotizaciones, órdenes de compra, recepciones, control presupuestal y validación CFDI/SAT (RFC, listado 69-B). Cada centro de costo ya tiene responsable asignado.
- Stack: Laravel 12, PHP 8.3, SQL Server 2019, Blade + Bootstrap 5 + jQuery. Si el repositorio ya usa Livewire, DataTables, Laravel Excel u OpenSpout, spatie/laravel-permission o dompdf/snappy, REUTILÍZALOS. No agregues dependencias nuevas sin justificarlas en el plan.
- Zona horaria de negocio: `America/Ciudad_Juarez`. Toda "fecha de corte" se interpreta a las 23:59:59 de esa zona.
- Moneda funcional: MXN. Los nombres de tablas y campos de este documento son SUPUESTOS: el esquema real del repositorio manda.

### Reglas de trabajo (obligatorias)
1. Antes de escribir código, inspecciona migraciones, modelos Eloquent, vistas/funciones SQL, servicios y jobs existentes. Construye una tabla de mapeo: `campo requerido → tabla.campo real | NO EXISTE`.
2. No crees tablas paralelas para datos que ya existen con otro nombre. Si falta un dato, propón en el plan la migración mínima y aditiva, y espera aprobación.
3. Si un prerrequisito no existe, NO simules el dato (ni ceros, ni NULL silenciosos, ni valores fijos). Repórtalo y separa en el plan "lo que se puede entregar hoy" de "lo que requiere construir el control primero". Un reporte que cuadra con datos falsos es peor que no tener reporte.
4. Montos en `DECIMAL(18,2)`; tipos de cambio en `DECIMAL(18,6)`; nunca `FLOAT`. Redondea a 2 decimales a nivel de renglón y después suma; usa el mismo criterio que los demás reportes con los que este debe cuadrar.
5. Consultas siempre parametrizadas (bindings). Prohibido concatenar filtros en SQL.
6. El reporte no consulta en línea los linked servers de las estaciones; usa las tablas ya consolidadas en el servidor del portal.
7. Una sola fuente de verdad por cálculo: si otro reporte o el motor de control usa el mismo cálculo, encapsúlalo (vista, función de tabla en línea o clase de servicio) y reutilízalo. No dupliques fórmulas.
8. No cambies reglas de negocio existentes salvo lo que este documento pida explícitamente. Si detectas un bug, repórtalo en el plan en lugar de corregirlo en silencio.
9. Reglas fiscales (tasas, claves SAT, plazos): van en catálogos parametrizables, nunca en código, y requieren confirmación de Contabilidad/Fiscal antes de liberarse.

### Paso 0: prerrequisitos que debes verificar antes de programar

- Localizar TODOS los puntos del código donde hoy se relaja un control (sobregiro, liberación fiscal, tolerancia, pago urgente, alta de proveedor incompleta). Cada uno debe pasar por un único servicio `ControlOverride::record()`.
- Captura de IP real: si el portal está detrás de Cloudflare/proxy, configurar TrustProxies (CF-Connecting-IP) o se registrará la IP del proxy.
- IMPORTANTE: RP-03 y RF-02 (F1) dependen de esta tabla; construye la tabla y el servicio en F1 aunque el reporte sea F2.

Si alguno no existe, repórtalo en el plan con su impacto; no lo sustituyas con datos simulados.

## B) NOMBRE Y PROPÓSITO DEL REPORTE

- ID: `RA-01` · Dominio: H. Auditoría interna e indicadores · Fase: F2
- Nombre técnico: `rpt_control_overrides_log`
- Nombre de negocio: Bitácora de excepciones, liberaciones y overrides
- Frecuencia: Continua
- Consumidor principal: Contraloría, auditoría interna
- Granularidad (una fila =): Una fila por acción que anuló, saltó o relajó un control.

**Pregunta de negocio que responde / decisión que habilita:** El registro de todas las veces que el proceso cedió, con monto y responsable. Es el primer reporte que pide un auditor y, por sí solo, hace que se piense dos veces antes de pedir la excepción.

**Requerimiento original de Contabilidad y Finanzas (campos mínimos):**

  • Fecha y hora · Usuario · Rol · Dirección IP
  • Tipo de override: sobregiro presupuestal, liberación de bloqueo fiscal, desviación de tolerancia, pago urgente fuera de programación, alta de proveedor con expediente incompleto
  • Documento afectado · Importe · Motivo capturado · Autorizador · Evidencia adjunta

## C) COLUMNAS / CAMPOS

Origen = SUPUESTO de nombre; mapea al esquema real en el Paso 0. O = obligatoria, Op = opcional.

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| occurred_at | DATETIME2 | override_logs |  | fecha-hora | O |
| user_name / role | NVARCHAR | users / roles | rol vigente al momento | texto | O |
| ip_address | VARCHAR(45) | override_logs | IPv4/IPv6 | texto | O |
| override_type | VARCHAR(30) | override_logs | SOBREGIRO_PRESUPUESTAL / LIBERACION_BLOQUEO_FISCAL / DESVIACION_TOLERANCIA / PAGO_URGENTE_FUERA_PROGRAMA / ALTA_PROVEEDOR_INCOMPLETA / EXCEPCION_SEMAFORO | badge | O |
| document_type / document_folio | VARCHAR / NVARCHAR | override_logs |  | liga | O |
| amount_mxn | DECIMAL(18,2) | override_logs |  | moneda | O |
| reason | NVARCHAR(1000) | override_logs |  | texto | O |
| authorizer | NVARCHAR | users | distinto del solicitante | texto | O |
| evidence | NVARCHAR | attachments |  | liga | O |
| before_state / after_state | NVARCHAR(MAX) JSON | override_logs | estado del control antes y después | JSON | Op |

## D) FILTROS Y PARÁMETROS

**Filtros fijos (siempre aplicados):**

- Todos los registros (tabla append-only).

**Parámetros de entrada (según el Excel: Tipo, usuario, periodo, importe, empresa.):**

| Parámetro | Tipo | Valor por defecto | O/Op |
|---|---|---|---|
| override_type | multi | todos | Op |
| user_id | int | todos | Op |
| date_from / date_to | date | mes en curso | O |
| amount_greater_than | decimal | 0 | Op |
| company_ids | array<int> | empresas del usuario | O |

## E) AGRUPACIONES, ORDEN Y GRANULARIDAD

- Una fila por acción.
- ORDER BY occurred_at DESC.
- Resumen por tipo, por usuario y por autorizador (conteo e importe).

## F) REGLAS DE NEGOCIO Y CÁLCULOS

- Append-only: el usuario de BD de la aplicación no tiene UPDATE/DELETE sobre la tabla (DENY) o un trigger lo impide.
- Motivo obligatorio con validación: longitud mínima (default 20 caracteres) y lista negra de textos genéricos ('ok', 'urgente', 'autorizado', 'n/a', 'se autoriza'…), parametrizable.
- Autorizador obligatorio y distinto del usuario que ejecuta.
- Prueba de arquitectura: búsqueda en el código de cada relajación de control; todas deben llamar al servicio.

## G) FORMATO DE SALIDA

- Tarjetas: # e importe por tipo en el periodo.
- Tabla con liga al documento y a la evidencia.
- Excel para auditoría.

Convenciones comunes de salida:
- Pantalla: tabla con DataTables en modo server-side (paginación en servidor de 25/50/100 filas), encabezado fijo y primera columna congelada, panel de filtros arriba con los parámetros de la sección D y botón "Limpiar filtros".
- Excel (.xlsx) con celdas tipadas de verdad (moneda `$#,##0.00`, fechas `dd/mm/yyyy`, porcentaje `0.0%`), nunca números como texto; encabezado con nombre del reporte, empresa(s), filtros aplicados, fecha/hora de generación y usuario. Escritura en streaming (chunks), sin cargar todo en memoria.
- CSV UTF-8 con BOM y separador coma.
- Exportaciones de más de 50 000 filas se encolan como job y se notifica al usuario cuando el archivo está listo.
- La exportación respeta exactamente los filtros activos en pantalla.

## H) CONSIDERACIONES TÉCNICAS

**Tablas/vistas probables (SUPUESTO):** override_logs (nueva), users, roles, attachments.

**Índices recomendados (valida contra los existentes antes de crear):**

- override_logs (occurred_at) INCLUDE (override_type, amount_mxn, user_id, authorizer_id).
- override_logs (document_type, document_id).

**Seguridad y permisos:**

- Permisos granulares: `reportes.control_overrides_log.ver` y `reportes.control_overrides_log.exportar` (crea los permisos con el mecanismo que ya use el repo).
- Alcance por empresa: el usuario solo ve las empresas que tiene asignadas; el filtro se aplica en el servidor (scope/policy), nunca solo en la UI.
- Cada exportación se registra en bitácora: usuario, fecha/hora, filtros, número de filas.

- Solo Contraloría y auditoría interna. Nadie (ni administradores de la app) puede editar o borrar.

**Endpoints y componentes:**

- GET /reportes/auditoria/overrides (+ /data, /export).
- Servicio `App\Services\Control\ControlOverride`.

## I) CRITERIOS DE ACEPTACIÓN (Definition of Done)

Criterio de negocio original: _Ningún control puede relajarse por vía técnica sin dejar registro. Motivo y autorizador son obligatorios y no admiten texto vacío o genérico._

- [ ] Ningún control puede relajarse por vía técnica sin dejar registro (prueba por cada tipo).
- [ ] Motivo y autorizador son obligatorios y rechazan texto vacío o genérico.
- [ ] UPDATE/DELETE sobre la tabla falla desde la conexión de la app.
- [ ] Todos los filtros de la sección D funcionan combinados y la exportación reproduce exactamente la vista filtrada.
- [ ] Usuarios sin permiso reciben 403 y ningún usuario ve empresas fuera de su alcance.
- [ ] La consulta principal responde en menos de 3 s con el volumen de un año de operación (o se justifica el snapshot/job).

## J) ENTREGABLE ESPERADO

Trabaja en tres entregas y DETENTE al terminar la primera:

1. **PLAN (sin código).**
   - Tabla de mapeo de esquema (campo requerido → tabla.campo real | NO EXISTE).
   - Estado de cada prerrequisito del Paso 0: existe / parcial / no existe, con su impacto en el reporte.
   - Arquitectura: archivos a crear o modificar (migraciones, vista o función SQL, clase de servicio del reporte, FormRequest de parámetros, controlador, rutas, vista Blade/JS, clase de exportación, policy/permisos, jobs y scheduler si aplica).
   - SQL propuesto (borrador) de la consulta principal.
   - Preguntas abiertas para Contraloría/Contabilidad/Fiscal, cada una con el supuesto que aplicarás si no hay respuesta.
   - Espera mi aprobación antes de continuar.
2. **CÓDIGO.** Implementa exactamente lo aprobado: migraciones aditivas, consulta encapsulada, servicio, endpoint, vista, exportaciones, permisos, jobs. Commits pequeños y descriptivos.
3. **PRUEBAS Y VALIDACIÓN.**
   - Pruebas de feature (Pest o PHPUnit, lo que use el repo) con datos semilla que ejerzan cada regla de negocio y cada criterio de aceptación de la sección I.
   - Pruebas de permisos: sin permiso → 403; alcance por empresa/proveedor respetado.
   - Script SQL de verificación que demuestre las identidades de cuadre del reporte.
   - Plan de ejecución en SQL Server con volumen realista; reporta tiempos y los índices creados.
   - Lista explícita de lo que quedó pendiente o fuera de alcance.

------------------------------------------------

================================================
REPORTE #30: RA-02 · Segregación de funciones e integridad de accesos
================================================

# REPORTE RA-02: Segregación de funciones e integridad de accesos

## A) ROL Y OBJETIVO

Actúa como desarrollador senior full-stack en el Portal de Proveedores (Módulo de Compras) de TotalGas, con experiencia en reporting financiero para Contabilidad, Contraloría y Tesorería en México (CFDI 4.0, SAT, REPSE).

**Objetivo medible:** Detectar combinaciones de permisos incompatibles, registrar los intentos bloqueados en tiempo de ejecución y exhibir altas, bajas, cambios de rol y cuentas inactivas, incluidos accesos de proveedores.

### Contexto técnico del proyecto
- Aplicación: Portal de Proveedores / Módulo de Compras de TotalGas (grupo gasolinero con 36 estaciones, Cd. Juárez, Chih.). Ya cubre requisiciones, cotizaciones, órdenes de compra, recepciones, control presupuestal y validación CFDI/SAT (RFC, listado 69-B). Cada centro de costo ya tiene responsable asignado.
- Stack: Laravel 12, PHP 8.3, SQL Server 2019, Blade + Bootstrap 5 + jQuery. Si el repositorio ya usa Livewire, DataTables, Laravel Excel u OpenSpout, spatie/laravel-permission o dompdf/snappy, REUTILÍZALOS. No agregues dependencias nuevas sin justificarlas en el plan.
- Zona horaria de negocio: `America/Ciudad_Juarez`. Toda "fecha de corte" se interpreta a las 23:59:59 de esa zona.
- Moneda funcional: MXN. Los nombres de tablas y campos de este documento son SUPUESTOS: el esquema real del repositorio manda.

### Reglas de trabajo (obligatorias)
1. Antes de escribir código, inspecciona migraciones, modelos Eloquent, vistas/funciones SQL, servicios y jobs existentes. Construye una tabla de mapeo: `campo requerido → tabla.campo real | NO EXISTE`.
2. No crees tablas paralelas para datos que ya existen con otro nombre. Si falta un dato, propón en el plan la migración mínima y aditiva, y espera aprobación.
3. Si un prerrequisito no existe, NO simules el dato (ni ceros, ni NULL silenciosos, ni valores fijos). Repórtalo y separa en el plan "lo que se puede entregar hoy" de "lo que requiere construir el control primero". Un reporte que cuadra con datos falsos es peor que no tener reporte.
4. Montos en `DECIMAL(18,2)`; tipos de cambio en `DECIMAL(18,6)`; nunca `FLOAT`. Redondea a 2 decimales a nivel de renglón y después suma; usa el mismo criterio que los demás reportes con los que este debe cuadrar.
5. Consultas siempre parametrizadas (bindings). Prohibido concatenar filtros en SQL.
6. El reporte no consulta en línea los linked servers de las estaciones; usa las tablas ya consolidadas en el servidor del portal.
7. Una sola fuente de verdad por cálculo: si otro reporte o el motor de control usa el mismo cálculo, encapsúlalo (vista, función de tabla en línea o clase de servicio) y reutilízalo. No dupliques fórmulas.
8. No cambies reglas de negocio existentes salvo lo que este documento pida explícitamente. Si detectas un bug, repórtalo en el plan en lugar de corregirlo en silencio.
9. Reglas fiscales (tasas, claves SAT, plazos): van en catálogos parametrizables, nunca en código, y requieren confirmación de Contabilidad/Fiscal antes de liberarse.

### Paso 0: prerrequisitos que debes verificar antes de programar

- Matriz de incompatibilidades (SUPUESTO inicial: solicitar ↔ aprobar la misma requisición; emitir OC ↔ recibir; recibir ↔ validar recepción; alta de proveedor ↔ alta de cuenta bancaria ↔ autorizar pago; capturar OC ↔ liberar desviación).
- Auditoría de altas, bajas y cambios de rol (verifica si spatie/permission u otra librería lo registra; si no, agrega auditoría).
- Registro de último acceso.
- Si los usuarios internos se autentican contra Active Directory, cruzar cuentas activas en el portal contra cuentas deshabilitadas en AD.

Si alguno no existe, repórtalo en el plan con su impacto; no lo sustituyas con datos simulados.

## B) NOMBRE Y PROPÓSITO DEL REPORTE

- ID: `RA-02` · Dominio: H. Auditoría interna e indicadores · Fase: F3
- Nombre técnico: `rpt_segregation_of_duties`
- Nombre de negocio: Segregación de funciones e integridad de accesos
- Frecuencia: Mensual
- Consumidor principal: Contraloría, Sistemas, auditoría
- Granularidad (una fila =): Una fila por usuario con combinación de permisos incompatibles y una por evento de conflicto detectado.

**Pregunta de negocio que responde / decisión que habilita:** La verificación de que la matriz de roles no se degradó con el uso, que es exactamente lo que pasa cuando alguien sale de vacaciones y se presta un perfil.

**Requerimiento original de Contabilidad y Finanzas (campos mínimos):**

  • Usuario · Roles asignados · Combinaciones incompatibles detectadas
  • Documentos donde el conflicto se materializó o intentos bloqueados
  • Altas y bajas de usuarios del periodo · Cambios de rol · Accesos de proveedores
  • Último acceso · Cuentas inactivas

## C) COLUMNAS / CAMPOS

Origen = SUPUESTO de nombre; mapea al esquema real en el Paso 0. O = obligatoria, Op = opcional.

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| user_name / user_type | NVARCHAR / VARCHAR | users | INTERNO / PROVEEDOR | texto | O |
| roles | NVARCHAR | model_has_roles |  | texto | O |
| conflict_code / conflict_description / severity | varios | sod_rules |  | texto / badge | O |
| materialized_docs | INT | calculada | documentos donde el mismo usuario ejecutó ambos pasos | número con liga | O |
| blocked_attempts | INT | sod_blocked_attempts | intentos impedidos en tiempo de ejecución | número | O |
| created_at / disabled_at | DATETIME2 | users |  | fecha | O |
| role_changes_in_period | INT | audit |  | número | O |
| last_login_at / inactive_days | DATETIME2 / INT | users |  | fecha / número | O |
| ad_status | VARCHAR(10) | Active Directory | ACTIVA / DESHABILITADA / NO_ENCONTRADA | badge | Op |

## D) FILTROS Y PARÁMETROS

**Filtros fijos (siempre aplicados):**

- Usuarios con al menos un conflicto, evento en el periodo o inactividad ≥ umbral.

**Parámetros de entrada (según el Excel: Tipo de conflicto, empresa, periodo, rol.):**

| Parámetro | Tipo | Valor por defecto | O/Op |
|---|---|---|---|
| conflict_code | multi | todos | Op |
| company_ids | array<int> | empresas del usuario | O |
| date_from / date_to | date | mes anterior | O |
| role | multi | todos | Op |
| inactive_days_threshold | int | 90 | Op |

## E) AGRUPACIONES, ORDEN Y GRANULARIDAD

- Vista 1: una fila por usuario con combinación incompatible.
- Vista 2: una fila por evento de conflicto (materializado o bloqueado).
- Vista 3: altas/bajas/cambios de rol y cuentas inactivas.

## F) REGLAS DE NEGOCIO Y CÁLCULOS

- Enforcement en tiempo de ejecución: un Gate/Policy global verifica, antes de cada paso del flujo, que el usuario no haya ejecutado un paso incompatible sobre el mismo documento; si sí, bloquea y registra en `sod_blocked_attempts`.
- La matriz es administrable (tabla `sod_rules`), no código.
- Cuentas de proveedores sin acceso en N días: se listan para desactivación.

## G) FORMATO DE SALIDA

- Tarjetas: usuarios con conflicto, intentos bloqueados, cuentas inactivas.
- Tabla por vista.

Convenciones comunes de salida:
- Pantalla: tabla con DataTables en modo server-side (paginación en servidor de 25/50/100 filas), encabezado fijo y primera columna congelada, panel de filtros arriba con los parámetros de la sección D y botón "Limpiar filtros".
- Excel (.xlsx) con celdas tipadas de verdad (moneda `$#,##0.00`, fechas `dd/mm/yyyy`, porcentaje `0.0%`), nunca números como texto; encabezado con nombre del reporte, empresa(s), filtros aplicados, fecha/hora de generación y usuario. Escritura en streaming (chunks), sin cargar todo en memoria.
- CSV UTF-8 con BOM y separador coma.
- Exportaciones de más de 50 000 filas se encolan como job y se notifica al usuario cuando el archivo está listo.
- La exportación respeta exactamente los filtros activos en pantalla.

## H) CONSIDERACIONES TÉCNICAS

**Tablas/vistas probables (SUPUESTO):** users, roles, permissions, model_has_roles, sod_rules, sod_blocked_attempts (nuevas), activity/audit log existente.

**Índices recomendados (valida contra los existentes antes de crear):**

- sod_blocked_attempts (occurred_at, user_id).
- Auditoría de roles por (model_id, created_at).

**Seguridad y permisos:**

- Permisos granulares: `reportes.segregation_of_duties.ver` y `reportes.segregation_of_duties.exportar` (crea los permisos con el mecanismo que ya use el repo).
- Alcance por empresa: el usuario solo ve las empresas que tiene asignadas; el filtro se aplica en el servidor (scope/policy), nunca solo en la UI.
- Cada exportación se registra en bitácora: usuario, fecha/hora, filtros, número de filas.

- Contraloría, Sistemas y auditoría.

**Endpoints y componentes:**

- GET /reportes/auditoria/segregacion (+ /data, /export).

## I) CRITERIOS DE ACEPTACIÓN (Definition of Done)

Criterio de negocio original: _El sistema impide en tiempo de ejecución que un mismo usuario ejecute dos pasos incompatibles sobre un mismo documento, y el reporte lista los intentos bloqueados, no solo los conflictos potenciales._

- [ ] El sistema impide en tiempo de ejecución que un usuario ejecute dos pasos incompatibles sobre el mismo documento (prueba por cada regla).
- [ ] El reporte lista los intentos bloqueados, no solo los conflictos potenciales.
- [ ] Todos los filtros de la sección D funcionan combinados y la exportación reproduce exactamente la vista filtrada.
- [ ] Usuarios sin permiso reciben 403 y ningún usuario ve empresas fuera de su alcance.
- [ ] La consulta principal responde en menos de 3 s con el volumen de un año de operación (o se justifica el snapshot/job).

**Preguntas abiertas (aplica el SUPUESTO indicado si no hay respuesta):**

- Matriz de incompatibilidades definitiva: Contraloría la valida.

## J) ENTREGABLE ESPERADO

Trabaja en tres entregas y DETENTE al terminar la primera:

1. **PLAN (sin código).**
   - Tabla de mapeo de esquema (campo requerido → tabla.campo real | NO EXISTE).
   - Estado de cada prerrequisito del Paso 0: existe / parcial / no existe, con su impacto en el reporte.
   - Arquitectura: archivos a crear o modificar (migraciones, vista o función SQL, clase de servicio del reporte, FormRequest de parámetros, controlador, rutas, vista Blade/JS, clase de exportación, policy/permisos, jobs y scheduler si aplica).
   - SQL propuesto (borrador) de la consulta principal.
   - Preguntas abiertas para Contraloría/Contabilidad/Fiscal, cada una con el supuesto que aplicarás si no hay respuesta.
   - Espera mi aprobación antes de continuar.
2. **CÓDIGO.** Implementa exactamente lo aprobado: migraciones aditivas, consulta encapsulada, servicio, endpoint, vista, exportaciones, permisos, jobs. Commits pequeños y descriptivos.
3. **PRUEBAS Y VALIDACIÓN.**
   - Pruebas de feature (Pest o PHPUnit, lo que use el repo) con datos semilla que ejerzan cada regla de negocio y cada criterio de aceptación de la sección I.
   - Pruebas de permisos: sin permiso → 403; alcance por empresa/proveedor respetado.
   - Script SQL de verificación que demuestre las identidades de cuadre del reporte.
   - Plan de ejecución en SQL Server con volumen realista; reporta tiempos y los índices creados.
   - Lista explícita de lo que quedó pendiente o fuera de alcance.

------------------------------------------------

================================================
REPORTE #31: RA-03 · Tablero de indicadores de Contraloría
================================================

# REPORTE RA-03: Tablero de indicadores de Contraloría

## A) ROL Y OBJETIVO

Actúa como desarrollador senior full-stack en el Portal de Proveedores (Módulo de Compras) de TotalGas, con experiencia en reporting financiero para Contabilidad, Contraloría y Tesorería en México (CFDI 4.0, SAT, REPSE).

**Objetivo medible:** Publicar mensualmente los indicadores de control con apertura por empresa y departamento, comparativo contra meses anteriores y meta, donde cada indicador es rastreable hasta el detalle y se calcula solo con datos del portal.

### Contexto técnico del proyecto
- Aplicación: Portal de Proveedores / Módulo de Compras de TotalGas (grupo gasolinero con 36 estaciones, Cd. Juárez, Chih.). Ya cubre requisiciones, cotizaciones, órdenes de compra, recepciones, control presupuestal y validación CFDI/SAT (RFC, listado 69-B). Cada centro de costo ya tiene responsable asignado.
- Stack: Laravel 12, PHP 8.3, SQL Server 2019, Blade + Bootstrap 5 + jQuery. Si el repositorio ya usa Livewire, DataTables, Laravel Excel u OpenSpout, spatie/laravel-permission o dompdf/snappy, REUTILÍZALOS. No agregues dependencias nuevas sin justificarlas en el plan.
- Zona horaria de negocio: `America/Ciudad_Juarez`. Toda "fecha de corte" se interpreta a las 23:59:59 de esa zona.
- Moneda funcional: MXN. Los nombres de tablas y campos de este documento son SUPUESTOS: el esquema real del repositorio manda.

### Reglas de trabajo (obligatorias)
1. Antes de escribir código, inspecciona migraciones, modelos Eloquent, vistas/funciones SQL, servicios y jobs existentes. Construye una tabla de mapeo: `campo requerido → tabla.campo real | NO EXISTE`.
2. No crees tablas paralelas para datos que ya existen con otro nombre. Si falta un dato, propón en el plan la migración mínima y aditiva, y espera aprobación.
3. Si un prerrequisito no existe, NO simules el dato (ni ceros, ni NULL silenciosos, ni valores fijos). Repórtalo y separa en el plan "lo que se puede entregar hoy" de "lo que requiere construir el control primero". Un reporte que cuadra con datos falsos es peor que no tener reporte.
4. Montos en `DECIMAL(18,2)`; tipos de cambio en `DECIMAL(18,6)`; nunca `FLOAT`. Redondea a 2 decimales a nivel de renglón y después suma; usa el mismo criterio que los demás reportes con los que este debe cuadrar.
5. Consultas siempre parametrizadas (bindings). Prohibido concatenar filtros en SQL.
6. El reporte no consulta en línea los linked servers de las estaciones; usa las tablas ya consolidadas en el servidor del portal.
7. Una sola fuente de verdad por cálculo: si otro reporte o el motor de control usa el mismo cálculo, encapsúlalo (vista, función de tabla en línea o clase de servicio) y reutilízalo. No dupliques fórmulas.
8. No cambies reglas de negocio existentes salvo lo que este documento pida explícitamente. Si detectas un bug, repórtalo en el plan en lugar de corregirlo en silencio.
9. Reglas fiscales (tasas, claves SAT, plazos): van en catálogos parametrizables, nunca en código, y requieren confirmación de Contabilidad/Fiscal antes de liberarse.

### Paso 0: prerrequisitos que debes verificar antes de programar

- Los reportes fuente de cada indicador (RP-01, RC-01, RC-02, RF-01, RF-05, RM-01, RM-03, RA-01, RT-01/RT-02). Un indicador cuya fuente no exista se muestra como 'No disponible', nunca con un valor capturado a mano.
- Tabla de metas por indicador/periodo.

Si alguno no existe, repórtalo en el plan con su impacto; no lo sustituyas con datos simulados.

## B) NOMBRE Y PROPÓSITO DEL REPORTE

- ID: `RA-03` · Dominio: H. Auditoría interna e indicadores · Fase: F3
- Nombre técnico: `rpt_controllership_kpis`
- Nombre de negocio: Tablero de indicadores de Contraloría
- Frecuencia: Mensual
- Consumidor principal: Dirección, Contraloría
- Granularidad (una fila =): Un indicador por periodo, con apertura por empresa y departamento.

**Pregunta de negocio que responde / decisión que habilita:** La medición del control, no de la operación. Permite fijar metas concretas (bajar el gasto sin OC previa de 30 % a 5 %, por ejemplo) y demostrar el avance con datos del propio sistema.

**Requerimiento original de Contabilidad y Finanzas (campos mínimos):**

  • % de gasto ejercido sin OC previa · % de OC directas sobre el total
  • Tiempo promedio de ciclo requisición-OC y recepción-pago · Días de pago promedio a proveedores
  • % de facturas rechazadas en primera carga y causa principal
  • % de proveedores en verde · Monto de cuentas por pagar bloqueado
  • Número e importe de excepciones autorizadas · IVA pendiente de acreditar por falta de REP
  • % de cobertura del expediente REPSE · Desviación presupuestal por departamento

## C) COLUMNAS / CAMPOS

Origen = SUPUESTO de nombre; mapea al esquema real en el Paso 0. O = obligatoria, Op = opcional.

| Columna | Tipo | Origen (SUPUESTO) | Cálculo / fórmula | Formato | O/Op |
|---|---|---|---|---|---|
| indicator_code / indicator_name | VARCHAR / NVARCHAR | kpi_definitions |  | texto | O |
| period | CHAR(7) |  |  | yyyy-mm | O |
| company / department | NVARCHAR |  |  | texto | O |
| numerator / denominator / value | DECIMAL | calculada | value = numerator / NULLIF(denominator,0) (o promedio según definición) | según indicador | O |
| previous_value / delta | DECIMAL | kpi_snapshots |  | según indicador | O |
| target / status | DECIMAL / VARCHAR | kpi_targets | cumple / no cumple | badge | O |
| drilldown_url | NVARCHAR | calculada | liga al reporte fuente con los mismos filtros | liga | O |

## D) FILTROS Y PARÁMETROS

**Filtros fijos (siempre aplicados):**

- Periodos cerrados (snapshot mensual congelado).

**Parámetros de entrada (según el Excel: Periodo, empresa, departamento, comparativo contra meses anteriores.):**

| Parámetro | Tipo | Valor por defecto | O/Op |
|---|---|---|---|
| period | yyyy-mm | mes anterior | O |
| company_ids | array<int> | empresas del usuario | O |
| department | multi | todos | Op |
| compare_months | int | 6 | Op |

## E) AGRUPACIONES, ORDEN Y GRANULARIDAD

- Un indicador por periodo, con apertura por empresa y departamento.
- Orden fijo del catálogo de indicadores.

## F) REGLAS DE NEGOCIO Y CÁLCULOS

- Fórmulas (SUPUESTO, Contraloría valida): % gasto sin OC previa = importe facturado sin OC o con OC posterior a la factura / importe facturado total · % OC directas (conteo e importe) de RC-02 · Ciclo requisición→OC (horas promedio) de RC-01 · Ciclo recepción→pago (días promedio) · Días de pago promedio = promedio ponderado por importe de (fecha de pago − fecha de recepción del CFDI) · % rechazo en primera carga y causa principal de RF-01 · % proveedores en verde de RM-01 · Monto de CxP bloqueado de RT-01 · # e importe de excepciones de RA-01 · IVA pendiente por falta de REP de RF-05 · % cobertura REPSE de RM-03 · Desviación presupuestal por departamento = (consumido − presupuesto vigente prorrateado al mes) / presupuesto vigente prorrateado, de RP-01.
- Cada indicador usa las mismas funciones/servicios del reporte fuente; no hay fórmulas duplicadas.
- Snapshot mensual congelado (`kpi_snapshots`) el día 5 para que el histórico no cambie.

## G) FORMATO DE SALIDA

- Tablero de tarjetas con valor, meta, tendencia (sparkline 6 meses) y semáforo.
- Clic en tarjeta → reporte fuente filtrado.
- PDF ejecutivo mensual para Dirección.

Convenciones comunes de salida:
- Pantalla: tabla con DataTables en modo server-side (paginación en servidor de 25/50/100 filas), encabezado fijo y primera columna congelada, panel de filtros arriba con los parámetros de la sección D y botón "Limpiar filtros".
- Excel (.xlsx) con celdas tipadas de verdad (moneda `$#,##0.00`, fechas `dd/mm/yyyy`, porcentaje `0.0%`), nunca números como texto; encabezado con nombre del reporte, empresa(s), filtros aplicados, fecha/hora de generación y usuario. Escritura en streaming (chunks), sin cargar todo en memoria.
- CSV UTF-8 con BOM y separador coma.
- Exportaciones de más de 50 000 filas se encolan como job y se notifica al usuario cuando el archivo está listo.
- La exportación respeta exactamente los filtros activos en pantalla.

## H) CONSIDERACIONES TÉCNICAS

**Tablas/vistas probables (SUPUESTO):** kpi_definitions, kpi_targets, kpi_snapshots (nuevas) + servicios de los reportes fuente.

**Índices recomendados (valida contra los existentes antes de crear):**

- kpi_snapshots (period, company_id, indicator_code).

**Seguridad y permisos:**

- Permisos granulares: `reportes.controllership_kpis.ver` y `reportes.controllership_kpis.exportar` (crea los permisos con el mecanismo que ya use el repo).
- Alcance por empresa: el usuario solo ve las empresas que tiene asignadas; el filtro se aplica en el servidor (scope/policy), nunca solo en la UI.
- Cada exportación se registra en bitácora: usuario, fecha/hora, filtros, número de filas.

- Dirección y Contraloría.

**Endpoints y componentes:**

- GET /reportes/tablero-contraloria (+ /pdf).
- Job mensual `KpiSnapshotJob`.

## I) CRITERIOS DE ACEPTACIÓN (Definition of Done)

Criterio de negocio original: _Cada indicador es rastreable hasta el detalle que lo compone. Ningún indicador se calcula con datos capturados fuera del portal._

- [ ] Cada indicador navega a su detalle y el detalle suma exactamente el numerador.
- [ ] Ningún indicador usa datos capturados fuera del portal; si la fuente no existe se muestra 'No disponible'.
- [ ] Todos los filtros de la sección D funcionan combinados y la exportación reproduce exactamente la vista filtrada.
- [ ] Usuarios sin permiso reciben 403 y ningún usuario ve empresas fuera de su alcance.
- [ ] La consulta principal responde en menos de 3 s con el volumen de un año de operación (o se justifica el snapshot/job).

**Preguntas abiertas (aplica el SUPUESTO indicado si no hay respuesta):**

- Metas iniciales por indicador: Contraloría las define.

## J) ENTREGABLE ESPERADO

Trabaja en tres entregas y DETENTE al terminar la primera:

1. **PLAN (sin código).**
   - Tabla de mapeo de esquema (campo requerido → tabla.campo real | NO EXISTE).
   - Estado de cada prerrequisito del Paso 0: existe / parcial / no existe, con su impacto en el reporte.
   - Arquitectura: archivos a crear o modificar (migraciones, vista o función SQL, clase de servicio del reporte, FormRequest de parámetros, controlador, rutas, vista Blade/JS, clase de exportación, policy/permisos, jobs y scheduler si aplica).
   - SQL propuesto (borrador) de la consulta principal.
   - Preguntas abiertas para Contraloría/Contabilidad/Fiscal, cada una con el supuesto que aplicarás si no hay respuesta.
   - Espera mi aprobación antes de continuar.
2. **CÓDIGO.** Implementa exactamente lo aprobado: migraciones aditivas, consulta encapsulada, servicio, endpoint, vista, exportaciones, permisos, jobs. Commits pequeños y descriptivos.
3. **PRUEBAS Y VALIDACIÓN.**
   - Pruebas de feature (Pest o PHPUnit, lo que use el repo) con datos semilla que ejerzan cada regla de negocio y cada criterio de aceptación de la sección I.
   - Pruebas de permisos: sin permiso → 403; alcance por empresa/proveedor respetado.
   - Script SQL de verificación que demuestre las identidades de cuadre del reporte.
   - Plan de ejecución en SQL Server con volumen realista; reporta tiempos y los índices creados.
   - Lista explícita de lo que quedó pendiente o fuera de alcance.

------------------------------------------------

# TABLA RESUMEN

| # | ID | Reporte | Fase | Complejidad | Dependencias entre reportes | Notas |
|---|---|---|---|---|---|---|
| 1 | RP-01 | Presupuesto vs. ejercido por departamento y renglón | F1 | Alta | Base de RC-03, RR-02, RP-03, RT-04, RA-03. Usa RP-02. | Obliga a unificar el cálculo con el motor de bloqueo; probablemente requiere refactor a libro mayor presupuestal. |
| 2 | RP-02 | Movimientos y traspasos presupuestales | F1 | Media | Alimenta RP-01 (presupuesto vigente). | Si hoy el presupuesto se edita directo, primero hay que construir la captura de movimientos. |
| 3 | RP-03 | Alertas de agotamiento, sobregiro y excepciones autorizadas | F1 | Media | Requiere RP-01 y la tabla de RA-01 (adelantar a F1). | Inconsistencia de fases: el criterio pide cuadrar contra RA-01, que está en F2. |
| 4 | RC-01 | Pipeline de requisiciones y tiempos de ciclo | F1 | Media | Alimenta RA-03 (ciclo requisición-OC). | Depende de que exista historial de pasos con timestamps. |
| 5 | RC-02 | Órdenes de compra emitidas | F1 | Media | Alimenta RC-05, RR-03, RA-03. | Clave para vigilar la fuga de control por OC directas. |
| 6 | RC-03 | Órdenes de compra abiertas y backlog por recibir | F1 | Media | Debe cuadrar con RP-01. Alimenta RT-04. | — |
| 7 | RC-04 | Modificaciones, adendas y cancelaciones de OC | F1 | Alta | Alimenta RA-01 (overrides) y RA-03. | Casi seguro requiere construir versionado de OC antes del reporte. |
| 8 | RC-05 | Concentración de gasto y detección de fraccionamiento | F2 | Alta | Usa datos de RC-02. | Regla antifraude: requiere historial de umbrales por fecha. |
| 9 | RR-01 | Recepciones validadas y su evidencia | F1 | Media | Alimenta RR-02, RF-02, RA-02. | — |
| 10 | RR-02 | Recepciones pendientes de facturar (provisión de cierre) | F1 | Alta | Usa RR-01; póliza automática depende de RK-01. | Inconsistencia de fases: el criterio de aceptación requiere pólizas (F2). |
| 11 | RR-03 | Excepciones de flujo | F1 | Media | Usa RC-02 (lista de OC directas). Alimenta RA-03. | — |
| 12 | RF-01 | Bitácora de validación de CFDI | F1 | Media | Alimenta RA-03 y RM-01. | El motor ya existe; lo nuevo es la bitácora por intento y el catálogo de códigos. |
| 13 | RF-02 | Resultado del cotejo triple y desviaciones | F1 | Alta | Requiere RR-01 y tabla de RA-01. Alimenta RT-01. | Riesgo técnico: mapeo concepto CFDI ↔ renglón OC. |
| 14 | RF-03 | IVA acreditable y conciliación para la DIOT | F2 | Alta | Requiere pagos, RF-05 (REP), RF-06, RK-02. | No codificar el layout de la DIOT de memoria. |
| 15 | RF-04 | Retenciones efectuadas y su entero | F2 | Alta | Requiere pagos; comparte motor con RF-01. | Sin PAC: el portal no emite CFDI de retenciones, solo registra el UUID. |
| 16 | RF-05 | Control de complementos de pago (REP) | F2 | Alta | Requiere pagos (RT-01) y RM-01. Alimenta RF-03 y RA-03. | — |
| 17 | RF-06 | CFDI de egreso, anticipos y ajustes | F2 | Alta | Requiere RK-01 y RP-01. | — |
| 18 | RF-07 | Conciliación del portal contra el repositorio del SAT | F3 | Alta | Usa RF-01, RT-01, RK-01. | Requiere decisión sobre custodia de la e.firma de cada empresa. |
| 19 | RT-01 | Programación semanal de pagos por vencimiento | F1 | Alta | Requiere RF-02 y centralizar bloqueos; se enriquece con RM-01 y RF-05. Base de RT-04. | Probablemente el portal aún no tiene módulo de Tesorería. |
| 20 | RT-02 | Antigüedad de saldos por proveedor | F1 | Media | Base de RT-03 y RT-04; cuadre depende de RK-02. | — |
| 21 | RT-03 | Estado de cuenta de proveedor | F1 | Media | Requiere RT-02; fecha estimada desde RT-01. | Cara externa del portal: cuidado con fuga de información. |
| 22 | RT-04 | Proyección de salidas de efectivo a 13 semanas | F3 | Alta | Requiere RT-01, RT-02, RC-03. | — |
| 23 | RK-01 | Auxiliar de pólizas generadas y archivo de exportación | F2 | Alta | Base de RR-02 (póliza), RF-06, RK-02. | Probablemente es la construcción del motor de pólizas. |
| 24 | RK-02 | Conciliación del portal contra la contabilidad | F2 | Alta | Requiere RK-01. Da el cuadre a RT-02 y RF-03. | — |
| 25 | RM-01 | Matriz de cumplimiento y semáforo de proveedores | F2 | Alta | Usa RM-02, RM-03, RM-04, RT-02, RC-03. Alimenta RT-01 y RF-05. | — |
| 26 | RM-02 | Vencimientos documentales del expediente | F2 | Baja | Alimenta RM-01. | Buen candidato para arrancar F2. |
| 27 | RM-03 | Auditoría mensual de proveedores REPSE | F3 | Alta | Alimenta RM-01 y RA-03. | Maneja datos personales sensibles de trabajadores de terceros. |
| 28 | RM-04 | Monitoreo del listado 69-B y proveedores bloqueados | F3 | Media | Alimenta RM-01. | Parte ya existe (validación EFOS); lo nuevo es historial y exposición. |
| 29 | RA-01 | Bitácora de excepciones, liberaciones y overrides | F2 | Media | Requerido por RP-03, RF-02, RM-01, RA-03. Adelantar a F1. | Inconsistencia de fases: está en F2 pero la necesitan reportes F1. |
| 30 | RA-02 | Segregación de funciones e integridad de accesos | F3 | Alta | Usa datos de RC-01, RR-01, RT-01. | — |
| 31 | RA-03 | Tablero de indicadores de Contraloría | F3 | Media | Depende de prácticamente todos los reportes. | Construir al final; su valor depende de la calidad de los demás. |
