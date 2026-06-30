# Cotización Manual del Comprador — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permitir que el comprador capture, dentro del Paso 3 del wizard de cotización, el precio y condiciones de un proveedor (registrado o externo) sin que ese proveedor pase por el portal.

**Architecture:** Reutiliza el mecanismo de "respuesta de proveedor" ya existente (`rfq_suppliers.responded_at` + `RfqResponse` con `status=SUBMITTED`) en vez de crear un flujo paralelo. Se agregan dos columnas pequeñas (`suppliers.is_external`, `rfq_responses.entry_source`/`entered_by`), se extrae la lógica de completitud de RFQ a un método reutilizable del modelo `Rfq`, y se añade un modal de pantalla completa en el Paso 3 manejado por el componente Livewire `QuotationWizard` ya existente.

**Tech Stack:** Laravel 12, Livewire 3.7, PHPUnit (`RefreshDatabase`, SQLite en memoria para tests), Blade + Bootstrap 5, jQuery/Select2 (capa JS existente del Paso 3, sin tocar).

## Global Constraints

- Spec de referencia: `docs/superpowers/specs/2026-06-30-cotizacion-manual-comprador-design.md`.
- No se crean tablas ni modelos nuevos — todo se apoya en `Supplier`, `Rfq`, `RfqSupplier` (pivote), `RfqResponse`, `QuotationGroup` ya existentes.
- Refinamiento respecto al spec: **no se altera `suppliers.email`/`suppliers.password` a nullable** (el proyecto no tiene `doctrine/dbal` instalado y la base de datos de producción es SQL Server vía `sqlsrv`, donde `->change()` es frágil sin esa dependencia). En su lugar, el proveedor externo se crea con valores por defecto seguros generados en código (email único sintético si no se da uno, password aleatorio con el cast `hashed` ya existente en `Supplier`). El resultado para el usuario es idéntico al descrito en el spec.
- Todas las migraciones nuevas son aditivas (`Schema::table(...)->boolean(...)`, `->enum(...)`, `->foreignId(...)`), sin `->change()`, para que funcionen igual en SQLite (tests) y SQL Server (producción).
- Todo texto visible para el comprador en español.
- Tests con `php artisan test --filter=<Clase>`.

---

## File Structure

| Archivo | Responsabilidad |
|---|---|
| `database/migrations/2026_06_30_120000_add_is_external_to_suppliers_table.php` | Agrega `suppliers.is_external` |
| `database/migrations/2026_06_30_120100_add_manual_entry_fields_to_rfq_responses_table.php` | Agrega `rfq_responses.entry_source` y `entered_by` |
| `app/Models/Supplier.php` | `is_external` en fillable/casts, scope `external()` |
| `app/Models/RfqResponse.php` | `entry_source`/`entered_by` en fillable, relación `enteredBy()` |
| `app/Models/Rfq.php` | Nuevo método público `refreshCompletionStatus()` |
| `app/Http/Controllers/SupplierPortalController.php` | `checkRfqCompletion()` delega en `Rfq::refreshCompletionStatus()` |
| `app/Livewire/Rfq/QuotationWizard.php` | `determineCurrentStep()` corregido; nuevas propiedades/métodos del modal de cotización manual |
| `resources/views/rfq/wizard-steps/step-3-suppliers.blade.php` | Botón, badges y modal de captura manual |
| `resources/views/rfq/comparison/index.blade.php` | Badge "Capturada manualmente" |
| `database/factories/SupplierFactory.php` | Estado `external()` para tests |
| `database/factories/RfqResponseFactory.php` | Default `entry_source` |
| `tests/Feature/SupplierExternalFieldTest.php` | **nuevo** |
| `tests/Feature/RfqResponseManualEntryFieldTest.php` | **nuevo** |
| `tests/Feature/RfqCompletionStatusTest.php` | **nuevo** |
| `tests/Feature/QuotationWizardStepDeterminationTest.php` | **nuevo** |
| `tests/Feature/QuotationWizardManualQuoteSupplierTest.php` | **nuevo** |
| `tests/Feature/QuotationWizardManualQuoteTest.php` | **nuevo** |
| `tests/Feature/Step3SuppliersManualQuoteViewTest.php` | **nuevo** |
| `tests/Feature/RfqComparisonManualBadgeTest.php` | **nuevo** |

---

### Task 1: `Supplier.is_external` (migración + modelo + scope)

**Files:**
- Create: `database/migrations/2026_06_30_120000_add_is_external_to_suppliers_table.php`
- Modify: `app/Models/Supplier.php:21-62` (fillable), `app/Models/Supplier.php:69-85` (casts)
- Modify: `database/factories/SupplierFactory.php:49-69` (nuevo estado `external()`)
- Test: `tests/Feature/SupplierExternalFieldTest.php`

**Interfaces:**
- Produces: `Supplier::$is_external` (bool, default `false`), `Supplier::scopeExternal($query)`, `SupplierFactory::external()` state usado por tareas posteriores.

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

namespace Tests\Feature;

use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierExternalFieldTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_external_defaults_to_false_and_casts_to_boolean(): void
    {
        $supplier = Supplier::factory()->create();

        $this->assertIsBool($supplier->fresh()->is_external);
        $this->assertFalse($supplier->fresh()->is_external);
    }

    public function test_external_state_marks_supplier_as_external_and_inactive(): void
    {
        $supplier = Supplier::factory()->external()->create();

        $this->assertTrue($supplier->fresh()->is_external);
        $this->assertFalse($supplier->fresh()->is_active);
    }

    public function test_scope_external_filters_only_external_suppliers(): void
    {
        $regular = Supplier::factory()->create();
        $external = Supplier::factory()->external()->create();

        $this->assertEquals([$external->id], Supplier::external()->pluck('id')->all());
        $this->assertNotContains($regular->id, Supplier::external()->pluck('id')->all());
    }
}
```

- [ ] **Step 2: Ejecutar y verificar que falla**

Run: `php artisan test --filter=SupplierExternalFieldTest`
Expected: FAIL — columna `is_external` no existe / método `external()` no existe en la factory.

- [ ] **Step 3: Crear la migración**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->boolean('is_external')->default(false)->after('approval_status');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('is_external');
        });
    }
};
```

- [ ] **Step 4: Actualizar el modelo `Supplier`**

En `app/Models/Supplier.php:61`, después de `'economic_activity',` dentro de `$fillable`:

```php
        'economic_activity',
        'is_external',
    ];
```

En `app/Models/Supplier.php:83`, dentro del array que retorna `casts()`, después de `'accepted_currencies' => 'array',`:

```php
            'accepted_currencies' => 'array',
            'is_external' => 'boolean',
        ];
    }
```

Después del método `casts()` (después de la línea 85), agregar el scope:

```php

    public function scopeExternal(Builder $query): Builder
    {
        return $query->where('is_external', true);
    }
```

(El modelo ya usa `Builder` en otros scopes como `scopeApproved` — confirmar el `use Illuminate\Database\Eloquent\Builder;` ya importado en la cabecera del archivo; si falta, agregarlo.)

