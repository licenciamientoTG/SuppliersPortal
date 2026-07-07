# Multi-clasificación de Productos/Servicios (categorías/cédulas N:M) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reemplazar el vínculo 1:1 `products_services.expense_category_id`/
`.budget_cedula_id` (agregado en un plan anterior el mismo día, nunca corrido contra
datos reales) por un vínculo muchos-a-muchos, permitiendo que un producto/servicio se
clasifique en varias categorías de gasto y varias cédulas, con la misma garantía de
consistencia (cada cédula elegida debe pertenecer a alguna de las categorías
elegidas) extendida de un par a conjuntos.

**Architecture:** Dos tablas pivote nuevas (`expense_category_product_service`,
`budget_cedula_product_service`) reemplazan las dos columnas FK de hoy. `ProductService`
gana `expenseCategories()`/`budgetCedulas()` (`BelongsToMany`). `ExpenseCategory`/
`BudgetCedula` ganan `productServices()` y su guard `isInUse()` se amplía para
bloquear borrado/desactivación si tienen productos vinculados. La validación pasa de
un `exists` simple a arreglos (`expense_category_ids[]`/`budget_cedula_ids[]`) con una
regla cruzada por conjunto. Los templates `create`/`edit` reemplazan los dos
`<select>` simples por dos `<select multiple>` con Select2 (mismo patrón ya usado en
`budget_monthly_distributions/partials/form.blade.php`), agrupando cédulas por
`<optgroup>` según las categorías elegidas, con el mismo mecanismo seguro de escape
(`JSON_HEX_*` + construcción de `<option>` vía `.text()`) ya validado por seguridad en
el plan anterior.

**Tech Stack:** Laravel 10, Eloquent `BelongsToMany`, Blade + jQuery + Select2 (ya
cargado globalmente en `layouts/zircos.blade.php`), PHPUnit (`Tests\Feature\*Test` +
`RefreshDatabase`, SQLite in-memory en tests).

## Global Constraints

- `expense_category_ids`/`budget_cedula_ids` siguen siendo **siempre opcionales** —
  un producto puede no tener ninguna categoría/cédula.
- `is_inventoriable` **no se toca** en absoluto por este plan.
- No se edita la migración de hoy (`2026_07_07_150000_...`, ya pusheada a
  `origin/main`) — se agregan migraciones nuevas que la deshacen/reemplazan.
- Cada `budget_cedula_id` enviado debe pertenecer a alguna de las `expense_category_ids`
  enviadas, o falla la validación con un error en `budget_cedula_ids`.
- El guard `isInUse()` de `ExpenseCategory` y `BudgetCedula` debe bloquear borrado/
  desactivación si la categoría/cédula tiene productos vinculados — mismo criterio que
  ya aplican a distribuciones/requisiciones/compromisos presupuestales.
- No se agrega auto-relleno de `RequisitionItem` desde el producto — sigue fuera de
  alcance.
- No se agrega backfill/migración de datos — la columna original nunca tuvo datos
  reales (verificado contra la BD real).
- Mensajes de validación y UI en español, consistentes con el resto del catálogo.
- Spec completo: `docs/superpowers/specs/2026-07-07-productos-multi-clasificacion-design.md`.

---

### Task 1: Migraciones (drop columnas + tablas pivote) + relaciones `BelongsToMany` en `ProductService`

**Files:**
- Create: `database/migrations/2026_07_07_160000_remove_expense_classification_columns_from_products_services_table.php`
- Create: `database/migrations/2026_07_07_160100_create_expense_category_product_service_table.php`
- Create: `database/migrations/2026_07_07_160200_create_budget_cedula_product_service_table.php`
- Modify: `app/Models/ProductService.php`
- Test: `tests/Feature/ProductServiceExpenseClassificationTest.php` (reemplaza por
  completo el contenido actual — ese archivo probaba el vínculo 1:1 de hoy, que este
  plan reemplaza)

**Interfaces:**
- Produces: `ProductService::expenseCategories(): BelongsToMany`,
  `ProductService::budgetCedulas(): BelongsToMany`. Tablas `expense_category_product_service`
  (`expense_category_id`, `product_service_id`) y `budget_cedula_product_service`
  (`budget_cedula_id`, `product_service_id`), ambas con `cascadeOnDelete()` en sus FKs
  y PK compuesta (sin columna `id` propia). Columnas `products_services.expense_category_id`
  y `.budget_cedula_id` dejan de existir.

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

namespace Tests\Feature;

