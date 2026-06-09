<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ProductService;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContractProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'contract_id'        => Contract::factory(),
            'product_service_id' => ProductService::factory(),
            'unit_price'         => fake()->randomFloat(4, 10, 9999),
            'currency_code'      => 'MXN',
            'unit_of_measure'    => 'PZA',
            'notes'              => null,
        ];
    }
}
