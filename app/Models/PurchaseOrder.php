<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;

    // Días naturales antes de cierre automático por inactividad
    const INACTIVITY_DAYS = 10;

    protected $fillable = [
        'folio',
        'requisition_id',
        'supplier_id',
        'quotation_summary_id',
        'source_type',
        'receiving_location_id',
        'subtotal',
        'iva_amount',
        'total',
        'currency',
        'payment_terms',
        'estimated_delivery_days',
        'status',
        'created_by',
        'received_by',
        'assigned_approver_id',
        'authorizer_role_id',
        'effective_authorization_limit',
        'approval_chain_snapshot',
        'resolution_notes',
        'approved_by',
        'rejected_by',
        'rejected_at',
        'approved_at',
        'issued_at',
        'received_at',
        'closed_at',
        'inactivity_warning_sent_at',
        'reception_notes',
        'supplier_delivered_at',
        'reception_deadline_at',
        'physical_receiver_name',
        'delivery_observations',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'issued_at' => 'datetime',
        'received_at' => 'datetime',
        'closed_at' => 'datetime',
        'inactivity_warning_sent_at' => 'datetime',
        'supplier_delivered_at' => 'datetime',
        'reception_deadline_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updated(function (PurchaseOrder $order) {
            $originalStatus = $order->getOriginal('status');
            $newStatus = $order->status;

            if ($originalStatus === $newStatus) {
                return;
            }

            if ($newStatus === 'DELIVERED_PENDING_RECEPTION') {
                app(\App\Services\BudgetAllocationService::class)->consumeOrder($order);
            }
        });
    }

    // Relación con el creador (el Superadmin que autorizó)
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Relación con el proveedor a quien le compramos
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    // ── Autorización de OC de contrato (convenio de precios) ─────────────

    public function assignedApprover()
    {
        return $this->belongsTo(User::class, 'assigned_approver_id');
    }

    public function authorizerRole()
    {
        return $this->belongsTo(AuthorizerRole::class, 'authorizer_role_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function approvals()
    {
        return $this->hasMany(PurchaseOrderApproval::class);
    }

    public function isPendingApproval(): bool
    {
        return $this->status === 'PENDING_APPROVAL';
    }

    public function isRejected(): bool
    {
        return $this->status === 'REJECTED';
    }

    public function isApproverFor(User $user): bool
    {
        return $this->assigned_approver_id !== null
            && (int) $this->assigned_approver_id === (int) $user->id;
    }

    public function scopeAssignedToApprover($query, int $userId)
    {
        return $query->where('assigned_approver_id', $userId)
            ->where('status', 'PENDING_APPROVAL');
    }

    // Relación con la requisición origen
    public function requisition()
    {
        return $this->belongsTo(Requisition::class);
    }

    // El corazón de la OC: sus partidas
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    // Historial de recepciones registradas contra esta OC
    public function receptions(): MorphMany
    {
        return $this->morphMany(Reception::class, 'receivable');
    }

    // Evidencias de entrega subidas por el proveedor
    public function deliveryEvidences(): MorphMany
    {
        return $this->morphMany(SupplierDeliveryEvidence::class, 'evidenceable');
    }

    public function budgetCommitment(): HasOne
    {
        return $this->hasOne(BudgetCommitment::class);
    }

    public function budgetCommitments(): HasMany
    {
        return $this->hasMany(BudgetCommitment::class);
    }

    public function financialProvisions(): MorphMany
    {
        return $this->morphMany(FinancialProvision::class, 'receivable');
    }

    public function supplierInvoices(): MorphMany
    {
        return $this->morphMany(SupplierInvoice::class, 'receivable');
    }

    public function quotationSummary()
    {
        return $this->belongsTo(QuotationSummary::class);
    }

    public function receivingLocation()
    {
        return $this->belongsTo(ReceivingLocation::class);
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    // --- Verificadores de estado ---

    public function isOpen(): bool
    {
        return $this->status === 'OPEN';
    }

    public function isIssued(): bool
    {
        return $this->status === 'ISSUED';
    }

    public function isPartiallyReceived(): bool
    {
        return $this->status === 'PARTIALLY_RECEIVED';
    }

    public function isReceived(): bool
    {
        return $this->status === 'RECEIVED';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'CANCELLED';
    }

    public function isClosedByInactivity(): bool
    {
        return $this->status === 'CLOSED_BY_INACTIVITY';
    }

    /**
     * Una OC puede recibirse si fue emitida al proveedor (ISSUED),
     * si ya tiene una recepción parcial previa (PARTIALLY_RECEIVED),
     * o si el proveedor registró la entrega física y queda pendiente
     * la captura formal de la recepción.
     */
    public function canBeReceived(): bool
    {
        return in_array($this->status, ['ISSUED', 'PARTIALLY_RECEIVED', 'DELIVERED_PENDING_RECEPTION']);
    }

    /**
     * La OC puede recibir entrega de proveedor si está emitida o parcialmente recibida
     * y NO está ya en estado de entrega pendiente de captura.
     */
    public function canReceiveSupplierDelivery(): bool
    {
        return in_array($this->status, ['ISSUED', 'PARTIALLY_RECEIVED']);
    }

    /**
     * La OC está en estado "entregada pero sin captura de recepción por la estación"
     */
    public function isDeliveredPendingReception(): bool
    {
        return $this->status === 'DELIVERED_PENDING_RECEPTION';
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'PENDING_APPROVAL' => 'Pendiente de autorización',
            'REJECTED' => 'Rechazada',
            'OPEN' => 'Abierta',
            'ISSUED' => 'Emitida',
            'PARTIALLY_RECEIVED' => 'Parcialmente Recibida',
            'RECEIVED' => 'Recibida Completa',
            'CANCELLED' => 'Cancelada',
            'PAID' => 'Pagada',
            'CLOSED_BY_INACTIVITY' => 'Cerrada por Inactividad',
            'DELIVERED_PENDING_RECEPTION' => 'Entregada — Pendiente de Captura',
            default => 'Desconocido',
        };
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            'PENDING_APPROVAL' => 'warning',
            'REJECTED' => 'danger',
            'OPEN' => 'warning',
            'ISSUED' => 'info',
            'PARTIALLY_RECEIVED' => 'primary',
            'RECEIVED' => 'success',
            'CANCELLED' => 'danger',
            'PAID' => 'success',
            'CLOSED_BY_INACTIVITY' => 'dark',
            'DELIVERED_PENDING_RECEPTION' => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Fecha límite de aprobación (created_at + 10 días naturales).
     */
    public function getAutoCloseDeadline(): \Carbon\Carbon
    {
        return ($this->issued_at ?? $this->created_at)->copy()->addDays(self::INACTIVITY_DAYS);
    }

    /**
     * Días naturales restantes antes del cierre automático.
     * Negativo si ya venció.
     */
    public function getDaysUntilAutoClose(): int
    {
        return (int) now()->diffInDays($this->getAutoCloseDeadline(), false);
    }
}
