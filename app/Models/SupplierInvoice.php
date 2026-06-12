<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SupplierInvoice extends Model
{
    use HasFactory;

    public const STATUS_UPLOADED = 'UPLOADED';
    public const STATUS_LINKED = 'LINKED';
    public const STATUS_REJECTED = 'REJECTED';

    public const ORIGIN_SUPPLIER = 'supplier';
    public const ORIGIN_FINANCE = 'finance';

    protected $fillable = [
        'supplier_id',
        'financial_provision_id',
        'receivable_type',
        'receivable_id',
        'uuid',
        'xml_path',
        'pdf_path',
        'issuer_rfc',
        'receiver_rfc',
        'subtotal',
        'iva_amount',
        'total',
        'currency',
        'issued_at',
        'uploaded_by',
        'uploaded_by_supplier_id',
        'uploaded_origin',
        'status',
        'linked_at',
        'rejected_at',
        'rejection_reason',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'iva_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'issued_at' => 'datetime',
        'linked_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function financialProvision(): BelongsTo
    {
        return $this->belongsTo(FinancialProvision::class);
    }

    public function receivable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function supplierUploader(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'uploaded_by_supplier_id');
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_UPLOADED => 'Cargada',
            self::STATUS_LINKED => 'Vinculada',
            self::STATUS_REJECTED => 'Rechazada',
            default => 'Desconocido',
        };
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_UPLOADED => 'warning',
            self::STATUS_LINKED => 'success',
            self::STATUS_REJECTED => 'danger',
            default => 'secondary',
        };
    }
}
