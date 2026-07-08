<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('budget_profile_department')) {
            Schema::create('budget_profile_department', function (Blueprint $table) {
                $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
                $table->foreignId('budget_profile_id')->constrained('budget_profiles')->cascadeOnDelete();
                $table->primary(['department_id', 'budget_profile_id']);
            });
        }

        $this->copyLegacyDepartmentAccess();
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_profile_department');
    }

    private function copyLegacyDepartmentAccess(): void
    {
        if (
            ! Schema::hasTable('department_subaccount')
            || ! Schema::hasTable('budget_profiles')
            || ! Schema::hasTable('budget_profile_subaccount')
            || ! Schema::hasTable('departments')
        ) {
            return;
        }

        $now = now();

        DB::table('departments')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->each(function ($department) use ($now) {
                $subaccountIds = DB::table('department_subaccount')
                    ->where('department_id', $department->id)
                    ->pluck('subaccount_id');

                if ($subaccountIds->isEmpty()) {
                    return;
                }

                $key = 'acceso_actual_'.Str::of($department->name)
                    ->ascii()
                    ->lower()
                    ->replaceMatches('/[^a-z0-9]+/', '_')
                    ->trim('_')
                    ->limit(50, '')
                    .'_'.$department->id;

                DB::table('budget_profiles')->updateOrInsert(
                    ['key' => $key],
                    [
                        'name' => 'Acceso actual - '.$department->name,
                        'description' => 'Perfil generado para conservar subcuentas directas del departamento durante la migracion.',
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );

                $profileId = DB::table('budget_profiles')->where('key', $key)->value('id');

                DB::table('budget_profile_department')->updateOrInsert([
                    'department_id' => $department->id,
                    'budget_profile_id' => $profileId,
                ]);

                $subaccountIds->each(function ($subaccountId) use ($profileId) {
                    DB::table('budget_profile_subaccount')->updateOrInsert([
                        'budget_profile_id' => $profileId,
                        'subaccount_id' => $subaccountId,
                    ]);
                });
            });
    }
};