use App\Models\BudgetCedula;
use App\Models\ExpenseCategory;
use App\Models\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductServiceExpenseClassificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_service_can_be_linked_to_multiple_expense_categories_and_cedulas(): void
    {
        $categoryA = ExpenseCategory::factory()->create();
        $categoryB = ExpenseCategory::factory()->create();
        $cedulaA = BudgetCedula::factory()->create(['expense_category_id' => $categoryA->id]);
        $cedulaB = BudgetCedula::factory()->create(['expense_category_id' => $categoryB->id]);

        $product = ProductService::factory()->create();
        $product->expenseCategories()->sync([$categoryA->id, $categoryB->id]);
        $product->budgetCedulas()->sync([$cedulaA->id, $cedulaB->id]);
        $product->refresh();

        $this->assertCount(2, $product->expenseCategories);
        $this->assertCount(2, $product->budgetCedulas);
        $this->assertTrue($product->expenseCategories->pluck('id')->contains($categoryA->id));
        $this->assertTrue($product->budgetCedulas->pluck('id')->contains($cedulaB->id));
    }

    public function test_product_service_can_have_no_classification(): void
    {
        $product = ProductService::factory()->create();

        $this->assertCount(0, $product->expenseCategories);
        $this->assertCount(0, $product->budgetCedulas);
    }

    public function test_single_column_classification_no_longer_exists(): void
    {
        $this->assertFalse(Schema::hasColumn('products_services', 'expense_category_id'));
        $this->assertFalse(Schema::hasColumn('products_services', 'budget_cedula_id'));
        $this->assertTrue(Schema::hasTable('expense_category_product_service'));
        $this->assertTrue(Schema::hasTable('budget_cedula_product_service'));
    }
}
```

- [ ] **Step 2: Ejecutar el test y confirmar que falla**

Run: `php artisan test --filter=ProductServiceExpenseClassificationTest`
Expected: FAIL — las tablas pivote no existen todavía, las columnas antiguas siguen
presentes, y `expenseCategories()`/`budgetCedulas()` no existen en el modelo.

- [ ] **Step 3: Migración que elimina las columnas de hoy**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products_services', function (Blueprint $table) {
            $table->dropForeign(['expense_category_id']);
            $table->dropForeign(['budget_cedula_id']);
            $table->dropColumn(['expense_category_id', 'budget_cedula_id']);
        });
    }

    public function down(): void
    {
        Schema::table('products_services', function (Blueprint $table) {
            $table->foreignId('expense_category_id')->nullable()
                ->after('subcategory')
                ->constrained('expense_categories')
                ->onDelete('no action')->onUpdate('no action');

            $table->foreignId('budget_cedula_id')->nullable()
                ->after('expense_category_id')
                ->constrained('budget_cedulas')
                ->onDelete('no action')->onUpdate('no action');
        });
    }
};
```

