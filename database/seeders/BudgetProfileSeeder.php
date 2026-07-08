<?php

namespace Database\Seeders;

use App\Services\BudgetProfileHomologationService;
use Illuminate\Database\Seeder;

class BudgetProfileSeeder extends Seeder
{
    public function run(): void
    {
        app(BudgetProfileHomologationService::class)->syncEmployeePositions();
    }
}
