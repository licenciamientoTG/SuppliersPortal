<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subaccounts', function (Blueprint $table) {
            if (! Schema::hasColumn('subaccounts', 'code')) {
                $table->string('code', 50)->nullable()->after('account_id');
            }
        });

        $usedAccountCodes = [];

        DB::table('accounts')
            ->leftJoin('expense_categories', 'accounts.legacy_expense_category_id', '=', 'expense_categories.id')
            ->select('accounts.id', 'accounts.code', 'accounts.name', 'expense_categories.name as legacy_name')
            ->orderBy('accounts.id')
            ->get()
            ->each(function ($account) use (&$usedAccountCodes) {
                $currentCode = trim((string) $account->code);

                if ($currentCode !== '' && preg_match('/^\d{4,}$/', $currentCode)) {
                    $usedAccountCodes[$currentCode] = true;
                    return;
                }

                $code = $this->uniqueAccountCode(
                    $this->accountCodeFor($account->legacy_name ?: $account->name, (int) $account->id),
                    $usedAccountCodes
                );

                DB::table('accounts')
                    ->where('id', $account->id)
                    ->update([
                        'code' => $code,
                        'updated_at' => now(),
                    ]);

                $usedAccountCodes[$code] = true;
            });

        DB::table('subaccounts')
            ->join('accounts', 'subaccounts.account_id', '=', 'accounts.id')
            ->select('subaccounts.id', 'subaccounts.code', 'subaccounts.legacy_budget_cedula_id', 'accounts.code as account_code')
            ->orderBy('subaccounts.id')
            ->get()
            ->each(function ($subaccount) {
                $currentCode = trim((string) $subaccount->code);

                if ($currentCode !== '') {
                    return;
                }

                $suffix = ((int) ($subaccount->legacy_budget_cedula_id ?: $subaccount->id) % 900) + 1;

                DB::table('subaccounts')
                    ->where('id', $subaccount->id)
                    ->update([
                        'code' => sprintf('%s.%03d', $subaccount->account_code, $suffix),
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('subaccounts', function (Blueprint $table) {
            if (Schema::hasColumn('subaccounts', 'code')) {
                $table->dropColumn('code');
            }
        });
    }

    private function accountCodeFor(?string $name, int $id): string
    {
        $normalized = mb_strtolower((string) $name);

        return match (true) {
            str_contains($normalized, 'ingreso') => '4000',
            str_contains($normalized, 'costo de venta') => '5000',
            str_contains($normalized, 'nomina'), str_contains($normalized, 'social') => '6000',
            str_contains($normalized, 'mantenimiento') => '6200',
            str_contains($normalized, 'gasto') => '6100',
            str_contains($normalized, 'depreci') || str_contains($normalized, 'amort') => '6800',
            str_contains($normalized, 'proyecto') || str_contains($normalized, 'extraordinaria') => '7000',
            default => (string) (6900 + ($id % 90)),
        };
    }

    private function uniqueAccountCode(string $preferredCode, array $usedCodes): string
    {
        $code = (int) $preferredCode;

        while (isset($usedCodes[(string) $code])) {
            $code++;
        }

        return (string) $code;
    }
};