- [ ] **Step 4: Migración de la tabla pivote de categorías**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('expense_category_product_service', function (Blueprint $table) {
            $table->foreignId('expense_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_service_id')->constrained()->cascadeOnDelete();
            $table->primary(['expense_category_id', 'product_service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_category_product_service');
    }
};
```

- [ ] **Step 5: Migración de la tabla pivote de cédulas**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('budget_cedula_product_service', function (Blueprint $table) {
            $table->foreignId('budget_cedula_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_service_id')->constrained()->cascadeOnDelete();
            $table->primary(['budget_cedula_id', 'product_service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_cedula_product_service');
    }
};
```

- [ ] **Step 6: Actualizar el modelo `ProductService`**

En `app/Models/ProductService.php`:

Quitar de `$fillable` (sección `// Clasificación`) las líneas `'expense_category_id',`
y `'budget_cedula_id',` (dejar `'category_id'`, `'subcategory'`, `'is_inventoriable'`).

Agregar el import:
```php
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
```

Reemplazar los métodos `expenseCategory()`/`budgetCedula()` existentes (sección
RELACIONES) por:
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

- [ ] **Step 7: Ejecutar el test y confirmar que pasa**

Run: `php artisan test --filter=ProductServiceExpenseClassificationTest`
Expected: PASS (3 tests)

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_07_07_160000_remove_expense_classification_columns_from_products_services_table.php \
        database/migrations/2026_07_07_160100_create_expense_category_product_service_table.php \
        database/migrations/2026_07_07_160200_create_budget_cedula_product_service_table.php \
        app/Models/ProductService.php \
        tests/Feature/ProductServiceExpenseClassificationTest.php
git commit -m "feat: reemplazar vinculo 1:1 de products_services con categoria/cedula por muchos-a-muchos"
```

---

### Task 2: `productServices()` + guard `isInUse()` ampliado en `ExpenseCategory`/`BudgetCedula`

**Files:**
- Modify: `app/Models/ExpenseCategory.php`
- Modify: `app/Models/BudgetCedula.php`
- Test: `tests/Feature/ExpenseCategoryCedulaProductServiceGuardTest.php` (nuevo)

**Interfaces:**
- Consumes: `ProductService::expenseCategories()`/`budgetCedulas()` (Task 1).
- Produces: `ExpenseCategory::productServices()`, `BudgetCedula::productServices()`
  (ambos `BelongsToMany`). `ExpenseCategory::isInUse()` y `BudgetCedula::isInUse()`
  devuelven `true` si tienen al menos un producto vinculado.

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

namespace Tests\Feature;

use App\Models\BudgetCedula;
use App\Models\ExpenseCategory;
use App\Models\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseCategoryCedulaProductServiceGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_is_in_use_when_linked_to_a_product(): void
    {
        $category = ExpenseCategory::factory()->create();
        $product = ProductService::factory()->create();
        $product->expenseCategories()->attach($category->id);

        $this->assertTrue($category->isInUse());
    }

    public function test_category_deactivation_is_blocked_when_linked_to_a_product(): void
    {
        $category = ExpenseCategory::factory()->create(['status' => 'ACTIVO']);
        $product = ProductService::factory()->create();
        $product->expenseCategories()->attach($category->id);

        $this->expectException(\Exception::class);
        $category->update(['status' => 'INACTIVO']);
    }

    public function test_category_without_products_is_not_in_use(): void
    {
        $category = ExpenseCategory::factory()->create();

        $this->assertFalse($category->isInUse());
    }

    public function test_cedula_is_in_use_when_linked_to_a_product(): void
    {
        $category = ExpenseCategory::factory()->create();
        $cedula = BudgetCedula::factory()->create(['expense_category_id' => $category->id]);
        $product = ProductService::factory()->create();
        $product->budgetCedulas()->attach($cedula->id);

        $this->assertTrue($cedula->isInUse());
    }

    public function test_cedula_deactivation_is_blocked_when_linked_to_a_product(): void
    {
        $category = ExpenseCategory::factory()->create();
        $cedula = BudgetCedula::factory()->create([
            'expense_category_id' => $category->id,
            'status' => 'ACTIVO',
        ]);
        $product = ProductService::factory()->create();
        $product->budgetCedulas()->attach($cedula->id);

        $this->expectException(\Exception::class);
        $cedula->update(['status' => 'INACTIVO']);
    }
}
```

- [ ] **Step 2: Ejecutar el test y confirmar que falla**

Run: `php artisan test --filter=ExpenseCategoryCedulaProductServiceGuardTest`
Expected: FAIL — `productServices()` no existe en ninguno de los dos modelos, y
`isInUse()` no considera productos vinculados.

- [ ] **Step 3: Actualizar `app/Models/ExpenseCategory.php`**

Agregar la relación (sección RELACIONES, junto a `budgetCommitments()`):
```php
/**
 * Productos/servicios del catálogo clasificados con esta categoría
 */
public function productServices()
{
    return $this->belongsToMany(ProductService::class);
}
```

Modificar `isInUse()` (sección MÉTODOS: VALIDACIONES) agregando la nueva condición:
```php
public function isInUse(): bool
{
    return $this->monthlyDistributions()->where('assigned_amount', '>', 0)->exists()
        || $this->requisitionItems()->exists()
        || $this->budgetMovementDetails()->exists()
        || $this->directPurchaseOrderItems()->exists()
        || $this->budgetCommitments()->exists()
        || $this->productServices()->exists();
}
```

- [ ] **Step 4: Actualizar `app/Models/BudgetCedula.php`**

Agregar la relación (sección RELACIONES, junto a `budgetCommitments()`):
```php
public function productServices()
{
    return $this->belongsToMany(ProductService::class);
}
```

Modificar `isInUse()`:
```php
public function isInUse(): bool
{
    return $this->monthlyDistributions()->exists()
        || $this->requisitionItems()->exists()
        || $this->budgetCommitments()->exists()
        || $this->productServices()->exists();
}
```

- [ ] **Step 5: Ejecutar el test y confirmar que pasa**

Run: `php artisan test --filter=ExpenseCategoryCedulaProductServiceGuardTest`
Expected: PASS (5 tests)

- [ ] **Step 6: Ejecutar también los tests existentes del guard para confirmar que no hay regresión**

Run: `php artisan test --filter=ExpenseCategoryByCostCenterTest`
Expected: PASS (sin cambios de comportamiento fuera de lo agregado)

- [ ] **Step 7: Commit**

```bash
git add app/Models/ExpenseCategory.php app/Models/BudgetCedula.php tests/Feature/ExpenseCategoryCedulaProductServiceGuardTest.php
git commit -m "feat: bloquear borrado/desactivacion de categoria o cedula con productos vinculados"
```

---

### Task 3: Validación en `SaveProductServiceRequest` (arreglos + regla cruzada por conjunto)

**Files:**
- Modify: `app/Http/Requests/SaveProductServiceRequest.php`
- Test: `tests/Feature/ProductServiceExpenseClassificationValidationTest.php` (reemplaza
  por completo el contenido actual)

**Interfaces:**
- Consumes: `ExpenseCategory`, `BudgetCedula` (existentes), rutas
  `products-services.store`/`.update` (existentes).
- Produces: reglas `expense_category_ids` (`nullable|array`),
  `expense_category_ids.*` (`integer|exists:expense_categories,id`),
  `budget_cedula_ids` (`nullable|array`), `budget_cedula_ids.*`
  (`integer|exists:budget_cedulas,id`). Error en `budget_cedula_ids` si alguna cédula
  enviada no pertenece a ninguna categoría enviada.

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

namespace Tests\Feature;

use App\Models\BudgetCedula;
use App\Models\Category;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductServiceExpenseClassificationValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_store_rejects_cedula_that_does_not_belong_to_any_selected_category(): void
    {
        [$user, $company, $costCenter] = $this->createContext();

        $categoryA = ExpenseCategory::factory()->create();
        $categoryB = ExpenseCategory::factory()->create();
        $cedulaOfB = BudgetCedula::factory()->create(['expense_category_id' => $categoryB->id]);

        $response = $this->actingAs($user)->post(route('products-services.store'), $this->basePayload($company, $costCenter, [
            'expense_category_ids' => [$categoryA->id],
            'budget_cedula_ids' => [$cedulaOfB->id],
        ]));

        $response->assertSessionHasErrors('budget_cedula_ids');
    }

    public function test_store_accepts_cedulas_that_belong_to_selected_categories(): void
    {
        [$user, $company, $costCenter] = $this->createContext();

        $categoryA = ExpenseCategory::factory()->create();
        $categoryB = ExpenseCategory::factory()->create();
        $cedulaOfA = BudgetCedula::factory()->create(['expense_category_id' => $categoryA->id]);
        $cedulaOfB = BudgetCedula::factory()->create(['expense_category_id' => $categoryB->id]);

        $response = $this->actingAs($user)->post(route('products-services.store'), $this->basePayload($company, $costCenter, [
            'expense_category_ids' => [$categoryA->id, $categoryB->id],
            'budget_cedula_ids' => [$cedulaOfA->id, $cedulaOfB->id],
        ]));

        $response->assertSessionHasNoErrors();
    }

    public function test_store_accepts_no_classification_at_all(): void
    {
        [$user, $company, $costCenter] = $this->createContext();

        $response = $this->actingAs($user)->post(route('products-services.store'), $this->basePayload($company, $costCenter, []));

        $response->assertSessionHasNoErrors();
    }

    private function basePayload(Company $company, CostCenter $costCenter, array $overrides): array
    {
        return array_merge([
            'product_type' => 'PRODUCTO',
            'company_id' => $company->id,
            'cost_center_id' => $costCenter->id,
            'unit_of_measure' => 'PIEZA',
            'estimated_price' => 100,
        ], $overrides);
    }

    private function createContext(): array
    {
        $user = User::factory()->create();
        $user->assignRole('catalog_admin');

        $company = Company::factory()->create();
        $category = Category::factory()->create();
        $costCenter = CostCenter::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
        ]);

        return [$user, $company, $costCenter];
    }
}
```

- [ ] **Step 2: Ejecutar el test y confirmar que falla**

Run: `php artisan test --filter=ProductServiceExpenseClassificationValidationTest`
Expected: FAIL — el request todavía valida `expense_category_id`/`budget_cedula_id`
como escalares, no arreglos; el primer test fallará por una razón distinta a la
esperada (probablemente `422`/error de validación por campos no reconocidos en vez de
un error específico en `budget_cedula_ids`), confirmando que el comportamiento actual
no implementa la regla de conjunto.

- [ ] **Step 3: Actualizar `rules()`**

En `app/Http/Requests/SaveProductServiceRequest.php`, reemplazar las dos líneas
`'expense_category_id' => ...` / `'budget_cedula_id' => ...` (sección `// Clasificación`)
por:
```php
            'expense_category_ids' => 'nullable|array',
            'expense_category_ids.*' => 'integer|exists:expense_categories,id',
            'budget_cedula_ids' => 'nullable|array',
            'budget_cedula_ids.*' => 'integer|exists:budget_cedulas,id',
```
(`is_inventoriable` se queda como está, sin cambios).

