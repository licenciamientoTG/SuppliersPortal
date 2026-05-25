<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Carbon\Carbon;
use App\Models\Tax;

class TaxSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $taxes = [
            [
                'name' => 'Exento',
                'rate_percent' => 0.00,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Frontera',
                'rate_percent' => 8.00,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'General',
                'rate_percent' => 16.00,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

        ];

        foreach ($taxes as $tax) {
            Tax::updateOrCreate(
                ['name' => $tax['name']],
                $tax
            );
        }
    }
}
