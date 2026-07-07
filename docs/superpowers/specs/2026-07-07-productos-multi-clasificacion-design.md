# Diseño: Vínculo muchos-a-muchos entre Productos/Servicios y Categorías/Cédulas de Gasto

**Fecha:** 2026-07-07
**Estado:** Aprobado
**Disparador:** Poco después de implementar el vínculo 1:1 (`docs/superpowers/specs/
2026-07-07-productos-servicios-clasificacion-presupuestal-design.md`), el negocio
indicó que un producto/servicio debe poder clasificarse en **varias** categorías de
gasto y **varias** cédulas, no solo una de cada una.

## Objetivo

Reemplazar el vínculo 1:1 (`products_services.expense_category_id` /
`.budget_cedula_id`, agregado hoy mismo) por un vínculo muchos-a-muchos, permitiendo
que un producto/servicio se asocie a N categorías de gasto y M cédulas, manteniendo la
misma garantía de consistencia que ya existía (cada cédula elegida debe pertenecer a
alguna de las categorías elegidas), y actualizando los templates de creación/edición
del catálogo para capturar la selección múltiple.

## Contexto del sistema actual

- La migración de hoy (`database/migrations/2026_07_07_150000_
  add_expense_classification_to_products_services_table.php`) agregó
  `expense_category_id`/`budget_cedula_id` (FK nullable) + `is_inventoriable`
  (boolean) a `products_services`. **Nunca se corrió contra la base de datos real**
  (verificado: la consulta `ProductService::whereNotNull('expense_category_id')`
  falla con "Invalid column name" en la BD SQL Server real) — no hay datos que migrar.
- Ya fue **pusheada a `origin/main`** (commit `99fbcfa` y anteriores) — por eso este
  diseño agrega migraciones nuevas para deshacerla/reemplazarla, en vez de editarla in
  situ (una migración ya compartida no debe editarse: cualquier compañero que ya la
  haya corrido localmente quedaría con un esquema inconsistente si el archivo cambia
  de contenido sin cambiar de nombre).
- Confirmado por grep: **ningún código fuera de `create.blade.php`, `edit.blade.php`
  y `show.blade.php`** (los tres archivos que este mismo trabajo ya tocó) lee
  `expense_category_id`, `budget_cedula_id`, `expenseCategory()` o `budgetCedula()`
  de `ProductService` — reemplazo sin efectos colaterales en otros módulos
  (`RequisitionItem` mantiene sus propios campos independientes, sin relación con el
  catálogo de productos; ver diseño anterior, "Fuera de alcance").
- `is_inventoriable` **no se toca** — sigue siendo un booleano simple en
  `products_services`, sin relación con este cambio.
- Patrón de UI ya existente en este proyecto para selección múltiple de categorías de
  gasto: `resources/views/budget_monthly_distributions/partials/form.blade.php`
  (`#categorySelector`, `<select multiple="multiple">` + Select2:
  `theme: 'bootstrap-5', placeholder, allowClear: true, closeOnSelect: false`).
  Select2 ya está cargado globalmente vía `resources/views/layouts/zircos.blade.php`
  — no se requieren assets nuevos.
- El cascade actual (categoría → cédula) en `create.blade.php`/`edit.blade.php` usa un
  catálogo JSON embebido (`<script type="application/json">`) con
  `JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP` y construye `<option>` con
  `.text()` (no template-literals), tras la corrección de un hallazgo de XSS en la
  primera ronda de este mismo feature. **Este patrón seguro se mantiene** para el
  nuevo catálogo embebido multi-select.

## Decisiones de diseño

1. **Reemplazo completo, no coexistencia**: se eliminan `expense_category_id` y
   `budget_cedula_id` de `products_services` (nueva migración de "down") y se
   reemplazan por dos tablas pivote — no queda ninguna columna "principal" +
   "adicionales", evita ambigüedad. `is_inventoriable` no se toca.
2. **Tablas pivote con nombres por convención de Laravel** (evita especificar nombre
   de tabla explícito en `belongsToMany()`): `expense_category_product_service`
   (`expense_category_id`, `product_service_id`) y `budget_cedula_product_service`
   (`budget_cedula_id`, `product_service_id`). Sin columnas extra (`id`, timestamps) —
   pivote simple, mismo criterio de simplicidad que el resto del esquema no necesita
   auditoría a nivel de fila de pivote.
3. **Regla de consistencia extendida a conjuntos** (ya aprobada): cada
   `budget_cedula_id` enviado debe tener su `expense_category_id` presente en el
   arreglo `expense_category_ids` enviado. Si no, error de validación en
   `budget_cedula_ids`.
4. **Ambos campos siguen siendo opcionales** — un producto puede no tener ninguna
   categoría/cédula asociada (mismo criterio que el diseño 1:1 original).
