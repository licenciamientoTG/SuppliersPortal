<?php

namespace Tests\Feature;

use App\Models\AnnualBudget;
use App\Models\Category;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AnnualBudgetDatatableTest extends TestCase
{
    use RefreshDatabase;

    public function test_datatable_searches_by_cost_center_name(): void
    {
        $user = $this->createBudgetUser();
        $matchingCenter = $this->createCostCenter('CC-SIS', 'SISTEMAS CORPORATIVO', 'Total Gas');
        $otherCenter = $this->createCostCenter('CC-OPS', 'OPERACIONES', 'Diaz Gas');

        $this->createBudget($matchingCenter);
        $this->createBudget($otherCenter);

        $response = $this->actingAs($user)
            ->getJson(route('annual_budgets.datatable').'?'.http_build_query([
                'search' => ['value' => 'SISTEMAS'],
            ]));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment([
            'cost_center_label' => '[CC-SIS] SISTEMAS CORPORATIVO',
        ]);
    }

    public function test_datatable_searches_by_cost_center_code(): void
    {
        $user = $this->createBudgetUser();
        $matchingCenter = $this->createCostCenter('CC-MKT', 'MERCADOTECNIA', 'Total Gas');
        $otherCenter = $this->createCostCenter('CC-RH', 'RECURSOS HUMANOS', 'Diaz Gas');

        $this->createBudget($matchingCenter);
        $this->createBudget($otherCenter);

        $response = $this->actingAs($user)
            ->getJson(route('annual_budgets.datatable').'?'.http_build_query([
                'search' => ['value' => 'CC-MKT'],
            ]));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment([
            'cost_center_label' => '[CC-MKT] MERCADOTECNIA',
        ]);
    }

    private function createBudgetUser(): User
    {
        Role::findOrCreate('superadmin', 'web');

        $user = User::factory()->create();
        $user->assignRole('superadmin');

        return $user;
    }

    private function createCostCenter(string $code, string $name, string $companyName): CostCenter
    {
        $user = User::factory()->create();
        $company = Company::factory()->create([
            'name' => $companyName,
        ]);
        $category = Category::factory()->create([
            'created_by' => $user->id,
        ]);

        return CostCenter::create([
            'code' => $code,
            'name' => $name,
            'purchase_type' => 'Gasto Operativo',
            'category_id' => $category->id,
            'company_id' => $company->id,
            'responsible_user_id' => $user->id,
            'budget_type' => 'ANNUAL',
            'status' => 'ACTIVO',
            'created_by' => $user->id,
        ]);
    }

    private function createBudget(CostCenter $costCenter): AnnualBudget
    {
        return AnnualBudget::create([
            'cost_center_id' => $costCenter->id,
            'fiscal_year' => 2026,
            'total_annual_amount' => 500000,
            'status' => 'PLANIFICACION',
            'created_by' => $costCenter->created_by,
        ]);
    }
}
