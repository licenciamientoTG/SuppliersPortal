<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DirectPurchaseOrderDocument extends Model
{
    use HasFactory;

    protected $table = 'odc_direct_purchase_order_documents';

    protected $fillable = [
        'direct_purchase_order_id',
        'document_type',
        'file_path',
        'original_filename',
        'uploaded_by',
    ];

    /**
     * =========================================
     * RELACIONES
     * =========================================
     */
    public function directPurchaseOrder(): BelongsTo
    {
        return $this->belongsTo(DirectPurchaseOrder::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * =========================================
     * MÉTODOS DE NEGOCIO
     * =========================================
     */

    /**
     * Verifica si el documento es una cotización
     */
    public function isQuotation(): bool
    {
        return $this->document_type === 'quotation';
    }

    /**
     * Verifica si el documento es de soporte
     */
    public function isSupportDocument(): bool
    {
        return $this->document_type === 'support_document';
    }

    /**
     * Verifica si el documento es evidencia de recepción
     */
    public function isReceptionEvidence(): bool
    {
        return $this->document_type === 'reception_evidence';
    }

    /**
     * Obtiene la URL completa del archivo
     */
    public function getFileUrl(): string
    {
        return route('direct-purchase-orders.documents.show', [$this->direct_purchase_order_id, $this->id]);
    }

    /**
     * Obtiene el tamaño del archivo en formato legible
     */
    public function getFileSize(): string
    {
        $bytes = Storage::disk('local')->size($this->file_path);
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    /**
     * Obtiene la extensión del archivo
     */
    public function getFileExtension(): string
    {
        return pathinfo($this->original_filename, PATHINFO_EXTENSION);
    }

    /**
     * Obtiene el ícono según el tipo de archivo
     */
    public function getFileIcon(): string
    {
        $extension = strtolower($this->getFileExtension());

        return match ($extension) {
            'pdf' => 'file-text',
            'jpg', 'jpeg', 'png', 'gif' => 'image',
            'doc', 'docx' => 'file-text',
            'xls', 'xlsx' => 'file-spreadsheet',
            default => 'file'
        };
    }

    /**
     * Obtiene el badge de color según el tipo de documento
     */
    public function getDocumentTypeBadgeClass(): string
    {
        return match ($this->document_type) {
            'quotation' => 'primary',
            'support_document' => 'info',
            'reception_evidence' => 'success',
            default => 'secondary'
        };
    }

    /**
     * Obtiene el texto legible del tipo de documento
     */
    public function getDocumentTypeLabel(): string
    {
        return match ($this->document_type) {
            'quotation' => 'Cotización',
            'support_document' => 'Documento de Soporte',
            'reception_evidence' => 'Evidencia de Recepción',
            default => 'Desconocido'
        };
    }

    /**
     * =========================================
     * SCOPES
     * =========================================
     */

    /**
     * Scope para filtrar por tipo de documento
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('document_type', $type);
    }

    /**
     * Scope para documentos de cotización
     */
    public function scopeQuotations($query)
    {
        return $query->where('document_type', 'quotation');
    }

    /**
     * =========================================
     * EVENTOS DEL MODELO
     * =========================================
     */
    protected static function booted(): void
    {
        // Al eliminar un documento, también eliminar el archivo físico
        static::deleting(function (DirectPurchaseOrderDocument $document) {
            if (Storage::disk('local')->exists($document->file_path)) {
                Storage::disk('local')->delete($document->file_path);
            }
        });
    }
}
