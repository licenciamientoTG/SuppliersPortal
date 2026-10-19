<?php

namespace Tests\Feature;

use App\Models\DirectPurchaseOrder;
use App\Models\PurchaseOrder;
use App\Models\ReceivingLocation;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El acuse del proveedor se marca solo, sin que tenga que confirmar nada,
 * la primera vez que ve sus ordenes en el portal de entregas.
 */
class SupplierDeliveryAcknowledgmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\App\Http\Middleware\EnsureSupplierIsApproved::class);
    }

    public function test_listing_pending_orders_acknowledges_both_order_types(): void
    {
        $supplier = Supplier::factory()->create();
        $location = ReceivingLocation::factory()->create();

        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'receiving_location_id' => $location->id,
            'status' => 'ISSUED',
            'issued_at' => now(),
        ]);
        $directOrder = DirectPurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'receiving_location_id' => $location->id,
            'status' => 'ISSUED',
            'issued_at' => now(),
        ]);

        $this->assertNull($purchaseOrder->supplier_acknowledged_at);
        $this->assertNull($directOrder->supplier_acknowledged_at);

        $this->actingAs($supplier, 'supplier')
            ->get(route('supplier.deliveries.index'))
            ->assertOk();

        $this->assertNotNull($purchaseOrder->fresh()->supplier_acknowledged_at);
        $this->assertNotNull($directOrder->fresh()->supplier_acknowledged_at);
    }

    public function test_opening_the_delivery_form_acknowledges_that_specific_order(): void
    {
        $supplier = Supplier::factory()->create();
        $location = ReceivingLocation::factory()->create();

        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'receiving_location_id' => $location->id,
            'status' => 'ISSUED',
            'issued_at' => now(),
        ]);

        $this->actingAs($supplier, 'supplier')
            ->get(route('supplier.deliveries.create', ['type' => 'standard', 'id' => $purchaseOrder->id]))
            ->assertOk();

        $this->assertNotNull($purchaseOrder->fresh()->supplier_acknowledged_at);
    }

    public function test_viewing_the_order_again_does_not_move_the_acknowledgment_timestamp(): void
    {
        $supplier = Supplier::factory()->create();
        $location = ReceivingLocation::factory()->create();

        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'receiving_location_id' => $location->id,
            'status' => 'ISSUED',
            'issued_at' => now(),
        ]);

        $this->actingAs($supplier, 'supplier')->get(route('supplier.deliveries.index'));
        $firstAckAt = $purchaseOrder->fresh()->supplier_acknowledged_at;

        $this->travel(3)->hours();
        $this->actingAs($supplier, 'supplier')->get(route('supplier.deliveries.index'));

        $this->assertEquals($firstAckAt, $purchaseOrder->fresh()->supplier_acknowledged_at);
    }
}