- [ ] **Step 4: Actualizar `attributes()`**

Reemplazar las líneas `'expense_category_id' => 'categoría de gasto',` /
`'budget_cedula_id' => 'cédula de gasto',` por:
```php
            'expense_category_ids' => 'categorías de gasto',
            'expense_category_ids.*' => 'categoría de gasto',
            'budget_cedula_ids' => 'cédulas de gasto',
            'budget_cedula_ids.*' => 'cédula de gasto',
```

- [ ] **Step 5: Actualizar la regla cruzada en `withValidator()`**

Reemplazar el bloque actual:
```php
            // Validar que la cédula pertenezca a la categoría de gasto seleccionada
            if (!empty($this->budget_cedula_id)) {
                $cedula = \App\Models\BudgetCedula::find($this->budget_cedula_id);
                if ($cedula && (int) $cedula->expense_category_id !== (int) $this->expense_category_id) {
                    $validator->errors()->add('budget_cedula_id', 'La cédula seleccionada no pertenece a la categoría de gasto elegida.');
                }
            }
```
por:
```php
            // Validar que cada cédula pertenezca a alguna de las categorías de gasto seleccionadas
            $selectedCategoryIds = collect($this->input('expense_category_ids', []))
                ->map(fn ($id) => (int) $id);
            $selectedCedulaIds = collect($this->input('budget_cedula_ids', []));

            if ($selectedCedulaIds->isNotEmpty()) {
                $invalidCedulaNames = \App\Models\BudgetCedula::whereIn('id', $selectedCedulaIds)
                    ->whereNotIn('expense_category_id', $selectedCategoryIds)
                    ->pluck('name');

                if ($invalidCedulaNames->isNotEmpty()) {
                    $validator->errors()->add(
                        'budget_cedula_ids',
                        'Las siguientes cédulas no pertenecen a ninguna categoría de gasto seleccionada: '
                            . $invalidCedulaNames->implode(', ') . '.'
                    );
                }
            }
```

- [ ] **Step 6: Ejecutar el test y confirmar que pasa**

Run: `php artisan test --filter=ProductServiceExpenseClassificationValidationTest`
Expected: PASS (3 tests)

- [ ] **Step 7: Commit**

```bash
git add app/Http/Requests/SaveProductServiceRequest.php tests/Feature/ProductServiceExpenseClassificationValidationTest.php
git commit -m "feat: validar arreglos de categorias/cedulas de gasto con regla cruzada por conjunto"
```

---

### Task 4: `ProductServiceController` — `sync()` en store/update, `edit()`/`show()` cargan relaciones plurales

**Files:**
- Modify: `app/Http/Controllers/ProductServiceController.php`
- Test: `tests/Feature/ProductServiceExpenseClassificationPersistenceTest.php`
  (reemplaza por completo el contenido actual)

**Interfaces:**
- Consumes: `SaveProductServiceRequest` validado (Task 3),
  `ProductService::expenseCategories()`/`budgetCedulas()` (Task 1).
- Produces: filas correctas en `expense_category_product_service`/
  `budget_cedula_product_service` tras crear/editar un producto. `edit()` expone
  `$selectedExpenseCategoryIds`/`$selectedBudgetCedulaIds` (arrays de IDs actuales del
  producto) a la vista.

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

namespace Tests\Feature;