- [ ] **Step 5: Agregar el estado `external()` a la factory**

En `database/factories/SupplierFactory.php`, después del método `repse()` (línea 69, antes del `}` final de la clase):

```php

    public function external()
    {
        return $this->state(fn () => [
            'is_external' => true,
            'is_active' => false,
            'password' => \Illuminate\Support\Str::random(40),
        ]);
    }
```

- [ ] **Step 6: Ejecutar migraciones y test, verificar que pasa**

Run: `php artisan migrate && php artisan test --filter=SupplierExternalFieldTest`
Expected: PASS (3 tests).

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_06_30_120000_add_is_external_to_suppliers_table.php app/Models/Supplier.php database/factories/SupplierFactory.php tests/Feature/SupplierExternalFieldTest.php
git commit -m "feat: agregar is_external a Supplier para proveedores capturados manualmente"
```

---

### Task 2: `RfqResponse.entry_source` / `entered_by` (migración + modelo)

**Files:**
- Create: `database/migrations/2026_06_30_120100_add_manual_entry_fields_to_rfq_responses_table.php`
- Modify: `app/Models/RfqResponse.php:21-58` (fillable), `app/Models/RfqResponse.php:140-143` (relación)
- Modify: `database/factories/RfqResponseFactory.php:23-38` (default `entry_source`)
- Test: `tests/Feature/RfqResponseManualEntryFieldTest.php`

**Interfaces:**
- Produces: `RfqResponse::$entry_source` (`'supplier_portal'|'buyer_manual'`, default `'supplier_portal'`), `RfqResponse::$entered_by` (nullable FK `users.id`), `RfqResponse::enteredBy(): BelongsTo`.

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

namespace Tests\Feature;

use App\Models\RfqResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfqResponseManualEntryFieldTest extends TestCase
{
    use RefreshDatabase;

    public function test_entry_source_defaults_to_supplier_portal(): void
    {
        $response = RfqResponse::factory()->create();

        $this->assertEquals('supplier_portal', $response->fresh()->entry_source);
        $this->assertNull($response->fresh()->entered_by);
    }

    public function test_entry_source_can_be_set_to_buyer_manual_with_entered_by(): void
    {
        $user = User::factory()->create();
        $response = RfqResponse::factory()->create([
            'entry_source' => 'buyer_manual',
            'entered_by' => $user->id,
        ]);

        $this->assertEquals('buyer_manual', $response->fresh()->entry_source);
        $this->assertTrue($response->fresh()->enteredBy->is($user));
    }
}
```

- [ ] **Step 2: Ejecutar y verificar que falla**

Run: `php artisan test --filter=RfqResponseManualEntryFieldTest`
Expected: FAIL — columnas no existen.

- [ ] **Step 3: Crear la migración**

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
            $table->enum('entry_source', ['supplier_portal', 'buyer_manual'])
                ->default('supplier_portal')
                ->after('status');

            $table->foreignId('entered_by')
                ->nullable()
                ->after('entry_source')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rfq_responses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('entered_by');
            $table->dropColumn('entry_source');
        });
    }
};
```

- [ ] **Step 4: Actualizar el modelo `RfqResponse`**

En `app/Models/RfqResponse.php:57`, dentro de `$fillable`, después de `'not_available',`:

```php
        'not_available',
        'entry_source',
        'entered_by',
    ];
```

Después de `evaluator()` (`app/Models/RfqResponse.php:140-143`), agregar:

```php

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
```

- [ ] **Step 5: Default explícito en la factory**

En `database/factories/RfqResponseFactory.php:37`, después de `'status' => 'DRAFT',`:

```php
            'status'              => 'DRAFT',
            'entry_source'        => 'supplier_portal',
        ];
```

- [ ] **Step 6: Ejecutar migraciones y test, verificar que pasa**

Run: `php artisan migrate && php artisan test --filter=RfqResponseManualEntryFieldTest`
Expected: PASS (2 tests).

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_06_30_120100_add_manual_entry_fields_to_rfq_responses_table.php app/Models/RfqResponse.php database/factories/RfqResponseFactory.php tests/Feature/RfqResponseManualEntryFieldTest.php
git commit -m "feat: agregar entry_source y entered_by a RfqResponse para auditoría de captura manual"
```

---

### Task 3: `Rfq::refreshCompletionStatus()` (extraer lógica de completitud)

**Files:**
- Modify: `app/Models/Rfq.php:269-277` (después de `markAsResponded()`)
- Modify: `app/Http/Controllers/SupplierPortalController.php:278-292`
- Test: `tests/Feature/RfqCompletionStatusTest.php`

**Interfaces:**
- Consumes: `Rfq::suppliers()` (`BelongsToMany` ya existente, pivote `responded_at`).
- Produces: `Rfq::refreshCompletionStatus(): void` — público, usado por `SupplierPortalController` (Task existente) y por el flujo de cotización manual (Task 6).

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

namespace Tests\Feature;

use App\Models\Rfq;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfqCompletionStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_stays_sent_until_all_suppliers_respond(): void
    {
        $rfq = Rfq::factory()->create(['status' => 'SENT']);
        $supplierA = Supplier::factory()->create();
        $supplierB = Supplier::factory()->create();

        $rfq->suppliers()->attach([
            $supplierA->id => ['invited_at' => now(), 'responded_at' => now()],
            $supplierB->id => ['invited_at' => now(), 'responded_at' => null],
        ]);

        $rfq->refreshCompletionStatus();

        $this->assertEquals('SENT', $rfq->fresh()->status);
    }

    public function test_status_becomes_received_when_all_suppliers_responded(): void
    {
        $rfq = Rfq::factory()->create(['status' => 'SENT']);
        $supplierA = Supplier::factory()->create();
        $supplierB = Supplier::factory()->create();

        $rfq->suppliers()->attach([
            $supplierA->id => ['invited_at' => now(), 'responded_at' => now()],
            $supplierB->id => ['invited_at' => now(), 'responded_at' => now()],
        ]);

        $rfq->refreshCompletionStatus();

        $this->assertEquals('RECEIVED', $rfq->fresh()->status);
    }
}
```

- [ ] **Step 2: Ejecutar y verificar que falla**

Run: `php artisan test --filter=RfqCompletionStatusTest`
Expected: FAIL — `Call to undefined method App\Models\Rfq::refreshCompletionStatus()`.

- [ ] **Step 3: Implementar el método en `Rfq.php`**

En `app/Models/Rfq.php`, agregar `use Illuminate\Support\Facades\Log;` a los `use` del archivo si no está (no lo está hoy en este modelo), e insertar después de `markAsResponded()` (líneas 269-277):

```php
    /**
     * Recalcula el status de la RFQ según cuántos proveedores invitados ya
     * respondieron (portal o captura manual). Pasa a RECEIVED solo cuando
     * TODOS los proveedores invitados tienen responded_at.
     */
    public function refreshCompletionStatus(): void
    {
        $totalInvited = $this->suppliers()->count();
        $totalResponded = $this->suppliers()->whereNotNull('responded_at')->count();

        if ($totalInvited > 0 && $totalResponded >= $totalInvited) {
            $this->update([
                'status' => 'RECEIVED',
                'updated_at' => now(),
            ]);
            Log::info("RFQ Folio {$this->folio}: Todos los proveedores respondieron. Estado actualizado a RECEIVED.");
        } else {
            Log::info("RFQ Folio {$this->folio}: Respuesta recibida ({$totalResponded}/{$totalInvited})");
        }
    }
