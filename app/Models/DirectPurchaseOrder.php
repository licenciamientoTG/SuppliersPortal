<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use App\Enum\PurchaseType;
use App\Models\CostCenter;

class DirectPurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'odc_direct_purchase_orders';

    // Días naturales antes de cierre automático por inactividad
    const INACTIVITY_DAYS = 7;

    protected $fillable = [
        'folio',
        'supplier_id',
        'receiving_location_id',
        'application_month',
        'justification',
        'subtotal',
        'iva_amount',
        'total',
        'currency',
        'payment_terms',
        'estimated_delivery_days',
        'required_approval_level',
        'assigned_approver_id',
        'authorizer_role_id',
        'effective_authorization_limit',
        'approval_chain_snapshot',
        'resolution_notes',
        'status',
        'pdf_path',
        'reception_notes',
        'created_by',
        'approved_by',
        'rejected_by',
        'returned_by',
        'received_by',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'returned_at',
        'budget_reserved_at',
        'budget_released_at',
        'issued_at',
        'received_at',
        'closed_at',
        'inactivity_warning_sent_at',
        'supplier_delivered_at',
        'reception_deadline_at',
        'physical_receiver_name',
        'delivery_observations',
    ];

    protected $casts = [
        'supplier_id' => 'integer',
        'receiving_location_id' => 'integer',
        'required_approval_level' => 'integer',
        'assigned_approver_id' => 'integer',
        'authorizer_role_id' => 'integer',
        'created_by' => 'integer',
        'approved_by' => 'integer',
        'rejected_by' => 'integer',
        'returned_by' => 'integer',
        'received_by' => 'integer',
        'subtotal' => 'decimal:2',
        'iva_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'effective_authorization_limit' => 'decimal:2',
        'approval_chain_snapshot' => 'array',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'returned_at' => 'datetime',
        'budget_reserved_at' => 'datetime',
        'budget_released_at' => 'datetime',
        'issued_at' => 'datetime',
        'received_at' => 'datetime',
        'closed_at' => 'datetime',
        'inactivity_warning_sent_at' => 'datetime',
        'supplier_delivered_at' => 'datetime',
        'reception_deadline_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updated(function (DirectPurchaseOrder $order) {
            $originalStatus = $order->getOriginal('status');
            $newStatus = $order->status;

            if ($originalStatus === $newStatus) {
                return;
            }

            if ($newStatus === 'ISSUED') {
                app(\App\Services\BudgetAllocationService::class)->syncCommitmentTrace($order);
            }

            if ($newStatus === 'RETURNED' && $originalStatus === 'ISSUED') {
                app(\App\Services\BudgetAllocationService::class)->releaseTrace($order);
            }

            if ($newStatus === 'DELIVERED_PENDING_RECEPTION') {
                app(\App\Services\BudgetAllocationService::class)->consumeOrder($order);
            }
        });
    }

    /**
     * =========================================
     * RELACIONES
     * =========================================
     */

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function receivingLocation(): BelongsTo
    {
        return $this->belongsTo(ReceivingLocation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function assignedApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_approver_id');
    }

    public function authorizerRole(): BelongsTo
    {
        return $this->belongsTo(AuthorizerRole::class);
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DirectPurchaseOrderItem::class);
    }

    public function primaryCostCenter(): ?CostCenter
    {
        if ($this->relationLoaded('items')) {
            return $this->items
                ->loadMissing('costCenter')
                ->pluck('costCenter')
                ->filter()
                ->first();
        }

        return $this->items()
            ->with('costCenter')
            ->orderBy('id')
            ->first()?->costCenter;
    }

    public function primaryCostCenterLabel(): string
    {
        $costCenter = $this->primaryCostCenter();

        if (! $costCenter) {
            return 'N/A';
        }

        return $costCenter->code
            ? "{$costCenter->code} - {$costCenter->name}"
            : $costCenter->name;
    }

    public function primaryPurchaseType(): ?string
    {
        $purchaseType = $this->primaryCostCenter()?->purchase_type;

        if ($purchaseType instanceof PurchaseType) {
            return $purchaseType->value;
        }

        return $purchaseType ? (string) $purchaseType : null;
    }

    public function primaryCompanyId(): ?int
    {
        return $this->primaryCostCenter()?->company_id;
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(DirectPurchaseOrderApproval::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DirectPurchaseOrderDocument::class);
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

    // Historial de recepciones registradas contra esta OCD
    public function receptions(): MorphMany
    {
        return $this->morphMany(Reception::class, 'receivable');
    }

    // Evidencias de entrega subidas por el proveedor
    public function deliveryEvidences(): MorphMany
    {
        return $this->morphMany(SupplierDeliveryEvidence::class, 'evidenceable');
    }

    /**
     * =========================================
     * MÉTODOS DE NEGOCIO
     * =========================================
     */

    /**
     * Genera el siguiente folio disponible para OCD
     */
    public static function generateNextFolio(): string
    {
        $year = now()->year;
        $lastOrder = self::whereBetween('created_at', ["{$year}-01-01 00:00:00", "{$year}-12-31 23:59:59"])
            ->whereNotNull('folio')
            ->lockForUpdate()
            ->orderBy('folio', 'desc')
            ->first();

        $nextNumber = 1;
        if ($lastOrder && preg_match('/OCD-\d{4}-(\d{4})/', $lastOrder->folio, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        }

        return sprintf('OCD-%d-%04d', $year, $nextNumber);
    }

    /**
     * Determina el nivel de aprobación requerido según el monto total
     */
    public function determineRequiredApprovalLevel(): int
    {
        $level = app(\App\Services\ApprovalService::class)->getLevelForAmount($this->total);

        return $level ? $level->level_number : 1;
    }

    /**
     * Envía la OCD a aprobación
     */
    public function submit(): bool
    {
        if (!$this->canBeSubmitted()) {
            return false;
        }

        return DB::transaction(function () {
            return $this->update([
                'folio' => $this->folio ?? self::generateNextFolio(),
                'status' => 'PENDING_APPROVAL',
                'required_approval_level' => null,
                'submitted_at' => now(),
            ]);
        });
    }

    /**
     * =========================================
     * MÉTODOS DE ESTADO (STATUS CHECKS)
     * =========================================
     */

    public function isDraft(): bool
    {
        return $this->status === 'DRAFT';
    }

    public function isPendingApproval(): bool
    {
        return $this->status === 'PENDING_APPROVAL';
    }

    public function isApproved(): bool
    {
        return $this->status === 'APPROVED';
    }

    public function isRejected(): bool
    {
        return $this->status === 'REJECTED';
    }

    public function isReturned(): bool
    {
        return $this->status === 'RETURNED';
    }

    public function isIssued(): bool
    {
        return $this->status === 'ISSUED';
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
     * Fecha límite de aprobación (submitted_at + 7 días naturales).
     * Retorna null si aún no ha sido enviada a aprobación.
     */
    public function getAutoCloseDeadline(): ?\Carbon\Carbon
    {
        return $this->submitted_at
            ? $this->submitted_at->copy()->addDays(self::INACTIVITY_DAYS)
            : null;
    }

    /**
     * Días naturales restantes antes del cierre automático.
     * Retorna null si no aplica, negativo si ya venció.
     */
    public function getDaysUntilAutoClose(): ?int
    {
        $deadline = $this->getAutoCloseDeadline();
        if (!$deadline) {
            return null;
        }
        return (int) now()->diffInDays($deadline, false);
    }

    /**
     * =========================================
     * VALIDACIONES DE PERMISOS Y ACCIONES
     * =========================================
     */

    public function isCreatedBy(User $user): bool
    {
        return $this->created_by === $user->id;
    }

    public function isApproverFor(User $user): bool
    {
        return $this->assigned_approver_id === $user->id;
    }

    public function canBeEdited(): bool
    {
        return in_array($this->status, ['DRAFT', 'RETURNED']);
    }

    public function canBeSubmitted(): bool
    {
        return in_array($this->status, ['DRAFT', 'RETURNED']) && $this->items()->count() > 0;
    }

    public function canBeApproved(): bool
    {
        return $this->status === 'PENDING_APPROVAL';
    }

    public function canBeReceived(): bool
    {
        return in_array($this->status, ['ISSUED', 'PARTIALLY_RECEIVED', 'DELIVERED_PENDING_RECEPTION']);
    }

    /**
     * La OCD puede recibir entrega de proveedor si está emitida o parcialmente recibida
     */
    public function canReceiveSupplierDelivery(): bool
    {
        return in_array($this->status, ['ISSUED', 'PARTIALLY_RECEIVED']);
    }

    /**
     * La OCD está en estado "entregada pero sin captura de recepción por la estación"
     */
    public function isDeliveredPendingReception(): bool
    {
        return $this->status === 'DELIVERED_PENDING_RECEPTION';
    }

    public function canBeReturnedToRevision(): bool
    {
        return $this->status === 'ISSUED' && $this->receptions()->count() === 0;
    }

    public function isPartiallyReceived(): bool
    {
        return $this->status === 'PARTIALLY_RECEIVED';
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            'DRAFT'              => 'secondary',
            'PENDING_APPROVAL'   => 'warning',
            'APPROVED'           => 'success',
            'REJECTED'           => 'danger',
            'RETURNED'           => 'info',
            'ISSUED'             => 'primary',
            'PARTIALLY_RECEIVED' => 'primary',
            'RECEIVED'           => 'success',
            'CANCELLED'          => 'dark',
            'CLOSED_BY_INACTIVITY' => 'dark',
            'DELIVERED_PENDING_RECEPTION' => 'danger',
            default              => 'secondary',
        };
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'DRAFT'              => 'Borrador',
            'PENDING_APPROVAL'   => 'Pendiente de Aprobación',
            'APPROVED'           => 'Aprobada',
            'REJECTED'           => 'Rechazada',
            'RETURNED'           => 'Devuelta para Corrección',
            'ISSUED'             => 'Emitida',
            'PARTIALLY_RECEIVED' => 'Parcialmente Recibida',
            'RECEIVED'           => 'Recibida Completa',
            'CANCELLED'          => 'Cancelada',
            'CLOSED_BY_INACTIVITY' => 'Cerrada por Inactividad',
            'DELIVERED_PENDING_RECEPTION' => 'Entregada — Pendiente de Captura',
            default              => 'Desconocido',
        };
    }

    /**
     * Calcula el total de la OCD sumando de forma segura sus items
     */
    public function calculateTotals(): array
    {
        $result = $this->items()
            ->selectRaw('SUM(subtotal) as subtotal, SUM(iva_amount) as iva_amount, SUM(total) as total')
            ->first();

        return [
            'subtotal'   => round((float) $result->subtotal, 2),
            'iva_amount' => round((float) $result->iva_amount, 2),
            'total'      => round((float) $result->total, 2),
        ];
    }

    /**
     * Actualiza los totales de la OCD basándose en sus items
     */
    public function updateTotals(): void
    {
        $totals = $this->calculateTotals();

        $this->update([
            'subtotal' => $totals['subtotal'],
            'iva_amount' => $totals['iva_amount'],
            'total' => $totals['total'],
        ]);
    }

    /**
     * =========================================
     * SCOPES
     * =========================================
     */

    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePendingApproval($query)
    {
        return $query->where('status', 'PENDING_APPROVAL');
    }

    public function scopeCreatedBy($query, $userId)
    {
        return $query->where('created_by', $userId);
    }

    public function scopeAssignedToApprover($query, $userId)
    {
        return $query->where('assigned_approver_id', $userId)
            ->where('status', 'PENDING_APPROVAL');
    }

    public function scopeByMonth($query, string $month)
    {
        return $query->where('application_month', $month);
    }
}
