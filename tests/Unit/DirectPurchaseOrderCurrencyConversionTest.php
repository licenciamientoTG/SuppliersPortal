<?php

namespace Tests\Unit;

use App\Models\DirectPurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectPurchaseOrderCurrencyConversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_convert_item_amount_to_mxn_returns_same_amount_when_order_is_mxn(): void
    {
        $order = DirectPurchaseOrder::factory()->create(['currency' => 'MXN', 'exchange_rate' => 1.000000]);

        $this->assertSame(1000.0, $order->convertItemAmountToMxn(1000.0));
    }

    public function test_convert_item_amount_to_mxn_applies_the_rate_captured_at_issuance(): void
    {
        $order = DirectPurchaseOrder::factory()->create(['currency' => 'USD', 'exchange_rate' => 18.5000]);

        $this->assertSame(1850.0, $order->convertItemAmountToMxn(100.0));
    }

    public function test_new_direct_purchase_order_defaults_to_mxn_with_rate_one(): void
    {
        $order = DirectPurchaseOrder::factory()->create()->fresh();

        $this->assertSame('MXN', $order->currency);
        $this->assertSame(1.0, (float) $order->exchange_rate);
    }
}
