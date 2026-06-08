# Centro de Costo por Partida — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mover `cost_center_id` de la cabecera de requisiciones y ODCs al nivel de cada partida, permitiendo que cada ítem se cargue a un centro de costos diferente.

**Architecture:** Se editan las 4 migraciones originales directamente y se ejecuta `migrate:fresh`. Los modelos eliminan/agregan la relación CC según corresponda. El `BudgetAllocationService` pasa a leer el CC de cada ítem en lugar del header. La UI del formulario de requisición mueve el selector de CC y tipo de compra al modal de partidas, con cascada puramente JS.

**Tech Stack:** Laravel 10+, PHPUnit (`RefreshDatabase`), Livewire 3, jQuery, Select2, SweetAlert2, Bootstrap 5 (Tabler), SQL Server (producción) / SQLite (tests).

---

## File Map

| Archivo | Acción |
|---|---|
| `database/migrations/2025_10_15_112438_create_requisitions_table.php` | Editar — eliminar `cost_center_id` |
| `database/migrations/2025_10_15_112442_create_requisition_items_table.php` | Editar — agregar `cost_center_id` |
| `database/migrations/2026_01_28_224431_create_odc_direct_purchase_orders_table.php` | Editar — eliminar `cost_center_id` |
| `database/migrations/2026_01_28_224435_create_odc_direct_purchase_order_items_table.php` | Editar — agregar `cost_center_id` |
| `app/Models/RequisitionItem.php` | Agregar `cost_center_id` en fillable, relación, isValid, getSummary |
| `app/Models/Requisition.php` | Eliminar `cost_center_id`, `costCenter()`, `scopeByCostCenter()` |
| `app/Models/DirectPurchaseOrderItem.php` | Agregar `cost_center_id` en fillable y relación |
| `app/Models/DirectPurchaseOrder.php` | Eliminar `cost_center_id`, `costCenter()`, `scopeByCostCenter()` |
| `app/Services/BudgetAllocationService.php` | 3 métodos: leer CC del ítem, no del header |
| `app/Livewire/RequisitionForm.php` | Mover CC del header al item array |
| `resources/views/livewire/requisition-form.blade.php` | Header simplificado, tabla + columna CC, modal con cascada JS |
| `app/Http/Controllers/DirectPurchaseOrderController.php` | CC por ítem en create/update/validate |
| `tests/Feature/RequisitionItemCostCenterTest.php` | Crear — tests de modelo y Livewire |
| `tests/Feature/BudgetAllocationPerItemTest.php` | Crear — tests del servicio |
| `tests/Feature/DirectPurchaseOrderPerItemCostCenterTest.php` | Crear — tests del controlador |

---

## Task 1: Editar migraciones y reconstruir DB

**Files:**
- Modify: `database/migrations/2025_10_15_112438_create_requisitions_table.php`
- Modify: `database/migrations/2025_10_15_112442_create_requisition_items_table.php`
- Modify: `database/migrations/2026_01_28_224431_create_odc_direct_purchase_orders_table.php`
- Modify: `database/migrations/2026_01_28_224435_create_odc_direct_purchase_order_items_table.php`

- [ ] **Step 1: Eliminar `cost_center_id` de `create_requisitions_table.php`**

Localizar y eliminar estas dos líneas del bloque `up()`:

```php
// ELIMINAR este bloque completo:
$table->foreignId('cost_center_id')
    ->constrained('cost_centers')
    ->onUpdate('NO ACTION')
    ->onDelete('NO ACTION');

// ELIMINAR esta línea del bloque de índices:
$table->index(['company_id', 'cost_center_id']);
```

- [ ] **Step 2: Agregar `cost_center_id` a `create_requisition_items_table.php`**

Agregar justo después del cierre del bloque de `expense_category_id`, antes de `$table->decimal('quantity'...)`:

```php
$table->foreignId('cost_center_id')
    ->constrained('cost_centers')
    ->onUpdate('NO ACTION')
    ->onDelete('NO ACTION');
```

Y al final del bloque de índices, antes del cierre `});`:

```php
$table->index('cost_center_id');
$table->index(['requisition_id', 'cost_center_id']);
```

- [ ] **Step 3: Eliminar `cost_center_id` de `create_odc_direct_purchase_orders_table.php`**

Localizar y eliminar esta línea del bloque `up()`:

```php
// ELIMINAR:
$table->foreignId('cost_center_id')->constrained()->noActionOnDelete();
```

- [ ] **Step 4: Agregar `cost_center_id` a `create_odc_direct_purchase_order_items_table.php`**

Agregar justo después del bloque de `expense_category_id`, antes de `$table->text('description')`:

```php
$table->foreignId('cost_center_id')
    ->constrained('cost_centers')
    ->noActionOnDelete();
```

Y en el bloque de índices, agregar:

```php
$table->index('cost_center_id');
$table->index(['direct_purchase_order_id', 'cost_center_id']);
```

- [ ] **Step 5: Ejecutar migrate:fresh**

```bash
php artisan migrate:fresh
```

Salida esperada: todas las migraciones corren sin error. Verificar con:

```bash
php artisan tinker --execute="echo Schema::hasColumn('requisition_items', 'cost_center_id') ? 'OK' : 'FAIL';"
php artisan tinker --execute="echo Schema::hasColumn('requisitions', 'cost_center_id') ? 'FAIL' : 'OK';"
php artisan tinker --execute="echo Schema::hasColumn('odc_direct_purchase_order_items', 'cost_center_id') ? 'OK' : 'FAIL';"
php artisan tinker --execute="echo Schema::hasColumn('odc_direct_purchase_orders', 'cost_center_id') ? 'FAIL' : 'OK';"
```

