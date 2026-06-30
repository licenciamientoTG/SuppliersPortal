<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationSummaryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'quotation_summary_id',
        'rfq_response_id',
        'requisition_item_id',
        'quoted_quantity',
        'approved_quantity',
        'rejected_quantity',
        'unit_price',
        'subtotal',
        'iva_rate',
        'iva_amount',
        'total',
        'approval_status',
        'approver_notes',
    ];

    protected $casts = [
        'quotation_summary_id' => 'integer',
        'rfq_response_id' => 'integer',
        'requisition_item_id' => 'integer',
        'quoted_quantity' => 'decimal:3',
        'approved_quantity' => 'decimal:3',
        'rejected_quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'iva_rate' => 'decimal:2',
        'iva_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function quotationSummary(): BelongsTo
    {
        return $this->belongsTo(QuotationSummary::class);
    }

    public function rfqResponse(): BelongsTo
    {
        return $this->belongsTo(RfqResponse::class);
    }

    public function requisitionItem(): BelongsTo
    {
        return $this->belongsTo(RequisitionItem::class);
    }

    public function recalculate(): void
    {
        $approvedQuantity = max(0, (float) $this->approved_quantity);
        $quotedQuantity = max(0, (float) $this->quoted_quantity);
        $unitPrice = (float) $this->unit_price;
        $ivaRate = (float) ($this->iva_rate ?? 16);

        $this->approved_quantity = min($approvedQuantity, $quotedQuantity);
        $this->rejected_quantity = max(0, $quotedQuantity - (float) $this->approved_quantity);
        $this->subtotal = round((float) $this->approved_quantity * $unitPrice, 2);
        $this->iva_amount = round((float) $this->subtotal * ($ivaRate / 100), 2);
        $this->total = round((float) $this->subtotal + (float) $this->iva_amount, 2);
        $this->approval_status = match (true) {
            (float) $this->approved_quantity <= 0 => 'rejected',
            (float) $this->approved_quantity + 0.000001 < $quotedQuantity => 'partially_approved',
            default => 'approved',
        };
    }
}
