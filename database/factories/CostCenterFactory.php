<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CostCenter>
 */
class CostCenterFactory extends Factory
{
    public function definition(): array
    {
        $user = User::factory()->create();

        return [
            'code'            => strtoupper($this->faker->unique()->lexify('CC???')),
            'name'            => $this->faker->words(3, true),
            'description'     => $this->faker->sentence(),
            'purchase_type'   => 'Gasto Operativo',
            'company_id'      => Company::factory(),
            'category_id'     => Category::factory(),
            'responsible_user_id' => User::factory(),
            'budget_type'     => 'FREE_CONSUMPTION',
            'global_amount'   => 100000,
            'status'          => 'ACTIVO',
            'created_by'      => $user->id,
        ];
    }
}
