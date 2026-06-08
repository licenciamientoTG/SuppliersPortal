<?php

namespace Database\Factories;

use App\Models\CostCenter;
use App\Models\DirectPurchaseOrder;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class DirectPurchaseOrderItemFactory extends Factory
{
    protected $model = \App\Models\DirectPurchaseOrderItem::class;

    public function definition(): array
    {
        $unitPrice = $this->faker->randomFloat(2, 10, 1000);
        $quantity  = 1;
        $subtotal  = $unitPrice * $quantity;
        $ivaRate   = 16.00;
        $ivaAmount = $subtotal * ($ivaRate / 100);
        $total     = $subtotal + $ivaAmount;

        return [
            'direct_purchase_order_id' => DirectPurchaseOrder::factory(),
            'expense_category_id'      => ExpenseCategory::factory(),
            'cost_center_id'           => CostCenter::factory(),
            'description'              => $this->faker->sentence(),
            'quantity'                 => $quantity,
            'unit_price'               => $unitPrice,
            'iva_rate'                 => $ivaRate,
            'subtotal'                 => $subtotal,
            'iva_amount'               => $ivaAmount,
            'total'                    => $total,
        ];
    }
}
