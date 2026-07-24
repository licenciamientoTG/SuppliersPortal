# Documento de Requerimientos Funcionales - Portal de Proveedores

## 1. Control del documento

| Campo | Valor |
|---|---|
| Sistema | Portal de Proveedores |
| Organizacion | TotalGas |
| Version | 0.1 |
| Fecha | 2026-04-30 |
| Responsable | Codex |
| Estado | Borrador |

## 2. Introduccion

### 2.1 Proposito

Documentar el alcance funcional observado en el codigo fuente del Portal de Proveedores construido en Laravel 12 para TotalGas, distinguiendo entre funcionalidad implementada, parcial y pendiente de validacion funcional.

### 2.2 Alcance

Este documento se basa en revision de:

- Rutas web y autenticacion.
- Controladores y requests.
- Modelos, migraciones y seeders.
- Servicios, jobs, commands y scheduler.
- Vistas Blade y componentes Livewire.
- Notificaciones, reglas y pruebas automatizadas.

No sustituye validacion funcional con usuarios de negocio. Cuando el codigo no permite afirmar una regla con suficiente certeza, se marca como `Pendiente de confirmacion`.

### 2.3 Problema de negocio

El sistema busca centralizar la operacion de compras, proveedores y presupuesto de estaciones de servicio de TotalGas, reduciendo:

- Riesgo fiscal por operar con proveedores en listas EFOS.
- Falta de trazabilidad entre requisicion, RFQ, orden y recepcion.
- Validaciones manuales de presupuesto y aprobacion.
- Retrasos en compras, pagos y recepcion de insumos.

### 2.4 Definiciones, acronimos y abreviaturas

| Termino | Definicion |
|---|---|
| EFOS | Listado fiscal consultado desde SAT para validar riesgo de proveedores. |
| RFQ | Request for Quotation o solicitud de cotizacion a proveedores. |
| Orden de Compra | Documento de compra emitido a partir de cotizaciones aprobadas o compra directa. |
| Requisicion | Solicitud interna de compra creada por usuario solicitante. |
| Centro de costo | Unidad de imputacion presupuestal usada para control y aprobacion. |
| TRESS | Fuente externa usada para sincronizacion de empleados. |
| OCD | Orden de Compra Directa. |

## 3. Contexto del sistema

El repositorio corresponde a una aplicacion Laravel 12 con:

- Backend PHP 8.2.
- Livewire 3 para flujos interactivos.
- Spatie Permission para roles y permisos.
- Spatie Activitylog para auditoria.
- DomPDF y PhpSpreadsheet para exportaciones.
- Vite y AlpineJS para frontend.

El sistema cuenta con dos grandes superficies de uso:

- Portal interno para usuarios de compras, administracion, aprobadores y receptores.
- Portal externo para proveedores autenticados.

## 4. Actores y usuarios

| Actor/Rol | Tipo | Descripcion | Frecuencia de uso |
|---|---|---|---|
| requester | Interno | Usuario que crea requisiciones y consulta su seguimiento. | Alta |
| buyer | Interno | Usuario de compras que valida proveedores, opera RFQ y participa en ordenes. | Alta |
| supplier | Externo | Proveedor autenticado que completa onboarding, recibe RFQ y registra entregas. | Media |
| receiver | Interno | Usuario que registra recepciones fisicas y valida remisiones/evidencias. | Alta |
| authorizer | Interno | Rol usado en codigo para aprobacion de OCD y revisiones de alto nivel. | Media |
| purchasing_manager | Interno | Actor funcional esperado para excepciones y aprobaciones; en codigo aparece aproximado por niveles de aprobacion. | Pendiente de confirmacion |
| superadmin | Interno | Usuario con control de catalogos, SAT, niveles de aprobacion y configuracion. | Media |
| accounting / finanzas | Interno | Rol presente para consultas y procesos financieros, con participacion parcial visible en codigo. | Media |
| general_director | Interno | Rol configurado en permisos para reportes y aprobaciones de alto nivel. | Baja |
| catalog_admin | Interno | Rol de gestion de productos y categorias. | Media |

## 5. Modulos dentro del alcance

### 5.1 Gestion integral de proveedores

Estado observado: `Implementado`.

Capacidades identificadas:

