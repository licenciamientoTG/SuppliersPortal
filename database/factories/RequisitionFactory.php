<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ReceivingLocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RequisitionFactory extends Factory
{
    protected $model = \App\Models\Requisition::class;

    public function definition(): array
    {
        $user = User::factory()->create();

        return [
            'folio'                   => strtoupper($this->faker->unique()->lexify('REQ-????-???')),
            'company_id'              => Company::factory(),
            'receiving_location_id'   => ReceivingLocation::factory(),
            'requested_by'            => $user->id,
            'created_by'              => $user->id,
            'status'                  => 'DRAFT',
            'description'             => $this->faker->sentence(),
        ];
    }
}
