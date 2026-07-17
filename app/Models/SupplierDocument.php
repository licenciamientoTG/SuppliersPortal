<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierDocument extends Model
{
    public const ALL_TYPES = [
        'constancia_fiscal',
        'comprobante_domicilio',
        'caratula_bancaria',
        'opinion_sat',
        'acta_constitutiva',
        'poder_legal',
        'identificacion_oficial',
        'opinion_imss',
        'opinion_infonavit',
        'solicitud_alta_proveedor',
        'repse',
        'acta_confidencialidad',
        'curso_induccion',
    ];

    // Conservado por compatibilidad con partes del modulo aun no migradas.
    public const REQUIRED_TYPES = self::ALL_TYPES;

    public const LARGE_TYPES = ['acta_constitutiva', 'poder_legal'];

    public const STATUS = ['pending_review', 'accepted', 'rejected'];

    protected $fillable = [
        'supplier_id',
        'uploaded_by',
        'doc_type',
        'path_file',
        'size_bytes',
        'mime_type',
        'status',
        'rejection_reason',
        'uploaded_at',
        'reviewed_by',
        'reviewed_at',
        'supplier_document_type_id',
        'supplier_document_requirement_id',
        'document_expiration_date',
        'expiration_verified_at',
        'expiration_verified_by',
        'issued_at',
        'issued_at_source',
        'issued_at_verified_at',
        'issued_at_verified_by',
        'issue_date_extraction_data',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'document_expiration_date' => 'date',
        'expiration_verified_at' => 'datetime',
        'issued_at' => 'date',
        'issued_at_verified_at' => 'datetime',
        'issue_date_extraction_data' => 'array',
    ];

    public static function requiredTypesFor(?Supplier $supplier): array
    {
        if ($supplier) {
            try {
                return SupplierDocumentType::query()
                    ->requiredForSupplier($supplier)
                    ->pluck('code')
                    ->all();
            } catch (\Throwable) {
                // Permite ejecutar instalaciones donde la migracion aun no se ha aplicado.
            }
        }
        $required = [
            'constancia_fiscal',
            'comprobante_domicilio',
            'caratula_bancaria',
            'opinion_sat',
            'identificacion_oficial',
            'solicitud_alta_proveedor',
            'acta_confidencialidad',
            'curso_induccion',
        ];

        if ($supplier?->person_type === 'moral') {
            $required[] = 'acta_constitutiva';
            $required[] = 'poder_legal';
        }

        if ($supplier?->requiresRepseRegistration()) {
            $required[] = 'repse';
        }

        return array_values(array_unique($required));
    }

    public static function maxKbFor(string $docType): int
    {
        return in_array($docType, self::LARGE_TYPES, true) ? 51200 : 10240;
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(SupplierDocumentType::class, 'supplier_document_type_id');
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(SupplierDocumentRequirement::class, 'supplier_document_requirement_id');
    }

    public function expirationVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'expiration_verified_by');
    }

    public function issueDateVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_at_verified_by');
    }

    public function hasFailedAutomaticValidation(): bool
    {
        $validation = $this->issue_date_extraction_data ?? [];

        return ($validation['rfc_matches_supplier'] ?? true) === false
            || ($validation['compliance_is_positive'] ?? true) === false;
    }
}
