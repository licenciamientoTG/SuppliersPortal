<?php

namespace App\Services;

use App\Models\User;

class ModuleAccessService
{
    public function rolesForModule(string $module): array
    {
        $roles = config("module_access.modules.{$module}.roles", []);

        if (empty($roles)) {
            $parent = data_get($this->viewPermissions(), "{$module}.parent");
            $parent ??= str_starts_with($module, 'catalog_') ? 'catalogs_config' : null;
            $roles = $parent ? $this->rolesForModule($parent) : [];
        }

        return $roles;
    }

    public function moduleExists(string $module): bool
    {
        return config()->has("module_access.modules.{$module}")
            || array_key_exists($module, $this->viewPermissions());
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

        $hasChildren = collect($this->viewPermissions())
            ->contains(fn (array $child) => ($child['parent'] ?? null) === $module)
            || ($module === 'catalogs_config' && collect(array_keys($this->viewPermissions()))
                ->contains(fn (string $child) => str_starts_with($child, 'catalog_')));

        // Una vista hija sólo responde a su permiso atomizado. Los roles se
        // sincronizan mediante el seeder; no se usa el rol padre como bypass.
        if (! empty($definition['parent']) || str_starts_with($module, 'catalog_')) {
            return $user->can($definition['permission'] ?? '');
        }

        $permissions = $hasChildren ? [] : array_filter(array_merge(
            [$definition['permission'] ?? null],
            $definition['legacy_permissions'] ?? [],
        ));

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        // Un módulo contenedor queda visible si existe acceso a cualquiera
        // de sus vistas atomizadas.
        foreach ($this->viewPermissions() as $childModule => $childDefinition) {
            if ((($childDefinition['parent'] ?? null) === $module
                    || ($module === 'catalogs_config' && str_starts_with($childModule, 'catalog_')))
                && $this->userCanAccessModule($user, $childModule)) {
                return true;
            }
        }

        if ($hasChildren) {
            return false;
        }

        // Las vistas hijas de Catálogos se controlan exclusivamente por su
        // permiso atomizado; el rol no debe reabrir todo el catálogo al
        // quitar una vista específica desde el panel.
        $allowedRoles = $this->normalizeRoles($this->rolesForModule($module));

        return ! empty($allowedRoles) && $user->hasAnyRole($allowedRoles);
    }
}
