<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

/**
 * BudgetMonthlyDistribution
 *
 * Distribución mensual por categoría de gasto.
 * Desglosa el presupuesto anual en meses y categorías.
 *
 * Estados del presupuesto:
 * - NORMAL: >70% disponible
 * - ALERTA: 30-70% disponible
 * - CRÍTICO: <30% disponible
 * - AGOTADO: 0% disponible
 */
class BudgetMonthlyDistribution extends Model
{
    use SoftDeletes;

    protected $table = 'budget_monthly_distributions';

    protected $fillable = [
        'annual_budget_id',
        'budget_cedula_id',
        'expense_category_id',
        'month',
        'assigned_amount',
        'consumed_amount',
        'committed_amount',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'month' => 'integer',
        'assigned_amount' => 'decimal:2',
        'consumed_amount' => 'decimal:2',
        'committed_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ===== RELACIONES =====

    /**
     * Presupuesto anual padre
     */
    public function annualBudget()
    {
        return $this->belongsTo(AnnualBudget::class);
    }

    public function budgetCedula()
    {
        return $this->belongsTo(BudgetCedula::class, 'budget_cedula_id');
    }

    /**
     * Categoría de gasto
     */
    public function expenseCategory()
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    public function getCedulaNameAttribute(): ?string
    {
        return $this->budgetCedula?->name;
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
     * Para mes específico
     */
    public function scopeForMonth($query, $month)
    {
        return $query->where('month', $month);
    }

    /**
     * Para categoría específica
     */
    public function scopeForCategory($query, $categoryId)
    {
        return $query->where('expense_category_id', $categoryId);
    }

    /**
     * Para presupuesto anual específico
     */
    public function scopeForBudget($query, $budgetId)
    {
        return $query->where('annual_budget_id', $budgetId);
    }

    /**
     * No eliminados
     */
    public function scopeNotDeleted($query)
    {
        return $query->whereNull('deleted_at');
    }

    /**
     * Con presupuesto disponible
     */
    public function scopeWithAvailable($query)
    {
        return $query->whereRaw('assigned_amount - consumed_amount - committed_amount > 0');
    }

    /**
     * Agotados (sin presupuesto disponible)
     */
    public function scopeExhausted($query)
    {
        return $query->whereRaw('assigned_amount - consumed_amount - committed_amount <= 0');
    }

    // ===== MÉTODOS: CÁLCULOS =====

    /**
     * Obtener presupuesto disponible
     */
    public function getAvailableAmount(): float
    {
        return max(0, (float) $this->assigned_amount
            - (float) $this->consumed_amount
            - (float) $this->committed_amount);
    }

    /**
     * Obtener porcentaje de utilización (0-100)
     */
    public function getUsagePercentage(): float
    {
        if ((float) $this->assigned_amount == 0) {
            return 0;
        }

        return (((float) $this->consumed_amount + (float) $this->committed_amount)
            / (float) $this->assigned_amount) * 100;
    }

    /**
     * Obtener porcentaje disponible (0-100)
     */
    public function getAvailablePercentage(): float
    {
        return 100 - $this->getUsagePercentage();
    }

    // ===== MÉTODOS: VALIDACIÓN =====

    /**
     * Verificar si hay presupuesto disponible para cierto monto
     */
    public function hasAvailableBudget($amount): bool
    {
        return (float) $amount <= $this->getAvailableAmount();
    }

    /**
     * Verificar si puede comprometer un monto
     */
    public function canCommit($amount): bool
    {
        return $this->hasAvailableBudget($amount);
    }

    /**
     * Verificar si puede consumir un monto
     */
    public function canConsume($amount): bool
    {
        return $this->hasAvailableBudget($amount);
    }

    // ===== MÉTODOS: ACTUALIZAR PRESUPUESTO =====

    /**
     * Comprometer presupuesto (cuando se autoriza requisición)
     *
     * Retorna:
     * - true: Si se comprometió exitosamente
     * - false: Si no hay presupuesto disponible
     */
    public function commitAmount(float $amount): bool
    {
        if (! $this->canCommit($amount)) {
            return false;
        }

        $this->committed_amount = (float) $this->committed_amount + $amount;
        $this->updated_by = Auth::guard('web')->id();
        $this->save();

        return true;
    }

    /**
     * Liberar compromiso (cuando se cancela requisición)
     *
     * Retorna:
     * - true: Si se liberó exitosamente
     * - false: Si hay error
     */
    public function releaseCommitment(float $amount): bool
    {
        $newCommitted = (float) $this->committed_amount - $amount;

        if ($newCommitted < 0) {
            return false;
        }

        $this->committed_amount = $newCommitted;
        $this->updated_by = Auth::guard('web')->id();
        $this->save();

        return true;
    }

    /**
     * Consumir presupuesto (cuando se ejecuta compra)
     *
     * Retorna:
     * - true: Si se consumió exitosamente
     * - false: Si no hay presupuesto disponible
     */
    public function consumeAmount(float $amount): bool
    {
        if (! $this->canConsume($amount)) {
            return false;
        }

        $this->consumed_amount = (float) $this->consumed_amount + $amount;
        $this->updated_by = Auth::guard('web')->id();
        $this->save();

        return true;
    }

    /**
     * Transferir de compromiso a consumo
     * (cuando se ejecuta una requisición comprometida)
     *
     * Retorna:
     * - true: Si se transfirió exitosamente
     * - false: Si hay error
     */
    public function commitToConsume(float $amount): bool
    {
        $newCommitted = (float) $this->committed_amount - $amount;
        $newConsumed = (float) $this->consumed_amount + $amount;

        if ($newCommitted < 0) {
            return false;
        }

        $this->committed_amount = $newCommitted;
        $this->consumed_amount = $newConsumed;
        $this->updated_by = Auth::guard('web')->id();
        $this->save();

        return true;
    }

    // ===== MÉTODOS: INFORMACIÓN =====

    /**
     * Obtener etiqueta del mes
     */
    public function getMonthLabelAttribute(): string
    {
        $months = [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre',
        ];

        return $months[$this->month] ?? "Mes {$this->month}";
    }

    /**
     * Obtener estado del presupuesto (NORMAL, ALERTA, CRÍTICO, AGOTADO)
     */
    public function getStatusAttribute(): string
    {
        $availablePercentage = $this->getAvailablePercentage();

        if ($availablePercentage > 70) {
            return 'NORMAL';
        } elseif ($availablePercentage > 30) {
            return 'ALERTA';
        } elseif ($availablePercentage > 0) {
            return 'CRÍTICO';
        } else {
            return 'AGOTADO';
        }
    }

    /**
     * Obtener etiqueta de estado con color
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'NORMAL' => '<span class="badge bg-success">Normal</span>',
            'ALERTA' => '<span class="badge bg-warning text-dark">Alerta</span>',
            'CRÍTICO' => '<span class="badge bg-danger">Crítico</span>',
            'AGOTADO' => '<span class="badge bg-dark">Agotado</span>',
            default => '<span class="badge bg-secondary">Desconocido</span>',
        };
    }

    /**
     * Obtener color para gráficos
     */
    public function getColorAttribute(): string
    {
        return match ($this->status) {
            'NORMAL' => '#28a745',   // verde
            'ALERTA' => '#ffc107',   // amarillo
            'CRÍTICO' => '#dc3545',  // rojo
            'AGOTADO' => '#343a40',  // gris
            default => '#6c757d',    // gris claro
        };
    }

    // ===== VALIDACIONES =====

    /**
     * Boot del modelo - Validaciones automáticas
     */
    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Validar que month esté entre 1-12
            if ($model->month < 1 || $model->month > 12) {
                throw new \InvalidArgumentException('El mes debe estar entre 1 y 12.');
            }

            // Validar que assigned_amount sea > 0
            if ((float) $model->assigned_amount <= 0) {
                throw new \InvalidArgumentException('El monto asignado debe ser mayor a 0.');
            }

            // Asegurar que consumed y committed empiezan en 0
            $model->consumed_amount = $model->consumed_amount ?? 0;
            $model->committed_amount = $model->committed_amount ?? 0;

            // Asegurar auditoría
            if (! $model->created_by) {
                $model->created_by = Auth::guard('web')->id();
            }
        });

        static::updating(function ($model) {
            // No permitir que asignado sea menor a consumido + comprometido
            $total = (float) $model->consumed_amount + (float) $model->committed_amount;
            if ((float) $model->assigned_amount < $total) {
                throw new \InvalidArgumentException(
                    'No se puede reducir el asignado por debajo de consumido + comprometido.'
                );
            }

            // Asegurar auditoría
            if (! $model->updated_by) {
                $model->updated_by = Auth::guard('web')->id();
            }
        });

        static::deleting(function ($model) {
            // Registrar quién lo elimina (soft delete)
            $model->deleted_by = Auth::guard('web')->id();
            $model->save();
        });
    }
}
