<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetMovementDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'budget_movement_id',
        'detail_type',
        'cost_center_id',
        'month',
        'expense_category_id',
        'budget_cedula_id',
        'amount',
    ];

    protected $casts = [
        'month' => 'integer',
        'amount' => 'decimal:2',
    ];

    /**
     * Constantes para tipos de detalle
     */
    const TYPE_ORIGIN = 'ORIGEN';

    const TYPE_DESTINATION = 'DESTINO';

    const TYPE_ADJUSTMENT = 'AJUSTE';

    /**
     * Movimiento presupuestal al que pertenece
     */
    public function budgetMovement(): BelongsTo
    {
        return $this->belongsTo(BudgetMovement::class);
    }

    /**
     * Centro de costo afectado
     */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    /**
     * Categoría de gasto afectada
     */
    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    /**
     * Subcuenta presupuestal afectada cuando el movimiento requiere precisión por subcuenta.
     */
    public function budgetCedula(): BelongsTo
    {
        return $this->belongsTo(BudgetCedula::class, 'budget_cedula_id');
    }

    /**
     * Scope: Detalles de origen
     */
    public function scopeOrigin($query)
    {
        return $query->where('detail_type', self::TYPE_ORIGIN);
    }

    /**
     * Scope: Detalles de destino
     */
    public function scopeDestination($query)
    {
        return $query->where('detail_type', self::TYPE_DESTINATION);
    }

    /**
     * Scope: Detalles de ajuste
     */
    public function scopeAdjustment($query)
    {
        return $query->where('detail_type', self::TYPE_ADJUSTMENT);
    }

    /**
     * Scope: Detalles de un centro de costo específico
     */
    public function scopeForCostCenter($query, $costCenterId)
    {
        return $query->where('cost_center_id', $costCenterId);
    }

    /**
     * Scope: Detalles de un mes específico
     */
    public function scopeForMonth($query, $month)
    {
        return $query->where('month', $month);
    }

    /**
     * Scope: Detalles de una categoría específica
     */
    public function scopeForCategory($query, $categoryId)
    {
        return $query->where('expense_category_id', $categoryId);
    }

    /**
     * Verificar si el detalle es de origen
     */
    public function isOrigin(): bool
    {
        return $this->detail_type === self::TYPE_ORIGIN;
    }

    /**
     * Verificar si el detalle es de destino
     */
    public function isDestination(): bool
    {
        return $this->detail_type === self::TYPE_DESTINATION;
    }

    /**
     * Verificar si el detalle es un ajuste
     */
    public function isAdjustment(): bool
    {
        return $this->detail_type === self::TYPE_ADJUSTMENT;
    }

    /**
     * Obtener el nombre del mes
     */
    public function getMonthNameAttribute(): string
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

        return $months[$this->month] ?? '';
    }
}