5. **UI**: dos `<select multiple>` con Select2, mismo patrón visual que
   `budget_monthly_distributions`. El select de cédulas se agrupa por `<optgroup>`
   (una por categoría seleccionada) y se reconstruye vía JS cada vez que cambia la
   selección de categorías — deseleccionando automáticamente cualquier cédula cuya
   categoría fue quitada. Se reutiliza el mismo mecanismo seguro de escape (JSON_HEX_*
   + `.text()`) ya validado por el revisor de seguridad en la ronda anterior.
6. **Migraciones nuevas, no edición de la existente** — ver "Modelo de datos".
7. **Se agregan relaciones inversas mínimas** `ExpenseCategory::productServices()` /
   `BudgetCedula::productServices()` (`BelongsToMany`) — no por simetría genérica,
   sino porque las necesita la decisión 8.
8. **El guard de borrado/desactivación (`isInUse()`) se amplía**: tanto
   `ExpenseCategory::isInUse()` como `BudgetCedula::isInUse()` (este último agregado
   en el feature de Cédulas de Gasto) deben considerar también los productos/
   servicios vinculados como "uso" — mismo criterio que ya aplican a distribuciones/
   requisiciones/compromisos: si un producto del catálogo está clasificado con esa
   categoría/cédula, no se puede eliminar ni desactivar sin antes desvincularla.

## Modelo de datos

### Migración 1 — `..._remove_expense_classification_columns_from_products_services_table.php`

```php
Schema::table('products_services', function (Blueprint $table) {
    $table->dropForeign(['expense_category_id']);
    $table->dropForeign(['budget_cedula_id']);
    $table->dropColumn(['expense_category_id', 'budget_cedula_id']);
});
```
`down()`: vuelve a agregar las columnas (mismo `foreignId()->nullable()->constrained()`
que la migración original), para que sea reversible.

### Migración 2 — `..._create_expense_category_product_service_table.php`

```php
Schema::create('expense_category_product_service', function (Blueprint $table) {
    $table->foreignId('expense_category_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_service_id')->constrained()->cascadeOnDelete();
    $table->primary(['expense_category_id', 'product_service_id']);
});
```

### Migración 3 — `..._create_budget_cedula_product_service_table.php`

```php
Schema::create('budget_cedula_product_service', function (Blueprint $table) {
    $table->foreignId('budget_cedula_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_service_id')->constrained()->cascadeOnDelete();
    $table->primary(['budget_cedula_id', 'product_service_id']);
});
```
`cascadeOnDelete()` en las pivote (a diferencia de `no action` usado en el resto del
esquema): si se elimina un producto, sus filas de pivote deben desaparecer con él —
no tiene sentido "proteger" una fila de relación N:M huérfana a nivel de base de
datos. La protección real contra borrar una categoría/cédula que sigue en uso ocurre
a nivel de aplicación: el guard `isInUse()` (ver decisión 8) bloquea ese borrado
*antes* de que la base de datos llegue a intentar el `cascadeOnDelete()` por ese lado.

## Cambios por componente

### 0. `app/Models/ExpenseCategory.php` y `app/Models/BudgetCedula.php`

- Agregar `productServices(): BelongsToMany` a ambos modelos.
- Ampliar `isInUse()` en ambos para incluir `|| $this->productServices()->exists()`
  en el `OR` existente (junto a `monthlyDistributions()`, `requisitionItems()`, etc.
  según cada modelo — ver `docs/superpowers/specs/2026-07-07-cedulas-de-gasto-catalog-design.md`
  para el estado actual exacto de `isInUse()` en cada uno).

### 1. `app/Models/ProductService.php`

- Quitar `expense_category_id`, `budget_cedula_id` de `$fillable`.
- Quitar los métodos `expenseCategory()`/`budgetCedula()` (`BelongsTo`).
- Agregar `use Illuminate\Database\Eloquent\Relations\BelongsToMany;` y:
```php
public function expenseCategories(): BelongsToMany
{
    return $this->belongsToMany(ExpenseCategory::class);
}

public function budgetCedulas(): BelongsToMany
{
    return $this->belongsToMany(BudgetCedula::class);
}
```

### 2. `app/Http/Requests/SaveProductServiceRequest.php`

- `expense_category_id` → `expense_category_ids` (`nullable|array`) +
  `expense_category_ids.*` (`integer|exists:expense_categories,id`).
- `budget_cedula_id` → `budget_cedula_ids` (mismo patrón, `exists:budget_cedulas,id`).
- `is_inventoriable` sin cambios.
- `withValidator()`: la regla cruzada existente (línea ~137-141 hoy) se reemplaza por
  un bucle sobre `budget_cedula_ids`, verificando que cada `BudgetCedula::
  expense_category_id` esté en `expense_category_ids`; si alguna no coincide, un solo
  error agregado en `budget_cedula_ids` (mensaje agregado, no por índice — Laravel no
  soporta errores por elemento de array de forma simple sin más complejidad de la que
  amerita este caso).

### 3. `app/Http/Controllers/ProductServiceController.php`

