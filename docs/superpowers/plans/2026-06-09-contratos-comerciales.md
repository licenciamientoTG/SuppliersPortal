# Módulo Contratos Comerciales — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar contratos marco con proveedores a precio fijo, permitir crear requisiciones desde ellos, y generar OCs automáticamente sin pasar por RFQ.

**Architecture:** Tablas nuevas `contracts` y `contract_products`; columnas nuevas en `requisitions` y `requisition_items`; `quotation_summary_id` nullable en `purchase_orders` para OCs de contrato. CRUD con `ContractController`, carga masiva con `ContractImportService`, requisición con Livewire `ContractRequisitionForm` que al hacer submit genera una `PurchaseOrder` por proveedor.

**Tech Stack:** Laravel 12, Livewire 3, Spatie ActivityLog 4, PHPSpreadsheet 5, Yajra DataTables 12, Bootstrap 5 + Tabler Icons, SQL Server

---

## Mapa de archivos

| Archivo | Acción | Responsabilidad |
|---|---|---|
| `database/migrations/XXXX_create_contracts_table.php` | Crear | Schema contracts |
| `database/migrations/XXXX_create_contract_products_table.php` | Crear | Schema contract_products |
| `database/migrations/XXXX_add_source_type_to_requisitions.php` | Crear | Columna source_type en requisitions |
| `database/migrations/XXXX_add_contract_fields_to_requisition_items.php` | Crear | contract_id, contract_product_id, unit_price, currency_code en requisition_items |
| `database/migrations/XXXX_make_quotation_summary_nullable_in_purchase_orders.php` | Crear | quotation_summary_id nullable + source_type en purchase_orders |
| `app/Enum/ContractStatus.php` | Crear | Enum: active, cancelled; label, badge, options |
| `app/Models/Contract.php` | Crear | Relaciones, scopes, isEligible(), effectiveStatus, nextFolio(), LogsActivity |
| `app/Models/ContractProduct.php` | Crear | belongsTo Contract + ProductService |
| `app/Http/Requests/StoreContractRequest.php` | Crear | Validación crear contrato |
| `app/Http/Requests/UpdateContractRequest.php` | Crear | Validación editar contrato |
| `app/Http/Controllers/ContractController.php` | Crear | index, datatable, create, store, show, edit, update, cancel, importForm, importPreview, importConfirm, downloadTemplate |
| `app/Services/ContractImportService.php` | Crear | Parse, validar, agrupar, deduplicar CSV/Excel |
| `app/Livewire/ContractRequisitionForm.php` | Crear | Formulario reactivo: empresa→contratos→productos→precio snapshot→submit→OC |
| `resources/views/contracts/index.blade.php` | Crear | DataTable con badges |
| `resources/views/contracts/create.blade.php` | Crear | Formulario nuevo contrato + filas de productos con Alpine.js |
| `resources/views/contracts/edit.blade.php` | Crear | Formulario editar contrato |
| `resources/views/contracts/show.blade.php` | Crear | Detalle + tab historial + tab compras realizadas |
| `resources/views/contracts/import.blade.php` | Crear | Upload form + link plantilla |
| `resources/views/contracts/import-preview.blade.php` | Crear | Tabla semáforo + botón confirmar |
| `resources/views/livewire/contract-requisition-form.blade.php` | Crear | Vista Livewire |
| `database/factories/ContractFactory.php` | Crear | Factory para tests |
| `database/factories/ContractProductFactory.php` | Crear | Factory para tests |
| `tests/Feature/ContractCrudTest.php` | Crear | CRUD + cancel |
| `tests/Feature/ContractImportTest.php` | Crear | Importación masiva |
| `tests/Feature/ContractRequisitionTest.php` | Crear | Requisición + OC |
| `routes/web.php` | Modificar | Rutas de contratos |
| `resources/views/layouts/partials/sidebar-staff.blade.php` | Modificar | Link "Contratos" para staff/superadmin |

---

## FASE 1 — Backend base y CRUD

---

### Task 1: Migraciones

**Files:**
- Create: `database/migrations/2026_06_09_000001_create_contracts_table.php`
- Create: `database/migrations/2026_06_09_000002_create_contract_products_table.php`
- Create: `database/migrations/2026_06_09_000003_add_source_type_to_requisitions.php`
- Create: `database/migrations/2026_06_09_000004_add_contract_fields_to_requisition_items.php`
- Create: `database/migrations/2026_06_09_000005_make_quotation_summary_nullable_in_purchase_orders.php`

- [ ] **Step 1: Crear migración contracts**

```php
// database/migrations/2026_06_09_000001_create_contracts_table.php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 20)->unique(); // CONT-YYYY-NNN
            $table->foreignId('supplier_id')->constrained()->noActionOnDelete();
            $table->foreignId('company_id')->constrained()->noActionOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('contract_amount', 14, 2)->default(0);
            $table->string('status', 20)->default('active'); // active | cancelled
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->noActionOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->noActionOnDelete();
            $table->foreignId('updated_by')->constrained('users')->noActionOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
```

- [ ] **Step 2: Crear migración contract_products**

```php
// database/migrations/2026_06_09_000002_create_contract_products_table.php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contract_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_service_id')->constrained('products_services')->noActionOnDelete();
            $table->decimal('unit_price', 14, 4);
            $table->char('currency_code', 3)->default('MXN');
            $table->string('unit_of_measure', 50);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['contract_id', 'product_service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_products');
    }
};
```

- [ ] **Step 3: Crear migración columnas en requisitions y requisition_items**

```php
// database/migrations/2026_06_09_000003_add_source_type_to_requisitions.php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('requisitions', function (Blueprint $table) {
            $table->string('source_type', 20)->default('rfq')->after('status');
            // valores: 'rfq' | 'contract'
        });
    }

    public function down(): void
    {
        Schema::table('requisitions', function (Blueprint $table) {
            $table->dropColumn('source_type');
        });
    }
};
```

```php
// database/migrations/2026_06_09_000004_add_contract_fields_to_requisition_items.php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('requisition_items', function (Blueprint $table) {
            $table->foreignId('contract_id')->nullable()->constrained()->noActionOnDelete();
            $table->foreignId('contract_product_id')->nullable()->constrained()->noActionOnDelete();
            $table->decimal('unit_price', 14, 4)->nullable();
            $table->char('currency_code', 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('requisition_items', function (Blueprint $table) {
            $table->dropForeign(['contract_id']);
            $table->dropForeign(['contract_product_id']);
            $table->dropColumn(['contract_id', 'contract_product_id', 'unit_price', 'currency_code']);
        });
    }
};
```

- [ ] **Step 4: Crear migración purchase_orders — nullable quotation_summary_id + source_type**

```php
// database/migrations/2026_06_09_000005_make_quotation_summary_nullable_in_purchase_orders.php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            // SQL Server: primero drop constraint, luego alter column
            $table->unsignedBigInteger('quotation_summary_id')->nullable()->change();
            $table->string('source_type', 20)->default('rfq')->after('folio');
            // valores: 'rfq' | 'contract'
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('source_type');
            $table->unsignedBigInteger('quotation_summary_id')->nullable(false)->change();
        });
    }
};
```

> **SQL Server:** Si `->change()` falla por la FK existente, ejecutar manualmente:
> `ALTER TABLE purchase_orders ALTER COLUMN quotation_summary_id BIGINT NULL;`

- [ ] **Step 5: Correr migraciones y verificar**

```bash
php artisan migrate
```

Expected: 5 migraciones aplicadas sin errores.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_06_09_*
git commit -m "feat(db): migraciones módulo contratos comerciales"
```

---

### Task 2: Enum ContractStatus

**Files:**
- Create: `app/Enum/ContractStatus.php`

- [ ] **Step 1: Crear enum**

```php
// app/Enum/ContractStatus.php
<?php

namespace App\Enum;

enum ContractStatus: string
{
    case ACTIVE    = 'active';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::ACTIVE    => 'Activo',
            self::CANCELLED => 'Cancelado',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::ACTIVE    => 'success',
            self::CANCELLED => 'secondary',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn($c) => [$c->value => $c->label()])
            ->toArray();
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Enum/ContractStatus.php
git commit -m "feat(enum): ContractStatus (active, cancelled)"
```

---

### Task 3: Modelos Contract y ContractProduct

**Files:**
- Create: `app/Models/Contract.php`
- Create: `app/Models/ContractProduct.php`

- [ ] **Step 1: Crear ContractProduct**

```php
// app/Models/ContractProduct.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContractProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'product_service_id',
        'unit_price',
        'currency_code',
        'unit_of_measure',
        'notes',
    ];

    protected $casts = [
        'unit_price' => 'decimal:4',
    ];

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function product()
    {
        return $this->belongsTo(ProductService::class, 'product_service_id');
    }
}
```

> Verifica el nombre de la clase en `app/Models/ProductService.php` (o `ProductsService.php`) antes de continuar.

- [ ] **Step 2: Crear Contract**

```php
// app/Models/Contract.php
<?php

namespace App\Models;

