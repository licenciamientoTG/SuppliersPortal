<?php

namespace App\Services;

use RuntimeException;

/**
 * Error de estructura del CFDI (RF-01): carga el código de rechazo estable y los datos
 * parciales que ya se lograron leer antes de fallar (uuid/issuer_rfc/receiver_rfc), para
 * que la bitácora no quede en blanco cuando el XML sí tenía algo legible.
 */
class CfdiParseException extends RuntimeException
{
    public function __construct(string $message, private readonly string $rejectionCode, private readonly array $partialData = [])
    {
        parent::__construct($message);
    }

    public function rejectionCode(): string
    {
        return $this->rejectionCode;
    }

    public function partialData(): array
    {
        return $this->partialData;
    }
}
