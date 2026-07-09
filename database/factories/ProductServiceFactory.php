<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductServiceFactory extends Factory
{
    protected $model = \App\Models\ProductService::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->lexify('PROD-???')),
            'technical_description' => $this->faker->sentence(10),
            'short_name' => $this->faker->word(),
            'product_type' => 'PRODUCTO',
            'unit_of_measure' => 'PZA',
            'estimated_price' => 100,
            'currency_code' => 'MXN',
            'status' => 'ACTIVE',
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }
}
