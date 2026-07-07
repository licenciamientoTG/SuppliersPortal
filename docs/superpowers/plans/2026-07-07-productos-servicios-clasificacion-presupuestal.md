# Clasificación Presupuestal de Productos/Servicios — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permitir que cada registro de `products_services` se vincule opcionalmente a una categoría de gasto (`expense_categories`) y su cédula (`budget_cedulas`), y agregar un campo booleano `is_inventoriable`, sin tocar la estructura contable (`account_major`/`account_sub`/`account_subsub`) ni la lógica de aprobación existente.

**Architecture:** Dos columnas FK nullable (`expense_category_id`, `budget_cedula_id`) + una columna boolean (`is_inventoriable`) agregadas a `products_services` vía migración con backfill. El modelo `ProductService` gana una relación nueva y los tres campos en `$fillable`/`$casts`. `SaveProductServiceRequest` valida existencia y consistencia categoría↔cédula. El controlador persiste los tres campos. Las vistas `create`/`edit` agregan un select en cascada (categoría → cédula) y un checkbox, siguiendo el patrón de filtrado 100% cliente (sin AJAX) que este mismo formulario ya usa para compañía→centro de costo — se embebe el catálogo completo de categorías/cédulas (14 categorías, ~192 cédulas) como datos en la página y se filtra con jQuery, igual que ya hace `company_id`→`cost_center_id`.

**Tech Stack:** Laravel 10, Eloquent, Blade + jQuery (sin AJAX para este cascade — ver nota de arquitectura), PHPUnit (`Tests\Feature\*Test` + `RefreshDatabase`, DB de test en SQLite in-memory).

## Global Constraints

