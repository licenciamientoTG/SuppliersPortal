<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AccountSubaccountSyncService
{
    public function syncFromLegacy(): void
    {
        if (! Schema::hasTable('accounts') || ! Schema::hasTable('subaccounts')) {
            return;
        }

        if (! Schema::hasTable('expense_categories') || ! Schema::hasTable('budget_cedulas')) {
            return;
        }

        $now = now();

        DB::table('expense_categories')->orderBy('id')->get()->each(function ($row) use ($now) {
            DB::table('accounts')->updateOrInsert(
                ['legacy_expense_category_id' => $row->id],
                [
                    'code' => $row->code,
                    'name' => $row->name,
                    'description' => $row->description,
                    'account_category' => null,
                    'is_fixed_asset' => false,
                    'is_active' => ($row->status ?? 'ACTIVO') === 'ACTIVO',
                    'created_by' => $row->created_by ?? null,
                    'updated_by' => $row->updated_by ?? null,
                    'deleted_by' => $row->deleted_by ?? null,
                    'deleted_at' => $row->deleted_at ?? null,
                    'created_at' => $row->created_at ?? $now,
                    'updated_at' => $row->updated_at ?? $now,
                ]
            );
        });

        DB::table('budget_cedulas')->orderBy('id')->get()->each(function ($row) use ($now) {
            $accountId = DB::table('accounts')
                ->where('legacy_expense_category_id', $row->expense_category_id)
                ->value('id');

            if (! $accountId) {
                return;
            }

            DB::table('subaccounts')->updateOrInsert(
                ['legacy_budget_cedula_id' => $row->id],
                [
                    'account_id' => $accountId,
                    'name' => $row->name,
                    'subaccount_category' => null,
                    'is_fixed_asset' => false,
                    'is_active' => ($row->status ?? 'ACTIVO') === 'ACTIVO',
                    'created_by' => $row->created_by ?? null,
                    'updated_by' => $row->updated_by ?? null,
                    'deleted_by' => $row->deleted_by ?? null,
                    'deleted_at' => $row->deleted_at ?? null,
                    'created_at' => $row->created_at ?? $now,
                    'updated_at' => $row->updated_at ?? $now,
                ]
            );
        });

        if (Schema::hasTable('expense_category_product_service')) {
            DB::table('expense_category_product_service')->get()->each(function ($row) {
                $accountId = DB::table('accounts')
                    ->where('legacy_expense_category_id', $row->expense_category_id)
                    ->value('id');

                if (! $accountId) {
                    return;
                }

                DB::table('account_product_service')->updateOrInsert([
                    'account_id' => $accountId,
                    'product_service_id' => $row->product_service_id,
                ]);
            });
        }

        if (Schema::hasTable('budget_cedula_product_service')) {
            DB::table('budget_cedula_product_service')->get()->each(function ($row) {
                $subaccountId = DB::table('subaccounts')
                    ->where('legacy_budget_cedula_id', $row->budget_cedula_id)
                    ->value('id');

                if (! $subaccountId) {
                    return;
                }

                DB::table('product_service_subaccount')->updateOrInsert([
                    'product_service_id' => $row->product_service_id,
                    'subaccount_id' => $subaccountId,
                ]);
            });
        }
    }
}
