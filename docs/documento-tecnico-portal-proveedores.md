# Documento Tecnico del Portal de Proveedores

Fecha de levantamiento: 2026-04-30  
Base analizada: codigo fuente actual del repositorio Laravel en `SuppliersPortal/`

## 1. Objetivo

Este documento consolida el estado tecnico actual del Portal de Proveedores de TotalGas a partir del codigo real del proyecto. Su objetivo es servir como referencia unica para:

- entendimiento funcional y tecnico del sistema;
- onboarding de desarrollo;
- mantenimiento correctivo y evolutivo;
- auditoria de arquitectura, seguridad y operacion.

El contenido prioriza el estado implementado hoy en el repositorio sobre documentacion historica que pueda haber quedado desfasada.

## 2. Resumen ejecutivo

El proyecto es una aplicacion Laravel 12 sobre PHP 8.2 orientada al ciclo de abastecimiento y control presupuestal. La plataforma cubre registro y cumplimiento documental de proveedores, requisiciones internas, planeacion y solicitud de cotizaciones, aprobaciones por monto, ordenes de compra regulares y directas, recepciones fisicas, catalogos maestros y controles satelitales como EFOS, REPSE y tipo de cambio.

El sistema se implementa principalmente como aplicacion web Blade con apoyo puntual de Livewire 3, autorizacion por roles con Spatie Permission, auditoria con Spatie Activity Log, colas basadas en base de datos, notificaciones por correo y base de datos, y uso intensivo de SQL Server como motor principal. Para testing, el proyecto usa SQLite en memoria.

Estado actual levantado:

- 319 rutas registradas en Laravel.
- 56 migraciones.
- 47 modelos Eloquent.
- 50+ controladores incluyendo autenticacion.
- 13 servicios de negocio.
- 8 comandos Artisan personalizados.
- 4 jobs en cola.
- 21 notificaciones.
- 3 componentes Livewire.
- 12 pruebas Feature y 1 prueba Unit.

## 3. Stack tecnologico

### Backend

- PHP `^8.2`
- Laravel `^12.0`
- Livewire `^3.7`
- SQL Server como base principal
- SQLite en pruebas

### Paquetes principales

- `spatie/laravel-permission`
- `spatie/laravel-activitylog`
- `barryvdh/laravel-dompdf`
- `phpoffice/phpspreadsheet`
- `yajra/laravel-datatables-oracle`
- `laravel/breeze`

### Frontend

- Blade como capa principal de presentacion
- Livewire para formularios y wizard de cotizaciones
- Vite `^7.0.4`
- Alpine.js `^3.4.2`
- Bootstrap y librerias JS vendorizadas en `public/assets/vendor`

## 4. Arquitectura general

La aplicacion sigue una arquitectura Laravel clasica con separacion por capas:

- `routes/`: exposicion de endpoints web, API y scheduler.
- `app/Http/Controllers`: coordinacion HTTP por modulo.
- `app/Http/Requests`: validacion de entrada.
- `app/Models`: modelo transaccional de negocio.
- `app/Services`: logica transversal y orquestacion de procesos.
- `app/Jobs`: trabajo asincrono y alertas.
- `app/Notifications`: comunicaciones a usuarios internos y proveedores.
- `app/Livewire`: componentes reactivos para formularios complejos.
- `resources/views`: vistas Blade por modulo.
- `database/migrations` y `database/seeders`: persistencia e inicializacion.

Patrones observados:

- Controladores delgados con validacion mediante Form Requests.
- Logica de negocio critica distribuida entre modelos y servicios.
- Flujos de estado basados en strings y, parcialmente, enums PHP.
- Autorizacion basada principalmente en roles y filtros de visibilidad por empresa y centro de costo.
- Integracion de operaciones asincronas mediante cola `database`.

## 5. Bootstrap, middleware y entry points

### Bootstrapping

`bootstrap/app.php` registra:

- rutas web en `routes/web.php`;
- rutas API en `routes/api.php`;
- comandos y scheduler en `routes/console.php`;
- endpoint de salud en `/up`.

