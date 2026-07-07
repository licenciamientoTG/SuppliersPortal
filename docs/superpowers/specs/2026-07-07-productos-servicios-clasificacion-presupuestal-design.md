# Diseño: Vincular catálogo de Productos/Servicios con Categoría/Cédula de Gasto + campo "Inventariable"

**Fecha:** 2026-07-07
**Estado:** Aprobado
**Disparador:** Con el CRUD de "Cédulas de Gasto" ya construido
(`docs/superpowers/specs/2026-07-07-cedulas-de-gasto-catalog-design.md`), el usuario
pidió vincular `products_services` con ese catálogo y agregar un campo booleano
"inventariable".

## Objetivo

Que cada registro del catálogo de Productos/Servicios (`products_services`) pueda
clasificarse opcionalmente con una categoría de gasto y su cédula, y que se pueda
marcar si el producto es controlado por inventario — sin tocar la lógica de
aprobación/estructura contable existente (`account_major`/`account_sub`/
`account_subsub`), que sigue funcionando igual.

## Contexto del sistema actual

- `products_services.category_id` (FK a `categories`) es la **categoría del centro de
  costo**, derivada automáticamente del `cost_center_id` elegido
  (`ProductServiceController::resolveCatalogCostCenter()` — el usuario nunca la
  elige). `subcategory` es texto libre sin relación. Ninguno de los dos tiene
  relación con `expense_categories`/`budget_cedulas` — son conceptos distintos, no
  hay colisión de nombres que resolver.
- `ProductService::expenseCategory(): BelongsTo` **ya existe en el modelo** pero es
  código muerto: usa la convención de FK por defecto (`expense_category_id`), columna
  que **no existe hoy** en `products_services`. Al agregar la columna, esta relación
  queda funcional automáticamente sin tocarla.
- Estructura contable manual (`account_major`/`account_sub`/`account_subsub`, texto
  libre) determina hoy `ProductService::hasCompleteAccountingStructure()` y
  `scopeForRequisitions()`, que gatean `approve()`/`reactivate()` en el controlador.
  **No se modifica** — coexiste con el nuevo vínculo, decisión explícita del usuario.
- `RequisitionItem` ya tiene sus propios `expense_category_id`/`budget_cedula_id`,
  capturados a mano en cada requisición vía un select en cascada
  (`resources/views/requisitions/create.blade.php`, usa `$.getJSON` contra
  `expense-categories.by-budget` y `expense-categories.cedulas-by-cost-center` —
  ambos **filtrados por presupuesto disponible de un centro de costo**, no aplicables
  aquí). El `creating` hook de `RequisitionItem` que autocompleta desde
  `product_service_id` (`description`, `item_category`, `product_code`, `unit`) **no
  se toca** — el auto-relleno de categoría/cédula en requisiciones queda fuera de
  alcance de este diseño (mejora futura independiente).
- Único booleano existente en `products_services` hoy: `is_active`. No existe ningún
  campo de inventario.
- Endpoints ya disponibles del catálogo nuevo (sin filtro de presupuesto, listan todo
  lo activo): `expense-cedulas.categories.data` (todas las categorías) y
  `expense-cedulas.cedulas.data` (`GET expense-cedulas/categories/{expenseCategory}/cedulas`,
  cédulas de una categoría). Estos son los que debe consumir el formulario de
  productos — no los de
  `requisitions`, que son específicos de presupuesto por centro de costo.

## Decisiones de diseño

1. **Se agregan `expense_category_id` y `budget_cedula_id` como columnas nuevas e
   independientes**, sin tocar `account_major`/`account_sub`/`account_subsub` ni la
   lógica de aprobación existente.
2. **Ambos campos son opcionales** (nullable en BD, no requeridos en el formulario) —
   los productos existentes quedan sin clasificar hasta que alguien los edite; no hay
   backfill de estos dos campos porque no hay forma automática de inferir la
   clasificación correcta de productos históricos.
3. **`is_inventoriable`**: boolean, sin restricción dura por `product_type` en
   backend/validación — el formulario pre-marca/desmarca el checkbox según
   `product_type` (`PRODUCTO` → marcado, `SERVICIO` → desmarcado) al cambiarlo, pero
   el usuario puede sobreescribirlo en cualquier caso (ej. un servicio con insumo
   consignado). Backfill de productos existentes: `true` si `product_type=PRODUCTO`,
   `false` si `SERVICIO` (mismo criterio que el default del formulario, para
   consistencia entre datos históricos y nuevos).
4. **UI**: select en cascada Categoría de Gasto → Cédula, replicando el patrón AJAX ya
   usado en `requisitions/create.blade.php` (Select2, cédula deshabilitada hasta
   elegir categoría), pero apuntando a los endpoints nuevos y simples
   (`expense-cedulas.categories.data` / `.../{expenseCategory}/cedulas`) en vez de los
   de `requisitions` (que filtran por presupuesto disponible, no aplicable a una
   clasificación permanente de catálogo).
5. **Consistencia categoría↔cédula**: validación de servidor que, si viene
   `budget_cedula_id`, confirme que su `expense_category_id` coincide con el enviado
   — defensa adicional aunque la UI en cascada ya lo garantiza normalmente.
6. **Fuera de alcance** (confirmado con el usuario): auto-relleno de
   `expense_category_id`/`budget_cedula_id` en `RequisitionItem` al elegir un producto
   clasificado; cualquier cambio a `account_major`/`account_sub`/`account_subsub` o a
   `hasCompleteAccountingStructure()`.

## Modelo de datos

### Migración — `..._add_expense_classification_to_products_services_table.php`

