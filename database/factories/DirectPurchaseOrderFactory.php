<?php

namespace Database\Factories;

use App\Models\ReceivingLocation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DirectPurchaseOrderFactory extends Factory
{
    protected $model = \App\Models\DirectPurchaseOrder::class;

    public function definition(): array
    {
        return [
            'folio'                  => strtoupper($this->faker->unique()->lexify('OCD-????-???')),
            'supplier_id'            => Supplier::factory(),
            'receiving_location_id'  => ReceivingLocation::factory(),
            'application_month'      => now()->format('Y-m'),
            'justification'          => $this->faker->sentence(),
            'subtotal'               => 0,
            'iva_amount'             => 0,
            'total'                  => 0,
            'currency'               => 'MXN',
            'status'                 => 'DRAFT',
            'created_by'             => User::factory(),
        ];
    }
}