### Middleware personalizados

- `lock` -> `App\Http\Middleware\CheckLockScreen`
- `api.key` -> `App\Http\Middleware\ApiKeyMiddleware`

### Middleware de terceros usados como alias

- `role`
- `permission`
- `role_or_permission`

### Puntos de entrada principales

- `/login`, `/register`, recuperacion de contrasena y verificacion de correo.
- `/dashboard` para usuarios internos autenticados.
- `/supplier/*` para portal del proveedor con `role:supplier`.
- `/api/empleados/*` para integracion externa con llave API.

## 6. Seguridad y control de acceso

El sistema usa autenticacion Laravel/Breeze y autorizacion por roles con Spatie Permission.

Roles observables en codigo y documentacion operativa:

- `superadmin`
- `buyer`
- `supplier`
- `requester`
- `purchasing_manager`

Controles relevantes:

- pantalla de bloqueo de sesion con middleware `lock`;
- restriccion de rutas por `auth`, `role:*` y `lock`;
- validacion fuerte mediante Form Requests;
- filtros funcionales por empresa y centro de costo;
- notificaciones por eventos de negocio;
- uso de `SoftDeletes` en varias entidades criticas.

Hallazgos importantes del estado actual:

- `config/database.php` contiene credenciales y host por defecto para SQL Server en codigo fuente; tecnicamente funciona, pero es una practica insegura y deberia depender solo de `.env`.
- las rutas `/dev/logs` y `DELETE /dev/logs` estan declaradas fuera de grupos visibles de middleware en `routes/web.php`; el documento las considera un riesgo operativo hasta validar su proteccion real.

## 7. Panorama funcional por modulos

### 7.1 Autenticacion y cuentas

Incluye login, logout, recuperacion de contrasena, verificacion de correo, perfil, cambio de contrasena y lock screen.

Controladores relevantes:

- `Auth\AuthenticatedSessionController`
- `Auth\SupplierRegistrationController`
- `ProfileController`
- `ProfilePasswordController`
- `LockScreenController`

### 7.2 Usuarios, staff y empleados

Administra usuarios internos, usuarios-proveedor, asignacion de roles, empresas y centros de costo por usuario. Adicionalmente gestiona el catalogo de empleados y su promocion a usuario del portal.

Elementos relevantes:

- `UserController`
- `EmployeeController`
- tablas pivote `company_user` y `cost_center_user`
- notificacion `StaffWelcomeNotification`

### 7.3 Proveedores y cumplimiento documental

Gestiona:

- alta de proveedores;
- expediente documental;
- datos bancarios;
- validaciones REPSE;
- SIROC;
- anuncios dirigidos a proveedores.

Controladores clave:

- `SupplierController`
- `SupplierDocumentController`
- `SupplierBankController`
- `SupplierSirocController`
- `DocumentReviewController`
- `SupplierPortalController`

Modelos clave:

- `Supplier`
- `SupplierDocument`
- `SupplierSiroc`
- `Announcement`
- `AnnouncementSupplier`

Reglas observadas:

- exclusion de proveedores EFOS 69B mediante `Supplier::scopeNotEfos69b()`;
- validacion de REPSE para proveedores de servicios especializados;
- el proveedor opera su propio portal autenticado en `/supplier`.

### 7.4 Catalogos maestros

Modulos CRUD para:

- empresas;
- estaciones;
- ubicaciones de recepcion;
- impuestos;
- categorias;
- categorias de gasto;
- centros de costo;
- departamentos;
- catalogo de proveedores SAT-retenciones;
- catalogo auxiliar `cat_suppliers`.

Controladores clave:

- `CompanyController`
- `StationController`
- `ReceivingLocationController`
- `TaxController`
- `CategoryController`
- `CostCenterController`
- `CostCenterImportController`
- `DepartmentController`
- `ExpenseCategoryController`
- `SatRetencionController`
- `CatSupplierController`

### 7.5 Catalogo de productos y servicios