- `store()`/`update()`: después de `$productService->save()`, agregar
  `$productService->expenseCategories()->sync($data['expense_category_ids'] ?? []);`
  y `$productService->budgetCedulas()->sync($data['budget_cedula_ids'] ?? []);`.
  Se quitan las líneas que hoy meten `expense_category_id`/`budget_cedula_id` en el
  array de `fill()`.
- `create()`: sin cambios en la carga de `$expenseCategories` (categorías activas +
  cédulas activas relacionadas, ya usado para el catálogo embebido).
- `edit()`: además de `$expenseCategories`, pasar los IDs actualmente vinculados del
  producto (`$productService->expenseCategories->pluck('id')`,
  `$productService->budgetCedulas->pluck('id')`) para preseleccionar el multi-select.
- `show()`: `$productService->load([..., 'expenseCategories', 'budgetCedulas'])` en
  vez de las relaciones singulares.

### 4. `resources/views/products_services/create.blade.php` y `edit.blade.php`

- Los dos `<select>` simples de categoría/cédula se reemplazan por dos
  `<select multiple name="expense_category_ids[]">` / `<select multiple
  name="budget_cedula_ids[]">`, inicializados con Select2 (mismas opciones que
  `budget_monthly_distributions`: `theme: 'bootstrap-5', placeholder, allowClear:
  true, closeOnSelect: false`).
- El select de cédulas se agrupa por `<optgroup label="[código] nombre">` por
  categoría. JS reconstruye las `<optgroup>`/`<option>` visibles cada vez que cambia
  la selección de categorías (mismo catálogo JSON embebido de hoy, mismo escape
  seguro), y llama `.trigger('change')` sobre el select de Select2 para refrescar su
  UI. Si una cédula ya seleccionada pertenece a una categoría que se deselecciona, se
  quita automáticamente de la selección.
- `edit.blade.php` precarga ambos multi-select con los IDs actuales del producto.
- El checkbox "Inventariable" no cambia.

### 5. `resources/views/products_services/show.blade.php`

- Categoría(s) de Gasto y Cédula(s) de Gasto se muestran como lista (badges o texto
  separado por comas), con "Sin clasificar" cuando la colección esté vacía, en vez de
  un solo valor.

## Validaciones

- `expense_category_ids`: opcional, array, cada elemento debe existir en
  `expense_categories`.
- `budget_cedula_ids`: opcional, array, cada elemento debe existir en
  `budget_cedulas`, y su `expense_category_id` debe estar presente en
  `expense_category_ids` (si no, error).
- Sin cambios en `is_inventoriable`.

## Casos borde

- **Producto sin ninguna categoría/cédula**: ambos arreglos vacíos o ausentes —
  `sync([])` limpia cualquier vínculo previo sin error.
- **Quitar una categoría que tenía cédulas seleccionadas**: el JS del formulario
  deselecciona automáticamente esas cédulas antes de enviar; el backend igual valida
  la consistencia por si el request llega manipulado.
- **Eliminar o desactivar una categoría/cédula desde el catálogo de Cédulas de Gasto
  mientras tiene productos vinculados**: bloqueado por `isInUse()` (decisión 8), igual
  que ya bloquea por distribuciones/requisiciones/compromisos — mensaje de error
  explicando que tiene productos del catálogo asociados.

## Archivos afectados

| Archivo | Cambio |
|---|---|
| `database/migrations/..._remove_expense_classification_columns_from_products_services_table.php` | **nuevo** — quita las 2 columnas FK de hoy |
| `database/migrations/..._create_expense_category_product_service_table.php` | **nuevo** — tabla pivote |
| `database/migrations/..._create_budget_cedula_product_service_table.php` | **nuevo** — tabla pivote |
| `app/Models/ProductService.php` | quita BelongsTo singulares, agrega `expenseCategories()`/`budgetCedulas()` BelongsToMany |
| `app/Models/ExpenseCategory.php` / `BudgetCedula.php` | agrega `productServices()`, amplía `isInUse()` |
| `app/Http/Requests/SaveProductServiceRequest.php` | reglas de array + validación cruzada por conjunto |
| `app/Http/Controllers/ProductServiceController.php` | `sync()` en vez de `fill()`, `edit()`/`show()` cargan las relaciones plural |
| `resources/views/products_services/create.blade.php` | multi-select Select2 con optgroups dinámicos |
| `resources/views/products_services/edit.blade.php` | ídem, precargado |
| `resources/views/products_services/show.blade.php` | lista de categorías/cédulas en vez de un solo valor |

## Fuera de alcance

- Auto-relleno de `RequisitionItem.expense_category_id`/`budget_cedula_id` desde el
  producto — ya estaba fuera de alcance del diseño original, sigue estándolo.
- Migración/backfill de datos — no aplica, la columna original nunca tuvo datos
  reales.
- Límite máximo de categorías/cédulas seleccionables por producto — sin restricción,
  no se pidió.
