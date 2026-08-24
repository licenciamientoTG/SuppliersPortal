<?php

namespace Database\Seeders;

use App\Models\LedgerAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LedgerAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = $this->sourceAccounts();

        DB::transaction(function () use ($accounts): void {
            foreach ($accounts as $account) {
                LedgerAccount::updateOrCreate(
                    ['one_goal_id' => (int) $account['one_goal_id']],
                    [
                        'one_goal_parent_id' => (int) $account['one_goal_parent_id'],
                        'code' => $account['code'] ?: null,
                        'name' => $account['name'] ?: 'SIN ASIGNAR',
                        'alternate_name' => $account['alternate_name'] ?: null,
                        'nature' => (int) $account['nature'],
                        'account_level' => (int) $account['account_level'],
                        'one_goal_type_id' => (int) $account['one_goal_type_id'],
                        'one_goal_external_system_id' => $account['one_goal_external_system_id'] ?: null,
                        'is_active' => (bool) $account['is_active'],
                        'is_selectable' => (int) $account['one_goal_id'] !== 0,
                    ],
                );
            }

            $idsBySource = LedgerAccount::query()->pluck('id', 'one_goal_id');

            foreach ($accounts as $account) {
                LedgerAccount::query()
                    ->where('one_goal_id', (int) $account['one_goal_id'])
                    ->update([
                        'parent_id' => (int) $account['one_goal_parent_id'] === 0
                            ? null
                            : $idsBySource[(int) $account['one_goal_parent_id']] ?? null,
                    ]);
            }
        });
    }

    /** @return array<int, array<string, mixed>> */
    private function sourceAccounts(): array
    {
        $accounts = json_decode(
            file_get_contents(database_path('data/one_goal_ledger_accounts.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        return $accounts;
    }
}