El catalogo `products_services` funciona como base de requisicion. Un producto o servicio solo puede usarse cuando:

- esta activo;
- su `status` es `ACTIVE`;
- tiene estructura contable completa.

Controlador: `ProductServiceController`  
Modelo: `ProductService`  
Enum: `ProductServiceStatus`

Estados observados:

- `PENDING`
- `ACTIVE`
- `INACTIVE`
- `REJECTED`

### 7.6 Presupuestos y control presupuestal

El sistema incluye:

- presupuestos anuales;
- distribuciones mensuales por categoria;
- movimientos presupuestales;
- compromisos y consumo por orden;
- soporte para cedulas presupuestales 2026;
- importadores especializados por empresa.

Controladores:

- `AnnualBudgetController`
- `BudgetMonthlyDistributionController`
- `BudgetMovementController`

Servicios:

- `BudgetService`
- `BudgetAllocationService`
- `BudgetCategorySummaryService`
- `LegacyBudgetService`
- `Budget2026TsaImportService`
- `Budget2026SmaImportService`
- `Budget2026GasomexImportService`
- `Budget2026DgaImportService`

Modelos clave:

- `AnnualBudget`
- `BudgetMonthlyDistribution`
- `BudgetMovement`
- `BudgetMovementDetail`
- `BudgetCommitment`
- `BudgetCedula`

Notas tecnicas:

- `AnnualBudget` trabaja con estados como `PLANIFICACION`, `APROBADO` y `CERRADO`.
- la validacion presupuestal es bloqueante en flujos de compra.
- la trazabilidad de compromiso/consumo/liberacion esta repartida entre servicios y hooks de modelos.

### 7.7 Requisiciones

Es el punto de arranque del flujo interno de compra. Incluye creacion, edicion, borrador, envio, validacion tecnica, pausa, rechazo, cancelacion y seguimiento.

Controladores:

- `RequisitionController`
- `RequisitionWorkflowController`

Componente Livewire:

- `RequisitionForm`

Modelo:

- `Requisition`

Estados formalizados en `RequisitionStatus`:

- `DRAFT`
- `PENDING`
- `PAUSED`
- `APPROVED`
- `REJECTED`
- `IN_QUOTATION`
- `QUOTED`
- `PENDING_BUDGET_ADJUSTMENT`
- `COMPLETED`
- `CANCELLED`

Observaciones:

- el modelo concentra mucha logica de negocio y notificaciones;
- el folio se genera como `REQ-YYYY-###`;
- existe doble manejo de cancelacion: una ruta CRUD y otra ruta de workflow.

### 7.8 Planeacion de cotizacion y RFQ

El flujo de cotizacion soporta:

- agrupacion de partidas;
- sugerencia y seleccion de proveedores;
- envio individual o masivo;
- bandejas de seguimiento;
- comparacion de respuestas;
- aprobacion de resumen de cotizacion.

Controladores:

- `QuotationPlannerController`
- `RfqController`
- `RfqInboxController`
- `RfqComparisonController`
- `QuotationApprovalController`
- `ApprovalLevelController`

Componentes Livewire:

- `App\Livewire\Rfq\QuotationWizard`
- `App\Livewire\Rfq\RfqIndex`

Modelos:

- `QuotationGroup`
- `QuotationGroupItem`
- `Rfq`
- `RfqSupplier`
- `RfqResponse`
- `QuotationSummary`
- `ApprovalLevel`

Servicios:

- `ApprovalService`
- `ApprovalDelegationService`
- `ApprovalDecisionService`
- `AuthorizationInboxService`
- `PricingService`

La delegacion temporal conserva el aprobador titular en las entidades existentes y calcula el acceso de los delegados mediante `approval_delegations` y `approval_delegation_members`. Las decisiones de los tres flujos se normalizan adicionalmente en `approval_decisions`, con bloqueo de fila para evitar resoluciones simultaneas.

Direccion General se representa con `approval_limit = null` como facultad ilimitada. El rol Consejo de Administracion fue retirado del seeder y de la resolucion de autorizadores.

