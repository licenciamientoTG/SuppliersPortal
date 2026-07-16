<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierDocumentRequirement extends Model
{
    public const STATUSES = ['pending', 'submitted', 'compliant', 'rejected', 'expired'];

    protected $fillable = [
        'supplier_id', 'supplier_document_type_id', 'current_document_id', 'status',
        'is_enforced', 'due_at', 'expires_at', 'fulfilled_at',
    ];

    protected function casts(): array
    {
        return [
            'is_enforced' => 'boolean', 'due_at' => 'datetime', 'expires_at' => 'datetime', 'fulfilled_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(SupplierDocumentType::class, 'supplier_document_type_id');
    }

    public function currentDocument(): BelongsTo
    {
        return $this->belongsTo(SupplierDocument::class, 'current_document_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SupplierDocument::class);
    }

    public function notificationLog(): HasMany
    {
        return $this->hasMany(SupplierDocumentRequirementNotification::class);
    }

    public function isOverdue(): bool
    {
        return ($this->due_at && $this->due_at->isPast()) || ($this->expires_at && $this->expires_at->isPast());
    }
}
