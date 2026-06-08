# Centro de Costo por Partida — Especificación de Diseño

**Fecha:** 2026-06-08  
**Alcance:** Requisiciones, Órdenes de Compra Regulares (OC), Órdenes de Compra Directas (ODC)

---

## Contexto y Motivación

Actualmente el sistema asigna un único `cost_center_id` a nivel de cabecera de la requisición y de la ODC. El flujo real del negocio requiere que **cada partida pueda cargarse a un centro de costos diferente**, ya que una misma compra puede involucrar gastos de múltiples áreas o estaciones.

---

## Decisiones de Diseño

| Decisión | Respuesta |
|---|---|
| ¿Dónde vive el CC en requisición? | Por partida (`requisition_items`) |
| ¿Se conserva CC en cabecera de requisición? | No — se elimina |
| ¿Dónde vive el CC en ODC? | Por partida (`odc_direct_purchase_order_items`) |
| ¿Se conserva CC en cabecera de ODC? | No — se elimina |
| ¿Qué CCs puede seleccionar el usuario? | Solo los asignados al usuario (`cost_center_user` donde `is_active = true`) |
| ¿`purchase_type` en cabecera? | No — se mueve al modal como filtro para el selector de CC por partida |
| Estrategia de migración | Editar migraciones originales + `migrate:fresh` (entorno de desarrollo, datos desechables) |

---

## 1. Base de Datos

### Migraciones a editar

**`2025_10_15_112438_create_requisitions_table.php`**
- Eliminar columna `cost_center_id` (FK + índices compuestos que la incluyan, incluyendo `['company_id', 'cost_center_id']`)

**`2025_10_15_112442_create_requisition_items_table.php`**
- Agregar:
  ```php
  $table->foreignId('cost_center_id')
      ->constrained('cost_centers')
      ->onUpdate('NO ACTION')
      ->onDelete('NO ACTION');
  $table->index('cost_center_id');
  $table->index(['requisition_id', 'cost_center_id']);
  ```

**`2026_01_28_224431_create_odc_direct_purchase_orders_table.php`**
- Eliminar columna `cost_center_id` (FK, índice, y cualquier referencia en constraints de SQL Server)

**`2026_01_28_224435_create_odc_direct_purchase_order_items_table.php`**
- Agregar:
  ```php
  $table->foreignId('cost_center_id')
      ->constrained('cost_centers')
      ->onUpdate('NO ACTION')
      ->onDelete('NO ACTION');
  $table->index('cost_center_id');
  ```

**Comando de reset:**
```bash
php artisan migrate:fresh
```

---

## 2. Modelos

### `RequisitionItem`
- Agregar `cost_center_id` a `$fillable`
- Agregar relación:
  ```php
  public function costCenter(): BelongsTo
  {
      return $this->belongsTo(CostCenter::class);
  }
  ```
- Actualizar `isValid()` para incluir `cost_center_id` como campo requerido
- Actualizar `getSummary()` para incluir `cost_center_name`
- Actualizar `scopeWithRelations()` para incluir `costCenter` en el eager load
- Actualizar `validateRequisitionItems()` para validar que cada partida tenga `cost_center_id`

### `Requisition`
- Eliminar `cost_center_id` de `$fillable`
- Eliminar método `costCenter(): BelongsTo`
- Eliminar `scopeByCostCenter()`
- En `submitToCompras()`: quitar `costCenter` del `$requisition->load(...)` 

### `DirectPurchaseOrderItem`
- Agregar `cost_center_id` a `$fillable`
- Agregar relación:
  ```php
  public function costCenter(): BelongsTo
  {
      return $this->belongsTo(CostCenter::class);
  }
  ```

### `DirectPurchaseOrder`
- Eliminar `cost_center_id` de `$fillable` y de `$casts`
- Eliminar método `costCenter(): BelongsTo`
- Eliminar `scopeByCostCenter()`

---

## 3. BudgetAllocationService

Este servicio toma el `cost_center_id` de la cabecera hoy. Con el cambio cada línea presupuestal lo toma del item.

