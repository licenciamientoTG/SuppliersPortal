<?php

namespace App\Models;

use App\Enum\PurchaseType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * CostCenter
 *
 * Centro de costo (estación, área, proyecto, etc.).
 * Puede ser ANNUAL (con presupuestos anuales) o FREE_CONSUMPTION (monto global).
 */
class CostCenter extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'cost_centers';

    protected $fillable = [
        'code',
        'name',
        'description',
        'purchase_type',
        'category_id',
        'company_id',
        'responsible_user_id',
        'budget_type',
        'global_amount',
        'free_consumption_justification',
        'authorized_by',
        'authorized_at',
        'validity_date',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'purchase_type' => PurchaseType::class,
        'budget_type' => 'string',
        'status' => 'string',
        'global_amount' => 'decimal:2',
        'authorized_at' => 'datetime',
        'validity_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ===== RELACIONES =====

    /**
     * Categoría del centro (ADMINISTRACION, ESTACIONES, etc.)
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Empresa a la que pertenece
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Responsable del centro (Jefe de Área)
     */
    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /**
     * Usuario que creó el registro
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Usuario que modificó el registro
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Usuario que eliminó el registro (soft delete)
     */
    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * Director General que autorizó el centro de consumo libre
     */
    public function authorizedBy()
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    // ===== RELACIONES CON PRESUPUESTOS =====

    /**
     * Presupuestos anuales (solo para ANNUAL)
     */
    public function annualBudgets()
    {
        return $this->hasMany(AnnualBudget::class);
    }

    /**
     * Usuarios asignados a este centro de costo.
     * Relación many-to-many con tabla pivote cost_center_user
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'cost_center_user')->withPivot('is_default', 'is_active', 'created_by', 'updated_by')
            ->withTimestamps()
            ->withTrashed();
    }

    /**
     * Usuarios activos de este centro de costo
     */
    public function activeUsers()
    {
        return $this->users()->wherePivot('is_active', true);
    }

    /**
     * Presupuesto anual actual (año actual o especificado)
     *
     * Uso:
     * $costCenter->currentAnnualBudget()
     * $costCenter->currentAnnualBudget(2026)
     */
    public function currentAnnualBudget($year = null)
    {
        $year = $year ?? now()->year;

        return $this->annualBudgets()
            ->where('fiscal_year', $year)
            ->where('status', 'APROBADO')
            ->first();
    }

    /**
     * Todos los presupuestos anuales aprobados
     */
    public function approvedBudgets()
    {
        return $this->annualBudgets()
            ->where('status', 'APROBADO');
    }

    // ===== SCOPES =====

    /**
     * Solo centros activos
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVO');
    }

    /**
     * Solo centros inactivos
     */
    public function scopeInactive($query)
    {
        return $query->where('status', 'INACTIVO');
    }

    /**
     * Solo centros de presupuesto anual
     */
    public function scopeAnnual($query)
    {
        return $query->where('budget_type', 'ANNUAL');
    }

    /**
     * Solo centros de consumo libre
     */
    public function scopeFreeConsumption($query)
    {
        return $query->where('budget_type', 'FREE_CONSUMPTION');
    }

    /**
     * Solo centros no eliminados
     */
    public function scopeNotDeleted($query)
    {
        return $query->whereNull('deleted_at');
    }

    /**
     * Por empresa
     */
    public function scopeByCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Por categoría
     */
    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Por responsable
     */
    public function scopeByResponsible($query, $userId)
    {
        return $query->where('responsible_user_id', $userId);
    }

    // ===== MÉTODOS: VALIDACIÓN PRESUPUESTAL =====

    /**
     * CENTROS ANUALES: Obtener presupuesto disponible para un mes y categoría
     *
     * Retorna:
     * disponible = asignado - consumido - comprometido
     *
     * Retorna NULL si:
     * - No es centro ANNUAL
     * - No hay presupuesto aprobado para este año
     * - No existe distribución para mes/categoría
     */
    public function getAvailableBudgetForMonthAndCategory($month, $categoryId, $year = null)
    {
        // Solo aplica para centros ANNUAL
        if ($this->budget_type !== 'ANNUAL') {
            return null;
        }

        $year = $year ?? now()->year;

        // Obtener presupuesto anual aprobado
        $annualBudget = $this->annualBudgets()
            ->where('fiscal_year', $year)
            ->where('status', 'APROBADO')
            ->first();

        if (!$annualBudget) {
            return null;
        }

        // Obtener distribución mensual
        $distributions = $annualBudget->monthlyDistributions()
            ->where('month', $month)
            ->where('expense_category_id', $categoryId)
            ->get();

        if ($distributions->isEmpty()) {
            return null;
        }

        return (float) $distributions->sum('assigned_amount')
            - (float) $distributions->sum('consumed_amount')
            - (float) $distributions->sum('committed_amount');
    }

    /**
     * CENTROS FREE_CONSUMPTION: Obtener presupuesto disponible global
     *
     * Retorna:
     * disponible = global_amount - consumido - comprometido
     *
     * Retorna NULL si no es centro FREE_CONSUMPTION
     */
    public function getAvailableFreeConsumption()
    {
        if ($this->budget_type !== 'FREE_CONSUMPTION') {
            return null;
        }

        // Obtener suma de consumido + comprometido
        $used = BudgetMovement::where('cost_center_id', $this->id)
            ->whereIn('type', ['CONSUMED', 'COMMITTED'])
            ->sum('amount');

        return max(0, $this->global_amount - $used);
    }

    /**
     * CENTROS ANUALES: Validar si hay presupuesto disponible
     * para un monto específico en mes/categoría
     *
     * Retorna: true/false
     */
    public function hasAvailableBudgetForMonthAndCategory($month, $categoryId, $amount, $year = null)
    {
        $available = $this->getAvailableBudgetForMonthAndCategory($month, $categoryId, $year);

        if ($available === null) {
            return false;
        }

        return $amount <= $available;
    }

    /**
     * CENTROS FREE_CONSUMPTION: Validar si hay presupuesto disponible
     * para un monto específico
     *
     * Retorna: true/false
     */
    public function canCommitFreeConsumption($amount): bool
    {
        $available = $this->getAvailableFreeConsumption();

        if ($available === null) {
            return false;
        }

        return $amount <= $available;
    }

    /**
     * UNIVERSAL: Validar presupuesto según tipo de centro
     *
     * Para ANNUAL: requiere month, categoryId
     * Para FREE_CONSUMPTION: no requiere parámetros adicionales
     */
    public function canCommitBudget($amount, $month = null, $categoryId = null, $year = null): bool
    {
        if ($this->budget_type === 'ANNUAL') {
            if ($month === null || $categoryId === null) {
                throw new \InvalidArgumentException('month y categoryId requeridos para centros ANNUAL');
            }
            return $this->hasAvailableBudgetForMonthAndCategory($month, $categoryId, $amount, $year);
        } elseif ($this->budget_type === 'FREE_CONSUMPTION') {
            return $this->canCommitFreeConsumption($amount);
        }

        return false;
    }

    // ===== MÉTODOS: INFORMACIÓN =====

    /**
     * Obtener estado del centro en formato legible
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'ACTIVO' => 'Activo',
            'INACTIVO' => 'Inactivo',
            default => $this->status,
        };
    }

    /**
     * Obtener tipo de presupuesto en formato legible
     */
    public function getBudgetTypeLabelAttribute(): string
    {
        return match ($this->budget_type) {
            'ANNUAL' => 'Presupuesto Anual',
            'FREE_CONSUMPTION' => 'Consumo Libre',
            default => $this->budget_type,
        };
    }

    /**
     * Verificar si está activo
     */
    public function isActive(): bool
    {
        return $this->status === 'ACTIVO';
    }

    /**
     * Verificar si es ANNUAL
     */
    public function isAnnual(): bool
    {
        return $this->budget_type === 'ANNUAL';
    }

    /**
     * Verificar si es FREE_CONSUMPTION
     */
    public function isFreeConsumption(): bool
    {
        return $this->budget_type === 'FREE_CONSUMPTION';
    }

    /**
     * Verificar si es ANNUAL y está autorizado
     */
    public function hasApprovedBudgetForCurrentYear(): bool
    {
        if (!$this->isAnnual()) {
            return false;
        }

        return $this->currentAnnualBudget() !== null;
    }

    /**
     * Obtener información resumida del presupuesto actual (ANNUAL)
     */
    public function getBudgetSummary($year = null): ?array
    {
        if (!$this->isAnnual()) {
            return null;
        }

        $year = $year ?? now()->year;
        $budget = $this->currentAnnualBudget($year);

        if (!$budget) {
            return null;
        }

        // Calcular totales por todas las categorías y meses
        $totalConsumed = $budget->monthlyDistributions()
            ->sum('consumed_amount');

        $totalCommitted = $budget->monthlyDistributions()
            ->sum('committed_amount');

        $totalAssigned = $budget->total_annual_amount;
        $totalAvailable = $totalAssigned - $totalConsumed - $totalCommitted;

        return [
            'fiscal_year' => $year,
            'total_assigned' => $totalAssigned,
            'total_consumed' => $totalConsumed,
            'total_committed' => $totalCommitted,
            'total_available' => $totalAvailable,
            'usage_percentage' => ($totalAssigned > 0)
                ? (($totalConsumed + $totalCommitted) / $totalAssigned) * 100
                : 0,
            'status' => $budget->status,
        ];
    }

    /**
     * Obtener información resumida del presupuesto actual (FREE_CONSUMPTION)
     */
    public function getFreeConsumptionSummary(): ?array
    {
        if (!$this->isFreeConsumption()) {
            return null;
        }

        $used = BudgetMovement::where('cost_center_id', $this->id)
            ->whereIn('type', ['CONSUMED', 'COMMITTED'])
            ->sum('amount');

        $available = $this->global_amount - $used;

        return [
            'global_amount' => $this->global_amount,
            'total_used' => $used,
            'total_available' => $available,
            'usage_percentage' => ($this->global_amount > 0)
                ? ($used / $this->global_amount) * 100
                : 0,
            'authorized_by' => $this->authorizedBy?->name,
            'authorized_at' => $this->authorized_at,
        ];
    }

    /**
     * Obtener resumen general (ANNUAL o FREE_CONSUMPTION)
     */
    public function getBudgetStatus($year = null): ?array
    {
        if ($this->isAnnual()) {
            return $this->getBudgetSummary($year);
        } elseif ($this->isFreeConsumption()) {
            return $this->getFreeConsumptionSummary();
        }

        return null;
    }

    // ===== MÉTODOS: VALIDACIONES DE NEGOCIO =====

    /**
     * Verificar si puede ser desactivado
     * (No debe tener presupuestos aprobados activos)
     */
    public function canBeDeactivated(): bool
    {
        if (!$this->isActive()) {
            return true; // Ya está inactivo
        }

        // Verificar que no haya presupuestos aprobados del año actual
        return !$this->approvedBudgets()
            ->where('fiscal_year', now()->year)
            ->exists();
    }

    /**
     * Obtener mensaje si no puede desactivarse
     */
    public function getDeactivationErrorMessage(): ?string
    {
        if ($this->canBeDeactivated()) {
            return null;
        }

        return 'No puedes desactivar un centro con presupuestos aprobados activos.';
    }


    /**
     * Scope que verifica si un centro tiene presupuesto anual o de consumo libre
     */
    public function scopeHasAnnualBudget($query)
    {
        return $query->whereIn('budget_type', ['ANNUAL', 'FREE_CONSUMPTION']);
    }

    public function hasAnnualBudget(int $fiscalYear): bool
    {
        if ($this->isFreeConsumption()) {
            return true;
        }

        return $this->annualBudgets()
            ->where('fiscal_year', $fiscalYear)
            ->whereIn('status', ['APROBADO', 'PLANIFICACION'])
            ->exists();
    }


    // ===== EVENTOS =====

    /**
     * Boot del modelo - Validaciones automáticas
     */
    public static function boot()
    {
        parent::boot();

        static::updating(function ($model) {
            // Si está cambiando a INACTIVO, verificar que pueda desactivarse
            if ($model->isDirty('status') && $model->status === 'INACTIVO') {
                if (!$model->canBeDeactivated()) {
                    throw new \Exception($model->getDeactivationErrorMessage());
                }
            }
        });
    }
}
