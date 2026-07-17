<?php

namespace App\Contracts;

use App\Models\Supplier;
use App\Models\SupplierDocumentType;
use App\ValueObjects\DocumentIssueDateExtraction;
use Illuminate\Http\UploadedFile;

interface DocumentIssueDateExtractor
{
    public function supports(SupplierDocumentType $type): bool;

    public function extract(UploadedFile $file, SupplierDocumentType $type, ?Supplier $supplier = null): ?DocumentIssueDateExtraction;
}
