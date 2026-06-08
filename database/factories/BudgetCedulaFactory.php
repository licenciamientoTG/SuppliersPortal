<?php

namespace Database\Factories;

use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BudgetCedulaFactory extends Factory
{
    protected $model = \App\Models\BudgetCedula::class;

    public function definition(): array
    {
        return [
            'expense_category_id' => ExpenseCategory::factory(),
            'name'                => $this->faker->words(4, true),
            'status'              => 'ACTIVO',
            'created_by'         => User::factory(),
        ];
    }
}