use App\Models\BudgetCedula;
use App\Models\Category;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Models\ProductService;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductServiceExpenseClassificationPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_store_persists_multiple_categories_cedulas_and_inventoriable(): void
    {
        [$user, $company, $costCenter] = $this->createContext();
        $categoryA = ExpenseCategory::factory()->create();
        $categoryB = ExpenseCategory::factory()->create();
        $cedulaA = BudgetCedula::factory()->create(['expense_category_id' => $categoryA->id]);
        $cedulaB = BudgetCedula::factory()->create(['expense_category_id' => $categoryB->id]);

        $response = $this->actingAs($user)->post(route('products-services.store'), [
            'product_type' => 'PRODUCTO',
            'company_id' => $company->id,
            'cost_center_id' => $costCenter->id,
            'unit_of_measure' => 'PIEZA',
            'estimated_price' => 100,
            'expense_category_ids' => [$categoryA->id, $categoryB->id],
            'budget_cedula_ids' => [$cedulaA->id, $cedulaB->id],
            'is_inventoriable' => '1',
        ]);

        $response->assertRedirect();
        $product = ProductService::latest('id')->first();
        $this->assertCount(2, $product->expenseCategories);
        $this->assertCount(2, $product->budgetCedulas);
        $this->assertTrue($product->is_inventoriable);
    }

    public function test_store_without_classification_persists_empty_relations(): void
    {
        [$user, $company, $costCenter] = $this->createContext();

        $response = $this->actingAs($user)->post(route('products-services.store'), [
            'product_type' => 'SERVICIO',
            'company_id' => $company->id,
            'cost_center_id' => $costCenter->id,
            'unit_of_measure' => 'SERVICIO',
            'estimated_price' => 100,
        ]);

        $response->assertRedirect();
        $product = ProductService::latest('id')->first();
        $this->assertCount(0, $product->expenseCategories);
        $this->assertCount(0, $product->budgetCedulas);
    }

    public function test_update_replaces_classification(): void
    {
        [$user, $company, $costCenter] = $this->createContext();
        $categoryA = ExpenseCategory::factory()->create();
        $categoryB = ExpenseCategory::factory()->create();
        $cedulaA = BudgetCedula::factory()->create(['expense_category_id' => $categoryA->id]);
        $cedulaB = BudgetCedula::factory()->create(['expense_category_id' => $categoryB->id]);

        $product = ProductService::factory()->create([
            'company_id' => $company->id,
            'cost_center_id' => $costCenter->id,
        ]);
        $product->expenseCategories()->sync([$categoryA->id]);
        $product->budgetCedulas()->sync([$cedulaA->id]);

        $response = $this->actingAs($user)->put(route('products-services.update', $product), [
            'product_type' => $product->product_type,
            'company_id' => $company->id,
            'cost_center_id' => $costCenter->id,
            'unit_of_measure' => $product->unit_of_measure,
            'estimated_price' => $product->estimated_price,
            'expense_category_ids' => [$categoryB->id],
            'budget_cedula_ids' => [$cedulaB->id],
            'is_inventoriable' => '1',
        ]);

        $response->assertRedirect();
        $product->refresh();
        $this->assertEquals([$categoryB->id], $product->expenseCategories->pluck('id')->all());
        $this->assertEquals([$cedulaB->id], $product->budgetCedulas->pluck('id')->all());
    }

    private function createContext(): array
    {
        $user = User::factory()->create();
        $user->assignRole('catalog_admin');

        $company = Company::factory()->create();
        $category = Category::factory()->create();
        $costCenter = CostCenter::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
        ]);

        return [$user, $company, $costCenter];
    }
}
```

- [ ] **Step 2: Ejecutar el test y confirmar que falla**

Run: `php artisan test --filter=ProductServiceExpenseClassificationPersistenceTest`
Expected: FAIL — el controlador todavía intenta meter `expense_category_id`/
`budget_cedula_id` (ya no existen como columnas tras la Task 1) en `fill()`, lo que
además de no persistir la clasificación probablemente genera un error de columna
inexistente.

- [ ] **Step 3: Actualizar `store()`**

En `app/Http/Controllers/ProductServiceController.php::store()`, dentro del array de
`fill()`, quitar las líneas:
```php
                'expense_category_id' => $data['expense_category_id'] ?? null,
                'budget_cedula_id' => $data['budget_cedula_id'] ?? null,
```
(dejar solo `'is_inventoriable' => $request->boolean('is_inventoriable'),` en ese
bloque).

Después de `$productService->save();` (todavía dentro del `DB::transaction()`),
agregar:
```php
            $productService->expenseCategories()->sync($data['expense_category_ids'] ?? []);
            $productService->budgetCedulas()->sync($data['budget_cedula_ids'] ?? []);
```

- [ ] **Step 4: Actualizar `update()`**

Mismo cambio que el Step 3: quitar las dos líneas del `fill()` de `update()`, y
agregar las dos líneas de `sync()` después de que `$productService` se guarde (busca
dónde `update()` persiste el modelo — usa el mismo patrón que `store()`).

- [ ] **Step 5: Actualizar `edit()`**

Después de donde hoy se calcula `$expenseCategories` en `edit()`, agregar:
```php
        $selectedExpenseCategoryIds = $productService->expenseCategories->pluck('id')->all();
        $selectedBudgetCedulaIds = $productService->budgetCedulas->pluck('id')->all();
```
Agregar `'selectedExpenseCategoryIds'` y `'selectedBudgetCedulaIds'` al `compact(...)`
del `return view('products_services.edit', compact(...))`.

- [ ] **Step 6: Actualizar `show()`**

En el `$productService->load([...])` de `show()`, reemplazar `'expenseCategory'` y
`'budgetCedula'` por `'expenseCategories'` y `'budgetCedulas'`.

- [ ] **Step 7: Ejecutar el test y confirmar que pasa**

Run: `php artisan test --filter=ProductServiceExpenseClassificationPersistenceTest`
Expected: PASS (3 tests)

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/ProductServiceController.php tests/Feature/ProductServiceExpenseClassificationPersistenceTest.php
git commit -m "feat: sincronizar categorias/cedulas de gasto via sync() en store/update"
```