### `buildQuotationSummaryBudgetLines(QuotationSummary $summary)`
- Cambiar:
  ```php
  // Antes
  'cost_center_id' => (int) $summary->requisition->cost_center_id,
  'budget_type'    => $summary->requisition->costCenter?->budget_type ?? 'ANNUAL',
  
  // Después
  'cost_center_id' => (int) $requisitionItem->cost_center_id,
  'budget_type'    => $requisitionItem->costCenter?->budget_type ?? 'ANNUAL',
  ```
- Actualizar `loadMissing` para incluir `rfq.rfqResponses.requisitionItem.costCenter`
- El grouping key ya incluye `cost_center_id`, por lo que items de distintos CCs generan líneas separadas automáticamente

### `getOrderBudgetLines` — rama `PurchaseOrder`
- Cambiar:
  ```php
  // Antes
  'cost_center_id' => (int) $order->requisition->cost_center_id,
  'budget_type'    => $order->requisition->costCenter?->budget_type ?? 'ANNUAL',
  
  // Después (por item)
  'cost_center_id' => (int) $item->requisitionItem->cost_center_id,
  'budget_type'    => $item->requisitionItem->costCenter?->budget_type ?? 'ANNUAL',
  ```
- Actualizar `loadMissing` para incluir `items.requisitionItem.costCenter`

### `getOrderBudgetLines` — rama `DirectPurchaseOrder`
- Cambiar agrupación de `groupBy('expense_category_id')` a `groupBy(fn($item) => $item->cost_center_id . '|' . $item->expense_category_id)`
- Cambiar:
  ```php
  // Antes
  'cost_center_id' => (int) $order->cost_center_id,
  'budget_type'    => $order->costCenter?->budget_type ?? 'ANNUAL',
  
  // Después (primer item del grupo, todos comparten el mismo CC por el groupBy)
  'cost_center_id' => (int) $items->first()->cost_center_id,
  'budget_type'    => $items->first()->costCenter?->budget_type ?? 'ANNUAL',
  ```
- Actualizar `loadMissing` para incluir `items.costCenter`

---

## 4. UI — RequisitionForm (Livewire + JS)

### `RequisitionForm.php` — Cambios al componente Livewire

**Eliminar propiedades:**
- `$cost_center_id`, `$purchase_type`, `$costCenters`, `$hasHydratedCostCenterOnce`

**Renombrar `$headerCostCenterCatalog`** a `$costCenterCatalog` — sigue siendo el catálogo de CCs del usuario, pero ahora se usa en el modal de partidas.

**Eliminar métodos:**
- `updatedCostCenterId()`, `updatedPurchaseType()`, `loadCostCenters()`

**Actualizar `$items[]`:** cada item incluye ahora:
```php
'cost_center_id'   => $item->cost_center_id,
'cost_center_name' => $item->costCenter->name ?? 'N/A',
'purchase_type'    => $item->costCenter->purchase_type->value ?? '',
```

**Actualizar `addItem()` / `updateItem()`:** recibir y guardar `cost_center_id`, `cost_center_name`, `purchase_type`.

**Actualizar `validateItemPayload()`:**
- Validar que `cost_center_id` esté presente
- Cambiar `$this->cost_center_id` → `$itemData['cost_center_id']`
- Verificar que el CC pertenezca al usuario (misma lógica de `validCostCenter` que hoy está en `save()`)

**Actualizar `save()`:**
- Eliminar validaciones de `cost_center_id` y `purchase_type` del `validate()`
- Eliminar el bloque `validCostCenter`
- Pasar `cost_center_id` de cada item en `RequisitionItem::create()`
- Eliminar `cost_center_id` de `Requisition::create()` y `$requisition->update()`

### `requisition-form.blade.php` — Cambios a la vista

**Cabecera del formulario:**
- Eliminar los campos de Tipo de Compra y Centro de Costo
- El grid pasa de 5 columnas a 3: Compañía | Ubicación de recepción | Fecha requerida | Descripción