- Registro autonomo de proveedor desde `/register`.
- Creacion de usuario con rol `supplier`.
- Alta de datos fiscales, contacto y tipo de proveedor.
- Soporte REPSE para proveedores de servicios especializados.
- Carga y revision de documentos.
- Revision administrativa de documentos y proveedores.
- Gestion bancaria y SIROC.
- Restriccion funcional por EFOS via RFC.

Evidencia principal:

- `app/Http/Controllers/Auth/SupplierRegistrationController.php`
- `app/Models/Supplier.php`
- `app/Http/Controllers/SupplierDocumentController.php`
- `app/Http/Controllers/DocumentReviewController.php`
- `app/Rules/EfosNotListed.php`

### 5.2 Gestion presupuestal

Estado observado: `Implementado con evolucion reciente`.

Capacidades identificadas:

- Presupuesto anual por centro de costo.
- Distribucion mensual por categoria y cedula presupuestal.
- Aprobacion de presupuestos anuales.
- Consulta de disponibilidad por mes y categoria.
- Soporte de centros `ANNUAL` y `FREE_CONSUMPTION`.
- Movimientos presupuestales con aprobacion y rechazo.
- Compromiso, liberacion y consumo de presupuesto desde ordenes.

Evidencia principal:

- `app/Http/Controllers/AnnualBudgetController.php`
- `app/Http/Controllers/BudgetMovementController.php`
- `app/Services/BudgetAllocationService.php`
- `database/migrations/2026_04_27_000001_create_budget_cedulas_table.php`

### 5.3 Gestion de requisiciones

Estado observado: `Implementado`.

Capacidades identificadas:

- Creacion, edicion y eliminacion de borradores.
- Soporte Blade y Livewire para captura.
- Relacion con empresa, centro de costo, ubicacion de recepcion y partidas.
- Envio a compras.
- Pausa, reactivacion, cancelacion y rechazo.
- Notificaciones al solicitante y a usuarios de compras.

Estados observados:

- `DRAFT`
- `PENDING`
- `PAUSED`
- `IN_QUOTATION`
- `QUOTED`
- `PENDING_BUDGET_ADJUSTMENT`
- `APPROVED`
- `COMPLETED`
- `CANCELLED`
- `REJECTED`

Evidencia principal:

- `app/Models/Requisition.php`
- `app/Http/Controllers/RequisitionController.php`
- `app/Http/Controllers/RequisitionWorkflowController.php`

### 5.4 Cotizacion / RFQ

Estado observado: `Implementado`.

Capacidades identificadas:

- Planeacion de cotizacion por grupos y partidas.
- Seleccion de proveedores por grupo.
- Creacion de RFQ en estado borrador.
- Envio individual de RFQ a proveedores.
- Bandejas de pendientes y recibidas.
- Vista de detalle y comparacion.
- Cancelacion con notificaciones.
- Historial de cotizaciones del proveedor.

Estados observados en RFQ:

- `DRAFT`
- `SENT`
- `RESPONSES_RECEIVED`
- `EVALUATED`
- `CANCELLED`

Evidencia principal:

- `app/Http/Controllers/RfqController.php`
- `app/Http/Controllers/RfqInboxController.php`
- `app/Http/Controllers/RfqComparisonController.php`
- `app/Livewire/Rfq/QuotationWizard.php`

### 5.5 Ordenes de Compra

Estado observado: `Implementado`.

Capacidades identificadas:

- Ordenes regulares derivadas de requisicion/cotizacion.
- Ordenes directas sin RFQ previo.
- Compromiso presupuestal al aprobar o emitir.
- Aprobacion por nivel de monto en OCD.
- Consulta tabular y vista detalle.
- Cierre automatico por inactividad.
- Alertas de entrega y cierre por inactividad.

Estados observados en OC regular:

- `OPEN`
- `ISSUED`
- `PARTIALLY_RECEIVED`
- `RECEIVED`
- `CANCELLED`
- `PAID`
- `CLOSED_BY_INACTIVITY`
- `DELIVERED_PENDING_RECEPTION`

Estados observados en OCD:

- `DRAFT`
- `PENDING_APPROVAL`
- `REJECTED`
- `RETURNED`
- `ISSUED`
- `PARTIALLY_RECEIVED`
- `RECEIVED`
- `CANCELLED`
- `CLOSED_BY_INACTIVITY`
- `DELIVERED_PENDING_RECEPTION`

Evidencia principal:

- `app/Models/PurchaseOrder.php`
- `app/Models/DirectPurchaseOrder.php`
- `app/Http/Controllers/PurchaseOrderController.php`
- `app/Http/Controllers/DirectPurchaseOrderController.php`
- `app/Console/Commands/CloseInactivePurchaseOrders.php`

### 5.6 Logistica de recepcion

Estado observado: `Implementado`.

Capacidades identificadas:

- Bandeja global y bandeja filtrada por locaciones.
- Recepcion contra OC regular y OCD.
- Recepciones parciales y completas.
- Registro de remision obligatoria.
- Fotos y notas por no conformidad.
- Validacion REPSE cuando aplica.
- Descarga de remision.

Estados observados:

- `PENDING`
- `PARTIAL`
- `COMPLETED`

Evidencia principal:

- `app/Models/Reception.php`
- `app/Http/Controllers/ReceptionController.php`
- `app/Services/ReceptionService.php`

### 5.7 Dashboard y metricas

Estado observado: `Implementado parcialmente`.

Capacidades identificadas:

- Dashboard general autenticado.
- Dashboard critico de movimientos presupuestales.
- Contadores en vistas de ordenes y recepciones.

Pendientes:

- La definicion completa de indicadores ejecutivos y su validacion funcional no queda cerrada solo con el codigo revisado.

### 5.8 Flujos de aprobacion

Estado observado: `Implementado`.

Capacidades identificadas:

- Catalogo de niveles de aprobacion configurables.
- Asignacion de nivel por monto para OCD.
- Aprobacion de resumen/cotizacion antes de emitir OC.
- Aprobacion y rechazo de movimientos presupuestales.

Niveles sembrados en codigo:

- Nivel 1: Comprador (`0` a `5000.00`)
- Nivel 2: Jefe de Area (`5000.01` a `50000.00`)
- Nivel 3: Gerente de Departamento (`50000.01` a `150000.00`)
- Nivel 4: Director de Area (`150000.01` a `500000.00`)
- Nivel 5: Director General (`500000.01` en adelante)

Evidencia principal:

- `database/seeders/ApprovalLevelSeeder.php`
- `app/Services/ApprovalService.php`
- `app/Http/Controllers/QuotationApprovalController.php`

### 5.9 Auditoria de cambios

Estado observado: `Implementado`.

Capacidades identificadas:

- Tabla `activity_log`.
- Registro explicito de rechazos de requisicion.
- Eventos de empleados para cambios de datos importados.
- Campos de auditoria `created_by`, `updated_by`, `approved_by`, `rejected_by`, etc.

Evidencia principal:

- `database/migrations/2025_12_10_163931_create_activity_log_table.php`
- `app/Http/Controllers/RequisitionWorkflowController.php`
- `app/Http/Controllers/EmployeeController.php`

### 5.10 Exportacion de reportes

Estado observado: `Implementado parcialmente`.

Capacidades identificadas:

- Dependencias para PDF y Excel en `composer.json`.
- Generacion de PDF en comunicados y documentos asociados.
- Soporte tecnico para reportes tabulares y exportables.

Pendiente de confirmacion:

- El inventario completo de reportes PDF, Excel y CSV disponibles al usuario final.

### 5.11 Modulos base del sistema

Estado observado: `Implementado`.

Capacidades identificadas:

- Login, logout, bloqueo de pantalla y desbloqueo.
- Registro de proveedor.
- Recuperacion y reseteo de contrasena.
- Perfil de usuario.
- Roles y permisos con Spatie.
- Asignacion de empresas y centros de costo a usuarios.

## 6. Modulos existentes con alcance pendiente de validacion

### Sistema de Comunicados

- Funcionalidad aparente: publicacion y consulta de comunicados para proveedores y administracion.
- Estado observado: existe codigo, rutas, tablas y vistas.
- Dependencias detectadas: `AnnouncementController`, tablas `announcements` y `announcement_suppliers`.
- Riesgo: no asumir que el flujo de lectura, visto y descarte esta completamente validado de negocio.
- Recomendacion: documentar como modulo existente y validar alcance formal.

### Gestion de Incidentes

- Funcionalidad aparente: registro y eliminacion de incidentes.
- Estado observado: existe ruta, controlador, vista indice y migracion.
- Dependencias detectadas: `IncidentController`, `StoreIncidentRequest`, `incidents`.
- Riesgo: no se identifico un flujo funcional completo con priorizacion, seguimiento y cierre.
- Recomendacion: documentar como parcial.

