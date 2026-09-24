# RP-02 · Movimientos y traspasos presupuestales — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Construir el reporte RP-02 (movimientos presupuestales con nivel de autorización, soporte y pestaña de reconstrucción del presupuesto) dentro de "Reportes solicitados", más los tres prerrequisitos que faltan: foto del presupuesto original, nivel de autorización registrado y adjuntos en "Solicitar movimiento".

**Architecture:** Una tabla `budget_baselines` guarda el presupuesto original por renglón al aprobar o importar un presupuesto anual (con un comando para reconstruir la foto de los ya aprobados). `budget_movements.approval_level` registra si aprobó el titular (DIRECCION) o el suplente (SUPLENTE). `budget_movement_attachments` guarda los soportes. El reporte vive en `App\Reports\Budget\BudgetMovementsReport` (filas, KPIs, reconstrucción y exportación) y se sirve en `/requested-reports/rp-02` solo para superadmin.

**Tech Stack:** Laravel 12, SQL Server (prod) / SQLite en memoria (tests), PHPUnit, Spatie Permission + Activitylog, yajra DataTables, PhpSpreadsheet, Blade + Bootstrap (Zircos), Select2.

**Spec:** `docs/reportes/prompts_reportes_portal_proveedores.md` § "REPORTE RP-02" (líneas 223-399), con estas decisiones del usuario (2026-09-24) que prevalecen sobre la spec:
1. Sí se guarda la foto del presupuesto original.
2. Se agregan adjuntos al formulario "Solicitar movimiento".
3. El suplente **no** cuenta como nivel Dirección.
4. RP-02 vive aparte; el reporte "Movimientos presupuestales" de Reportería (`budget-movements-risk`) no se toca.
5. Solo superadministradores pueden ver y exportar RP-02.

## Global Constraints

- PHP: `C:/PHP83/php.exe`. Pruebas: `C:/PHP83/php.exe artisan test --filter=<Nombre>`. Formato: `C:/PHP83/php.exe vendor/bin/pint <archivos>`.
- Pruebas en SQLite en memoria; no tocar `phpunit.xml`; nunca `migrate` contra la BD configurada (solo `migrate --pretend`).
- Migraciones solo aditivas y compatibles con SQL Server y SQLite.
- Textos de UI en español. Folio mostrado como `MP-` + id a 6 dígitos (`MP-000123`).
- Tipos: `AMPLIACION` → "Ampliación", `REDUCCION` → "Reducción", `TRANSFERENCIA` → "Traspaso".
- Nivel requerido = "Dirección" solo para traspasos entre centros de costo distintos o entre empresas distintas; en los demás casos no hay nivel requerido.
- `level_violation` = nivel requerido Dirección **y** movimiento APROBADO **y** `approval_level` ≠ `DIRECCION` (incluye `SUPLENTE` y `NULL` del flujo anterior).
- Reconstrucción por renglón (centro de costo + mes + cuenta + subcuenta): `vigente = original + Σ detalles de movimientos APROBADOS`, `diferencia = assigned_amount actual − vigente` (debe ser 0).
- Por defecto el reporte muestra solo movimientos APROBADOS; periodo por defecto 1 de enero del año en curso → hoy.
- Acceso a `/requested-reports/rp-02/*`: middleware `role:superadmin`.
- Commits terminan con `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- Suite base antes de este plan: 36 fallos preexistentes; ninguno nuevo permitido.

## Review Focus

1. **Traspaso aprobado por el suplente entre centros distintos** → debe aparecer como violación de nivel (Task 3 y Task 5).
2. **Presupuesto editado directamente después de aprobado (o reimportado)** → la reconstrucción debe mostrar la diferencia ≠ 0, no esconderla (Task 5 `test_reconstruction_flags_direct_edits`).
3. **Movimiento sin adjuntos o con archivo no permitido** → soporte opcional; tipos fuera de la lista se rechazan con mensaje en español (Task 4).
4. **Usuario con acceso a Reportería pero sin superadmin** → 403 en página, datos y exportación (Task 6).
5. **Reimportación de un presupuesto ya aprobado** → la foto se reemplaza (origen "Importación"), no se duplica (Task 2).

---

## File Structure

| Archivo | Responsabilidad |
|---|---|
| `database/migrations/2026_09_24_000001_create_budget_baselines_table.php` (nuevo) | Foto del presupuesto original |
| `app/Models/BudgetBaseline.php` (nuevo) | Modelo de la foto |
| `app/Services/BudgetBaselineService.php` (nuevo) | Capturar, reconstruir y efectos de movimientos por renglón |
| `app/Models/AnnualBudget.php` (mod.) | Relación `baselines()` |
| `app/Http/Controllers/AnnualBudgetController.php` (mod.) | Capturar foto al aprobar |
| `app/Services/Budget2026DgaImportService.php` (mod.) | Capturar foto al importar presupuestos aprobados |
| `app/Console/Commands/ReconstructBudgetBaselines.php` (nuevo) | `budget:reconstruct-baselines` |
| `database/migrations/2026_09_24_000002_add_approval_level_to_budget_movements_table.php` (nuevo) | Nivel aplicado |
| `app/Models/BudgetMovement.php` (mod.) | Constantes de nivel, fillable, relación `attachments()` |
| `app/Http/Controllers/BudgetMovementWorkflowController.php` (mod.) | Registrar nivel; guardar y descargar adjuntos |
| `database/migrations/2026_09_24_000003_create_budget_movement_attachments_table.php` (nuevo) | Adjuntos |
| `app/Models/BudgetMovementAttachment.php` (nuevo) | Modelo de adjunto |
| `app/Http/Requests/SaveBudgetMovementRequest.php` (mod.) | Reglas de adjuntos |
| `resources/views/budget_movements/workflow/_form.blade.php` (mod.) | `enctype` y campo de archivos |
| `resources/views/budget_movements/workflow/show.blade.php` (mod.) | Lista de soportes |
| `routes/web.php` (mod.) | Ruta de descarga de adjunto y rutas RP-02 |
| `app/Reports/Budget/BudgetMovementsReport.php` (nuevo) | Filas, KPIs, reconstrucción, exportación |
| `app/Http/Requests/BudgetMovementsReportRequest.php` (nuevo) | Filtros |
| `app/Http/Controllers/RequestedReports/BudgetMovementsReportController.php` (nuevo) | index / data / reconstruction / export |
| `resources/views/requested-reports/rp02.blade.php` (nuevo) | Pantalla con pestañas |
| `config/requested_reports.php` + `resources/views/requested-reports/index.blade.php` (mod.) | Enlace "Abrir reporte" |
| Tests: `BudgetBaselineServiceTest`, `BudgetBaselineCaptureTest`, `BudgetMovementApprovalLevelTest`, `BudgetMovementAttachmentTest`, `BudgetMovementsReportTest`, `BudgetMovementsReportHttpTest` | |

---

### Task 1: Foto del presupuesto original (tabla, modelo y servicio)

**Files:**
- Create: `database/migrations/2026_09_24_000001_create_budget_baselines_table.php`
- Create: `app/Models/BudgetBaseline.php`
- Create: `app/Services/BudgetBaselineService.php`
- Modify: `app/Models/AnnualBudget.php` (agregar relación)
- Test: `tests/Feature/BudgetBaselineServiceTest.php`

**Interfaces:**
- Produces: `BudgetBaseline` (constantes `SOURCE_APPROVAL='APPROVAL'`, `SOURCE_IMPORT='IMPORT'`, `SOURCE_RECONSTRUCTED='RECONSTRUCTED'`); `AnnualBudget::baselines(): HasMany`.
- Produces `App\Services\BudgetBaselineService`:
  - `static lineKey(int|string $month, int|string $categoryId, int|string|null $cedulaId): string` → `"9|4|12"` (`-` si subcuenta nula).
  - `capture(AnnualBudget $budget, string $source, ?int $userId = null, bool $overwrite = false): int` (filas creadas; 0 si ya había foto y `$overwrite=false`).
  - `approvedMovementEffects(int $costCenterId, int $fiscalYear): array<string, array{increases: float, decreases: float}>` (decreases negativo).
  - `reconstructMissing(?int $userId = null): int` (presupuestos reconstruidos).

- [ ] **Step 1: Write the failing test** — `tests/Feature/BudgetBaselineServiceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\AnnualBudget;
use App\Models\BudgetBaseline;
use App\Models\BudgetCedula;
use App\Models\BudgetMonthlyDistribution;
use App\Models\BudgetMovement;
use App\Models\BudgetMovementDetail;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\BudgetBaselineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetBaselineServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private CostCenter $costCenter;
    private ExpenseCategory $category;
    private BudgetCedula $cedula;
    private AnnualBudget $budget;
    private BudgetBaselineService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->costCenter = CostCenter::factory()->create();
        $this->category = ExpenseCategory::factory()->create();
        $this->cedula = BudgetCedula::factory()->create(['expense_category_id' => $this->category->id]);
        $this->budget = AnnualBudget::create(['cost_center_id' => $this->costCenter->id, 'fiscal_year' => 2026, 'total_annual_amount' => 3000, 'status' => 'APROBADO', 'created_by' => $this->user->id]);
        foreach ([1 => 1000, 2 => 2000] as $month => $amount) {
            BudgetMonthlyDistribution::create(['annual_budget_id' => $this->budget->id, 'budget_cedula_id' => $this->cedula->id, 'expense_category_id' => $this->category->id, 'month' => $month, 'assigned_amount' => $amount, 'created_by' => $this->user->id]);
        }
        $this->service = app(BudgetBaselineService::class);
    }

    public function test_capture_stores_original_amount_per_line_once(): void
    {
        $this->assertSame(2, $this->service->capture($this->budget, BudgetBaseline::SOURCE_APPROVAL, $this->user->id));
        $this->assertSame(0, $this->service->capture($this->budget, BudgetBaseline::SOURCE_APPROVAL, $this->user->id));

        $this->assertDatabaseHas('budget_baselines', ['annual_budget_id' => $this->budget->id, 'month' => 2, 'budget_cedula_id' => $this->cedula->id, 'original_amount' => 2000, 'source' => 'APPROVAL']);
        $this->assertSame(2, BudgetBaseline::count());
    }

    public function test_capture_with_overwrite_replaces_the_previous_snapshot(): void
    {
        $this->service->capture($this->budget, BudgetBaseline::SOURCE_APPROVAL);
        BudgetMonthlyDistribution::where('month', 1)->update(['assigned_amount' => 1500]);

        $this->service->capture($this->budget, BudgetBaseline::SOURCE_IMPORT, null, true);

        $this->assertSame(2, BudgetBaseline::count());
        $this->assertDatabaseHas('budget_baselines', ['month' => 1, 'original_amount' => 1500, 'source' => 'IMPORT']);
    }

    public function test_reconstruct_missing_subtracts_approved_movements(): void
    {
        $movement = BudgetMovement::create(['movement_type' => 'AMPLIACION', 'fiscal_year' => 2026, 'movement_date' => '2026-02-10', 'total_amount' => 500, 'justification' => 'Ampliación de prueba para reconstrucción.', 'status' => 'APROBADO', 'created_by' => $this->user->id]);
        BudgetMovementDetail::create(['budget_movement_id' => $movement->id, 'detail_type' => 'AJUSTE', 'cost_center_id' => $this->costCenter->id, 'month' => 2, 'expense_category_id' => $this->category->id, 'budget_cedula_id' => $this->cedula->id, 'amount' => 500]);
        $pending = BudgetMovement::create(['movement_type' => 'AMPLIACION', 'fiscal_year' => 2026, 'movement_date' => '2026-02-11', 'total_amount' => 99, 'justification' => 'Pendiente que no debe contar.', 'status' => 'PENDIENTE_DIRECCION', 'created_by' => $this->user->id]);
        BudgetMovementDetail::create(['budget_movement_id' => $pending->id, 'detail_type' => 'AJUSTE', 'cost_center_id' => $this->costCenter->id, 'month' => 2, 'expense_category_id' => $this->category->id, 'budget_cedula_id' => $this->cedula->id, 'amount' => 99]);

        $this->assertSame(1, $this->service->reconstructMissing($this->user->id));
        $this->assertSame(0, $this->service->reconstructMissing($this->user->id));

        $this->assertDatabaseHas('budget_baselines', ['month' => 2, 'original_amount' => 1500, 'source' => 'RECONSTRUCTED']);
        $this->assertDatabaseHas('budget_baselines', ['month' => 1, 'original_amount' => 1000, 'source' => 'RECONSTRUCTED']);
    }

    public function test_approved_movement_effects_split_increases_and_decreases(): void
    {
        $movement = BudgetMovement::create(['movement_type' => 'TRANSFERENCIA', 'fiscal_year' => 2026, 'movement_date' => '2026-01-10', 'total_amount' => 300, 'justification' => 'Traspaso de prueba entre meses.', 'status' => 'APROBADO', 'created_by' => $this->user->id]);
        BudgetMovementDetail::create(['budget_movement_id' => $movement->id, 'detail_type' => 'ORIGEN', 'cost_center_id' => $this->costCenter->id, 'month' => 1, 'expense_category_id' => $this->category->id, 'budget_cedula_id' => $this->cedula->id, 'amount' => -300]);
        BudgetMovementDetail::create(['budget_movement_id' => $movement->id, 'detail_type' => 'DESTINO', 'cost_center_id' => $this->costCenter->id, 'month' => 2, 'expense_category_id' => $this->category->id, 'budget_cedula_id' => $this->cedula->id, 'amount' => 300]);

        $effects = $this->service->approvedMovementEffects($this->costCenter->id, 2026);

        $this->assertEqualsWithDelta(-300.0, $effects[BudgetBaselineService::lineKey(1, $this->category->id, $this->cedula->id)]['decreases'], 0.001);
        $this->assertEqualsWithDelta(300.0, $effects[BudgetBaselineService::lineKey(2, $this->category->id, $this->cedula->id)]['increases'], 0.001);
    }
}
```

- [ ] **Step 2: Run to verify it fails** — `C:/PHP83/php.exe artisan test --filter=BudgetBaselineServiceTest` → FAIL (`Class "App\Models\BudgetBaseline" not found`).

- [ ] **Step 3: Implement.**

Migration `2026_09_24_000001_create_budget_baselines_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_baselines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annual_budget_id')->constrained('annual_budgets')->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->foreignId('expense_category_id')->constrained('expense_categories')->noActionOnDelete();
            $table->foreignId('budget_cedula_id')->nullable()->constrained('budget_cedulas')->noActionOnDelete();
            $table->decimal('original_amount', 15, 2);
            $table->string('source', 20);
            $table->timestamp('captured_at');
            $table->foreignId('captured_by')->nullable()->constrained('users')->noActionOnDelete();
            $table->timestamps();
            $table->index(['annual_budget_id', 'month'], 'idx_budget_baselines_budget_month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_baselines');
    }
};
```

`app/Models/BudgetBaseline.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Presupuesto original de un renglón (mes + cuenta + subcuenta) al aprobar o importar el presupuesto anual. */
class BudgetBaseline extends Model
{
    public const SOURCE_APPROVAL = 'APPROVAL';

