<?php

namespace App\Exceptions\Rfq;

use DomainException;

class RfqAlreadyRejectedException extends DomainException
{
    public static function make(): self
    {
        return new self('Esta RFQ ya fue rechazada en autorización. Usa la opción de re-adjudicar para crear una nueva vuelta.');
    }
}
