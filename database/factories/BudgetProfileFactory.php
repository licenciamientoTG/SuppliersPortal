<?php

namespace Database\Factories;

use App\Models\BudgetProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class BudgetProfileFactory extends Factory
{
    protected $model = BudgetProfile::class;

    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->slug(2),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'is_active' => true,
        ];
    }
}
