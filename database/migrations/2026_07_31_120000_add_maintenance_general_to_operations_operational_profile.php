<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('budget_profiles') || ! Schema::hasTable('budget_profile_subaccount')) {
            return;
        }

        $profileId = DB::table('budget_profiles')
            ->where('key', 'operativo_ope')
            ->value('id');
        $subaccountId = DB::table('subaccounts')
            ->where('code', '6200.093')
            ->value('id');

        if (! $profileId || ! $subaccountId) {
            return;
        }

        DB::table('budget_profile_subaccount')->updateOrInsert([
            'budget_profile_id' => $profileId,
            'subaccount_id' => $subaccountId,
        ]);
    }

    public function down(): void
    {
        $profileId = DB::table('budget_profiles')
            ->where('key', 'operativo_ope')
            ->value('id');
        $subaccountId = DB::table('subaccounts')
            ->where('code', '6200.093')
            ->value('id');

        if ($profileId && $subaccountId) {
            DB::table('budget_profile_subaccount')
                ->where('budget_profile_id', $profileId)
                ->where('subaccount_id', $subaccountId)
                ->delete();
        }
    }
};