    public const SOURCE_IMPORT = 'IMPORT';

    public const SOURCE_RECONSTRUCTED = 'RECONSTRUCTED';

    public const SOURCE_LABELS = [
        self::SOURCE_APPROVAL => 'Aprobación',
        self::SOURCE_IMPORT => 'Importación',
        self::SOURCE_RECONSTRUCTED => 'Reconstruida',
    ];

    protected $fillable = ['annual_budget_id', 'month', 'expense_category_id', 'budget_cedula_id', 'original_amount', 'source', 'captured_at', 'captured_by'];

    protected $casts = ['original_amount' => 'decimal:2', 'captured_at' => 'datetime', 'month' => 'integer'];

    public function annualBudget(): BelongsTo
    {
        return $this->belongsTo(AnnualBudget::class);
    }
}
```

`AnnualBudget.php` — agregar junto a `monthlyDistributions()`:

```php
    public function baselines()
    {
        return $this->hasMany(BudgetBaseline::class);
    }
```

`app/Services/BudgetBaselineService.php`:

```php
<?php

namespace App\Services;

use App\Models\AnnualBudget;
use App\Models\BudgetBaseline;
use App\Models\BudgetMonthlyDistribution;
use App\Models\BudgetMovement;
use App\Models\BudgetMovementDetail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BudgetBaselineService
{
    public static function lineKey(int|string $month, int|string $categoryId, int|string|null $cedulaId): string
    {
        return (int) $month.'|'.(int) $categoryId.'|'.($cedulaId === null ? '-' : (int) $cedulaId);
    }

    /** Guarda la foto del presupuesto actual. Sin $overwrite no toca una foto existente. */
    public function capture(AnnualBudget $budget, string $source, ?int $userId = null, bool $overwrite = false): int
    {
        return DB::transaction(function () use ($budget, $source, $userId, $overwrite) {
            $query = BudgetBaseline::where('annual_budget_id', $budget->id);
            if ($overwrite) {
                $query->delete();
            } elseif ($query->exists()) {
                return 0;
            }

            $lines = $this->currentLines($budget->id);
            foreach ($lines as $line) {
                $this->storeLine($budget->id, $line, (float) $line->amount, $source, $userId);
            }

            return $lines->count();
        });
    }

    /** Aumentos (positivos) y disminuciones (negativas) de movimientos APROBADOS por renglón. */
    public function approvedMovementEffects(int $costCenterId, int $fiscalYear): array
    {
        return BudgetMovementDetail::query()
            ->join('budget_movements as bm', 'bm.id', '=', 'budget_movement_details.budget_movement_id')
            ->where('bm.status', BudgetMovement::STATUS_APPROVED)
            ->where('bm.fiscal_year', $fiscalYear)
            ->where('budget_movement_details.cost_center_id', $costCenterId)
            ->groupBy('budget_movement_details.month', 'budget_movement_details.expense_category_id', 'budget_movement_details.budget_cedula_id')
            ->selectRaw('budget_movement_details.month, budget_movement_details.expense_category_id, budget_movement_details.budget_cedula_id')
            ->selectRaw('SUM(CASE WHEN budget_movement_details.amount > 0 THEN budget_movement_details.amount ELSE 0 END) as increases')
            ->selectRaw('SUM(CASE WHEN budget_movement_details.amount < 0 THEN budget_movement_details.amount ELSE 0 END) as decreases')
            ->get()
            ->mapWithKeys(fn ($row) => [self::lineKey($row->month, $row->expense_category_id, $row->budget_cedula_id) => [
                'increases' => (float) $row->increases,
                'decreases' => (float) $row->decreases,
            ]])
            ->all();
    }

    /** Presupuestos APROBADOS sin foto: original = asignado actual − efecto de movimientos aprobados. */
    public function reconstructMissing(?int $userId = null): int
    {
        $count = 0;

        AnnualBudget::where('status', 'APROBADO')->whereDoesntHave('baselines')->get()
            ->each(function (AnnualBudget $budget) use ($userId, &$count) {
                $effects = $this->approvedMovementEffects($budget->cost_center_id, (int) $budget->fiscal_year);

                DB::transaction(function () use ($budget, $effects, $userId) {
                    foreach ($this->currentLines($budget->id) as $line) {
                        $effect = $effects[self::lineKey($line->month, $line->expense_category_id, $line->budget_cedula_id)] ?? ['increases' => 0, 'decreases' => 0];
                        $original = (float) $line->amount - $effect['increases'] - $effect['decreases'];
                        $this->storeLine($budget->id, $line, $original, BudgetBaseline::SOURCE_RECONSTRUCTED, $userId);
                    }
                });

                $count++;
            });

        return $count;
    }

    private function currentLines(int $annualBudgetId): Collection
    {
        return BudgetMonthlyDistribution::where('annual_budget_id', $annualBudgetId)
            ->groupBy('month', 'expense_category_id', 'budget_cedula_id')
            ->selectRaw('month, expense_category_id, budget_cedula_id, SUM(assigned_amount) as amount')
            ->get();
    }

    private function storeLine(int $annualBudgetId, object $line, float $amount, string $source, ?int $userId): void
    {
        BudgetBaseline::create([
            'annual_budget_id' => $annualBudgetId,
            'month' => (int) $line->month,
            'expense_category_id' => $line->expense_category_id,
            'budget_cedula_id' => $line->budget_cedula_id,
            'original_amount' => round($amount, 2),
            'source' => $source,
            'captured_at' => now(),
            'captured_by' => $userId,
        ]);
    }
}
```

- [ ] **Step 4: Run to verify it passes** — `C:/PHP83/php.exe artisan test --filter=BudgetBaselineServiceTest` → 4 passed.
- [ ] **Step 5: Commit** — pint on the 5 files, then `git add` them and `git commit -m "feat: add budget baseline snapshot table and service"`.

---

### Task 2: Capturar la foto al aprobar e importar; comando de reconstrucción

**Files:**
- Modify: `app/Http/Controllers/AnnualBudgetController.php` (`approveStore`, líneas 239-259)
- Modify: `app/Services/Budget2026DgaImportService.php` (dentro de la transacción de `import()`, después del bloque que inserta `$aggregatedRows`)
- Create: `app/Console/Commands/ReconstructBudgetBaselines.php`
- Test: `tests/Feature/BudgetBaselineCaptureTest.php`

**Interfaces:**
- Consumes: `BudgetBaselineService::capture()`, `::reconstructMissing()`, `BudgetBaseline::SOURCE_*` (Task 1).
- Produces: comando `budget:reconstruct-baselines`.

- [ ] **Step 1: Write the failing test** — `tests/Feature/BudgetBaselineCaptureTest.php`. La ruta es `annual_budgets.approve.store` (`POST /annual_budgets/{annual_budget}/approve`, middleware `module.access:budget_control`, que el superadmin cumple); `ApproveBudgetRequest` solo acepta `notes` opcional.

```php
<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckLockScreen;
use App\Models\AnnualBudget;
use App\Models\BudgetBaseline;
use App\Models\BudgetCedula;
use App\Models\BudgetMonthlyDistribution;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BudgetBaselineCaptureTest extends TestCase
{
    use RefreshDatabase;

    public function test_approving_an_annual_budget_captures_its_baseline(): void
    {
        $this->withoutMiddleware(CheckLockScreen::class);
        $admin = User::factory()->create()->assignRole(Role::findOrCreate('superadmin', 'web'));
        $category = ExpenseCategory::factory()->create();
        $cedula = BudgetCedula::factory()->create(['expense_category_id' => $category->id]);
        $budget = AnnualBudget::create(['cost_center_id' => CostCenter::factory()->create()->id, 'fiscal_year' => 2026, 'total_annual_amount' => 1200, 'status' => 'PLANIFICACION', 'created_by' => $admin->id]);
        BudgetMonthlyDistribution::create(['annual_budget_id' => $budget->id, 'budget_cedula_id' => $cedula->id, 'expense_category_id' => $category->id, 'month' => 3, 'assigned_amount' => 1200, 'created_by' => $admin->id]);

        $this->actingAs($admin)->post(route('annual_budgets.approve.store', $budget), ['notes' => 'Aprobado en prueba'])->assertRedirect();

        $this->assertSame('APROBADO', $budget->fresh()->status);
        $this->assertDatabaseHas('budget_baselines', ['annual_budget_id' => $budget->id, 'month' => 3, 'original_amount' => 1200, 'source' => BudgetBaseline::SOURCE_APPROVAL, 'captured_by' => $admin->id]);
    }

    public function test_reconstruct_command_fills_missing_baselines(): void
    {
        $user = User::factory()->create();
        $category = ExpenseCategory::factory()->create();
        $budget = AnnualBudget::create(['cost_center_id' => CostCenter::factory()->create()->id, 'fiscal_year' => 2026, 'total_annual_amount' => 700, 'status' => 'APROBADO', 'created_by' => $user->id]);
        BudgetMonthlyDistribution::create(['annual_budget_id' => $budget->id, 'budget_cedula_id' => BudgetCedula::factory()->create(['expense_category_id' => $category->id])->id, 'expense_category_id' => $category->id, 'month' => 1, 'assigned_amount' => 700, 'created_by' => $user->id]);

        $this->artisan('budget:reconstruct-baselines')->expectsOutputToContain('1')->assertSuccessful();

        $this->assertDatabaseHas('budget_baselines', ['annual_budget_id' => $budget->id, 'source' => BudgetBaseline::SOURCE_RECONSTRUCTED]);
    }
}
```

Si ya existe una prueba de importación de presupuestos 2026 (`grep -rl "Budget2026" tests`), agrégale una aserción: importar con `status = 'APROBADO'` deja filas `source = IMPORT` y reimportar no las duplica. Si no existe, la cobertura de la importación queda en `capture(..., overwrite: true)` de Task 1 y se anota en el reporte.

- [ ] **Step 2: Run to verify it fails** — `C:/PHP83/php.exe artisan test --filter=BudgetBaselineCaptureTest` → FAIL (sin foto / comando inexistente).

- [ ] **Step 3: Implement.**

`AnnualBudgetController::approveStore` — después del `$annual_budget->update([...])` y antes del `redirect`:

```php
        app(\App\Services\BudgetBaselineService::class)->capture($annual_budget, \App\Models\BudgetBaseline::SOURCE_APPROVAL, Auth::id());
