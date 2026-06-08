<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PurchaseOrderItem extends Model
{
    use HasFactory;
    protected $fillable = [
        'purchase_order_id',
        'requisition_item_id',
        'description',
        'quantity',
        'quantity_received',
        'unit_price',
        'subtotal',
        'iva_amount',
        'total',
    ];

    protected $casts = [
        'quantity'          => 'decimal:3',
        'quantity_received' => 'decimal:3',
        'unit_price'        => 'decimal:2',
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

    // Líneas de recepción registradas contra este ítem
    public function receptionItems(): MorphMany
    {
        return $this->morphMany(ReceptionItem::class, 'receivable_item');
    }

    // Relación con su cabecera
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    // Relación con el item original de la requisición
    public function requisitionItem()
    {
        return $this->belongsTo(RequisitionItem::class);
    }
}
