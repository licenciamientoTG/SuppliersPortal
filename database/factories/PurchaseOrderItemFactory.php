<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\RequisitionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseOrderItemFactory extends Factory
{
    protected $model = \App\Models\PurchaseOrderItem::class;

    public function definition(): array
    {
        $unitPrice = $this->faker->randomFloat(2, 10, 1000);
        $quantity  = 1;
        $subtotal  = $unitPrice * $quantity;
        $ivaAmount = $subtotal * 0.16;
        $total     = $subtotal + $ivaAmount;

        return [
            'purchase_order_id'   => PurchaseOrder::factory(),
            'requisition_item_id' => RequisitionItem::factory(),
            'description'         => $this->faker->sentence(),
            'quantity'            => $quantity,
            'unit_price'          => $unitPrice,
            'subtotal'            => $subtotal,
            'iva_amount'          => $ivaAmount,
            'total'               => $total,
        ];
    }
}
