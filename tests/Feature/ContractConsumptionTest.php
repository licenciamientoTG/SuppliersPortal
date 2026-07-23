<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\RequisitionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContractConsumptionTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('buyer', 'web');
        $this->buyer = User::factory()->create()->assignRole('buyer');
    }

    private function addConsumption(Contract $contract, float $subtotal, string $poStatus = 'ISSUED'): void
    {
        $reqItem = RequisitionItem::factory()->create(['contract_id' => $contract->id]);
        $po      = PurchaseOrder::factory()->create(['status' => $poStatus]);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id'   => $po->id,
            'requisition_item_id' => $reqItem->id,
            'subtotal'            => $subtotal,
        ]);
    }

    private function datatableJson(): string
    {
        $response = $this->actingAs($this->buyer)
            ->get(route('contracts.datatable'), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();

        return json_encode($response->json('data'), JSON_UNESCAPED_UNICODE);
    }

    public function test_datatable_shows_consumption_ring_with_percentage_and_amounts(): void
    {
        $contract = Contract::factory()->create([
            'status'          => 'active',
            'end_date'        => now()->addYear(),
            'contract_amount' => 1000,
        ]);
        $this->addConsumption($contract, 200);

        $html = $this->datatableJson();

        $this->assertStringContainsString('contract-consumption', $html);
        $this->assertStringContainsString('>20%<', $html);
        $this->assertStringContainsString('Ejercido $200.00 de $1,000.00 (20%)', $html);
    }

    public function test_ring_color_changes_with_consumption_level(): void
    {
        foreach ([200, 800, 950] as $consumed) {
            $contract = Contract::factory()->create([
                'status'          => 'active',
                'end_date'        => now()->addYear(),
                'contract_amount' => 1000,
            ]);
            $this->addConsumption($contract, $consumed);
        }

        $html = $this->datatableJson();

        $this->assertStringContainsString('--bs-success', $html); // 20%
        $this->assertStringContainsString('--bs-warning', $html); // 80%
        $this->assertStringContainsString('--bs-danger', $html);  // 95%
    }

    public function test_cancelled_purchase_orders_do_not_count(): void
    {
        $contract = Contract::factory()->create([
            'status'          => 'active',
            'end_date'        => now()->addYear(),
            'contract_amount' => 1000,
        ]);
        $this->addConsumption($contract, 500, 'CANCELLED');

        $html = $this->datatableJson();

        $this->assertStringContainsString('>0%<', $html);
    }

    public function test_contract_without_amount_shows_dash(): void
    {
        Contract::factory()->create([
            'status'          => 'active',
            'end_date'        => now()->addYear(),
            'contract_amount' => 0,
        ]);

        $html = $this->datatableJson();

        $this->assertStringContainsString('Sin monto contratado', $html);
    }
}
