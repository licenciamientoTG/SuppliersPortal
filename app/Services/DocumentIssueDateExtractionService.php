<?php

namespace App\Services;

use App\Contracts\DocumentIssueDateExtractor;
use App\Models\Supplier;
use App\Models\SupplierDocumentType;
use App\ValueObjects\DocumentIssueDateExtraction;
use Illuminate\Http\UploadedFile;
use Throwable;

class DocumentIssueDateExtractionService
{
    /** @param iterable<DocumentIssueDateExtractor> $extractors */
    public function __construct(private readonly iterable $extractors = []) {}

    public function extract(UploadedFile $file, SupplierDocumentType $type, ?Supplier $supplier = null): array
    {
        foreach ($this->extractors as $extractor) {
            if (! $extractor->supports($type)) {
                continue;
            }
            try {
                $result = $extractor->extract($file, $type, $supplier);
                if ($result instanceof DocumentIssueDateExtraction) {
                    $metadata = $result->metadata;
                    $detectedRfc = strtoupper((string) ($metadata['rfc'] ?? ''));
                    $expectedRfc = strtoupper((string) ($supplier?->rfc ?? ''));
                    if ($detectedRfc !== '' && $expectedRfc !== '') {
                        $metadata['rfc_matches_supplier'] = hash_equals($expectedRfc, $detectedRfc);
                    }
                    if (isset($metadata['compliance_status'])) {
                        $metadata['compliance_is_positive'] = $metadata['compliance_status'] === 'POSITIVA';
                    }

                    return ['issued_at' => $result->issuedAt, 'metadata' => $metadata];
                }
            } catch (Throwable $exception) {
                return ['issued_at' => null, 'metadata' => ['status' => 'failed', 'message' => $exception->getMessage()]];
            }
        }

        return ['issued_at' => null, 'metadata' => ['status' => 'not_available']];
    }
}
