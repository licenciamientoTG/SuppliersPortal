<?php

namespace App\Exceptions\Rfq;

use DomainException;

class ItemsNotInRequisitionException extends DomainException
{
    public static function make(): self
    {
        return new self('Algunas partidas no pertenecen a esta requisición.');
    }
}
