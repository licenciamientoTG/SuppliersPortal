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

    public function viewPermissions(): array
    {
        return config('view_permissions.modules', []);
    }

    public function permissionForModule(string $module): ?string
    {
        return data_get($this->viewPermissions(), "{$module}.permission");
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

        $definition = $this->viewPermissions()[$module] ?? [];
        $permissions = array_filter(array_merge(
            [$definition['permission'] ?? null],
            $definition['legacy_permissions'] ?? [],
        ));

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        // El grupo padre de Catálogos se muestra si el usuario puede abrir
        // al menos una de sus vistas hijas.
        if ($module === 'catalogs_config') {
            foreach (array_keys($this->viewPermissions()) as $childModule) {
                if (str_starts_with($childModule, 'catalog_')
                    && $this->userCanAccessModule($user, $childModule)) {
                    return true;
                }
            }
        }

        // Las vistas hijas de Catálogos se controlan exclusivamente por su
        // permiso atomizado; el rol no debe reabrir todo el catálogo al
        // quitar una vista específica desde el panel.
        if (str_starts_with($module, 'catalog_')) {
            return false;
        }

        $allowedRoles = $this->normalizeRoles($this->rolesForModule($module));

        return ! empty($allowedRoles) && $user->hasAnyRole($allowedRoles);
    }
}