```

`Budget2026DgaImportService::import()` — dentro del `foreach` de hojas, justo después del bloque `if ($aggregatedRows !== []) { ... }`:

```php
                // La importación reemplaza las distribuciones: si el presupuesto está aprobado, esa es su nueva foto original.
                if ($budget->status === 'APROBADO') {
                    app(\App\Services\BudgetBaselineService::class)->capture($budget, \App\Models\BudgetBaseline::SOURCE_IMPORT, $actorId, true);
                }
```

`app/Console/Commands/ReconstructBudgetBaselines.php`:

```php
<?php

namespace App\Console\Commands;

use App\Services\BudgetBaselineService;
use Illuminate\Console\Command;

class ReconstructBudgetBaselines extends Command
{
    protected $signature = 'budget:reconstruct-baselines';

    protected $description = 'Genera la foto del presupuesto original para presupuestos aprobados que no la tienen (asignado actual menos movimientos aprobados).';

    public function handle(BudgetBaselineService $service): int
    {
        $count = $service->reconstructMissing();
        $this->info("Presupuestos reconstruidos: {$count}");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run to verify it passes** — `C:/PHP83/php.exe artisan test --filter="BudgetBaselineCaptureTest|BudgetBaselineServiceTest|AnnualBudget"` → todo en verde.
- [ ] **Step 5: Commit** — `feat: capture budget baseline on approval and import`.

---

### Task 3: Registrar el nivel de autorización aplicado

**Files:**
- Create: `database/migrations/2026_09_24_000002_add_approval_level_to_budget_movements_table.php`
- Modify: `app/Models/BudgetMovement.php`
- Modify: `app/Http/Controllers/BudgetMovementWorkflowController.php` (`approveExecutive`)
- Test: `tests/Feature/BudgetMovementApprovalLevelTest.php`

**Interfaces:**
- Produces: columna `budget_movements.approval_level` (`string(20)`, nullable); constantes `BudgetMovement::LEVEL_DIRECTION = 'DIRECCION'`, `BudgetMovement::LEVEL_SUBSTITUTE = 'SUPLENTE'`, `BudgetMovement::LEVEL_LABELS = [DIRECCION => 'Dirección', SUPLENTE => 'Suplente de Dirección']`; `approval_level` en `$fillable`.

- [ ] **Step 1: Write the failing test** — `tests/Feature/BudgetMovementApprovalLevelTest.php` (reutiliza el arreglo de `BudgetMovementWorkflowTest`: director con rol `general_director`, `BudgetMovementApprovalSetting`, centros de costo y una ampliación enviada por el responsable):

```php
<?php

namespace Tests\Feature;

use App\Models\BudgetCedula;
use App\Models\BudgetMovement;
use App\Models\BudgetMovementApprovalSetting;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BudgetMovementApprovalLevelTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;
    private User $director;
    private User $substitute;
    private CostCenter $center;
    private ExpenseCategory $category;
    private BudgetCedula $cedula;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('general_director');
        $this->requester = User::factory()->create(['is_active' => true]);
        $this->director = User::factory()->create(['is_active' => true]);
        $this->director->assignRole('general_director');
        $this->substitute = User::factory()->create(['is_active' => true]);
        BudgetMovementApprovalSetting::create(['director_user_id' => $this->director->id, 'substitute_user_id' => $this->substitute->id, 'substitute_starts_at' => now()->subHour(), 'substitute_ends_at' => now()->addHour()]);
        $this->center = CostCenter::factory()->create(['responsible_user_id' => $this->requester->id]);
        $this->category = ExpenseCategory::factory()->create();
        $this->cedula = BudgetCedula::factory()->create(['expense_category_id' => $this->category->id]);
        // applyMovement() exige un presupuesto anual del año; la distribución la crea él mismo.
        \App\Models\AnnualBudget::create(['cost_center_id' => $this->center->id, 'fiscal_year' => now()->year, 'total_annual_amount' => 0, 'status' => 'APROBADO', 'created_by' => $this->director->id]);
    }

    private function submitIncrease(): BudgetMovement
    {
        $this->actingAs($this->requester)->post(route('budget_movements.store'), [
            'movement_type' => 'AMPLIACION', 'fiscal_year' => now()->year, 'movement_date' => now()->toDateString(), 'total_amount' => 500,
            'justification' => 'Ampliación para prueba de nivel de autorización.', 'cost_center_id' => $this->center->id, 'month' => 3,
            'expense_category_id' => $this->category->id, 'budget_cedula_id' => $this->cedula->id,
        ])->assertRedirect();

        return BudgetMovement::latest('id')->firstOrFail();
    }

    public function test_director_approval_records_direction_level(): void
    {
        $movement = $this->submitIncrease();
        $this->actingAs($this->director)->post(route('budget_movements.approve', $movement))->assertRedirect();

        $this->assertSame(BudgetMovement::LEVEL_DIRECTION, $movement->fresh()->approval_level);
    }

    public function test_substitute_approval_records_substitute_level(): void
    {
        $movement = $this->submitIncrease();
        $this->actingAs($this->substitute)->post(route('budget_movements.approve', $movement))->assertRedirect();

        $this->assertSame(BudgetMovement::LEVEL_SUBSTITUTE, $movement->fresh()->approval_level);
    }
}
```

- [ ] **Step 2: Run to verify it fails** — FAIL (`approval_level` null / constante inexistente).

- [ ] **Step 3: Implement.**

Migración:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_movements', function (Blueprint $table) {
            $table->string('approval_level', 20)->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('budget_movements', function (Blueprint $table) {
            $table->dropColumn('approval_level');
        });
    }
};
```

`BudgetMovement.php`: agregar `'approval_level'` a `$fillable` y, junto a las constantes de estado:

```php
    /** Nivel con el que se autorizó: titular de Dirección o su suplente. NULL = flujo anterior. */
    const LEVEL_DIRECTION = 'DIRECCION';

    const LEVEL_SUBSTITUTE = 'SUPLENTE';

    const LEVEL_LABELS = [self::LEVEL_DIRECTION => 'Dirección', self::LEVEL_SUBSTITUTE => 'Suplente de Dirección'];
