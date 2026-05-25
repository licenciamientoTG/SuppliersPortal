<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserRoleSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('Fl3x.2025');

        if (Role::count() === 0) {
            $this->command->error('No hay roles en la base de datos. Ejecuta primero RolePermissionSeeder.');
            return;
        }

        $initialUsers = [
            ['name' => 'Super Administrador', 'email' => 'tgsuperadmin@yopmail.com', 'role' => 'superadmin'],
            ['name' => 'Compras User', 'email' => 'tgcompras@yopmail.com', 'role' => 'buyer'],
            ['name' => 'Contabilidad User', 'email' => 'tgcontabilidad@yopmail.com', 'role' => 'accounting'],
            ['name' => 'Proveedor User', 'email' => 'tgproveedor@yopmail.com', 'role' => 'supplier'],
            ['name' => 'Autorizador User', 'email' => 'tgautorizador@yopmail.com', 'role' => 'authorizer'],
            ['name' => 'Jefe de Departamento', 'email' => 'tgjefedepartamento@yopmail.com', 'role' => 'department_head'],
            ['name' => 'Staff User', 'email' => 'tgstaff@yopmail.com', 'role' => 'staff'],
            ['name' => 'Receptor User', 'email' => 'tgreceptor@yopmail.com', 'role' => 'receiver'],
            ['name' => 'Director General', 'email' => 'tgdirector@yopmail.com', 'role' => 'general_director'],
            ['name' => 'Admin Catalogo', 'email' => 'tgcatalogo@yopmail.com', 'role' => 'catalog_admin'],
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

            try {
                $user->syncRoles([$data['role']]);
            } catch (\Throwable $e) {
                $this->command->warn("No se pudo asignar el rol {$data['role']} a {$data['email']}.");
            }
        }

        foreach (range(1, 20) as $index) {
            $email = sprintf('tgproveedor%02d@yopmail.com', $index);

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => sprintf('Proveedor Seed %02d', $index),
                    'password' => $password,
                    'email_verified_at' => now(),
                ]
            );

            try {
                $user->syncRoles(['supplier']);
            } catch (\Throwable $e) {
                $this->command->warn("No se pudo asignar el rol supplier a {$email}.");
            }
        }
    }
}
