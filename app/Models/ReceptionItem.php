<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ReceptionItem extends Model
{
    use HasFactory;

    const CONFORMITY_OK = 'CONFORME';

    const CONFORMITY_FAIL = 'NO_CONFORME';

    const NONCONFORMITY_TYPES = [
        'defective' => 'Producto defectuoso',
        'wrong_specs' => 'Especificaciones incorrectas',
        'wrong_product' => 'Producto diferente al solicitado',
        'damaged_packaging' => 'Daño en empaque/producto',
        'other' => 'Otro',
    ];

    protected $fillable = [
        'reception_id',
        'receivable_item_type',
        'receivable_item_id',
        'quantity_received',
        'conformity',
        'nonconformity_type',
        'nonconformity_notes',
        'photos',
    ];

    protected $casts = [
        'quantity_received' => 'decimal:3',
        'photos' => 'array',
    ];

    // ─── Relaciones ───────────────────────────────────────────────────────────

    public function reception(): BelongsTo
    {
        return $this->belongsTo(Reception::class);
    }

    public function receivableItem(): MorphTo
    {
        return $this->morphTo();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ReceptionAttachment::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isConforming(): bool
    {
        return $this->conformity === self::CONFORMITY_OK;
    }

    public function isNonConforming(): bool
    {
        return $this->conformity === self::CONFORMITY_FAIL;
    }

    public function getNonconformityLabel(): string
    {
        return self::NONCONFORMITY_TYPES[$this->nonconformity_type] ?? $this->nonconformity_type ?? '—';
    }

    public function hasPhotos(): bool
    {
        return ! empty($this->photos);
    }

    public function hasRejections(): bool
    {
        return $this->conformity === self::CONFORMITY_FAIL;
    }

    // Cantidad rechazada: toda la cantidad si no conforme, 0 si conforme
    public function getQuantityRejectedAttribute(): float
    {
        return $this->hasRejections() ? (float) $this->quantity_received : 0.0;
    }

    // Cantidad aceptada: toda si conforme, 0 si no conforme
    public function getQuantityAcceptedAttribute(): float
    {
        return $this->hasRejections() ? 0.0 : (float) $this->quantity_received;
    }

    // Alias legible para la razón de no conformidad
    public function getRejectionReasonAttribute(): ?string
    {
        return $this->nonconformity_notes;
    }
}
