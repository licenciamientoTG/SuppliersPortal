# Diseño: CRUD "Cédulas de Gasto" (categorías de gasto + cédulas)

**Fecha:** 2026-07-07
**Estado:** Aprobado
**Disparador:** `resources/views/budget_monthly_distributions/partials/form.blade.php` (donde
hoy solo se *leen* categorías/cédulas) y falta de cualquier pantalla de administración
para `expense_categories` / `budget_cedulas`, que hoy solo se mantienen vía seeders
(`ExpenseCategorySeeder`, `BudgetCedulaSeeder`).

## Objetivo

Dar de alta una pantalla de administración única, tipo catálogo maestro-detalle, donde
un usuario con permiso de `budget_control` pueda crear, editar y eliminar (borrado
lógico) tanto **categorías de gasto** (`expense_categories`) como sus **cédulas**
(`budget_cedulas`, subcategorías) — sin salir de una sola vista, con SweetAlert2 para
toda notificación no ligada a un campo específico, y sin recargar la página completa
en ninguna operación.

## Contexto del sistema actual

- `expense_categories`: `code` (único, máx 3, mayúsculas), `name`, `description`,
  `status` (`ACTIVO`/`INACTIVO`), soft delete + auditoría (`created_by`/`updated_by`/
  `deleted_by`). Modelo `App\Models\ExpenseCategory` ya tiene `canBeDeactivated()`
  (bloquea pasar a `INACTIVO` si tiene `monthlyDistributions()` con
  `assigned_amount > 0`) invocado desde un hook `updating` en `boot()`.
- `budget_cedulas`: `expense_category_id` (FK), `name`, `status`, soft delete +
  auditoría. Modelo `App\Models\BudgetCedula` **no tiene ninguna guarda de
  desactivación/borrado hoy**.
- Ambos catálogos hoy solo se pueblan por seeders (`database/seeders/
  ExpenseCategorySeeder.php`, `BudgetCedulaSeeder.php`) — no existe controlador ni
  vista de administración. `ExpenseCategoryController` solo expone endpoints de
  **lectura** (`getForSelect`, `byBudget`, `getByCostCenter`,
  `getCedulasByCostCenter`) y un `store()` aislado sin `update`/`destroy`.
- Tablas que referencian `expense_category_id` y/o `budget_cedula_id` (relevantes para
  la regla de borrado): `budget_monthly_distributions` (ambas FKs), `requisition_items`
  (ambas FKs), `budget_movement_details`, `budget_commitments`,
  `odc_direct_purchase_order_items` (estas tres últimas con `budget_cedula_id`).
- Patrón de CRUD ya establecido en el proyecto (`CostCenterController`,
  `CategoryController`, `employees/index.blade.php`): Controller REST + Blade + `fetch`
  + SweetAlert2, modales Bootstrap 5, errores 422 mostrados inline
  (`is-invalid`/`invalid-feedback`) y SweetAlert2 reservado para: confirmaciones de
  borrado, mensajes de éxito (toast auto-cierre) y errores de negocio/red. Livewire
  (v3) existe en el proyecto pero solo se usa para wizards multi-paso complejos
  (Requisiciones, RFQ) — no para catálogos simples.
- Sidebar: `resources/views/layouts/partials/sidebar-staff.blade.php` ya tiene un
  submenú colapsable **"Control Presupuestal"** (`@moduleAccess('budget_control')`,
  líneas 244-286) con: Centros de Costo, Presupuestos Anuales, Distribuciones
  Mensuales, Movimiento Presupuestal, Categorias (esta última es
  `App\Models\Category`, catálogo de categorías de **centro de costo** — entidad
  totalmente distinta a `ExpenseCategory`, no confundir).
- `config/module_access.php`: módulo `budget_control` ya permite roles `authorizer`,
  `superadmin`, `general_director`, `accounting`, `department_head`.

## Decisiones de diseño

1. **Patrón técnico: Controller + Blade + AJAX (`fetch`) + SweetAlert2**, sin
   Livewire — consistente con el resto de catálogos del proyecto.