Esperado: cuatro líneas con `OK`.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/
git commit -m "refactor(db): mover cost_center_id de cabecera a items en requisiciones y ODC"
```

---

## Task 2: Modelo RequisitionItem — agregar CC

**Files:**
- Modify: `app/Models/RequisitionItem.php`
- Create: `tests/Feature/RequisitionItemCostCenterTest.php`

- [ ] **Step 1: Escribir el test**

Crear `tests/Feature/RequisitionItemCostCenterTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\CostCenter;
use App\Models\Company;
use App\Models\Category;
use App\Models\User;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\ProductService;
use App\Models\ExpenseCategory;
use App\Models\BudgetCedula;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequisitionItemCostCenterTest extends TestCase
{
    use RefreshDatabase;

    private function makeCostCenter(): CostCenter
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create();
        $user = User::factory()->create();

        return CostCenter::factory()->create([
            'company_id'          => $company->id,
            'category_id'         => $category->id,
            'responsible_user_id' => $user->id,
            'budget_type'         => 'FREE_CONSUMPTION',
            'global_amount'       => 100000,
            'status'              => 'ACTIVO',
        ]);
    }

    public function test_cost_center_id_is_fillable(): void
    {
        $this->assertContains('cost_center_id', (new RequisitionItem())->getFillable());
    }

    public function test_cost_center_relation_returns_cost_center_instance(): void
    {
        $costCenter = $this->makeCostCenter();
        $item = new RequisitionItem(['cost_center_id' => $costCenter->id]);
        $item->cost_center_id = $costCenter->id;

        $this->assertInstanceOf(CostCenter::class, $item->costCenter()->getRelated());
    }

    public function test_is_valid_requires_cost_center_id(): void
    {
        $item = new RequisitionItem([
            'product_service_id'  => 1,
            'expense_category_id' => 1,
            'budget_cedula_id'    => 1,
            'quantity'            => 1,
            'cost_center_id'      => null,
        ]);

        $this->assertFalse($item->isValid());
    }

    public function test_is_valid_passes_with_all_required_fields(): void
    {
        $item = new RequisitionItem([
            'product_service_id'  => 1,
            'expense_category_id' => 1,
            'budget_cedula_id'    => 1,
            'quantity'            => 1,
            'cost_center_id'      => 1,
        ]);

        $this->assertTrue($item->isValid());
    }
}
```

- [ ] **Step 2: Correr el test para verificar que falla**

```bash
php artisan test tests/Feature/RequisitionItemCostCenterTest.php
```

Esperado: `FAIL` — `cost_center_id` no está en `$fillable` ni existe la relación.

- [ ] **Step 3: Implementar cambios en `RequisitionItem.php`**

**3a.** En `$fillable`, agregar `'cost_center_id'` después de `'budget_cedula_id'`:

```php
protected $fillable = [
    'requisition_id',
    'product_service_id',
    'line_number',
    'item_category',
    'product_code',
    'description',
    'expense_category_id',
    'budget_cedula_id',
    'cost_center_id',       // ← agregar aquí
    'quantity',
    'unit',
    'suggested_vendor_id',
    'notes',
];
```

**3b.** En `$casts`, agregar:

```php
'cost_center_id' => 'integer',
```

**3c.** Agregar la relación después de `budgetCedula()`:

```php
public function costCenter(): BelongsTo
{
    return $this->belongsTo(CostCenter::class);
}
```

**3d.** Actualizar `isValid()`:

```php
public function isValid(): bool
{
    return ! empty($this->product_service_id)
        && ! empty($this->expense_category_id)
        && ! empty($this->budget_cedula_id)
        && ! empty($this->cost_center_id)
        && $this->quantity > 0;
}
```

**3e.** Actualizar `getSummary()` — agregar al array retornado:

```php
'cost_center' => $this->costCenter?->name ?? 'Sin centro de costo',
```

**3f.** Actualizar `scopeWithRelations()`:

```php
public function scopeWithRelations($query)
{
    return $query->with([
        'productService',
        'expenseCategory',
        'budgetCedula',
        'suggestedVendor',
        'costCenter',       // ← agregar
    ]);
}
```

**3g.** Actualizar `validateRequisitionItems()` — agregar dentro del foreach:

```php
if (! $item->cost_center_id) {
    $errors[] = "Partida {$item->line_number}: Falta el centro de costo.";
}
```

- [ ] **Step 4: Correr el test para verificar que pasa**

```bash
php artisan test tests/Feature/RequisitionItemCostCenterTest.php
```

Esperado: `PASSED` (4 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Models/RequisitionItem.php tests/Feature/RequisitionItemCostCenterTest.php
git commit -m "feat(model): agregar cost_center_id por partida a RequisitionItem"
```

---

## Task 3: Modelo Requisition — eliminar CC

**Files:**
- Modify: `app/Models/Requisition.php`

- [ ] **Step 1: Eliminar `cost_center_id` de `$fillable`**

Quitar la línea `'cost_center_id',` del array `$fillable`.

- [ ] **Step 2: Eliminar el método `costCenter()`**

Eliminar el bloque:

```php
public function costCenter(): BelongsTo
{
    return $this->belongsTo(CostCenter::class);
}
```

- [ ] **Step 3: Eliminar `scopeByCostCenter()`**

Eliminar el bloque:

```php
public function scopeByCostCenter($query, int $costCenterId)
{
    return $query->where('cost_center_id', $costCenterId);
}
```

- [ ] **Step 4: Actualizar `submitToCompras()` — quitar `costCenter` del `load()`**

En la línea `$requisition->load('requester', 'costCenter', 'items');`, cambiar a:

```php
$requisition->load('requester', 'items');
```

- [ ] **Step 5: Correr la suite de tests existentes para detectar regresiones**

```bash
php artisan test
```

Esperado: todos los tests pasan. Si alguno falla por referencia a `cost_center_id` en `Requisition`, corregirlo en el test (usar `cost_center_id` en el item en su lugar).

- [ ] **Step 6: Commit**

```bash
git add app/Models/Requisition.php
git commit -m "refactor(model): eliminar cost_center_id de cabecera de Requisition"
```

---

## Task 4: Modelos DirectPurchaseOrder y DirectPurchaseOrderItem

**Files:**
- Modify: `app/Models/DirectPurchaseOrder.php`
- Modify: `app/Models/DirectPurchaseOrderItem.php`

- [ ] **Step 1: `DirectPurchaseOrderItem` — agregar `cost_center_id`**

En `$fillable`, agregar `'cost_center_id'` después de `'direct_purchase_order_id'`:

```php
protected $fillable = [
    'direct_purchase_order_id',
    'cost_center_id',           // ← agregar
    'expense_category_id',
    'description',
    'quantity',
    'quantity_received',
    'unit_price',
    'iva_rate',
    'subtotal',
    'iva_amount',
    'total',
    'unit_of_measure',
    'sku',
    'notes',
];
```

En `$casts`, agregar:

```php
'cost_center_id' => 'integer',
```

Agregar la relación después de `expenseCategory()`:

```php
public function costCenter(): BelongsTo
{
    return $this->belongsTo(CostCenter::class);
}
```

- [ ] **Step 2: `DirectPurchaseOrder` — eliminar `cost_center_id`**

Quitar `'cost_center_id'` de `$fillable`.

Quitar `'cost_center_id' => 'integer'` de `$casts`.

Eliminar el método:

```php
public function costCenter(): BelongsTo
{
    return $this->belongsTo(CostCenter::class);
}
```

Eliminar el scope:

```php
public function scopeByCostCenter($query, $costCenterId)
{
    return $query->where('cost_center_id', $costCenterId);
}
```

- [ ] **Step 3: Correr tests para detectar regresiones**

```bash
php artisan test
```

Esperado: todos los tests pasan.

- [ ] **Step 4: Commit**

```bash
git add app/Models/DirectPurchaseOrder.php app/Models/DirectPurchaseOrderItem.php
git commit -m "refactor(model): mover cost_center_id de cabecera a items en DirectPurchaseOrder"
```

