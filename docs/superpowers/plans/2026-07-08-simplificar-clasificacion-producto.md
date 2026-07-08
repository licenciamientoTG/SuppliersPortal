# Simplificar Clasificación de Productos/Servicios — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove the unused `subcategory` column/field and the read-only `category_display` field from the product/service catalog forms, and replace the two-step "Categoría de Gasto → Cédula de Gasto" selection with a single multi-select listing all budget cédulas prefixed with their category code (e.g. `MAT - Papelería y oficina`).

**Architecture:** Laravel 12 MVC app. A migration drops `products_services.subcategory`. `ProductServiceController` stops reading `subcategory`/`expense_category_ids` from requests; when saving, it derives the product's expense categories automatically from the `expense_category_id` of the selected budget cédulas via `BudgetCedula::whereIn(...)->pluck('expense_category_id')`. The two Blade forms (`create.blade.php`, `edit.blade.php`) drop the removed fields and render one server-side `<select multiple>` of cédulas instead of the old JS-driven cascading selects.

**Tech Stack:** Laravel 12, PHP 8.2, Blade, jQuery + Select2, PHPUnit (`php artisan test`), SQLite in-memory for tests.

## Global Constraints

- Test command: `php artisan test --filter=<TestClassName>` for a single file, `php artisan test --filter=ProductService` to run the whole product/service-related suite.
- Follow existing code style: controllers use fully-qualified class names inline (e.g. `\App\Models\BudgetCedula::...`) rather than adding `use` imports, when that's the existing local pattern.
- Every step that changes a Blade view or PHP file must be followed by running the affected tests before moving on — do not batch multiple tasks before testing.
- Do not touch `category_id` / the cost-center-derived Category system — out of scope (confirmed in the design doc).

---

### Task 1: Drop `subcategory` from the database and all backend code paths

**Files:**
- Create: `database/migrations/2026_07_08_120000_remove_subcategory_from_products_services_table.php`
- Modify: `app/Models/ProductService.php`
- Modify: `app/Http/Requests/SaveProductServiceRequest.php`
- Modify: `app/Http/Controllers/ProductServiceController.php`
- Modify: `database/seeders/ProductServiceSycSeeder.php`
- Modify: `tests/Feature/ProductServiceSycSeederTest.php`
- Test: `tests/Feature/ProductServiceSubcategoryColumnRemovedTest.php` (new)

