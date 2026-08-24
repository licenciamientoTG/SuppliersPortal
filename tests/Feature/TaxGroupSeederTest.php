<?php

namespace Tests\Feature;

use App\Models\LedgerAccount;
use App\Models\TaxGroup;
use Database\Seeders\LedgerAccountSeeder;
use Database\Seeders\TaxCodeSeeder;
use Database\Seeders\TaxGroupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxGroupSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_the_one_goal_chart_of_accounts_with_its_hierarchy(): void
    {
        $this->seed(LedgerAccountSeeder::class);

        $this->assertDatabaseCount('ledger_accounts', 762);
        $this->assertDatabaseHas('ledger_accounts', [
            'one_goal_id' => 77,
            'code' => '11801000',
            'name' => 'IVA ACREDITABLE PAGADO 16%',
        ]);

        $cashAccount = LedgerAccount::query()->where('one_goal_id', 3)->firstOrFail();
        $this->assertSame(2, $cashAccount->parent?->one_goal_id);
        $this->assertFalse(LedgerAccount::query()->where('one_goal_id', 0)->firstOrFail()->is_selectable);
    }

    public function test_it_imports_tax_groups_with_simple_tax_codes_and_ledger_accounts(): void
    {
        $this->seed([TaxCodeSeeder::class, LedgerAccountSeeder::class, TaxGroupSeeder::class]);

        $this->assertDatabaseCount('tax_groups', 25);
        $this->assertDatabaseCount('tax_group_items', 37);

        $group = TaxGroup::query()
            ->where('one_goal_id', 5)
            ->with(['items.taxCode', 'items.ledgerAccount'])
            ->firstOrFail();

        $this->assertSame('IVA E ISR RETENIDO HONORARIOS', $group->name);
        $this->assertCount(3, $group->items);
        $this->assertSame('RET IVA 10.66%', $group->items->firstWhere('one_goal_id', 6)?->taxCode?->name);
        $this->assertSame('21610000', $group->items->firstWhere('one_goal_id', 6)?->ledgerAccount?->code);
        $this->assertIsInt($group->items->firstWhere('one_goal_id', 6)?->ledger_account_id);
    }
}