use App\Enum\ContractStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Contract extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'folio',
        'supplier_id',
        'company_id',
        'start_date',
        'end_date',
        'contract_amount',
        'status',
        'cancellation_reason',
        'cancelled_by',
        'cancelled_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date'    => 'date',
        'end_date'      => 'date',
        'cancelled_at'  => 'datetime',
        'contract_amount' => 'decimal:2',
        'status'        => ContractStatus::class,
    ];

    // ── Relaciones ────────────────────────────────────────────────────────

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function products()
    {
        return $this->hasMany(ContractProduct::class);
    }

    public function cancelledByUser()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeEligible($query)
    {
        return $query->where('status', 'active')
            ->whereDate('end_date', '>=', Carbon::today()->toDateString())
            ->whereHas('supplier', fn($q) => $q->where('status', 'activo'));
    }

    public function scopeByCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    // ── Lógica de negocio ─────────────────────────────────────────────────

    public function isEligible(): bool
    {
        return $this->status === ContractStatus::ACTIVE
            && $this->end_date->gte(Carbon::today())
            && $this->supplier->status === 'activo';
    }

    public function getEffectiveStatusAttribute(): string
    {
        if ($this->status === ContractStatus::CANCELLED) {
            return 'cancelled';
        }
        if ($this->end_date->lt(Carbon::today())) {
            return 'expired';
        }
        return 'active';
    }

    public function getEffectiveStatusLabelAttribute(): string
    {
        return match($this->effective_status) {
            'cancelled' => 'Cancelado',
            'expired'   => 'Vencido',
            default     => 'Activo',
        };
    }

    public function getEffectiveStatusBadgeAttribute(): string
    {
        return match($this->effective_status) {
            'cancelled' => 'secondary',
            'expired'   => 'warning',
            default     => 'success',
        };
    }

    public static function nextFolio(): string
    {
        $year   = date('Y');
        $prefix = "CONT-{$year}-";
        $last   = static::where('folio', 'like', $prefix . '%')
            ->orderBy('folio', 'desc')
            ->value('folio');

        $n = 0;
        if ($last && preg_match('/CONT-\d{4}-(\d+)/', $last, $m)) {
            $n = (int) $m[1];
        }

        return sprintf('%s%03d', $prefix, $n + 1);
    }

    // ── ActivityLog ───────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'supplier_id', 'company_id', 'start_date',
                'end_date', 'status', 'contract_amount',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('contracts');
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Models/Contract.php app/Models/ContractProduct.php
git commit -m "feat(models): Contract y ContractProduct"
```

---

### Task 4: Factories

**Files:**
- Create: `database/factories/ContractFactory.php`
- Create: `database/factories/ContractProductFactory.php`

- [ ] **Step 1: ContractFactory**

```php
// database/factories/ContractFactory.php
<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContractFactory extends Factory
{
    public function definition(): array
    {
        return [
            'folio'            => 'CONT-' . date('Y') . '-' . str_pad(fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'supplier_id'      => Supplier::factory(),
            'company_id'       => Company::factory(),
            'start_date'       => now()->toDateString(),
            'end_date'         => now()->addYear()->toDateString(),
            'contract_amount'  => fake()->randomFloat(2, 10000, 500000),
            'status'           => 'active',
            'created_by'       => User::factory(),
            'updated_by'       => User::factory(),
        ];
    }

    public function expired(): static
    {
        return $this->state(['end_date' => now()->subDay()->toDateString()]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status'              => 'cancelled',
            'cancellation_reason' => 'Cancelado por test',
            'cancelled_at'        => now(),
        ]);
    }
}
```

- [ ] **Step 2: ContractProductFactory**

```php
// database/factories/ContractProductFactory.php
<?php

namespace Database\Factories;

use App\Models\Contract;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContractProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'contract_id'       => Contract::factory(),
            'product_service_id'=> \App\Models\ProductService::factory(),
            'unit_price'        => fake()->randomFloat(4, 10, 9999),
            'currency_code'     => 'MXN',
            'unit_of_measure'   => 'PZA',
            'notes'             => null,
        ];
    }
}
```

> Ajusta el nombre de clase `ProductService` si la clase real se llama diferente (revisa `app/Models/`).

- [ ] **Step 3: Agregar `HasFactory` reference en Contract y ContractProduct** — ya incluido en los modelos del Task 3. Verificar que `Contract::class` tiene `use HasFactory`.

- [ ] **Step 4: Commit**

```bash
git add database/factories/ContractFactory.php database/factories/ContractProductFactory.php
git commit -m "feat(factories): ContractFactory y ContractProductFactory"
```

---

### Task 5: FormRequests

**Files:**
- Create: `app/Http/Requests/StoreContractRequest.php`
- Create: `app/Http/Requests/UpdateContractRequest.php`

- [ ] **Step 1: StoreContractRequest**

```php
// app/Http/Requests/StoreContractRequest.php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContractRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'supplier_id'                => ['required', 'integer', 'exists:suppliers,id'],
            'company_id'                 => ['required', 'integer', 'exists:companies,id'],
            'start_date'                 => ['required', 'date'],
            'end_date'                   => ['required', 'date', 'after:start_date'],
            'contract_amount'            => ['nullable', 'numeric', 'min:0'],
            'products'                   => ['required', 'array', 'min:1'],
            'products.*.product_service_id' => ['required', 'integer', 'exists:products_services,id', 'distinct'],
            'products.*.unit_price'      => ['required', 'numeric', 'min:0.0001'],
            'products.*.currency_code'   => ['required', 'string', 'size:3'],
            'products.*.unit_of_measure' => ['required', 'string', 'max:50'],
            'products.*.notes'           => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'end_date.after'                         => 'La fecha de fin debe ser posterior a la fecha de inicio.',
            'products.required'                      => 'El contrato debe tener al menos un producto.',
            'products.*.product_service_id.distinct' => 'No puedes agregar el mismo producto dos veces.',
        ];
    }
}
```

- [ ] **Step 2: UpdateContractRequest**

```php
// app/Http/Requests/UpdateContractRequest.php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContractRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'start_date'                 => ['required', 'date'],
            'end_date'                   => ['required', 'date', 'after:start_date'],
            'contract_amount'            => ['nullable', 'numeric', 'min:0'],
            'products'                   => ['required', 'array', 'min:1'],
            'products.*.product_service_id' => ['required', 'integer', 'exists:products_services,id', 'distinct'],
            'products.*.unit_price'      => ['required', 'numeric', 'min:0.0001'],
            'products.*.currency_code'   => ['required', 'string', 'size:3'],
            'products.*.unit_of_measure' => ['required', 'string', 'max:50'],
            'products.*.notes'           => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'end_date.after' => 'La fecha de fin debe ser posterior a la fecha de inicio.',
        ];
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Requests/StoreContractRequest.php app/Http/Requests/UpdateContractRequest.php
git commit -m "feat(requests): StoreContractRequest y UpdateContractRequest"
```

---

### Task 6: ContractController

**Files:**
- Create: `app/Http/Controllers/ContractController.php`

- [ ] **Step 1: Crear controller**

```php
// app/Http/Controllers/ContractController.php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContractRequest;
use App\Http\Requests\UpdateContractRequest;
use App\Models\Contract;
use App\Models\ContractProduct;
use App\Models\Company;
use App\Models\Supplier;
use App\Services\ContractImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class ContractController extends Controller
{
    public function index()
    {
        return view('contracts.index');
    }

    public function datatable(Request $request)
    {
        if (! $request->ajax()) {
            abort(403);
        }

        $query = Contract::with(['supplier', 'company', 'creator'])
            ->select('contracts.*');

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('folio_col', fn($c) => '<span class="fw-bold">' . $c->folio . '</span>')
            ->addColumn('supplier_name', fn($c) => $c->supplier->company_name ?? '—')
            ->addColumn('company_name', fn($c) => $c->company->name ?? '—')
            ->addColumn('start_date_col', fn($c) => $c->start_date->format('d/m/Y'))
            ->addColumn('end_date_col', fn($c) => $c->end_date->format('d/m/Y'))
            ->addColumn('status_col', function ($c) {
                return '<span class="badge bg-' . $c->effective_status_badge . '">'
                    . '<i class="ti ti-' . ($c->effective_status === 'active' ? 'file-check' : ($c->effective_status === 'expired' ? 'clock-x' : 'file-x')) . ' me-1"></i>'
                    . $c->effective_status_label . '</span>';
            })
            ->addColumn('actions', function ($c) {
                $show = route('contracts.show', $c->id);
                $edit = route('contracts.edit', $c->id);
                $btns = '<a href="' . $show . '" class="btn btn-sm btn-outline-primary me-1"><i class="ti ti-eye"></i></a>';
                if ($c->effective_status === 'active') {
                    $btns .= '<a href="' . $edit . '" class="btn btn-sm btn-outline-secondary"><i class="ti ti-edit"></i></a>';
                }
                return $btns;
            })
            ->rawColumns(['folio_col', 'status_col', 'actions'])
            ->make(true);
    }

    public function create()
    {
        $companies = Company::where('is_active', true)->orderBy('name')->get();
        $suppliers = Supplier::where('status', 'activo')->orderBy('company_name')->get();
        return view('contracts.create', compact('companies', 'suppliers'));
    }

    public function store(StoreContractRequest $request)
    {
        DB::transaction(function () use ($request) {
            $contract = Contract::create([
                'folio'           => Contract::nextFolio(),
                'supplier_id'     => $request->supplier_id,
                'company_id'      => $request->company_id,
                'start_date'      => $request->start_date,
                'end_date'        => $request->end_date,
                'contract_amount' => $request->contract_amount ?? 0,
                'status'          => 'active',
                'created_by'      => Auth::id(),
                'updated_by'      => Auth::id(),
            ]);

            foreach ($request->products as $p) {
                $contract->products()->create([
                    'product_service_id' => $p['product_service_id'],
                    'unit_price'         => $p['unit_price'],
                    'currency_code'      => $p['currency_code'],
                    'unit_of_measure'    => $p['unit_of_measure'],
                    'notes'              => $p['notes'] ?? null,
                ]);
            }
        });

        return redirect()->route('contracts.index')
            ->with('success', 'Contrato creado correctamente.');
    }

    public function show(Contract $contract)
    {
        $contract->load(['supplier', 'company', 'products.product', 'creator', 'cancelledByUser']);
        $history = activity()->forSubject($contract)->latest()->get();
        $purchases = \App\Models\RequisitionItem::with(['requisition'])
            ->where('contract_id', $contract->id)
            ->latest()
            ->paginate(20);

        return view('contracts.show', compact('contract', 'history', 'purchases'));
    }

    public function edit(Contract $contract)
    {
        abort_if($contract->effective_status !== 'active', 403, 'Solo se pueden editar contratos activos.');
        $contract->load('products');
        $companies = Company::where('is_active', true)->orderBy('name')->get();
        $suppliers = Supplier::where('status', 'activo')->orderBy('company_name')->get();
        return view('contracts.edit', compact('contract', 'companies', 'suppliers'));
    }

    public function update(UpdateContractRequest $request, Contract $contract)
    {
        abort_if($contract->effective_status !== 'active', 403);

        DB::transaction(function () use ($request, $contract) {
            $contract->update([
                'start_date'      => $request->start_date,
                'end_date'        => $request->end_date,
                'contract_amount' => $request->contract_amount ?? 0,
                'updated_by'      => Auth::id(),
            ]);

            // Reemplazar productos: eliminar los que no llegaron, crear/actualizar
            $incomingIds = collect($request->products)->pluck('product_service_id')->filter()->toArray();
            $contract->products()->whereNotIn('product_service_id', $incomingIds)->delete();

            foreach ($request->products as $p) {
                $contract->products()->updateOrCreate(
                    ['product_service_id' => $p['product_service_id']],
                    [
                        'unit_price'      => $p['unit_price'],
                        'currency_code'   => $p['currency_code'],
                        'unit_of_measure' => $p['unit_of_measure'],
                        'notes'           => $p['notes'] ?? null,
                    ]
                );
            }
        });

        return redirect()->route('contracts.show', $contract)
            ->with('success', 'Contrato actualizado.');
    }

    public function cancel(Request $request, Contract $contract)
    {
        $request->validate([
            'cancellation_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        abort_if($contract->status->value !== 'active', 422, 'El contrato ya no está activo.');

        $contract->update([
            'status'              => 'cancelled',
            'cancellation_reason' => $request->cancellation_reason,
            'cancelled_by'        => Auth::id(),
            'cancelled_at'        => now(),
            'updated_by'          => Auth::id(),
        ]);

        activity('contracts')
            ->causedBy(Auth::user())
            ->performedOn($contract)
            ->event('cancelled')
            ->withProperties([
                'old'    => ['status' => 'active'],
                'new'    => ['status' => 'cancelled'],
                'reason' => $request->cancellation_reason,
            ])
            ->log('Contrato cancelado');

        return redirect()->route('contracts.show', $contract)
            ->with('success', 'Contrato cancelado.');
    }

    // ── Carga masiva ─────────────────────────────────────────────────────

    public function importForm()
    {
        return view('contracts.import');
    }

    public function downloadTemplate()
    {
        $csvContent = "empresa_code,supplier_rfc,start_date,end_date,contract_amount,product_code,unit_price,currency\n";
        $csvContent .= "TG001,AAA010101AAA,2026-01-01,2026-12-31,100000,PROD-001,250.5000,MXN\n";

        return response($csvContent, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="plantilla_contratos.csv"',
        ]);
    }

    public function importPreview(Request $request, ContractImportService $service)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,xlsx,xls', 'max:5120'],
        ]);

        $result = $service->preview($request->file('file'));

        return view('contracts.import-preview', compact('result'));
    }

    public function importConfirm(Request $request, ContractImportService $service)
    {
        $request->validate([
            'valid_rows' => ['required', 'string'],
        ]);

        $validRows = json_decode($request->valid_rows, true);
        $created = $service->confirm($validRows);

        return redirect()->route('contracts.index')
            ->with('success', "Importación completa. {$created} contratos creados.");
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Http/Controllers/ContractController.php
git commit -m "feat(controller): ContractController — CRUD, cancel, import"
```

---

### Task 7: Rutas

**Files:**
- Modify: `routes/web.php`

- [ ] **Step 1: Agregar rutas al grupo de contratos**

Agregar al final de `routes/web.php`, antes del último `});` si lo hubiera, o como bloque independiente:

```php
// routes/web.php — Contratos Comerciales
Route::middleware(['auth'])
    ->controller(\App\Http\Controllers\ContractController::class)
    ->prefix('contracts')
    ->name('contracts.')
    ->group(function () {
        Route::get('/datatable', 'datatable')->name('datatable');
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/import', 'importForm')->name('import');
        Route::get('/template', 'downloadTemplate')->name('template');
        Route::post('/import/preview', 'importPreview')->name('import.preview');
        Route::post('/import/confirm', 'importConfirm')->name('import.confirm');
        Route::get('/{contract}', 'show')->name('show');
        Route::get('/{contract}/edit', 'edit')->name('edit');
        Route::put('/{contract}', 'update')->name('update');
        Route::post('/{contract}/cancel', 'cancel')->name('cancel');
    });
