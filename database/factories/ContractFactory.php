<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContractFactory extends Factory
{
    protected $model = \App\Models\Contract::class;

    public function definition(): array
    {
        return [
            'folio'            => 'CONT-' . date('Y') . '-' . str_pad(fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'supplier_id'      => Supplier::factory(),
            'company_id'       => Company::factory(),
            'start_date'       => now()->toDateString(),
            'end_date'         => now()->addYear()->toDateString(),
            'contract_amount'  => fake()->randomFloat(2, 10000, 500000),
            'status'           => \App\Enum\ContractStatus::ACTIVE->value,
            'created_by'       => User::factory(),
            'updated_by'       => User::factory(),
        ];
    }

    public function expired(): static
    {
        return $this->state(['end_date' => now()->subDay()->toDateString()]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status'              => \App\Enum\ContractStatus::CANCELLED->value,
            'cancellation_reason' => 'Cancelado por test',
            'cancelled_at'        => now(),
            'cancelled_by'        => \App\Models\User::factory(),
        ]);
    }
}