### 7.9 Ordenes de compra

Hay dos variantes:

- orden de compra regular derivada de requisicion/cotizacion;
- orden de compra directa.

Controladores:

- `PurchaseOrderController`
- `DirectPurchaseOrderController`

Modelos:

- `PurchaseOrder`
- `PurchaseOrderItem`
- `DirectPurchaseOrder`
- `DirectPurchaseOrderItem`
- `DirectPurchaseOrderApproval`
- `DirectPurchaseOrderDocument`

Comportamiento observado:

- las OC regulares usan cierre por inactividad a 10 dias;
- las OCD usan cierre por inactividad a 7 dias desde `submitted_at`;
- ambos modelos disparan consumo o sincronizacion presupuestal segun cambios de estado;
- las OCD generan folio `OCD-YYYY-####`.

### 7.10 Recepciones y entregas fisicas

El sistema separa:

- entrega del proveedor;
- recepcion formal en ubicacion.

Controladores:

- `SupplierDeliveryController`
- `ReceptionController`

Servicio:

- `ReceptionService`

Modelos:

- `Reception`
- `ReceptionItem`
- `SupplierDeliveryEvidence`
- `ReceivingLocation`

Reglas importantes:

- una orden solo puede recibirse si esta `ISSUED` o `PARTIALLY_RECEIVED`;
- si el proveedor marca entrega, la orden pasa a `DELIVERED_PENDING_RECEPTION`;
- al completar recepcion se recalculan estados agregados y se notifican usuarios clave.

### 7.11 EFOS, retenciones y tipo de cambio

Integraciones y catalogos fiscales:

- EFOS SAT 69B
- SAT retenciones
- tipo de cambio USD/MXN

Controladores:

- `SatEfos69bController`
- `SatRetencionController`

Comandos y jobs:

- `SyncEfos69b`
- `SyncEfosJob`
- `SyncExchangeRate`

Modelos:

- `SatEfos69b`
- `SatRetencion`
- `ExchangeRate`

## 8. Inventario de componentes tecnicos

### Modelos principales por dominio

Usuarios y seguridad:

- `User`
- `Employee`
- `EmployeeEvent`

Proveedores:

- `Supplier`
- `SupplierDocument`
- `SupplierSiroc`
- `SupplierDeliveryEvidence`
- `CatSupplier`

Compras:

- `Requisition`
- `RequisitionItem`
- `Rfq`
- `RfqSupplier`
- `RfqResponse`
- `QuotationSummary`
- `QuotationGroup`
- `QuotationGroupItem`
- `PurchaseOrder`
- `PurchaseOrderItem`
- `DirectPurchaseOrder`
- `DirectPurchaseOrderItem`
- `DirectPurchaseOrderApproval`
- `DirectPurchaseOrderDocument`
- `Reception`
- `ReceptionItem`

Presupuesto:

- `AnnualBudget`
- `BudgetMonthlyDistribution`
- `BudgetMovement`
- `BudgetMovementDetail`
- `BudgetCommitment`
- `BudgetCedula`
- `ApprovalLevel`

Catalogos:

- `Company`
- `Station`
- `ReceivingLocation`
- `CostCenter`
- `Department`
- `Category`
- `ExpenseCategory`
- `ProductService`
- `Tax`
- `SatRetencion`

Comunicacion y soporte:

- `Announcement`
- `AnnouncementSupplier`
- `Incident`
- `IncidentAttachment`

### Servicios

- `ApprovalService`
- `AlertRecipientService`
- `BudgetService`
- `BudgetAllocationService`
- `BudgetCategorySummaryService`
- `LegacyBudgetService`
- `CostCenterImportService`
- `PricingService`
- `ReceptionService`
- `Budget2026TsaImportService`
- `Budget2026SmaImportService`
- `Budget2026GasomexImportService`
- `Budget2026DgaImportService`

### Jobs

- `SyncEfosJob`
- `SendDeliveryAlertDay0Job`
- `SendDeliveryAlertDay2Job`
- `SendDeliveryAlertDay3Job`