### Bloqueo de Ubicaciones

- Funcionalidad aparente: bloqueo de locaciones de recepcion para portal.
- Estado observado: existe via `receiving-locations/{id}/block-portal` y filtro `portal_blocked`.
- Dependencias detectadas: `ReceivingLocationController`, vistas de ubicaciones.
- Riesgo: si se documenta como modulo independiente puede sobredimensionarse; parece una capacidad de administracion de recepcion.
- Recomendacion: mantener como subfuncion dentro de locaciones de recepcion o backlog.

### Comparador de Cotizaciones

- Funcionalidad aparente: comparacion y seleccion de respuestas RFQ.
- Estado observado: existe implementacion y ruta `rfq/{rfq}/comparison`.
- Dependencias detectadas: `RfqComparisonController`, `QuotationSummary`.
- Riesgo: el flujo existe, pero la definicion funcional cerrada de criterios de seleccion y multiples proveedores aun requiere validacion.
- Recomendacion: tratarlo como implementado tecnicamente, con alcance funcional pendiente de cierre.

### Historial de Cotizaciones

- Funcionalidad aparente: historial para proveedor.
- Estado observado: existe vista y ruta `supplier/quotations/history`.
- Dependencias detectadas: `SupplierPortalController`.
- Riesgo: no se reviso exhaustivamente el conjunto de filtros y estados visibles al proveedor.
- Recomendacion: documentar como existente.

### Recuperacion de contrasena

- Funcionalidad aparente: flujo Breeze de olvido y reseteo.
- Estado observado: existe en `routes/auth.php` y vistas de autenticacion.
- Dependencias detectadas: controladores auth de Laravel.
- Riesgo: aunque existe codigo, no se localizaron pruebas especificas del flujo ni confirmacion de negocio.
- Recomendacion: mantener como modulo base implementado pero validar operacion real.

## 7. Requisitos funcionales

