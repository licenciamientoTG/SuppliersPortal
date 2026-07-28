<?php

namespace App\Exceptions\Rfq;

use DomainException;

class AwardNotAllowedException extends DomainException
{
    /** @param string[] $reasons */
    public function __construct(string $message, public readonly array $reasons = [])
    {
        parent::__construct($message);
    }
}
