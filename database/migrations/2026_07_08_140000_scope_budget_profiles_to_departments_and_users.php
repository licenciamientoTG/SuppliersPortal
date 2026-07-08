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
        Schema::table('budget_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('budget_profiles', 'department_id')) {
                $table->foreignId('department_id')->nullable()->after('id')
                    ->constrained('departments')->nullOnDelete();
                $table->index(['department_id', 'is_active']);
            }
        });

        if (! Schema::hasTable('budget_profile_user')) {
            Schema::create('budget_profile_user', function (Blueprint $table) {
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('budget_profile_id')->constrained('budget_profiles')->cascadeOnDelete();
                $table->primary(['user_id', 'budget_profile_id']);
            });
        }

        $this->moveDepartmentProfilePivotIntoProfileOwner();
        $this->copyLegacyUserProfiles();
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_profile_user');

        Schema::table('budget_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('budget_profiles', 'department_id')) {
                $table->dropForeign(['department_id']);
                $table->dropIndex(['department_id', 'is_active']);
                $table->dropColumn('department_id');
            }
        });
    }

    private function moveDepartmentProfilePivotIntoProfileOwner(): void
    {
        if (! Schema::hasTable('budget_profile_department')) {
            return;
        }

        $now = now();

        DB::table('budget_profile_department')
            ->orderBy('budget_profile_id')
            ->orderBy('department_id')
            ->get()
            ->groupBy('budget_profile_id')
            ->each(function ($rows, $profileId) use ($now) {
                $profile = DB::table('budget_profiles')->where('id', $profileId)->first();

                if (! $profile) {
                    return;
                }

                $rows->values()->each(function ($row, $index) use ($profile, $now) {
                    if ((int) $profile->department_id === (int) $row->department_id) {
                        return;
                    }

                    if ($index === 0 && ! $profile->department_id) {
                        DB::table('budget_profiles')
                            ->where('id', $profile->id)
                            ->update([
                                'department_id' => $row->department_id,
                                'updated_at' => $now,
                            ]);

                        return;
                    }

                    $department = DB::table('departments')->where('id', $row->department_id)->first();
                    $suffix = Str::of($department?->abbreviated ?: 'dep_'.$row->department_id)
                        ->ascii()
                        ->lower()
                        ->replaceMatches('/[^a-z0-9]+/', '_')
                        ->trim('_');

                    $newKey = Str::limit($profile->key.'_'.$suffix, 80, '');

                    DB::table('budget_profiles')->updateOrInsert(
                        ['key' => $newKey],
                        [
                            'department_id' => $row->department_id,
                            'name' => $profile->name,
                            'description' => $profile->description,
                            'is_active' => $profile->is_active,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );

                    $newProfileId = DB::table('budget_profiles')->where('key', $newKey)->value('id');

                    DB::table('budget_profile_subaccount')
                        ->where('budget_profile_id', $profile->id)
                        ->pluck('subaccount_id')
                        ->each(function ($subaccountId) use ($newProfileId) {
                            DB::table('budget_profile_subaccount')->updateOrInsert([
                                'budget_profile_id' => $newProfileId,
                                'subaccount_id' => $subaccountId,
                            ]);
                        });
                });
            });
    }

    private function copyLegacyUserProfiles(): void
    {
        if (! Schema::hasColumn('users', 'budget_profile_id')) {
            return;
        }

        DB::table('users')
            ->whereNotNull('budget_profile_id')
            ->whereNotNull('department_id')
            ->orderBy('id')
            ->get(['id', 'department_id', 'budget_profile_id'])
            ->each(function ($user) {
                $profile = DB::table('budget_profiles')
                    ->where('id', $user->budget_profile_id)
                    ->where('department_id', $user->department_id)
                    ->first();

                if (! $profile) {
                    return;
                }

                DB::table('budget_profile_user')->updateOrInsert([
                    'user_id' => $user->id,
                    'budget_profile_id' => $profile->id,
                ]);
            });
    }
};