```php
Schema::table('products_services', function (Blueprint $table) {
    $table->foreignId('expense_category_id')->nullable()
        ->after('subcategory')
        ->constrained('expense_categories')
        ->onDelete('no action')->onUpdate('no action');

    $table->foreignId('budget_cedula_id')->nullable()
        ->after('expense_category_id')
        ->constrained('budget_cedulas')
        ->onDelete('no action')->onUpdate('no action');

    $table->boolean('is_inventoriable')->default(true)
        ->after('product_type')
        ->comment('TRUE si el producto se controla por inventario');

    $table->index('expense_category_id');
    $table->index('budget_cedula_id');
    $table->index('is_inventoriable');
});
```

Seguido de un backfill en la misma migración (`DB::table('products_services')
->where('product_type', 'SERVICIO')->update(['is_inventoriable' => false])` — el
default `true` ya cubre `PRODUCTO`).

## Cambios por componente

### 1. `app/Models/ProductService.php`

- `$fillable`: agregar `expense_category_id`, `budget_cedula_id`, `is_inventoriable`.
- `$casts`: agregar `'is_inventoriable' => 'boolean'`.
- Nueva relación `budgetCedula(): BelongsTo` (FK `budget_cedula_id`).
- `expenseCategory(): BelongsTo` ya existe, queda funcional sin cambios.

### 2. `app/Http/Requests/SaveProductServiceRequest.php`

- `expense_category_id`: `['nullable', 'exists:expense_categories,id']`.
- `budget_cedula_id`: `['nullable', 'exists:budget_cedulas,id']`.
- `is_inventoriable`: `['boolean']`.
- En `withValidator()`: si `budget_cedula_id` está presente, verificar que
  `BudgetCedula::find($budget_cedula_id)->expense_category_id === $expense_category_id`
  enviado; si no coincide, error de validación en `budget_cedula_id`.

### 3. `app/Http/Controllers/ProductServiceController.php`

- `store()`/`update()`: agregar `expense_category_id`, `budget_cedula_id` al array
  persistido (junto a donde hoy se arma `category_id`/`subcategory`), y
  `'is_inventoriable' => $request->boolean('is_inventoriable')` (checkbox ausente en
  el POST se trata como `false`, patrón estándar de Laravel).

### 4. Vistas `resources/views/products_services/{create,edit}.blade.php` (o su
   partial compartido de formulario, según cómo estén estructuradas)

- Nueva sección "Clasificación Presupuestal" junto a "Estructura Contable".
- Select Categoría de Gasto (`expense_category_id`) + select Cédula
  (`budget_cedula_id`, deshabilitado hasta elegir categoría, poblado vía
  `$.getJSON` al endpoint `expense-cedulas.cedulas.data`), mismo patrón
  Select2/cascada que `requisitions/create.blade.php`.
- Checkbox "Inventariable" (`is_inventoriable`), con JS que lo pre-marca/desmarca al
  cambiar `product_type`, editable manualmente en cualquier momento.
- En `edit.blade.php`: precargar selects con los valores actuales del producto
  (incluye poblar el select de cédula con las opciones de su categoría actual antes
  de que el usuario interactúe).

### 5. `resources/views/products_services/show.blade.php`

- Mostrar categoría de gasto, cédula e "Inventariable" (Sí/No) de solo lectura, junto
  a la estructura contable existente.

## Validaciones

- `expense_category_id`: opcional, debe existir en `expense_categories`.
- `budget_cedula_id`: opcional, debe existir en `budget_cedulas`, y su categoría padre
  debe coincidir con `expense_category_id` si ambos vienen en el request.
- `is_inventoriable`: booleano, sin restricción cruzada con `product_type`.

## Casos borde

- **Producto sin clasificar (histórico)**: `expense_category_id`/`budget_cedula_id`
  quedan `null`; el catálogo sigue funcionando igual que hoy (no se exige para
  aprobación/uso en requisiciones, ya que eso sigue gobernado por
  `hasCompleteAccountingStructure()`).
- **Cambiar categoría en edición**: si el producto ya tenía cédula de una categoría
  distinta a la nueva, el formulario debe limpiar la selección de cédula al cambiar
  de categoría (mismo comportamiento que la cascada de `requisitions/create.blade.php`).
- **`budget_cedula_id` sin `expense_category_id`**: bloqueado por la regla de
  consistencia del punto 5 (no se puede enviar cédula sin su categoría).
- **Servicio marcado como inventariable**: permitido explícitamente (ver decisión 3),
  no se valida ni se bloquea.

## Archivos afectados

| Archivo | Cambio |
|---|---|
| `database/migrations/..._add_expense_classification_to_products_services_table.php` | **nuevo** — 3 columnas + backfill de `is_inventoriable` |
| `app/Models/ProductService.php` | fillable, cast, relación `budgetCedula()` |
| `app/Http/Requests/SaveProductServiceRequest.php` | reglas nuevas + validación cruzada categoría/cédula |
| `app/Http/Controllers/ProductServiceController.php` | persistir los 3 campos en `store()`/`update()` |
| `resources/views/products_services/create.blade.php` / `edit.blade.php` | sección "Clasificación Presupuestal", select cascada, checkbox |
| `resources/views/products_services/show.blade.php` | mostrar los 3 valores de solo lectura |

## Fuera de alcance

- Auto-relleno de `expense_category_id`/`budget_cedula_id` en `RequisitionItem` al
  elegir un producto clasificado.
- Cambios a `account_major`/`account_sub`/`account_subsub`,
  `hasCompleteAccountingStructure()` o al flujo de aprobación.
- Backfill de `expense_category_id`/`budget_cedula_id` en productos históricos (no
  hay forma automática de inferirlo).
- Filtros por categoría/cédula/inventariable en el listado (`index`/`datatable`) del
  catálogo — no se pidió, se puede agregar después si se necesita.