| ID | Modulo | Requisito | Prioridad | Actor principal | Estado |
|---|---|---|---|---|---|
| RF-001 | Proveedores | El sistema debe permitir que un proveedor se registre de forma autonoma desde un formulario publico. | Alta | supplier | Implementado |
| RF-002 | Proveedores | El sistema debe crear un usuario autenticable y asociarlo a un registro de proveedor con rol `supplier`. | Alta | supplier | Implementado |
| RF-003 | Proveedores | El sistema debe capturar informacion REPSE cuando el proveedor declare servicios especializados. | Alta | supplier | Implementado |
| RF-004 | Proveedores | El sistema debe permitir carga, revision, aceptacion y rechazo de documentos del proveedor. | Alta | buyer | Implementado |
| RF-005 | Proveedores | El sistema debe notificar a usuarios `buyer` cuando se registra un nuevo proveedor. | Alta | buyer | Implementado |
| RF-006 | EFOS | El sistema debe consultar el listado EFOS y mantenerlo sincronizado en una tabla local. | Alta | superadmin | Implementado |
| RF-007 | EFOS | El sistema debe poder identificar un proveedor como riesgoso cuando su RFC aparece como `Definitivo` o `Presunto`. | Alta | buyer | Implementado |
| RF-008 | Presupuestos | El sistema debe administrar presupuestos anuales por centro de costo y ejercicio fiscal. | Alta | superadmin | Implementado |
| RF-009 | Presupuestos | El sistema debe manejar distribuciones mensuales por categoria de gasto y cedula presupuestal. | Alta | superadmin | Implementado |
| RF-010 | Presupuestos | El sistema debe aprobar presupuestos antes de permitir su uso normal en validaciones. | Alta | superadmin | Implementado |
| RF-011 | Presupuestos | El sistema debe permitir centros de costo de consumo libre sin restriccion presupuestal anual. | Media | superadmin | Implementado |
| RF-012 | Presupuestos | El sistema debe calcular montos asignados, comprometidos, consumidos y disponibles. | Alta | buyer | Implementado |
| RF-013 | Requisiciones | El sistema debe permitir crear requisiciones con partidas, centro de costo y ubicacion de recepcion. | Alta | requester | Implementado |
| RF-014 | Requisiciones | El sistema debe permitir guardar requisiciones en borrador y editarlas mientras sigan editables. | Alta | requester | Implementado |
| RF-015 | Requisiciones | El sistema debe permitir enviar requisiciones a compras y notificar al solicitante y compradores. | Alta | requester | Implementado |
| RF-016 | Requisiciones | El sistema debe permitir pausar, reactivar, cancelar y rechazar requisiciones bajo reglas de estado. | Alta | buyer | Implementado |
| RF-017 | RFQ | El sistema debe planear cotizacion por grupos o partidas de requisicion. | Alta | buyer | Implementado |
| RF-018 | RFQ | El sistema debe permitir seleccionar varios proveedores por grupo y generar RFQ en borrador. | Alta | buyer | Implementado |
| RF-019 | RFQ | El sistema debe enviar una RFQ a proveedores asignados y registrar la fecha de invitacion. | Alta | buyer | Implementado |
| RF-020 | RFQ | El sistema debe permitir al proveedor consultar RFQ y guardar su cotizacion. | Alta | supplier | Implementado |
| RF-021 | RFQ | El sistema debe permitir cancelar RFQ y notificar a requisitor y proveedores. | Media | buyer | Implementado |
| RF-022 | Ordenes de Compra | El sistema debe mostrar ordenes regulares y directas en bandejas separadas. | Alta | buyer | Implementado |
| RF-023 | Ordenes de Compra | El sistema debe permitir crear OCD con proveedor, centro de costo, categorias e identificacion del nivel de aprobacion requerido. | Alta | buyer | Implementado |
| RF-024 | Ordenes de Compra | El sistema debe validar disponibilidad presupuestal antes de aprobar una OCD. | Alta | authorizer | Implementado |
| RF-025 | Ordenes de Compra | El sistema debe comprometer presupuesto al aprobar o emitir ordenes. | Alta | system | Implementado |
| RF-026 | Ordenes de Compra | El sistema debe cerrar ordenes inactivas automaticamente segun el tipo de orden. | Media | system | Implementado |
| RF-027 | Recepciones | El sistema debe permitir registrar recepciones contra ordenes emitidas y parcialmente recibidas. | Alta | receiver | Implementado |
| RF-028 | Recepciones | El sistema debe permitir recepcion parcial o completa por partida. | Alta | receiver | Implementado |
| RF-029 | Recepciones | El sistema debe requerir remision y evidencia adicional cuando exista no conformidad. | Alta | receiver | Implementado |
| RF-030 | Recepciones | El sistema debe registrar folio de recepcion y permitir descarga de remision. | Alta | receiver | Implementado |
| RF-031 | Aprobaciones | El sistema debe asignar niveles de aprobacion por monto para OCD. | Alta | system | Implementado |
| RF-032 | Aprobaciones | El sistema debe permitir aprobar o rechazar movimientos presupuestales. | Media | superadmin | Implementado |
| RF-033 | Usuarios y seguridad | El sistema debe gestionar roles, permisos, empresas y centros de costo por usuario. | Alta | superadmin | Implementado |
| RF-034 | Integraciones | El sistema debe sincronizar empleados desde archivos TRESS y opcionalmente crear usuarios si el CSV incluye correo. | Media | superadmin | Implementado |
| RF-035 | Integraciones | El sistema debe sincronizar tipo de cambio USD/MXN mediante un proceso programado. | Media | system | Implementado |
| RF-036 | Reportes | El sistema debe ofrecer informacion operativa y presupuestal en dashboards y tablas exportables. | Media | superadmin | Implementado parcialmente |
| RF-037 | Aprobaciones | Un autorizador debe poder configurar uno o mas autorizadores delegados y activar el modo `Delegar` con fecha de termino opcional. | Alta | authorizer | Implementado |
| RF-038 | Aprobaciones | Los delegados vigentes deben visualizar los pendientes existentes y nuevos del titular para compra regular, OCD y OC por convenio. | Alta | authorizer | Implementado |
| RF-039 | Aprobaciones | El titular y sus delegados deben compartir el pendiente; la primera decision valida debe cerrarlo para todos. | Alta | authorizer | Implementado |
| RF-040 | Aprobaciones | Toda decision delegada debe registrar al usuario que actuo, al titular representado y el periodo de delegacion. | Alta | system | Implementado |
| RF-041 | Aprobaciones | El sistema debe mostrar una bandeja unificada con filtros para autorizaciones propias y delegadas. | Alta | authorizer | Implementado |
| RF-042 | Aprobaciones | Superadmin debe poder consultar y desactivar delegaciones activas registrando un motivo. | Media | superadmin | Implementado |
| RF-043 | Aprobaciones | Direccion General debe operar sin limite superior de autorizacion y Consejo de Administracion no debe existir como rol autorizador. | Alta | general_director | Implementado |

