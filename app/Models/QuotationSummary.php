<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuotationSummary extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'quotation_summaries';

    protected $fillable = [
        'requisition_id',
        'rfq_id',
        'subtotal',
        'iva_amount',
        'total',
        'approval_level_id',
        'selected_supplier_id',
        'requested_by_user_id',
        'selected_by_user_id',
        'current_approver_user_id',
        'authorizer_role_id',
        'effective_authorization_limit',
        'approval_chain_snapshot',
        'resolution_notes',
        'budget_reserved_at',
        'budget_released_at',
        'approval_status',
        'justification',
        'notes',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
    ];

    protected $casts = [
        'requisition_id' => 'integer',
        'rfq_id' => 'integer',
        'approval_level_id' => 'integer',
        'selected_supplier_id' => 'integer',
        'requested_by_user_id' => 'integer',
        'selected_by_user_id' => 'integer',
        'current_approver_user_id' => 'integer',
        'authorizer_role_id' => 'integer',
        'approved_by' => 'integer',
        'rejected_by' => 'integer',
        'subtotal' => 'decimal:2',
        'iva_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'effective_authorization_limit' => 'decimal:2',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'budget_reserved_at' => 'datetime',
        'budget_released_at' => 'datetime',
        'approval_chain_snapshot' => 'array',
    ];

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function currentApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_approver_user_id');
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function selector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selected_by_user_id');
    }

    public function approvalLevel(): BelongsTo
    {
        return $this->belongsTo(ApprovalLevel::class);
    }

    public function selectedSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'selected_supplier_id');
    }

    public function authorizerRole(): BelongsTo
    {
        return $this->belongsTo(AuthorizerRole::class);
    }

    public function budgetCommitments(): HasMany
    {
        return $this->hasMany(BudgetCommitment::class);
    }

    public function approve(int $userId, ?string $notes = null): void
    {
        $this->update([
            'approval_status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
            'notes' => $notes ?? $this->notes,
            'current_approver_user_id' => null,
        ]);
    }

    public function reject(int $userId, string $reason): void
    {
        $this->update([
            'approval_status' => 'rejected',
            'rejected_by' => $userId,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
            'current_approver_user_id' => null,
        ]);
    }

    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->approval_status === 'rejected';
    }

    public function isPending(): bool
    {
        return $this->approval_status === 'pending';
    }

    public function scopeApproved($query)
    {
        return $query->where('approval_status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('approval_status', 'rejected');
    }

    public function scopePending($query)
    {
        return $query->where('approval_status', 'pending');
    }

    public function scopeAssignedTo($query, int $userId)
    {
        return $query->where('current_approver_user_id', $userId);
    }

    public function getSubtotalFormattedAttribute(): string
    {
        return '$'.number_format((float) $this->subtotal, 2);
    }

    public function getIvaAmountFormattedAttribute(): string
    {
        return '$'.number_format((float) $this->iva_amount, 2);
    }

    public function getTotalFormattedAttribute(): string
    {
        return '$'.number_format((float) $this->total, 2);
    }

    public function getApprovalStatusLabelAttribute(): string
    {
        return match ($this->approval_status) {
            'pending' => 'Pendiente',
            'approved' => 'Aprobado',
            'rejected' => 'Rechazado',
            default => 'N/A',
        };
    }
}