```

- [ ] **Step 4: Ejecutar test, verificar que pasa**

Run: `php artisan test --filter=RfqCompletionStatusTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Refactorizar `SupplierPortalController` para reusar el método**

En `app/Http/Controllers/SupplierPortalController.php:278-292`, reemplazar el cuerpo de `checkRfqCompletion`:

```php
    /**
     * Verifica si todos los proveedores respondieron y actualiza el estado de la RFQ
     */
    private function checkRfqCompletion(Rfq $rfq): void
    {
        $rfq->refreshCompletionStatus();
    }
```

(Se deja el método privado `checkRfqCompletion` como wrapper fino para no tocar las dos líneas que lo invocan en `saveQuotation()`, líneas 441-442.)

- [ ] **Step 6: Correr la suite completa de RFQ existente para verificar que no hay regresión**

Run: `php artisan test --filter=RfqResponseNotAvailableTest && php artisan test --filter=RfqCompletionStatusTest`
Expected: PASS — el comportamiento del flujo de proveedor por portal no cambió.

- [ ] **Step 7: Commit**

```bash
git add app/Models/Rfq.php app/Http/Controllers/SupplierPortalController.php tests/Feature/RfqCompletionStatusTest.php
git commit -m "refactor: extraer Rfq::refreshCompletionStatus() para reusar entre portal y captura manual"
```

---

### Task 4: Corregir `determineCurrentStep()` para esperar a todos los grupos activos

**Files:**
- Modify: `app/Livewire/Rfq/QuotationWizard.php:94-118`
- Test: `tests/Feature/QuotationWizardStepDeterminationTest.php`

**Interfaces:**
- Consumes: `Rfq::scopeActive()` (ya existente en `app/Models/Rfq.php:383-386`).
- Produces: ningún símbolo nuevo — corrige el comportamiento interno de `determineCurrentStep()`, usado por `mount()`.

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

namespace Tests\Feature;