---

### Task 5: `create.blade.php` — multi-select Select2 con optgroups dinámicos

**Files:**
- Modify: `resources/views/products_services/create.blade.php`
- Test: `tests/Feature/ProductServiceCreateFormClassificationFieldsTest.php`
  (reemplaza por completo el contenido actual)

**Interfaces:**
- Consumes: `$expenseCategories` (ya cargado por el controlador, Task 4 no lo cambia),
  catálogo embebido JSON existente (`#expense-cedulas-catalog`).
- Produces: campos de formulario `name="expense_category_ids[]"` y
  `name="budget_cedula_ids[]"`, ambos `<select multiple>` inicializados con Select2.

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

namespace Tests\Feature;

use App\Models\BudgetCedula;
use App\Models\ExpenseCategory;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductServiceCreateFormClassificationFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_shows_multi_select_classification_fields(): void
    {
        $user = User::factory()->create();
        $this->seed(RolePermissionSeeder::class);
        $user->assignRole('catalog_admin');

        $category = ExpenseCategory::factory()->create(['name' => 'Mantenimiento']);
        BudgetCedula::factory()->create(['expense_category_id' => $category->id, 'name' => 'Mantenimiento de Estaciones']);

        $response = $this->actingAs($user)->get(route('products-services.create'));

        $response->assertOk();
        $response->assertSee('name="expense_category_ids[]"', false);
        $response->assertSee('name="budget_cedula_ids[]"', false);
        $response->assertSee('Mantenimiento', false);
        $response->assertSee('Mantenimiento de Estaciones', false);
    }
}
```

- [ ] **Step 2: Ejecutar el test y confirmar que falla**

Run: `php artisan test --filter=ProductServiceCreateFormClassificationFieldsTest`
Expected: FAIL — los campos siguen llamándose `expense_category_id`/
`budget_cedula_id` (sin `[]`), sin `multiple`.

- [ ] **Step 3: Reemplazar el bloque de los dos `<select>`**

En `resources/views/products_services/create.blade.php`, ubicar el bloque que
empieza en `{{-- Categoría de Gasto --}}` y termina justo antes de
`{{-- Inventariable --}}` (dos columnas dentro de un `<div class="row">`).
Reemplazarlo completo por:

```blade
                    <div class="row">
                        {{-- Categorías de Gasto --}}
                        <div class="col-md-6 mb-3">
                            <label for="expense_category_ids" class="form-label">Categorías de Gasto</label>
                            <select class="form-select @error('expense_category_ids') is-invalid @enderror"
                                    id="expense_category_ids"
                                    name="expense_category_ids[]"
                                    multiple
                                    style="width: 100%;">
                                @foreach ($expenseCategories as $category)
                                    <option value="{{ $category->id }}"
                                        {{ collect(old('expense_category_ids', []))->contains($category->id) ? 'selected' : '' }}>
                                        [{{ $category->code }}] {{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('expense_category_ids')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Cédulas de Gasto --}}
                        <div class="col-md-6 mb-3">
                            <label for="budget_cedula_ids" class="form-label">Cédulas de Gasto</label>
                            <select class="form-select @error('budget_cedula_ids') is-invalid @enderror"
                                    id="budget_cedula_ids"
                                    name="budget_cedula_ids[]"
                                    multiple
                                    style="width: 100%;">
                            </select>
                            <div class="form-text">Selecciona primero una o más categorías de gasto.</div>
                            @error('budget_cedula_ids')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

```

(El checkbox "Inventariable" que sigue después no cambia.)

- [ ] **Step 4: Reemplazar el JS del cascade por la versión multi-select**

Ubicar, dentro de `@push('scripts')`, el bloque que empieza en el comentario
`// Cascade Categoría de Gasto -> Cédula (sin AJAX, catálogo embebido)` y termina en
la línea `refreshCedulaOptions('{{ old('budget_cedula_id') }}');`. Reemplazarlo
completo por:

```javascript
            // Multi-select Categorías de Gasto -> Cédulas de Gasto (sin AJAX, catálogo embebido)
            const cedulasByCategory = JSON.parse(document.getElementById('expense-cedulas-catalog').textContent);

            const $categorySelect = $('#expense_category_ids');
            const $cedulaSelect = $('#budget_cedula_ids');

            $categorySelect.select2({
                theme: 'bootstrap-5',
                placeholder: 'Seleccione categorías...',
                allowClear: true,
                closeOnSelect: false,
            });

            $cedulaSelect.select2({
                theme: 'bootstrap-5',
                placeholder: 'Seleccione cédulas...',
                allowClear: true,
                closeOnSelect: false,
            });

            function refreshCedulaOptions(preselectedCedulaIds) {
                const selectedCategoryIds = ($categorySelect.val() || []).map(String);
                const previouslySelected = preselectedCedulaIds !== undefined
                    ? preselectedCedulaIds.map(String)
                    : ($cedulaSelect.val() || []).map(String);

                $cedulaSelect.empty();

                selectedCategoryIds.forEach(function (categoryId) {
                    const cedulas = cedulasByCategory[categoryId] || [];
                    if (cedulas.length === 0) {
                        return;
                    }

                    const categoryLabel = $categorySelect.find(`option[value="${categoryId}"]`).text().trim();
                    const $optgroup = $('<optgroup>').attr('label', categoryLabel);

                    cedulas.forEach(function (cedula) {
                        const $option = $('<option>')
                            .val(cedula.id)
                            .text(cedula.name)
                            .prop('selected', previouslySelected.includes(String(cedula.id)));
                        $optgroup.append($option);
                    });

                    $cedulaSelect.append($optgroup);
                });

                $cedulaSelect.trigger('change');
            }

            $categorySelect.on('change', function () {
                refreshCedulaOptions();
            });

            refreshCedulaOptions(@json(old('budget_cedula_ids', [])));
```

