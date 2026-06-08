<?php

namespace Database\Factories;

use App\Models\Requisition;
use App\Models\Rfq;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuotationSummaryFactory extends Factory
{
    protected $model = \App\Models\QuotationSummary::class;

    public function definition(): array
    {
        return [
            'requisition_id'  => Requisition::factory(),
            'rfq_id'          => Rfq::factory(),
            'subtotal'        => 0,
            'iva_amount'      => 0,
            'total'           => 0,
            'approval_status' => 'pending',
        ];
    }
}