## 8. Requisitos no funcionales

### 8.1 Seguridad

- Autenticacion basada en Laravel/Breeze.
- Autorizacion por roles con Spatie Permission.
- Validaciones de request dedicadas para operaciones criticas.
- Middleware `auth`, `lock`, `role:*` y `ApiKeyMiddleware`.
- Restricciones de acceso por centro de costo, empresa y locacion en distintos flujos.

### 8.2 Rendimiento

- Uso de DataTables server-side en listados volumetricos.
- Jobs asincronos para EFOS.
- Scheduler para procesos recurrentes.
- Cache para niveles de aprobacion y estado de sincronizacion EFOS.

### 8.3 Usabilidad

- Interfaz Blade con modulos separados por rol.
- Flujos dedicados para proveedor y para staff.
- Formularios especializados para requisiciones, RFQ, OCD y recepcion.

### 8.4 Disponibilidad

- Dependencia de base de datos, cola y almacenamiento de archivos.
- Procesos programados para mantener datos de tipo de cambio y cierres automaticos.
- Sin evidencia suficiente en el repo para afirmar objetivos formales de SLA o alta disponibilidad.

### 8.5 Auditoria y trazabilidad

- Activity Log.
- Eventos de cambios en empleados.
- Campos de auditoria en entidades centrales.
- Notificaciones de cambios y estados en requisiciones, RFQ y ordenes.

### 8.6 Integridad de datos

- Validaciones de integridad por migraciones, relaciones y requests.
- Uso de transacciones en registro de proveedor, OCD, RFQ y recepciones.
- Bloqueo y reparto de presupuesto por cedula para evitar sobregiros funcionales.

### 8.7 Cumplimiento fiscal

- Integracion EFOS.
- Soporte REPSE.
- Catalogos SAT adicionales como retenciones.
- Control de RFC y validaciones relacionadas.

## 9. Reglas de negocio

| ID | Regla | Modulo | Estado |
|---|---|---|---|
| RN-001 | Un proveedor no debe considerarse limpio si su RFC aparece en EFOS como `Definitivo` o `Presunto`. | Proveedores / EFOS | Implementado |
| RN-002 | Un proveedor con servicios especializados debe capturar informacion REPSE. | Proveedores | Implementado |
| RN-003 | Una requisicion solo puede enviarse si esta en borrador y tiene al menos una partida. | Requisiciones | Implementado |
| RN-004 | Una requisicion solo puede editarse en `DRAFT`, `PAUSED` o `REJECTED`. | Requisiciones | Implementado |
| RN-005 | Una requisicion puede ser pausada por compras y luego reactivada. | Requisiciones | Implementado |
| RN-006 | Una requisicion rechazada debe registrar motivo y notificar al solicitante. | Requisiciones | Implementado |
| RN-007 | Una RFQ individual solo puede enviarse si esta en `DRAFT` y tiene proveedores asignados. | RFQ | Implementado |
| RN-008 | La seleccion de proveedores para RFQ usa proveedores con estado `approved`. | RFQ / Proveedores | Implementado |
| RN-009 | Una OCD debe determinar su nivel de aprobacion segun monto total. | OCD / Aprobaciones | Implementado |
| RN-010 | Una OCD no debe aprobarse si no existe presupuesto suficiente para sus categorias y mes de aplicacion. | OCD / Presupuesto | Implementado |
| RN-011 | Los centros de costo `FREE_CONSUMPTION` no requieren validacion presupuestal anual. | Presupuesto | Implementado |
| RN-012 | La recepcion solo puede registrarse contra ordenes en `ISSUED` o `PARTIALLY_RECEIVED`. | Recepciones | Implementado |
| RN-013 | Cuando una partida se marca `NO_CONFORME`, debe incluir tipo, descripcion amplia y evidencia fotografica. | Recepciones | Implementado |
| RN-014 | Las ordenes se cierran automaticamente por inactividad: 10 dias para OC regulares y 7 dias para OCD. | Ordenes de Compra | Implementado |
| RN-015 | La sincronizacion TRESS solo crea o actualiza usuarios si el CSV incluye correo valido. | Integracion TRESS | Implementado |
| RN-016 | El tipo de cambio USD/MXN se actualiza por scheduler en horario laboral entre semana. | Integracion tipo de cambio | Implementado |
| RN-017 | El consumo presupuestal final se materializa cuando la orden pasa a `DELIVERED_PENDING_RECEPTION`. | Presupuesto / Ordenes | Implementado |
| RN-018 | La definicion exacta del flujo de aprobacion de cotizaciones regulares requiere confirmacion funcional adicional. | RFQ / Ordenes | Pendiente de confirmacion |
| RN-019 | Una delegacion solo puede incluir usuarios activos con facultad autorizadora y acceso a cotizaciones y Ordenes de Compra. | Aprobaciones | Implementado |
| RN-020 | La delegacion tiene un solo salto; un delegado no puede propagar pendientes recibidos a sus propios delegados. | Aprobaciones | Implementado |
| RN-021 | El delegado utiliza la facultad efectiva registrada para el titular en el pendiente, no su limite personal. | Aprobaciones | Implementado |
| RN-022 | El acceso delegado termina inmediatamente al retirar al miembro, desactivar el periodo o alcanzar su fecha de termino. | Aprobaciones | Implementado |
| RN-023 | Direccion General es el unico rol autorizador configurable sin limite de monto. | Aprobaciones | Implementado |