- [ ] **Step 5: Ejecutar el test y confirmar que pasa**

Run: `php artisan test --filter=ProductServiceCreateFormClassificationFieldsTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add resources/views/products_services/create.blade.php tests/Feature/ProductServiceCreateFormClassificationFieldsTest.php
git commit -m "feat: multi-select de categorias y cedulas de gasto en formulario de nuevo producto"
```

---

### Task 6: `edit.blade.php` — mismo multi-select, precargado con la clasificación actual

**Files:**
- Modify: `resources/views/products_services/edit.blade.php`
- Test: `tests/Feature/ProductServiceEditFormClassificationFieldsTest.php`
  (reemplaza por completo el contenido actual)

**Interfaces:**
- Consumes: `$expenseCategories`, `$selectedExpenseCategoryIds`,
  `$selectedBudgetCedulaIds` (todos ya provistos por `edit()`, Task 4).

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

namespace Tests\Feature;

use App\Models\BudgetCedula;
use App\Models\ExpenseCategory;
use App\Models\ProductService;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductServiceEditFormClassificationFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_form_preselects_current_multi_classification(): void
    {
        $user = User::factory()->create();
        $this->seed(RolePermissionSeeder::class);
        $user->assignRole('catalog_admin');

        $categoryA = ExpenseCategory::factory()->create();
        $categoryB = ExpenseCategory::factory()->create();
        $cedulaA = BudgetCedula::factory()->create(['expense_category_id' => $categoryA->id, 'name' => 'Cedula Precargada A']);
        $cedulaB = BudgetCedula::factory()->create(['expense_category_id' => $categoryB->id, 'name' => 'Cedula Precargada B']);

        $product = ProductService::factory()->create();
        $product->expenseCategories()->sync([$categoryA->id, $categoryB->id]);
        $product->budgetCedulas()->sync([$cedulaA->id, $cedulaB->id]);

        $response = $this->actingAs($user)->get(route('products-services.edit', $product));
        $content = $response->getContent();

        $response->assertOk();
        $response->assertSee('name="expense_category_ids[]"', false);
        $response->assertSee('Cedula Precargada A', false);
        $response->assertSee('Cedula Precargada B', false);
        $this->assertMatchesRegularExpression(
            '/<option value="' . $categoryA->id . '"\s+selected/',
            $content
        );
        $this->assertMatchesRegularExpression(
            '/<option value="' . $categoryB->id . '"\s+selected/',
            $content
        );
    }
}
```

- [ ] **Step 2: Ejecutar el test y confirmar que falla**

Run: `php artisan test --filter=ProductServiceEditFormClassificationFieldsTest`
Expected: FAIL — el formulario sigue con los `<select>` simples de hoy.

- [ ] **Step 3: Reemplazar el bloque de los dos `<select>`**

Mismo reemplazo que Task 5 Step 3, pero usando los valores actuales del producto como
fallback de `old()`:

```blade
                    <div class="row">
                        {{-- Categorías de Gasto --}}
                        <div class="col-md-6 mb-3">
                            <label for="expense_category_ids" class="form-label">Categorías de Gasto</label>
                            <select class="form-select @error('expense_category_ids') is-invalid @enderror"
                                    id="expense_category_ids"
                                    name="expense_category_ids[]"
                                    multiple
                                    style="width: 100%;">
                                @foreach ($expenseCategories as $category)
                                    <option value="{{ $category->id }}"
                                        {{ collect(old('expense_category_ids', $selectedExpenseCategoryIds))->contains($category->id) ? 'selected' : '' }}>
                                        [{{ $category->code }}] {{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('expense_category_ids')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Cédulas de Gasto --}}
                        <div class="col-md-6 mb-3">
                            <label for="budget_cedula_ids" class="form-label">Cédulas de Gasto</label>
                            <select class="form-select @error('budget_cedula_ids') is-invalid @enderror"
                                    id="budget_cedula_ids"
                                    name="budget_cedula_ids[]"
                                    multiple
                                    style="width: 100%;">
                            </select>
                            <div class="form-text">Selecciona primero una o más categorías de gasto.</div>
                            @error('budget_cedula_ids')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

```

- [ ] **Step 4: Reemplazar el JS del cascade**

Mismo bloque JS que Task 5 Step 4, con una sola diferencia en la última línea (usa el
fallback de la clasificación actual del producto en vez de un arreglo vacío):

```javascript
            refreshCedulaOptions(@json(old('budget_cedula_ids', $selectedBudgetCedulaIds)));
