<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuotationGroup extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'quotation_groups';

    protected $fillable = [
        'requisition_id',
        'name',
        'notes',
        'status',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'rejected_at',
        'rejected_by',
        'rejection_reason',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'requisition_id' => 'integer',
        'cancelled_at' => 'datetime',
        'cancelled_by' => 'integer',
        'rejected_at' => 'datetime',
        'rejected_by' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    // =========================================================================
    // RELACIONES
    // =========================================================================

    /**
     * Requisición a la que pertenece este grupo.
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    /**
     * Partidas que pertenecen a este grupo (relación muchos a muchos).
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(
            RequisitionItem::class,
            'quotation_group_items',
            'quotation_group_id',
            'requisition_item_id'
        )
            ->withPivot(['notes', 'sort_order'])
            ->withTimestamps()
            ->orderBy('quotation_group_items.sort_order');
    }

    /**
     * RFQs generadas para este grupo.
     */
    public function rfqs(): HasMany
    {
        return $this->hasMany(Rfq::class);
    }

    /**
     * Usuario que creó el grupo.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Usuario que actualizó el grupo.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    // =========================================================================
    // MÉTODOS DE LÓGICA DE NEGOCIO
    // =========================================================================

    /**
     * Calcula el subtotal del grupo (suma de todas las partidas).
     */
    public function calculateSubtotal(): float
    {
        return $this->items->sum(function ($item) {
            return $item->quantity * $item->unit_price;
        });
    }

    /**
     * Obtiene las categorías únicas en este grupo.
     */
    public function getCategories(): array
    {
        return $this->items->pluck('product.category.name')->unique()->toArray();
    }

    /**
     * Verifica si el grupo tiene categorías mixtas.
     */
    public function hasMixedCategories(): bool
    {
        return count($this->getCategories()) > 1;
    }

    /**
     * Obtiene el conteo de partidas en el grupo.
     */
    public function getItemsCount(): int
    {
        return $this->items()->count();
    }

    /**
     * Verifica si el grupo está vacío.
     */
    public function isEmpty(): bool
    {
        return $this->getItemsCount() === 0;
    }

    public function isCancelled(): bool
    {
        return $this->status === 'CANCELLED';
    }

    public function isRejected(): bool
    {
        return $this->status === 'REJECTED';
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    public function isClosed(): bool
    {
        return ! $this->isActive();
    }

    public function cancel(string $reason, int $userId): void
    {
        $this->update([
            'status' => 'CANCELLED',
            'cancelled_at' => now(),
            'cancelled_by' => $userId,
            'cancellation_reason' => $reason,
            'updated_by' => $userId,
        ]);
    }

    public function reject(string $reason, int $userId): void
    {
        $this->update([
            'status' => 'REJECTED',
            'rejected_at' => now(),
            'rejected_by' => $userId,
            'rejection_reason' => $reason,
            'updated_by' => $userId,
        ]);
    }

    public function reopen(int $userId): void
    {
        $this->update([
            'status' => 'ACTIVE',
            'cancelled_at' => null,
            'cancelled_by' => null,
            'cancellation_reason' => null,
            'rejected_at' => null,
            'rejected_by' => null,
            'rejection_reason' => null,
            'updated_by' => $userId,
        ]);
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Grupos de una requisición específica.
     */
    public function scopeByRequisition($query, int $requisitionId)
    {
        return $query->where('requisition_id', $requisitionId);
    }

    /**
     * Grupos con partidas (no vacíos).
     */
    public function scopeWithItems($query)
    {
        return $query->has('items');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'REJECTED');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'CANCELLED');
    }

    /**
     * Grupos vacíos.
     */
    public function scopeEmpty($query)
    {
        return $query->doesntHave('items');
    }

    // =========================================================================
    // ACCESORES
    // =========================================================================

    /**
     * Obtiene el subtotal formateado.
     */
    public function getSubtotalFormattedAttribute(): string
    {
        return format_money($this->calculateSubtotal());
    }
}