## 10. Flujos principales

### 10.1 Alta y validacion de proveedor

1. El proveedor accede a `/register`.
2. Captura datos personales, empresa, RFC y datos REPSE si aplican.
3. El sistema crea usuario `supplier` y registro `Supplier`.
4. El sistema notifica a usuarios `buyer`.
5. El proveedor inicia sesion y continua con carga documental.
6. Compras revisa y acepta/rechaza documentacion.

### 10.2 Validacion EFOS

1. Un usuario `superadmin` dispara sincronizacion EFOS.
2. Se crea `jobId` y se despacha `SyncEfosJob`.
3. El job descarga el CSV desde `config('efos.csv_url')`.
4. El sistema hace `MERGE` sobre `sat_efos_69b`.
5. Los proveedores pueden consultarse contra la tabla local por RFC.

### 10.3 Creacion de requisicion

1. El solicitante crea requisicion con empresa, centro de costo, locacion y partidas.
2. La requisicion inicia como `DRAFT`.
3. El usuario puede editar, eliminar o enviar.
4. Al enviarse, cambia a `PENDING` y se notifican solicitante y compras.

### 10.4 Flujo de aprobacion

1. Compras revisa requisiciones pendientes.
2. Puede pausar, rechazar o enviar a cotizacion.
3. En OCD, el sistema calcula nivel de aprobacion requerido por monto.
4. El aprobador asignado puede aprobar, rechazar o devolver para correccion.
5. Al aprobarse la OCD, se compromete presupuesto.
6. El autorizador puede configurar delegados antes de activar el modo `Delegar`.
7. Al activarlo, los pendientes del titular se incorporan a la bandeja de cada delegado sin cambiar la asignacion original.
8. Titular y delegados pueden actuar; la decision se bloquea transaccionalmente y registra actor y titular.
9. Al vencer o desactivar el periodo, los delegados pierden acceso y el titular conserva los pendientes no resueltos.

### 10.5 RFQ y cotizacion

1. Compras define grupos o partidas de cotizacion.
2. Selecciona proveedores aprobados y fecha limite.
3. Se generan RFQ en borrador.
4. Compras envia RFQ individuales a proveedores.
5. El proveedor consulta la RFQ y captura respuesta.
6. Compras compara respuestas y selecciona resultado.

### 10.6 Generacion de orden de compra

1. Para OCD, el usuario crea orden directa con sus partidas.
2. El sistema valida disponibilidad presupuestal por categoria y mes.
3. El aprobador autoriza.
4. Se registra traza presupuestal y se notifica al proveedor.
5. La orden queda lista para recepcion.

Pendiente de confirmacion:

- El detalle funcional de emision de OC regular a partir del resumen de cotizaciones requiere revision adicional del flujo de `QuotationApprovalController` y uso en operacion real.

### 10.7 Recepcion de mercancia

1. El receptor accede a ordenes emitidas o parcialmente recibidas.
2. Registra locacion, fecha, remision y cantidades recibidas.
3. Si hay no conformidad, adjunta evidencia fotografica y notas.
4. El sistema genera una recepcion con folio `REC-YYYY-NNNN`.
5. La orden se conserva para futuras recepciones parciales o se completa segun cantidades.

