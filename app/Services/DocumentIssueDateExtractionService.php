<?php

namespace App\Services;

use App\Contracts\DocumentIssueDateExtractor;
use App\Models\SupplierDocumentType;
use App\ValueObjects\DocumentIssueDateExtraction;
use Illuminate\Http\UploadedFile;
use Throwable;

class DocumentIssueDateExtractionService
{
    /** @param iterable<DocumentIssueDateExtractor> $extractors */
    public function __construct(private readonly iterable $extractors = []) {}

    public function extract(UploadedFile $file, SupplierDocumentType $type): array
    {
        foreach ($this->extractors as $extractor) {
            if (! $extractor->supports($type)) {
                continue;
            }
            try {
                $result = $extractor->extract($file, $type);
                if ($result instanceof DocumentIssueDateExtraction) {
                    return ['issued_at' => $result->issuedAt, 'metadata' => $result->metadata];
                }
            } catch (Throwable $exception) {
                return ['issued_at' => null, 'metadata' => ['status' => 'failed', 'message' => $exception->getMessage()]];
            }
        }

        return ['issued_at' => null, 'metadata' => ['status' => 'not_available']];
    }
}
