<?php

namespace App\Enum;

enum ContractType: string
{
    case IGUALA   = 'iguala';
    case CONVENIO = 'convenio';

    public function label(): string
    {
        return match ($this) {
            self::IGUALA   => 'Iguala',
            self::CONVENIO => 'Convenio de precios',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::IGUALA   => 'Monto fijo ya comprometido y autorizado al contratar. Las compras contra este contrato se emiten directo, sin autorización adicional.',
            self::CONVENIO => 'Solo pacta precios con el proveedor; no compromete gasto. Cada compra contra este contrato requiere autorización según la matriz.',
        };
    }
}