---

## Task 5: BudgetAllocationService — QuotationSummary

**Files:**
- Modify: `app/Services/BudgetAllocationService.php`
- Create: `tests/Feature/BudgetAllocationPerItemTest.php`

- [ ] **Step 1: Escribir el test**

Crear `tests/Feature/BudgetAllocationPerItemTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\AnnualBudget;
use App\Models\BudgetCedula;
use App\Models\BudgetMonthlyDistribution;
use App\Models\Category;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\DirectPurchaseOrder;
use App\Models\DirectPurchaseOrderItem;
use App\Models\ExpenseCategory;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Rfq;
use App\Models\RfqResponse;
use App\Models\QuotationGroup;
use App\Models\QuotationSummary;
use App\Models\Supplier;
use App\Models\User;
use App\Services\BudgetAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetAllocationPerItemTest extends TestCase
{
    use RefreshDatabase;

    private BudgetAllocationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BudgetAllocationService::class);
    }

    private function makeFreeConsumptionCostCenter(): CostCenter
    {
        $company  = Company::factory()->create();
        $category = Category::factory()->create();
        $user     = User::factory()->create();

        return CostCenter::factory()->create([
            'company_id'          => $company->id,
            'category_id'         => $category->id,
            'responsible_user_id' => $user->id,
            'budget_type'         => 'FREE_CONSUMPTION',
            'global_amount'       => 999999,
            'status'              => 'ACTIVO',
            'purchase_type'       => 'Gasto Operativo',
        ]);
    }

    public function test_build_quotation_summary_budget_lines_uses_item_cost_center(): void
    {
        $costCenter1 = $this->makeFreeConsumptionCostCenter();
        $costCenter2 = $this->makeFreeConsumptionCostCenter();

        $expenseCategory = ExpenseCategory::factory()->create();
        $supplier        = Supplier::factory()->create();
        $user            = User::factory()->create();

        // Requisición sin cost_center_id (ya eliminado de cabecera)
        $requisition = Requisition::factory()->create([
            'requested_by' => $user->id,
            'created_by'   => $user->id,
        ]);

        $item1 = RequisitionItem::factory()->create([
            'requisition_id'      => $requisition->id,
            'expense_category_id' => $expenseCategory->id,
            'cost_center_id'      => $costCenter1->id,
            'quantity'            => 1,
        ]);

        $item2 = RequisitionItem::factory()->create([
            'requisition_id'      => $requisition->id,
            'expense_category_id' => $expenseCategory->id,
            'cost_center_id'      => $costCenter2->id,
            'quantity'            => 1,
        ]);

        $quotationGroup = QuotationGroup::factory()->create(['requisition_id' => $requisition->id]);
        $rfq = Rfq::factory()->create(['requisition_id' => $requisition->id, 'quotation_group_id' => $quotationGroup->id]);

        $response1 = RfqResponse::factory()->create([
            'rfq_id'               => $rfq->id,
            'supplier_id'          => $supplier->id,
            'requisition_item_id'  => $item1->id,
            'status'               => 'SUBMITTED',
            'total'                => 1000.00,
            'quotation_date'       => now()->toDateString(),
            'delivery_days'        => 5,
        ]);

        $response2 = RfqResponse::factory()->create([
            'rfq_id'               => $rfq->id,
            'supplier_id'          => $supplier->id,
            'requisition_item_id'  => $item2->id,
            'status'               => 'SUBMITTED',
            'total'                => 2000.00,
            'quotation_date'       => now()->toDateString(),
            'delivery_days'        => 5,
        ]);

        $summary = QuotationSummary::factory()->create([
            'requisition_id'       => $requisition->id,
            'rfq_id'               => $rfq->id,
            'selected_supplier_id' => $supplier->id,
        ]);

        $lines = $this->service->buildQuotationSummaryBudgetLines($summary);

        // Deben existir dos líneas, una por centro de costo
        $this->assertCount(2, $lines);
        $costCenterIds = collect($lines)->pluck('cost_center_id')->unique()->sort()->values()->toArray();
        $this->assertEquals(
            collect([$costCenter1->id, $costCenter2->id])->sort()->values()->toArray(),
            $costCenterIds
        );
    }
}
```

- [ ] **Step 2: Correr el test para verificar que falla**

```bash
php artisan test tests/Feature/BudgetAllocationPerItemTest.php::test_build_quotation_summary_budget_lines_uses_item_cost_center
```

Esperado: `FAIL` — aún lee `$summary->requisition->cost_center_id`.

- [ ] **Step 3: Implementar el cambio en `buildQuotationSummaryBudgetLines`**

Localizar en `app/Services/BudgetAllocationService.php` el método `buildQuotationSummaryBudgetLines`.

Cambiar el `loadMissing`:

```php
// Antes:
$summary->loadMissing('rfq.rfqResponses.requisitionItem', 'requisition.costCenter');

// Después:
$summary->loadMissing('rfq.rfqResponses.requisitionItem.costCenter');
```

Dentro del `->map(function ($response) use ($summary)`, cambiar:

```php
// Antes:
return [
    'cost_center_id' => (int) $summary->requisition->cost_center_id,
    // ...
    'budget_type' => $summary->requisition->costCenter?->budget_type ?? 'ANNUAL',
];

// Después:
return [
    'cost_center_id' => (int) $requisitionItem->cost_center_id,
    // ...
    'budget_type' => $requisitionItem->costCenter?->budget_type ?? 'ANNUAL',
];
```

- [ ] **Step 4: Correr el test**

```bash
php artisan test tests/Feature/BudgetAllocationPerItemTest.php::test_build_quotation_summary_budget_lines_uses_item_cost_center
```

Esperado: `PASSED`.

- [ ] **Step 5: Commit parcial**

```bash
git add app/Services/BudgetAllocationService.php tests/Feature/BudgetAllocationPerItemTest.php
git commit -m "feat(service): buildQuotationSummaryBudgetLines lee CC del item"
```

---

## Task 6: BudgetAllocationService — PurchaseOrder

**Files:**
- Modify: `app/Services/BudgetAllocationService.php`
- Modify: `tests/Feature/BudgetAllocationPerItemTest.php`

- [ ] **Step 1: Agregar el test**

Agregar al final de `BudgetAllocationPerItemTest`:

```php
public function test_get_order_budget_lines_purchase_order_uses_item_cost_center(): void
{
    $costCenter1 = $this->makeFreeConsumptionCostCenter();
    $costCenter2 = $this->makeFreeConsumptionCostCenter();

    $expenseCategory = ExpenseCategory::factory()->create();
    $supplier        = Supplier::factory()->create();
    $user            = User::factory()->create();

    $requisition = Requisition::factory()->create(['requested_by' => $user->id, 'created_by' => $user->id]);

    $reqItem1 = RequisitionItem::factory()->create([
        'requisition_id'      => $requisition->id,
        'expense_category_id' => $expenseCategory->id,
        'cost_center_id'      => $costCenter1->id,
    ]);

    $reqItem2 = RequisitionItem::factory()->create([
        'requisition_id'      => $requisition->id,
        'expense_category_id' => $expenseCategory->id,
        'cost_center_id'      => $costCenter2->id,
    ]);

    $purchaseOrder = \App\Models\PurchaseOrder::factory()->create([
        'requisition_id' => $requisition->id,
        'supplier_id'    => $supplier->id,
    ]);

    \App\Models\PurchaseOrderItem::factory()->create([
        'purchase_order_id'   => $purchaseOrder->id,
        'requisition_item_id' => $reqItem1->id,
        'total'               => 500,
    ]);

    \App\Models\PurchaseOrderItem::factory()->create([
        'purchase_order_id'   => $purchaseOrder->id,
        'requisition_item_id' => $reqItem2->id,
        'total'               => 800,
    ]);

    // Llamar al método privado via reflexión
    $ref = new \ReflectionMethod(BudgetAllocationService::class, 'getOrderBudgetLines');
    $ref->setAccessible(true);
    $lines = $ref->invoke($this->service, $purchaseOrder);

    $costCenterIds = collect($lines)->pluck('cost_center_id')->unique()->sort()->values()->toArray();
    $this->assertEquals(
        collect([$costCenter1->id, $costCenter2->id])->sort()->values()->toArray(),
        $costCenterIds
    );
}
```

- [ ] **Step 2: Correr el test para verificar que falla**

```bash
php artisan test tests/Feature/BudgetAllocationPerItemTest.php::test_get_order_budget_lines_purchase_order_uses_item_cost_center
```

Esperado: `FAIL`.

- [ ] **Step 3: Implementar el cambio — rama PurchaseOrder en `getOrderBudgetLines`**

Localizar la rama `if ($order instanceof PurchaseOrder)` en `getOrderBudgetLines`.

Cambiar el `loadMissing`:

```php
// Antes:
$order->loadMissing('items.requisitionItem', 'requisition.costCenter');

// Después:
$order->loadMissing('items.requisitionItem.costCenter');
```

Cambiar el groupBy y el map:

```php
// Antes:
return $order->items
    ->groupBy(fn ($item) => implode('|', [
        $item->requisitionItem?->expense_category_id,
        $item->requisitionItem?->budget_cedula_id,
        $applicationMonth,
    ]))
    ->map(function ($items) use ($order, $applicationMonth) {
        $firstItem = $items->first();

        return [
            'cost_center_id' => (int) $order->requisition->cost_center_id,
            // ...
            'budget_type' => $order->requisition->costCenter?->budget_type ?? 'ANNUAL',
        ];
    })

// Después:
return $order->items
    ->groupBy(fn ($item) => implode('|', [
        $item->requisitionItem?->cost_center_id,
        $item->requisitionItem?->expense_category_id,
        $item->requisitionItem?->budget_cedula_id,
        $applicationMonth,
    ]))
    ->map(function ($items) use ($applicationMonth) {
        $firstItem = $items->first();

        return [
            'cost_center_id' => (int) $firstItem->requisitionItem?->cost_center_id,
            'expense_category_id' => (int) $firstItem->requisitionItem?->expense_category_id,
            'budget_cedula_id' => $firstItem->requisitionItem?->budget_cedula_id
                ? (int) $firstItem->requisitionItem->budget_cedula_id
                : null,
            'amount' => (float) $items->sum('total'),
            'year' => (int) substr($applicationMonth, 0, 4),
            'month' => (int) substr($applicationMonth, 5, 2),
            'application_month' => $applicationMonth,
            'budget_type' => $firstItem->requisitionItem?->costCenter?->budget_type ?? 'ANNUAL',
        ];
    })
```

- [ ] **Step 4: Correr el test**

```bash
php artisan test tests/Feature/BudgetAllocationPerItemTest.php
```

Esperado: todos los tests del archivo pasan.

- [ ] **Step 5: Commit**

```bash
git add app/Services/BudgetAllocationService.php tests/Feature/BudgetAllocationPerItemTest.php
git commit -m "feat(service): getOrderBudgetLines PurchaseOrder lee CC del item"
```

---

## Task 7: BudgetAllocationService — DirectPurchaseOrder

**Files:**
- Modify: `app/Services/BudgetAllocationService.php`
- Modify: `tests/Feature/BudgetAllocationPerItemTest.php`

- [ ] **Step 1: Agregar el test**

Agregar al final de `BudgetAllocationPerItemTest`:

```php
public function test_get_order_budget_lines_direct_purchase_order_uses_item_cost_center(): void
{
    $costCenter1 = $this->makeFreeConsumptionCostCenter();
    $costCenter2 = $this->makeFreeConsumptionCostCenter();

    $expenseCategory = ExpenseCategory::factory()->create();
    $supplier        = Supplier::factory()->create();
    $user            = User::factory()->create();

    $ocd = DirectPurchaseOrder::factory()->create([
        'supplier_id'    => $supplier->id,
        'created_by'     => $user->id,
        'application_month' => now()->format('Y-m'),
    ]);

    DirectPurchaseOrderItem::factory()->create([
        'direct_purchase_order_id' => $ocd->id,
        'expense_category_id'      => $expenseCategory->id,
        'cost_center_id'           => $costCenter1->id,
        'total'                    => 1500,
    ]);

    DirectPurchaseOrderItem::factory()->create([
        'direct_purchase_order_id' => $ocd->id,
        'expense_category_id'      => $expenseCategory->id,
        'cost_center_id'           => $costCenter2->id,
        'total'                    => 2500,
    ]);

    $ref = new \ReflectionMethod(BudgetAllocationService::class, 'getOrderBudgetLines');
    $ref->setAccessible(true);
    $lines = $ref->invoke($this->service, $ocd);

    $this->assertCount(2, $lines);
    $costCenterIds = collect($lines)->pluck('cost_center_id')->unique()->sort()->values()->toArray();
    $this->assertEquals(
        collect([$costCenter1->id, $costCenter2->id])->sort()->values()->toArray(),
        $costCenterIds
    );
}
```

- [ ] **Step 2: Correr el test para verificar que falla**

```bash
php artisan test tests/Feature/BudgetAllocationPerItemTest.php::test_get_order_budget_lines_direct_purchase_order_uses_item_cost_center
```

Esperado: `FAIL`.

- [ ] **Step 3: Implementar el cambio — rama DirectPurchaseOrder en `getOrderBudgetLines`**

Localizar la rama `if ($order instanceof DirectPurchaseOrder)`.

Cambiar el `loadMissing`:

```php
// Antes:
$order->loadMissing('items', 'costCenter');

// Después:
$order->loadMissing('items.costCenter');
```

Cambiar el groupBy y el map completo:

