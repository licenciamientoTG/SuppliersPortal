<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierDocumentRequirementNotification extends Model
{
    protected $fillable = ['supplier_document_requirement_id', 'milestone_days', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(SupplierDocumentRequirement::class, 'supplier_document_requirement_id');
    }
}
