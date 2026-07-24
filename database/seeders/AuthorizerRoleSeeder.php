<?php

namespace Database\Seeders;

use App\Models\AuthorizerRole;
use Illuminate\Database\Seeder;

class AuthorizerRoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Dirección General', 'approval_limit' => null, 'is_active' => true],
            ['name' => 'Dirección de Administración y Finanzas', 'approval_limit' => 144336.48, 'is_active' => true],
            ['name' => 'Dirección de Operaciones', 'approval_limit' => 144336.48, 'is_active' => true],
            ['name' => 'Gerencia de Finanzas y Normatividad', 'approval_limit' => 36084.12, 'is_active' => true],
            ['name' => 'Jefatura de Administración e Ingresos', 'approval_limit' => 13920.00, 'is_active' => true],
            ['name' => 'Gerente de Recursos Humanos', 'approval_limit' => 36084.12, 'is_active' => true],
            ['name' => 'Gerente de Operaciones', 'approval_limit' => 36084.12, 'is_active' => true],
            ['name' => 'Gerente Comercial y de MKT', 'approval_limit' => 36084.12, 'is_active' => true],
            ['name' => 'Jefatura de Abasto y Logística', 'approval_limit' => 13922.32, 'is_active' => true],
        ];

        $activeRoleNames = collect($roles)->pluck('name')->all();

        AuthorizerRole::query()
            ->whereNotIn('name', $activeRoleNames)
            ->update(['is_active' => false]);

        foreach ($roles as $role) {
            AuthorizerRole::updateOrCreate(
                ['name' => $role['name']],
                [
                    'approval_limit' => $role['approval_limit'],
                    'is_active' => $role['is_active'],
                ]
            );
        }
    }
}