```php
// Antes:
return $order->items
    ->groupBy('expense_category_id')
    ->map(function ($items, $categoryId) use ($order) {
        return [
            'cost_center_id' => (int) $order->cost_center_id,
            'expense_category_id' => (int) $categoryId,
            'budget_cedula_id' => null,
            'amount' => (float) $items->sum('total'),
            'year' => (int) substr((string) $order->application_month, 0, 4),
            'month' => (int) substr((string) $order->application_month, 5, 2),
            'application_month' => $order->application_month,
            'budget_type' => $order->costCenter?->budget_type ?? 'ANNUAL',
        ];
    })
    ->values()
    ->all();

// Después:
return $order->items
    ->groupBy(fn ($item) => $item->cost_center_id . '|' . $item->expense_category_id)
    ->map(function ($items) use ($order) {
        $first = $items->first();

        return [
            'cost_center_id'      => (int) $first->cost_center_id,
            'expense_category_id' => (int) $first->expense_category_id,
            'budget_cedula_id'    => null,
            'amount'              => (float) $items->sum('total'),
            'year'                => (int) substr((string) $order->application_month, 0, 4),
            'month'               => (int) substr((string) $order->application_month, 5, 2),
            'application_month'   => $order->application_month,
            'budget_type'         => $first->costCenter?->budget_type ?? 'ANNUAL',
        ];
    })
    ->values()
    ->all();
```

- [ ] **Step 4: Correr todos los tests del archivo**

```bash
php artisan test tests/Feature/BudgetAllocationPerItemTest.php
```

Esperado: todos pasan.

- [ ] **Step 5: Correr la suite completa**

```bash
php artisan test
```

Esperado: sin regresiones.

- [ ] **Step 6: Commit**

```bash
git add app/Services/BudgetAllocationService.php tests/Feature/BudgetAllocationPerItemTest.php
git commit -m "feat(service): getOrderBudgetLines DirectPurchaseOrder lee CC del item"
```

---

## Task 8: RequisitionForm.php — Livewire component

**Files:**
- Modify: `app/Livewire/RequisitionForm.php`

- [ ] **Step 1: Eliminar propiedades de cabecera**

Eliminar las siguientes propiedades públicas:

```php
// ELIMINAR:
public $purchase_type;
public $cost_center_id;
public $hasHydratedCostCenterOnce = false;
public $costCenters = [];
```

Renombrar `$headerCostCenterCatalog` → `$costCenterCatalog`:

```php
// Antes:
public $headerCostCenterCatalog = [];

// Después:
public $costCenterCatalog = [];
```

- [ ] **Step 2: Actualizar `mount()`**

Renombrar todas las referencias de `$this->headerCostCenterCatalog` a `$this->costCenterCatalog`.

Eliminar estas líneas del bloque de modo edición:

```php
// ELIMINAR:
$this->purchase_type = $requisition->costCenter?->purchase_type?->value ?? ...;
$this->loadCostCenters($this->company_id, $this->purchase_type);
$this->cost_center_id = $requisition->cost_center_id;
```

Actualizar el `$this->items = $requisition->items->map(...)` para incluir CC por ítem:

```php
$this->items = $requisition->items->map(function ($item) {
    return [
        'product_id'            => $item->product_service_id,
        'product_name'          => "[{$item->product_code}] " . ($item->productService->short_name ?? $item->description),
        'description'           => $item->description,
        'quantity'              => $item->quantity,
        'unit'                  => $item->unit,
        'expense_category_id'   => $item->expense_category_id,
        'expense_category_name' => $item->expenseCategory->name ?? 'N/A',
        'budget_cedula_id'      => $item->budget_cedula_id,
        'budget_cedula_name'    => $item->budgetCedula->name ?? 'N/A',
        'cost_center_id'        => $item->cost_center_id,
        'cost_center_name'      => $item->costCenter->name ?? 'N/A',
        'purchase_type'         => $item->costCenter?->purchase_type?->value ?? '',
        'notes'                 => $item->notes ?? '',
    ];
})->toArray();
```

- [ ] **Step 3: Eliminar métodos de cabecera**

Eliminar completamente:
- `updatedCompanyId($value)` — solo el bloque de `$this->cost_center_id`, `loadCostCenters`. Si el método hace otras cosas, conservar esas partes.
- `updatedPurchaseType($value)` — eliminar el método completo
- `updatedCostCenterId($value)` — eliminar el método completo
- `loadCostCenters($companyId, $purchaseType)` — eliminar el método completo

- [ ] **Step 4: Actualizar `addItem()` y `updateItem()`**

En `addItem()`, agregar al array que se guarda en `$this->items[]`:

```php
'cost_center_id'   => $itemData['cost_center_id'],
'cost_center_name' => $itemData['cost_center_name'],
'purchase_type'    => $itemData['purchase_type'],
```

Igual en `updateItem()` — el ítem en `$this->items[$index]` incluye los tres campos nuevos.

- [ ] **Step 5: Actualizar `validateItemPayload()`**

```php
private function validateItemPayload(array $itemData): bool
{
    if (empty($itemData['product_id'])
        || empty($itemData['expense_category_id'])
        || empty($itemData['budget_cedula_id'])
        || empty($itemData['cost_center_id'])) {
        return false;
    }

    // Verificar que el CC pertenezca al usuario
    $validCostCenter = Auth::user()->costCenters()
        ->where('cost_centers.id', (int) $itemData['cost_center_id'])
        ->where('cost_centers.status', 'ACTIVO')
        ->whereNull('cost_centers.deleted_at')
        ->wherePivot('is_active', true)
        ->exists();

    if (! $validCostCenter) {
        return false;
    }

    $cedula = BudgetCedula::query()
        ->whereKey((int) $itemData['budget_cedula_id'])
        ->where('expense_category_id', (int) $itemData['expense_category_id'])
        ->first();

    if (! $cedula) {
        return false;
    }

    return app(BudgetCedulaCatalogService::class)->isValidCedulaForContext(
        (int) $itemData['cost_center_id'],
        (int) $itemData['expense_category_id'],
        (int) $itemData['budget_cedula_id'],
        now()->year
    );
}
```

- [ ] **Step 6: Actualizar `save()`**

Eliminar de `validate()` las reglas:

```php
// ELIMINAR:
'purchase_type'  => 'required|in:' . implode(',', PurchaseType::values()),
'cost_center_id' => 'required|exists:cost_centers,id',
```

Eliminar el bloque `validCostCenter` completo.

En `Requisition::create([...])` y `$requisition->update([...])`, eliminar:

```php
// ELIMINAR:
'cost_center_id' => $this->cost_center_id,
```

En los bloques `RequisitionItem::create([...])`, agregar:

```php
'cost_center_id' => $item['cost_center_id'],
```

- [ ] **Step 7: Correr los tests para detectar regresiones**

```bash
php artisan test
```