use App\Livewire\Rfq\QuotationWizard;
use App\Models\QuotationGroup;
use App\Models\Requisition;
use App\Models\Rfq;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuotationWizardStepDeterminationTest extends TestCase
{
    use RefreshDatabase;

    public function test_wizard_stays_on_step_4_if_any_active_group_is_still_pending(): void
    {
        $requisition = Requisition::factory()->create(['validated_at' => now()]);
        $groupA = QuotationGroup::factory()->create(['requisition_id' => $requisition->id]);
        $groupB = QuotationGroup::factory()->create(['requisition_id' => $requisition->id]);

        Rfq::factory()->create([
            'requisition_id' => $requisition->id,
            'quotation_group_id' => $groupA->id,
            'status' => 'RECEIVED',
        ]);
        Rfq::factory()->create([
            'requisition_id' => $requisition->id,
            'quotation_group_id' => $groupB->id,
            'status' => 'SENT',
        ]);

        Livewire::test(QuotationWizard::class, ['requisition' => $requisition])
            ->assertSet('currentStep', 4);
    }

    public function test_wizard_jumps_to_step_5_only_when_all_active_groups_received(): void
    {
        $requisition = Requisition::factory()->create(['validated_at' => now()]);
        $group = QuotationGroup::factory()->create(['requisition_id' => $requisition->id]);

        Rfq::factory()->create([
            'requisition_id' => $requisition->id,
            'quotation_group_id' => $group->id,
            'status' => 'RECEIVED',
        ]);

        Livewire::test(QuotationWizard::class, ['requisition' => $requisition])
            ->assertSet('currentStep', 5);
    }
}
```

- [ ] **Step 2: Ejecutar y verificar que falla**

Run: `php artisan test --filter=QuotationWizardStepDeterminationTest`
Expected: FAIL en `test_wizard_stays_on_step_4_if_any_active_group_is_still_pending` (hoy salta a 5 con que un grupo esté RECEIVED).

- [ ] **Step 3: Corregir `determineCurrentStep()`**

En `app/Livewire/Rfq/QuotationWizard.php:94-99`, reemplazar:

```php
    private function determineCurrentStep(): int
    {
        // 🎯 NUEVO: Si hay RFQs que ya tienen respuestas, ir al paso 5
        if ($this->requisition->rfqs()->whereIn('status', ['RECEIVED', 'EVALUATED'])->exists()) {
            return 5;
        }
```

por:

```php
    private function determineCurrentStep(): int
    {
        // Solo saltar al paso 5 cuando TODOS los RFQs activos de la requisición ya
        // tienen respuesta (antes bastaba con que UNO solo llegara a RECEIVED, lo que
        // arrastraba todo el wizard al paso 5 aunque otros grupos siguieran pendientes).
        $activeRfqs = $this->requisition->rfqs()->active()->get();
        if ($activeRfqs->isNotEmpty() && $activeRfqs->every(fn ($rfq) => in_array($rfq->status, ['RECEIVED', 'EVALUATED'], true))) {
            return 5;
        }
```

(El resto del método, líneas 101-118, no cambia.)

- [ ] **Step 4: Ejecutar test, verificar que pasa**

Run: `php artisan test --filter=QuotationWizardStepDeterminationTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Livewire/Rfq/QuotationWizard.php tests/Feature/QuotationWizardStepDeterminationTest.php
git commit -m "fix: el wizard solo salta al paso 5 cuando todos los grupos activos ya respondieron"
```

---

### Task 5: `QuotationWizard::resolveManualQuoteSupplier()` (resolver/crear proveedor)

**Files:**
- Modify: `app/Livewire/Rfq/QuotationWizard.php` (imports + nuevas propiedades + método público)
- Test: `tests/Feature/QuotationWizardManualQuoteSupplierTest.php`

**Interfaces:**
- Consumes: `SupplierFactory` (Task 1).
- Produces: propiedades públicas `manualQuoteSupplierId`, `manualQuoteNewSupplier` (array); método público `resolveManualQuoteSupplier(): ?Supplier`, usado por Task 6 (`saveManualQuote()`). Si el RFC ya existe, retorna `null` y deja un error en `manualQuoteNewSupplier.rfc` vía `addError()`.

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

namespace Tests\Feature;

use App\Livewire\Rfq\QuotationWizard;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationWizardManualQuoteSupplierTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_new_external_supplier_with_minimal_data(): void
    {
        $component = new QuotationWizard();
        $component->manualQuoteNewSupplier = [
            'company_name' => 'Tornillos del Norte SA',
            'rfc' => 'tdn900101ab1',
            'postal_code' => '64000',
            'contact_person' => '',
            'email' => '',
            'phone_number' => '',
        ];

        $supplier = $component->resolveManualQuoteSupplier();

        $this->assertNotNull($supplier);
        $this->assertTrue($supplier->is_external);
        $this->assertFalse($supplier->is_active);
        $this->assertEquals('TDN900101AB1', $supplier->rfc);
        $this->assertEquals('Tornillos del Norte SA', $supplier->company_name);
        $this->assertEquals('64000', $supplier->postal_code);
    }

    public function test_reuses_existing_supplier_by_id(): void
    {
        $existing = Supplier::factory()->create();
        $component = new QuotationWizard();
        $component->manualQuoteSupplierId = $existing->id;

        $supplier = $component->resolveManualQuoteSupplier();

        $this->assertTrue($supplier->is($existing));
    }

    public function test_rejects_duplicate_rfc_and_suggests_existing_supplier(): void
    {
        Supplier::factory()->create([
            'rfc' => 'TDN900101AB1',
            'company_name' => 'Tornillos del Norte SA',
        ]);

        $component = new QuotationWizard();
        $component->manualQuoteNewSupplier = [
            'company_name' => 'Otro nombre',
            'rfc' => 'tdn900101ab1',
            'postal_code' => '64000',
            'contact_person' => '',
            'email' => '',
            'phone_number' => '',
        ];

        $supplier = $component->resolveManualQuoteSupplier();

        $this->assertNull($supplier);
        $this->assertTrue($component->getErrorBag()->has('manualQuoteNewSupplier.rfc'));
        $this->assertStringContainsString(
            'Tornillos del Norte SA',
            $component->getErrorBag()->first('manualQuoteNewSupplier.rfc')
        );
    }
}
```

- [ ] **Step 2: Ejecutar y verificar que falla**

Run: `php artisan test --filter=QuotationWizardManualQuoteSupplierTest`
Expected: FAIL — propiedades y método no existen aún.

- [ ] **Step 3: Agregar propiedades y el método al componente**

En `app/Livewire/Rfq/QuotationWizard.php`, agregar el import después de `use Illuminate\Support\Facades\Log;` (línea 11):

```php
use Illuminate\Support\Facades\Log;
use App\Models\Supplier;
use Illuminate\Support\Str;
```

Agregar las nuevas propiedades públicas después de `public $groups = [];` (línea 30):

```php
    public $groups = [];

    // ======= Cotización manual del comprador (Paso 3) =======
    public $showManualQuoteModal = false;
    public $manualQuoteGroupId = null;
    public $manualQuoteSupplierId = null; // null = crear proveedor externo nuevo
    public $manualQuoteNewSupplier = [
        'company_name' => '',
        'rfc' => '',
        'postal_code' => '',
        'contact_person' => '',
        'email' => '',
        'phone_number' => '',
    ];
    public $manualQuoteItems = [];
    public $manualQuoteQuotationDate = null;
    public $manualQuoteValidityDays = 30;
    public $manualQuoteAttachment = null;
```

Agregar el método público, después de `prepareSupplierPivotData()` (después de la línea 397, antes de `generateRFQFolio()`):

```php
    /**
     * Resuelve el proveedor para una cotización manual: reusa el seleccionado por id,
     * o crea uno externo nuevo si no hay id. Si el RFC ya existe, no crea duplicado:
     * agrega un error sugiriendo seleccionar el proveedor existente y retorna null.
     */
    public function resolveManualQuoteSupplier(): ?Supplier
    {
        if ($this->manualQuoteSupplierId) {
            return Supplier::findOrFail($this->manualQuoteSupplierId);
        }

        $rfc = strtoupper(trim($this->manualQuoteNewSupplier['rfc']));
        $existing = Supplier::where('rfc', $rfc)->first();

        if ($existing) {
            $this->addError(
                'manualQuoteNewSupplier.rfc',
                "Ya existe un proveedor con este RFC: {$existing->company_name}. Selecciónalo de la lista en vez de crear uno nuevo."
            );

            return null;
        }

        $companyName = $this->manualQuoteNewSupplier['company_name'];

        return Supplier::create([
            'first_name' => $companyName,
            'last_name' => '',
            'email' => $this->manualQuoteNewSupplier['email'] ?: Str::uuid().'@externo.invalido',
            'password' => Str::random(40),
            'is_active' => false,
            'company_name' => $companyName,
            'rfc' => $rfc,
            'address' => '',
            'phone_number' => $this->manualQuoteNewSupplier['phone_number'] ?: '0000000000',
            'contact_person' => $this->manualQuoteNewSupplier['contact_person'] ?: $companyName,
            'supplier_type' => 'product_service',
            'postal_code' => $this->manualQuoteNewSupplier['postal_code'],
            'approval_status' => 'approved',
            'document_status' => 'approved',
            'is_external' => true,
        ]);
    }
```

- [ ] **Step 4: Ejecutar test, verificar que pasa**

Run: `php artisan test --filter=QuotationWizardManualQuoteSupplierTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Livewire/Rfq/QuotationWizard.php tests/Feature/QuotationWizardManualQuoteSupplierTest.php
git commit -m "feat: resolver/crear proveedor externo para cotización manual del comprador"
```

---

### Task 6: `openManualQuoteModal()` + `saveManualQuote()` (orquestación completa)

**Files:**
- Modify: `app/Livewire/Rfq/QuotationWizard.php` (imports + métodos)
- Test: `tests/Feature/QuotationWizardManualQuoteTest.php`

**Interfaces:**
- Consumes: `Rfq::refreshCompletionStatus()` (Task 3), `resolveManualQuoteSupplier()` (Task 5), `Rfq::active()` scope, `createNewRfq()`/`generateRFQFolio()` (ya existentes en el mismo archivo).
- Produces: métodos públicos `openManualQuoteModal($quotationGroupId)`, `closeManualQuoteModal()`, `saveManualQuote()`; computed properties `getManualQuoteGroupProperty()` y `getManualQuoteSelectableSuppliersProperty()` (consumidas por la vista en Task 7).

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

namespace Tests\Feature;

use App\Livewire\Rfq\QuotationWizard;
use App\Models\QuotationGroup;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Rfq;
use App\Models\RfqResponse;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuotationWizardManualQuoteTest extends TestCase
{
    use RefreshDatabase;

    private function makeGroupWithOneItem(Requisition $requisition): QuotationGroup
    {
        $group = QuotationGroup::factory()->create(['requisition_id' => $requisition->id]);
        $item = RequisitionItem::factory()->create(['requisition_id' => $requisition->id]);
        $group->items()->attach($item->id, ['sort_order' => 1]);

        return $group;
    }

    public function test_saving_manual_quote_for_a_single_supplier_group_marks_rfq_received(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $requisition = Requisition::factory()->create(['validated_at' => now()]);
        $group = $this->makeGroupWithOneItem($requisition);
        $item = $group->items->first();
        $supplier = Supplier::factory()->create();

        Livewire::test(QuotationWizard::class, ['requisition' => $requisition])
            ->call('openManualQuoteModal', $group->id)
            ->assertSet('showManualQuoteModal', true)
            ->set('manualQuoteSupplierId', $supplier->id)
            ->set("manualQuoteItems.{$item->id}.unit_price", 150)
            ->set("manualQuoteItems.{$item->id}.iva_rate", 16)
            ->set("manualQuoteItems.{$item->id}.delivery_days", 5)
            ->call('saveManualQuote')
            ->assertSet('showManualQuoteModal', false);

        $rfq = Rfq::where('quotation_group_id', $group->id)->firstOrFail();
        $this->assertEquals('RECEIVED', $rfq->status);

        $response = RfqResponse::where('rfq_id', $rfq->id)
            ->where('supplier_id', $supplier->id)
            ->where('requisition_item_id', $item->id)
            ->firstOrFail();

        $this->assertEquals('SUBMITTED', $response->status);
        $this->assertEquals('buyer_manual', $response->entry_source);
        $this->assertEquals($user->id, $response->entered_by);
        $this->assertEquals(150.0, (float) $response->unit_price);

        $pivot = $rfq->suppliers()->where('supplier_id', $supplier->id)->first();
        $this->assertNotNull($pivot->pivot->responded_at);
    }

    public function test_mixed_group_stays_sent_until_portal_supplier_also_responds(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $requisition = Requisition::factory()->create(['validated_at' => now()]);
        $group = $this->makeGroupWithOneItem($requisition);
        $item = $group->items->first();

        $manualSupplier = Supplier::factory()->create();
        $portalSupplier = Supplier::factory()->create();

        $rfq = Rfq::factory()->create([
            'requisition_id' => $requisition->id,
            'quotation_group_id' => $group->id,
            'status' => 'SENT',
        ]);
        $rfq->suppliers()->attach($portalSupplier->id, ['invited_at' => now()]);

        Livewire::test(QuotationWizard::class, ['requisition' => $requisition])
            ->call('openManualQuoteModal', $group->id)
            ->set('manualQuoteSupplierId', $manualSupplier->id)
            ->set("manualQuoteItems.{$item->id}.unit_price", 80)
            ->set("manualQuoteItems.{$item->id}.iva_rate", 16)
            ->set("manualQuoteItems.{$item->id}.delivery_days", 3)
            ->call('saveManualQuote');

        $this->assertEquals('SENT', $rfq->fresh()->status);
    }

    public function test_not_available_item_is_saved_without_requiring_price(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $requisition = Requisition::factory()->create(['validated_at' => now()]);
        $group = $this->makeGroupWithOneItem($requisition);
        $item = $group->items->first();
        $supplier = Supplier::factory()->create();

        Livewire::test(QuotationWizard::class, ['requisition' => $requisition])
            ->call('openManualQuoteModal', $group->id)
            ->set('manualQuoteSupplierId', $supplier->id)
            ->set("manualQuoteItems.{$item->id}.not_available", true)
            ->call('saveManualQuote')
            ->assertHasNoErrors();

        $response = RfqResponse::where('supplier_id', $supplier->id)->firstOrFail();
        $this->assertTrue($response->not_available);
        $this->assertEquals(0, (float) $response->unit_price);
    }
}
```

- [ ] **Step 2: Ejecutar y verificar que falla**

Run: `php artisan test --filter=QuotationWizardManualQuoteTest`
Expected: FAIL — `openManualQuoteModal`/`saveManualQuote` no existen.

- [ ] **Step 3: Agregar imports adicionales**

En `app/Livewire/Rfq/QuotationWizard.php`, ampliar el bloque de imports (después de los agregados en Task 5):

```php
use App\Models\QuotationGroup;
use App\Models\RfqResponse;
use Livewire\WithFileUploads;
```

Agregar el trait a la clase (línea 13):

```php
class QuotationWizard extends Component
{
    use WithFileUploads;

    // La requisición con la que trabajaremos
    public Requisition $requisition;
```

- [ ] **Step 4: Implementar `openManualQuoteModal`, `closeManualQuoteModal`, las computed properties y `saveManualQuote`**

Agregar después de `resolveManualQuoteSupplier()` (Task 5):

```php
    public function openManualQuoteModal($quotationGroupId): void
    {
        $group = QuotationGroup::with('items')->findOrFail($quotationGroupId);

        $this->manualQuoteGroupId = $group->id;
        $this->manualQuoteSupplierId = null;
        $this->manualQuoteNewSupplier = [
            'company_name' => '',
            'rfc' => '',
            'postal_code' => '',
            'contact_person' => '',
            'email' => '',
            'phone_number' => '',
        ];

        $this->manualQuoteItems = [];
        foreach ($group->items as $item) {
            $this->manualQuoteItems[$item->id] = [
                'not_available' => false,
                'unit_price' => null,
                'iva_rate' => 16,
                'currency' => 'MXN',
                'delivery_days' => null,
                'payment_terms' => null,
                'warranty_terms' => null,
                'brand' => null,
                'model' => null,
                'specifications' => null,
            ];
        }

        $this->manualQuoteQuotationDate = now()->format('Y-m-d');
        $this->manualQuoteValidityDays = 30;
        $this->manualQuoteAttachment = null;
        $this->resetErrorBag();
        $this->showManualQuoteModal = true;
    }

    public function closeManualQuoteModal(): void
    {
        $this->showManualQuoteModal = false;
    }

    public function getManualQuoteGroupProperty()
    {
        return $this->manualQuoteGroupId
            ? QuotationGroup::with('items.productService')->find($this->manualQuoteGroupId)
            : null;
    }

    public function getManualQuoteSelectableSuppliersProperty()
    {
        return Supplier::query()
            ->where(function ($q) {
                $q->where('approval_status', 'approved')->where('is_active', true);
            })
            ->orWhere('is_external', true)
            ->orderBy('company_name')
            ->get();
    }

    public function saveManualQuote(): void
    {
        $rules = [
            'manualQuoteQuotationDate' => 'required|date',
            'manualQuoteValidityDays' => 'required|integer|min:1|max:365',
            'manualQuoteItems' => 'required|array|min:1',
            'manualQuoteItems.*.not_available' => 'boolean',
            'manualQuoteItems.*.unit_price' => ['exclude_if:manualQuoteItems.*.not_available,true', 'required', 'numeric', 'min:0'],
            'manualQuoteItems.*.iva_rate' => ['exclude_if:manualQuoteItems.*.not_available,true', 'required', 'numeric', 'in:0,8,16'],
            'manualQuoteItems.*.currency' => 'nullable|string|in:MXN,USD,EUR',
            'manualQuoteItems.*.delivery_days' => ['exclude_if:manualQuoteItems.*.not_available,true', 'required', 'integer', 'min:0'],
            'manualQuoteItems.*.payment_terms' => 'nullable|string|max:255',
            'manualQuoteItems.*.warranty_terms' => 'nullable|string|max:500',
            'manualQuoteItems.*.brand' => 'nullable|string|max:100',
            'manualQuoteItems.*.model' => 'nullable|string|max:100',
            'manualQuoteItems.*.specifications' => 'nullable|string',
            'manualQuoteAttachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ];

        if (! $this->manualQuoteSupplierId) {
            $rules['manualQuoteNewSupplier.company_name'] = 'required|string|max:150';
            $rules['manualQuoteNewSupplier.rfc'] = 'required|string|regex:/^[A-ZÑ\&]{3,4}[0-9]{6}[A-Z0-9]{3}$/i';
            $rules['manualQuoteNewSupplier.postal_code'] = 'required|digits:5';
            $rules['manualQuoteNewSupplier.email'] = 'nullable|email|max:150';
            $rules['manualQuoteNewSupplier.phone_number'] = 'nullable|string|max:15';
        }

        $this->validate($rules);

        $supplier = $this->resolveManualQuoteSupplier();
        if (! $supplier) {
            return;
        }

        $group = QuotationGroup::with('items')->findOrFail($this->manualQuoteGroupId);

        DB::beginTransaction();
        try {
            $rfq = Rfq::where('requisition_id', $this->requisition->id)
                ->where('quotation_group_id', $group->id)
                ->active()
                ->first();

            if (! $rfq) {
                $rfq = Rfq::create([
                    'folio' => $this->generateRFQFolio(),
                    'requisition_id' => $this->requisition->id,
                    'quotation_group_id' => $group->id,
                    'status' => 'DRAFT',
                    'response_deadline' => now()->addDays(7),
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]);
            }

            $rfq->suppliers()->syncWithoutDetaching([
                $supplier->id => ['invited_at' => now(), 'responded_at' => now()],
            ]);

            $attachmentPath = $this->manualQuoteAttachment
                ? $this->manualQuoteAttachment->store("rfq_responses/manual/{$this->requisition->id}", 'public')
                : null;

            foreach ($this->manualQuoteItems as $itemId => $itemData) {
                $notAvailable = (bool) ($itemData['not_available'] ?? false);
                $unitPrice = $notAvailable ? 0 : (float) $itemData['unit_price'];
                $ivaRate = $notAvailable ? 0 : (float) $itemData['iva_rate'];
                $quantity = (float) ($group->items->firstWhere('id', (int) $itemId)->quantity ?? 1);
                $subtotal = $unitPrice * $quantity;
                $ivaAmount = $subtotal * ($ivaRate / 100);

                RfqResponse::updateOrCreate(
                    [
                        'rfq_id' => $rfq->id,
                        'supplier_id' => $supplier->id,
                        'requisition_item_id' => $itemId,
                    ],
                    [
                        'quotation_date' => $this->manualQuoteQuotationDate,
                        'validity_days' => $this->manualQuoteValidityDays,
                        'unit_price' => $unitPrice,
                        'quantity' => $quantity,
                        'subtotal' => $subtotal,
                        'iva_rate' => $ivaRate,
                        'iva_amount' => $ivaAmount,
                        'total' => $subtotal + $ivaAmount,
                        'currency' => $itemData['currency'] ?? 'MXN',
                        'delivery_days' => $notAvailable ? null : ($itemData['delivery_days'] ?? null),
                        'payment_terms' => $itemData['payment_terms'] ?? null,
                        'warranty_terms' => $itemData['warranty_terms'] ?? null,
                        'brand' => $itemData['brand'] ?? null,
                        'model' => $itemData['model'] ?? null,
                        'specifications' => $itemData['specifications'] ?? null,
                        'not_available' => $notAvailable,
                        'attachment_path' => $attachmentPath,
                        'status' => 'SUBMITTED',
                        'submitted_at' => now(),
                        'entry_source' => 'buyer_manual',
                        'entered_by' => Auth::id(),
                    ]
                );
            }

            $rfq->refreshCompletionStatus();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'No se pudo guardar la cotización manual: '.$e->getMessage());

            return;
        }

        $this->showManualQuoteModal = false;
        $this->loadSuppliersData();
        session()->flash('success', "✅ Cotización de {$supplier->company_name} capturada para el grupo {$group->name}.");
    }
```

- [ ] **Step 5: Ejecutar test, verificar que pasa**

Run: `php artisan test --filter=QuotationWizardManualQuoteTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Correr regresión de Task 3 y 4 juntos**

Run: `php artisan test --filter=RfqCompletionStatusTest && php artisan test --filter=QuotationWizardStepDeterminationTest && php artisan test --filter=QuotationWizardManualQuoteTest`
Expected: PASS — el flujo manual no rompe la lógica de completitud ni de determinación de paso.

- [ ] **Step 7: Commit**

```bash
git add app/Livewire/Rfq/QuotationWizard.php tests/Feature/QuotationWizardManualQuoteTest.php
git commit -m "feat: capturar cotización manual del comprador en el Paso 3 del wizard"
```

---

### Task 7: Blade — botón, badges y modal en `step-3-suppliers.blade.php`

**Files:**
- Modify: `resources/views/rfq/wizard-steps/step-3-suppliers.blade.php`
- Modify: `app/Livewire/Rfq/QuotationWizard.php:37-47` (eager load de `rfqs.rfqResponses`)
- Test: `tests/Feature/Step3SuppliersManualQuoteViewTest.php`

**Interfaces:**
- Consumes: `showManualQuoteModal`, `manualQuoteGroupId`, `manualQuoteSupplierId`, `manualQuoteNewSupplier`, `manualQuoteItems`, `manualQuoteQuotationDate`, `manualQuoteValidityDays`, `manualQuoteAttachment`, `manualQuoteGroup` (computed), `manualQuoteSelectableSuppliers` (computed), `openManualQuoteModal`, `closeManualQuoteModal`, `saveManualQuote` (Tasks 5-6).

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

namespace Tests\Feature;

use App\Models\QuotationGroup;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Rfq;
use App\Models\RfqResponse;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Step3SuppliersManualQuoteViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_step_3_shows_manual_quote_button_and_badge_for_captured_supplier(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $requisition = Requisition::factory()->create(['validated_at' => now()]);
        $group = QuotationGroup::factory()->create(['requisition_id' => $requisition->id]);
        $item = RequisitionItem::factory()->create(['requisition_id' => $requisition->id]);
        $group->items()->attach($item->id, ['sort_order' => 1]);

        $supplier = Supplier::factory()->external()->create(['company_name' => 'Tornillos del Norte SA']);
        $rfq = Rfq::factory()->create([
            'requisition_id' => $requisition->id,
            'quotation_group_id' => $group->id,
            'status' => 'RECEIVED',
        ]);
        $rfq->suppliers()->attach($supplier->id, ['invited_at' => now(), 'responded_at' => now()]);
        RfqResponse::factory()->create([
            'rfq_id' => $rfq->id,
            'supplier_id' => $supplier->id,
            'requisition_item_id' => $item->id,
            'entry_source' => 'buyer_manual',
        ]);

        $this->get(route('rfq.wizard.steps', $requisition))
            ->assertOk()
            ->assertSee('Cotización manual')
            ->assertSee('Cotización capturada')
            ->assertSee('Tornillos del Norte SA');
    }
}
```

- [ ] **Step 2: Ejecutar y verificar que falla**

Run: `php artisan test --filter=Step3SuppliersManualQuoteViewTest`
Expected: FAIL — `assertSee('Cotización manual')` no encuentra el texto.

(Ruta confirmada: `routes/web.php:430-432`, nombrada `rfq.wizard.steps`, retorna la vista `rfq.wizard` que monta el componente Livewire `QuotationWizard`.)

- [ ] **Step 3: Ampliar el eager load del wizard**

En `app/Livewire/Rfq/QuotationWizard.php:37-47`, agregar `'rfqs.rfqResponses'` al arreglo de `load`:

```php
        $this->requisition = $requisition->load([
            'requester',
            'company',
            'department',
            'items.costCenter',
            'items.productService',
            'items.expenseCategory',
            'quotationGroups.items',
            'rfqs',
            'rfqs.suppliers',
            'rfqs.rfqResponses',
        ]);
```

- [ ] **Step 4: Agregar el botón y los badges por grupo**

En `resources/views/rfq/wizard-steps/step-3-suppliers.blade.php`, después de la línea 158 (`</div>` que cierra el `row` de Proveedores/Fecha/Seleccionados) y antes de la línea 160 (`<div class="row mt-3">` de Notas), insertar:

```blade
                    <div class="row mt-3">
                        <div class="col-12">
                            <button type="button"
                                    class="btn btn-sm btn-outline-success"
                                    wire:click="openManualQuoteModal({{ $group->id }})">
                                <i class="ti ti-pencil-plus"></i> Cotización manual / Proveedor externo
                            </button>

                            @php
                                $manualSupplierIds = $activeRfq
                                    ? $activeRfq->rfqResponses->where('entry_source', 'buyer_manual')->pluck('supplier_id')->unique()
                                    : collect();
                            @endphp

                            @if($manualSupplierIds->isNotEmpty())
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    @foreach($activeRfq->suppliers->whereIn('id', $manualSupplierIds) as $manualSupplier)
                                        <span class="badge bg-success-subtle text-success border border-success">
                                            <i class="ti ti-circle-check"></i> Cotización capturada — {{ $manualSupplier->company_name }}
                                            @if($manualSupplier->is_external)
                                                <span class="badge bg-secondary ms-1">Externo</span>
                                            @endif
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
```

- [ ] **Step 5: Agregar el modal de pantalla completa**

En el mismo archivo, justo antes de `@push('styles')` (línea 193), insertar:

```blade
<div class="modal {{ $showManualQuoteModal ? 'show d-block' : '' }}"
     tabindex="-1"
     style="{{ $showManualQuoteModal ? 'background: rgba(0,0,0,.5);' : '' }}"
     wire:ignore.self>
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="ti ti-pencil-plus"></i> Cotización manual
                    @if($manualQuoteGroup)
                        — {{ $manualQuoteGroup->name }}
                    @endif
                </h5>
                <button type="button" class="btn-close" wire:click="closeManualQuoteModal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Proveedor</label>
                        <select class="form-select" wire:model="manualQuoteSupplierId">
                            <option value="">-- Nuevo proveedor externo --</option>
                            @foreach($manualQuoteSelectableSuppliers as $sel)
                                <option value="{{ $sel->id }}">{{ $sel->company_name }}{{ $sel->is_external ? ' (externo)' : '' }}</option>
                            @endforeach
                        </select>
                        @error('manualQuoteSupplierId') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Fecha de cotización</label>
                        <input type="date" class="form-control" wire:model="manualQuoteQuotationDate">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Vigencia (días)</label>
                        <input type="number" class="form-control" wire:model="manualQuoteValidityDays" min="1" max="365">
                    </div>
                </div>

                @if(! $manualQuoteSupplierId)
                    <div class="card border-info mb-3">
                        <div class="card-body">
                            <h6 class="text-info"><i class="ti ti-building-store"></i> Nuevo proveedor externo</h6>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label small">Razón social *</label>
                                    <input type="text" class="form-control form-control-sm" wire:model="manualQuoteNewSupplier.company_name">
                                    @error('manualQuoteNewSupplier.company_name') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small">RFC *</label>
                                    <input type="text" class="form-control form-control-sm" wire:model="manualQuoteNewSupplier.rfc">
                                    @error('manualQuoteNewSupplier.rfc') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small">Código postal *</label>
                                    <input type="text" class="form-control form-control-sm" wire:model="manualQuoteNewSupplier.postal_code">
                                    @error('manualQuoteNewSupplier.postal_code') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small">Contacto</label>
                                    <input type="text" class="form-control form-control-sm" wire:model="manualQuoteNewSupplier.contact_person">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small">Email</label>
                                    <input type="email" class="form-control form-control-sm" wire:model="manualQuoteNewSupplier.email">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small">Teléfono</label>
                                    <input type="text" class="form-control form-control-sm" wire:model="manualQuoteNewSupplier.phone_number">
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if($manualQuoteGroup)
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Partida</th>
                                    <th width="10%">No disp.</th>
                                    <th width="12%">Precio unit.</th>
                                    <th width="8%">IVA</th>
                                    <th width="8%">Moneda</th>
                                    <th width="10%">Entrega (días)</th>
                                    <th width="15%">Cond. pago</th>
                                    <th width="15%">Garantía</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($manualQuoteGroup->items as $item)
                                    <tr>
                                        <td>{{ $item->productService->short_name ?? $item->description }}</td>
                                        <td class="text-center">
                                            <input type="checkbox" wire:model="manualQuoteItems.{{ $item->id }}.not_available">
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" class="form-control form-control-sm" wire:model="manualQuoteItems.{{ $item->id }}.unit_price">
                                        </td>
                                        <td>
                                            <select class="form-select form-select-sm" wire:model="manualQuoteItems.{{ $item->id }}.iva_rate">
                                                <option value="16">16%</option>
                                                <option value="8">8%</option>
                                                <option value="0">0%</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select class="form-select form-select-sm" wire:model="manualQuoteItems.{{ $item->id }}.currency">
                                                <option value="MXN">MXN</option>
                                                <option value="USD">USD</option>
                                                <option value="EUR">EUR</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" class="form-control form-control-sm" wire:model="manualQuoteItems.{{ $item->id }}.delivery_days">
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm" wire:model="manualQuoteItems.{{ $item->id }}.payment_terms">
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm" wire:model="manualQuoteItems.{{ $item->id }}.warranty_terms">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <div class="row mt-3">
                    <div class="col-md-8">
                        <label class="form-label fw-bold">Adjunto (opcional)</label>
                        <input type="file" class="form-control" wire:model="manualQuoteAttachment" accept=".pdf,.jpg,.jpeg,.png">
                        @error('manualQuoteAttachment') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" wire:click="closeManualQuoteModal">Cancelar</button>
                <button type="button"
                        class="btn btn-success"
                        wire:click="saveManualQuote"
                        wire:loading.attr="disabled"
                        wire:target="saveManualQuote">
                    <span wire:loading.remove wire:target="saveManualQuote"><i class="ti ti-device-floppy"></i> Guardar cotización</span>
                    <span wire:loading wire:target="saveManualQuote"><i class="ti ti-loader rotating"></i> Guardando...</span>
                </button>
            </div>
        </div>
    </div>
</div>
```

- [ ] **Step 6: Ejecutar test, verificar que pasa**

Run: `php artisan test --filter=Step3SuppliersManualQuoteViewTest`
Expected: PASS.

- [ ] **Step 7: Verificación manual en navegador (no automatizable con PHPUnit)**

Levantar el entorno local (`php artisan serve` + `npm run dev` si aplica) y, sobre una requisición en Paso 3:
1. Click en "Cotización manual / Proveedor externo" → confirmar que el modal abre con las partidas del grupo.
2. Crear un proveedor externo nuevo con RFC duplicado de uno existente → confirmar que aparece el error sugiriendo el proveedor existente.
3. Capturar una cotización completa con un proveedor nuevo → confirmar que el badge "Cotización capturada" aparece en la tarjeta del grupo tras guardar.
4. Confirmar que el botón "Siguiente"/flujo normal de Selección de Proveedores (Select2 + jQuery) sigue funcionando sin errores de consola tras la apertura/cierre del modal (el `MutationObserver` existente reinicializa `Step3Suppliers`).

- [ ] **Step 8: Commit**

```bash
git add resources/views/rfq/wizard-steps/step-3-suppliers.blade.php app/Livewire/Rfq/QuotationWizard.php tests/Feature/Step3SuppliersManualQuoteViewTest.php
git commit -m "feat: UI del Paso 3 para capturar cotización manual del comprador"
```

---

### Task 8: Badge "Capturada manualmente" en el comparativo (Paso 5)

**Files:**
- Modify: `resources/views/rfq/comparison/index.blade.php:144-151`
- Test: `tests/Feature/RfqComparisonManualBadgeTest.php`

**Interfaces:**
- Consumes: `$rfq->rfqResponses` (ya cargado por `RfqComparisonController@index`, `app/Http/Controllers/RfqComparisonController.php:39`), campo `entry_source` (Task 2).

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

namespace Tests\Feature;

use App\Models\QuotationGroup;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Rfq;
use App\Models\RfqResponse;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfqComparisonManualBadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_comparison_view_shows_manual_badge_for_buyer_captured_quote(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $requisition = Requisition::factory()->create();
        $group = QuotationGroup::factory()->create(['requisition_id' => $requisition->id]);
        $item = RequisitionItem::factory()->create(['requisition_id' => $requisition->id]);
        $group->items()->attach($item->id, ['sort_order' => 1]);

        $supplier = Supplier::factory()->external()->create(['company_name' => 'Tornillos del Norte SA']);
        $rfq = Rfq::factory()->create([
            'requisition_id' => $requisition->id,
            'quotation_group_id' => $group->id,
            'status' => 'RECEIVED',
        ]);
        $rfq->suppliers()->attach($supplier->id, ['invited_at' => now(), 'responded_at' => now()]);
        RfqResponse::factory()->create([
            'rfq_id' => $rfq->id,
            'supplier_id' => $supplier->id,
            'requisition_item_id' => $item->id,
            'status' => 'SUBMITTED',
            'entry_source' => 'buyer_manual',
        ]);

        $this->get(route('rfq.comparison.index', $rfq))
            ->assertOk()
            ->assertSee('CAPTURADA MANUALMENTE');
    }
}
```

(Ruta confirmada: `routes/web.php:426`, `rfq.comparison.index`.)

- [ ] **Step 2: Ejecutar y verificar que falla**

Run: `php artisan test --filter=RfqComparisonManualBadgeTest`
Expected: FAIL — el badge no existe todavía en la vista.

- [ ] **Step 3: Agregar el badge en la vista**

En `resources/views/rfq/comparison/index.blade.php`, dentro del bloque `@if($hasResponded)` (línea 144), justo después de la apertura de `<div class="d-flex flex-wrap justify-content-center gap-1 mt-1">` (línea 145) y antes de `@if($nivelAsignado)` (línea 146), insertar:

```blade
                                        @php
                                            $isManualEntry = $supplierResponses->where('entry_source', 'buyer_manual')->isNotEmpty();
                                        @endphp
                                        @if($isManualEntry)
                                            <span class="badge bg-soft-secondary text-secondary border fs-9" title="Capturada por el comprador, no enviada por el proveedor">
                                                <i class="ti ti-pencil"></i> CAPTURADA MANUALMENTE
                                            </span>
                                        @endif
```

- [ ] **Step 4: Ejecutar test, verificar que pasa**

Run: `php artisan test --filter=RfqComparisonManualBadgeTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/rfq/comparison/index.blade.php tests/Feature/RfqComparisonManualBadgeTest.php
git commit -m "feat: mostrar badge de cotización capturada manualmente en el comparativo"
```

---

## Self-Review

**1. Cobertura del spec:**
- Decisión 1 (punto de entrada Paso 3, modal pantalla completa) → Task 7.
- Decisión 2 (mismo mecanismo para cualquier proveedor) → Task 6 (`manualQuoteSupplierId` opcional) + Task 7 (selector incluye aprobados y externos).
- Decisión 3 (proveedor externo ligero con datos de facturación) → Task 1 + Task 5.
- Decisión 4 (reusar mecanismo de respuesta existente) → Task 6 (mismo patrón `rfq_suppliers.responded_at` + `RfqResponse`).
- Decisión 5 (auditoría `entry_source`/`entered_by`) → Task 2 + Task 6.
- Decisión 6 (adjunto opcional) → Task 6 (`manualQuoteAttachment`) + Task 7 (input file).
- Decisión 7 (fix `determineCurrentStep`) → Task 4.
- Decisión 8 (sin botón de enviar para manuales) → cubierto por Task 3/6: `refreshCompletionStatus()` ya dispara `RECEIVED` automáticamente; no se necesitó tocar `wizardDatatable()`.
- Badge "Capturada manualmente" en comparativo → Task 8.
- Caso borde "RFC duplicado sugiere proveedor existente" → Task 5.
- Caso borde "grupo mixto" → cubierto y probado en Task 6.

**2. Placeholders:** revisado — no quedan `TBD`/`TODO`. Los nombres de ruta usados en los tests de Tasks 7 y 8 (`rfq.wizard.steps`, `rfq.comparison.index`) fueron verificados contra `routes/web.php:426` y `routes/web.php:430-432` antes de cerrar el plan.

**3. Consistencia de tipos:** `resolveManualQuoteSupplier(): ?Supplier` (Task 5) es exactamente el tipo que `saveManualQuote()` consume en Task 6. `Rfq::refreshCompletionStatus(): void` (Task 3) es el mismo nombre y firma usados en `SupplierPortalController` (Task 3) y en `QuotationWizard::saveManualQuote()` (Task 6). Las claves de `manualQuoteItems` (`not_available`, `unit_price`, `iva_rate`, `currency`, `delivery_days`, `payment_terms`, `warranty_terms`, `brand`, `model`, `specifications`) son idénticas entre `openManualQuoteModal()` (Task 6, inicialización) y `saveManualQuote()` (Task 6, lectura) y la vista (Task 7, `wire:model`).
