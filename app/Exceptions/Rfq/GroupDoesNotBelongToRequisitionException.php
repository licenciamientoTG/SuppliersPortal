<?php

namespace App\Exceptions\Rfq;

use DomainException;

class GroupDoesNotBelongToRequisitionException extends DomainException
{
    public static function make(): self
    {
        return new self('Grupo inválido.');
    }
}