2. **Una sola vista maestro-detalle** (`resources/views/expense_cedulas_catalog/
   index.blade.php`), sin páginas separadas de create/edit: panel izquierdo =
   categorías, panel derecho = cédulas de la categoría seleccionada. Todo movimiento
   (crear/editar/eliminar en cualquiera de los dos) ocurre por AJAX contra el mismo
   controlador, sin recargar la página.
3. **Sin DataTables/yajra**: volumen bajo (13 categorías, máx. ~35 cédulas por
   categoría) — se cargan como JSON simple y se filtran con un buscador de texto en
   cliente. Evita el costo de paginación server-side para un catálogo de este tamaño.
4. **Regla de borrado/desactivación unificada** (aplica igual a "eliminar" que a poner
   `status = INACTIVO`):
   - **Categoría**: bloqueada si tiene cédulas asociadas no eliminadas, o si tiene uso
     directo en `budget_monthly_distributions` o `requisition_items` (vía
     `expense_category_id`).
   - **Cédula**: bloqueada si tiene uso en `budget_monthly_distributions`,
     `requisition_items`, `budget_movement_details`, `budget_commitments` u
     `odc_direct_purchase_order_items` (vía `budget_cedula_id`).
   - Se expone como `isInUse(): bool` en ambos modelos (nuevo en `BudgetCedula`,
     refactor del `canBeDeactivated()` existente en `ExpenseCategory` para reutilizar
     la misma lógica en `destroy`).
   - Si está bloqueada, el backend responde `409` con un mensaje explicativo; el
     frontend lo muestra con `Swal.fire({icon:'error', ...})`.
5. **Permiso**: módulo `budget_control` (ya existente), mismo grupo de rutas que
   `cost-centers`/`annual_budgets`/`budget_monthly_distributions`.
6. **Ubicación en sidebar**: nueva entrada **"Cédulas de Gasto"** dentro de "Control
   Presupuestal", inmediatamente después de "Centros de Costo" (catálogo base
   necesario antes de crear presupuestos).
7. **Feedback al usuario**: errores de validación (422) inline por campo; SweetAlert2
   para todo lo demás — éxito (toast ~2s), confirmación de borrado, y errores de
   negocio/red (mensaje completo del backend).

## Rutas

Nuevas, dentro del grupo existente `Route::middleware('module.access:budget_control')`
en `routes/web.php` (junto a `cost-centers`, `annual_budgets`, etc.):

```
GET    expense-cedulas                                          expense-cedulas.index
GET    expense-cedulas/categories                                expense-cedulas.categories.data
POST   expense-cedulas/categories                                expense-cedulas.categories.store
PUT    expense-cedulas/categories/{expenseCategory}               expense-cedulas.categories.update
DELETE expense-cedulas/categories/{expenseCategory}               expense-cedulas.categories.destroy
GET    expense-cedulas/categories/{expenseCategory}/cedulas        expense-cedulas.cedulas.data
POST   expense-cedulas/cedulas                                    expense-cedulas.cedulas.store
PUT    expense-cedulas/cedulas/{budgetCedula}                     expense-cedulas.cedulas.update
DELETE expense-cedulas/cedulas/{budgetCedula}                     expense-cedulas.cedulas.destroy
```

Todos los endpoints (excepto `index`) responden JSON.

## Cambios por componente

### 1. `app/Models/ExpenseCategory.php`

- Nuevo método `isInUse(): bool` — reemplaza el chequeo interno de
  `canBeDeactivated()` para incluir también `requisitionItems()` (ver relación nueva
  abajo) además de `monthlyDistributions()`. `canBeDeactivated()` pasa a delegar en
  `!$this->isInUse() || $this->isInactive()` para no romper el hook `updating`
  existente.
- Nuevo método `hasCedulas(): bool` → `$this->cedulas()->exists()`.
- Nueva relación `requisitionItems(): HasMany` (vía `expense_category_id`), si
  `RequisitionItem` no la expone ya en sentido inverso — confirmar en implementación.

### 2. `app/Models/BudgetCedula.php`

- Nuevas relaciones `requisitionItems()`, `budgetMovementDetails()`,
  `budgetCommitments()`, `directPurchaseOrderItems()` (todas vía `budget_cedula_id`).
