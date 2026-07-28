<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code'          => strtoupper($this->faker->unique()->lexify('?????')),
            'name'          => $this->faker->company(),
            'legal_name'    => $this->faker->company() . ' SA de CV',
            'rfc'           => strtoupper($this->faker->regexify('[A-Z]{3}[0-9]{6}[A-Z0-9]{3}')),
            'locale'        => 'es_MX',
            'timezone'      => 'America/Monterrey',
            'currency_code' => 'MXN',
            'is_active'     => true,
        ];
    }
}
