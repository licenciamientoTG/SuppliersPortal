<?php

namespace App\Enum;

enum ContractStatus: string
{
    case ACTIVE    = 'active';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::ACTIVE    => 'Activo',
            self::CANCELLED => 'Cancelado',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::ACTIVE    => 'success',
            self::CANCELLED => 'secondary',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn($c) => [$c->value => $c->label()])
            ->toArray();
    }
}
