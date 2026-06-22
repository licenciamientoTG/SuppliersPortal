# Cotización Parcial de RFQ + RFQ Complementario — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permitir que un proveedor envíe su cotización final marcando explícitamente partidas como "no disponible", y que el comprador genere un RFQ complementario con las partidas que nadie cotizó — sin generar ODCs automáticas.

**Architecture:** Se agrega una columna booleana `not_available` a `rfq_responses`. El proveedor marca partidas no disponibles (montos en 0, fila real). El backend valida condicionalmente. La vista de comparativa del comprador muestra badges y un resumen "X de Y", y un modal genera un nuevo RFQ (nuevo `QuotationGroup` + `supersedes_rfq_id`) enviado de inmediato. Guardas garantizan que la ODC nunca incluya partidas no disponibles.

**Tech Stack:** Laravel 12, Blade, jQuery + SweetAlert2 (ya presentes), Eloquent, PHPUnit (tests estilo clase `Tests\TestCase`), SQLite `:memory:` con `RefreshDatabase`.

## Global Constraints

- Todo texto visible (proveedor y comprador) en **español**, claro, sin jerga técnica.
- **Ninguna ODC automática**: la generación de ODC sigue siendo decisión manual del comprador; este cambio solo agrega guardas para que una ODC nunca incluya una partida `not_available`.
- Columna `not_available`: `boolean`, `default(false)` **explícito** (filas existentes = cotizadas).
- Semántica de partida no disponible: `not_available = true`, `unit_price/quantity/subtotal/iva_amount/total = 0`, `status` normal (DRAFT/SUBMITTED).
- Se permite enviar con **cero** partidas cotizadas (no bloquear por ese motivo).
- El RFQ complementario se crea y envía de inmediato: `status='SENT'`, `sent_at=now()`, `supersedes_rfq_id` = RFQ origen.
- Seguir patrones existentes del proyecto (factories, `noActionOnDelete`, estilo de controladores).
- Tests: clase que extiende `Tests\TestCase`, `use RefreshDatabase`; correr con `php artisan test --filter=<TestClass>`.

---

### Task 1: Columna `not_available` en `rfq_responses` (migración + modelo)

**Files:**
- Create: `database/migrations/2026_06_22_100000_add_not_available_to_rfq_responses_table.php`
- Modify: `app/Models/RfqResponse.php` (`$fillable` ~línea 20-56, `$casts` ~línea 58-77, agregar scopes)
- Test: `tests/Feature/RfqResponseNotAvailableTest.php`

**Interfaces:**
- Produces:
  - Columna `rfq_responses.not_available` (boolean, default false).
  - `RfqResponse::$casts['not_available'] = 'boolean'`.
  - `RfqResponse::scopeQuoted($q)` → filtra `not_available = false`.
  - `RfqResponse::scopeNotAvailable($q)` → filtra `not_available = true`.

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Feature/RfqResponseNotAvailableTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\RfqResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfqResponseNotAvailableTest extends TestCase
{
    use RefreshDatabase;

    public function test_not_available_defaults_to_false_and_casts_to_boolean(): void
    {
        $response = RfqResponse::factory()->create();

        $this->assertIsBool($response->fresh()->not_available);
        $this->assertFalse($response->fresh()->not_available);
    }

    public function test_quoted_and_not_available_scopes_filter_rows(): void
    {
        $quoted = RfqResponse::factory()->create(['not_available' => false]);
        $unavailable = RfqResponse::factory()->create([
            'not_available' => true,
            'unit_price' => 0,
            'quantity' => 0,
            'subtotal' => 0,
            'iva_amount' => 0,
            'total' => 0,
        ]);

        $this->assertEquals([$quoted->id], RfqResponse::quoted()->pluck('id')->all());
        $this->assertEquals([$unavailable->id], RfqResponse::notAvailable()->pluck('id')->all());
    }
}
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `php artisan test --filter=RfqResponseNotAvailableTest`
Expected: FAIL — `Column not found: not_available` y `Call to undefined method ...::quoted()`.

- [ ] **Step 3: Crear la migración**

Crear `database/migrations/2026_06_22_100000_add_not_available_to_rfq_responses_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfq_responses', function (Blueprint $table) {
            // Diferencia explícita entre "no cotizada" y "cotizada en $0 real".
            // Default false: las filas existentes quedan como cotizadas.
            $table->boolean('not_available')->default(false)->after('meets_specs');
        });
    }

    public function down(): void
    {
        Schema::table('rfq_responses', function (Blueprint $table) {
            $table->dropColumn('not_available');
        });
    }
};
```

- [ ] **Step 4: Modificar el modelo `RfqResponse`**

En `app/Models/RfqResponse.php`, agregar `'not_available'` al final de `$fillable` (después de `'evaluated_at'`):

```php
        'evaluated_by',
        'evaluated_at',
        'not_available',
    ];
```

Agregar el cast dentro de `$casts` (después de `'meets_specs' => 'boolean',`):

```php
        'meets_specs' => 'boolean',
        'not_available' => 'boolean',
```

Agregar los scopes dentro de la clase (por ejemplo después del bloque `$casts`):

```php
    /**
     * Solo respuestas con partida cotizada (no marcadas como no disponible).
     */
    public function scopeQuoted($query)
    {
        return $query->where('not_available', false);
    }

    /**
     * Solo respuestas de partidas marcadas como no disponibles por el proveedor.
     */
    public function scopeNotAvailable($query)
    {
        return $query->where('not_available', true);
    }
```

- [ ] **Step 5: Correr el test y verificar que pasa**

Run: `php artisan test --filter=RfqResponseNotAvailableTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_06_22_100000_add_not_available_to_rfq_responses_table.php app/Models/RfqResponse.php tests/Feature/RfqResponseNotAvailableTest.php
git commit -m "feat: agregar columna not_available a rfq_responses con scopes"
```

---

### Task 2: Backend del proveedor — guardar partidas no disponibles

**Files:**
- Modify: `app/Http/Controllers/SupplierPortalController.php`
  - `validateQuotationData` (~línea 39-78)
  - `calculateItemTotals` (~línea 85-103)
  - `saveQuotationItem` (~línea 108-145)
- Test: `tests/Feature/SupplierPartialQuotationTest.php`

