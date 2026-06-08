<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class DirectPurchaseOrderItem extends Model
{
    use HasFactory;
    protected $table = 'odc_direct_purchase_order_items';

    protected $fillable = [
        'direct_purchase_order_id',
        'cost_center_id',
        'expense_category_id',
        'description',
        'quantity',
        'quantity_received',
        'unit_price',
        'iva_rate',
        'subtotal',
        'iva_amount',
        'total',
        'unit_of_measure',
        'sku',
        'notes',
    ];

    protected $casts = [
        'cost_center_id'    => 'integer',
        'quantity'          => 'decimal:3',
        'quantity_received' => 'decimal:3',
        'unit_price'        => 'decimal:2',
        'iva_rate'          => 'decimal:2',
        'subtotal'          => 'decimal:2',
        'iva_amount'        => 'decimal:2',
        'total'             => 'decimal:2',
    ];

    // --- Estado de recepción del ítem ---

    public function getQuantityPendingAttribute(): float
    {
        return max(0, (float) $this->quantity - (float) $this->quantity_received);
    }

    public function isFullyReceived(): bool
    {
        return (float) $this->quantity_received >= (float) $this->quantity;
    }

    public function isPartiallyReceived(): bool
    {
        return (float) $this->quantity_received > 0 && ! $this->isFullyReceived();
    }

    /**
     * Boot del modelo - Auto-calcular montos
     */
    protected static function booted(): void
    {
        static::saving(function (DirectPurchaseOrderItem $item) {
            $item->calculateAmounts();
        });

        // Actualizar totales de la OCD cuando se crea/actualiza/elimina un item
        static::saved(function (DirectPurchaseOrderItem $item) {
            $item->directPurchaseOrder->updateTotals();
        });

        static::deleted(function (DirectPurchaseOrderItem $item) {
            $item->directPurchaseOrder->updateTotals();
        });
    }

    /**
     * Calcular montos basados en cantidad, precio unitario y tasa de IVA
     */
    public function calculateAmounts(): void
    {
        // Asegurar que iva_rate tenga valor por defecto
        if ($this->iva_rate === null) {
            $this->iva_rate = 16.00;
        }

        $this->subtotal = round($this->quantity * $this->unit_price, 2);

        // Calcular IVA según la tasa especificada (0%, 8% o 16%)
        $this->iva_amount = round($this->subtotal * ($this->iva_rate / 100), 2);

        $this->total = round($this->subtotal + $this->iva_amount, 2);
    }

    /**
     * Actualizar montos del item
     */
    public function updateAmounts(): void
    {
        $this->calculateAmounts();
        $this->save();
    }

    /**
     * =========================================
     * RELACIONES
     * =========================================
     */

    // Líneas de recepción registradas contra este ítem
    public function receptionItems(): MorphMany
    {
        return $this->morphMany(ReceptionItem::class, 'receivable_item');
    }

    public function directPurchaseOrder(): BelongsTo
    {
        return $this->belongsTo(DirectPurchaseOrder::class, 'direct_purchase_order_id');
    }

    /**
     * ✅ NUEVA RELACIÓN: Categoría de gasto de esta partida específica
     */
    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    /**
     * =========================================
     * MÉTODOS Y SCOPES
     * =========================================
     */

    /**
     * Obtener etiqueta de la tasa de IVA
     */
    public function getIvaRateLabel(): string
    {
        $rate = (float) $this->iva_rate;

        return match ($rate) {
            0.00 => 'Exento (0%)',
            8.00 => 'Fronteriza (8%)',
            16.00 => 'General (16%)',
            default => $rate . '%',
        };
    }

    public function scopeWithGeneralIva($query)
    {
        return $query->where('iva_rate', 16.00);
    }

    public function scopeWithBorderIva($query)
    {
        return $query->where('iva_rate', 8.00);
    }

    public function scopeExempt($query)
    {
        return $query->where('iva_rate', 0.00);
    }
}
