<?php

namespace Tests\Feature;

use App\Models\RequisitionItem;
use App\Models\RfqResponse;
use App\Models\Rfq;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierPartialQuotationTest extends TestCase
{
    use RefreshDatabase;

    private function makeRfqForSupplier(Supplier $supplier): array
    {
        $rfq = Rfq::factory()->create();
        $rfq->suppliers()->attach($supplier->id, ['invited_at' => now()]);
        $itemA = RequisitionItem::factory()->create(['requisition_id' => $rfq->requisition_id]);
        $itemB = RequisitionItem::factory()->create(['requisition_id' => $rfq->requisition_id]);

        return [$rfq, $itemA, $itemB];
    }

    public function test_submit_stores_priced_and_not_available_items_correctly(): void
    {
        $supplier = Supplier::factory()->create();
        [$rfq, $itemA, $itemB] = $this->makeRfqForSupplier($supplier);

        $this->actingAs($supplier, 'supplier')
            ->post(route('supplier.rfq.quotation.save', $rfq), [
                'action' => 'submit',
                'supplier_quotation_number' => 'COT-1',
                'validity_days' => 30,
                'items' => [
                    ['item_id' => $itemA->id, 'unit_price' => 100, 'quantity' => 2, 'iva_rate' => 16, 'delivery_days' => 5, 'currency' => 'MXN'],
                    ['item_id' => $itemB->id, 'not_available' => 1],
                ],
            ])->assertRedirect();

        $priced = RfqResponse::where('requisition_item_id', $itemA->id)->first();
        $this->assertFalse($priced->not_available);
        $this->assertEquals(200.00, (float) $priced->subtotal);
        $this->assertEquals('SUBMITTED', $priced->status);

        $unavailable = RfqResponse::where('requisition_item_id', $itemB->id)->first();
        $this->assertTrue($unavailable->not_available);
        $this->assertEquals(0.0, (float) $unavailable->total);
        $this->assertEquals('SUBMITTED', $unavailable->status);
    }

    public function test_submit_allowed_when_all_items_not_available(): void
    {
        $supplier = Supplier::factory()->create();
        [$rfq, $itemA, $itemB] = $this->makeRfqForSupplier($supplier);

        $this->actingAs($supplier, 'supplier')
            ->post(route('supplier.rfq.quotation.save', $rfq), [
                'action' => 'submit',
                'items' => [
                    ['item_id' => $itemA->id, 'not_available' => 1],
                    ['item_id' => $itemB->id, 'not_available' => 1],
                ],
            ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertEquals(2, RfqResponse::notAvailable()->count());
    }
}