- Nuevo método `isInUse(): bool` → `OR` de `monthlyDistributions()->exists()` y las
  cuatro relaciones anteriores.
- Nuevo `boot()` con hooks `creating`/`updating`/`deleting` análogos a
  `ExpenseCategory` (mayúsculas de `status` no aplica aquí porque no tiene `code`;
  auditoría `created_by`/`updated_by`/`deleted_by` vía `Auth::id()`; bloquear
  `updating` a `INACTIVO` si `isInUse()`).

### 3. `app/Http/Controllers/ExpenseCedulaCatalogController.php` (nuevo)

- `index()`: retorna la vista con las categorías precargadas (para el primer render
  sin esperar un fetch).
- `categoriesData()`: JSON de todas las categorías no eliminadas, con
  `cedulas_count` (`withCount('cedulas')`), ordenadas por `code`.
- `storeCategory(Request)` / `updateCategory(Request, ExpenseCategory)`: valida
  (`code` único ignorando self, `name`, `description`, `status`), crea/actualiza.
- `destroyCategory(ExpenseCategory)`: si `isInUse()` o `hasCedulas()` → 409 con
  mensaje; si no, `->delete()` (soft delete, dispara auditoría en `boot()`).
- `cedulasData(ExpenseCategory)`: JSON de cédulas no eliminadas de esa categoría,
  ordenadas por `name`.
- `storeCedula(Request)` / `updateCedula(Request, BudgetCedula)`: valida
  (`expense_category_id` requerido/existente, `name`, `status`).
- `destroyCedula(BudgetCedula)`: si `isInUse()` → 409 con mensaje; si no,
  `->delete()`.
- Todas las respuestas de error de validación usan el formato estándar Laravel 422
  (`{errors: {...}}`) que ya consume el patrón `is-invalid` del proyecto.

### 4. `resources/views/expense_cedulas_catalog/index.blade.php` (nuevo)

- `@extends('layouts.zircos')`, breadcrumb "Cédulas de Gasto".
- **Panel izquierdo** ("Categorías"): input de búsqueda (filtra en cliente sobre el
  array ya cargado), botón `+ Nueva categoría`, lista de items clicables (código +
  nombre + badge de estado + contador de cédulas + iconos editar/eliminar). Click
  selecciona la categoría (resaltada) y dispara `fetch` a `cedulas.data`.
- **Panel derecho** ("Cédulas de: [código] Nombre"): estado vacío inicial
  ("Selecciona una categoría para ver sus cédulas"), buscador en cliente, botón
  `+ Nueva cédula` (deshabilitado sin categoría seleccionada), lista de cédulas con
  badge de estado + iconos editar/eliminar.
- Modal `#categoryModal` (crear/editar: código, nombre, descripción, switch de
  estado) y modal `#cedulaModal` (crear/editar: nombre, switch de estado; categoría
  de contexto mostrada de solo lectura + input oculto `expense_category_id`).
- JS vanilla (mismo estilo que `employees/index.blade.php` /
  `sat_efos_69b/index.blade.php`): helpers `fetch` con
  `X-CSRF-TOKEN`/`Accept: application/json`; al recibir 422 limpia y vuelve a pintar
  `is-invalid`/`invalid-feedback` por campo; éxito → `Swal.fire` tipo toast y
  refresco del panel afectado (sin recargar la página); borrado → `Swal.fire` de
  confirmación antes de llamar `DELETE`, luego toast o error según respuesta.

### 5. `resources/views/layouts/partials/sidebar-staff.blade.php`

- Dentro de `#sidebarPresupuesto` (bloque `@moduleAccess('budget_control')`), nuevo
  `<li>` con link a `route('expense-cedulas.index')`, texto "Cédulas de Gasto",
  insertado entre "Centros de Costo" y "Presupuestos Anuales".

### 6. `routes/web.php`

- Agregar el bloque de rutas de la sección "Rutas" arriba, dentro del grupo
  `module.access:budget_control` ya existente (mismo grupo donde vive
  `cost-centers`, `annual_budgets`, `budget_monthly_distributions`,
  `budget_movements`, `categories`).

## Validaciones

