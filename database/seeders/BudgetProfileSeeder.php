<?php

namespace Database\Seeders;

use App\Models\BudgetProfile;
use App\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BudgetProfileSeeder extends Seeder
{
    public function run(): void
    {
        Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->each(function (Department $department) {
                foreach ($this->defaultProfilesFor($department) as $definition) {
                    $profile = BudgetProfile::query()->firstOrCreate(
                        ['key' => $definition['key']],
                        [
                            'name' => $definition['name'],
                            'description' => $definition['description'],
                            'is_active' => true,
                        ]
                    );

                    $department->budgetProfiles()->syncWithoutDetaching([$profile->id]);
                }
            });
    }

    private function defaultProfilesFor(Department $department): array
    {
        $slug = Str::of($department->abbreviated ?: $department->name)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->limit(55, '');

        if ($slug->isEmpty()) {
            $slug = Str::of('departamento_'.$department->id);
        }

        $slug = (string) $slug;

        return [
            [
                'key' => 'jefe_'.$slug,
                'name' => 'Jefe de '.$department->name,
                'description' => 'Perfil base para responsables del departamento '.$department->name.'.',
            ],
            [
                'key' => 'operativo_'.$slug,
                'name' => 'Operativo de '.$department->name,
                'description' => 'Perfil base para operacion del departamento '.$department->name.'.',
            ],
            [
                'key' => 'auxiliar_'.$slug,
                'name' => 'Auxiliar de '.$department->name,
                'description' => 'Perfil base para apoyo administrativo u operativo del departamento '.$department->name.'.',
            ],
        ];
    }
}