- No modificar `account_major`/`account_sub`/`account_subsub`, `hasCompleteAccountingStructure()`, ni el flujo de aprobación (`approve`/`reactivate`) — deben seguir funcionando exactamente igual (spec, sección "Decisiones de diseño" #1 y "Fuera de alcance").
- `expense_category_id` y `budget_cedula_id` son **siempre opcionales** (nullable en BD y en el formulario) — no se exige backfill de productos históricos (spec, decisión #2).
- `is_inventoriable`: sin restricción dura por `product_type` en backend/validación; el JS del formulario solo lo pre-marca/desmarca como sugerencia (spec, decisión #3).
- No auto-rellenar `expense_category_id`/`budget_cedula_id` en `RequisitionItem` — fuera de alcance (spec, "Fuera de alcance").
- Mensajes de validación y UI en español, siguiendo el estilo ya usado en `SaveProductServiceRequest`/`products_services/*.blade.php`.
- Spec completo: `docs/superpowers/specs/2026-07-07-productos-servicios-clasificacion-presupuestal-design.md`.

---

### Task 1: Migración + modelo `ProductService`

**Files:**
- Create: `database/migrations/2026_07_07_150000_add_expense_classification_to_products_services_table.php`
- Modify: `app/Models/ProductService.php`
- Test: `tests/Feature/ProductServiceExpenseClassificationTest.php`

**Interfaces:**
- Produces: columnas `products_services.expense_category_id` (nullable FK → `expense_categories.id`), `products_services.budget_cedula_id` (nullable FK → `budget_cedulas.id`), `products_services.is_inventoriable` (boolean, default `true`). Relación `ProductService::budgetCedula(): BelongsTo`. `ProductService::expenseCategory(): BelongsTo` (ya existía en el modelo, queda funcional al existir la columna).

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

namespace Tests\Feature;

use App\Models\BudgetCedula;
use App\Models\ExpenseCategory;
use App\Models\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductServiceExpenseClassificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_service_can_be_linked_to_expense_category_and_cedula(): void
    {
        $category = ExpenseCategory::factory()->create();
        $cedula = BudgetCedula::factory()->create(['expense_category_id' => $category->id]);

        $product = ProductService::factory()->create([
            'expense_category_id' => $category->id,
            'budget_cedula_id' => $cedula->id,
            'is_inventoriable' => true,
        ]);

        $this->assertTrue($product->expenseCategory->is($category));
        $this->assertTrue($product->budgetCedula->is($cedula));
        $this->assertTrue($product->fresh()->is_inventoriable);
    }

    public function test_expense_category_and_cedula_are_nullable(): void
    {
        $product = ProductService::factory()->create([
            'expense_category_id' => null,
            'budget_cedula_id' => null,
        ]);

        $this->assertNull($product->fresh()->expense_category_id);
        $this->assertNull($product->fresh()->budget_cedula_id);
    }

    public function test_migration_backfills_is_inventoriable_from_product_type(): void
    {
        Artisan::call('migrate:rollback', ['--step' => 1]);

        $now = now();
        $id = DB::table('products_services')->insertGetId([
            'code' => 'PROD-BKFL01',
            'technical_description' => 'Servicio de prueba para backfill',
            'product_type' => 'SERVICIO',
            'category_id' => \App\Models\Category::factory()->create()->id,
            'cost_center_id' => \App\Models\CostCenter::factory()->create()->id,
            'company_id' => \App\Models\Company::factory()->create()->id,
            'unit_of_measure' => 'SERVICIO',
            'estimated_price' => 100,
            'status' => 'ACTIVE',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Artisan::call('migrate');

        $row = DB::table('products_services')->find($id);
        $this->assertEquals(0, $row->is_inventoriable);
    }
}
```

- [ ] **Step 2: Ejecutar el test y confirmar que falla**

Run: `php artisan test --filter=ProductServiceExpenseClassificationTest`
Expected: FAIL — columnas `expense_category_id`/`budget_cedula_id`/`is_inventoriable` no existen (`SQLSTATE... no such column`), y `budgetCedula()` no existe en el modelo.

- [ ] **Step 3: Crear la migración**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
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

            $table->boolean('is_inventoriable')->default(true)
                ->after('product_type')
                ->comment('TRUE si el producto se controla por inventario');

            $table->index('expense_category_id');
            $table->index('budget_cedula_id');
            $table->index('is_inventoriable');
        });

        DB::table('products_services')
            ->where('product_type', 'SERVICIO')
            ->update(['is_inventoriable' => false]);
    }

    public function down(): void
    {
        Schema::table('products_services', function (Blueprint $table) {
            $table->dropForeign(['expense_category_id']);
            $table->dropForeign(['budget_cedula_id']);
            $table->dropColumn(['expense_category_id', 'budget_cedula_id', 'is_inventoriable']);
        });
    }
};
```

- [ ] **Step 4: Agregar la relación y los campos al modelo**

En `app/Models/ProductService.php`, agregar a `$fillable` (dentro del bloque `// Clasificación`):

```php
        // Clasificación
        'category_id',
        'subcategory',
        'expense_category_id',
        'budget_cedula_id',
        'is_inventoriable',
```

Agregar a `$casts`:

```php
        'is_inventoriable' => 'boolean',
```

Agregar la relación nueva, junto a `expenseCategory()` (que ya existe en el archivo, sección RELACIONES):

```php
    public function budgetCedula(): BelongsTo
    {
        return $this->belongsTo(BudgetCedula::class);
    }
```

- [ ] **Step 5: Ejecutar el test y confirmar que pasa**

Run: `php artisan test --filter=ProductServiceExpenseClassificationTest`
Expected: PASS (3 tests, 0 failures)

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_07_150000_add_expense_classification_to_products_services_table.php app/Models/ProductService.php tests/Feature/ProductServiceExpenseClassificationTest.php
git commit -m "feat: vincular products_services con expense_category/budget_cedula y agregar is_inventoriable"
```

---

### Task 2: Validación en `SaveProductServiceRequest`

**Files:**
- Modify: `app/Http/Requests/SaveProductServiceRequest.php`
- Test: `tests/Feature/ProductServiceExpenseClassificationValidationTest.php`

**Interfaces:**
- Consumes: `ExpenseCategory`, `BudgetCedula` (Task 1), rutas `products-services.store`/`products-services.update` (ya existentes).
- Produces: reglas `expense_category_id` (`nullable|exists:expense_categories,id`), `budget_cedula_id` (`nullable|exists:budget_cedulas,id`), `is_inventoriable` (`boolean`); error de validación en `budget_cedula_id` si su categoría padre no coincide con `expense_category_id` enviado.

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

    public function test_store_rejects_cedula_that_does_not_belong_to_selected_category(): void
    {
        [$user, $company, $costCenter] = $this->createContext();

        $categoryA = ExpenseCategory::factory()->create();
        $categoryB = ExpenseCategory::factory()->create();
        $cedulaOfB = BudgetCedula::factory()->create(['expense_category_id' => $categoryB->id]);

        $response = $this->actingAs($user)->post(route('products-services.store'), $this->basePayload($company, $costCenter, [
            'expense_category_id' => $categoryA->id,
            'budget_cedula_id' => $cedulaOfB->id,
        ]));

        $response->assertSessionHasErrors('budget_cedula_id');
    }

    public function test_store_accepts_cedula_that_belongs_to_selected_category(): void
    {
        [$user, $company, $costCenter] = $this->createContext();

        $category = ExpenseCategory::factory()->create();
        $cedula = BudgetCedula::factory()->create(['expense_category_id' => $category->id]);

        $response = $this->actingAs($user)->post(route('products-services.store'), $this->basePayload($company, $costCenter, [
            'expense_category_id' => $category->id,
            'budget_cedula_id' => $cedula->id,
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
Expected: FAIL — `test_store_rejects_cedula_that_does_not_belong_to_selected_category` falla porque hoy no hay ninguna regla que rechace esa combinación (no hay error en `budget_cedula_id`).

- [ ] **Step 3: Agregar las reglas y la validación cruzada**

En `app/Http/Requests/SaveProductServiceRequest.php`, dentro de `rules()`, agregar bajo el bloque `// Clasificación`:

```php
            // Clasificación
            'subcategory' => 'nullable|string|max:100',
            'expense_category_id' => 'nullable|exists:expense_categories,id',
            'budget_cedula_id' => 'nullable|exists:budget_cedulas,id',
            'is_inventoriable' => 'boolean',
```

En `attributes()`, agregar:

```php
            'expense_category_id' => 'categoría de gasto',
            'budget_cedula_id' => 'cédula de gasto',
            'is_inventoriable' => 'inventariable',
```

En `withValidator()`, dentro del `$validator->after(function ($validator) { ... })` ya existente, agregar al final (antes del cierre del closure):

```php
            // Validar que la cédula pertenezca a la categoría de gasto seleccionada
            if (!empty($this->budget_cedula_id)) {
                $cedula = \App\Models\BudgetCedula::find($this->budget_cedula_id);
                if ($cedula && (int) $cedula->expense_category_id !== (int) $this->expense_category_id) {
                    $validator->errors()->add('budget_cedula_id', 'La cédula seleccionada no pertenece a la categoría de gasto elegida.');
                }
            }
```

- [ ] **Step 4: Ejecutar el test y confirmar que pasa**

Run: `php artisan test --filter=ProductServiceExpenseClassificationValidationTest`
Expected: PASS (3 tests, 0 failures)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Requests/SaveProductServiceRequest.php tests/Feature/ProductServiceExpenseClassificationValidationTest.php
git commit -m "feat: validar categoria de gasto y consistencia con la cedula en products_services"
```

---

### Task 3: Persistir los campos en `ProductServiceController`

**Files:**
- Modify: `app/Http/Controllers/ProductServiceController.php:177-235` (método `store`), `:294-...` (método `update`)
- Test: `tests/Feature/ProductServiceExpenseClassificationPersistenceTest.php`

**Interfaces:**
- Consumes: `SaveProductServiceRequest` validado (Task 2), rutas `products-services.store`/`products-services.update`.
- Produces: `products_services.expense_category_id`, `.budget_cedula_id`, `.is_inventoriable` persistidos correctamente en creación y edición.

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

    public function test_store_persists_expense_category_cedula_and_inventoriable(): void
    {
        [$user, $company, $costCenter] = $this->createContext();
        $category = ExpenseCategory::factory()->create();
        $cedula = BudgetCedula::factory()->create(['expense_category_id' => $category->id]);

        $response = $this->actingAs($user)->post(route('products-services.store'), [
            'product_type' => 'PRODUCTO',
            'company_id' => $company->id,
            'cost_center_id' => $costCenter->id,
            'unit_of_measure' => 'PIEZA',
            'estimated_price' => 100,
            'expense_category_id' => $category->id,
            'budget_cedula_id' => $cedula->id,
            'is_inventoriable' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('products_services', [
            'expense_category_id' => $category->id,
            'budget_cedula_id' => $cedula->id,
            'is_inventoriable' => true,
        ]);
    }

    public function test_store_without_classification_persists_nulls(): void
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
        $this->assertNull($product->expense_category_id);
        $this->assertNull($product->budget_cedula_id);
        $this->assertFalse((bool) $product->is_inventoriable);
    }

    public function test_update_persists_new_classification(): void
    {
        [$user, $company, $costCenter] = $this->createContext();
        $category = ExpenseCategory::factory()->create();
        $cedula = BudgetCedula::factory()->create(['expense_category_id' => $category->id]);

        $product = ProductService::factory()->create([
            'company_id' => $company->id,
            'cost_center_id' => $costCenter->id,
        ]);

        $response = $this->actingAs($user)->put(route('products-services.update', $product), [
            'product_type' => $product->product_type,
            'company_id' => $company->id,
            'cost_center_id' => $costCenter->id,
            'unit_of_measure' => $product->unit_of_measure,
            'estimated_price' => $product->estimated_price,
            'expense_category_id' => $category->id,
            'budget_cedula_id' => $cedula->id,
            'is_inventoriable' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('products_services', [
            'id' => $product->id,
            'expense_category_id' => $category->id,
            'budget_cedula_id' => $cedula->id,
            'is_inventoriable' => true,
        ]);
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
Expected: FAIL — los tres campos nuevos no se guardan (el controlador aún no los incluye en `fill()`).

- [ ] **Step 3: Actualizar `store()`**

En `app/Http/Controllers/ProductServiceController.php`, dentro de `store()`, en el array de `fill()`, bajo el comentario `// Clasificación derivada del centro de costo`:

```php
                // Clasificación derivada del centro de costo
                'category_id' => $costCenter->category_id,
                'subcategory' => $data['subcategory'] ?? null,
                'expense_category_id' => $data['expense_category_id'] ?? null,
                'budget_cedula_id' => $data['budget_cedula_id'] ?? null,
                'is_inventoriable' => $request->boolean('is_inventoriable'),
```

- [ ] **Step 4: Actualizar `update()`**

Mismo bloque en `update()` (busca el segundo `// Clasificación derivada del centro de costo` en el archivo, dentro de `fill()`):

```php
                // Clasificación derivada del centro de costo
                'category_id' => $costCenter->category_id,
                'subcategory' => $data['subcategory'] ?? null,
                'expense_category_id' => $data['expense_category_id'] ?? null,
                'budget_cedula_id' => $data['budget_cedula_id'] ?? null,
                'is_inventoriable' => $request->boolean('is_inventoriable'),
```

- [ ] **Step 5: Ejecutar el test y confirmar que pasa**

Run: `php artisan test --filter=ProductServiceExpenseClassificationPersistenceTest`
Expected: PASS (3 tests, 0 failures)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ProductServiceController.php tests/Feature/ProductServiceExpenseClassificationPersistenceTest.php
git commit -m "feat: persistir expense_category_id, budget_cedula_id e is_inventoriable al guardar productos"
```

---

### Task 4: Formulario `create.blade.php` — select en cascada + checkbox

**Files:**
- Modify: `app/Http/Controllers/ProductServiceController.php:132-172` (método `create`)
- Modify: `resources/views/products_services/create.blade.php`
- Test: `tests/Feature/ProductServiceCreateFormClassificationFieldsTest.php`

**Interfaces:**
- Consumes: `ExpenseCategory::active()`, `BudgetCedula::active()->notDeleted()` (scopes ya existentes en ambos modelos).
- Produces: la vista `products_services.create` recibe una variable `expenseCategories` (colección de categorías activas con sus cédulas activas cargadas, `->load('cedulas')` scopeado a activas) para poblar el cascade sin AJAX.

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ExpenseCategory;
use App\Models\BudgetCedula;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductServiceCreateFormClassificationFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_shows_expense_category_and_cedula_and_inventoriable_fields(): void
    {
        $user = User::factory()->create();
        $this->seed(RolePermissionSeeder::class);
        $user->assignRole('catalog_admin');

        $category = ExpenseCategory::factory()->create(['name' => 'Mantenimiento']);
        BudgetCedula::factory()->create(['expense_category_id' => $category->id, 'name' => 'Mantenimiento de Estaciones']);

        $response = $this->actingAs($user)->get(route('products-services.create'));

        $response->assertOk();
        $response->assertSee('name="expense_category_id"', false);
        $response->assertSee('name="budget_cedula_id"', false);
        $response->assertSee('name="is_inventoriable"', false);
        $response->assertSee('Mantenimiento', false);
        $response->assertSee('Mantenimiento de Estaciones', false);
    }
}
```

- [ ] **Step 2: Ejecutar el test y confirmar que falla**

Run: `php artisan test --filter=ProductServiceCreateFormClassificationFieldsTest`
Expected: FAIL — no existen los campos `expense_category_id`/`budget_cedula_id`/`is_inventoriable` en la vista.

- [ ] **Step 3: Cargar el catálogo en el controlador**

En `app/Http/Controllers/ProductServiceController.php`, dentro de `create()`, agregar antes del `return view(...)`:

```php
        $expenseCategories = \App\Models\ExpenseCategory::active()
            ->with(['cedulas' => fn ($q) => $q->active()->notDeleted()->orderBy('name')])
            ->orderBy('code')
            ->get(['id', 'code', 'name']);
```

Y agregar `'expenseCategories'` al `compact(...)` del `return view('products_services.create', compact(...))`.

- [ ] **Step 4: Agregar los campos en la vista**

En `resources/views/products_services/create.blade.php`, dentro de la card "Información Principal", justo después del bloque `{{-- Subcategoría --}}` (línea ~138, antes de `{{-- Descripción Técnica --}}`), agregar:

```blade
                    <div class="row">
                        {{-- Categoría de Gasto --}}
                        <div class="col-md-6 mb-3">
                            <label for="expense_category_id" class="form-label">Categoría de Gasto</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="ti ti-receipt-2"></i>
                                </span>
                                <select class="form-select @error('expense_category_id') is-invalid @enderror"
                                        id="expense_category_id"
                                        name="expense_category_id">
                                    <option value="">Sin clasificar</option>
                                    @foreach ($expenseCategories as $category)
                                        <option value="{{ $category->id }}"
                                            {{ old('expense_category_id') == $category->id ? 'selected' : '' }}>
                                            [{{ $category->code }}] {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @error('expense_category_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Cédula de Gasto --}}
                        <div class="col-md-6 mb-3">
                            <label for="budget_cedula_id" class="form-label">Cédula de Gasto</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="ti ti-file-text"></i>
                                </span>
                                <select class="form-select @error('budget_cedula_id') is-invalid @enderror"
                                        id="budget_cedula_id"
                                        name="budget_cedula_id"
                                        disabled>
                                    <option value="">Seleccione categoría primero...</option>
                                </select>
                            </div>
                            @error('budget_cedula_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Inventariable --}}
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="is_inventoriable" name="is_inventoriable" value="1"
                               {{ old('is_inventoriable', $productService->product_type === 'PRODUCTO') ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_inventoriable">Inventariable</label>
                        <div class="form-text">Marca si este producto se controla por inventario. Se sugiere automáticamente según el tipo, pero puedes ajustarlo.</div>
                    </div>
```

Al final de `@section('content')`, justo antes de `@endsection` (fuera del `<form>`, pero dentro de la misma vista), agregar el catálogo embebido para el JS:

```blade
    <script id="expense-cedulas-catalog" type="application/json">
        {!! $expenseCategories->pluck('cedulas', 'id')->map(fn ($cedulas) => $cedulas->map(fn ($c) => ['id' => $c->id, 'name' => $c->name]))->toJson() !!}
    </script>
```

En el `@push('scripts')` ya existente, dentro del `$(function() { ... })`, agregar (después del bloque de `updateCategoryDisplay`):

```javascript
            // Cascade Categoría de Gasto -> Cédula (sin AJAX, catálogo embebido)
            const cedulasByCategory = JSON.parse(document.getElementById('expense-cedulas-catalog').textContent);

            function refreshCedulaOptions(selectedCedulaId) {
                const categoryId = $('#expense_category_id').val();
                const $cedulaSelect = $('#budget_cedula_id');
                const cedulas = cedulasByCategory[categoryId] || [];

                $cedulaSelect.empty();

                if (!categoryId || cedulas.length === 0) {
                    $cedulaSelect.append('<option value="">Sin cédulas disponibles</option>').prop('disabled', true);
                    return;
                }

                $cedulaSelect.append('<option value="">Seleccione cédula...</option>');
                cedulas.forEach(function (cedula) {
                    const selected = String(cedula.id) === String(selectedCedulaId) ? 'selected' : '';
                    $cedulaSelect.append(`<option value="${cedula.id}" ${selected}>${cedula.name}</option>`);
                });
                $cedulaSelect.prop('disabled', false);
            }

            $('#expense_category_id').on('change', function () {
                refreshCedulaOptions(null);
            });

            refreshCedulaOptions('{{ old('budget_cedula_id') }}');

            // Sugerir Inventariable según el tipo de producto
            $('#product_type').on('change', function () {
                $('#is_inventoriable').prop('checked', $(this).val() === 'PRODUCTO');
            });
```

- [ ] **Step 5: Ejecutar el test y confirmar que pasa**

Run: `php artisan test --filter=ProductServiceCreateFormClassificationFieldsTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ProductServiceController.php resources/views/products_services/create.blade.php tests/Feature/ProductServiceCreateFormClassificationFieldsTest.php
git commit -m "feat: agregar seleccion de categoria/cedula de gasto e inventariable al formulario de nuevo producto"
```

---

### Task 5: Formulario `edit.blade.php` — mismos campos, precargados

**Files:**
- Modify: `app/Http/Controllers/ProductServiceController.php:257-289` (método `edit`)
- Modify: `resources/views/products_services/edit.blade.php`
- Test: `tests/Feature/ProductServiceEditFormClassificationFieldsTest.php`

**Interfaces:**
- Consumes: mismos scopes de Task 4 (`ExpenseCategory::active()`, cédulas activas), `$productService` ya inyectado por route model binding.
- Produces: la vista `products_services.edit` muestra los valores actuales del producto (`expense_category_id`, `budget_cedula_id`, `is_inventoriable`) ya seleccionados.

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

    public function test_edit_form_preselects_current_classification(): void
    {
        $user = User::factory()->create();
        $this->seed(RolePermissionSeeder::class);
        $user->assignRole('catalog_admin');

        $category = ExpenseCategory::factory()->create();
        $cedula = BudgetCedula::factory()->create(['expense_category_id' => $category->id, 'name' => 'Cedula Precargada']);
        $product = ProductService::factory()->create([
            'expense_category_id' => $category->id,
            'budget_cedula_id' => $cedula->id,
            'is_inventoriable' => true,
        ]);

        $response = $this->actingAs($user)->get(route('products-services.edit', $product));

        $response->assertOk();
        $response->assertSee('name="expense_category_id"', false);
        $response->assertSee('name="budget_cedula_id"', false);
        $response->assertSee('Cedula Precargada', false);
        $response->assertSee('checked', false);
    }
}
```

- [ ] **Step 2: Ejecutar el test y confirmar que falla**

Run: `php artisan test --filter=ProductServiceEditFormClassificationFieldsTest`
Expected: FAIL — campos no existen en la vista de edición.

- [ ] **Step 3: Cargar el catálogo en el controlador**

En `app/Http/Controllers/ProductServiceController.php`, dentro de `edit()`, mismo bloque que Task 4 Step 3 (agregar `$expenseCategories` y sumarlo al `compact(...)`).

- [ ] **Step 4: Agregar los campos en la vista**

En `resources/views/products_services/edit.blade.php`, mismo bloque HTML de Task 4 Step 4, adaptando los valores precargados:

```blade
                    <div class="row">
                        {{-- Categoría de Gasto --}}
                        <div class="col-md-6 mb-3">
                            <label for="expense_category_id" class="form-label">Categoría de Gasto</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="ti ti-receipt-2"></i>
                                </span>
                                <select class="form-select @error('expense_category_id') is-invalid @enderror"
                                        id="expense_category_id"
                                        name="expense_category_id">
                                    <option value="">Sin clasificar</option>
                                    @foreach ($expenseCategories as $category)
                                        <option value="{{ $category->id }}"
                                            {{ old('expense_category_id', $productService->expense_category_id) == $category->id ? 'selected' : '' }}>
                                            [{{ $category->code }}] {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @error('expense_category_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Cédula de Gasto --}}
                        <div class="col-md-6 mb-3">
                            <label for="budget_cedula_id" class="form-label">Cédula de Gasto</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="ti ti-file-text"></i>
                                </span>
                                <select class="form-select @error('budget_cedula_id') is-invalid @enderror"
                                        id="budget_cedula_id"
                                        name="budget_cedula_id">
                                    <option value="">Seleccione categoría primero...</option>
                                </select>
                            </div>
                            @error('budget_cedula_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Inventariable --}}
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="is_inventoriable" name="is_inventoriable" value="1"
                               {{ old('is_inventoriable', $productService->is_inventoriable) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_inventoriable">Inventariable</label>
                        <div class="form-text">Marca si este producto se controla por inventario. Se sugiere automáticamente según el tipo, pero puedes ajustarlo.</div>
                    </div>
```

Agregar el mismo `<script id="expense-cedulas-catalog">` embebido de Task 4 (antes de `@endsection`).

En el `@push('scripts')` de `edit.blade.php`, agregar el mismo bloque JS de Task 4 Step 4, con una diferencia: el cascade inicial debe respetar el valor actual del producto:

```javascript
            const cedulasByCategory = JSON.parse(document.getElementById('expense-cedulas-catalog').textContent);

            function refreshCedulaOptions(selectedCedulaId) {
                const categoryId = $('#expense_category_id').val();
                const $cedulaSelect = $('#budget_cedula_id');
                const cedulas = cedulasByCategory[categoryId] || [];

                $cedulaSelect.empty();

                if (!categoryId || cedulas.length === 0) {
                    $cedulaSelect.append('<option value="">Sin cédulas disponibles</option>').prop('disabled', true);
                    return;
                }

                $cedulaSelect.append('<option value="">Seleccione cédula...</option>');
                cedulas.forEach(function (cedula) {
                    const selected = String(cedula.id) === String(selectedCedulaId) ? 'selected' : '';
                    $cedulaSelect.append(`<option value="${cedula.id}" ${selected}>${cedula.name}</option>`);
                });
                $cedulaSelect.prop('disabled', false);
            }

            $('#expense_category_id').on('change', function () {
                refreshCedulaOptions(null);
            });

            refreshCedulaOptions('{{ old('budget_cedula_id', $productService->budget_cedula_id) }}');

            $('#product_type').on('change', function () {
                $('#is_inventoriable').prop('checked', $(this).val() === 'PRODUCTO');
            });
```

- [ ] **Step 5: Ejecutar el test y confirmar que pasa**

Run: `php artisan test --filter=ProductServiceEditFormClassificationFieldsTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ProductServiceController.php resources/views/products_services/edit.blade.php tests/Feature/ProductServiceEditFormClassificationFieldsTest.php
git commit -m "feat: precargar categoria/cedula de gasto e inventariable en el formulario de edicion"
```

---

### Task 6: Mostrar los campos en `show.blade.php`

**Files:**
- Modify: `app/Http/Controllers/ProductServiceController.php:240-252` (método `show`)
- Modify: `resources/views/products_services/show.blade.php`
- Test: `tests/Feature/ProductServiceShowClassificationFieldsTest.php`

**Interfaces:**
- Consumes: `$productService->load([..., 'expenseCategory', 'budgetCedula'])`.
- Produces: la vista de detalle muestra categoría de gasto, cédula e "Inventariable" de solo lectura.

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

    public function test_show_page_displays_expense_category_cedula_and_inventoriable(): void
    {
        $user = User::factory()->create();
        $this->seed(RolePermissionSeeder::class);
        $user->assignRole('catalog_admin');

        $category = ExpenseCategory::factory()->create(['name' => 'Mantenimiento']);
        $cedula = BudgetCedula::factory()->create(['expense_category_id' => $category->id, 'name' => 'Cedula Visible']);
        $product = ProductService::factory()->create([
            'expense_category_id' => $category->id,
            'budget_cedula_id' => $cedula->id,
            'is_inventoriable' => true,
        ]);

        $response = $this->actingAs($user)->get(route('products-services.show', $product));

        $response->assertOk();
        $response->assertSee('Mantenimiento');
        $response->assertSee('Cedula Visible');
        $response->assertSee('Inventariable');
    }

    public function test_show_page_handles_product_without_classification(): void
    {
        $user = User::factory()->create();
        $this->seed(RolePermissionSeeder::class);
        $user->assignRole('catalog_admin');

        $product = ProductService::factory()->create([
            'expense_category_id' => null,
            'budget_cedula_id' => null,
        ]);

        $response = $this->actingAs($user)->get(route('products-services.show', $product));

        $response->assertOk();
    }
}
```

- [ ] **Step 2: Ejecutar el test y confirmar que falla**

Run: `php artisan test --filter=ProductServiceShowClassificationFieldsTest`
Expected: FAIL — `test_show_page_displays_expense_category_cedula_and_inventoriable` falla porque la vista no muestra "Mantenimiento"/"Cedula Visible"/"Inventariable" en ningún lado nuevo.

- [ ] **Step 3: Cargar las relaciones en el controlador**

En `app/Http/Controllers/ProductServiceController.php`, dentro de `show()`, agregar `'expenseCategory'` y `'budgetCedula'` al array de `$productService->load([...])` ya existente:

```php
        $productService->load([
            'category',
            'costCenter',
            'company',
            'defaultVendor',
            'creator',
            'approver',
            'expenseCategory',
            'budgetCedula',
        ]);
```

- [ ] **Step 4: Agregar la sección en la vista**

En `resources/views/products_services/show.blade.php`, dentro de la card "Estructura Contable" (después del bloque `@else` de la alerta de estructura incompleta, antes del cierre `</div>` del `card-body`), agregar:

```blade
                    <hr>

                    <div class="row mb-2">
                        <div class="col-5">
                            <strong>Categoría de Gasto:</strong>
                        </div>
                        <div class="col-7">
                            {{ $productService->expenseCategory ? "[{$productService->expenseCategory->code}] {$productService->expenseCategory->name}" : 'Sin clasificar' }}
                        </div>
                    </div>

                    <div class="row mb-2">
                        <div class="col-5">
                            <strong>Cédula de Gasto:</strong>
                        </div>
                        <div class="col-7">
                            {{ $productService->budgetCedula->name ?? 'Sin clasificar' }}
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-5">
                            <strong>Inventariable:</strong>
                        </div>
                        <div class="col-7">
                            @if ($productService->is_inventoriable)
                                <span class="badge bg-success">Sí</span>
                            @else
                                <span class="badge bg-secondary">No</span>
                            @endif
                        </div>
                    </div>
```

- [ ] **Step 5: Ejecutar el test y confirmar que pasa**

Run: `php artisan test --filter=ProductServiceShowClassificationFieldsTest`
Expected: PASS

- [ ] **Step 6: Ejecutar toda la suite de tests afectada para descartar regresiones**

Run: `php artisan test --filter=ProductService`
Expected: PASS (todos los tests de `ProductService*Test`, incluidos `ProductServiceForRequisitionApiTest` y `ProductServiceSycSeederTest` preexistentes)

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/ProductServiceController.php resources/views/products_services/show.blade.php tests/Feature/ProductServiceShowClassificationFieldsTest.php
git commit -m "feat: mostrar categoria de gasto, cedula e inventariable en el detalle de producto"
```

---

## Verificación final (manual, tras completar las 6 tareas)

- Correr toda la suite: `php artisan test` — debe pasar sin regresiones (prestar atención a `CostCenterCrudTest`, `ProductServiceForRequisitionApiTest`, `ExpenseCategoryByCostCenterTest`, y los tests de `ExpenseCedulaCatalogController` si existen).
- En el navegador: crear un producto nuevo, elegir una categoría de gasto, confirmar que el select de cédula se habilita y filtra correctamente; guardar y verificar en el detalle. Editar ese mismo producto, confirmar que ambos selects llegan preseleccionados. Cambiar `product_type` de PRODUCTO a SERVICIO y confirmar que el checkbox "Inventariable" se desmarca automáticamente (y viceversa), pero que se puede sobreescribir a mano.
