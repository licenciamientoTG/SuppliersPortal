<?php

namespace Tests\Feature;

use App\Models\Department;
use Database\Seeders\BudgetProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetProfileSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_default_profiles_for_active_departments(): void
    {
        $department = Department::create([
            'name' => 'Compras Test',
            'abbreviated' => 'COMP',
            'is_active' => true,
        ]);

        $this->seed(BudgetProfileSeeder::class);

        $department->refresh()->load('budgetProfiles');

        $this->assertCount(3, $department->budgetProfiles);
        $this->assertTrue($department->budgetProfiles->contains('key', 'jefe_comp'));
        $this->assertTrue($department->budgetProfiles->contains('key', 'operativo_comp'));
        $this->assertTrue($department->budgetProfiles->contains('key', 'auxiliar_comp'));
    }
}
