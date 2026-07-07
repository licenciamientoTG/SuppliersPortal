<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReceivingLocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id'      => Company::factory(),
            'code'           => strtoupper($this->faker->unique()->lexify('LOC???')),
            'name'           => $this->faker->words(3, true),
            'type'           => 'corporate',
            'is_active'      => true,
            'portal_blocked' => false,
        ];
    }
}
