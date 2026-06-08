<?php

namespace Database\Factories;

use App\Models\Rfq;
use App\Models\RequisitionItem;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class RfqResponseFactory extends Factory
{
    protected $model = \App\Models\RfqResponse::class;

    public function definition(): array
    {
        $unitPrice = $this->faker->randomFloat(2, 10, 1000);
        $quantity  = 1;
        $subtotal  = $unitPrice * $quantity;
        $ivaRate   = 16.00;
        $ivaAmount = $subtotal * ($ivaRate / 100);
        $total     = $subtotal + $ivaAmount;

        return [
            'rfq_id'              => Rfq::factory(),
            'supplier_id'         => Supplier::factory(),
            'requisition_item_id' => RequisitionItem::factory(),
            'quotation_date'      => now()->toDateString(),
            'validity_days'       => 30,
            'unit_price'          => $unitPrice,
            'quantity'            => $quantity,
            'subtotal'            => $subtotal,
            'iva_rate'            => $ivaRate,
            'iva_amount'          => $ivaAmount,
            'total'               => $total,
            'delivery_days'       => 5,
            'currency'            => 'MXN',
            'status'              => 'DRAFT',
        ];
    }
}
