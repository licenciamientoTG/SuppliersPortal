<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class AlertRecipientService
{
    /**
     * Obtiene los emails de usuarios con roles específicos cacheados.
     *
     * @param string|array $roles Rol o array de roles a consultar
     * @param int $ttl Tiempo de vida del caché en segundos (default: 1 hora)
     * @return array Emails únicos de los usuarios
     */
    public static function getByRoles(string|array $roles, int $ttl = 3600): array
    {
        $roles = is_array($roles) ? $roles : [$roles];
        $cacheKey = 'alert_recipients_' . implode('_', $roles);

        return Cache::remember($cacheKey, $ttl, function () use ($roles) {
            return User::role($roles)
                ->pluck('email')
                ->filter()
                ->values()
                ->toArray();
        });
    }

    /**
     * Obtiene emails de buyers (Departamento de Compras).
     */
    public static function getBuyers(int $ttl = 3600): array
    {
        return self::getByRoles('buyer', $ttl);
    }

    /**
     * Obtiene emails de receptores (rol receiver).
     */
    public static function getReceivers(int $ttl = 3600): array
    {
        return self::getByRoles('receiver', $ttl);
    }

    /**
     * Obtiene emails de superadmins (Finanzas / Dirección).
     */
    public static function getSuperadmins(int $ttl = 3600): array
    {
        return self::getByRoles('superadmin', $ttl);
    }

    /**
     * Invalida el caché de destinatarios cuando se actualizan roles.
     */
    public static function invalidateCache(): void
    {
        Cache::forget('alert_recipients_receiver');
        Cache::forget('alert_recipients_buyer');
        Cache::forget('alert_recipients_superadmin');
        Cache::forget('alert_recipients_buyer_superadmin');
    }
}