**Interfaces:**
- Consumes: columna y modelo de Task 1.
- Produces: el endpoint `POST supplier.rfq.quotation.save` acepta `items[i][not_available]=1`, guarda fila con montos en 0 y `not_available=true`, y exenta a esas partidas de las reglas de precio/cantidad/IVA/entrega.

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Feature/SupplierPartialQuotationTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\RequisitionItem;
use App\Models\RfqResponse;
use App\Models\Rfq;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierPartialQuotationTest extends TestCase
{
    use RefreshDatabase;

    private function makeRfqForSupplier(Supplier $supplier): array
    {
        $rfq = Rfq::factory()->create();
        $rfq->suppliers()->attach($supplier->id, ['invited_at' => now()]);
        $itemA = RequisitionItem::factory()->create(['requisition_id' => $rfq->requisition_id]);
        $itemB = RequisitionItem::factory()->create(['requisition_id' => $rfq->requisition_id]);

        return [$rfq, $itemA, $itemB];
    }

    public function test_submit_stores_priced_and_not_available_items_correctly(): void
    {
        $supplier = Supplier::factory()->create();
        [$rfq, $itemA, $itemB] = $this->makeRfqForSupplier($supplier);

        $this->actingAs($supplier, 'supplier')
            ->withoutMiddleware()
            ->post(route('supplier.rfq.quotation.save', $rfq), [
                'action' => 'submit',
                'supplier_quotation_number' => 'COT-1',
                'validity_days' => 30,
                'items' => [
                    ['item_id' => $itemA->id, 'unit_price' => 100, 'quantity' => 2, 'iva_rate' => 16, 'delivery_days' => 5, 'currency' => 'MXN'],
                    ['item_id' => $itemB->id, 'not_available' => 1],
                ],
            ])->assertRedirect();

        $priced = RfqResponse::where('requisition_item_id', $itemA->id)->first();
        $this->assertFalse($priced->not_available);
        $this->assertEquals(200.00, (float) $priced->subtotal);
        $this->assertEquals('SUBMITTED', $priced->status);

        $unavailable = RfqResponse::where('requisition_item_id', $itemB->id)->first();
        $this->assertTrue($unavailable->not_available);
        $this->assertEquals(0.0, (float) $unavailable->total);
        $this->assertEquals('SUBMITTED', $unavailable->status);
    }

    public function test_submit_allowed_when_all_items_not_available(): void
    {
        $supplier = Supplier::factory()->create();
        [$rfq, $itemA, $itemB] = $this->makeRfqForSupplier($supplier);

        $this->actingAs($supplier, 'supplier')
            ->withoutMiddleware()
            ->post(route('supplier.rfq.quotation.save', $rfq), [
                'action' => 'submit',
                'items' => [
                    ['item_id' => $itemA->id, 'not_available' => 1],
                    ['item_id' => $itemB->id, 'not_available' => 1],
                ],
            ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertEquals(2, RfqResponse::notAvailable()->count());
    }
}
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `php artisan test --filter=SupplierPartialQuotationTest`
Expected: FAIL — la validación exige `unit_price`/`delivery_days` para la partida no disponible (errores de sesión) y/o `not_available` no se persiste.

- [ ] **Step 3: Actualizar `validateQuotationData`**

Reemplazar el bloque del filtro de borrador y las reglas en `app/Http/Controllers/SupplierPortalController.php`.

Filtro de borrador (reemplazar el `array_filter` existente):

```php
        if ($request->input('action') === 'save_draft' && is_array($request->input('items'))) {
            $items = array_filter(
                $request->input('items'),
                static function ($item) {
                    $hasPrice = isset($item['unit_price']) && trim((string) $item['unit_price']) !== '';
                    $notAvailable = ! empty($item['not_available']) && (string) $item['not_available'] === '1';

                    return $hasPrice || $notAvailable;
                }
            );
            $request->merge(['items' => $items]);
        }
```

Reglas dentro de `$request->validate([...])` — reemplazar las reglas de `unit_price`, `quantity`, `iva_rate` y `delivery_days`, y agregar `not_available`. `exclude_if` omite la regla y el valor cuando la partida es no disponible (alineado por índice con el wildcard):

```php
            'items' => 'nullable|array',
            'items.*.item_id' => 'required|exists:requisition_items,id',
            'items.*.not_available' => 'nullable|boolean',
            'items.*.unit_price' => ['exclude_if:items.*.not_available,1', 'required', 'numeric', 'min:0'],
            'items.*.quantity' => ['exclude_if:items.*.not_available,1', 'required', 'numeric', 'min:0.01'],
            'items.*.iva_rate' => ['exclude_if:items.*.not_available,1', 'required', 'numeric', 'in:0,8,16'],
            'items.*.currency' => 'nullable|string|in:MXN,USD,EUR',
            'items.*.delivery_days' => [
                'exclude_if:items.*.not_available,1',
                Rule::requiredIf(fn () => $request->input('action') === 'submit'),
                'nullable',
                'integer',
                'min:0',
            ],
```

(El resto de las reglas —`payment_terms`, `warranty_terms`, `brand`, `model`, `specifications`, `notes`, `attachment`, `supplier_quotation_number`, `validity_days`, `quotation_pdf_file`, `action`— se mantienen igual.)

- [ ] **Step 4: Actualizar `calculateItemTotals`**

Al inicio de `calculateItemTotals`, antes de leer `unit_price`, agregar el cortocircuito para partidas no disponibles:

```php
    private function calculateItemTotals(array $itemData): array
    {
        $notAvailable = ! empty($itemData['not_available']) && (string) $itemData['not_available'] === '1';

        if ($notAvailable) {
            return [
                'unit_price' => 0,
                'quantity' => 0,
                'subtotal' => 0,
                'iva_rate' => 0,
                'iva_amount' => 0,
                'total' => 0,
            ];
        }

        $unitPrice = $itemData['unit_price'];
        // ... resto sin cambios
```

- [ ] **Step 5: Actualizar `saveQuotationItem`**

Dentro de `saveQuotationItem`, calcular el flag y agregarlo al array de atributos del `updateOrCreate`:

```php
        $totals = $this->calculateItemTotals($itemData);
        $notAvailable = ! empty($itemData['not_available']) && (string) $itemData['not_available'] === '1';

        return RfqResponse::updateOrCreate(
            [
                'rfq_id' => $rfq->id,
                'supplier_id' => $supplierId,
                'requisition_item_id' => $itemId,
            ],
            [
                'unit_price' => $totals['unit_price'],
                'quantity' => $totals['quantity'],
                'subtotal' => $totals['subtotal'],
                'iva_rate' => $totals['iva_rate'],
                'iva_amount' => $totals['iva_amount'],
                'total' => $totals['total'],
                'not_available' => $notAvailable,
                'currency' => $itemData['currency'] ?? 'MXN',
                // ... resto de los campos sin cambios
```

- [ ] **Step 6: Correr el test y verificar que pasa**

Run: `php artisan test --filter=SupplierPartialQuotationTest`
Expected: PASS (2 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/SupplierPortalController.php tests/Feature/SupplierPartialQuotationTest.php
git commit -m "feat: backend del proveedor acepta partidas no disponibles en el envío"
```

---

### Task 3: Vista del proveedor — toggle "No puedo cotizar" + confirmación

**Files:**
- Modify: `resources/views/supplier/rfq-detail.blade.php`
  - Cabecera de cada `.quotation-item` (~línea 450-516): agregar toggle + badge.
  - Inputs de precio (~línea 519-613): agregar input hidden `not_available` y `data-*`.
  - JS `validateFieldsBeforeSubmit` (~línea 1236-1261): solo exigir precio en partidas cotizadas.
  - JS evento submit (~línea 1212-1234): listar partidas no disponibles en la confirmación.
  - JS de totales/resumen (~línea 891-987): excluir partidas no disponibles.
- Test: `tests/Feature/SupplierRfqDetailRendersToggleTest.php`

**Interfaces:**
- Consumes: el contrato de campo de Task 2 (`items[i][not_available]`).
- Produces: UI que envía `items[i][not_available]=1` cuando el proveedor marca la partida.

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Feature/SupplierRfqDetailRendersToggleTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\QuotationGroup;
use App\Models\RequisitionItem;
use App\Models\Rfq;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierRfqDetailRendersToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_quotation_page_shows_not_available_toggle(): void
    {
        $supplier = Supplier::factory()->create();
        $rfq = Rfq::factory()->create(['status' => 'SENT']);
        $rfq->suppliers()->attach($supplier->id, ['invited_at' => now()]);

        $group = QuotationGroup::factory()->create(['requisition_id' => $rfq->requisition_id]);
        $item = RequisitionItem::factory()->create(['requisition_id' => $rfq->requisition_id]);
        $group->items()->attach($item->id, ['sort_order' => 1]);
        $rfq->update(['quotation_group_id' => $group->id]);

        $this->actingAs($supplier, 'supplier')
            ->withoutMiddleware()
            ->get(route('supplier.rfq.show', $rfq))
            ->assertOk()
            ->assertSee('No puedo cotizar esta partida');
    }
}
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `php artisan test --filter=SupplierRfqDetailRendersToggleTest`
Expected: FAIL — la cadena "No puedo cotizar esta partida" no existe en la vista.

- [ ] **Step 3: Agregar el toggle + input hidden en cada partida**

En `resources/views/supplier/rfq-detail.blade.php`, justo después del input hidden del `item_id` (línea ~519, `<input type="hidden" name="{{ $itemPrefix }}[item_id]" ...>`), insertar:

```blade
                                    {{-- Marca de "no disponible" para esta partida --}}
                                    <input type="hidden"
                                           class="item-not-available-flag"
                                           name="{{ $itemPrefix }}[not_available]"
                                           value="{{ old("{$itemPrefix}[not_available]", ($existingResponse && $existingResponse->not_available) ? '1' : '0') }}"
                                           {{ $isLocked ? 'disabled' : '' }}>

                                    @unless($isLocked)
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input toggle-not-available" type="checkbox"
                                               id="not_available_{{ $index }}"
                                               {{ ($existingResponse && $existingResponse->not_available) ? 'checked' : '' }}>
                                        <label class="form-check-label small text-muted" for="not_available_{{ $index }}">
                                            <i class="ti ti-ban me-1"></i>No puedo cotizar esta partida
                                        </label>
                                    </div>
                                    @endunless

                                    <span class="badge bg-warning text-dark item-unavailable-badge mb-2 {{ ($existingResponse && $existingResponse->not_available) ? '' : 'd-none' }}">
                                        <i class="ti ti-ban me-1"></i>Marcada como no disponible
                                    </span>
```

- [ ] **Step 4: JS — alternar campos al marcar la partida**

Dentro del primer `$(document).ready(function() { ... })` (después del bloque de cálculo de totales, antes del cierre en línea ~1263), agregar:

```javascript
    // =========================================================================
    // Marcar/desmarcar partida como "no disponible"
    // =========================================================================
    function applyNotAvailableState($item, isUnavailable) {
        const $fields = $item.find('.unit-price, .quantity, .iva-rate, input[name$="[delivery_days]"]');
        $item.find('.item-not-available-flag').val(isUnavailable ? '1' : '0');
        $item.find('.item-unavailable-badge').toggleClass('d-none', !isUnavailable);
        $item.toggleClass('opacity-50', isUnavailable);

        if (isUnavailable) {
            $item.find('.unit-price, .quantity').val('');
        }
        $fields.prop('disabled', isUnavailable).removeClass('is-invalid');

        const index = $item.data('item-index');
        calculateItemTotals(index);
    }

    $('.toggle-not-available').on('change', function() {
        const $item = $(this).closest('.quotation-item');
        applyNotAvailableState($item, this.checked);
    });

    // Estado inicial (por old() o datos guardados)
    $('.toggle-not-available').each(function() {
        if (this.checked) {
            applyNotAvailableState($(this).closest('.quotation-item'), true);
        }
    });
```

- [ ] **Step 5: JS — excluir partidas no disponibles del resumen/total**

En `updateSummaryPanel` (línea ~916) y `calculateGrandTotal` (línea ~953), saltar las partidas marcadas. Al inicio del callback `$('.quotation-item').each(function(...) {` de cada función, agregar:

En `updateSummaryPanel`, dentro del `.each`:

```javascript
        $('.quotation-item').each(function(index) {
            const $item = $(this);
            const itemNumber = index + 1;
            const isUnavailable = $item.find('.item-not-available-flag').val() === '1';

            if (isUnavailable) {
                summaryHtml += `
                    <div class="d-flex justify-content-between align-items-start mb-2 text-warning">
                        <div class="flex-grow-1 me-2">
                            <span class="badge bg-warning text-dark badge-sm me-1">${itemNumber}</span>
                            <small>No disponible</small>
                        </div>
                        <strong class="text-nowrap small">—</strong>
                    </div>`;
                return; // continue
            }
            // ... resto del cuerpo existente sin cambios
```

En `calculateGrandTotal`, dentro del `.each`:

```javascript
        $('.quotation-item').each(function() {
            if ($(this).find('.item-not-available-flag').val() === '1') {
                return; // no suma
            }
            const subtotal = parseFloat($(this).find('.subtotal').val()) || 0;
            // ... resto sin cambios
```

- [ ] **Step 6: JS — validar solo partidas cotizadas y listar no disponibles en la confirmación**

Reemplazar el cuerpo del bucle de validación en `validateFieldsBeforeSubmit` (línea ~1248) para saltar partidas no disponibles:

```javascript
        let hasErrors = false;
        $('.quotation-item').each(function() {
            if ($(this).find('.item-not-available-flag').val() === '1') {
                return; // partida no disponible: no requiere precio
            }
            const unitPrice = $(this).find('.unit-price').val();
            if (!unitPrice || parseFloat(unitPrice) <= 0) {
                $(this).find('.unit-price').addClass('is-invalid');
                hasErrors = true;
            }
        });
```

En el evento `$('#submit-quotation-btn').on('click', ...)` (línea ~1212), después de `if (!validateFieldsBeforeSubmit()) return;`, construir la lista de no disponibles y agregar un paso al modal de confirmación:

```javascript
        if (!validateFieldsBeforeSubmit()) return;

        // Recolectar partidas marcadas como no disponibles
        const unavailableNames = [];
        $('.quotation-item').each(function() {
            if ($(this).find('.item-not-available-flag').val() === '1') {
                unavailableNames.push($(this).find('h6.fw-bold').text().trim());
            }
        });

        const grandTotal = $('#grand-total').text();
        const baseSteps = [
            `<i class="ti ti-currency-dollar step-icon"></i> <div class="step-content"><strong>Monto Total:</strong> Se enviará una oferta formal por <strong>$${grandTotal}</strong> (IVA incluido).</div>`,
            '<i class="ti ti-lock step-icon"></i> <div class="step-content"><strong>Bloqueo de Edición:</strong> Una vez enviada, la cotización quedará en estado <strong>RECIBIDA</strong> y no podrá ser modificada.</div>',
            '<i class="ti ti-bell-ringing step-icon"></i> <div class="step-content"><strong>Notificación:</strong> El departamento de compras será notificado inmediatamente para iniciar el proceso de comparativa.</div>'
        ];

        if (unavailableNames.length > 0) {
            const lista = unavailableNames.map(n => `• ${n}`).join('<br>');
            baseSteps.unshift(
                `<i class="ti ti-ban step-icon"></i> <div class="step-content"><strong>Sin incluir ${unavailableNames.length} partida(s):</strong><br>${lista}<br>El comprador será notificado de esta falta de producto.</div>`
            );
        }

        confirmAction({
            title: 'Enviar Cotización Formal',
            headerIcon: 'ti ti-send',
            headerText: 'Efectos del envío definitivo',
            actionValue: 'submit',
            confirmColor: '#1a5276',
            icon: 'warning',
            confirmButtonText: 'Confirmar Envío',
            checkboxText: 'Confirmo que los montos y documentos son correctos',
            steps: baseSteps
        });
```

(Eliminar el `confirmAction({...})` anterior de ese handler, ya que se reemplaza por el bloque de arriba.)

- [ ] **Step 7: Correr el test y verificar que pasa**

Run: `php artisan test --filter=SupplierRfqDetailRendersToggleTest`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add resources/views/supplier/rfq-detail.blade.php tests/Feature/SupplierRfqDetailRendersToggleTest.php
git commit -m "feat: toggle de partida no disponible y confirmación de envío parcial"
```

---

### Task 4: Guardas para que la ODC nunca incluya partidas no disponibles

**Files:**
- Modify: `app/Http/Controllers/QuotationApprovalController.php` (`generatePurchaseOrder` ~línea 205-208)
- Modify: `app/Http/Controllers/RfqComparisonController.php` (`select` ~línea 69-73; `buildSupplierDiagnostics` ~línea 201-204)
- Test: `tests/Feature/PurchaseOrderExcludesNotAvailableTest.php`

**Interfaces:**
- Consumes: columna/scopes de Task 1.
- Produces: las consultas de adjudicación, diagnóstico y generación de ODC filtran `not_available = false`.

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Feature/PurchaseOrderExcludesNotAvailableTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\RequisitionItem;
use App\Models\Rfq;
use App\Models\RfqResponse;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderExcludesNotAvailableTest extends TestCase
{
    use RefreshDatabase;

    public function test_winning_responses_query_excludes_not_available(): void
    {
        $supplier = Supplier::factory()->create();
        $rfq = Rfq::factory()->create();
        $itemA = RequisitionItem::factory()->create(['requisition_id' => $rfq->requisition_id]);
        $itemB = RequisitionItem::factory()->create(['requisition_id' => $rfq->requisition_id]);

        $quoted = RfqResponse::factory()->create([
            'rfq_id' => $rfq->id, 'supplier_id' => $supplier->id,
            'requisition_item_id' => $itemA->id, 'status' => 'SUBMITTED',
            'subtotal' => 100, 'iva_amount' => 16, 'total' => 116, 'not_available' => false,
        ]);
        RfqResponse::factory()->create([
            'rfq_id' => $rfq->id, 'supplier_id' => $supplier->id,
            'requisition_item_id' => $itemB->id, 'status' => 'SUBMITTED',
            'subtotal' => 0, 'iva_amount' => 0, 'total' => 0, 'not_available' => true,
        ]);

        // Réplica exacta de la consulta usada por generatePurchaseOrder (con la guarda).
        $winning = RfqResponse::where('rfq_id', $rfq->id)
            ->where('supplier_id', $supplier->id)
            ->where('not_available', false)
            ->get();

        $this->assertCount(1, $winning);
        $this->assertEquals($quoted->id, $winning->first()->id);
    }
}
```

> Nota: este test fija el predicado `not_available = false`. Los Steps 2-3 aplican ese mismo predicado en las tres consultas del flujo de adjudicación/ODC. El test no fallará por el cambio de código (es una réplica), por lo que **el Step adicional verifica la presencia del predicado en el código** vía `grep` en Step 4.

- [ ] **Step 2: Guardar la generación de ODC**

En `app/Http/Controllers/QuotationApprovalController.php`, dentro de `generatePurchaseOrder`, agregar el filtro a `$winningResponses`:

```php
        $winningResponses = RfqResponse::with('requisitionItem')
            ->where('rfq_id', $summary->rfq_id)
            ->where('supplier_id', $summary->selected_supplier_id)
            ->where('not_available', false)
            ->get();
```

- [ ] **Step 3: Guardar adjudicación y diagnóstico**

En `app/Http/Controllers/RfqComparisonController.php`, método `select`, agregar el filtro a la consulta de totales:

```php
        $totals = $rfq->rfqResponses()
            ->where('supplier_id', $request->integer('supplier_id'))
            ->where('status', 'SUBMITTED')
            ->where('not_available', false)
            ->selectRaw('SUM(subtotal) as subtotal, SUM(iva_amount) as iva, SUM(total) as total')
            ->first();
```

En `buildSupplierDiagnostics`, filtrar la colección de respuestas:

```php
        $responses = $rfq->rfqResponses
            ->where('supplier_id', $supplierId)
            ->where('status', 'SUBMITTED')
            ->where('not_available', false)
            ->values();
```

- [ ] **Step 4: Correr el test y verificar el predicado**

Run: `php artisan test --filter=PurchaseOrderExcludesNotAvailableTest`
Expected: PASS.

Verificar que el predicado quedó en las tres consultas:

Run: `grep -rn "not_available', false" app/Http/Controllers/QuotationApprovalController.php app/Http/Controllers/RfqComparisonController.php`
Expected: 3 coincidencias (1 en QuotationApprovalController, 2 en RfqComparisonController).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/QuotationApprovalController.php app/Http/Controllers/RfqComparisonController.php tests/Feature/PurchaseOrderExcludesNotAvailableTest.php
git commit -m "feat: excluir partidas no disponibles de adjudicación, diagnóstico y ODC"
```

---

### Task 5: Métodos del modelo `Rfq` para estadísticas de comparativa

**Files:**
- Modify: `app/Models/Rfq.php` (agregar `use Illuminate\Support\Collection;` y dos métodos en la sección de lógica de negocio, después de `getItemsToQuote` ~línea 316)
- Test: `tests/Feature/RfqComparisonStatsTest.php`

**Interfaces:**
- Consumes: columna/scopes de Task 1; `getItemsToQuote()` existente.
- Produces:
  - `Rfq::quotedItemCountForSupplier(int $supplierId): int` — partidas distintas cotizadas (no disponibles excluidas) por un proveedor (status SUBMITTED o SELECTED).
  - `Rfq::itemsQuotedByNoSupplier(): \Illuminate\Support\Collection` — partidas de la RFQ que ningún proveedor cotizó.

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Feature/RfqComparisonStatsTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\QuotationGroup;
use App\Models\RequisitionItem;
use App\Models\Rfq;
use App\Models\RfqResponse;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfqComparisonStatsTest extends TestCase
{
    use RefreshDatabase;

    private function buildRfqWithItems(int $itemCount): array
    {
        $rfq = Rfq::factory()->create();
        $group = QuotationGroup::factory()->create(['requisition_id' => $rfq->requisition_id]);
        $items = [];
        for ($i = 0; $i < $itemCount; $i++) {
            $item = RequisitionItem::factory()->create(['requisition_id' => $rfq->requisition_id]);
            $group->items()->attach($item->id, ['sort_order' => $i + 1]);
            $items[] = $item;
        }
        $rfq->update(['quotation_group_id' => $group->id]);

        return [$rfq->fresh(), $items];
    }

    public function test_quoted_item_count_excludes_not_available(): void
    {
        [$rfq, $items] = $this->buildRfqWithItems(3);
        $supplier = Supplier::factory()->create();

        RfqResponse::factory()->create(['rfq_id' => $rfq->id, 'supplier_id' => $supplier->id, 'requisition_item_id' => $items[0]->id, 'status' => 'SUBMITTED', 'not_available' => false]);
        RfqResponse::factory()->create(['rfq_id' => $rfq->id, 'supplier_id' => $supplier->id, 'requisition_item_id' => $items[1]->id, 'status' => 'SUBMITTED', 'not_available' => true]);

        $this->assertEquals(1, $rfq->quotedItemCountForSupplier($supplier->id));
    }

    public function test_items_quoted_by_no_supplier_returns_only_uncovered_items(): void
    {
        [$rfq, $items] = $this->buildRfqWithItems(2);
        $supplierA = Supplier::factory()->create();
        $supplierB = Supplier::factory()->create();

        // item[0] lo cotiza A; item[1] nadie (B lo marca no disponible)
        RfqResponse::factory()->create(['rfq_id' => $rfq->id, 'supplier_id' => $supplierA->id, 'requisition_item_id' => $items[0]->id, 'status' => 'SUBMITTED', 'not_available' => false]);
        RfqResponse::factory()->create(['rfq_id' => $rfq->id, 'supplier_id' => $supplierB->id, 'requisition_item_id' => $items[1]->id, 'status' => 'SUBMITTED', 'not_available' => true]);

        $uncovered = $rfq->itemsQuotedByNoSupplier();

        $this->assertEquals([$items[1]->id], $uncovered->pluck('id')->all());
    }
}
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `php artisan test --filter=RfqComparisonStatsTest`
Expected: FAIL — `Call to undefined method ...::quotedItemCountForSupplier()`.

- [ ] **Step 3: Agregar el import y los métodos al modelo `Rfq`**

En `app/Models/Rfq.php`, agregar el import junto a los demás `use`:

```php
use Illuminate\Support\Collection;
```

Después del método `getItemsToQuote()`, agregar:

```php
    /**
     * Cuenta las partidas distintas que un proveedor sí cotizó
     * (excluye las marcadas como no disponibles).
     */
    public function quotedItemCountForSupplier(int $supplierId): int
    {
        return $this->rfqResponses()
            ->where('supplier_id', $supplierId)
            ->where('not_available', false)
            ->whereIn('status', ['SUBMITTED', 'SELECTED'])
            ->distinct()
            ->count('requisition_item_id');
    }

    /**
     * Partidas de la RFQ que ningún proveedor cotizó
     * (todos las marcaron no disponible o no respondieron).
     */
    public function itemsQuotedByNoSupplier(): Collection
    {
        $quotedItemIds = $this->rfqResponses()
            ->where('not_available', false)
            ->whereIn('status', ['SUBMITTED', 'SELECTED'])
            ->pluck('requisition_item_id')
            ->unique();

        return $this->getItemsToQuote()
            ->reject(fn ($item) => $quotedItemIds->contains($item->id))
            ->values();
    }
```

- [ ] **Step 4: Correr el test y verificar que pasa**

Run: `php artisan test --filter=RfqComparisonStatsTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Models/Rfq.php tests/Feature/RfqComparisonStatsTest.php
git commit -m "feat: métodos de Rfq para estadísticas de partidas cotizadas/faltantes"
```

---

### Task 6: Vista del comprador — badges, resumen y botón/modal

**Files:**
- Modify: `app/Http/Controllers/RfqComparisonController.php` (`index` ~línea 28-54)
- Modify: `routes/web.php` (registrar la ruta del modal; el método se agrega en Task 7)
- Modify: `resources/views/rfq/comparison/index.blade.php`
  - Cabecera de proveedor (~línea 130-165): resumen "X de Y".
  - Celda de partida (~línea 180-249): badge "Producto no disponible".
  - Zona de acciones (~línea 32-60): botón "Generar RFQ con partidas faltantes".
  - Final del archivo (antes de `@endsection`): modal.
- Test: `tests/Feature/RfqComparisonViewTest.php`

**Interfaces:**
- Consumes: métodos de Task 5; `not_available` en respuestas.
- Produces: variables de vista `$itemsNobodyQuoted` y `$approvedSuppliers`; UI del comprador. El modal hace POST a `rfq.comparison.generate-complementary` (Task 7).

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Feature/RfqComparisonViewTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\QuotationGroup;
use App\Models\RequisitionItem;
use App\Models\Rfq;
use App\Models\RfqResponse;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfqComparisonViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_comparison_shows_not_available_badge_and_summary_and_button(): void
    {
        $user = User::factory()->create();
        $rfq = Rfq::factory()->create(['status' => 'RECEIVED']);
        $group = QuotationGroup::factory()->create(['requisition_id' => $rfq->requisition_id]);
        $itemA = RequisitionItem::factory()->create(['requisition_id' => $rfq->requisition_id]);
        $itemB = RequisitionItem::factory()->create(['requisition_id' => $rfq->requisition_id]);
        $group->items()->attach($itemA->id, ['sort_order' => 1]);
        $group->items()->attach($itemB->id, ['sort_order' => 2]);
        $rfq->update(['quotation_group_id' => $group->id]);

        $supplier = Supplier::factory()->create();
        $rfq->suppliers()->attach($supplier->id, ['invited_at' => now(), 'responded_at' => now()]);

        RfqResponse::factory()->create(['rfq_id' => $rfq->id, 'supplier_id' => $supplier->id, 'requisition_item_id' => $itemA->id, 'status' => 'SUBMITTED', 'not_available' => false]);
        RfqResponse::factory()->create(['rfq_id' => $rfq->id, 'supplier_id' => $supplier->id, 'requisition_item_id' => $itemB->id, 'status' => 'SUBMITTED', 'not_available' => true]);

        $this->actingAs($user)
            ->withoutMiddleware()
            ->get(route('rfq.comparison.index', $rfq))
            ->assertOk()
            ->assertSee('Producto no disponible')
            ->assertSee('1 de 2 partidas cotizadas')
            ->assertSee('Generar RFQ con partidas faltantes');
    }
}
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `php artisan test --filter=RfqComparisonViewTest`
Expected: FAIL — las cadenas no existen aún.

- [ ] **Step 3: Pasar datos nuevos desde `index`**

En `app/Http/Controllers/RfqComparisonController.php`, en `index`, agregar el cálculo y pasarlos a la vista:

```php
        $items = $rfq->getItemsToQuote();
        $approvalLevels = $this->approvalService->getAllLevels();
        $itemsNobodyQuoted = $rfq->itemsQuotedByNoSupplier();
        $approvedSuppliers = \App\Models\Supplier::approved()->orderBy('company_name')->get();
        $supplierDiagnostics = $rfq->suppliers
            ->mapWithKeys(fn ($supplier) => [
                $supplier->id => $this->buildSupplierDiagnostics($rfq, $supplier->id),
            ])
            ->all();

        return view('rfq.comparison.index', [
            'rfq' => $rfq,
            'items' => $items,
            'presupuestoDisponible' => null,
            'approvalLevels' => $approvalLevels,
            'supplierDiagnostics' => $supplierDiagnostics,
            'itemsNobodyQuoted' => $itemsNobodyQuoted,
            'approvedSuppliers' => $approvedSuppliers,
        ]);
```

- [ ] **Step 4: Resumen "X de Y" en la cabecera del proveedor**

En `resources/views/rfq/comparison/index.blade.php`, dentro del `<th>` de proveedor, justo después del `<div>` con `{{ $supplier->company_name }}` (línea ~133), insertar:

```blade
                                    <div class="small text-muted mt-1">
                                        {{ $rfq->quotedItemCountForSupplier($supplier->id) }} de {{ $items->count() }} partidas cotizadas
                                    </div>
```

- [ ] **Step 5: Badge "Producto no disponible" en la celda de partida**

En la celda de la matriz, reemplazar el bloque `@if($resp) ... @else ... @endif` (líneas ~181-249) para anteponer la rama de no disponible. Cambiar la apertura:

```blade
                                    <td class="{{ $resp ? ($resp->not_available ? 'text-center' : '') : 'bg-soft-danger text-center' }}">
                                        @if($resp && $resp->not_available)
                                            <span class="badge bg-soft-warning text-warning border border-warning border-opacity-25 fs-11">
                                                <i class="ti ti-ban me-1"></i>Producto no disponible
                                            </span>
                                        @elseif($resp)
                                            {{-- 💰 PRECIO, MARCA Y MONEDA --}}
                                            {{-- ... contenido existente de la oferta SIN cambios ... --}}
                                        @else
                                            <span class="text-danger fw-bold fs-11"><i class="ti ti-clock-exclamation me-1"></i>SIN OFERTA</span>
                                        @endif
                                    </td>
```

(El contenido entre `@elseif($resp)` y `@else` es exactamente el bloque que hoy está dentro de `@if($resp)`; solo cambia el condicional de apertura. No tocar ese contenido.)

- [ ] **Step 6: Botón "Generar RFQ con partidas faltantes"**

En la zona de acciones superior (dentro del `<div class="float-end ...">` o junto a los botones existentes ~línea 43-46), agregar:

```blade
                    @if($itemsNobodyQuoted->isNotEmpty())
                        <button type="button" class="btn btn-outline-primary btn-sm ms-2" id="btnGenerateComplementaryRfq">
                            <i class="ti ti-file-plus me-1"></i>Generar RFQ con partidas faltantes ({{ $itemsNobodyQuoted->count() }})
                        </button>
                    @endif
```

- [ ] **Step 7: Registrar la ruta del modal**

El modal genera su `action` con `route('rfq.comparison.generate-complementary', $rfq)`, así que la ruta debe existir antes de renderizar la página (el método del controlador se agrega en Task 7; la resolución del método solo ocurre al hacer POST, no al construir la URL). En `routes/web.php`, junto a las rutas de comparación (después de `rfq.comparison.cancel-rejected` ~línea 617):

```php
    Route::middleware('module.access:quotations')->post('/rfq/{rfq}/generate-complementary', [RfqComparisonController::class, 'generateComplementaryRfq'])->name('rfq.comparison.generate-complementary');
```

- [ ] **Step 8: Modal de generación**

Antes de `@endsection`, agregar el modal y su script:

```blade
@if($itemsNobodyQuoted->isNotEmpty())
<div class="modal fade" id="complementaryRfqModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('rfq.comparison.generate-complementary', $rfq) }}">
                @csrf
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="ti ti-file-plus me-2"></i>Generar RFQ con partidas faltantes</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Estas partidas no fueron cotizadas por ningún proveedor. Selecciona cuáles incluir y a qué proveedores enviar la nueva solicitud.</p>

                    <label class="fw-bold small d-block mb-2">Partidas a incluir</label>
                    @foreach($itemsNobodyQuoted as $item)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="item_ids[]" value="{{ $item->id }}" id="citem_{{ $item->id }}" checked>
                            <label class="form-check-label small" for="citem_{{ $item->id }}">
                                {{ $item->description }} <span class="text-muted">({{ number_format($item->quantity, 2) }} {{ $item->unit }})</span>
                            </label>
                        </div>
                    @endforeach

                    <hr>

                    <label class="fw-bold small d-block mb-2">Proveedores destino</label>
                    <select name="supplier_ids[]" class="form-select form-select-sm" multiple size="6" required>
                        @foreach($approvedSuppliers as $sup)
                            <option value="{{ $sup->id }}">{{ $sup->company_name }}</option>
                        @endforeach
                    </select>

                    <div class="row g-2 mt-2">
                        <div class="col-md-6">
                            <label class="form-label form-label-sm">Fecha límite de respuesta</label>
                            <input type="date" name="response_deadline" class="form-control form-control-sm" required min="{{ now()->addDay()->format('Y-m-d') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-sm">Mensaje (opcional)</label>
                            <input type="text" name="message" class="form-control form-control-sm" placeholder="Instrucciones para el proveedor">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="ti ti-send me-1"></i>Generar y enviar</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.getElementById('btnGenerateComplementaryRfq')?.addEventListener('click', function () {
        new bootstrap.Modal(document.getElementById('complementaryRfqModal')).show();
    });
</script>
@endpush
@endif
```

- [ ] **Step 9: Correr el test y verificar que pasa**

Run: `php artisan test --filter=RfqComparisonViewTest`
Expected: PASS.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/RfqComparisonController.php routes/web.php resources/views/rfq/comparison/index.blade.php tests/Feature/RfqComparisonViewTest.php
git commit -m "feat: comparativa muestra partidas no disponibles, resumen y modal de RFQ complementario"
```

---

### Task 7: Generación del RFQ complementario (acción + ruta)

**Files:**
- Modify: `app/Http/Controllers/RfqComparisonController.php` (agregar `generateComplementaryRfq`; ya tiene `use ... DB, Auth, Log, Throwable`)
- Test: `tests/Feature/GenerateComplementaryRfqTest.php`

> La ruta `rfq.comparison.generate-complementary` ya fue registrada en Task 6 Step 7. Esta tarea solo agrega el método del controlador que la atiende.

**Interfaces:**
- Consumes: `supersedes_rfq_id` (ya existe), `QuotationGroup`, pivote `rfq_suppliers`, `Rfq::nextFolio()`; ruta registrada en Task 6.
- Produces: `RfqComparisonController::generateComplementaryRfq` crea `QuotationGroup` + `Rfq` (`status=SENT`, `supersedes_rfq_id`) con proveedores adjuntos.

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Feature/GenerateComplementaryRfqTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\QuotationGroup;
use App\Models\RequisitionItem;
use App\Models\Rfq;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateComplementaryRfqTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_sent_rfq_with_selected_items_and_suppliers(): void
    {
        $user = User::factory()->create();
        $origin = Rfq::factory()->create();
        $itemA = RequisitionItem::factory()->create(['requisition_id' => $origin->requisition_id]);
        $itemB = RequisitionItem::factory()->create(['requisition_id' => $origin->requisition_id]);
        $supplier = Supplier::factory()->create();

        $this->actingAs($user)
            ->withoutMiddleware()
            ->post(route('rfq.comparison.generate-complementary', $origin), [
                'item_ids' => [$itemA->id, $itemB->id],
                'supplier_ids' => [$supplier->id],
                'response_deadline' => now()->addDays(5)->format('Y-m-d'),
                'message' => 'Faltó producto',
            ])->assertRedirect(route('rfq.comparison.index', $origin));

        $new = Rfq::where('supersedes_rfq_id', $origin->id)->first();
        $this->assertNotNull($new);
        $this->assertEquals('SENT', $new->status);
        $this->assertNotNull($new->sent_at);
        $this->assertEquals($origin->requisition_id, $new->requisition_id);

        $group = QuotationGroup::find($new->quotation_group_id);
        $this->assertNotNull($group);
        $this->assertEqualsCanonicalizing(
            [$itemA->id, $itemB->id],
            $group->items()->pluck('requisition_items.id')->all()
        );

        $this->assertTrue($new->suppliers->contains($supplier->id));
    }
}
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `php artisan test --filter=GenerateComplementaryRfqTest`
Expected: FAIL — el POST resuelve a `RfqComparisonController::generateComplementaryRfq`, que aún no existe (error de método no encontrado / 500), por lo que `assertRedirect` falla.

- [ ] **Step 3: Agregar el método al controlador**

En `app/Http/Controllers/RfqComparisonController.php`, agregar el método (usa `QuotationGroup`; agregar `use App\Models\QuotationGroup;` junto a los demás `use`):

```php
    public function generateComplementaryRfq(Request $request, Rfq $rfq)
    {
        $validated = $request->validate([
            'item_ids' => 'required|array|min:1',
            'item_ids.*' => 'integer|exists:requisition_items,id',
            'supplier_ids' => 'required|array|min:1',
            'supplier_ids.*' => 'integer|exists:suppliers,id',
            'response_deadline' => 'required|date|after:today',
            'message' => 'nullable|string',
        ]);

        try {
            $newRfq = DB::transaction(function () use ($validated, $rfq) {
                $group = QuotationGroup::create([
                    'requisition_id' => $rfq->requisition_id,
                    'name' => 'Complemento de '.$rfq->folio,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]);

                $attach = [];
                foreach (array_values($validated['item_ids']) as $i => $itemId) {
                    $attach[$itemId] = ['sort_order' => $i + 1];
                }
                $group->items()->attach($attach);

                $newRfq = Rfq::create([
                    'folio' => Rfq::nextFolio(),
                    'requisition_id' => $rfq->requisition_id,
                    'quotation_group_id' => $group->id,
                    'supersedes_rfq_id' => $rfq->id,
                    'source' => 'portal',
                    'status' => 'SENT',
                    'sent_at' => now(),
                    'response_deadline' => $validated['response_deadline'],
                    'message' => $validated['message'] ?? null,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]);

                $supplierData = [];
                foreach ($validated['supplier_ids'] as $supplierId) {
                    $supplierData[$supplierId] = ['invited_at' => now()];
                }
                $newRfq->suppliers()->attach($supplierData);

                foreach ($validated['supplier_ids'] as $supplierId) {
                    // El envío real de correo sigue siendo TODO en el sistema (igual que sendRFQ).
                    Log::info('📧 RFQ complementaria enviada', [
                        'rfq_id' => $newRfq->id,
                        'folio' => $newRfq->folio,
                        'supersedes_rfq_id' => $rfq->id,
                        'supplier_id' => $supplierId,
                    ]);
                }

                return $newRfq;
            });

            return redirect()
                ->route('rfq.comparison.index', $rfq)
                ->with('status', "RFQ complementaria {$newRfq->folio} generada y enviada a ".count($validated['supplier_ids']).' proveedor(es).');
        } catch (Throwable $exception) {
            Log::error("Error al generar RFQ complementaria desde {$rfq->id}: {$exception->getMessage()}");

            return back()->with('error', 'No fue posible generar la RFQ complementaria: '.$exception->getMessage());
        }
    }
```

- [ ] **Step 4: Correr el test y verificar que pasa**

Run: `php artisan test --filter=GenerateComplementaryRfqTest`
Expected: PASS.

- [ ] **Step 5: Correr toda la suite nueva y verificar regresiones**

Run: `php artisan test --filter="RfqResponseNotAvailableTest|SupplierPartialQuotationTest|SupplierRfqDetailRendersToggleTest|PurchaseOrderExcludesNotAvailableTest|RfqComparisonStatsTest|RfqComparisonViewTest|GenerateComplementaryRfqTest"`
Expected: PASS (todos).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/RfqComparisonController.php tests/Feature/GenerateComplementaryRfqTest.php
git commit -m "feat: generar RFQ complementario con partidas faltantes desde la comparativa"
```

---

## Self-Review

**Spec coverage:**
- Flag explícito "no cotizada" → Task 1 (columna `not_available` + scopes). ✅
- Guardar partida en cero/vacío como no cotizada, diferente de $0 → Tasks 1-2. ✅
- Modal de confirmación con conteo/lista antes de enviar → Task 3 Step 6. ✅
- Validación backend (no bypass) → Task 2. ✅
- Permitir cero partidas cotizadas → Task 2 Step 1 (`test_submit_allowed_when_all_items_not_available`). ✅
- Badge "Producto no disponible" distinto de $0 → Task 6 Step 5. ✅
- Resumen "X de Y partidas cotizadas" → Tasks 5-6. ✅
- Botón "Generar RFQ con partidas faltantes" → Task 6 Steps 6-7. ✅
- Selección de partidas faltantes (nadie cotizó) → Task 5 (`itemsQuotedByNoSupplier`) + Task 6 modal. ✅
- Nuevo RFQ pre-llenado con trazabilidad (`supersedes_rfq_id`) → Task 7. ✅
- Elegir proveedores destino → Task 7 modal + acción. ✅
- ODC manual y nunca incluye no disponibles → Task 4. ✅
- Migración siguiendo convención (default explícito) → Task 1. ✅
- Textos en español → todas las vistas/mensajes. ✅

**Placeholder scan:** Sin "TBD/TODO" pendientes salvo la nota explícita de que el envío de correo a proveedores ya era un TODO preexistente del sistema (fuera de alcance), consistente con `sendRFQ`/`createRFQs`. ✅

**Type consistency:** `not_available` (boolean) usado consistente en migración, modelo, controladores y vistas. `quotedItemCountForSupplier(int): int` e `itemsQuotedByNoSupplier(): Collection` usados igual en Task 5 (definición) y Task 6 (consumo). Ruta `rfq.comparison.generate-complementary` registrada en Task 6 Step 7 (antes de que la vista la use en Step 8), y el método del controlador que la atiende se agrega en Task 7 — el orden evita un `RouteNotFoundException` al renderizar la comparativa. ✅
