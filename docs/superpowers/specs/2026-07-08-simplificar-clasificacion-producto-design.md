# Simplificar clasificación de productos/servicios (eliminar subcategoría y selección directa de cédulas)

## Contexto

Los formularios `products_services/create.blade.php` y `edit.blade.php` tienen dos problemas:

1. Muestran un campo "Categoría asignada" (`category_display`, solo lectura, sin `name`, no se guarda en BD) y un campo "Subcategoría" (`subcategory`, sí es columna real en `products_services`) que ya no deben capturarse.
2. Para clasificar el producto por presupuesto, el usuario debe elegir primero una o más "Categorías de Gasto" y luego, en un segundo select dependiente (poblado por JS desde un catálogo embebido), las "Cédulas de Gasto" de esas categorías. Se quiere simplificar a un solo select con todas las cédulas, mostrando el código de su categoría como prefijo (ej. `MAT - Papelería y oficina`).

## Decisiones de diseño (confirmadas con el usuario)

- Las "Categorías de Gasto" del producto (pivot `product_service_expense_category` vía relación `expenseCategories`) se **derivan automáticamente** de las cédulas elegidas — no se elimina el dato, solo se deja de capturar a mano. Cada cédula ya sabe a qué categoría pertenece (`budget_cedulas.expense_category_id`).
- El nuevo select de cédulas sigue siendo **multi-select** (un producto puede tener varias cédulas), igual que hoy.
- `subcategory` se elimina también de la base de datos (columna en `products_services`), no solo de la vista.
- `category_display` no es columna de BD; se elimina solo de la vista y su JS asociado.
- Cualquier valor existente en `subcategory` se pierde de forma permanente al aplicar la migración (el campo estaba sin uso real en el formulario, por lo que no se preserva).

## Cambios

### 1. Migración: eliminar `subcategory`

Nueva migración `remove_subcategory_from_products_services_table`:
- `up()`: elimina el índice compuesto `['category_id', 'subcategory']` y luego la columna `subcategory`.
- `down()`: recrea la columna `subcategory` (string, 100, nullable) y el índice, para reversibilidad.

### 2. Backend

**`app/Models/ProductService.php`**
- Quitar `'subcategory'` de `$fillable`.

**`app/Http/Requests/SaveProductServiceRequest.php`**
- Quitar la regla `subcategory` y su entrada en `attributes()`.
- Quitar la regla `expense_category_ids` / `expense_category_ids.*` (ya no se envía desde el formulario).
- Quitar el bloque de `withValidator()` que valida que cada cédula pertenezca a una categoría seleccionada (ya no aplica sin selección manual de categoría).
- Mantener `budget_cedula_ids` / `budget_cedula_ids.*` (array de ids existentes en `budget_cedulas`).

**`app/Http/Controllers/ProductServiceController.php`**
- `create()` y `edit()`: reemplazar la carga de `$expenseCategories` (con cédulas anidadas) por una lista plana `$budgetCedulas`:
  ```php
  $budgetCedulas = \App\Models\BudgetCedula::active()->notDeleted()
      ->whereHas('expenseCategory', fn ($q) => $q->active())
      ->with('expenseCategory:id,code,name')
      ->get()
      ->sortBy([['expenseCategory.code', 'asc'], ['name', 'asc']])
      ->values();
  ```
  Pasar `budgetCedulas` a la vista en lugar de `expenseCategories`. En `edit()`, quitar `selectedExpenseCategoryIds` (ya no se usa); mantener `selectedBudgetCedulaIds`.
- `store()` y `update()`:
  - Quitar `'subcategory' => $data['subcategory'] ?? null,` del `fill()`.
  - Después de `$productService->budgetCedulas()->sync($data['budget_cedula_ids'] ?? [])`, derivar las categorías y sincronizarlas:
    ```php
    $expenseCategoryIds = \App\Models\BudgetCedula::whereIn('id', $data['budget_cedula_ids'] ?? [])
        ->pluck('expense_category_id')
        ->unique()
        ->values();
    $productService->expenseCategories()->sync($expenseCategoryIds);
    ```
- `storeFromRequisition()`: quitar la regla de validación `subcategory` y su uso en `fill()`.

### 3. Vistas

**`create.blade.php` / `edit.blade.php`**
- Eliminar el bloque "Categoría derivada" (`category_display`) completo.
- Eliminar el bloque "Subcategoría" (`subcategory`) completo.
- Reemplazar los dos selects "Categorías de Gasto" + "Cédulas de Gasto" por un único select:
  ```blade
  <label for="budget_cedula_ids" class="form-label">Cédulas de Gasto</label>
  <select class="form-select @error('budget_cedula_ids') is-invalid @enderror"
          id="budget_cedula_ids" name="budget_cedula_ids[]" multiple style="width: 100%;">
      @foreach ($budgetCedulas as $cedula)
          <option value="{{ $cedula->id }}"
              {{ collect(old('budget_cedula_ids', $selectedBudgetCedulaIds ?? []))->contains($cedula->id) ? 'selected' : '' }}>
              {{ $cedula->expenseCategory->code }} - {{ $cedula->name }}
          </option>
      @endforeach
  </select>
  ```
- Quitar el `<script id="expense-cedulas-catalog">` (catálogo JSON embebido) y todo el JS de cascada (`cedulasByCategory`, `refreshCedulaOptions`, listener de `$categorySelect`). Dejar solo la inicialización de Select2 sobre `#budget_cedula_ids`.
- Quitar la función `updateCategoryDisplay`, sus llamadas, y el atributo `data-category-name` en las `<option>` de `cost_center_id` (queda sin uso).

**`show.blade.php`**
- Quitar la línea que muestra `$productService->subcategory` junto a la categoría.

**`requisitions/_request_product_modal.blade.php`**
- Quitar el campo "Subcategoría" (`modal_subcategory` / `name="subcategory"`) del modal.

### 4. Seeder y test

- `database/seeders/ProductServiceSycSeeder.php`: quitar `'subcategory' => null,`.
- `tests/Feature/ProductServiceSycSeederTest.php`: quitar la aserción/entrada `'subcategory' => null,`.

## Fuera de alcance

- No se toca la relación `category_id` del producto (la Categoría del catálogo derivada del centro de costo) — eso es un sistema distinto al de Categorías/Cédulas de Gasto presupuestales y no fue parte de la solicitud.
- No se modifica el módulo de Categorías/Cédulas de Gasto en sí (`ExpenseCategory`, `BudgetCedula`), solo cómo se seleccionan desde el formulario de producto.
