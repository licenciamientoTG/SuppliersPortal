<?php

namespace Database\Seeders;

use App\Models\LedgerAccount;
use App\Models\TaxCode;
use App\Models\TaxGroup;
use App\Models\TaxGroupItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TaxGroupSeeder extends Seeder
{
    public function run(): void
    {
        $rows = $this->sourceRows();

        DB::transaction(function () use ($rows): void {
            foreach ($rows as $row) {
                TaxGroup::updateOrCreate(
                    ['one_goal_id' => (int) $row['one_goal_id']],
                    [
                        'name' => $row['name'] ?: 'SIN ASIGNAR',
                        'one_goal_type_id' => (int) $row['one_goal_type_id'],
                        'one_goal_compound_id' => (int) $row['one_goal_compound_id'],
                        'is_payment_tax' => (bool) $row['is_payment_tax'],
                        'is_border_zone' => (bool) $row['is_border_zone'],
                        'is_vat_tax' => (bool) $row['is_vat_tax'],
                        'sat_tax_object' => $row['sat_tax_object'] ?: null,
                        'is_south_border_zone' => (bool) $row['is_south_border_zone'],
                        'is_active' => (bool) $row['is_active'],
                    ],
                );
            }

            $groups = TaxGroup::query()->pluck('id', 'one_goal_id');
            $taxCodes = TaxCode::query()->pluck('id', 'one_goal_id');
            $accounts = LedgerAccount::query()->pluck('id', 'one_goal_id');

            foreach ($rows as $row) {
                if ($row['item_one_goal_id'] === null) {
                    continue;
                }

                TaxGroupItem::updateOrCreate(
                    ['one_goal_id' => (int) $row['item_one_goal_id']],
                    [
                        'tax_group_id' => $groups[(int) $row['one_goal_id']],
                        'tax_code_id' => $taxCodes[(int) $row['tax_code_one_goal_id']] ?? null,
                        'ledger_account_id' => $accounts[(int) $row['ledger_account_one_goal_id']] ?? null,
                        'one_goal_tax_code_id' => (int) $row['tax_code_one_goal_id'],
                        'one_goal_ledger_account_id' => (int) $row['ledger_account_one_goal_id'],
                        'is_iva_base' => (bool) $row['is_iva_base'],
                        'related_iva_item_one_goal_id' => (int) $row['related_iva_item_one_goal_id'],
                        'withholding_type_id' => (int) $row['withholding_type_id'],
                        'is_excluded_from_cfdi' => (bool) $row['is_excluded_from_cfdi'],
                        'sat_tax_object' => $row['item_sat_tax_object'] ?: null,
                    ],
                );
            }
        });
    }

    /** @return array<int, array<string, mixed>> */
    private function sourceRows(): array
    {
        return json_decode(
            file_get_contents(database_path('data/one_goal_tax_group_rows.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
