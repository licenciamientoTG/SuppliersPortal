<?php

namespace App\Services;

use App\Models\User;

class ModuleAccessService
{
    public function rolesForModule(string $module): array
    {
        return config("module_access.modules.{$module}.roles", []);
    }

    public function moduleExists(string $module): bool
    {
        return config()->has("module_access.modules.{$module}");
    }

    public function normalizeRoleLabel(string $role): string
    {
        return config("module_access.role_aliases.{$role}", $role);
    }

    public function normalizeRoles(array $roles): array
    {
        return array_values(array_unique(array_map(
            fn (string $role) => $this->normalizeRoleLabel($role),
            $roles
        )));
    }

    public function userCanAccessModule(?User $user, string $module): bool
    {
        if (! $user) {
            return false;
        }

        // superadmin tiene acceso total a todos los módulos, sin restricciones.
        if ($user->hasRole('superadmin')) {
            return true;
        }

        if (! $this->moduleExists($module)) {
            return false;
        }

        $allowedRoles = $this->normalizeRoles($this->rolesForModule($module));

        return ! empty($allowedRoles) && $user->hasAnyRole($allowedRoles);
    }
}
