<?php

namespace App\Exceptions\Rfq;

use App\Models\Supplier;
use DomainException;

class DuplicateSupplierRfcException extends DomainException
{
    public function __construct(public readonly Supplier $existingSupplier)
    {
        parent::__construct(
            "Ya existe un proveedor con este RFC: {$existingSupplier->company_name}. Selecciónalo de la lista en vez de crear uno nuevo."
        );
    }
}
