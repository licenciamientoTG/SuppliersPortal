<?php

namespace App\ValueObjects;

use Carbon\CarbonInterface;

readonly class DocumentIssueDateExtraction
{
    public function __construct(
        public CarbonInterface $issuedAt,
        public array $metadata = [],
    ) {}
}