**Interfaces:**
- Produces: `products_services` table with no `subcategory` column (relied on by Task 2).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ProductServiceSubcategoryColumnRemovedTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductServiceSubcategoryColumnRemovedTest extends TestCase
{
    use RefreshDatabase;

    public function test_subcategory_column_no_longer_exists(): void
    {
        $this->assertFalse(Schema::hasColumn('products_services', 'subcategory'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ProductServiceSubcategoryColumnRemovedTest`
Expected: FAIL — `Failed asserting that true is false.` (the column still exists)

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_07_08_120000_remove_subcategory_from_products_services_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products_services', function (Blueprint $table) {
            $table->dropIndex(['category_id', 'subcategory']);
            $table->dropColumn('subcategory');
        });
    }

    public function down(): void
    {
        Schema::table('products_services', function (Blueprint $table) {
            $table->string('subcategory', 100)->nullable()->after('category_id')
                ->comment('Subcategoría específica');
            $table->index(['category_id', 'subcategory']);
        });
    }
};
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ProductServiceSubcategoryColumnRemovedTest`
Expected: PASS (RefreshDatabase runs all migrations, including the new one)

- [ ] **Step 5: Remove `subcategory` from `ProductService::$fillable`**

In `app/Models/ProductService.php`, in the `$fillable` array, delete this line (it sits right after `'category_id',` under the `// Clasificación` comment):

```php
        'subcategory',
```

- [ ] **Step 6: Remove `subcategory` from `SaveProductServiceRequest`**

In `app/Http/Requests/SaveProductServiceRequest.php`, in `rules()`, delete:

```php
            'subcategory' => 'nullable|string|max:100',
```

In `attributes()`, delete:

```php
            'subcategory' => 'subcategoría',
```

- [ ] **Step 7: Remove `subcategory` from `ProductServiceController::store()` and `::update()`**

In `app/Http/Controllers/ProductServiceController.php`, in both `store()` and `update()`, delete this line from the `fill([...])` array (it's right after `'category_id' => $costCenter->category_id,`):

```php
                'subcategory' => $data['subcategory'] ?? null,
```

- [ ] **Step 8: Remove `subcategory` from `storeFromRequisition()`**

In the same controller, in `storeFromRequisition()`, delete from the `$request->validate([...])` call:

```php
            'subcategory' => 'nullable|string|max:100',
```

And delete from the `fill([...])` array:

```php
                'subcategory' => $validated['subcategory'] ?? null,
```

- [ ] **Step 9: Remove `subcategory` from the SYC seeder**

In `database/seeders/ProductServiceSycSeeder.php`, delete this line from the `ProductService::create([...])` array:

```php
                    'subcategory' => null,
```

- [ ] **Step 10: Update the seeder test to stop asserting `subcategory`**

In `tests/Feature/ProductServiceSycSeederTest.php`, in the `assertDatabaseHas` array (around line 88), delete:

```php
            'subcategory' => null,
```

- [ ] **Step 11: Run the full product/service test suite**

Run: `php artisan test --filter=ProductService`
Expected: PASS — all `ProductService*` test files green (this includes `ProductServiceSycSeederTest`, `ProductServiceExpenseClassificationPersistenceTest`, `ProductServiceExpenseClassificationTest`, `ProductServiceShowClassificationFieldsTest`, `ProductServiceForRequisitionApiTest`, and the new `ProductServiceSubcategoryColumnRemovedTest`). Note: `ProductServiceCreateFormClassificationFieldsTest`, `ProductServiceEditFormClassificationFieldsTest`, and `ProductServiceExpenseClassificationValidationTest` are addressed in Tasks 2–3; if they fail here for reasons unrelated to `subcategory`, that's expected and will be fixed in those tasks. If they fail because of `subcategory`, something was missed in this task.

- [ ] **Step 12: Commit**

```bash
git add database/migrations/2026_07_08_120000_remove_subcategory_from_products_services_table.php \
        app/Models/ProductService.php \
        app/Http/Requests/SaveProductServiceRequest.php \
        app/Http/Controllers/ProductServiceController.php \
        database/seeders/ProductServiceSycSeeder.php \
        tests/Feature/ProductServiceSycSeederTest.php \
        tests/Feature/ProductServiceSubcategoryColumnRemovedTest.php
git commit -m "refactor: eliminar columna subcategory de products_services"
```

---

### Task 2: Remove `category_display` and `subcategory` fields from the views

**Files:**
- Modify: `resources/views/products_services/create.blade.php`
- Modify: `resources/views/products_services/edit.blade.php`
- Modify: `resources/views/products_services/show.blade.php`
- Modify: `resources/views/requisitions/_request_product_modal.blade.php`
- Modify: `app/Http/Controllers/ProductServiceController.php` (drop now-unused `category` eager load on cost centers)
- Test: `tests/Feature/ProductServiceFormSubcategoryFieldsRemovedTest.php` (new)

**Interfaces:**
- Consumes: nothing new from Task 1 beyond "the app no longer writes `subcategory`" (already true).
- Produces: nothing consumed by later tasks (this task is UI-only cleanup, independent of Task 3).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ProductServiceFormSubcategoryFieldsRemovedTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\ProductService;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductServiceFormSubcategoryFieldsRemovedTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_does_not_show_subcategory_or_category_display_fields(): void
    {
        $user = User::factory()->create();
        $this->seed(RolePermissionSeeder::class);
        $user->assignRole('catalog_admin');

        $response = $this->actingAs($user)->get(route('products-services.create'));

        $response->assertOk();
        $response->assertDontSee('name="subcategory"', false);
        $response->assertDontSee('id="category_display"', false);
    }

    public function test_edit_form_does_not_show_subcategory_or_category_display_fields(): void
    {
        $user = User::factory()->create();
        $this->seed(RolePermissionSeeder::class);
        $user->assignRole('catalog_admin');

        $product = ProductService::factory()->create();

        $response = $this->actingAs($user)->get(route('products-services.edit', $product));

        $response->assertOk();
        $response->assertDontSee('name="subcategory"', false);
        $response->assertDontSee('id="category_display"', false);
    }

    public function test_request_product_modal_does_not_include_subcategory_field(): void
    {
        $html = view('requisitions._request_product_modal')->render();

        $this->assertStringNotContainsString('name="subcategory"', $html);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ProductServiceFormSubcategoryFieldsRemovedTest`
Expected: FAIL — the create/edit forms and the modal still contain `subcategory` and `category_display`.

- [ ] **Step 3: Remove the "Categoría derivada" + "Subcategoría" row from `create.blade.php`**

In `resources/views/products_services/create.blade.php`, delete this entire block (it comes right after the closing `</div>` of the "Tipo / Compañía / Centro de Costo" row, and right before the "Categorías de Gasto / Cédulas de Gasto" row):

```blade
                    <div class="row">
                        {{-- Categoría derivada --}}
                        <div class="col-md-6 mb-3">
                            <label for="category_display" class="form-label">Categoría asignada</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="ti ti-tag"></i>
                                </span>
                                <input type="text"
                                        class="form-control"
                                        id="category_display"
                                        value="Se asigna automáticamente según el centro de costo"
                                        readonly>
                            </div>
                            <div class="form-text">Este dato ya no se captura manualmente 1.</div>
                        </div>

                        {{-- Subcategoría --}}
                        <div class="col-md-6 mb-3">
                            <label for="subcategory" class="form-label">Subcategoría</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="ti ti-tags"></i>
                                </span>
                                <input type="text" 
                                        class="form-control @error('subcategory') is-invalid @enderror"
                                        id="subcategory" 
                                        name="subcategory" 
                                        value="{{ old('subcategory') }}" 
                                        maxlength="100"
                                        placeholder="Ej: Material de oficina">
                            </div>
                            @error('subcategory')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

```

- [ ] **Step 4: Remove the same block from `edit.blade.php`**

In `resources/views/products_services/edit.blade.php`, delete (note this version has different `value=` bindings than create):

```blade
                    <div class="row">
                        {{-- Categoría derivada --}}
                        <div class="col-md-6 mb-3">
                            <label for="category_display" class="form-label">Categoría asignada</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="ti ti-tag"></i>
                                </span>
                                <input type="text"
                                        class="form-control"
                                        id="category_display"
                                        value="{{ $productService->costCenter?->category?->name ?? 'Sin categoría configurada en el centro de costo' }}"
                                        readonly>
                            </div>
                            <div class="form-text">Este dato ya no se captura manualmente 2.</div>
                        </div>

                        {{-- Subcategoría --}}
                        <div class="col-md-6 mb-3">
                            <label for="subcategory" class="form-label">Subcategoría</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="ti ti-tags"></i>
                                </span>
                                <input type="text" 
                                        class="form-control @error('subcategory') is-invalid @enderror"
                                        id="subcategory" 
                                        name="subcategory" 
                                        value="{{ old('subcategory') ?? $productService->subcategory }}" 
                                        maxlength="100"
                                        placeholder="Ej: Material de oficina">
                            </div>
                            @error('subcategory')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

```

- [ ] **Step 5: Remove the dead `updateCategoryDisplay` JS and `data-category-name` attribute from `create.blade.php`**

In the `@push('scripts')` section of `create.blade.php`, replace:

```javascript
            // Filtrar centros de costo por compañía seleccionada
            $('#company_id').on('change', function() {
                const companyId = $(this).val();
                const $costCenterSelect = $('#cost_center_id');

                $costCenterSelect.find('option').each(function() {
                    const optionCompanyId = $(this).data('company-id');
                    if (!optionCompanyId || optionCompanyId == companyId) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });

                const currentCostCenter = $costCenterSelect.val();
                const currentOption = $costCenterSelect.find(`option[value="${currentCostCenter}"]`);
                if (currentOption.data('company-id') != companyId) {
                    $costCenterSelect.val('');
                }

                updateCategoryDisplay();
            });

            $('#cost_center_id').on('change', updateCategoryDisplay);

            function updateCategoryDisplay() {
                const categoryName = $('#cost_center_id option:selected').data('category-name');
                $('#category_display').val(categoryName || 'Sin categoría configurada en el centro de costo');
            }

            // Ejecutar al cargar si hay compañía pre-seleccionada
            if ($('#company_id').val()) {
                $('#company_id').trigger('change');
            } else {
                updateCategoryDisplay();
            }
```

with:

```javascript
            // Filtrar centros de costo por compañía seleccionada
            $('#company_id').on('change', function() {
                const companyId = $(this).val();
                const $costCenterSelect = $('#cost_center_id');

                $costCenterSelect.find('option').each(function() {
                    const optionCompanyId = $(this).data('company-id');
                    if (!optionCompanyId || optionCompanyId == companyId) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });

                const currentCostCenter = $costCenterSelect.val();
                const currentOption = $costCenterSelect.find(`option[value="${currentCostCenter}"]`);
                if (currentOption.data('company-id') != companyId) {
                    $costCenterSelect.val('');
                }
            });

            // Ejecutar al cargar si hay compañía pre-seleccionada
            if ($('#company_id').val()) {
                $('#company_id').trigger('change');
            }
```

Then, in the "Centro de Costo" `<select>` in the same file, remove the now-unused attribute from the `<option>` tag:

```blade
                                    <option value="{{ $cc->id }}" 
                                            data-company-id="{{ $cc->company_id }}"
                                            data-category-name="{{ $cc->category?->name ?? '' }}"
                                            {{ old('cost_center_id', $productService->cost_center_id) == $cc->id ? 'selected' : '' }}>
```

becomes:

```blade
                                    <option value="{{ $cc->id }}" 
                                            data-company-id="{{ $cc->company_id }}"
                                            {{ old('cost_center_id', $productService->cost_center_id) == $cc->id ? 'selected' : '' }}>
```

- [ ] **Step 6: Apply the same two edits to `edit.blade.php`**

In the `@push('scripts')` section of `resources/views/products_services/edit.blade.php`, replace:

```javascript
            // Filtrar centros de costo por compañía seleccionada
            $('#company_id').on('change', function() {
                const companyId = $(this).val();
                const $costCenterSelect = $('#cost_center_id');

                $costCenterSelect.find('option').each(function() {
                    const optionCompanyId = $(this).data('company-id');
                    if (!optionCompanyId || optionCompanyId == companyId) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });

                const currentCostCenter = $costCenterSelect.val();
                const currentOption = $costCenterSelect.find(`option[value="${currentCostCenter}"]`);
                if (currentOption.data('company-id') != companyId) {
                    $costCenterSelect.val('');
                }

                updateCategoryDisplay();
            });

            $('#cost_center_id').on('change', updateCategoryDisplay);

            function updateCategoryDisplay() {
                const categoryName = $('#cost_center_id option:selected').data('category-name');
                $('#category_display').val(categoryName || 'Sin categoría configurada en el centro de costo');
            }

            // Ejecutar al cargar si hay compañía pre-seleccionada
            if ($('#company_id').val()) {
                $('#company_id').trigger('change');
            } else {
                updateCategoryDisplay();
            }
```

with:

```javascript
            // Filtrar centros de costo por compañía seleccionada
            $('#company_id').on('change', function() {
                const companyId = $(this).val();
                const $costCenterSelect = $('#cost_center_id');

                $costCenterSelect.find('option').each(function() {
                    const optionCompanyId = $(this).data('company-id');
                    if (!optionCompanyId || optionCompanyId == companyId) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });

                const currentCostCenter = $costCenterSelect.val();
                const currentOption = $costCenterSelect.find(`option[value="${currentCostCenter}"]`);
                if (currentOption.data('company-id') != companyId) {
                    $costCenterSelect.val('');
                }
            });

            // Ejecutar al cargar si hay compañía pre-seleccionada
            if ($('#company_id').val()) {
                $('#company_id').trigger('change');
            }
```

Then, in the same file's "Centro de Costo" `<select>`, remove the now-unused attribute from the `<option>` tag:

```blade
                                    <option value="{{ $cc->id }}" 
                                            data-company-id="{{ $cc->company_id }}"
                                            data-category-name="{{ $cc->category?->name ?? '' }}"
                                            {{ old('cost_center_id', $productService->cost_center_id) == $cc->id ? 'selected' : '' }}>
```

becomes:

```blade
                                    <option value="{{ $cc->id }}" 
                                            data-company-id="{{ $cc->company_id }}"
                                            {{ old('cost_center_id', $productService->cost_center_id) == $cc->id ? 'selected' : '' }}>
```

- [ ] **Step 7: Stop eager-loading the unused `category` relation on cost centers**

In `app/Http/Controllers/ProductServiceController.php`, in both `create()` and `edit()`, change:

```php
        $costCenters = CostCenter::with('category:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'company_id', 'status', 'category_id']);
```

to:

```php
        $costCenters = CostCenter::orderBy('name')
            ->get(['id', 'name', 'code', 'company_id', 'status']);
```

- [ ] **Step 8: Remove the subcategory line from `show.blade.php`**

In `resources/views/products_services/show.blade.php`, change:

```blade
                        <div class="col-md-9">
                            {{ $productService->category?->name ?? '—' }}
                            @if ($productService->subcategory)
                                / <span class="text-muted">{{ $productService->subcategory }}</span>
                            @endif
                        </div>
```

to:

```blade
                        <div class="col-md-9">
                            {{ $productService->category?->name ?? '—' }}
                        </div>
```

- [ ] **Step 9: Remove the subcategory field from the "solicitar producto" modal**

In `resources/views/requisitions/_request_product_modal.blade.php`, delete:

```blade
                    <div class="mb-3">
                        <label for="modal_subcategory" class="form-label">
                            Subcategoría <small class="text-muted">(opcional)</small>
                        </label>
                        <input type="text" class="form-control" id="modal_subcategory" name="subcategory"
                            maxlength="100" placeholder="Ej: Material de oficina, Servicios de limpieza...">
                    </div>

```

- [ ] **Step 10: Run test to verify it passes**

Run: `php artisan test --filter=ProductServiceFormSubcategoryFieldsRemovedTest`
Expected: PASS

- [ ] **Step 11: Run the full product/service test suite to check for regressions**

Run: `php artisan test --filter=ProductService`
Expected: PASS (same caveat as Task 1 Step 11 regarding the tests handled in Task 3)

- [ ] **Step 12: Commit**

```bash
git add resources/views/products_services/create.blade.php \
        resources/views/products_services/edit.blade.php \
        resources/views/products_services/show.blade.php \
        resources/views/requisitions/_request_product_modal.blade.php \
        app/Http/Controllers/ProductServiceController.php \
        tests/Feature/ProductServiceFormSubcategoryFieldsRemovedTest.php
git commit -m "refactor: quitar campos category_display y subcategoría de los formularios de producto"
```

---

### Task 3: Replace the category→cédula cascade with a single cédula select

**Files:**
- Modify: `app/Http/Requests/SaveProductServiceRequest.php`
- Modify: `app/Http/Controllers/ProductServiceController.php`
- Modify: `resources/views/products_services/create.blade.php`
- Modify: `resources/views/products_services/edit.blade.php`
- Modify: `tests/Feature/ProductServiceCreateFormClassificationFieldsTest.php`
- Modify: `tests/Feature/ProductServiceEditFormClassificationFieldsTest.php`
- Delete: `tests/Feature/ProductServiceExpenseClassificationValidationTest.php`

**Interfaces:**
- Consumes: `BudgetCedula` model (`expenseCategory()` relation, `active()`/`notDeleted()` scopes) — already exists.
- Produces: nothing consumed by later tasks (final task in this plan).

- [ ] **Step 1: Update the create-form test to expect the new single select**

Replace the full contents of `tests/Feature/ProductServiceCreateFormClassificationFieldsTest.php`:

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

    public function test_create_form_shows_single_cedula_select_with_category_prefix(): void
    {
        $user = User::factory()->create();
        $this->seed(RolePermissionSeeder::class);
        $user->assignRole('catalog_admin');

        $category = ExpenseCategory::factory()->create(['code' => 'MAN', 'name' => 'Mantenimiento']);
        BudgetCedula::factory()->create(['expense_category_id' => $category->id, 'name' => 'Mantenimiento de Estaciones']);

        $response = $this->actingAs($user)->get(route('products-services.create'));

        $response->assertOk();
        $response->assertDontSee('name="expense_category_ids[]"', false);
        $response->assertSee('name="budget_cedula_ids[]"', false);
        $response->assertSee('MAN - Mantenimiento de Estaciones', false);
    }
}
```

- [ ] **Step 2: Update the edit-form test to expect the new single select**

Replace the full contents of `tests/Feature/ProductServiceEditFormClassificationFieldsTest.php`:

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

    public function test_edit_form_preselects_current_cedulas(): void
    {
        $user = User::factory()->create();
        $this->seed(RolePermissionSeeder::class);
        $user->assignRole('catalog_admin');

        $categoryA = ExpenseCategory::factory()->create(['code' => 'MNT']);
        $categoryB = ExpenseCategory::factory()->create(['code' => 'TEC']);
        $cedulaA = BudgetCedula::factory()->create(['expense_category_id' => $categoryA->id, 'name' => 'Cedula Precargada A']);
        $cedulaB = BudgetCedula::factory()->create(['expense_category_id' => $categoryB->id, 'name' => 'Cedula Precargada B']);

        $product = ProductService::factory()->create();
        $product->expenseCategories()->sync([$categoryA->id, $categoryB->id]);
        $product->budgetCedulas()->sync([$cedulaA->id, $cedulaB->id]);

        $response = $this->actingAs($user)->get(route('products-services.edit', $product));
        $content = $response->getContent();

        $response->assertOk();
        $response->assertDontSee('name="expense_category_ids[]"', false);
        $response->assertSee('MNT - Cedula Precargada A', false);
        $response->assertSee('TEC - Cedula Precargada B', false);
        $this->assertMatchesRegularExpression(
            '/<option value="' . $cedulaA->id . '"\s+selected/',
            $content
        );
        $this->assertMatchesRegularExpression(
            '/<option value="' . $cedulaB->id . '"\s+selected/',
            $content
        );
    }
}
```

- [ ] **Step 3: Delete the obsolete cross-category validation test**

The cédula-must-belong-to-a-selected-category rule is being removed by design (there's no manual category selection anymore), so this whole file's premise is gone:

```bash
rm tests/Feature/ProductServiceExpenseClassificationValidationTest.php
```

- [ ] **Step 4: Run the updated tests to verify they fail**

Run: `php artisan test --filter=ProductServiceCreateFormClassificationFieldsTest`
Run: `php artisan test --filter=ProductServiceEditFormClassificationFieldsTest`
Expected: Both FAIL — the views still render the old two-select cascade, so `budget_cedula_ids[]` isn't a plain select with server-rendered `CODE - Name` options yet, and `expense_category_ids[]` is still present.

- [ ] **Step 5: Remove `expense_category_ids` validation from `SaveProductServiceRequest`**

In `app/Http/Requests/SaveProductServiceRequest.php`, delete from `rules()`:

```php
            'expense_category_ids' => 'nullable|array',
            'expense_category_ids.*' => 'integer|exists:expense_categories,id',
```

Delete from `attributes()`:

```php
            'expense_category_ids' => 'categorías de gasto',
            'expense_category_ids.*' => 'categoría de gasto',
```

In `withValidator()`, delete the cross-category check (keep the accounting-structure check above it untouched):

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

- [ ] **Step 6: Load `$budgetCedulas` instead of `$expenseCategories` in `create()`**

In `app/Http/Controllers/ProductServiceController.php`, in `create()`, replace:

```php
        $expenseCategories = \App\Models\ExpenseCategory::active()
            ->with(['cedulas' => fn ($q) => $q->active()->notDeleted()->orderBy('name')])
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return view('products_services.create', compact(
            'productService',
            'companies',
            'costCenters',
            'suppliers',
            'statusOpts',
            'selectedCompanyId',
            'unitsOfMeasure',
            'expenseCategories'
        ));
```

with:

```php
        $budgetCedulas = \App\Models\BudgetCedula::active()->notDeleted()
            ->whereHas('expenseCategory', fn ($q) => $q->active())
            ->with('expenseCategory:id,code,name')
            ->get()
            ->sortBy([['expenseCategory.code', 'asc'], ['name', 'asc']])
            ->values();

        return view('products_services.create', compact(
            'productService',
            'companies',
            'costCenters',
            'suppliers',
            'statusOpts',
            'selectedCompanyId',
            'unitsOfMeasure',
            'budgetCedulas'
        ));
```

- [ ] **Step 7: Load `$budgetCedulas` instead of `$expenseCategories` in `edit()`**

In the same controller, in `edit()`, replace:

```php
        $expenseCategories = \App\Models\ExpenseCategory::active()
            ->with(['cedulas' => fn ($q) => $q->active()->notDeleted()->orderBy('name')])
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $selectedExpenseCategoryIds = $productService->expenseCategories->pluck('id')->all();
        $selectedBudgetCedulaIds = $productService->budgetCedulas->pluck('id')->all();

        return view('products_services.edit', compact(
            'productService',
            'companies',
            'costCenters',
            'suppliers',
            'statusOpts',
            'selectedCompanyId',
            'unitsOfMeasure',
            'expenseCategories',
            'selectedExpenseCategoryIds',
            'selectedBudgetCedulaIds'
        ));
```

with:

```php
        $budgetCedulas = \App\Models\BudgetCedula::active()->notDeleted()
            ->whereHas('expenseCategory', fn ($q) => $q->active())
            ->with('expenseCategory:id,code,name')
            ->get()
            ->sortBy([['expenseCategory.code', 'asc'], ['name', 'asc']])
            ->values();

        $selectedBudgetCedulaIds = $productService->budgetCedulas->pluck('id')->all();

        return view('products_services.edit', compact(
            'productService',
            'companies',
            'costCenters',
            'suppliers',
            'statusOpts',
            'selectedCompanyId',
            'unitsOfMeasure',
            'budgetCedulas',
            'selectedBudgetCedulaIds'
        ));
```

- [ ] **Step 8: Derive expense categories from selected cédulas in `store()`**

In the same controller, in `store()`, replace:

```php
            $productService->expenseCategories()->sync($data['expense_category_ids'] ?? []);
            $productService->budgetCedulas()->sync($data['budget_cedula_ids'] ?? []);
```

with:

```php
            $budgetCedulaIds = $data['budget_cedula_ids'] ?? [];
            $productService->budgetCedulas()->sync($budgetCedulaIds);

            $expenseCategoryIds = \App\Models\BudgetCedula::whereIn('id', $budgetCedulaIds)
                ->pluck('expense_category_id')
                ->unique();
            $productService->expenseCategories()->sync($expenseCategoryIds);
```

- [ ] **Step 9: Apply the same change to `update()`**

Same replacement as Step 8, in `update()`.

- [ ] **Step 10: Replace the two-select block with one select in `create.blade.php`**

In `resources/views/products_services/create.blade.php`, replace:

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

with:

```blade
                    <div class="row">
                        {{-- Cédulas de Gasto --}}
                        <div class="col-md-12 mb-3">
                            <label for="budget_cedula_ids" class="form-label">Cédulas de Gasto</label>
                            <select class="form-select @error('budget_cedula_ids') is-invalid @enderror"
                                    id="budget_cedula_ids"
                                    name="budget_cedula_ids[]"
                                    multiple
                                    style="width: 100%;">
                                @foreach ($budgetCedulas as $cedula)
                                    <option value="{{ $cedula->id }}"
                                        {{ collect(old('budget_cedula_ids', []))->contains($cedula->id) ? 'selected' : '' }}>
                                        {{ $cedula->expenseCategory->code }} - {{ $cedula->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('budget_cedula_ids')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
```

- [ ] **Step 11: Replace the two-select block with one select in `edit.blade.php`**

Same as Step 10, but the option's `selected` check defaults to `$selectedBudgetCedulaIds` instead of `[]`:

```blade
                    <div class="row">
                        {{-- Cédulas de Gasto --}}
                        <div class="col-md-12 mb-3">
                            <label for="budget_cedula_ids" class="form-label">Cédulas de Gasto</label>
                            <select class="form-select @error('budget_cedula_ids') is-invalid @enderror"
                                    id="budget_cedula_ids"
                                    name="budget_cedula_ids[]"
                                    multiple
                                    style="width: 100%;">
                                @foreach ($budgetCedulas as $cedula)
                                    <option value="{{ $cedula->id }}"
                                        {{ collect(old('budget_cedula_ids', $selectedBudgetCedulaIds))->contains($cedula->id) ? 'selected' : '' }}>
                                        {{ $cedula->expenseCategory->code }} - {{ $cedula->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('budget_cedula_ids')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
```

- [ ] **Step 12: Remove the embedded JSON catalog script tag from both views**

In `create.blade.php`, delete (it sits right before `@endsection`):

```blade
    <script id="expense-cedulas-catalog" type="application/json">
        {!! json_encode($expenseCategories->pluck('cedulas', 'id')->map(fn ($cedulas) => $cedulas->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}
    </script>
```

Do the same in `edit.blade.php` (identical block).

- [ ] **Step 13: Replace the cascading JS with a plain Select2 init in `create.blade.php`**

In the `@push('scripts')` section, replace:

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

with:

```javascript
            // Cédulas de Gasto (select2 simple, sin cascada de categorías)
            $('#budget_cedula_ids').select2({
                theme: 'bootstrap-5',
                placeholder: 'Seleccione cédulas...',
                allowClear: true,
                closeOnSelect: false,
            });
```

- [ ] **Step 14: Apply the same JS replacement to `edit.blade.php`**

In the `@push('scripts')` section of `resources/views/products_services/edit.blade.php`, replace:

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

            refreshCedulaOptions(@json(old('budget_cedula_ids', $selectedBudgetCedulaIds)));
```

with:

```javascript
            // Cédulas de Gasto (select2 simple, sin cascada de categorías)
            $('#budget_cedula_ids').select2({
                theme: 'bootstrap-5',
                placeholder: 'Seleccione cédulas...',
                allowClear: true,
                closeOnSelect: false,
            });
```

- [ ] **Step 15: Run the updated tests to verify they pass**

Run: `php artisan test --filter=ProductServiceCreateFormClassificationFieldsTest`
Run: `php artisan test --filter=ProductServiceEditFormClassificationFieldsTest`
Expected: Both PASS

- [ ] **Step 16: Run the full product/service test suite**

Run: `php artisan test --filter=ProductService`
Expected: PASS — including `ProductServiceExpenseClassificationPersistenceTest` (its assertions already match the auto-derivation behavior: it sends matching `expense_category_ids`/`budget_cedula_ids` pairs, and the controller now derives the same categories from the cédulas regardless of the now-ignored `expense_category_ids` input), `ProductServiceExpenseClassificationTest`, `ProductServiceShowClassificationFieldsTest`, `ExpenseCategoryCedulaProductServiceGuardTest`, and `ProductServiceForRequisitionApiTest`.

- [ ] **Step 17: Run the whole test suite as a final sanity check**

Run: `php artisan test`
Expected: PASS (no regressions anywhere else in the app)

- [ ] **Step 18: Commit**

```bash
git add app/Http/Requests/SaveProductServiceRequest.php \
        app/Http/Controllers/ProductServiceController.php \
        resources/views/products_services/create.blade.php \
        resources/views/products_services/edit.blade.php \
        tests/Feature/ProductServiceCreateFormClassificationFieldsTest.php \
        tests/Feature/ProductServiceEditFormClassificationFieldsTest.php
git rm tests/Feature/ProductServiceExpenseClassificationValidationTest.php
git commit -m "feat: reemplazar selección categoría+cédula por un solo select de cédulas con prefijo de categoría"
```
