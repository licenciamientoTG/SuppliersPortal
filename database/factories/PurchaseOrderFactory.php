<?php

namespace Database\Factories;

use App\Models\QuotationSummary;
use App\Models\Requisition;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseOrderFactory extends Factory
{
    protected $model = \App\Models\PurchaseOrder::class;

    public function definition(): array
    {
        $requisition = Requisition::factory()->create();
        $summary     = QuotationSummary::factory()->create(['requisition_id' => $requisition->id]);

        return [
            'folio'                 => strtoupper($this->faker->unique()->lexify('OC-????-???')),
            'requisition_id'        => $requisition->id,
            'supplier_id'           => Supplier::factory(),
            'quotation_summary_id'  => $summary->id,
            'subtotal'              => 0,
            'iva_amount'            => 0,
            'total'                 => 0,
            'currency'              => 'MXN',
            'status'                => 'OPEN',
            'created_by'            => User::factory(),
        ];
    }
}