```

- [ ] **Step 2: Verificar rutas registradas**

```bash
php artisan route:list --name=contracts
```

Expected: 11 rutas listadas.

- [ ] **Step 3: Commit**

```bash
git add routes/web.php
git commit -m "feat(routes): rutas del módulo contratos comerciales"
```

---

### Task 8: Vistas Blade — index y formulario create/edit

**Files:**
- Create: `resources/views/contracts/index.blade.php`
- Create: `resources/views/contracts/create.blade.php`
- Create: `resources/views/contracts/edit.blade.php`

- [ ] **Step 1: index.blade.php**

```blade
{{-- resources/views/contracts/index.blade.php --}}
@extends('layouts.zircos')

@section('title', 'Contratos Comerciales')
@section('page.title', 'Contratos Comerciales')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Contratos</li>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row mb-3">
        <div class="col">
            <a href="{{ route('contracts.create') }}" class="btn btn-primary">
                <i class="ti ti-plus me-1"></i> Nuevo Contrato
            </a>
            <a href="{{ route('contracts.import') }}" class="btn btn-outline-secondary ms-2">
                <i class="ti ti-upload me-1"></i> Carga Masiva
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table id="contracts-table" class="table table-hover w-100">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Folio</th>
                        <th>Proveedor</th>
                        <th>Empresa</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function () {
    $('#contracts-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("contracts.datatable") }}',
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'folio_col', name: 'folio' },
            { data: 'supplier_name', name: 'supplier.company_name' },
            { data: 'company_name', name: 'company.name' },
            { data: 'start_date_col', name: 'start_date' },
            { data: 'end_date_col', name: 'end_date' },
            { data: 'status_col', name: 'status', orderable: false },
            { data: 'actions', orderable: false, searchable: false },
        ],
    });
});
</script>
@endpush
```

- [ ] **Step 2: create.blade.php** (formulario con Alpine.js para filas de productos)

```blade
{{-- resources/views/contracts/create.blade.php --}}
@extends('layouts.zircos')
@section('title', 'Nuevo Contrato')
@section('page.title', 'Nuevo Contrato')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('contracts.index') }}">Contratos</a></li>
    <li class="breadcrumb-item active">Nuevo</li>
@endsection