```

`approveExecutive` — reemplazar la línea `$movement->update([... 'approved_at' => now()]);` por:

```php
            $level = (int) $this->approvalSettings()?->director_user_id === (int) $request->user()->id
                ? BudgetMovement::LEVEL_DIRECTION
                : BudgetMovement::LEVEL_SUBSTITUTE;
            $movement->update(['status' => BudgetMovement::STATUS_APPROVED, 'approved_by' => $request->user()->id, 'approved_at' => now(), 'approval_level' => $level]);
```

- [ ] **Step 4: Run** — `--filter="BudgetMovementApprovalLevelTest|BudgetMovementWorkflowTest"` → verde.
- [ ] **Step 5: Commit** — `feat: record approval level on budget movements`.

---

### Task 4: Adjuntos en "Solicitar movimiento"

**Files:**
- Create: `database/migrations/2026_09_24_000003_create_budget_movement_attachments_table.php`
- Create: `app/Models/BudgetMovementAttachment.php`
- Modify: `app/Models/BudgetMovement.php` (relación `attachments()`)
- Modify: `app/Http/Requests/SaveBudgetMovementRequest.php` (reglas y mensajes)
- Modify: `app/Http/Controllers/BudgetMovementWorkflowController.php` (`store`, `update`, nuevo `downloadAttachment`, carga de `attachments` en `show`)
- Modify: `routes/web.php` (grupo "Budget Movements", antes del `Route::resource`)
- Modify: `resources/views/budget_movements/workflow/_form.blade.php`, `show.blade.php`
- Test: `tests/Feature/BudgetMovementAttachmentTest.php`

**Interfaces:**
- Produces: `BudgetMovement::attachments(): HasMany`; ruta `budget_movements.attachments.download` (`GET budget_movements/{budgetMovement}/attachments/{attachment}`); archivos en disco `local` bajo `budget-movements/{id}/`.

- [ ] **Step 1: Write the failing test** — `tests/Feature/BudgetMovementAttachmentTest.php` (mismo arreglo que Task 3 sin suplente):

```php
    public function test_request_stores_support_files_and_only_visible_users_download_them(): void
    {
        Storage::fake('local');
        $this->actingAs($this->requester)->post(route('budget_movements.store'), $this->payload() + [
            'attachments' => [UploadedFile::fake()->create('soporte.pdf', 120, 'application/pdf')],
        ])->assertRedirect();

        $movement = BudgetMovement::latest('id')->firstOrFail();
        $attachment = $movement->attachments()->sole();
        $this->assertSame('soporte.pdf', $attachment->original_name);
        Storage::disk('local')->assertExists($attachment->file_path);

        $this->actingAs($this->requester)->get(route('budget_movements.attachments.download', [$movement, $attachment]))->assertOk()->assertDownload('soporte.pdf');
        $this->actingAs(User::factory()->create())->get(route('budget_movements.attachments.download', [$movement, $attachment]))->assertForbidden();
        $this->actingAs($this->requester)->get(route('budget_movements.show', $movement))->assertSeeText('soporte.pdf');
    }

    public function test_request_without_attachments_is_still_valid(): void
    {
        $this->actingAs($this->requester)->post(route('budget_movements.store'), $this->payload())->assertRedirect();
        $this->assertSame(0, BudgetMovement::latest('id')->firstOrFail()->attachments()->count());
    }

    public function test_disallowed_file_types_are_rejected(): void
    {
        Storage::fake('local');
        $this->actingAs($this->requester)->post(route('budget_movements.store'), $this->payload() + [
            'attachments' => [UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream')],
        ])->assertSessionHasErrors('attachments.0');
        $this->assertSame(0, BudgetMovement::count());
    }
```

(`payload()` = el mismo arreglo de ampliación de Task 3; imports: `Illuminate\Http\UploadedFile`, `Illuminate\Support\Facades\Storage`.)

- [ ] **Step 2: Run to verify it fails.**

- [ ] **Step 3: Implement.**

Migración:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_movement_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_movement_id')->constrained('budget_movements')->cascadeOnDelete();
            $table->string('original_name');
            $table->string('file_path');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->noActionOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_movement_attachments');
    }
};
```

`app/Models/BudgetMovementAttachment.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetMovementAttachment extends Model
{
    protected $fillable = ['budget_movement_id', 'original_name', 'file_path', 'mime_type', 'size_bytes', 'uploaded_by'];

    public function movement(): BelongsTo
    {
        return $this->belongsTo(BudgetMovement::class, 'budget_movement_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
```

`BudgetMovement.php`:

```php
    public function attachments(): HasMany
    {
        return $this->hasMany(BudgetMovementAttachment::class);
    }
```

`SaveBudgetMovementRequest::rules()` — agregar al arreglo base:

```php
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png,xls,xlsx,doc,docx', 'max:10240'],
```

y en `messages()`:

```php
            'attachments.max' => 'Puedes adjuntar como máximo 5 archivos.',
            'attachments.*.mimes' => 'Los soportes deben ser PDF, imagen (JPG/PNG), Excel o Word.',
            'attachments.*.max' => 'Cada soporte debe pesar como máximo 10 MB.',
```

Controlador — nuevo método privado y su uso en `store` y `update` (después de la transacción, antes de notificar):

```php
    /** @param  \Illuminate\Http\UploadedFile[]  $files */
    private function storeAttachments(BudgetMovement $movement, array $files, User $actor): void
    {
        foreach ($files as $file) {
            $movement->attachments()->create([
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $file->store("budget-movements/{$movement->id}", 'local'),
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
                'uploaded_by' => $actor->id,
            ]);
        }
    }
```

En `store`: `$this->storeAttachments($movement, $request->file('attachments', []), $actor);` En `update`: igual con `$movement`. En `show`: agregar `'attachments.uploader'` al `load([...])`.

Descarga:

```php
    public function downloadAttachment(Request $request, BudgetMovement $budgetMovement, BudgetMovementAttachment $attachment)
    {
        $this->ensureVisible($request->user(), $budgetMovement);
        abort_unless((int) $attachment->budget_movement_id === (int) $budgetMovement->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404);

        return Storage::disk('local')->download($attachment->file_path, $attachment->original_name);
    }
```

(imports: `App\Models\BudgetMovementAttachment`, `Illuminate\Support\Facades\Storage`.)

Ruta, antes de `Route::resource('budget_movements', ...)`:

```php
        Route::get('budget_movements/{budgetMovement}/attachments/{attachment}', [BudgetMovementWorkflowController::class, 'downloadAttachment'])->name('budget_movements.attachments.download');
```

`_form.blade.php`: en la etiqueta `<form ... id="budgetMovementForm">` agregar `enctype="multipart/form-data"`; al final de la sección "3. Justificación" (antes de `</section>`):

```blade
<div class="mt-3"><label class="form-label" for="attachments">Documentos soporte <span class="text-muted fw-normal">(opcional, hasta 5 archivos: PDF, imagen, Excel o Word, 10 MB c/u)</span></label><input class="form-control @error('attachments') is-invalid @enderror @error('attachments.*') is-invalid @enderror" type="file" id="attachments" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.xls,.xlsx,.doc,.docx">@error('attachments')<div class="invalid-feedback">{{ $message }}</div>@enderror @foreach($errors->get('attachments.*') as $messages)<div class="invalid-feedback d-block">{{ $messages[0] }}</div>@endforeach</div>
```

`show.blade.php`: después de la tarjeta "Partidas afectadas", agregar:

```blade
<div class="bm-card"><span class="bm-kicker">Documentos soporte</span>@forelse($budgetMovement->attachments as $attachment)<div class="d-flex justify-content-between align-items-center py-2 border-bottom"><div><i class="ti ti-paperclip me-1"></i>{{ $attachment->original_name }}<small class="d-block text-muted">{{ $attachment->uploader?->name }} · {{ $attachment->created_at->format('d/m/Y H:i') }}</small></div><a class="btn btn-sm btn-outline-primary" href="{{ route('budget_movements.attachments.download', [$budgetMovement, $attachment]) }}"><i class="ti ti-download"></i> Descargar</a></div>@empty<p class="text-muted mb-0 mt-2">Sin documentos soporte.</p>@endforelse</div>
```

- [ ] **Step 4: Run** — `--filter="BudgetMovementAttachmentTest|BudgetMovementWorkflowTest|BudgetMovementApprovalLevelTest"` → verde; `C:/PHP83/php.exe artisan view:cache && C:/PHP83/php.exe artisan view:clear`.
- [ ] **Step 5: Commit** — `feat: attach support documents to budget movement requests`.

---

### Task 5: Servicio del reporte RP-02 (filas, KPIs y reconstrucción)

**Files:**
- Create: `app/Reports/Budget/BudgetMovementsReport.php`
- Test: `tests/Feature/BudgetMovementsReportTest.php`

**Interfaces:**
- Consumes: `BudgetBaselineService::approvedMovementEffects()`, `::lineKey()`, `BudgetBaseline::SOURCE_LABELS`, `BudgetMovement::LEVEL_*`, `attachments()`.
- Produces `App\Reports\Budget\BudgetMovementsReport`:
  - `const COLUMNS` (clave → encabezado, en orden de pantalla y Excel).
  - `const RECONSTRUCTION_COLUMNS`.
  - `rows(array $filters): Collection` de arreglos con las claves de `COLUMNS` (+ `id`).
  - `kpis(Collection $rows): array` (etiqueta → valor).
  - `reconstruction(array $filters): Collection` de arreglos con las claves de `RECONSTRUCTION_COLUMNS`.
  - `filterOptions(): array`.
- Filtros aceptados (claves): `date_from`, `date_to`, `status` (`APROBADO` por defecto, `ALL` = todos), `movement_type`, `company_ids[]`, `cost_center_ids[]`, `authorized_by`, `amount_greater_than`, `only_level_violations`, `fiscal_year` (solo reconstrucción; por defecto año en curso).

- [ ] **Step 1: Write the failing test** — `tests/Feature/BudgetMovementsReportTest.php`. Helper `movement(type, status, level, details[], approvedAt)` que crea el movimiento y sus detalles directamente. Casos:

```php
    public function test_rows_map_columns_and_default_to_approved_movements(): void
    // Ampliación APROBADA (DIRECCION) + una PENDIENTE_DIRECCION → solo 1 fila; folio 'MP-000001' (usa el id real con str_pad),
    // tipo 'Ampliación', destino = centro/renglón del AJUSTE, origen vacío, nivel_aplicado 'Dirección', nivel_requerido '—',
    // violacion_nivel false, soportes = número de adjuntos. Con status=ALL → 2 filas.

    public function test_cross_cost_center_transfer_approved_by_substitute_is_a_level_violation(): void
    // Traspaso APROBADO entre dos centros con approval_level SUPLENTE → nivel_requerido 'Dirección', violacion_nivel true.
    // Mismo traspaso con DIRECCION → false. Traspaso APROBADO con approval_level NULL → true (flujo anterior).
    // Traspaso dentro del mismo centro (distinto mes) con SUPLENTE → nivel_requerido '—', false.

    public function test_cross_company_flag(): void
    // Traspaso entre centros de empresas distintas → entre_empresas true; misma empresa → false.

    public function test_filters_combine(): void
    // movement_type, company_ids, cost_center_ids (origen o destino), authorized_by, amount_greater_than,
    // only_level_violations y rango de fechas (sobre approved_at) reducen las filas como se espera.

    public function test_kpis_total_by_type(): void
    // 'Movimientos', 'Importe total', 'Ampliaciones', 'Reducciones', 'Traspasos', 'Violaciones de nivel'.

    public function test_reconstruction_matches_current_budget_after_approved_movements(): void
    // Presupuesto APROBADO 2026 con distribución mes 3 = 1000 y foto (capture APPROVAL). Aprobar por HTTP (director)
    // una ampliación de 250 al mismo renglón → fila: original 1000, aumentos 250, disminuciones 0, vigente 1250,
    // actual 1250, diferencia 0, origen_foto 'Aprobación'.

    public function test_reconstruction_flags_direct_edits(): void
    // Igual, pero después se edita assigned_amount a 1400 directamente → diferencia 150.

    public function test_reconstruction_without_baseline_is_labeled(): void
    // Presupuesto APROBADO sin foto → origen_foto 'Sin foto', original 0, diferencia = actual.
```

Escribe cada caso completo con aserciones concretas sobre las claves de `COLUMNS` / `RECONSTRUCTION_COLUMNS`.

- [ ] **Step 2: Run to verify it fails** — `--filter=BudgetMovementsReportTest` → FAIL (clase inexistente).

- [ ] **Step 3: Implement** `app/Reports/Budget/BudgetMovementsReport.php`:

```php
<?php

namespace App\Reports\Budget;

use App\Models\AnnualBudget;
use App\Models\BudgetBaseline;
use App\Models\BudgetCedula;
use App\Models\BudgetMovement;
use App\Models\BudgetMovementDetail;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\BudgetBaselineService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** RP-02 · Movimientos y traspasos presupuestales. */
class BudgetMovementsReport
{
    public const COLUMNS = [
        'folio' => 'Folio', 'fecha' => 'Fecha', 'tipo' => 'Tipo', 'importe' => 'Importe',
        'empresa_origen' => 'Empresa origen', 'centro_origen' => 'Centro de costo origen', 'renglon_origen' => 'Renglón origen',
        'empresa_destino' => 'Empresa destino', 'centro_destino' => 'Centro de costo destino', 'renglon_destino' => 'Renglón destino',
        'motivo' => 'Motivo', 'solicitante' => 'Solicitante', 'autorizador' => 'Autorizador',
        'nivel_aplicado' => 'Nivel aplicado', 'nivel_requerido' => 'Nivel requerido', 'violacion_nivel' => 'Violación de nivel',
        'entre_empresas' => 'Entre empresas', 'soportes' => 'Documentos soporte', 'estatus' => 'Estatus',
    ];

    public const RECONSTRUCTION_COLUMNS = [
        'empresa' => 'Empresa', 'centro' => 'Centro de costo', 'mes' => 'Mes', 'cuenta' => 'Cuenta', 'subcuenta' => 'Subcuenta',
        'original' => 'Original', 'aumentos' => 'Ampliaciones y traspasos entrantes', 'disminuciones' => 'Reducciones y traspasos salientes',
        'vigente' => 'Vigente calculado', 'actual' => 'Asignado actual', 'diferencia' => 'Diferencia', 'origen_foto' => 'Origen de la foto',
    ];

    private const TYPE_LABELS = [BudgetMovement::TYPE_INCREASE => 'Ampliación', BudgetMovement::TYPE_DECREASE => 'Reducción', BudgetMovement::TYPE_TRANSFER => 'Traspaso'];

    private const STATUS_LABELS = [
        BudgetMovement::STATUS_PENDING => 'Pendiente', BudgetMovement::STATUS_PENDING_ORIGIN => 'Validación de origen',
        BudgetMovement::STATUS_PENDING_EXECUTIVE => 'Pendiente Dirección', BudgetMovement::STATUS_RETURNED => 'Devuelto',
        BudgetMovement::STATUS_APPROVED => 'Autorizado', BudgetMovement::STATUS_REJECTED => 'Rechazado',
    ];

    private const MONTHS = [1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'];

    public function __construct(private readonly BudgetBaselineService $baselines) {}

    public function rows(array $filters): Collection
    {
        $status = $filters['status'] ?? BudgetMovement::STATUS_APPROVED;
        $from = Carbon::parse($filters['date_from'] ?? now()->startOfYear())->startOfDay();
        $to = Carbon::parse($filters['date_to'] ?? now())->endOfDay();

        $rows = BudgetMovement::query()
            ->with(['details.costCenter.company', 'details.expenseCategory', 'details.budgetCedula', 'creator', 'approver'])
            ->withCount('attachments')
            ->when($status !== 'ALL', fn ($q) => $q->where('status', $status))
            ->where(fn ($q) => $q->whereBetween('approved_at', [$from, $to])
                ->orWhere(fn ($q) => $q->whereNull('approved_at')->whereBetween('movement_date', [$from->toDateString(), $to->toDateString()])))
            ->when($filters['movement_type'] ?? null, fn ($q, $type) => $q->where('movement_type', $type))
            ->when($filters['authorized_by'] ?? null, fn ($q, $userId) => $q->where('approved_by', $userId))
            ->when((float) ($filters['amount_greater_than'] ?? 0) > 0, fn ($q) => $q->where('total_amount', '>', (float) $filters['amount_greater_than']))
            ->when($filters['company_ids'] ?? [], fn ($q, $ids) => $q->whereHas('details.costCenter', fn ($c) => $c->whereIn('company_id', $ids)))
            ->when($filters['cost_center_ids'] ?? [], fn ($q, $ids) => $q->whereHas('details', fn ($d) => $d->whereIn('cost_center_id', $ids)))
            ->get()
            ->map(fn (BudgetMovement $movement) => $this->row($movement));

        if (! empty($filters['only_level_violations'])) {
            $rows = $rows->where('violacion_nivel', true);
        }

        return $rows->sortBy([['fecha', 'asc'], ['folio', 'asc']])->values();
    }

    public function kpis(Collection $rows): array
    {
        $sumType = fn (string $label) => round((float) $rows->where('tipo', $label)->sum('importe'), 2);

        return [
            'Movimientos' => $rows->count(),
            'Importe total' => round((float) $rows->sum('importe'), 2),
            'Ampliaciones' => $sumType('Ampliación'),
            'Reducciones' => $sumType('Reducción'),
            'Traspasos' => $sumType('Traspaso'),
            'Violaciones de nivel' => $rows->where('violacion_nivel', true)->count(),
        ];
    }

    public function reconstruction(array $filters): Collection
    {
        $year = (int) ($filters['fiscal_year'] ?? now()->year);
        $categories = ExpenseCategory::pluck('name', 'id');
        $cedulas = BudgetCedula::withTrashed()->pluck('name', 'id');

        return AnnualBudget::query()
            ->with(['costCenter.company', 'baselines'])
            ->where('fiscal_year', $year)->where('status', 'APROBADO')
            ->when($filters['company_ids'] ?? [], fn ($q, $ids) => $q->whereHas('costCenter', fn ($c) => $c->whereIn('company_id', $ids)))
            ->when($filters['cost_center_ids'] ?? [], fn ($q, $ids) => $q->whereIn('cost_center_id', $ids))
            ->get()
            ->flatMap(function (AnnualBudget $budget) use ($year, $categories, $cedulas) {
                $current = $budget->monthlyDistributions()
                    ->groupBy('month', 'expense_category_id', 'budget_cedula_id')
                    ->selectRaw('month, expense_category_id, budget_cedula_id, SUM(assigned_amount) as amount')->get()
                    ->mapWithKeys(fn ($r) => [BudgetBaselineService::lineKey($r->month, $r->expense_category_id, $r->budget_cedula_id) => (float) $r->amount]);
                $baseline = $budget->baselines->mapWithKeys(fn (BudgetBaseline $b) => [BudgetBaselineService::lineKey($b->month, $b->expense_category_id, $b->budget_cedula_id) => $b]);
                $effects = $this->baselines->approvedMovementEffects($budget->cost_center_id, $year);
                $source = $budget->baselines->first()?->source;

                return $current->keys()->merge($baseline->keys())->merge(array_keys($effects))->unique()
                    ->map(function (string $key) use ($budget, $current, $baseline, $effects, $source, $categories, $cedulas) {
                        [$month, $categoryId, $cedulaId] = explode('|', $key);
                        $original = (float) ($baseline[$key]->original_amount ?? 0);
                        $increases = $effects[$key]['increases'] ?? 0.0;
                        $decreases = $effects[$key]['decreases'] ?? 0.0;
                        $expected = round($original + $increases + $decreases, 2);
                        $actual = round($current[$key] ?? 0, 2);

                        return [
                            'empresa' => $budget->costCenter?->company?->name,
                            'centro' => trim(($budget->costCenter?->code ?? '').' · '.($budget->costCenter?->name ?? ''), ' ·'),
                            'mes' => self::MONTHS[(int) $month] ?? $month,
                            'cuenta' => $categories[(int) $categoryId] ?? '—',
                            'subcuenta' => $cedulaId === '-' ? 'Sin subcuenta' : ($cedulas[(int) $cedulaId] ?? '—'),
                            'original' => round($original, 2),
                            'aumentos' => round($increases, 2),
                            'disminuciones' => round($decreases, 2),
                            'vigente' => $expected,
                            'actual' => $actual,
                            'diferencia' => round($actual - $expected, 2),
                            'origen_foto' => $source ? BudgetBaseline::SOURCE_LABELS[$source] : 'Sin foto',
                            'sort' => sprintf('%s|%02d|%s', $budget->costCenter?->code, (int) $month, $categories[(int) $categoryId] ?? ''),
                        ];
                    });
            })
            ->sortBy('sort')->map(fn (array $row) => collect($row)->except('sort')->all())->values();
    }

    public function filterOptions(): array
    {
        return [
            'companies' => Company::orderBy('name')->get(['id', 'name']),
            'costCenters' => CostCenter::orderBy('name')->get(['id', 'code', 'name']),
            'authorizers' => User::whereIn('id', BudgetMovement::whereNotNull('approved_by')->select('approved_by'))->orderBy('name')->get(['id', 'name']),
            'types' => self::TYPE_LABELS,
            'statuses' => self::STATUS_LABELS,
        ];
    }

    private function row(BudgetMovement $movement): array
    {
        $detail = fn (string $type) => $movement->details->firstWhere('detail_type', $type);
        $adjustment = $detail(BudgetMovementDetail::TYPE_ADJUSTMENT);
        $origin = $detail(BudgetMovementDetail::TYPE_ORIGIN) ?? ($movement->movement_type === BudgetMovement::TYPE_DECREASE ? $adjustment : null);
        $target = $detail(BudgetMovementDetail::TYPE_DESTINATION) ?? ($movement->movement_type === BudgetMovement::TYPE_INCREASE ? $adjustment : null);

        $isTransfer = $movement->movement_type === BudgetMovement::TYPE_TRANSFER;
        $crossCompany = $origin && $target && (int) $origin->costCenter?->company_id !== (int) $target->costCenter?->company_id;
        $crossCenter = $origin && $target && (int) $origin->cost_center_id !== (int) $target->cost_center_id;
        $requiresDirection = $isTransfer && ($crossCenter || $crossCompany);
        $approved = $movement->status === BudgetMovement::STATUS_APPROVED;

        return [
            'id' => $movement->id,
            'folio' => 'MP-'.str_pad((string) $movement->id, 6, '0', STR_PAD_LEFT),
            'fecha' => ($movement->approved_at ?? $movement->movement_date)?->format('Y-m-d'),
            'tipo' => self::TYPE_LABELS[$movement->movement_type] ?? $movement->movement_type,
            'importe' => round((float) $movement->total_amount, 2),
            'empresa_origen' => $origin?->costCenter?->company?->name,
            'centro_origen' => $this->centerLabel($origin),
            'renglon_origen' => $this->lineLabel($origin),
            'empresa_destino' => $target?->costCenter?->company?->name,
            'centro_destino' => $this->centerLabel($target),
            'renglon_destino' => $this->lineLabel($target),
            'motivo' => $movement->justification,
            'solicitante' => $movement->creator?->name,
            'autorizador' => $movement->approver?->name,
            'nivel_aplicado' => BudgetMovement::LEVEL_LABELS[$movement->approval_level] ?? ($approved ? 'Sin nivel registrado' : '—'),
            'nivel_requerido' => $requiresDirection ? 'Dirección' : '—',
            'violacion_nivel' => $requiresDirection && $approved && $movement->approval_level !== BudgetMovement::LEVEL_DIRECTION,
            'entre_empresas' => $crossCompany,
            'soportes' => (int) $movement->attachments_count,
            'estatus' => self::STATUS_LABELS[$movement->status] ?? $movement->status,
        ];
    }

    private function centerLabel(?BudgetMovementDetail $detail): ?string
    {
        return $detail?->costCenter ? trim($detail->costCenter->code.' · '.$detail->costCenter->name, ' ·') : null;
    }

    private function lineLabel(?BudgetMovementDetail $detail): ?string
    {
        if (! $detail) {
            return null;
        }

        return collect([self::MONTHS[(int) $detail->month] ?? null, $detail->expenseCategory?->name, $detail->budgetCedula?->name ?? 'Sin subcuenta'])
            ->filter()->implode(' · ');
    }
}
```

(`BudgetMovementDetail::TYPE_ORIGIN/TYPE_DESTINATION/TYPE_ADJUSTMENT` ya existen: `ORIGEN`, `DESTINO`, `AJUSTE`.)

- [ ] **Step 4: Run** — `--filter=BudgetMovementsReportTest` → verde.
- [ ] **Step 5: Commit** — `feat: add RP-02 budget movements report service`.

---

### Task 6: Pantalla, datos, exportación y enlace desde "Reportes solicitados"

**Files:**
- Create: `app/Http/Requests/BudgetMovementsReportRequest.php`
- Create: `app/Http/Controllers/RequestedReports/BudgetMovementsReportController.php`
- Create: `resources/views/requested-reports/rp02.blade.php`
- Modify: `routes/web.php` (debajo de la ruta `requested-reports.index`)
- Modify: `config/requested_reports.php` (RP-02: `'route' => 'requested-reports.rp-02.index'`)
- Modify: `resources/views/requested-reports/index.blade.php` (pie de tarjeta)
- Test: `tests/Feature/BudgetMovementsReportHttpTest.php`

**Interfaces:**
- Consumes: `BudgetMovementsReport::{rows,kpis,reconstruction,filterOptions,COLUMNS,RECONSTRUCTION_COLUMNS}`.
- Produces rutas (middleware `role:superadmin`, dentro del grupo `auth` + `lock`): `requested-reports.rp-02.index` (GET `/requested-reports/rp-02`), `.data` (`/data`), `.reconstruction` (`/reconstruction`), `.export` (`/export/{format}`, `xlsx|csv`).

- [ ] **Step 1: Write the failing test** — `tests/Feature/BudgetMovementsReportHttpTest.php`:

```php
    public function test_only_superadmin_can_use_rp02(): void
    // Usuario con permiso 'reportes.ver' (Permission::findOrCreate + givePermissionTo) → 403 en index, data, reconstruction y export/xlsx.

    public function test_superadmin_gets_page_data_and_reconstruction(): void
    // index 200 con 'RP-02' y pestañas 'Movimientos' y 'Reconstrucción'; data (getJson con X-Requested-With) devuelve
    // data[0].folio y kpis['Movimientos']; reconstruction devuelve data con la clave 'diferencia'.

    public function test_export_xlsx_has_both_sheets_and_is_logged(): void
    // GET export/xlsx → streamedContent guardado en archivo temporal; IOFactory::load → hojas 'Movimientos' y 'Reconstrucción';
    // celda de importe numérica (getDataType() === 'n'); activity_log con description 'Exportación de reporte RP-02'.

    public function test_export_csv_contains_headers(): void
    // GET export/csv → contenido empieza con BOM UTF-8 y contiene 'Folio,Fecha,Tipo'.

    public function test_requested_reports_card_links_to_rp02_for_superadmin(): void
    // GET requested-reports.index como superadmin → assertSee(route('requested-reports.rp-02.index')) y 'Abrir reporte'.
```

- [ ] **Step 2: Run to verify it fails.**

- [ ] **Step 3: Implement.**

`app/Http/Requests/BudgetMovementsReportRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Models\BudgetMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BudgetMovementsReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('superadmin');
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'fiscal_year' => ['nullable', 'integer', 'between:2020,2100'],
            'status' => ['nullable', Rule::in(['ALL', BudgetMovement::STATUS_PENDING, BudgetMovement::STATUS_PENDING_ORIGIN, BudgetMovement::STATUS_PENDING_EXECUTIVE, BudgetMovement::STATUS_RETURNED, BudgetMovement::STATUS_APPROVED, BudgetMovement::STATUS_REJECTED])],
            'movement_type' => ['nullable', Rule::in([BudgetMovement::TYPE_INCREASE, BudgetMovement::TYPE_DECREASE, BudgetMovement::TYPE_TRANSFER])],
            'company_ids' => ['nullable', 'array'], 'company_ids.*' => ['integer'],
            'cost_center_ids' => ['nullable', 'array'], 'cost_center_ids.*' => ['integer'],
            'authorized_by' => ['nullable', 'integer'],
            'amount_greater_than' => ['nullable', 'numeric', 'min:0'],
            'only_level_violations' => ['nullable', 'boolean'],
        ];
    }

    public function filters(): array
    {
        return $this->validated();
    }
}
```

`app/Http/Controllers/RequestedReports/BudgetMovementsReportController.php`:

```php
<?php

namespace App\Http\Controllers\RequestedReports;

use App\Http\Controllers\Controller;
use App\Http\Requests\BudgetMovementsReportRequest;
use App\Reports\Budget\BudgetMovementsReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Yajra\DataTables\Facades\DataTables;

/** RP-02 · Movimientos y traspasos presupuestales (solo superadmin). */
class BudgetMovementsReportController extends Controller
{
    private const MONEY = ['importe', 'original', 'aumentos', 'disminuciones', 'vigente', 'actual', 'diferencia'];

    public function __construct(private readonly BudgetMovementsReport $report) {}

    public function index(): View
    {
        return view('requested-reports.rp02', [
            'options' => $this->report->filterOptions(),
            'columns' => BudgetMovementsReport::COLUMNS,
            'reconstructionColumns' => BudgetMovementsReport::RECONSTRUCTION_COLUMNS,
            'defaultFrom' => now()->startOfYear()->toDateString(),
            'defaultTo' => now()->toDateString(),
        ]);
    }

    public function data(BudgetMovementsReportRequest $request): JsonResponse
    {
        $rows = $this->report->rows($request->filters());

        return DataTables::of($rows)->with(['kpis' => $this->report->kpis($rows)])->toJson();
    }

    public function reconstruction(BudgetMovementsReportRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->report->reconstruction($request->filters())]);
    }

    public function export(BudgetMovementsReportRequest $request, string $format): StreamedResponse
    {
        abort_unless(in_array($format, ['xlsx', 'csv'], true), 404);
        $filters = $request->filters();
        $rows = $this->report->rows($filters);
        $reconstruction = $format === 'xlsx' ? $this->report->reconstruction($filters) : collect();

        activity()->causedBy($request->user())
            ->withProperties(['report' => 'RP-02', 'format' => $format, 'filters' => $filters, 'rows' => $rows->count()])
            ->log('Exportación de reporte RP-02');

        $spreadsheet = new Spreadsheet;
        $this->fillSheet($spreadsheet->getActiveSheet()->setTitle('Movimientos'), BudgetMovementsReport::COLUMNS, $rows, $filters, $request->user()->name, $format === 'xlsx');
        if ($format === 'xlsx') {
            $this->fillSheet($spreadsheet->createSheet()->setTitle('Reconstrucción'), BudgetMovementsReport::RECONSTRUCTION_COLUMNS, $reconstruction, $filters, $request->user()->name, true);
            $spreadsheet->setActiveSheetIndex(0);
        }

        $filename = 'rp02_movimientos_presupuestales_'.now()->format('Ymd_His').'.'.$format;

        return response()->streamDownload(function () use ($spreadsheet, $format) {
            $writer = $format === 'csv' ? (new Csv($spreadsheet))->setUseBOM(true) : new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, ['Content-Type' => $format === 'csv' ? 'text/csv; charset=UTF-8' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function fillSheet(Worksheet $sheet, array $columns, Collection $rows, array $filters, string $user, bool $withHeader): void
    {
        $row = 1;
        if ($withHeader) {
            $sheet->setCellValue('A1', 'RP-02 · Movimientos y traspasos presupuestales');
            $sheet->setCellValue('A2', 'Generado: '.now()->format('d/m/Y H:i').' por '.$user);
            $sheet->setCellValue('A3', 'Filtros: '.json_encode($filters, JSON_UNESCAPED_UNICODE));
            $row = 5;
        }

        $sheet->fromArray(array_values($columns), null, 'A'.$row);
        $sheet->getStyle('A'.$row.':'.$sheet->getHighestColumn().$row)->getFont()->setBold(true);

        foreach ($rows as $item) {
            $row++;
            $col = 1;
            foreach (array_keys($columns) as $key) {
                $value = $item[$key] ?? null;
                $cell = $sheet->getCell([$col, $row]);
                if (in_array($key, self::MONEY, true)) {
                    $cell->setValue((float) $value);
                    $cell->getStyle()->getNumberFormat()->setFormatCode('$#,##0.00');
                } elseif ($key === 'fecha' && $value) {
                    $cell->setValue(ExcelDate::PHPToExcel(\Illuminate\Support\Carbon::parse($value)));
                    $cell->getStyle()->getNumberFormat()->setFormatCode('dd/mm/yyyy');
                } elseif (is_bool($value)) {
                    $cell->setValue($value ? 'Sí' : 'No');
                } else {
                    $cell->setValue($value);
                }
                $col++;
            }
        }
    }
}
```

Rutas (debajo de `requested-reports.index`):

```php
    Route::middleware('role:superadmin')->prefix('requested-reports/rp-02')->name('requested-reports.rp-02.')->group(function () {
        Route::get('/', [BudgetMovementsReportController::class, 'index'])->name('index');
        Route::get('/data', [BudgetMovementsReportController::class, 'data'])->name('data');
        Route::get('/reconstruction', [BudgetMovementsReportController::class, 'reconstruction'])->name('reconstruction');
        Route::get('/export/{format}', [BudgetMovementsReportController::class, 'export'])->name('export');
    });
```

(con `use App\Http\Controllers\RequestedReports\BudgetMovementsReportController;` arriba.)

`config/requested_reports.php`: en la entrada RP-02 agregar `'route' => 'requested-reports.rp-02.index',`. En `index.blade.php`, reemplazar el `<span ...>Pendiente de construir</span>` por:

```blade
@if(! empty($report['route']))
    @role('superadmin')
        <a class="btn btn-sm btn-primary" href="{{ route($report['route']) }}"><i class="ti ti-player-play me-1"></i>Abrir reporte</a>
    @else
        <span class="text-success"><i class="ti ti-circle-check me-1"></i>Disponible (solo superadministradores)</span>
    @endrole
@else
    <span class="text-muted"><i class="ti ti-hourglass me-1"></i>Pendiente de construir</span>
@endif
```

`resources/views/requested-reports/rp02.blade.php` — mismo estilo que `reports/show.blade.php` (copiar su bloque `<style>` y la estructura `report-shell` / `report-filter` / `report-kpi`), con:
- Encabezado: kicker "A. Presupuesto y control del compromiso · RP-02", título, descripción del propósito y botón "Reportes solicitados" (volver).
- Formulario `#rp02Filters`: Desde / Hasta (`date`), Estatus (select; `APROBADO` seleccionado; opción "Todos" = `ALL`), Tipo, Empresas (`company_ids[]` select2 múltiple), Centros de costo (`cost_center_ids[]` select2 múltiple), Autorizador, Importe mayor a, checkbox "Solo violaciones de nivel" (`only_level_violations=1`), Año fiscal (para la reconstrucción; por defecto año actual), botones "Actualizar" y "Limpiar filtros".
- KPIs (`#rp02Kpis`) con el formato de `reports/show`.
- Pestañas Bootstrap `nav-tabs`: "Movimientos" (tabla `#rp02Table`, encabezados desde `$columns`) y "Reconstrucción" (tabla `#rp02Reconstruction`, encabezados desde `$reconstructionColumns`).
- JS: DataTable `serverSide:true`, `ajax.url = route('requested-reports.rp-02.data')` con los filtros serializados (`$.param` de `serializeArray` para respetar arreglos), columnas en el orden de `$columns`; renderizadores: `importe` como moneda (`MX$` con `Intl.NumberFormat('es-MX')`), `tipo` como chip (`badge` azul/verde/naranja), `violacion_nivel` como `<i class="ti ti-alert-triangle text-danger">` o vacío, `entre_empresas` Sí/No, `soportes` número. Botones Excel y CSV que navegan a `route('requested-reports.rp-02.export', 'xlsx'|'csv')` + `?` + filtros. `pageLength: 25`, `lengthMenu: [25, 50, 100]`, `scrollX: true`, `fixedHeader` si ya está cargado en el layout, idioma `assets/vendor/datatables.net/es-MX.json`.
- Pestaña Reconstrucción: `fetch(route('requested-reports.rp-02.reconstruction') + '?' + filtros)` al mostrar la pestaña y al aplicar filtros; pinta filas con importes en moneda y la celda `diferencia` con clase `table-danger` y texto en rojo cuando `≠ 0`; mensaje "No hay presupuestos aprobados para el año y filtros seleccionados." si viene vacío.

- [ ] **Step 4: Run** — `--filter="BudgetMovementsReportHttpTest|RequestedReportsIndexTest|BudgetMovementsReportTest"` → verde; `view:cache` / `view:clear` sin errores.
- [ ] **Step 5: Commit** — `feat: add RP-02 budget movements report page and exports`.

---

### Task 7: Verificación final

- [ ] **Step 1:** `C:/PHP83/php.exe artisan test` → 36 fallos preexistentes; ningún fallo nuevo (comparar nombres con la corrida base si el conteo cambia).
- [ ] **Step 2:** `C:/PHP83/php.exe artisan route:list --name=rp-02` (4 rutas con `role:superadmin`) y `--name=budget_movements.attachments`.
- [ ] **Step 3:** Imprimir conexión y BD efectiva y correr `C:/PHP83/php.exe artisan migrate --pretend` → solo las 3 migraciones nuevas. **No** ejecutar `migrate`.
- [ ] **Step 4 (lo hace el usuario en el servidor):** `git pull` → `php artisan migrate` → `php artisan budget:reconstruct-baselines` → `php artisan optimize:clear`; abrir RP-02 y validar que la pestaña Reconstrucción muestre diferencia 0 en los renglones sin ediciones directas.
