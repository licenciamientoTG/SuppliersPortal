<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CfdiValidationLog extends Model
{
    public const RESULT_ACCEPTED = 'ACEPTADO';

    public const RESULT_REJECTED = 'RECHAZADO';

    public const CHANNEL_PORTAL = 'PORTAL_PROVEEDOR';

    public const CHANNEL_INTERNAL = 'INTERNO';

    protected $fillable = [
        'attempted_at',
        'uuid',
        'issuer_rfc',
        'issuer_name',
        'receiver_rfc',
        'company_id',
        'cfdi_type',
        'total',
        'currency',
        'result',
        'rejection_code',
        'channel',
        'supplier_id',
        'user_id',
        'supplier_invoice_id',
        'xml_sha256',
    ];

    protected $casts = [
        'attempted_at' => 'datetime',
        'total' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    public function rejectionCode(): BelongsTo
    {
        return $this->belongsTo(CfdiRejectionCode::class, 'rejection_code', 'code');
    }
}