@section('content')
<div class="container-fluid" x-data="contractForm()">
    <form action="{{ route('contracts.store') }}" method="POST">
        @csrf

        {{-- Datos generales --}}
        <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0">Datos del contrato</h5></div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label">Empresa <span class="text-danger">*</span></label>
                    <select name="company_id" class="form-select @error('company_id') is-invalid @enderror" required>
                        <option value="">Seleccionar...</option>
                        @foreach($companies as $co)
                            <option value="{{ $co->id }}" {{ old('company_id') == $co->id ? 'selected' : '' }}>
                                {{ $co->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('company_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">Proveedor <span class="text-danger">*</span></label>
                    <select name="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror" required>
                        <option value="">Seleccionar...</option>
                        @foreach($suppliers as $s)
                            <option value="{{ $s->id }}" {{ old('supplier_id') == $s->id ? 'selected' : '' }}>
                                {{ $s->company_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label">Fecha inicio <span class="text-danger">*</span></label>
                    <input type="date" name="start_date" class="form-control @error('start_date') is-invalid @enderror"
                        value="{{ old('start_date') }}" x-model="startDate" required>
                    @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label">Fecha fin <span class="text-danger">*</span></label>
                    <input type="date" name="end_date" class="form-control @error('end_date') is-invalid @enderror"
                        value="{{ old('end_date') }}" x-model="endDate" required>
                    <template x-if="endInPast">
                        <div class="alert alert-warning py-1 mt-1 small">
                            La fecha de fin está en el pasado. El contrato quedará vencido al guardar.
                        </div>
                    </template>
                    @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label">Monto contrato</label>
                    <input type="number" name="contract_amount" step="0.01" min="0"
                        class="form-control" value="{{ old('contract_amount', 0) }}">
                </div>
            </div>
        </div>

        {{-- Productos --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Productos</h5>
                <button type="button" class="btn btn-sm btn-outline-primary" @click="addRow()">
                    <i class="ti ti-plus me-1"></i> Agregar producto
                </button>
            </div>
            <div class="card-body">
                @error('products')<div class="alert alert-danger">{{ $message }}</div>@enderror
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Producto/Servicio</th>
                            <th>Precio unitario</th>
                            <th>Moneda</th>
                            <th>U/M</th>
                            <th>Notas</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(row, index) in rows" :key="index">
                            <tr>
                                <td>
                                    <select :name="`products[${index}][product_service_id]`" class="form-select form-select-sm" required>
                                        <option value="">Seleccionar...</option>
                                        @foreach(\App\Models\ProductService::where('is_active', true)->orderBy('name')->get() as $prod)
                                            <option value="{{ $prod->id }}">{{ $prod->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="number" :name="`products[${index}][unit_price]`"
                                        step="0.0001" min="0.0001" class="form-control form-control-sm"
                                        x-model="row.unit_price" required>
                                </td>
                                <td>
                                    <select :name="`products[${index}][currency_code]`" class="form-select form-select-sm" x-model="row.currency_code">
                                        <option value="MXN">MXN</option>
                                        <option value="USD">USD</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" :name="`products[${index}][unit_of_measure]`"
                                        class="form-control form-control-sm" x-model="row.unit_of_measure"
                                        placeholder="PZA" required>
                                </td>
                                <td>
                                    <input type="text" :name="`products[${index}][notes]`"
                                        class="form-control form-control-sm" x-model="row.notes">
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                        @click="removeRow(index)" :disabled="rows.length === 1">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Guardar contrato</button>
            <a href="{{ route('contracts.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
function contractForm() {
    return {
        rows: [{ unit_price: '', currency_code: 'MXN', unit_of_measure: '', notes: '' }],
        startDate: '{{ old("start_date") }}',
        endDate: '{{ old("end_date") }}',
        get endInPast() {
            return this.endDate && this.endDate < new Date().toISOString().slice(0, 10);
        },
        addRow() {
            this.rows.push({ unit_price: '', currency_code: 'MXN', unit_of_measure: '', notes: '' });
        },
        removeRow(index) {
            if (this.rows.length > 1) this.rows.splice(index, 1);
        },
    };
}
</script>
@endpush
```

- [ ] **Step 3: edit.blade.php** — igual que create pero pre-poblado. Copiar el blade de create y ajustar:
  - Cambiar `action="{{ route('contracts.store') }}"` → `action="{{ route('contracts.update', $contract) }}"`
  - Agregar `@method('PUT')` dentro del form
  - Pre-poblar valores con `$contract->...` en vez de `old(...)`
  - En el Alpine `contractForm()`, inicializar `rows` con los productos existentes:

```blade
{{-- Dentro del @push('scripts') de edit.blade.php --}}
<script>
function contractForm() {
    return {
        rows: @json($contract->products->map(fn($p) => [
            'product_service_id' => $p->product_service_id,
            'unit_price' => $p->unit_price,
            'currency_code' => $p->currency_code,
            'unit_of_measure' => $p->unit_of_measure,
            'notes' => $p->notes,
        ])),
        startDate: '{{ $contract->start_date->format("Y-m-d") }}',
        endDate: '{{ $contract->end_date->format("Y-m-d") }}',
        get endInPast() {
            return this.endDate && this.endDate < new Date().toISOString().slice(0, 10);
        },
        addRow() { this.rows.push({ unit_price: '', currency_code: 'MXN', unit_of_measure: '', notes: '' }); },
        removeRow(index) { if (this.rows.length > 1) this.rows.splice(index, 1); },
    };
}
</script>
```

- [ ] **Step 4: Commit**

```bash
git add resources/views/contracts/
git commit -m "feat(views): contracts index, create, edit"
```

---

### Task 9: Vista show + modal de cancelación

**Files:**
- Create: `resources/views/contracts/show.blade.php`

- [ ] **Step 1: show.blade.php**

```blade
{{-- resources/views/contracts/show.blade.php --}}
@extends('layouts.zircos')
@section('title', 'Contrato ' . $contract->folio)
@section('page.title', 'Contrato ' . $contract->folio)

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('contracts.index') }}">Contratos</a></li>
    <li class="breadcrumb-item active">{{ $contract->folio }}</li>
@endsection

@section('content')
<div class="container-fluid">

    {{-- Encabezado con estado y acciones --}}
    <div class="card mb-3">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-1">{{ $contract->folio }}</h4>
                <span class="badge bg-{{ $contract->effective_status_badge }} fs-6">
                    <i class="ti ti-{{ $contract->effective_status === 'active' ? 'file-check' : ($contract->effective_status === 'expired' ? 'clock-x' : 'file-x') }} me-1"></i>
                    {{ $contract->effective_status_label }}
                </span>
            </div>
            <div class="d-flex gap-2">
                @if($contract->effective_status === 'active')
                    <a href="{{ route('contracts.edit', $contract) }}" class="btn btn-outline-secondary">
                        <i class="ti ti-edit me-1"></i> Editar
                    </a>
                    <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal">
                        <i class="ti ti-ban me-1"></i> Cancelar contrato
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Datos generales --}}
    <div class="card mb-3">
        <div class="card-body row g-3">
            <div class="col-md-4"><strong>Proveedor:</strong><br>{{ $contract->supplier->company_name }}</div>
            <div class="col-md-4"><strong>Empresa:</strong><br>{{ $contract->company->name }}</div>
            <div class="col-md-4"><strong>Monto contrato:</strong><br>${{ number_format($contract->contract_amount, 2) }}</div>
            <div class="col-md-4"><strong>Vigencia:</strong><br>{{ $contract->start_date->format('d/m/Y') }} — {{ $contract->end_date->format('d/m/Y') }}</div>
            <div class="col-md-4"><strong>Creado por:</strong><br>{{ $contract->creator->name }}</div>
        </div>
    </div>

    {{-- Tabs --}}
    <ul class="nav nav-tabs mb-3" id="contractTabs">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-products">Productos</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-history">Historial</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-purchases">Compras realizadas</a></li>
    </ul>

    <div class="tab-content">
        {{-- Tab: Productos --}}
        <div class="tab-pane fade show active" id="tab-products">
            <div class="card">
                <div class="card-body">
                    <table class="table table-sm">
                        <thead>
                            <tr><th>Producto</th><th>Precio unitario</th><th>Moneda</th><th>U/M</th><th>Notas</th></tr>
                        </thead>
                        <tbody>
                            @foreach($contract->products as $cp)
                            <tr>
                                <td>{{ $cp->product->name ?? $cp->product_service_id }}</td>
                                <td>${{ number_format($cp->unit_price, 4) }}</td>
                                <td>{{ $cp->currency_code }}</td>
                                <td>{{ $cp->unit_of_measure }}</td>
                                <td>{{ $cp->notes ?? '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Tab: Historial --}}
        <div class="tab-pane fade" id="tab-history">
            <div class="card">
                <div class="card-body">
                    @forelse($history as $log)
                    <div class="d-flex mb-2">
                        <div class="text-muted small me-3" style="min-width:140px">{{ $log->created_at->format('d/m/Y H:i') }}</div>
                        <div>
                            <strong>{{ $log->causer->name ?? 'Sistema' }}</strong>
                            <span class="text-muted ms-1">{{ $log->description }}</span>
                        </div>
                    </div>
                    @empty
                    <p class="text-muted">Sin historial.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Tab: Compras realizadas --}}
        <div class="tab-pane fade" id="tab-purchases">
            <div class="card">
                <div class="card-body">
                    <table class="table table-sm">
                        <thead>
                            <tr><th>Requisición</th><th>Producto</th><th>Cantidad</th><th>Precio snapshot</th><th>Fecha</th></tr>
                        </thead>
                        <tbody>
                            @forelse($purchases as $item)
                            <tr>
                                <td>
                                    <a href="{{ route('requisitions.show', $item->requisition_id) }}">
                                        {{ $item->requisition->folio ?? $item->requisition_id }}
                                    </a>
                                </td>
                                <td>{{ $item->description }}</td>
                                <td>{{ $item->quantity }}</td>
                                <td>${{ number_format($item->unit_price, 4) }} {{ $item->currency_code }}</td>
                                <td>{{ $item->created_at->format('d/m/Y') }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-muted">Sin compras registradas.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    {{ $purchases->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal cancelar --}}
@if($contract->effective_status === 'active')
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('contracts.cancel', $contract) }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cancelar contrato {{ $contract->folio }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Motivo de cancelación <span class="text-danger">*</span></label>
                    <textarea name="cancellation_reason" class="form-control" rows="3" minlength="10" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-danger">Confirmar cancelación</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/contracts/show.blade.php
git commit -m "feat(views): contracts show con tabs historial y compras"
```

---

### Task 10: Sidebar + Tests CRUD

**Files:**
- Modify: `resources/views/layouts/partials/sidebar-staff.blade.php`
- Create: `tests/Feature/ContractCrudTest.php`

- [ ] **Step 1: Agregar "Contratos" al sidebar**

En `sidebar-staff.blade.php`, dentro del bloque `@if ($showPurchasingSection)`, después del `@endmoduleAccess` de Requisiciones (línea ~134), agregar:

```blade
@hasanyrole('superadmin|staff')
<li class="side-nav-item">
    <a href="{{ route('contracts.index') }}"
        class="side-nav-link {{ request()->routeIs('contracts.*') ? 'active' : '' }}">
        <span class="menu-icon"><i class="ti ti-file-invoice"></i></span>
        <span class="menu-text">Contratos</span>
    </a>
</li>
@endhasanyrole
```

También agregar `'contracts'` al `$showPurchasingSection` collector (línea ~6):

```php
$showPurchasingSection = collect([
    'requisitions',
    'quotations',
    'purchase_orders',
    'receptions',
    'products_services',
    'contracts',    // <-- agregar
])->contains(fn ($module) => $moduleAccess->userCanAccessModule($user, $module));
```

- [ ] **Step 2: Escribir tests CRUD**

```php
// tests/Feature/ContractCrudTest.php
<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractProduct;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractCrudTest extends TestCase
{
    use RefreshDatabase;

    private function buyer(): User
    {
        return User::factory()->create();
    }

    private function validPayload(Company $company, Supplier $supplier, array $overrides = []): array
    {
        return array_merge([
            'company_id'      => $company->id,
            'supplier_id'     => $supplier->id,
            'start_date'      => now()->toDateString(),
            'end_date'        => now()->addYear()->toDateString(),
            'contract_amount' => 50000,
            'products'        => [[
                'product_service_id' => \App\Models\ProductService::factory()->create()->id,
                'unit_price'         => 100.00,
                'currency_code'      => 'MXN',
                'unit_of_measure'    => 'PZA',
                'notes'              => null,
            ]],
        ], $overrides);
    }

    public function test_store_creates_contract_and_products(): void
    {
        $user     = $this->buyer();
        $company  = Company::factory()->create(['is_active' => true]);
        $supplier = Supplier::factory()->create(['status' => 'activo']);

        $response = $this->actingAs($user)
            ->post(route('contracts.store'), $this->validPayload($company, $supplier));

        $response->assertRedirect(route('contracts.index'));
        $this->assertDatabaseHas('contracts', [
            'company_id'  => $company->id,
            'supplier_id' => $supplier->id,
            'status'      => 'active',
        ]);
        $this->assertDatabaseCount('contract_products', 1);
    }

    public function test_folio_format_is_correct(): void
    {
        $user     = $this->buyer();
        $company  = Company::factory()->create(['is_active' => true]);
        $supplier = Supplier::factory()->create(['status' => 'activo']);

        $this->actingAs($user)
            ->post(route('contracts.store'), $this->validPayload($company, $supplier));

        $folio = Contract::latest()->value('folio');
        $this->assertMatchesRegularExpression('/^CONT-\d{4}-\d{3}$/', $folio);
    }

    public function test_store_fails_when_end_date_before_start_date(): void
    {
        $user     = $this->buyer();
        $company  = Company::factory()->create(['is_active' => true]);
        $supplier = Supplier::factory()->create(['status' => 'activo']);

        $response = $this->actingAs($user)->post(route('contracts.store'),
            $this->validPayload($company, $supplier, [
                'start_date' => now()->toDateString(),
                'end_date'   => now()->subDay()->toDateString(),
            ])
        );

        $response->assertSessionHasErrors('end_date');
        $this->assertDatabaseCount('contracts', 0);
    }

    public function test_cancel_sets_status_to_cancelled(): void
    {
        $user     = $this->buyer();
        $contract = Contract::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->post(route('contracts.cancel', $contract), [
            'cancellation_reason' => 'Proveedor no cumplió condiciones pactadas.',
        ]);

        $response->assertRedirect(route('contracts.show', $contract));
        $contract->refresh();
        $this->assertEquals('cancelled', $contract->status->value);
        $this->assertNotNull($contract->cancelled_at);
    }

    public function test_cancel_requires_reason(): void
    {
        $user     = $this->buyer();
        $contract = Contract::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->post(route('contracts.cancel', $contract), [
            'cancellation_reason' => 'corto',
        ]);

        $response->assertSessionHasErrors('cancellation_reason');
        $contract->refresh();
        $this->assertEquals('active', $contract->status->value);
    }

    public function test_update_replaces_products(): void
    {
        $user     = $this->buyer();
        $contract = Contract::factory()->create(['status' => 'active']);
        $old      = ContractProduct::factory()->create(['contract_id' => $contract->id]);
        $newProd  = \App\Models\ProductService::factory()->create();

        $this->actingAs($user)->put(route('contracts.update', $contract), [
            'start_date'      => $contract->start_date->toDateString(),
            'end_date'        => $contract->end_date->toDateString(),
            'contract_amount' => 0,
            'products'        => [[
                'product_service_id' => $newProd->id,
                'unit_price'         => 200,
                'currency_code'      => 'MXN',
                'unit_of_measure'    => 'KG',
                'notes'              => null,
            ]],
        ]);

        $this->assertDatabaseMissing('contract_products', ['id' => $old->id]);
        $this->assertDatabaseHas('contract_products', ['product_service_id' => $newProd->id]);
    }
}
```

- [ ] **Step 3: Correr tests**

```bash
php artisan test --filter=ContractCrudTest
```

Expected: 5 tests passed.

- [ ] **Step 4: Commit**

```bash
git add resources/views/layouts/partials/sidebar-staff.blade.php tests/Feature/ContractCrudTest.php
git commit -m "feat(tests): ContractCrudTest — CRUD y cancelación"
```

---

## FASE 2 — Carga masiva (independiente de Fases 3 y 4)

---

### Task 11: ContractImportService

**Files:**
- Create: `app/Services/ContractImportService.php`

- [ ] **Step 1: Crear servicio**

```php
// app/Services/ContractImportService.php
<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ContractImportService
{
    private const MAX_ROWS = 500;

    /**
     * Parsea el archivo, valida fila a fila y devuelve el resultado de la preview.
     * No guarda nada en BD.
     */
    public function preview(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getPathname());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        // Quitar encabezado
        array_shift($rows);

        if (count($rows) > self::MAX_ROWS) {
            return [
                'error'  => 'El archivo supera el límite de ' . self::MAX_ROWS . ' filas.',
                'valid'  => [],
                'errors' => [],
            ];
        }

        $validRows   = [];
        $errorRows   = [];
        $seenContracts = []; // clave: empresa+rfc+start+end

        foreach ($rows as $i => $row) {
            [$empresaCode, $supplierRfc, $startDate, $endDate, $contractAmount, $productCode, $unitPrice, $currency]
                = array_pad($row, 8, null);

            $lineNum = $i + 2; // +2: header + 1-based
            $errors  = [];

            $company = Company::where('code', $empresaCode)->where('is_active', true)->first();
            if (! $company) {
                $errors[] = "Empresa '{$empresaCode}' no encontrada o inactiva.";
            }

            $supplier = Supplier::where('rfc', $supplierRfc)->where('status', 'activo')->first();
            if (! $supplier) {
                $errors[] = "Proveedor RFC '{$supplierRfc}' no encontrado o inactivo.";
            }

            if (! $startDate || ! strtotime($startDate)) {
                $errors[] = "start_date inválida: '{$startDate}'.";
            }
            if (! $endDate || ! strtotime($endDate)) {
                $errors[] = "end_date inválida: '{$endDate}'.";
            }
            if ($startDate && $endDate && strtotime($endDate) <= strtotime($startDate)) {
                $errors[] = "end_date debe ser posterior a start_date.";
            }

            $product = \App\Models\ProductService::where('code', $productCode)
                ->where('is_active', true)
                ->first();
            if (! $product) {
                $errors[] = "Producto '{$productCode}' no encontrado o inactivo.";
            }

            if (! is_numeric($unitPrice) || $unitPrice <= 0) {
                $errors[] = "unit_price inválido: '{$unitPrice}'.";
            }

            // Deduplicación en archivo
            $contractKey = "{$empresaCode}|{$supplierRfc}|{$startDate}|{$endDate}";
            if (isset($seenContracts[$contractKey]) && empty($errors)) {
                // mismo contrato en múltiples filas → agrupar (correcto)
            }

            // Deduplicación en BD
            if (empty($errors) && $company && $supplier) {
                $exists = Contract::where('company_id', $company->id)
                    ->where('supplier_id', $supplier->id)
                    ->whereDate('start_date', Carbon::parse($startDate)->toDateString())
                    ->whereDate('end_date', Carbon::parse($endDate)->toDateString())
                    ->exists();
                if ($exists) {
                    $errors[] = "Ya existe un contrato para empresa+proveedor+fechas en BD.";
                }
            }

            $parsedRow = [
                'line'             => $lineNum,
                'empresa_code'     => $empresaCode,
                'supplier_rfc'     => $supplierRfc,
                'company_id'       => $company?->id,
                'supplier_id'      => $supplier?->id,
                'start_date'       => $startDate,
                'end_date'         => $endDate,
                'contract_amount'  => is_numeric($contractAmount) ? $contractAmount : 0,
                'product_service_id' => $product?->id,
                'product_code'     => $productCode,
                'unit_price'       => $unitPrice,
                'currency_code'    => $currency ?: 'MXN',
                'contract_key'     => $contractKey,
            ];

            if ($errors) {
                $parsedRow['errors'] = $errors;
                $errorRows[] = $parsedRow;
            } else {
                $seenContracts[$contractKey] = true;
                $validRows[] = $parsedRow;
            }
        }

        return [
            'valid'  => $validRows,
            'errors' => $errorRows,
        ];
    }

    /**
     * Recibe las filas válidas (ya procesadas por preview) y las persiste.
     */
    public function confirm(array $validRows): int
    {
        $grouped = collect($validRows)->groupBy('contract_key');
        $count   = 0;

        DB::transaction(function () use ($grouped, &$count) {
            foreach ($grouped as $key => $rows) {
                $first    = $rows->first();
                $folio    = Contract::nextFolio();

                $contract = Contract::create([
                    'folio'           => $folio,
                    'company_id'      => $first['company_id'],
                    'supplier_id'     => $first['supplier_id'],
                    'start_date'      => $first['start_date'],
                    'end_date'        => $first['end_date'],
                    'contract_amount' => $first['contract_amount'],
                    'status'          => 'active',
                    'created_by'      => Auth::id(),
                    'updated_by'      => Auth::id(),
                ]);

                foreach ($rows as $row) {
                    $contract->products()->create([
                        'product_service_id' => $row['product_service_id'],
                        'unit_price'         => $row['unit_price'],
                        'currency_code'      => $row['currency_code'],
                        'unit_of_measure'    => 'PZA', // el CSV no tiene U/M; ajustar layout si se requiere
                    ]);
                }

                activity('contracts')
                    ->causedBy(Auth::user())
                    ->performedOn($contract)
                    ->event('bulk_imported')
                    ->withProperties([
                        'created' => 1,
                        'rows'    => $rows->count(),
                        'source'  => 'csv_import',
                    ])
                    ->log('Importación masiva');

                $count++;
            }
        });

        return $count;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Services/ContractImportService.php
git commit -m "feat(service): ContractImportService — preview y confirm"
```

---

### Task 12: Vistas import + tests

**Files:**
- Create: `resources/views/contracts/import.blade.php`
- Create: `resources/views/contracts/import-preview.blade.php`
- Create: `tests/Feature/ContractImportTest.php`

- [ ] **Step 1: import.blade.php**

```blade
{{-- resources/views/contracts/import.blade.php --}}
@extends('layouts.zircos')
@section('title', 'Importar Contratos')
@section('page.title', 'Carga masiva de contratos')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('contracts.index') }}">Contratos</a></li>
    <li class="breadcrumb-item active">Importar</li>
@endsection

@section('content')
<div class="container-fluid">
    <div class="card" style="max-width:600px">
        <div class="card-body">
            <p>
                <a href="{{ route('contracts.template') }}" class="btn btn-outline-secondary btn-sm"
                   title="Descarga la plantilla CSV con el formato requerido">
                    <i class="ti ti-download me-1"></i> Descargar plantilla CSV
                </a>
            </p>

            <form action="{{ route('contracts.import.preview') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Archivo CSV o Excel (máx. 500 filas, 5 MB)</label>
                    <input type="file" name="file" class="form-control @error('file') is-invalid @enderror"
                        accept=".csv,.xlsx,.xls" required>
                    @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="ti ti-eye me-1"></i> Previsualizar
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
```

- [ ] **Step 2: import-preview.blade.php**

```blade
{{-- resources/views/contracts/import-preview.blade.php --}}
@extends('layouts.zircos')
@section('title', 'Previsualizar importación')
@section('page.title', 'Previsualizar importación')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('contracts.index') }}">Contratos</a></li>
    <li class="breadcrumb-item"><a href="{{ route('contracts.import') }}">Importar</a></li>
    <li class="breadcrumb-item active">Previsualizar</li>
@endsection

@section('content')
<div class="container-fluid">

    @if(isset($result['error']))
        <div class="alert alert-danger">{{ $result['error'] }}</div>
    @else

    <div class="alert alert-info">
        Se crearán <strong>{{ collect($result['valid'])->pluck('contract_key')->unique()->count() }}</strong> contratos.
        <strong>{{ count($result['errors']) }}</strong> filas con errores serán omitidas.
    </div>

    {{-- Filas válidas --}}
    @if(count($result['valid']) > 0)
    <div class="card mb-3">
        <div class="card-header text-success"><i class="ti ti-check me-1"></i> Filas válidas ({{ count($result['valid']) }})</div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead><tr><th>Línea</th><th>Empresa</th><th>Proveedor RFC</th><th>Vigencia</th><th>Producto</th><th>Precio</th></tr></thead>
                <tbody>
                    @foreach($result['valid'] as $row)
                    <tr>
                        <td>{{ $row['line'] }}</td>
                        <td>{{ $row['empresa_code'] }}</td>
                        <td>{{ $row['supplier_rfc'] }}</td>
                        <td>{{ $row['start_date'] }} / {{ $row['end_date'] }}</td>
                        <td>{{ $row['product_code'] }}</td>
                        <td>{{ $row['unit_price'] }} {{ $row['currency_code'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Filas con error --}}
    @if(count($result['errors']) > 0)
    <div class="card mb-3">
        <div class="card-header text-danger"><i class="ti ti-x me-1"></i> Filas con errores ({{ count($result['errors']) }})</div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead><tr><th>Línea</th><th>Empresa</th><th>Proveedor RFC</th><th>Producto</th><th>Errores</th></tr></thead>
                <tbody>
                    @foreach($result['errors'] as $row)
                    <tr class="table-danger">
                        <td>{{ $row['line'] }}</td>
                        <td>{{ $row['empresa_code'] }}</td>
                        <td>{{ $row['supplier_rfc'] }}</td>
                        <td>{{ $row['product_code'] }}</td>
                        <td>{{ implode('; ', $row['errors']) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @if(count($result['valid']) > 0)
    <form action="{{ route('contracts.import.confirm') }}" method="POST">
        @csrf
        <input type="hidden" name="valid_rows" value="{{ json_encode($result['valid']) }}">
        <button type="submit" class="btn btn-success">
            <i class="ti ti-check me-1"></i> Confirmar importación
        </button>
        <a href="{{ route('contracts.import') }}" class="btn btn-outline-secondary ms-2">Cancelar</a>
    </form>
    @endif

    @endif
</div>
@endsection
```

- [ ] **Step 3: Tests de importación**

```php
// tests/Feature/ContractImportTest.php
<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ContractImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContractImportTest extends TestCase
{
    use RefreshDatabase;

    private function makeCsvFile(string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'contract_import_') . '.csv';
        file_put_contents($path, $content);
        return new UploadedFile($path, 'contratos.csv', 'text/csv', null, true);
    }

    public function test_preview_returns_valid_and_error_rows(): void
    {
        $company  = Company::factory()->create(['code' => 'TG001', 'is_active' => true]);
        $supplier = Supplier::factory()->create(['rfc' => 'AAA010101AAA', 'status' => 'activo']);
        $product  = \App\Models\ProductService::factory()->create(['code' => 'PROD-001', 'is_active' => true]);

        $csv = "empresa_code,supplier_rfc,start_date,end_date,contract_amount,product_code,unit_price,currency\n"
             . "TG001,AAA010101AAA,2026-01-01,2026-12-31,50000,PROD-001,250.00,MXN\n"
             . "TG001,BADAAA,2026-01-01,2026-12-31,0,PROD-001,100,MXN\n"; // RFC inválido

        $service = app(ContractImportService::class);
        $result  = $service->preview($this->makeCsvFile($csv));

        $this->assertCount(1, $result['valid']);
        $this->assertCount(1, $result['errors']);
    }

    public function test_confirm_creates_contracts(): void
    {
        $user     = User::factory()->create();
        $company  = Company::factory()->create(['code' => 'TG001', 'is_active' => true]);
        $supplier = Supplier::factory()->create(['rfc' => 'AAA010101AAA', 'status' => 'activo']);
        $product  = \App\Models\ProductService::factory()->create(['code' => 'PROD-001', 'is_active' => true]);

        $csv = "empresa_code,supplier_rfc,start_date,end_date,contract_amount,product_code,unit_price,currency\n"
             . "TG001,AAA010101AAA,2026-01-01,2026-12-31,50000,PROD-001,250.00,MXN\n";

        $service = app(ContractImportService::class);
        $preview = $service->preview($this->makeCsvFile($csv));

        $this->actingAs($user);
        $created = $service->confirm($preview['valid']);

        $this->assertEquals(1, $created);
        $this->assertDatabaseCount('contracts', 1);
        $this->assertDatabaseCount('contract_products', 1);
    }

    public function test_preview_rejects_file_over_500_rows(): void
    {
        $header = "empresa_code,supplier_rfc,start_date,end_date,contract_amount,product_code,unit_price,currency\n";
        $row    = "TG001,AAA010101AAA,2026-01-01,2026-12-31,0,P1,1,MXN\n";
        $csv    = $header . str_repeat($row, 501);

        $service = app(ContractImportService::class);
        $result  = $service->preview($this->makeCsvFile($csv));

        $this->assertArrayHasKey('error', $result);
    }
}
```

- [ ] **Step 4: Correr tests**

```bash
php artisan test --filter=ContractImportTest
```

Expected: 3 tests passed.

- [ ] **Step 5: Commit**

```bash
git add resources/views/contracts/import.blade.php resources/views/contracts/import-preview.blade.php tests/Feature/ContractImportTest.php
git commit -m "feat(import): vistas y tests de carga masiva"
```

---

## FASE 3 — Integración con Requisiciones

---

### Task 13: Livewire ContractRequisitionForm

**Files:**
- Create: `app/Livewire/ContractRequisitionForm.php`
- Create: `resources/views/livewire/contract-requisition-form.blade.php`
- Create: `resources/views/contracts/requisition-create.blade.php`

- [ ] **Step 1: Crear componente Livewire**

```php
// app/Livewire/ContractRequisitionForm.php
<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractProduct;
use App\Models\ReceivingLocation;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ContractRequisitionForm extends Component
{
    public $company_id;
    public $required_date;
    public $receiving_location_id;
    public $notes = '';

    public array $items = [];

    // Catálogos
    public $companies      = [];
    public $locations      = [];
    public $eligibleContracts = [];

    // Estado de partida nueva
    public $newItem = [
        'contract_id'            => '',
        'contract_product_id'    => '',
        'quantity'               => 1,
        'cost_center_id'         => '',
        'budget_category_id'     => '',
        'notes'                  => '',
    ];
    public $newItemContractProducts = [];
    public $newItemSnapshotPrice    = null;
    public $newItemCurrency         = 'MXN';

    public function mount(): void
    {
        $this->companies = Company::where('is_active', true)->orderBy('name')->get();
        $this->locations = ReceivingLocation::orderBy('name')->get();
    }

    public function updatedCompanyId(): void
    {
        $this->eligibleContracts = Contract::eligible()
            ->byCompany((int) $this->company_id)
            ->with('supplier')
            ->get();
        $this->newItem['contract_id']         = '';
        $this->newItem['contract_product_id'] = '';
        $this->newItemContractProducts        = [];
        $this->newItemSnapshotPrice           = null;
    }

    public function updatedNewItemContractId($value): void
    {
        if (! $value) {
            $this->newItemContractProducts = [];
            $this->newItemSnapshotPrice    = null;
            return;
        }
        $this->newItemContractProducts = ContractProduct::where('contract_id', $value)
            ->with('product')
            ->get();
        $this->newItem['contract_product_id'] = '';
        $this->newItemSnapshotPrice           = null;
    }

    public function updatedNewItemContractProductId($value): void
    {
        if (! $value) {
            $this->newItemSnapshotPrice = null;
            return;
        }
        $cp = ContractProduct::find($value);
        $this->newItemSnapshotPrice = $cp?->unit_price;
        $this->newItemCurrency      = $cp?->currency_code ?? 'MXN';
    }

    public function addItem(): void
    {
        $this->validate([
            'newItem.contract_id'         => ['required', 'integer'],
            'newItem.contract_product_id' => ['required', 'integer'],
            'newItem.quantity'            => ['required', 'numeric', 'min:0.001'],
        ], [
            'newItem.contract_id.required'         => 'Selecciona un contrato.',
            'newItem.contract_product_id.required' => 'Selecciona un producto.',
            'newItem.quantity.required'            => 'Ingresa la cantidad.',
        ]);

        $contract = Contract::find($this->newItem['contract_id']);
        if (! $contract || ! $contract->isEligible()) {
            $this->addError('newItem.contract_id', 'El contrato seleccionado ya no está activo o el proveedor fue inactivado.');
            return;
        }

        $cp = ContractProduct::where('id', $this->newItem['contract_product_id'])
            ->where('contract_id', $this->newItem['contract_id'])
            ->first();

        if (! $cp) {
            $this->addError('newItem.contract_product_id', 'El producto no pertenece al contrato seleccionado.');
            return;
        }

        // Aviso si el contrato vence en ≤ 30 días
        $daysLeft = Carbon::today()->diffInDays($contract->end_date, false);

        $this->items[] = [
            'contract_id'            => $contract->id,
            'contract_folio'         => $contract->folio,
            'supplier_name'          => $contract->supplier->company_name,
            'contract_product_id'    => $cp->id,
            'product_name'           => $cp->product->name ?? "Producto #{$cp->product_service_id}",
            'product_service_id'     => $cp->product_service_id,
            'unit_price'             => $cp->unit_price,
            'currency_code'          => $cp->currency_code,
            'unit_of_measure'        => $cp->unit_of_measure,
            'quantity'               => $this->newItem['quantity'],
            'cost_center_id'         => $this->newItem['cost_center_id'],
            'budget_category_id'     => $this->newItem['budget_category_id'],
            'notes'                  => $this->newItem['notes'],
            'expiry_warning'         => $daysLeft <= 30 ? $contract->end_date->format('d/m/Y') : null,
        ];

        $this->reset(['newItem', 'newItemContractProducts', 'newItemSnapshotPrice', 'newItemCurrency']);
        $this->newItem = ['contract_id' => '', 'contract_product_id' => '', 'quantity' => 1,
                          'cost_center_id' => '', 'budget_category_id' => '', 'notes' => ''];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function submit(): void
    {
        $this->validate([
            'company_id'            => ['required', 'integer', 'exists:companies,id'],
            'required_date'         => ['required', 'date'],
            'receiving_location_id' => ['required', 'integer', 'exists:receiving_locations,id'],
            'items'                 => ['required', 'array', 'min:1'],
        ]);

        // Re-validar elegibilidad de todos los contratos en el submit
        foreach ($this->items as $item) {
            $contract = Contract::find($item['contract_id']);
            if (! $contract || ! $contract->isEligible()) {
                session()->flash('error', "El contrato {$item['contract_folio']} ya no está activo. Revisa las partidas.");
                return;
            }
        }

        DB::transaction(function () {
            $requisition = Requisition::create([
                'folio'                 => Requisition::nextFolio(),
                'company_id'            => $this->company_id,
                'required_date'         => $this->required_date,
                'receiving_location_id' => $this->receiving_location_id,
                'notes'                 => $this->notes,
                'source_type'           => 'contract',
                'status'                => 'SUBMITTED',
                'created_by'            => Auth::id(),
                'updated_by'            => Auth::id(),
                'requester_id'          => Auth::id(),
            ]);

            foreach ($this->items as $itemData) {
                $cp = ContractProduct::find($itemData['contract_product_id']);

                $reqItem = $requisition->items()->create([
                    'product_service_id'  => $itemData['product_service_id'],
                    'description'         => $itemData['product_name'],
                    'quantity'            => $itemData['quantity'],
                    'unit_of_measure'     => $itemData['unit_of_measure'],
                    'contract_id'         => $itemData['contract_id'],
                    'contract_product_id' => $itemData['contract_product_id'],
                    'unit_price'          => $cp->unit_price,   // snapshot
                    'currency_code'       => $cp->currency_code,
                    'cost_center_id'      => $itemData['cost_center_id'] ?: null,
                    'budget_category_id'  => $itemData['budget_category_id'] ?: null,
                    'notes'               => $itemData['notes'] ?? null,
                    'created_by'          => Auth::id(),
                    'updated_by'          => Auth::id(),
                ]);
            }

            // Generar OCs agrupadas por proveedor
            app(\App\Services\ContractPurchaseOrderService::class)
                ->generateFromRequisition($requisition);

            $this->redirect(route('requisitions.show', $requisition), navigate: true);
        });
    }

    public function render()
    {
        return view('livewire.contract-requisition-form');
    }
}
```

- [ ] **Step 2: Crear vista Livewire** (`resources/views/livewire/contract-requisition-form.blade.php`)

```blade
<div>
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- Datos de la requisición --}}
    <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0">Requisición por contrato</h5></div>
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label">Empresa <span class="text-danger">*</span></label>
                <select wire:model.live="company_id" class="form-select">
                    <option value="">Seleccionar...</option>
                    @foreach($companies as $co)
                        <option value="{{ $co->id }}">{{ $co->name }}</option>
                    @endforeach
                </select>
                @error('company_id')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label class="form-label">Fecha requerida <span class="text-danger">*</span></label>
                <input type="date" wire:model="required_date" class="form-control">
                @error('required_date')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label class="form-label">Ubicación de recepción <span class="text-danger">*</span></label>
                <select wire:model="receiving_location_id" class="form-select">
                    <option value="">Seleccionar...</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                    @endforeach
                </select>
                @error('receiving_location_id')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    {{-- Agregar partida --}}
    <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0">Agregar partida</h6></div>
        <div class="card-body row g-3">
            <div class="col-md-5">
                <label class="form-label">Contrato</label>
                <select wire:model.live="newItem.contract_id" class="form-select" @if(!$company_id) disabled @endif>
                    <option value="">{{ $company_id ? 'Seleccionar contrato...' : 'Primero selecciona empresa' }}</option>
                    @foreach($eligibleContracts as $c)
                        <option value="{{ $c->id }}">{{ $c->folio }} — {{ $c->supplier->company_name }}</option>
                    @endforeach
                </select>
                @error('newItem.contract_id')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label class="form-label">Producto</label>
                <select wire:model.live="newItem.contract_product_id" class="form-select"
                    @if(!$newItem['contract_id']) disabled @endif>
                    <option value="">Seleccionar producto...</option>
                    @foreach($newItemContractProducts as $cp)
                        <option value="{{ $cp->id }}">{{ $cp->product->name ?? "Producto #{$cp->product_service_id}" }}</option>
                    @endforeach
                </select>
                @error('newItem.contract_product_id')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-2">
                <label class="form-label">Cantidad</label>
                <input type="number" wire:model="newItem.quantity" step="0.001" min="0.001" class="form-control">
                @error('newItem.quantity')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            @if($newItemSnapshotPrice)
            <div class="col-12">
                <span class="badge bg-light text-dark border">
                    Precio: <strong>${{ number_format($newItemSnapshotPrice, 4) }} {{ $newItemCurrency }}</strong>
                    (precio del contrato, se copiará al guardar)
                </span>
            </div>
            @endif

            <div class="col-12">
                <button type="button" wire:click="addItem" class="btn btn-outline-primary btn-sm">
                    <i class="ti ti-plus me-1"></i> Agregar partida
                </button>
            </div>
        </div>
    </div>

    {{-- Partidas agregadas --}}
    @if(count($items) > 0)
    <div class="card mb-3">
        <div class="card-header">Partidas ({{ count($items) }})</div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead>
                    <tr><th>Contrato</th><th>Proveedor</th><th>Producto</th><th>Cant.</th><th>Precio</th><th>Avisos</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach($items as $index => $item)
                    <tr>
                        <td>{{ $item['contract_folio'] }}</td>
                        <td>{{ $item['supplier_name'] }}</td>
                        <td>{{ $item['product_name'] }}</td>
                        <td>{{ $item['quantity'] }} {{ $item['unit_of_measure'] }}</td>
                        <td>${{ number_format($item['unit_price'], 4) }} {{ $item['currency_code'] }}</td>
                        <td>
                            @if($item['expiry_warning'])
                                <span class="badge bg-warning text-dark" title="Vence el {{ $item['expiry_warning'] }}">
                                    <i class="ti ti-clock-x me-1"></i> Vence {{ $item['expiry_warning'] }}
                                </span>
                            @endif
                        </td>
                        <td>
                            <button type="button" wire:click="removeItem({{ $index }})" class="btn btn-sm btn-outline-danger">
                                <i class="ti ti-trash"></i>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <button type="button" wire:click="submit" class="btn btn-primary"
        wire:loading.attr="disabled">
        <span wire:loading.remove>Generar requisición y OC</span>
        <span wire:loading>Procesando...</span>
    </button>
    @endif
</div>
```

- [ ] **Step 3: Crear blade wrapper**

```blade
{{-- resources/views/contracts/requisition-create.blade.php --}}
@extends('layouts.zircos')
@section('title', 'Nueva Requisición por Contrato')
@section('page.title', 'Nueva Requisición por Contrato')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('requisitions.index') }}">Requisiciones</a></li>
    <li class="breadcrumb-item active">Por contrato</li>
@endsection

@section('content')
<div class="container-fluid">
    @livewire('contract-requisition-form')
</div>
@endsection
```

- [ ] **Step 4: Agregar ruta**

En `routes/web.php`, dentro del grupo `auth`, agregar:

```php
Route::get('/contracts-requisitions/create',
    [\App\Http\Controllers\ContractController::class, 'requisitionCreate'])
    ->middleware('auth')
    ->name('contracts.requisition.create');
```

Agregar método al `ContractController`:

```php
public function requisitionCreate()
{
    return view('contracts.requisition-create');
}
```

- [ ] **Step 5: Commit**

```bash
git add app/Livewire/ContractRequisitionForm.php \
        resources/views/livewire/contract-requisition-form.blade.php \
        resources/views/contracts/requisition-create.blade.php
git commit -m "feat(livewire): ContractRequisitionForm — cascada empresa→contrato→producto"
```

---

### Task 14: ContractPurchaseOrderService — OC automática

**Files:**
- Create: `app/Services/ContractPurchaseOrderService.php`

- [ ] **Step 1: Explorar modelo PurchaseOrderItem**

Leer `app/Models/PurchaseOrderItem.php` y verificar los fillable antes de continuar, para confirmar la estructura exacta de campos a poblar.

```bash
# Solo leer, no modificar:
cat app/Models/PurchaseOrderItem.php
```

- [ ] **Step 2: Crear servicio**

```php
// app/Services/ContractPurchaseOrderService.php
<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use Illuminate\Support\Facades\Auth;

class ContractPurchaseOrderService
{
    /**
     * Genera una PurchaseOrder por cada proveedor distinto en la requisición.
     * Requiere que todos los items tengan contract_id y unit_price snapshot.
     */
    public function generateFromRequisition(Requisition $requisition): void
    {
        $itemsBySupplier = $requisition->items
            ->load(['contract.supplier', 'contract.products'])
            ->groupBy(fn(RequisitionItem $item) => $item->contract->supplier_id);

        foreach ($itemsBySupplier as $supplierId => $items) {
            $first = $items->first();

            $subtotal = $items->sum(fn($i) => $i->unit_price * $i->quantity);
            $iva      = round($subtotal * 0.16, 2);
            $total    = round($subtotal + $iva, 2);

            $po = PurchaseOrder::create([
                'folio'                  => $this->nextPoFolio(),
                'requisition_id'         => $requisition->id,
                'supplier_id'            => $supplierId,
                'quotation_summary_id'   => null,        // OC por contrato, sin cotización
                'source_type'            => 'contract',
                'receiving_location_id'  => $requisition->receiving_location_id,
                'subtotal'               => $subtotal,
                'iva_amount'             => $iva,
                'total'                  => $total,
                'currency'               => $first->currency_code ?? 'MXN',
                'status'                 => 'OPEN',
                'created_by'             => Auth::id(),
            ]);

            foreach ($items as $item) {
                $po->items()->create([
                    'requisition_item_id' => $item->id,
                    'description'         => $item->description,
                    'quantity'            => $item->quantity,
                    'unit_price'          => $item->unit_price,
                    'subtotal'            => round($item->unit_price * $item->quantity, 2),
                    'iva_amount'          => round($item->unit_price * $item->quantity * 0.16, 2),
                    'total'               => round($item->unit_price * $item->quantity * 1.16, 2),
                ]);
            }
        }
    }

    private function nextPoFolio(): string
    {
        $year   = date('Y');
        $prefix = "OC-{$year}-";
        $last   = PurchaseOrder::where('folio', 'like', $prefix . '%')
            ->orderBy('folio', 'desc')
            ->value('folio');

        $n = 0;
        if ($last && preg_match('/OC-\d{4}-(\d+)/', $last, $m)) {
            $n = (int) $m[1];
        }
        return sprintf('%s%04d', $prefix, $n + 1);
    }
}
```

> **Nota IVA:** El 16% es el valor predeterminado. Si la aplicación ya tiene un servicio de cálculo de IVA (ej. `TaxService`), reemplazar el cálculo manual con ese servicio.

- [ ] **Step 3: Registrar servicio en el contenedor** (opcional, Laravel lo resuelve automáticamente con auto-discovery, pero si el proyecto usa un ServiceProvider dedicado, registrarlo ahí).

- [ ] **Step 4: Commit**

```bash
git add app/Services/ContractPurchaseOrderService.php
git commit -m "feat(service): ContractPurchaseOrderService — OC automática por proveedor"
```

---

### Task 15: Tests de Requisición + OC

**Files:**
- Create: `tests/Feature/ContractRequisitionTest.php`

- [ ] **Step 1: Escribir tests**

```php
// tests/Feature/ContractRequisitionTest.php
<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractProduct;
use App\Models\PurchaseOrder;
use App\Models\Requisition;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ContractPurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractRequisitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_creates_one_po_per_supplier(): void
    {
        $user      = User::factory()->create();
        $supplier  = Supplier::factory()->create(['status' => 'activo']);
        $company   = Company::factory()->create(['is_active' => true]);
        $contract  = Contract::factory()->create([
            'supplier_id' => $supplier->id,
            'company_id'  => $company->id,
            'status'      => 'active',
        ]);
        $cp = ContractProduct::factory()->create([
            'contract_id' => $contract->id,
            'unit_price'  => 200.00,
            'currency_code' => 'MXN',
        ]);

        $requisition = Requisition::factory()->create([
            'source_type' => 'contract',
            'company_id'  => $company->id,
            'created_by'  => $user->id,
            'updated_by'  => $user->id,
        ]);

        $requisition->items()->create([
            'product_service_id'  => $cp->product_service_id,
            'description'         => 'Producto test',
            'quantity'            => 5,
            'unit_of_measure'     => 'PZA',
            'contract_id'         => $contract->id,
            'contract_product_id' => $cp->id,
            'unit_price'          => $cp->unit_price,
            'currency_code'       => 'MXN',
            'created_by'          => $user->id,
            'updated_by'          => $user->id,
        ]);

        $this->actingAs($user);
        app(ContractPurchaseOrderService::class)->generateFromRequisition($requisition);

        $this->assertDatabaseCount('purchase_orders', 1);
        $po = PurchaseOrder::first();
        $this->assertEquals('contract', $po->source_type);
        $this->assertEquals($supplier->id, $po->supplier_id);
        $this->assertNull($po->quotation_summary_id);
        $this->assertDatabaseCount('purchase_order_items', 1);
    }

    public function test_two_suppliers_create_two_pos(): void
    {
        $user      = User::factory()->create();
        $company   = Company::factory()->create(['is_active' => true]);
        $supplier1 = Supplier::factory()->create(['status' => 'activo']);
        $supplier2 = Supplier::factory()->create(['status' => 'activo']);

        $contract1 = Contract::factory()->create(['supplier_id' => $supplier1->id, 'company_id' => $company->id, 'status' => 'active']);
        $contract2 = Contract::factory()->create(['supplier_id' => $supplier2->id, 'company_id' => $company->id, 'status' => 'active']);
        $cp1 = ContractProduct::factory()->create(['contract_id' => $contract1->id, 'unit_price' => 100]);
        $cp2 = ContractProduct::factory()->create(['contract_id' => $contract2->id, 'unit_price' => 200]);

        $requisition = Requisition::factory()->create([
            'source_type' => 'contract', 'company_id' => $company->id,
            'created_by' => $user->id, 'updated_by' => $user->id,
        ]);

        foreach ([$cp1, $cp2] as $cp) {
            $requisition->items()->create([
                'product_service_id'  => $cp->product_service_id,
                'description'         => 'Test',
                'quantity'            => 1,
                'unit_of_measure'     => 'PZA',
                'contract_id'         => $cp->contract_id,
                'contract_product_id' => $cp->id,
                'unit_price'          => $cp->unit_price,
                'currency_code'       => 'MXN',
                'created_by'          => $user->id,
                'updated_by'          => $user->id,
            ]);
        }

        $this->actingAs($user);
        app(ContractPurchaseOrderService::class)->generateFromRequisition($requisition);

        $this->assertDatabaseCount('purchase_orders', 2);
    }
}
```

- [ ] **Step 2: Correr tests**

```bash
php artisan test --filter=ContractRequisitionTest
```

Expected: 2 tests passed.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/ContractRequisitionTest.php
git commit -m "feat(tests): ContractRequisitionTest — OC por proveedor"
```

---

## FASE 4 — Historial y Badge de precio (independiente de Fase 3)

---

### Task 16: Badge de precio desactualizado en detalle de requisición

**Files:**
- Modify: vista de detalle de `requisition_items` (buscar en `resources/views/requisitions/show.blade.php` la tabla de partidas)

- [ ] **Step 1: Localizar la tabla de partidas en show.blade.php**

```bash
grep -n "unit_price\|contract_product_id\|partidas" resources/views/requisitions/show.blade.php | head -20
```

- [ ] **Step 2: Agregar badge cuando el precio snapshot difiere del precio actual del contrato**

En la columna de precio unitario de cada `requisition_item`, agregar:

```blade
{{-- Dentro del loop de items en requisitions/show.blade.php --}}
@if($item->contract_product_id)
    @php $currentPrice = $item->contractProduct?->unit_price; @endphp
    @if($currentPrice && (float)$currentPrice !== (float)$item->unit_price)
        <span class="badge bg-warning text-dark ms-1"
            title="Precio actual en contrato: ${{ number_format($currentPrice, 4) }}">
            <i class="ti ti-refresh me-1"></i>Precio actualizado en contrato
        </span>
    @endif
@endif
```

- [ ] **Step 3: Agregar relación `contractProduct` en `RequisitionItem`**

En `app/Models/RequisitionItem.php`, agregar:

```php
public function contractProduct()
{
    return $this->belongsTo(\App\Models\ContractProduct::class);
}

public function contract()
{
    return $this->belongsTo(\App\Models\Contract::class);
}
```

- [ ] **Step 4: Eager load en `RequisitionController::show()`**

Buscar el `show()` de `RequisitionController` y agregar `contractProduct` al `with()`:

```php
// Agregar 'items.contractProduct' al eager load existente
$requisition->load(['items.contractProduct', /* ...otros loads... */]);
```

- [ ] **Step 5: Commit**

```bash
git add app/Models/RequisitionItem.php resources/views/requisitions/show.blade.php app/Http/Controllers/RequisitionController.php
git commit -m "feat(ui): badge precio desactualizado en detalle de requisición"
```

---

## Resumen de fases y dependencias

```
Fase 1 (Tasks 1–10)  →  requerida por Fase 3
Fase 2 (Tasks 11–12) →  independiente (puede hacerse en paralelo con Fase 3)
Fase 3 (Tasks 13–15) →  depende de Fase 1
Fase 4 (Task 16)     →  independiente (solo depende de que Task 1 migración esté lista)
```

**Orden recomendado:**
1. Tasks 1–9 (Fase 1 completa)
2. Tasks 10–12 (Fase 2) y Tasks 13–15 (Fase 3) en paralelo
3. Task 16 (Fase 4) cuando sea conveniente

**Total estimado:** ~9–11 días de desarrollo.
