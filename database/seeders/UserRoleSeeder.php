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
        $productionEmail = 'licenciamiento@totalgas.com';
        $legacySuperAdminEmail = 'tgsuperadmin@yopmail.com';

        if (Role::count() === 0) {
            $this->command->error('No hay roles en la base de datos. Ejecuta primero RolePermissionSeeder.');
            return;
        }

        $superAdmin = User::withTrashed()
            ->whereIn('email', [$productionEmail, $legacySuperAdminEmail])
            ->orderByRaw("CASE WHEN email = ? THEN 0 ELSE 1 END", [$productionEmail])
            ->first() ?? new User();

        if ($superAdmin->exists && $superAdmin->trashed()) {
            $superAdmin->restore();
        }

        $superAdmin->fill([
            'name' => 'Super Administrador',
            'email' => $productionEmail,
            'password' => $password,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $superAdmin->save();

        try {
            $superAdmin->syncRoles(['superadmin']);
        } catch (\Throwable $e) {
            $this->command->warn("No se pudo asignar el rol superadmin a {$productionEmail}.");
        }

        $demoEmails = [
            $legacySuperAdminEmail,
            'tgcompras@yopmail.com',
            'tgcontabilidad@yopmail.com',
            'tgproveedor@yopmail.com',
            'tgautorizador@yopmail.com',
            'tgjefedepartamento@yopmail.com',
            'tgstaff@yopmail.com',
            'tgreceptor@yopmail.com',
            'tgdirector@yopmail.com',
            'tgcatalogo@yopmail.com',
        ];

        foreach (range(1, 20) as $index) {
            $demoEmails[] = sprintf('tgproveedor%02d@yopmail.com', $index);
        }

        User::withTrashed()
            ->whereIn('email', $demoEmails)
            ->whereKeyNot($superAdmin->getKey())
            ->get()
            ->each(function (User $user) {
                $user->syncRoles([]);

                if (! $user->trashed()) {
                    $user->delete();
                }
            });

        $this->command->info("Seeder de usuarios ejecutado. Usuario inicial configurado: {$productionEmail}.");
    }
}