```

(El resto del bloque — inicialización de Select2, `refreshCedulaOptions()`, el
listener `change` — es idéntico al de `create.blade.php`.)

- [ ] **Step 5: Ejecutar el test y confirmar que pasa**

Run: `php artisan test --filter=ProductServiceEditFormClassificationFieldsTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add resources/views/products_services/edit.blade.php tests/Feature/ProductServiceEditFormClassificationFieldsTest.php
git commit -m "feat: precargar multi-select de categorias y cedulas de gasto en formulario de edicion"
```

---

### Task 7: `show.blade.php` — listar categorías y cédulas vinculadas

**Files:**
- Modify: `resources/views/products_services/show.blade.php`
- Test: `tests/Feature/ProductServiceShowClassificationFieldsTest.php` (reemplaza por
  completo el contenido actual)

**Interfaces:**
- Consumes: `$productService->expenseCategories`, `$productService->budgetCedulas`
  (ya cargadas por `show()`, Task 4).

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

namespace Tests\Feature;

use App\Models\BudgetCedula;
use App\Models\ExpenseCategory;
use App\Models\ProductService;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductServiceShowClassificationFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_page_displays_multiple_categories_and_cedulas(): void
    {
        $user = User::factory()->create();
        $this->seed(RolePermissionSeeder::class);
        $user->assignRole('catalog_admin');

        $categoryA = ExpenseCategory::factory()->create(['name' => 'Mantenimiento']);
        $categoryB = ExpenseCategory::factory()->create(['name' => 'Gastos Fijos']);
        $cedulaA = BudgetCedula::factory()->create(['expense_category_id' => $categoryA->id, 'name' => 'Cedula Uno']);
        $cedulaB = BudgetCedula::factory()->create(['expense_category_id' => $categoryB->id, 'name' => 'Cedula Dos']);

        $product = ProductService::factory()->create(['is_inventoriable' => true]);
        $product->expenseCategories()->sync([$categoryA->id, $categoryB->id]);
        $product->budgetCedulas()->sync([$cedulaA->id, $cedulaB->id]);

        $response = $this->actingAs($user)->get(route('products-services.show', $product));

        $response->assertOk();
        $response->assertSee('Mantenimiento');
        $response->assertSee('Gastos Fijos');
        $response->assertSee('Cedula Uno');
        $response->assertSee('Cedula Dos');
        $response->assertSee('Sí');
    }

    public function test_show_page_handles_product_without_classification(): void
    {
        $user = User::factory()->create();
        $this->seed(RolePermissionSeeder::class);
        $user->assignRole('catalog_admin');

        $product = ProductService::factory()->create();

        $response = $this->actingAs($user)->get(route('products-services.show', $product));

        $response->assertOk();
        $response->assertSee('Sin clasificar');
    }
}
```

- [ ] **Step 2: Ejecutar el test y confirmar que falla**

Run: `php artisan test --filter=ProductServiceShowClassificationFieldsTest`
Expected: FAIL — `$productService->expenseCategory`/`->budgetCedula` (singular) ya no
existen como relaciones válidas tras la Task 1, y la vista todavía las referencia.

- [ ] **Step 3: Reemplazar el bloque de categoría/cédula**

En `resources/views/products_services/show.blade.php`, dentro de la card "Estructura
Contable", ubicar los dos `<div class="row mb-2">` que muestran "Categoría de Gasto:"
y "Cédula de Gasto:" (justo después del `<hr>`, antes del bloque "Inventariable:") y
reemplazarlos por:

```blade
                    <div class="row mb-2">
                        <div class="col-5">
                            <strong>Categorías de Gasto:</strong>
                        </div>
                        <div class="col-7">
                            @forelse ($productService->expenseCategories as $category)
                                <span class="badge bg-light text-dark border me-1 mb-1">[{{ $category->code }}] {{ $category->name }}</span>
                            @empty
                                Sin clasificar
                            @endforelse
                        </div>
                    </div>

                    <div class="row mb-2">
                        <div class="col-5">
                            <strong>Cédulas de Gasto:</strong>
                        </div>
                        <div class="col-7">
                            @forelse ($productService->budgetCedulas as $cedula)
                                <span class="badge bg-light text-dark border me-1 mb-1">{{ $cedula->name }}</span>
                            @empty
                                Sin clasificar
                            @endforelse
                        </div>
                    </div>
```

(El bloque "Inventariable:" que sigue después no cambia.)

- [ ] **Step 4: Ejecutar el test y confirmar que pasa**

Run: `php artisan test --filter=ProductServiceShowClassificationFieldsTest`
Expected: PASS (2 tests)

- [ ] **Step 5: Ejecutar toda la suite de `ProductService` para descartar regresiones**

Run: `php artisan test --filter=ProductService`
Expected: PASS — incluye las 7 tareas de este plan más los dos tests preexistentes
(`ProductServiceForRequisitionApiTest`, `ProductServiceSycSeederTest`), que no deben
verse afectados (ninguno de los dos toca `expense_category_id`/`budget_cedula_id`).

- [ ] **Step 6: Commit**

```bash
git add resources/views/products_services/show.blade.php tests/Feature/ProductServiceShowClassificationFieldsTest.php
git commit -m "feat: mostrar lista de categorias y cedulas de gasto vinculadas en el detalle de producto"
```

---

## Verificación final (manual, tras completar las 7 tareas)

- Correr toda la suite: `php artisan test` — comparar contra el baseline conocido (los
  mismos ~50 fallos preexistentes y no relacionados que ya se documentaron en
  `.superpowers/sdd/progress.md` del plan anterior); no debe haber fallos nuevos.
- En el navegador: crear un producto nuevo, seleccionar 2+ categorías de gasto,
  confirmar que el multi-select de cédulas se puebla agrupado por categoría
  (`<optgroup>`) y permite elegir cédulas de ambas. Quitar una de las categorías
  seleccionadas y confirmar que sus cédulas ya elegidas desaparecen de la selección
  automáticamente. Guardar y verificar en el detalle que aparecen todas las
  categorías/cédulas como badges. Editar ese mismo producto y confirmar que ambos
  multi-select llegan preseleccionados correctamente.
- Desde el catálogo de "Cédulas de Gasto", intentar eliminar o desactivar una
  categoría/cédula que quedó vinculada al producto de prueba — confirmar que el guard
  la bloquea con un mensaje, igual que ya bloquea por otros usos.