Esperado: sin regresiones.

- [ ] **Step 8: Commit**

```bash
git add app/Livewire/RequisitionForm.php
git commit -m "feat(livewire): mover cost_center_id del header al item en RequisitionForm"
```

---

## Task 9: Vista Blade — cabecera, tabla y modal JS

**Files:**
- Modify: `resources/views/livewire/requisition-form.blade.php`

- [ ] **Step 1: Simplificar la cabecera — eliminar campos CC y tipo de compra**

Eliminar los dos bloques `<div class="col-md-2">` de Tipo de compra y el `<div class="col-md-3">` de Centro de costo de la sección `Información General`. El grid queda con: Compañía | Ubicación de recepción | Fecha requerida | Descripción.

- [ ] **Step 2: Agregar columna "Centro de Costo" a la tabla**

En el `<thead>`, agregar después de `<th>Unidad</th>`:

```html
<th>Centro de Costo</th>
```

En el `@forelse`, agregar después de `<td>{{ $item['unit'] }}</td>`:

```html
<td>
    <span class="badge bg-secondary" title="{{ $item['cost_center_name'] }}">
        {{ Str::limit($item['cost_center_name'], 25) }}
    </span>
    <small class="d-block text-muted">{{ $item['purchase_type'] }}</small>
</td>
```

Actualizar el `colspan` del empty-state de `8` a `9`.

- [ ] **Step 3: Agregar campos de CC al modal**

Dentro del `<form id="itemForm">`, agregar ANTES del bloque de producto (`Producto del catálogo`):

```html
{{-- Tipo de compra (filtro para CC) --}}
<div class="row mb-3">
    <div class="col-md-5">
        <label for="modal_purchase_type" class="form-label fw-semibold">
            Tipo de compra <span class="text-danger">*</span>
        </label>
        <div class="input-group">
            <span class="input-group-text"><i class="ti ti-filter"></i></span>
            <select id="modal_purchase_type" class="form-select" required>
                <option value="">Seleccionar...</option>
                @foreach ($purchaseTypes as $pt)
                    <option value="{{ $pt }}">{{ $pt }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="col-md-7">
        <label for="modal_cost_center_id" class="form-label fw-semibold">
            Centro de Costo <span class="text-danger">*</span>
        </label>
        <div class="input-group">
            <span class="input-group-text"><i class="ti ti-chart-pie"></i></span>
            <select id="modal_cost_center_id" class="form-select" required disabled>
                <option value="">Selecciona primero el tipo de compra...</option>
            </select>
        </div>
        <div class="form-text" id="modal_cost_center_help"></div>
    </div>
</div>
```

- [ ] **Step 4: Agregar `$purchaseTypes` a la vista (si no está disponible)**

Verificar que `$purchaseTypes` ya está en el render del componente. Si fue eliminado en Task 8, re-agregarlo como propiedad pública del componente:

```php
public $purchaseTypes = [];
```

Y en `mount()`:

```php
$this->purchaseTypes = PurchaseType::values();
```

- [ ] **Step 5: Actualizar JS — eliminar referencias a CC del header**

En `syncFormValuesToWire()`, eliminar:

```js
// ELIMINAR:
wire.$set('purchase_type', $('#purchase_type').val() || '');
wire.$set('cost_center_id', $('#cost_center_id').val() || '');
```

Eliminar los listeners de `#company_id`, `#purchase_type`, `#cost_center_id` del header que renderizan el selector de CC.

Eliminar las funciones: `syncHeaderValuesToWire()`, `renderHeaderCostCenters()`, `getMatchingHeaderCostCenters()`.

Renombrar `headerCostCenterCatalog` → `costCenterCatalog` en el JS embebido:

```js
// Antes:
const headerCostCenterCatalog = @json($headerCostCenterCatalog);

// Después:
const costCenterCatalog = @json($costCenterCatalog);
```

- [ ] **Step 6: Actualizar JS — nueva cascada en el modal**

Agregar función para poblar el selector de CC del modal:

```js
function renderModalCostCenters(mode = 'reset') {
    const companyId    = $('#company_id').val() || '';
    const purchaseType = $('#modal_purchase_type').val() || '';
    const $cc          = $('#modal_cost_center_id');

    $cc.empty();

    if (!companyId || !purchaseType) {
        $cc.prop('disabled', true)
           .append('<option value="">Selecciona primero el tipo de compra...</option>');
        $('#modal_cost_center_help').text('');
        return;
    }

    const matches = costCenterCatalog.filter(row =>
        String(row.company_id) === String(companyId) &&
        String(row.purchase_type) === String(purchaseType)
    );

    if (matches.length === 0) {
        $cc.prop('disabled', true)
           .append('<option value="">Sin centros de costo para esta combinación</option>');
        $('#modal_cost_center_help').text('No tienes centros de costo asignados para este tipo de compra.');
        return;
    }

    $cc.prop('disabled', false)
       .append('<option value="">Seleccionar centro de costo...</option>');

    matches.forEach(row => {
        const label = row.code ? `[${row.code}] ${row.name}` : row.name;
        $cc.append($('<option>', { value: row.id, text: label, 'data-name': row.name, 'data-purchase-type': row.purchase_type }));
    });

    if (mode === 'edit' && $cc.data('pending-value')) {
        $cc.val(String($cc.data('pending-value')));
        $cc.data('pending-value', null);
    } else if (matches.length === 1) {
        $cc.val(String(matches[0].id));
    }

    // Si se seleccionó un CC, disparar carga de productos y categorías
    if ($cc.val()) {
        loadProductsForCostCenter();
        loadExpenseCategories();
    }
}
```

Agregar listener para `#modal_purchase_type`:

```js
$(document)
    .off('change.modalPurchaseType', '#modal_purchase_type')
    .on('change.modalPurchaseType', '#modal_purchase_type', function () {
        $('#modal_cost_center_id').val('').trigger('change');
        renderModalCostCenters('reset');
    });
```

Agregar listener para `#modal_cost_center_id`:

```js
$(document)
    .off('change.modalCostCenter', '#modal_cost_center_id')
    .on('change.modalCostCenter', '#modal_cost_center_id', function () {
        const ccId = $(this).val();
        $('#modal_expense_category').val(null).trigger('change');
        resetBudgetCedulaSelect();

        if (ccId) {
            loadProductsForCostCenter();
            loadExpenseCategories();
        } else {
            $('#modal_expense_category')
                .empty()
                .append('<option value="">Seleccione primero un centro de costo...</option>')
                .prop('disabled', true);
            initializeExpenseCategorySelect();
        }
    });
```

- [ ] **Step 7: Actualizar `loadProductsForCostCenter()` y `loadExpenseCategories()`**

Cambiar ambas funciones para leer el CC del modal en lugar del header:

```js
// Antes en ambas funciones:
const costCenterId = $('#cost_center_id').val();

// Después:
const costCenterId = $('#modal_cost_center_id').val();
```

