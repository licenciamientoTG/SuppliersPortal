<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceptionAttachment extends Model
{
    public const TYPE_REMISSION = 'REMISSION';

    public const TYPE_PHOTO = 'PHOTO';

    public const HASH_ORIGIN_EN_CARGA = 'EN_CARGA';

    public const HASH_ORIGIN_BACKFILL = 'BACKFILL';

    protected $fillable = [
        'reception_id',
        'attachable_type',
        'reception_item_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'sha256',
        'hash_origin',
        'uploaded_by',
        'uploaded_at',
    ];

    protected $casts = [
        'size' => 'integer',
        'uploaded_at' => 'datetime',
    ];

    public function reception(): BelongsTo
    {
        return $this->belongsTo(Reception::class);
    }

    public function receptionItem(): BelongsTo
    {
        return $this->belongsTo(ReceptionItem::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isRemission(): bool
    {
        return $this->attachable_type === self::TYPE_REMISSION;
    }

    public function isPhoto(): bool
    {
        return $this->attachable_type === self::TYPE_PHOTO;
    }
}
