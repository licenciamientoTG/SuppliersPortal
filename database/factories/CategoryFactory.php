<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'        => $this->faker->unique()->word(),
            'description' => $this->faker->sentence(),
            'is_active'   => true,
            'created_by'  => null,
        ];
    }
}