### Comandos Artisan

- `purchase-orders:close-inactive`
- `exchange-rates:sync`
- `efos:sync`
- `tress:sync-users`
- `budget:import-2026-tsa`
- `budget:import-2026-sma`
- `budget:import-2026-gasomex`
- `budget:import-2026-dga`

### Eventos y listeners

Registrados en `EventServiceProvider`:

- `Login` -> `UpdateLastLogin`
- `ProductServiceApproved` -> `ReactivatePausedRequisitions`

## 9. Rutas y segmentacion funcional

La ruta `web.php` concentra casi toda la aplicacion. Los grupos principales son:

- panel interno `auth + lock`;
- portal proveedor `auth + role:supplier`;
- administracion global `auth + lock + role:superadmin`;
- compras y recepciones `auth + lock + role:superadmin|buyer`;
- API externa de empleados protegida por `api.key`.

Areas de rutas mas relevantes:

- `users/*`
- `admin/announcements/*`
- `admin/review/*`
- `documents/*`
- `suppliers/*/sirocs`
- `annual_budgets/*`
- `budget_monthly_distributions/*`
- `budget_movements/*`
- `requisitions/*`
- `rfq/*`
- `products-services/*`
- `supplier/*`
- `purchase-orders/*`
- `direct-purchase-orders/*`
- `receptions/*`
- `receiving-locations/*`

## 10. Flujos de negocio principales

### Flujo 1. Alta y cumplimiento de proveedor

1. El proveedor se registra por `register`.
2. Se crea usuario y perfil de proveedor.
3. Carga documentos y datos bancarios.
4. Admin revisa expediente documental.
5. Si el proveedor presta servicios especializados, aplica validacion REPSE.
6. El proveedor queda habilitado para portal, RFQ y entregas.

### Flujo 2. Requisicion a compra

1. Usuario interno crea requisicion.
2. Agrega partidas del catalogo disponible.
3. Envia a Compras.
4. Compras valida, pausa, rechaza o manda a cotizacion.
5. Se planea RFQ y se seleccionan proveedores.
6. Proveedores responden.
7. Se compara y aprueba resumen de cotizacion.
8. Se genera orden de compra.
9. El proveedor registra entrega.
10. La ubicacion registra recepcion.

### Flujo 3. Compra directa

1. Usuario comprador crea OCD.
2. Agrega partidas y justificacion.
3. Se calcula nivel de aprobacion por monto.
4. Se envia a aprobador asignado.
5. Puede aprobarse, rechazarse o devolverse.
6. Si se aprueba, se emite.
7. El proveedor entrega y la ubicacion recibe.

### Flujo 4. Presupuesto

1. Se define presupuesto anual.
2. Se distribuye por mes y categoria.
3. Se compromete presupuesto al emitir compra.
4. Se consume al registrar entrega o recepcion segun el flujo.
5. Se libera en cierres o retornos aplicables.

### Flujo 5. Integracion de empleados

1. Un proceso externo llama `POST /api/empleados/recibir`.
2. Se registra o actualiza empleado.
3. Se resuelven lideres por segunda pasada con `POST /api/empleados/resolver-lideres`.
4. Los empleados pueden promoverse a usuarios internos.

## 11. Persistencia y esquema de base de datos

### Motor principal

El proyecto esta configurado para `sqlsrv` como conexion principal. Se observan adaptaciones especificas para SQL Server:

- `PDO::SQLSRV_ATTR_ENCODING => UTF8`
- `use_iso_datetime_format => true`
- uso de `MERGE` en sincronizacion EFOS

### Agrupacion de migraciones

Base y framework:

- usuarios, cache, jobs, permisos, activity log, notificaciones

Proveedores:

- suppliers
- supplier_documents
- supplier_sirocs
- sat_efos69bs
- cat_suppliers
- announcements

Organizacion:

- companies
- stations
- cost_centers
- departments
- taxes
- categories
- receiving_locations
- employees

