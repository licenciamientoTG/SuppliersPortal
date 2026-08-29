<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetCommitment extends Model
{
    use HasFactory;

    protected $table = 'budget_commitments';

    protected $fillable = [
        'direct_purchase_order_id',
        'purchase_order_id',
        'quotation_summary_id',
        'cost_center_id',
        'application_month',
        'expense_category_id',
        'budget_cedula_id',
        'committed_amount',
        'consumed_amount',
        'status',
        'committed_at',
        'released_at',
        'received_at',
    ];

    protected $casts = [
        'committed_amount' => 'decimal:2',
        'consumed_amount' => 'decimal:2',
        'committed_at' => 'datetime',
        'released_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    /**
     * =========================================
     * RELACIONES
     * =========================================
     */
    public function directPurchaseOrder(): BelongsTo
    {
        return $this->belongsTo(DirectPurchaseOrder::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function quotationSummary(): BelongsTo
    {
        return $this->belongsTo(QuotationSummary::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    public function budgetCedula(): BelongsTo
    {
        return $this->belongsTo(BudgetCedula::class, 'budget_cedula_id');
    }

    /**
     * =========================================
     * MÉTODOS DE ESTADO
     * =========================================
     */

    /**
     * Verifica si el compromiso está activo
     */
    public function isCommitted(): bool
    {
        return $this->status === 'COMMITTED';
    }

    /**
     * Verifica si ya se recibió el bien/servicio
     */
    public function isReceived(): bool
    {
        return $this->status === 'RECEIVED';
    }

    /**
     * Verifica si el compromiso fue liberado
     */
    public function isReleased(): bool
    {
        return $this->status === 'RELEASED';
    }

    /**
     * =========================================
     * MÉTODOS DE NEGOCIO
     * =========================================
     */

    /**
     * Libera el compromiso presupuestal
     */
    public function release(): void
    {
        $this->update([
            'status' => 'RELEASED',
            'released_at' => now(),
        ]);
    }

    /**
     * Marca el compromiso como recibido registrando la fecha de recepción.
     */
    public function markAsReceived(): void
    {
        $this->update([
            'status' => 'RECEIVED',
            'received_at' => now(),
        ]);
    }

    /**
     * Obtiene el badge de color según el estatus
     */
    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            'COMMITTED' => 'warning',
            'RECEIVED' => 'success',
            'RELEASED' => 'secondary',
            default => 'secondary'
        };
    }

    /**
     * Obtiene el texto legible del estatus
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'COMMITTED' => 'Comprometido',
            'RECEIVED' => 'Recibido',
            'RELEASED' => 'Liberado',
            default => 'Desconocido'
        };
    }

    /**
     * Obtiene el tipo de orden asociada
     */
    public function getOrderType(): string
    {
        if ($this->direct_purchase_order_id) {
            return 'OCD';
        }
        if ($this->purchase_order_id) {
            return 'OC';
        }
        if ($this->quotation_summary_id) {
            return 'COT';
        }

        return 'Desconocido';
    }

    /**
     * Obtiene el folio de la orden asociada
     */
    public function getOrderFolio(): ?string
    {
        if ($this->direct_purchase_order_id) {
            return $this->directPurchaseOrder?->folio;
        }
        if ($this->purchase_order_id) {
            return $this->purchaseOrder?->folio;
        }
        if ($this->quotation_summary_id) {
            return $this->quotationSummary?->rfq?->folio;
        }

        return null;
    }

    /**
     * =========================================
     * SCOPES
     * =========================================
     */

    /**
     * Scope para compromisos activos (no liberados)
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['COMMITTED', 'RECEIVED']);
    }

    /**
     * Scope para compromisos de un centro de costo y mes específico
     */
    public function scopeForBudget($query, $costCenterId, $month, $categoryId)
    {
        return $query->where('cost_center_id', $costCenterId)
            ->where('application_month', $month)
            ->where('expense_category_id', $categoryId);
    }

    /**
     * Scope para compromisos comprometidos (bloquean presupuesto)
     */
    public function scopeCommitted($query)
    {
        return $query->whereIn('status', ['COMMITTED', 'RECEIVED']);
    }

    /**
     * Scope para compromisos de OCD
     */
    public function scopeFromDirectOrders($query)
    {
        return $query->whereNotNull('direct_purchase_order_id');
    }

    /**
     * Scope para compromisos de OC normales
     */
    public function scopeFromPurchaseOrders($query)
    {
        return $query->whereNotNull('purchase_order_id');
    }

    public function scopeFromQuotationSummaries($query)
    {
        return $query->whereNotNull('quotation_summary_id');
    }

    /**
     * =========================================
     * MÉTODOS ESTÁTICOS DE CONSULTA
     * =========================================
     */

    /**
     * Calcula el total comprometido para un centro de costo/mes/categoría
     */
    public static function getTotalCommitted($costCenterId, $month, $categoryId): float
    {
        return static::forBudget($costCenterId, $month, $categoryId)
            ->committed()
            ->sum('committed_amount');
    }

    /**
     * Verifica si hay presupuesto disponible para un monto específico
     */
    public static function checkAvailability($costCenterId, $month, $categoryId, $requiredAmount): array
    {
        // Aquí asumimos que tienes un modelo Budget que maneja el presupuesto asignado
        // Esta lógica puede ajustarse según tu implementación actual

        // Presupuesto asignado (necesitarás implementar esto según tu sistema)
        // $assignedBudget = Budget::getAssigned($costCenterId, $month, $categoryId);

        $committed = static::getTotalCommitted($costCenterId, $month, $categoryId);

        // Presupuesto ejercido (facturas pagadas) - también depende de tu implementación
        // $exercised = BudgetExercise::getTotal($costCenterId, $month, $categoryId);

        // Por ahora retornamos solo lo comprometido
        return [
            'committed' => $committed,
            // 'assigned' => $assignedBudget,
            // 'exercised' => $exercised,
            // 'available' => $assignedBudget - $committed - $exercised,
            // 'is_available' => ($assignedBudget - $committed - $exercised) >= $requiredAmount
        ];
    }
}
