<?php

namespace App\Models;

use App\Enum\ProductServiceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Catálogo de Productos y Servicios
 * según ESPECIFICACIONES_TECNICAS_SISTEMA_CONTROL_PRESUPUESTAL.md
 * Sección 5.1: Entidad PRODUCTO/SERVICIO
 */
class ProductService extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'products_services';

    protected $fillable = [
        // Identificación
        'code',
        'technical_description',
        'short_name',
        'product_type',

        // Clasificación
        'is_inventoriable',

        // Organización

        // Especificaciones técnicas
        'brand',
        'model',
        'unit_of_measure',
        'specifications',

        // Información comercial
        'estimated_price',
        'currency_code',
        'default_vendor_id',
        'minimum_quantity',
        'maximum_quantity',
        'lead_time_days',

        // Estructura contable
        'account_major',
        'account_sub',
        'account_subsub',

        // Estado y aprobación
        'status',
        'is_active',
        'rejection_reason',

        // Observaciones
        'observations',
        'internal_notes',

        // Auditoría
        'created_by',
        'updated_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'estimated_price' => 'decimal:2',
        'minimum_quantity' => 'decimal:3',
        'maximum_quantity' => 'decimal:3',
        'lead_time_days' => 'integer',
        'is_active' => 'boolean',
        'is_inventoriable' => 'boolean',
        'specifications' => 'array', // JSON a array automático
        'approved_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ==========================================
    // RELACIONES
    // ==========================================

    /**
     * Proveedor sugerido del catálogo.
     */
    public function defaultVendor(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'default_vendor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function expenseCategories(): BelongsToMany
    {
        return $this->belongsToMany(ExpenseCategory::class);
    }

    public function budgetCedulas(): BelongsToMany
    {
        return $this->belongsToMany(BudgetCedula::class);
    }

    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class, 'account_product_service');
    }

    public function subaccounts(): BelongsToMany
    {
        return $this->belongsToMany(Subaccount::class, 'product_service_subaccount');
    }

    public function departmentSubaccountMappings(): HasMany
    {
        return $this->hasMany(ProductDepartmentSubaccount::class);
    }

    // ==========================================
    // LÓGICA DE NEGOCIO
    // ==========================================

    /**
     * Genera el siguiente código único para producto/servicio.
     * Formato: PROD-NNNNNN (6 dígitos incrementales)
     */
    public static function nextCode(): string
    {
        $prefix = 'PROD-';
        $last = static::withTrashed()
            ->where('code', 'like', $prefix.'%')
            ->orderBy('code', 'desc')
            ->value('code');

        $n = 0;
        if ($last && preg_match('/PROD-(\d+)/', $last, $m)) {
            $n = (int) $m[1];
        }
        $n++;

        return sprintf('%s%06d', $prefix, $n);
    }

    /**
     * Etiqueta legible del estado.
     */
    public function statusLabel(): string
    {
        $opts = ProductServiceStatus::options();

        return $opts[$this->status] ?? $this->status;
    }

    /**
     * Obtiene la clase CSS para el color del estado.
     */
    public function statusColor(): string
    {
        $status = ProductServiceStatus::tryFrom($this->status);

        return $status ? ProductServiceStatus::badgeClass($status) : 'secondary';
    }

    /**
     * Verifica si el producto/servicio tiene estructura contable completa.
     */
    public function hasCompleteAccountingStructure(): bool
    {
        return ! empty($this->account_major)
            && ! empty($this->account_sub)
            && ! empty($this->account_subsub);
    }

    /**
     * Verifica si está disponible para ser usado en requisiciones.
     */
    public function isAvailableForRequisitions(): bool
    {
        return $this->is_active === true
            && $this->status === ProductServiceStatus::ACTIVE->value
            && $this->hasCompleteAccountingStructure();
    }

    /**
     * Obtiene el nombre para mostrar (corto si existe, sino técnico).
     */
    public function getDisplayName(): string
    {
        return $this->short_name ?? $this->technical_description;
    }

    /**
     * Descripción segura para requisiciones y documentos operativos.
     */
    public function getRequisitionDescription(): string
    {
        foreach ([
            $this->technical_description,
            $this->getAttribute('description'),
            $this->short_name,
            $this->code,
        ] as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return 'Producto sin descripción';
    }

    /**
     * Verifica si tiene proveedor sugerido.
     */
    public function hasSuggestedVendor(): bool
    {
        return $this->default_vendor_id !== null;
    }

    /**
     * Valida si una cantidad está dentro de los límites permitidos.
     */
    public function isValidQuantity(float $quantity): bool
    {
        if ($this->minimum_quantity && $quantity < $this->minimum_quantity) {
            return false;
        }

        if ($this->maximum_quantity && $quantity > $this->maximum_quantity) {
            return false;
        }

        return true;
    }

    /**
     * Calcula la fecha estimada de entrega desde hoy.
     */
    public function getEstimatedDeliveryDate(): ?\Carbon\Carbon
    {
        if (! $this->lead_time_days) {
            return null;
        }

        return now()->addDays($this->lead_time_days);
    }

    // ==========================================
    // SCOPES
    // ==========================================

    /**
     * Solo productos/servicios activos.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('status', ProductServiceStatus::ACTIVE->value);
    }

    /**
     * Solo pendientes de aprobación.
     */
    public function scopePendingApproval($query)
    {
        return $query->where('status', ProductServiceStatus::PENDING->value);
    }

    /**
     * Solo rechazados.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', ProductServiceStatus::REJECTED->value);
    }

    /**
     * Solo inactivos.
     */
    public function scopeInactive($query)
    {
        return $query->where('status', ProductServiceStatus::INACTIVE->value);
    }

    /**
     * Por compañía.
     */
    /**
     * Por centro de costo.
     */
    /**
     * Por tipo de producto (PRODUCTO o SERVICIO).
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('product_type', $type);
    }

    /**
     * Solo productos (no servicios).
     */
    public function scopeProducts($query)
    {
        return $query->where('product_type', 'PRODUCTO');
    }

    /**
     * Solo servicios (no productos).
     */
    public function scopeServices($query)
    {
        return $query->where('product_type', 'SERVICIO');
    }

    /**
     * Por marca.
     */
    public function scopeByBrand($query, string $brand)
    {
        return $query->where('brand', $brand);
    }

    /**
     * Disponibles para requisiciones (activos + estructura contable).
     */
    public function scopeForRequisitions($query)
    {
        return $query->where('is_active', true)
            ->where('status', ProductServiceStatus::ACTIVE->value)
            ->whereNotNull('account_major')
            ->whereNotNull('account_sub')
            ->whereNotNull('account_subsub');
    }

    public function scopeWithAllowedSubaccounts($query, iterable $subaccountIds)
    {
        $ids = collect($subaccountIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('subaccounts', fn ($subquery) => $subquery->whereIn('subaccounts.id', $ids));
    }
}
