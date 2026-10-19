<?php

namespace Tests\Unit;

use App\Models\DirectPurchaseOrder;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El "acuse" del proveedor es automatico: se marca la primera vez que ve la
 * orden en el portal (listado o detalle de entregas), no requiere que haga
 * click en nada. No debe sobrescribirse una vez capturado.
 */
class SupplierAcknowledgmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_order_starts_without_acknowledgment(): void
    {
        $order = PurchaseOrder::factory()->create();

        $this->assertNull($order->supplier_acknowledged_at);
    }

    public function test_purchase_order_marks_acknowledgment_the_first_time(): void
    {
        $order = PurchaseOrder::factory()->create();

        $order->markAcknowledgedBySupplier();

        $this->assertNotNull($order->fresh()->supplier_acknowledged_at);
    }

    public function test_purchase_order_does_not_overwrite_an_existing_acknowledgment(): void
    {
        $order = PurchaseOrder::factory()->create();
        $order->markAcknowledgedBySupplier();
        $firstAckAt = $order->fresh()->supplier_acknowledged_at;

        $this->travel(2)->hours();
        $order->markAcknowledgedBySupplier();

        $this->assertEquals($firstAckAt, $order->fresh()->supplier_acknowledged_at);
    }

    public function test_direct_purchase_order_starts_without_acknowledgment(): void
    {
        $order = DirectPurchaseOrder::factory()->create();

        $this->assertNull($order->supplier_acknowledged_at);
    }

    public function test_direct_purchase_order_marks_acknowledgment_the_first_time(): void
    {
        $order = DirectPurchaseOrder::factory()->create();

        $order->markAcknowledgedBySupplier();

        $this->assertNotNull($order->fresh()->supplier_acknowledged_at);
    }

    public function test_direct_purchase_order_does_not_overwrite_an_existing_acknowledgment(): void
    {
        $order = DirectPurchaseOrder::factory()->create();
        $order->markAcknowledgedBySupplier();
        $firstAckAt = $order->fresh()->supplier_acknowledged_at;

        $this->travel(2)->hours();
        $order->markAcknowledgedBySupplier();

        $this->assertEquals($firstAckAt, $order->fresh()->supplier_acknowledged_at);
    }
}