- **Categoría** — crear/editar: `code` requerido, string, máx 3, se normaliza a
  mayúsculas, único en `expense_categories` (ignorando el propio id al editar);
  `name` requerido, máx 200; `description` opcional; `status` en
  `['ACTIVO','INACTIVO']`.
- **Cédula** — crear/editar: `expense_category_id` requerido, debe existir y no estar
  eliminado; `name` requerido, máx 200; `status` en `['ACTIVO','INACTIVO']`.
- Mensajes de validación en español (mismo criterio que el resto del proyecto, p.ej.
  `SaveRequisitionRequest`).

## Casos borde

- **Eliminar categoría con cédulas**: bloqueado siempre (409), aunque las cédulas
  mismas no tengan uso transaccional — evita cédulas huérfanas. El mensaje sugiere
  eliminar/reasignar las cédulas primero.
- **Eliminar cédula en uso**: bloqueado (409) con mensaje indicando que tiene
  movimientos asociados (distribución, requisición, compromiso u orden de compra).
- **Desactivar (status → INACTIVO) en vez de eliminar**: sigue la misma regla
  (`isInUse()`); una categoría/cédula con historial solo puede desactivarse una vez
  que ya no tiene movimientos "vivos" — mismo criterio que hoy aplica
  `ExpenseCategory::canBeDeactivated()` (basado en `assigned_amount > 0`), pero
  extendido a chequear también `requisition_items`/otras tablas de uso real, no solo
  `assigned_amount`.
- **Código de categoría duplicado al editar**: el `unique` de validación ignora el
  propio registro, para permitir guardar sin tocar el código.
- **Cambiar la categoría de una cédula existente**: fuera de alcance de este diseño —
  el modal de cédula no permite reasignar `expense_category_id` una vez creada (se
  crea/edita siempre en el contexto de la categoría seleccionada en el panel
  izquierdo). Si se necesita mover una cédula de categoría, se elimina y se vuelve a
  crear en la categoría destino.

## Archivos afectados

| Archivo | Cambio |
|---|---|
| `app/Models/ExpenseCategory.php` | `isInUse()`, `hasCedulas()`, relación `requisitionItems()`, `canBeDeactivated()` delega en `isInUse()` |
| `app/Models/BudgetCedula.php` | relaciones de uso, `isInUse()`, `boot()` con hooks de auditoría/guarda |
| `app/Http/Controllers/ExpenseCedulaCatalogController.php` | **nuevo** — CRUD JSON de categorías y cédulas |
| `resources/views/expense_cedulas_catalog/index.blade.php` | **nuevo** — vista maestro-detalle, modales, JS AJAX + SweetAlert2 |
| `resources/views/layouts/partials/sidebar-staff.blade.php` | nueva entrada "Cédulas de Gasto" en "Control Presupuestal" |
| `routes/web.php` | nuevo bloque de rutas dentro del grupo `budget_control` |

## UX y mensajes

- Todo texto visible en español, tono directo, sin jerga técnica.
- Estilo visual coherente con el resto de "Control Presupuestal" (cards, badges de
  estado `ACTIVO`/`INACTIVO` con los mismos colores que ya usa `CostCenter`/
  `Category`).
- SweetAlert2 para: confirmación de borrado ("¿Eliminar la categoría [X]? Esta acción
  no se puede deshacer"), éxito (toast auto-cierre ~2s), y errores de negocio/red
  (mensaje completo devuelto por el backend, sin genérico "algo salió mal" cuando el
  backend ya da un mensaje específico).

## Fuera de alcance

- Reasignar la categoría padre de una cédula existente (ver "Casos borde").
- Importación/exportación masiva de categorías/cédulas (hoy cubierto por seeders;
  no se toca en este diseño).
- Cambios a los endpoints de solo lectura ya existentes en `ExpenseCategoryController`
  (`getForSelect`, `byBudget`, `getByCostCenter`, `getCedulasByCostCenter`,
  `checkBudget`) — siguen intactos, este CRUD vive en un controlador nuevo.
- Auditoría/historial de cambios (quién editó qué y cuándo) más allá de los campos
  `created_by`/`updated_by`/`deleted_by` ya existentes en el esquema.