**Tabla de partidas:**
- Agregar columna **Centro de Costo** (entre Unidad y Categoría de gasto):
  ```html
  <span class="badge bg-secondary">[{{ $item['cost_center_code'] }}] {{ $item['cost_center_name'] }}</span>
  ```
- Actualizar `colspan` del empty-state de 8 → 9

**Modal de partida — nueva cascada:**
```
1. Tipo de compra    (select — filtra CCs en JS puro)
2. Centro de Costo   (select — habilitado tras elegir tipo; dispara loadProducts + loadCategories)
3. Producto          (select2 con búsqueda)
4. Descripción       (readonly, auto-poblada)
5. Cantidad / Unidad
6. Categoría de gasto (select2 — habilitada tras elegir CC)
7. Subcategoría presupuestal (select2 — habilitada tras elegir categoría)
8. Observaciones
```

**JS — `syncFormValuesToWire`:**
- Eliminar `wire.$set('purchase_type', ...)` y `wire.$set('cost_center_id', ...)`

**JS — `#btnAddItem` click handler:**
- Solo verificar `companyId` (ya no requiere `costCenterId` en cabecera)

**JS — cascada en modal:**
- Al cambiar `#modal_purchase_type`: filtrar `costCenterCatalog` por `company_id` + `purchase_type` y repoblar `#modal_cost_center_id`
- Al cambiar `#modal_cost_center_id`: llamar `loadProductsForCostCenter()` + `loadExpenseCategories()`, ambas leen de `#modal_cost_center_id` (no de `#cost_center_id`)
- `loadExpenseCategories()` y `loadBudgetCedulas()` leen CC del modal

**JS — `#btnSaveItem` validaciones (en orden, antes de `wire.$call`):**
1. Sin `purchase_type` → `Swal.fire('Selecciona el tipo de compra')`
2. Sin `cost_center_id` → `Swal.fire('Selecciona el centro de costo de esta partida')`
3. Sin `product_id` → error existente
4. Sin cantidad válida → error existente
5. Sin `expense_category_id` → error existente
6. Sin `budget_cedula_id` → error existente

**JS — `openItemModalForEdit`:** pre-poblar `#modal_purchase_type` y `#modal_cost_center_id` del item al abrir en modo edición, disparando la cascada.

---

## 5. DirectPurchaseOrderController

- Eliminar `cost_center_id` del `create` y `update` de la ODC cabecera
- Agregar `cost_center_id` al crear/actualizar cada `DirectPurchaseOrderItem`
- `getAvailableCategories`: leer `cost_center_id` del item (parámetro del request), no de la cabecera de la ODC
- Validación presupuestal en `submit`/`approve`: iterar por item usando `$item->cost_center_id` en lugar de `$order->cost_center_id`

---

## Archivos Afectados (resumen)

| Archivo | Tipo de cambio |
|---|---|
| `database/migrations/2025_10_15_112438_create_requisitions_table.php` | Editar: eliminar `cost_center_id` |
| `database/migrations/2025_10_15_112442_create_requisition_items_table.php` | Editar: agregar `cost_center_id` |
| `database/migrations/2026_01_28_224431_create_odc_direct_purchase_orders_table.php` | Editar: eliminar `cost_center_id` |
| `database/migrations/2026_01_28_224435_create_odc_direct_purchase_order_items_table.php` | Editar: agregar `cost_center_id` |
| `app/Models/RequisitionItem.php` | Agregar CC, actualizar isValid/getSummary |
| `app/Models/Requisition.php` | Eliminar CC, scope, relación |
| `app/Models/DirectPurchaseOrderItem.php` | Agregar CC |
| `app/Models/DirectPurchaseOrder.php` | Eliminar CC, scope, relación |
| `app/Services/BudgetAllocationService.php` | Leer CC de items en 3 métodos |
| `app/Livewire/RequisitionForm.php` | Mover CC del header al item |
| `resources/views/livewire/requisition-form.blade.php` | Nueva cascada en modal, tabla actualizada |
| `app/Http/Controllers/DirectPurchaseOrderController.php` | CC por item en ODC |
