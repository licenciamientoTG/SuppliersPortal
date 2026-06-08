<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code'        => strtoupper($this->faker->unique()->lexify('EXP???')),
            'name'        => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'status'      => 'ACTIVO',
            'created_by'  => User::factory(),
        ];
    }
}
