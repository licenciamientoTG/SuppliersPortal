<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Spatie\Permission\Models\Role;

class UserRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = Hash::make('Fl3x.2025');

        // 1. Asegúrate de que los roles existan ANTES de empezar
        // Si no existen, este seeder no debe seguir. ¡Disciplina!
        if (Role::count() === 0) {
            $this->command->error("¡ERROR DE COMBATE! No hay roles en la base de datos. Ejecuta primero RolePermissionSeeder.");
            return;
        }

        $initialUsers = [
            ['name' => 'Super Administrador',   'email' => 'tgsuperadmin@yopmail.com',       'role' => 'superadmin'],
            ['name' => 'Compras User',          'email' => 'tgcompras@yopmail.com',           'role' => 'buyer'],
            ['name' => 'Contabilidad User',     'email' => 'tgcontabilidad@yopmail.com',      'role' => 'accounting'],
            ['name' => 'Proveedor User',        'email' => 'tgproveedor@yopmail.com',         'role' => 'supplier'],
            ['name' => 'Autorizador User',      'email' => 'tgautorizador@yopmail.com',       'role' => 'authorizer'],
            ['name' => 'Jefe de Departamento',  'email' => 'tgjefedepartamento@yopmail.com',  'role' => 'department_head'],
            ['name' => 'Staff User',            'email' => 'tgstaff@yopmail.com',             'role' => 'staff'],
            ['name' => 'Receptor User',         'email' => 'tgreceptor@yopmail.com',          'role' => 'receiver'],
            ['name' => 'Director General',      'email' => 'tgdirector@yopmail.com',          'role' => 'general_director'],
            ['name' => 'Admin Catálogo',        'email' => 'tgcatalogo@yopmail.com',          'role' => 'catalog_admin'],
        ];

        foreach ($initialUsers as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => $password,
                    'email_verified_at' => now(),
                ]
            );

            // Usamos assignRole directamente. 
            // Si el rol no existe, Spatie lanzará una excepción y sabrás EXACTAMENTE qué falló.
            try {
                $user->syncRoles([$data['role']]);
            } catch (\Exception $e) {
                $this->command->warn("No se pudo asignar el rol {$data['role']} a {$data['email']}. ¿Seguro que existe?");
            }
        }

        // 2. Factory para los proveedores de relleno
        User::factory(20)->create(['password' => $password])->each(function ($user) {
            $user->assignRole('supplier');
        });
    }
}
