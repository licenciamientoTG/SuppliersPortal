<?php

namespace Database\Factories;

use App\Models\QuotationGroup;
use App\Models\Requisition;
use Illuminate\Database\Eloquent\Factories\Factory;

class RfqFactory extends Factory
{
    protected $model = \App\Models\Rfq::class;

    public function definition(): array
    {
        return [
            'folio'              => strtoupper($this->faker->unique()->lexify('RFQ-????-???')),
            'requisition_id'     => Requisition::factory(),
            'quotation_group_id' => QuotationGroup::factory(),
            'source'             => 'portal',
            'status'             => 'SENT',
        ];
    }
}