Compras:

- products_services
- requisitions
- requisition_items
- quotation_groups
- quotation_group_items
- rfqs
- rfq_responses
- rfq_suppliers
- quotation_summaries
- approval_levels
- purchase_orders
- purchase_order_items
- odc_direct_purchase_orders
- odc_direct_purchase_order_items
- odc_direct_purchase_order_approvals
- odc_direct_purchase_order_documents
- receptions
- reception_items
- supplier_delivery_evidences

Presupuesto:

- annual_budgets
- budget_monthly_distributions
- budget_movements
- budget_movement_details
- budget_commitments
- budget_cedulas

Fiscal y auxiliares:

- exchange_rates
- sat_retencions

### Seeders disponibles

- `DatabaseSeeder`
- `RolePermissionSeeder`
- `UserRoleSeeder`
- `CompanySeeder`
- `StationSeeder`
- `DepartmentSeeder`
- `CostCenterSeeder`
- `CategorySeeder`
- `ExpenseCategorySeeder`
- `TaxSeeder`
- `SupplierSeeder`
- `ReceivingLocationSeeder`
- `ApprovalLevelSeeder`
- `AnnualBudgetSeeder`
- `BudgetCedulaSeeder`
- `QuotationPlannerTestSeeder`
- `SatRetencionSeeder`

## 12. Integraciones externas

### SAT EFOS 69B

- descarga CSV externo;
- parseo por lotes;
- upsert con SQL `MERGE`;
- exposicion por UI y sincronizacion asincrona.

### Tipo de cambio

- consulta HTTP a `exchangerate-api.com`;
- guarda o actualiza `ExchangeRate`.

### Integracion TRESS / empleados

- existe disco `tress` en `config/filesystems.php`;
- existe comando `SyncTressUsers`;
- existe API protegida para recepcion de empleados.

### Importadores presupuestales 2026

- TSA
- SMA
- Gasomex
- DGA

Usan PhpSpreadsheet y configuraciones especificas bajo `config/budget_2026_*.php`.

## 13. Almacenamiento de archivos

Discos observados:

- `local` -> `storage/app/private`
- `public` -> ruta configurable por `STORAGE_PUBLIC_PATH`
- `s3`
- `tress`

Particularidad tecnica:

- existe una ruta `GET /storage/{path}` para servir archivos desde PHP cuando el enlace simbolico a una ruta de red no funciona en desarrollo local.

Casos de uso de archivos:

- documentos de proveedor;
- PDFs de cotizacion;
- evidencias de entrega;
- remisiones de recepcion;
- importaciones de EFOS y presupuestos.

## 14. Procesamiento asincrono y scheduler

### Cola

- conexion por defecto: `database`
- tabla principal: `jobs`
- fallos en `failed_jobs`

### Jobs identificados

- alertas de entrega dia 0, 2 y 3;
- sincronizacion EFOS asincrona.

### Scheduler visible en `routes/console.php`

- `purchase-orders:close-inactive` diario a las `00:30`
- `exchange-rates:sync` cada hora, lunes a viernes, entre `08:00` y `18:00`
- `approval-delegations:expire` cada minuto para cerrar periodos programados; las consultas tambien validan `ends_at` directamente para revocar acceso sin depender del scheduler.

Observacion relevante:

- el comando `efos:sync` existe, pero no aparece programado en el scheduler visible del repositorio. Si se ejecuta por cron externo o manualmente, eso no esta reflejado en el codigo centralizado de scheduling.

## 15. Notificaciones y comunicaciones

Las notificaciones cubren eventos funcionales clave:

- bienvenida de personal;
- alta de proveedor;
- envio y rechazo de requisiciones;
- avance a cotizacion;
- RFQ nueva o cancelada;
- alta y aprobacion de OCD;
- activacion de delegacion con resumen de pendientes;
- avisos individuales de pendientes nuevos a titular y delegados;
- aviso al titular cuando un delegado actua en su representacion;
- alertas y cierres por inactividad;
- recepcion completada;
- nuevos productos solicitados.

