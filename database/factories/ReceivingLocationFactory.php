<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ReceivingLocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code'           => strtoupper($this->faker->unique()->lexify('LOC???')),
            'name'           => $this->faker->words(3, true),
            'type'           => 'corporate',
            'is_active'      => true,
            'portal_blocked' => false,
        ];
    }
}
