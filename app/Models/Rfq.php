<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Rfq extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'rfqs';

    protected $fillable = [
        'folio',
        'requisition_id',
        'quotation_group_id',
        'requisition_item_id',
        'supplier_id',
        'supersedes_rfq_id',
        'source',
        'external_contact_method',
        'external_notes',
        'status',
        'sent_at',
        'response_deadline',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'rejected_at',
        'rejected_by',
        'rejection_reason',
        'notes',
        'message',
        'requirements',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'response_deadline' => 'datetime',
        'cancelled_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'response_deadline']); // Define qué quieres auditar
    }

    // =========================================================================
    // RELACIONES
    // =========================================================================

    /**
     * Requisición de origen.
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    /**
     * Grupo de cotización (si aplica).
     */
    public function quotationGroup(): BelongsTo
    {
        return $this->belongsTo(QuotationGroup::class);
    }

    public function supersededRfq(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_rfq_id');
    }

    /**
     * Partida individual (si aplica).
     */
    public function requisitionItem(): BelongsTo
    {
        return $this->belongsTo(RequisitionItem::class);
    }

    /**
     * Proveedor al que se envió la RFQ.
     */
    public function suppliers()
    {
        return $this->belongsToMany(Supplier::class, 'rfq_suppliers')
            ->using(RfqSupplier::class) // 👈 Especificar modelo pivot personalizado
            ->withPivot([
                'invited_at',
                'responded_at',
                'quotation_pdf_path', // 👈 Agregar campo
                'notes',
            ])
            ->withTimestamps();
    }

    // Relación opcional para proveedor único (legacy)
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Respuestas del proveedor (cotizaciones).
     */
    public function rfqResponses(): HasMany
    {
        return $this->hasMany(RfqResponse::class);
    }

    /**
     * Usuario que actualizó la RFQ.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Usuario que creó la RFQ.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Usuario que canceló la RFQ. ✅ NUEVO
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function quotationSummary()
    {
        return $this->hasOne(QuotationSummary::class);
    }

    public function successorRfqs(): HasMany
    {
        return $this->hasMany(self::class, 'supersedes_rfq_id');
    }

    // =========================================================================
    // MÉTODOS DE LÓGICA DE NEGOCIO
    // =========================================================================

    /**
     * Genera el siguiente folio consecutivo.
     * Formato: RFQ-YYYY-###
     */
    public static function nextFolio(): string
    {
        $year = date('Y');
        $prefix = "RFQ-{$year}-";

        $last = static::where('folio', 'like', $prefix.'%')
            ->orderBy('folio', 'desc')
            ->value('folio');

        $n = 0;
        if ($last && preg_match('/RFQ-\d{4}-(\d+)/', $last, $m)) {
            $n = (int) $m[1];
        }
        $n++;

        return sprintf('%s%03d', $prefix, $n);
    }

    /**
     * Verifica si es RFQ de grupo.
     */
    public function isGroupRfq(): bool
    {
        return ! is_null($this->quotation_group_id);
    }

    /**
     * Verifica si es RFQ de partida individual.
     */
    public function isItemRfq(): bool
    {
        return ! is_null($this->requisition_item_id);
    }

    /**
     * Verifica si es RFQ a través del portal.
     */
    public function isPortalRfq(): bool
    {
        return $this->source === 'portal';
    }

    /**
     * Verifica si es cotización externa (manual).
     */
    public function isExternalRfq(): bool
    {
        return $this->source === 'external';
    }

    /**
     * Verifica si la RFQ está vencida.
     */
    public function isExpired(): bool
    {
        return $this->response_deadline && $this->response_deadline->isPast();
    }

    /**
     * Verifica si el proveedor ya respondió.
     */
    public function hasResponded(): bool
    {
        return $this->status === 'RECEIVED';
    }

    /**
     * Verifica si la RFQ está cancelada. ✅ NUEVO
     */
    public function isCancelled(): bool
    {
        return $this->status === 'CANCELLED';
    }

    public function isRejected(): bool
    {
        return $this->status === 'REJECTED';
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['COMPLETED', 'CANCELLED', 'REJECTED'], true);
    }

    public function isActive(): bool
    {
        return ! $this->isClosed();
    }

    /**
     * Marca la RFQ como enviada.
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'SENT',
            'sent_at' => now(),
        ]);
    }

    /**
     * Marca la RFQ como respondida.
     */
    public function markAsResponded(): void
    {
        $this->update([
            'status' => 'RECEIVED',
        ]);
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

    /**
     * Obtiene las partidas a cotizar.
     * Si es grupo, retorna todas las partidas del grupo.
     * Si es individual, retorna solo esa partida.
     */
    public function getItemsToQuote()
    {
        if ($this->isGroupRfq()) {
            return $this->quotationGroup->items;
        }

        if ($this->isItemRfq()) {
            return collect([$this->requisitionItem]);
        }

        return collect([]);
    }

    /**
     * Cuenta las partidas distintas que un proveedor sí cotizó
     * (excluye las marcadas como no disponibles).
     */
    public function quotedItemCountForSupplier(int $supplierId): int
    {
        return $this->rfqResponses()
            ->where('supplier_id', $supplierId)
            ->where('not_available', false)
            ->whereIn('status', ['SUBMITTED', 'SELECTED'])
            ->distinct()
            ->count('requisition_item_id');
    }

    /**
     * Partidas de la RFQ que ningún proveedor cotizó
     * (todos las marcaron no disponible o no respondieron).
     */
    public function itemsQuotedByNoSupplier(): Collection
    {
        $quotedItemIds = $this->rfqResponses()
            ->where('not_available', false)
            ->whereIn('status', ['SUBMITTED', 'SELECTED'])
            ->pluck('requisition_item_id')
            ->unique();

        return $this->getItemsToQuote()
            ->reject(fn ($item) => $quotedItemIds->contains($item->id))
            ->values();
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * RFQs enviadas (no borradores).
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'SENT');
    }

    /**
     * RFQs respondidas.
     */
    public function scopeResponded($query)
    {
        return $query->where('status', 'RECEIVED');
    }

    /**
     * RFQs canceladas. ✅ NUEVO
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'CANCELLED');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'REJECTED');
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['COMPLETED', 'CANCELLED', 'REJECTED']);
    }

    /**
     * Scope para filtrar solo RFQs que están en proceso activo.
     */
    public function scopePending($query)
    {
        // Solo las que fueron enviadas a proveedores o ya tienen respuestas
        return $query->whereIn('status', ['SENT', 'RECEIVED', 'EVALUATED']);
    }

    /**
     * RFQs del portal.
     */
    public function scopePortal($query)
    {
        return $query->where('source', 'portal');
    }

    /**
     * RFQs externas.
     */
    public function scopeExternal($query)
    {
        return $query->where('source', 'external');
    }

    /**
     * RFQs de un proveedor específico.
     */
    public function scopeBySupplier($query, int $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    /**
     * RFQs de una requisición específica.
     */
    public function scopeByRequisition($query, int $requisitionId)
    {
        return $query->where('requisition_id', $requisitionId);
    }

    // =========================================================================
    // ACCESORES
    // =========================================================================

    /**
     * Días restantes para responder.
     */
    public function getDaysRemainingAttribute(): ?int
    {
        if (! $this->response_deadline) {
            return null;
        }

        return now()->diffInDays($this->response_deadline, false);
    }

    /**
     * Label del tipo de RFQ.
     */
    public function getTypeLabelAttribute(): string
    {
        if ($this->isGroupRfq()) {
            return 'Grupo: '.$this->quotationGroup->name;
        }

        if ($this->isItemRfq()) {
            return 'Partida Individual';
        }

        return 'N/A';
    }
}