Canales observados:

- `mail`
- `database`

## 16. Frontend y experiencia de usuario

El frontend es mayormente Blade con apoyo de:

- Livewire para formularios complejos;
- DataTables para bandejas;
- scripts dedicados en `public/js`;
- assets vendorizados en `public/assets/vendor`.

Vistas representativas:

- dashboard interno;
- portal de proveedor;
- bandejas de requisiciones, RFQ, ordenes y recepciones;
- bandeja unificada de autorizaciones propias y delegadas;
- control persistente `Delegar` en la barra superior;
- modulo de configuracion personal e historial de delegaciones;
- supervision de delegaciones activas para Superadmin;
- formularios presupuestales;
- revisiones administrativas.

## 17. Calidad, pruebas y mantenibilidad

Pruebas actuales:

- 12 pruebas Feature
- 1 prueba Unit

Cobertura funcional visible en pruebas:

- registro de proveedor y notificaciones;
- tipo de cambio;
- EFOS sync;
- catalogo y promocion de empleados;
- perfil;
- dominio de correo permitido;
- presupuesto anual y cedulas;
- CRUD e importacion de centros de costo.

Lectura tecnica del estado actual:

- hay pruebas relevantes en integraciones y presupuestos, pero la cobertura automatizada sigue siendo parcial frente al tamano del sistema;
- flujos complejos como RFQ, OCD, recepciones, aprobaciones multinivel y portal proveedor no muestran una cobertura equivalente en `tests/Feature`.

## 18. Riesgos tecnicos y discrepancias detectadas

### Discrepancias entre documentacion y codigo

- el `README.md` no refleja con precision varias decisiones actuales del sistema;
- existe documentacion historica util, pero no siempre alineada con rutas, scheduler y estados reales;
- el scheduler operativo documentado previamente no coincide por completo con `routes/console.php`.

### Riesgos tecnicos

- credenciales por defecto de SQL Server presentes en `config/database.php`;
- rutas de desarrollo aparentemente expuestas fuera de middleware visible;
- logica de estados repartida entre strings libres, enums parciales y side effects en modelos;
- alta concentracion de reglas de negocio dentro de modelos y controladores, lo que dificulta pruebas unitarias finas;
- fuerte dependencia de SQL Server en consultas y `MERGE`, lo que reduce portabilidad;
- cobertura de pruebas insuficiente para modulos de compras completos.

### Deuda tecnica observable

- coexistencia de documentacion vieja y nueva;
- mezcla de nomenclaturas de estado en mayusculas, minusculas y labels historicos;
- scheduler parcialmente documentado fuera del codigo;
- algunos flujos dependen de hooks `updated()` en modelos, lo que vuelve menos explicita la orquestacion.

## 19. Recomendaciones tecnicas prioritarias

1. Centralizar este documento como fuente vigente y retirar o marcar como historica la documentacion ya desalineada.
2. Mover credenciales hardcodeadas de `config/database.php` a variables de entorno obligatorias.
3. Validar y proteger formalmente las rutas de desarrollo `/dev/logs`.
4. Unificar estados de negocio criticos con enums o value objects consistentes.
5. Llevar el scheduler completo al codigo fuente o documentar explicitamente el cron externo real.
6. Incrementar pruebas Feature para RFQ, OCD, recepciones, portal proveedor y cierre automatico por inactividad.
7. Seguir extrayendo logica de modelos y controladores hacia servicios mas testeables.

## 20. Conclusion

El Portal de Proveedores ya es una plataforma transaccional amplia y operativamente madura, con cobertura real de abastecimiento, cumplimiento documental, presupuesto y recepcion. Su principal reto actual no es de funcionalidad base, sino de consolidacion tecnica: reducir deuda documental, endurecer seguridad/configuracion, homogeneizar estados y aumentar cobertura automatizada en los flujos mas sensibles.

Este documento representa la linea base tecnica del proyecto al 30 de abril de 2026 segun el codigo presente en el repositorio.
