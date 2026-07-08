<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->lexify('ACC???')),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'account_category' => $this->faker->word(),
            'is_fixed_asset' => false,
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }
}
