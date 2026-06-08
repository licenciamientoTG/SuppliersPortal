<?php

namespace Database\Factories;

use App\Models\Requisition;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuotationGroupFactory extends Factory
{
    protected $model = \App\Models\QuotationGroup::class;

    public function definition(): array
    {
        return [
            'requisition_id' => Requisition::factory(),
            'name'           => $this->faker->words(3, true),
            'status'         => 'ACTIVE',
        ];
    }
}
