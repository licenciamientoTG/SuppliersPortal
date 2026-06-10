<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;

class RfqResponse extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'rfq_responses';

    protected $fillable = [
        'rfq_id',
        'supplier_id',
        'requisition_item_id',
        'quotation_date',
        'validity_days',
        'supplier_quotation_number',
        'unit_price',
        'quantity',
        'subtotal',
        // 🆕 IMPUESTOS
        'iva_rate',
        'iva_amount',
        'total',
        // 🆕 DESCUENTOS
        'discount_percentage',
        'discount_amount',
        // 🆕 MONEDA
        'currency',
        // CONDICIONES
        'delivery_days',
        'payment_terms',
        'warranty_terms',
        'brand',
        'model',
        'specifications',
        'notes',
        'attachment_path',
        'meets_specs',
        'score',
        'evaluation_notes',
        'status',
        'selection_justification',
        'submitted_at',
        'evaluated_by',
        'evaluated_at',
    ];

    protected $casts = [
        'quotation_date' => 'date',
        'validity_days' => 'integer',
        'unit_price' => 'decimal:2',
        'quantity' => 'decimal:3',
        'subtotal' => 'decimal:2',
        // 🆕 IMPUESTOS
        'iva_rate' => 'decimal:2',
        'iva_amount' => 'decimal:2',
        'total' => 'decimal:2',
        // 🆕 DESCUENTOS
        'discount_percentage' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        // OTROS
        'delivery_days' => 'integer',
        'meets_specs' => 'boolean',
        'score' => 'integer',
        'submitted_at' => 'datetime',
        'evaluated_at' => 'datetime',
    ];

    /**
     * Configuración de la munición de logs
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['unit_price', 'quantity', 'status']) // Lo que queremos vigilar
            ->logOnlyDirty() // Solo si hubo cambios reales
            ->dontSubmitEmptyLogs();
    }

    /**
     * 🎯 LA MANIOBRA MAESTRA: Vincular al Padre
     */
    public function tapActivity(Activity $activity, string $eventName)
    {
        // Forzamos que el "Sujeto" de la actividad sea la RFQ principal
        // Así, cuando consultes $rfq->activities, aparecerán estos registros.
        $activity->subject_id = $this->rfq_id;
        $activity->subject_type = Rfq::class;

        // Opcional: Guardamos quién es el proveedor en las propiedades para saber de quién era la respuesta
        $activity->properties = $activity->properties->put('supplier_id', $this->supplier_id);

        // Personalizamos la descripción para que el administrativo no se confunda
        $supplierName = $this->supplier->company_name ?? 'Proveedor';
        $activity->description = "Respuesta de {$supplierName}: " . $this->getDescriptionForEvent($eventName);
    }

    protected function getDescriptionForEvent(string $eventName): string
    {
        return match ($eventName) {
            'created' => 'creó un borrador de cotización',
            'updated' => 'actualizó los montos de la oferta',
            'deleted' => 'eliminó una respuesta enviada',
            default   => $eventName
        };
    }

    // =========================================================================
    // RELACIONES
    // =========================================================================

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function requisitionItem(): BelongsTo
    {
        return $this->belongsTo(RequisitionItem::class);
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }

    /**
     * Respuestas de proveedores para esta RFQ
     */
    public function rfqResponses(): HasMany
    {
        return $this->hasMany(RfqResponse::class);
    }

    /**
     * Helper: Obtener la respuesta de un proveedor específico
     */
    public function getResponseForSupplier(int $supplierId): ?RfqResponse
    {
        return $this->rfqResponses()
            ->where('supplier_id', $supplierId)
            ->first();
    }

    /**
     * Helper: Verificar si un proveedor ya respondió
     */
    public function hasResponseFrom(int $supplierId): bool
    {
        return $this->rfqResponses()
            ->where('supplier_id', $supplierId)
            ->where('status', 'SUBMITTED')
            ->exists();
    }

    // =========================================================================
    // 🆕 MÉTODOS DE CÁLCULO (IVA Y TOTALES)
    // =========================================================================

    /**
     * Calcula el subtotal base (precio × cantidad)
     */
    public function calculateSubtotal(): float
    {
        return $this->unit_price * $this->quantity;
    }

    /**
     * Calcula el subtotal después de aplicar descuento
     */
    public function calculateSubtotalWithDiscount(): float
    {
        $subtotal = $this->calculateSubtotal();

        if ($this->discount_amount) {
            return $subtotal - $this->discount_amount;
        }

        if ($this->discount_percentage) {
            $discount = $subtotal * ($this->discount_percentage / 100);
            return $subtotal - $discount;
        }

        return $subtotal;
    }

    /**
     * Calcula el monto del IVA
     */
    public function calculateIvaAmount(): float
    {
        $subtotal = $this->calculateSubtotalWithDiscount();
        $ivaRate = $this->iva_rate ?? 16.00;

        return $subtotal * ($ivaRate / 100);
    }

    /**
     * Calcula el total final (subtotal con descuento + IVA)
     */
    public function calculateTotal(): float
    {
        $subtotal = $this->calculateSubtotalWithDiscount();
        $iva = $this->calculateIvaAmount();

        return $subtotal + $iva;
    }

    /**
     * Actualiza todos los campos calculados
     */
    public function updateCalculatedFields(): void
    {
        $subtotal = $this->calculateSubtotal();

        // Si hay descuento porcentual, calcular monto
        if ($this->discount_percentage && !$this->discount_amount) {
            $this->discount_amount = $subtotal * ($this->discount_percentage / 100);
        }

        $subtotalWithDiscount = $this->calculateSubtotalWithDiscount();
        $ivaAmount = $this->calculateIvaAmount();
        $total = $subtotalWithDiscount + $ivaAmount;

        $this->update([
            'subtotal' => $subtotal,
            'iva_amount' => $ivaAmount,
            'total' => $total,
        ]);
    }

    /**
     * @deprecated Use updateCalculatedFields() instead
     */
    public function updateSubtotal(): void
    {
        $this->updateCalculatedFields();
    }

    // =========================================================================
    // MÉTODOS DE LÓGICA DE NEGOCIO
    // =========================================================================

    public function isDraft(): bool
    {
        return $this->status === 'DRAFT';
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'SUBMITTED';
    }

    public function isApproved(): bool
    {
        return $this->status === 'APPROVED';
    }

    public function isRejected(): bool
    {
        return $this->status === 'REJECTED';
    }

    public function submit(): void
    {
        $this->update([
            'status' => 'SUBMITTED',
            'submitted_at' => now(),
            'quotation_date' => now(),
        ]);
    }

    public function approve(int $userId, ?string $justification = null): void
    {
        $this->update([
            'status' => 'APPROVED',
            'selection_justification' => $justification,
            'evaluated_by' => $userId,
            'evaluated_at' => now(),
        ]);
    }

    public function reject(int $userId, ?string $justification = null): void
    {
        $this->update([
            'status' => 'REJECTED',
            'selection_justification' => $justification,
            'evaluated_by' => $userId,
            'evaluated_at' => now(),
        ]);
    }

    public function isValid(): bool
    {
        if (!$this->quotation_date || !$this->validity_days) {
            return true;
        }

        $expiryDate = $this->quotation_date->addDays($this->validity_days);
        return now()->lte($expiryDate);
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeDraft($query)
    {
        return $query->where('status', 'DRAFT');
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', 'SUBMITTED');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'APPROVED');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'REJECTED');
    }

    public function scopeByRfq($query, int $rfqId)
    {
        return $query->where('rfq_id', $rfqId);
    }

    public function scopeBySupplier($query, int $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeByItem($query, int $itemId)
    {
        return $query->where('requisition_item_id', $itemId);
    }

    public function scopeMeetsSpecs($query)
    {
        return $query->where('meets_specs', true);
    }

    public function scopeValid($query)
    {
        return $query->whereRaw('
            quotation_date IS NULL 
            OR validity_days IS NULL 
            OR DATEADD(day, validity_days, quotation_date) >= GETDATE()
        ');
    }

    // =========================================================================
    // 🆕 ACCESORES FORMATEADOS
    // =========================================================================

    public function getUnitPriceFormattedAttribute(): string
    {
        return $this->formatCurrency($this->unit_price);
    }

    public function getSubtotalFormattedAttribute(): string
    {
        return $this->formatCurrency($this->subtotal);
    }

    public function getIvaAmountFormattedAttribute(): string
    {
        return $this->formatCurrency($this->iva_amount);
    }

    public function getTotalFormattedAttribute(): string
    {
        return $this->formatCurrency($this->total);
    }

    public function getDiscountAmountFormattedAttribute(): string
    {
        return $this->formatCurrency($this->discount_amount ?? 0);
    }

    /**
     * Helper para formatear moneda
     */
    private function formatCurrency(float $amount): string
    {
        return format_money($amount, $this->currency);
    }

    /**
     * Obtiene el símbolo de la moneda
     */
    public function getCurrencySymbol(): string
    {
        return match ($this->currency ?? 'MXN') {
            'MXN' => '$',
            'USD' => 'US$',
            'EUR' => '€',
            default => '$',
        };
    }

    public function getExpiryDateAttribute(): ?\Carbon\Carbon
    {
        if (!$this->quotation_date || !$this->validity_days) {
            return null;
        }

        return $this->quotation_date->addDays($this->validity_days);
    }

    public function getDaysRemainingAttribute(): ?int
    {
        if (!$this->expiry_date) {
            return null;
        }

        return now()->diffInDays($this->expiry_date, false);
    }

    /**
     * Eliminar archivo adjunto del storage
     */
    public function deleteAttachment(): void
    {
        if ($this->attachment_path && Storage::disk('public')->exists($this->attachment_path)) {
            Storage::disk('public')->delete($this->attachment_path);
            $this->update(['attachment_path' => null]);
        }
    }
}