### 10.8 Consulta de dashboard y reportes

1. Usuarios autenticados acceden al dashboard principal.
2. Compras y administracion consultan listados con DataTables.
3. Presupuestos criticos pueden visualizarse en dashboard presupuestal.
4. Reportes y salidas exportables quedan soportados parcialmente por librerias y vistas.

## 11. Integraciones externas

| Sistema externo | Tipo de integracion | Proposito | Frecuencia |
|---|---|---|---|
| SAT / EFOS | Descarga de CSV y carga local | Validar proveedores con riesgo fiscal | Bajo demanda por UI y soporte de job |
| TRESS | Lectura de CSV en disk `tress` | Sincronizar empleados y usuarios | Manual por comando |
| ExchangeRate API | HTTP GET | Obtener tipo de cambio USD/MXN | Horaria L-V entre 08:00 y 18:00 |
| Correo electronico | Notificaciones Laravel | Avisos de requisicion, RFQ, OCD, proveedor y recepcion | Event-driven |
| CRON / Scheduler | Laravel Schedule | Cierres automaticos y tipo de cambio | Programado |

## 12. Restricciones

- Varias validaciones dependen de roles Spatie correctamente sembrados.
- EFOS requiere URL configurada en `EFOS_CSV_URL`.
- Tipo de cambio requiere `services.exchangerate.key`.
- TRESS depende de archivos CSV depositados en el disk configurado `tress`.
- Parte de la logica presupuestal asume presupuesto anual aprobado y distribuciones mensuales existentes.

## 13. Supuestos

- El rol `buyer` representa operacion principal de compras.
- El rol `authorizer` es el actor tecnico usado para aprobacion de OCD.
- El sistema opera sobre una base SQL Server o compatible con `MERGE` y ciertos indices filtrados.
- Las notificaciones por correo y base de datos estan habilitadas en el entorno productivo.

## 14. Riesgos funcionales

- Coexisten flujos y nombres de estado con evolucion reciente; algunas rutas/controladores sugieren capas nuevas y legado activo al mismo tiempo.
- `QuotationSummary` muestra una mezcla entre niveles numericos y labels tipo `buyer/manager/director`, lo que requiere validacion antes de declararlo como flujo cerrado.
- `RequisitionWorkflowController` contiene un metodo `submitToApproval()` con `var_dump` y `die()`, señal de codigo no cerrado o residual.
- Varios modulos existen tecnicamente pero no necesariamente con cierre funcional formal: incidentes, comunicados, comparador y recuperacion de contrasena.
- No toda la capa de reporteria exportable pudo validarse a nivel de experiencia final desde el repo.

## 15. Pendientes de definicion

- Confirmar si el flujo regular de orden de compra siempre requiere aprobacion adicional del resumen de cotizacion.
- Confirmar definicion funcional exacta de `purchasing_manager` frente a roles tecnicos `authorizer`, `general_director` y `superadmin`.
- Confirmar si los dashboards actuales cubren todos los KPI ejecutivos esperados por negocio.
- Confirmar alcance oficial de incidentes y comunicados dentro de la version productiva.
- Confirmar si la recuperacion de contrasena esta habilitada operativamente para todos los perfiles.

## 16. Anexos

### 16.1 Fuentes tecnicas revisadas

- `routes/web.php`
- `routes/auth.php`
- `routes/console.php`
- `composer.json`
- `package.json`
- `app/Models/*`
- `app/Http/Controllers/*`
- `app/Services/*`
- `app/Console/Commands/*`
- `app/Jobs/SyncEfosJob.php`
- `database/migrations/*`
- `database/seeders/*`
- `tests/Feature/*`

### 16.2 Pruebas relevantes identificadas

Se localizaron, entre otras:

- `tests/Feature/SupplierRegistrationNotificationTest.php`
- `tests/Feature/EfosSyncTest.php`
- `tests/Feature/EmployeeCatalogTest.php`
- `tests/Feature/EmployeePromoteTest.php`
- `tests/Feature/ExchangeRateSyncTest.php`
- `tests/Feature/AnnualBudgetStoreTest.php`
- `tests/Feature/BudgetCedulaDistributionTest.php`
- `tests/Feature/CostCenterCrudTest.php`
- `tests/Feature/CostCenterImportTest.php`
