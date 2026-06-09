<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Models\Supplier;
use App\Models\User;

/**
 * Evidencia de entrega del proveedor (remisión digital).
 *
 * Cuando un proveedor entrega físicamente productos/servicios en una estación
 * u oficina, sube su remisión como comprobante antes de que la estación
 * registre la recepción formal en el sistema.
 */
class SupplierDeliveryEvidence extends Model
{
    protected $table = 'supplier_delivery_evidences';

    protected $fillable = [
        'evidenceable_type',
        'evidenceable_id',
        'file_path',
        'file_format',
        'uploaded_by',
        'uploaded_by_supplier_id',
        'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    // =========================================================================
    // RELACIONES
    // =========================================================================

    /**
     * Orden de compra asociada (PurchaseOrder o DirectPurchaseOrder)
     */
    public function evidenceable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Usuario proveedor que subió la evidencia
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function supplierUploader(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'uploaded_by_supplier_id');
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Verifica si el archivo es un PDF
     */
    public function isPdf(): bool
    {
        return strtolower($this->file_format) === 'pdf';
    }

    /**
     * Verifica si el archivo es una imagen
     */
    public function isImage(): bool
    {
        return in_array(strtolower($this->file_format), ['jpg', 'jpeg', 'png']);
    }
}
