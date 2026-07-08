<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Subaccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubaccountFactory extends Factory
{
    protected $model = Subaccount::class;

    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'name' => $this->faker->unique()->words(3, true),
            'subaccount_category' => $this->faker->word(),
            'is_fixed_asset' => false,
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }
}