Y en `loadBudgetCedulas()`:

```js
// Antes:
const costCenterId = $('#cost_center_id').val();

// Después:
const costCenterId = $('#modal_cost_center_id').val();
```

- [ ] **Step 8: Actualizar `#btnAddItem` click handler**

```js
// Antes:
$(document).off('click.requisitionAddItem', '#btnAddItem').on('click.requisitionAddItem', '#btnAddItem', function() {
    const companyId = $('#company_id').val();
    const costCenterId = $('#cost_center_id').val();

    if (!companyId || !costCenterId) {
        Swal.fire('Datos incompletos', 'Primero selecciona compañía y centro de costo.', 'warning');
        return;
    }
    // ...
});

// Después:
$(document).off('click.requisitionAddItem', '#btnAddItem').on('click.requisitionAddItem', '#btnAddItem', function() {
    const companyId = $('#company_id').val();

    if (!companyId) {
        Swal.fire('Datos incompletos', 'Primero selecciona una compañía.', 'warning');
        return;
    }

    openItemModal();
});
```

- [ ] **Step 9: Actualizar `openItemModal()` y `openItemModalForEdit()`**

En `openItemModal()`, agregar reset del CC y tipo de compra:

```js
openItemModal = function() {
    editingIndex = null;

    $('#itemModalTitle').text('Agregar Partida');
    document.getElementById('itemForm').reset();
    $('#item_index').val('');
    $('#budgetAlert').hide();
    $('#product_info').hide();
    resetBudgetCedulaSelect();

    // Reset cascada del modal
    $('#modal_purchase_type').val('');
    $('#modal_cost_center_id').prop('disabled', true)
        .empty()
        .append('<option value="">Selecciona primero el tipo de compra...</option>');
    $('#modal_expense_category').empty()
        .append('<option value="">Seleccione primero un centro de costo...</option>')
        .prop('disabled', true);
    initializeExpenseCategorySelect();

    $('#modal_product_id').empty().append('<option value="">Buscar producto del catálogo...</option>');

    $('#itemModal').modal('show');
}
```

En `openItemModalForEdit()`, pre-poblar CC y tipo de compra:

```js
openItemModalForEdit = function(index, item) {
    editingIndex = index;

    $('#itemModalTitle').text('Editar Partida');
    $('#item_index').val(index);
    $('#budgetAlert').hide();
    resetBudgetCedulaSelect();

    // Pre-poblar tipo de compra
    $('#modal_purchase_type').val(item.purchase_type || '');

    // Pre-poblar CC (usando data-pending-value para esperar al render)
    $('#modal_cost_center_id').data('pending-value', item.cost_center_id || null);
    renderModalCostCenters('edit');

    setTimeout(() => {
        $('#modal_product_id').val(item.product_id).trigger('change');
        $('#modal_description').val(item.description);
        $('#modal_quantity').val(item.quantity);
        $('#modal_unit').val(item.unit);
        $('#modal_budget_cedula').data('pending-value', item.budget_cedula_id || null);
        $('#modal_expense_category').val(item.expense_category_id).trigger('change');
        $('#modal_notes').val(item.notes || '');
    }, 500);

    $('#itemModal').modal('show');
}
```

- [ ] **Step 10: Actualizar `#btnSaveItem` — agregar validaciones CC y construir `itemData`**

Agregar al inicio del handler, antes de la validación de producto:

```js
const purchaseType = $('#modal_purchase_type').val();
const costCenterId = $('#modal_cost_center_id').val();
const costCenterName = $('#modal_cost_center_id option:selected').data('name') || '';

if (!purchaseType) {
    Swal.fire('Campo requerido', 'Selecciona el tipo de compra de esta partida.', 'error');
    return;
}

if (!costCenterId) {
    Swal.fire('Campo requerido', 'Selecciona el centro de costo de esta partida.', 'error');
    return;
}
```

Agregar al objeto `itemData`:

```js
const itemData = {
    // ... campos existentes ...
    cost_center_id:   costCenterId,
    cost_center_name: costCenterName,
    purchase_type:    purchaseType,
};
```

- [ ] **Step 11: Verificar manualmente en el navegador**

1. Abrir el formulario de nueva requisición.
2. Verificar que la cabecera ya NO tiene Tipo de compra ni Centro de costo.
3. Hacer clic en "Agregar Partida" (solo verificando que haya compañía).
4. En el modal: seleccionar Tipo de compra → verificar que se populan los CCs.
5. Seleccionar CC → verificar que cargan productos y categorías.
6. Completar el formulario y guardar la partida.
7. Verificar que la tabla muestra la columna "Centro de Costo" con el badge.
8. Abrir edición de la partida → verificar que se pre-popula CC y tipo de compra.

- [ ] **Step 12: Commit**

```bash
git add resources/views/livewire/requisition-form.blade.php
git commit -m "feat(ui): mover selector CC al modal de partidas, cascada JS purchase_type→CC→categoría"
```

---

## Task 10: DirectPurchaseOrderController

**Files:**
- Modify: `app/Http/Controllers/DirectPurchaseOrderController.php`
- Create: `tests/Feature/DirectPurchaseOrderPerItemCostCenterTest.php`

- [ ] **Step 1: Escribir el test**

