<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

/**
 * ExpenseCategory
 *
 * Categorías estándar de gasto:
 * - MAT: Materiales (Insumos y materias primas)
 * - SER: Servicios (Servicios profesionales y técnicos)
 * - VIA: Viáticos (Gastos de viaje y representación)
 * - MAN: Mantenimiento (Mantenimiento de equipos e instalaciones)
 * - CAP: Capacitación (Programas de desarrollo de personal)
 * - TEC: Tecnología (Software, hardware y servicios TI)
 * - OTR: Otros Gastos (Gastos diversos no clasificados)
 */
class ExpenseCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'expense_categories';

    protected $fillable = [
        'code',
        'name',
        'description',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'status' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ===== RELACIONES =====

    /**
     * Distribuciones mensuales que usan esta categoría
     */
    public function monthlyDistributions()
    {
        return $this->hasMany(BudgetMonthlyDistribution::class);
    }

    public function cedulas()
    {
        return $this->hasMany(BudgetCedula::class, 'expense_category_id');
    }

    /**
     * Partidas de requisición que usan esta categoría
     */
    public function requisitionItems()
    {
        return $this->hasMany(RequisitionItem::class, 'expense_category_id');
    }

    /**
     * Detalles de movimiento presupuestal que usan esta categoría
     */
    public function budgetMovementDetails()
    {
        return $this->hasMany(BudgetMovementDetail::class, 'expense_category_id');
    }

    /**
     * Partidas de orden de compra directa que usan esta categoría
     */
    public function directPurchaseOrderItems()
    {
        return $this->hasMany(DirectPurchaseOrderItem::class, 'expense_category_id');
    }

    /**
     * Compromisos presupuestales que usan esta categoría
     */
    public function budgetCommitments()
    {
        return $this->hasMany(BudgetCommitment::class, 'expense_category_id');
    }

    /**
     * Productos/servicios del catálogo clasificados con esta categoría
     */
    public function productServices()
    {
        return $this->belongsToMany(ProductService::class);
    }

    /**
     * Distribuciones mensuales presupuestales que usan esta categoría
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function budgetMonthlyDistributions()
    {
        return $this->hasMany(BudgetMonthlyDistribution::class, 'expense_category_id');
    }

    /**
     * Auditoría: creador
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Auditoría: modificador
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Auditoría: eliminador (soft delete)
     */
    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    // ===== SCOPES =====

    /**
     * Solo categorías activas
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVO');
    }

    /**
     * Solo categorías inactivas
     */
    public function scopeInactive($query)
    {
        return $query->where('status', 'INACTIVO');
    }

    /**
     * No eliminadas (soft delete)
     */
    public function scopeNotDeleted($query)
    {
        return $query->whereNull('deleted_at');
    }

    /**
     * Por código
     */
    public function scopeByCode($query, $code)
    {
        return $query->where('code', strtoupper($code));
    }

    /**
     * Con distribuciones mensuales
     */
    public function scopeWithDistributions($query)
    {
        return $query->whereHas('monthlyDistributions');
    }

    // ===== MÉTODOS: INFORMACIÓN =====

    /**
     * Obtener etiqueta de estado
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'ACTIVO' => 'Activa',
            'INACTIVO' => 'Inactiva',
            default => $this->status,
        };
    }

    /**
     * Verificar si esta categoría corresponde a Servicios (código SER).
     * Usado para determinar si se aplica la validación REPSE en recepciones.
     */
    public function isService(): bool
    {
        return $this->code === 'SER';
    }

    /**
     * Verificar si está activa
     */
    public function isActive(): bool
    {
        return $this->status === 'ACTIVO';
    }

    /**
     * Verificar si está inactiva
     */
    public function isInactive(): bool
    {
        return $this->status === 'INACTIVO';
    }

    /**
     * Obtener descripción o fallback
     */
    public function getDescriptionOrDefault(): string
    {
        return $this->description ?? "Categoría: {$this->name}";
    }

    // ===== MÉTODOS: VALIDACIONES =====

    /**
     * Verificar si tiene cédulas asociadas (no eliminadas)
     */
    public function hasCedulas(): bool
    {
        return $this->cedulas()->exists();
    }

    /**
     * Verificar si la categoría está en uso real: distribuciones con monto
     * asignado, o cualquier movimiento transaccional que la referencie
     * (requisiciones, movimientos presupuestales, órdenes de compra directa,
     * compromisos presupuestales).
     */
    public function isInUse(): bool
    {
        return $this->monthlyDistributions()->where('assigned_amount', '>', 0)->exists()
            || $this->requisitionItems()->exists()
            || $this->budgetMovementDetails()->exists()
            || $this->directPurchaseOrderItems()->exists()
            || $this->budgetCommitments()->exists()
            || $this->productServices()->exists();
    }

    /**
     * Verificar si puede ser desactivada
     * (No debe tener distribuciones activas con presupuesto ni movimientos asociados)
     */
    public function canBeDeactivated(): bool
    {
        // Usa el valor original (previo a la mutación en memoria) porque este
        // método se invoca desde el hook `updating` cuando `status` ya fue
        // asignado a INACTIVO en el modelo, antes de persistirse.
        if (($this->getOriginal('status') ?? $this->status) === 'INACTIVO') {
            return true; // Ya estaba inactiva
        }

        return !$this->isInUse();
    }

    /**
     * Obtener mensaje si no puede desactivarse
     */
    public function getDeactivationErrorMessage(): ?string
    {
        if ($this->canBeDeactivated()) {
            return null;
        }

        return 'No se puede desactivar una categoría que tiene distribuciones presupuestales activas.';
    }

    /**
     * Obtener total de presupuesto asignado en esta categoría
     */
    public function getTotalAssignedBudget(): float
    {
        return (float) $this->monthlyDistributions()
            ->sum('assigned_amount');
    }

    /**
     * Obtener total de presupuesto consumido en esta categoría
     */
    public function getTotalConsumedBudget(): float
    {
        return (float) $this->monthlyDistributions()
            ->sum('consumed_amount');
    }

    /**
     * Obtener total de presupuesto comprometido en esta categoría
     */
    public function getTotalCommittedBudget(): float
    {
        return (float) $this->monthlyDistributions()
            ->sum('committed_amount');
    }

    /**
     * Obtener total de presupuesto disponible en esta categoría
     */
    public function getTotalAvailableBudget(): float
    {
        $assigned = $this->getTotalAssignedBudget();
        $consumed = $this->getTotalConsumedBudget();
        $committed = $this->getTotalCommittedBudget();

        return max(0, $assigned - $consumed - $committed);
    }

    /**
     * Obtener resumen de presupuesto
     */
    public function getBudgetSummary(): array
    {
        $assigned = $this->getTotalAssignedBudget();
        $consumed = $this->getTotalConsumedBudget();
        $committed = $this->getTotalCommittedBudget();
        $available = $this->getTotalAvailableBudget();

        return [
            'total_assigned' => $assigned,
            'total_consumed' => $consumed,
            'total_committed' => $committed,
            'total_available' => $available,
            'usage_percentage' => ($assigned > 0)
                ? (($consumed + $committed) / $assigned) * 100
                : 0,
        ];
    }

    // ===== VALIDACIONES =====

    /**
     * Boot del modelo - Validaciones automáticas
     */
    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Código debe ser único y en mayúsculas
            $model->code = strtoupper($model->code);

            // Asegurar status válido
            if (!in_array($model->status, ['ACTIVO', 'INACTIVO'])) {
                $model->status = 'ACTIVO';
            }

            // Asegurar auditoría
            if (!$model->created_by) {
                $model->created_by = Auth::id();
            }
        });

        static::updating(function ($model) {
            // Código debe ser único y en mayúsculas
            $model->code = strtoupper($model->code);

            // Validar desactivación
            if ($model->isDirty('status') && $model->status === 'INACTIVO') {
                if (!$model->canBeDeactivated()) {
                    throw new \Exception($model->getDeactivationErrorMessage());
                }
            }

            // Asegurar auditoría
            if (!$model->updated_by) {
                $model->updated_by = Auth::id();
            }
        });

        static::deleting(function ($model) {
            // Registrar quién lo elimina (soft delete)
            $model->deleted_by = Auth::id();
            $model->save();
        });
    }
}