Crear `tests/Feature/DirectPurchaseOrderPerItemCostCenterTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\CostCenter;
use App\Models\Category;
use App\Models\Company;
use App\Models\DirectPurchaseOrder;
use App\Models\DirectPurchaseOrderItem;
use App\Models\ExpenseCategory;
use App\Models\Supplier;
use App\Models\ReceivingLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectPurchaseOrderPerItemCostCenterTest extends TestCase
{
    use RefreshDatabase;

    private function makeSetup(): array
    {
        $user          = User::factory()->create();
        $company       = Company::factory()->create();
        $category      = Category::factory()->create();
        $costCenter    = CostCenter::factory()->create([
            'company_id'          => $company->id,
            'category_id'         => $category->id,
            'responsible_user_id' => $user->id,
            'budget_type'         => 'FREE_CONSUMPTION',
            'global_amount'       => 999999,
            'status'              => 'ACTIVO',
            'purchase_type'       => 'Gasto Operativo',
        ]);
        $user->costCenters()->attach($costCenter->id, ['is_active' => true, 'is_default' => false, 'created_by' => $user->id]);

        $supplier         = Supplier::factory()->create();
        $receivingLocation = ReceivingLocation::factory()->create();
        $expenseCategory  = ExpenseCategory::factory()->create();

        return compact('user', 'costCenter', 'supplier', 'receivingLocation', 'expenseCategory');
    }

    public function test_store_does_not_require_cost_center_at_header_level(): void
    {
        ['user' => $user, 'costCenter' => $costCenter, 'supplier' => $supplier,
         'receivingLocation' => $receivingLocation, 'expenseCategory' => $expenseCategory] = $this->makeSetup();

        $response = $this->actingAs($user)->post(route('direct-purchase-orders.store'), [
            'supplier_id'           => $supplier->id,
            'receiving_location_id' => $receivingLocation->id,
            'application_month'     => now()->format('Y-m'),
            'justification'         => 'Compra urgente de materiales',
            'items'                 => [
                [
                    'expense_category_id' => $expenseCategory->id,
                    'cost_center_id'      => $costCenter->id,
                    'description'         => 'Artículo de prueba',
                    'quantity'            => 2,
                    'unit_price'          => 500.00,
                    'iva_rate'            => 16,
                    'unit_of_measure'     => 'PZA',
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('odc_direct_purchase_orders', ['cost_center_id' => $costCenter->id]);
        $this->assertDatabaseHas('odc_direct_purchase_order_items', ['cost_center_id' => $costCenter->id]);
    }

    public function test_each_item_can_have_a_different_cost_center(): void
    {
        ['user' => $user, 'supplier' => $supplier,
         'receivingLocation' => $receivingLocation, 'expenseCategory' => $expenseCategory] = $this->makeSetup();

        $company   = $user->companies()->first();
        $category  = Category::factory()->create();
        $cc2 = CostCenter::factory()->create([
            'company_id' => $company->id, 'category_id' => $category->id,
            'responsible_user_id' => $user->id, 'budget_type' => 'FREE_CONSUMPTION',
            'global_amount' => 999999, 'status' => 'ACTIVO', 'purchase_type' => 'Gasto Operativo',
        ]);
        $user->costCenters()->attach($cc2->id, ['is_active' => true, 'is_default' => false, 'created_by' => $user->id]);

        $cc1 = $user->costCenters()->first();

        $this->actingAs($user)->post(route('direct-purchase-orders.store'), [
            'supplier_id'           => $supplier->id,
            'receiving_location_id' => $receivingLocation->id,
            'application_month'     => now()->format('Y-m'),
            'justification'         => 'Compra multi-CC',
            'items'                 => [
                ['expense_category_id' => $expenseCategory->id, 'cost_center_id' => $cc1->id,
                 'description' => 'Item 1', 'quantity' => 1, 'unit_price' => 100, 'iva_rate' => 16, 'unit_of_measure' => 'PZA'],
                ['expense_category_id' => $expenseCategory->id, 'cost_center_id' => $cc2->id,
                 'description' => 'Item 2', 'quantity' => 1, 'unit_price' => 200, 'iva_rate' => 16, 'unit_of_measure' => 'PZA'],
            ],
        ]);

        $ocd = DirectPurchaseOrder::latest()->first();
        $this->assertCount(2, $ocd->items);
        $this->assertEquals($cc1->id, $ocd->items[0]->cost_center_id);
        $this->assertEquals($cc2->id, $ocd->items[1]->cost_center_id);
    }
}
```

- [ ] **Step 2: Correr el test para verificar que falla**

```bash
php artisan test tests/Feature/DirectPurchaseOrderPerItemCostCenterTest.php
```

Esperado: `FAIL`.

- [ ] **Step 3: Actualizar el método `store()` del controlador**

En `store()`, eliminar `cost_center_id` del `DirectPurchaseOrder::create([...])`.

Al crear los ítems dentro del loop, agregar `'cost_center_id' => $itemData['cost_center_id']`:

```php
DirectPurchaseOrderItem::create([
    'direct_purchase_order_id' => $ocd->id,
    'cost_center_id'           => $itemData['cost_center_id'],  // ← agregar
    'expense_category_id'      => $itemData['expense_category_id'],
    'description'              => $itemData['description'],
    'quantity'                 => $itemData['quantity'],
    'unit_price'               => $itemData['unit_price'],
    'iva_rate'                 => $itemData['iva_rate'] ?? 16,
    'unit_of_measure'          => $itemData['unit_of_measure'] ?? null,
    'sku'                      => $itemData['sku'] ?? null,
    'notes'                    => $itemData['notes'] ?? null,
]);
```

- [ ] **Step 4: Actualizar el método `update()` del controlador**

Eliminar `cost_center_id` de `$ocd->update([...])`. Al recrear ítems en update, igual agregar `cost_center_id` del array del item.

- [ ] **Step 5: Actualizar `getAvailableCategories()`**

Cambiar el request validation y lectura de `cost_center_id`:

```php
// Antes — valida a nivel request/cabecera:
$request->validate(['cost_center_id' => 'required|exists:cost_centers,id']);
$costCenterId = $request->cost_center_id;

// Después — recibe CC por item:
$request->validate(['cost_center_id' => 'required|exists:cost_centers,id']);
$costCenterId = $request->cost_center_id; // Sigue siendo un parámetro, pero ahora lo manda el modal del item
```

> Nota: el endpoint ya recibe `cost_center_id` como parámetro GET, así que solo hay que asegurarse de que el JS del modal ODC lo mande del item, no de la cabecera.

- [ ] **Step 6: Actualizar validación presupuestal en `submit()`/`approve()`**

Localizar el bloque donde se valida disponibilidad presupuestal. Cambiar de leer `$directPurchaseOrder->cost_center_id` a iterar por ítem:

```php
// Antes:
foreach ($itemsByCategory as $categoryId => $items) {
    $requiredAmount = (float) $items->sum('total');
    $budgetCheck = $this->validateBudgetAvailability(
        $directPurchaseOrder->cost_center_id,
        ...
    );
}

// Después:
foreach ($ocd->items as $item) {
    $budgetCheck = $this->validateBudgetAvailability(
        $item->cost_center_id,
        $ocd->application_month,
        $item->expense_category_id,
        (float) $item->total
    );
    if (! $budgetCheck['available']) {
        return back()->withErrors(['budget' => $budgetCheck['message']]);
    }
}
```

- [ ] **Step 7: Correr los tests**

```bash
php artisan test tests/Feature/DirectPurchaseOrderPerItemCostCenterTest.php
php artisan test
```

Esperado: todos pasan.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/DirectPurchaseOrderController.php tests/Feature/DirectPurchaseOrderPerItemCostCenterTest.php
git commit -m "feat(controller): ODC maneja cost_center_id por item"
```

---

## Verificación Final

- [ ] **Correr suite completa**

```bash
php artisan test
```

Esperado: sin regresiones.

- [ ] **Verificar flujo completo manualmente**

1. Crear requisición con 2 partidas en distintos CCs → guardar borrador → enviar a Compras.
2. Crear ODC con 2 ítems en distintos CCs → enviar a aprobación.
3. Verificar que `BudgetAllocationService` no lanza excepción al reservar presupuesto.

- [ ] **Commit final de cierre**

```bash
git add .
git commit -m "feat: centro de costo por partida en requisiciones y ODC [completo]"
```
