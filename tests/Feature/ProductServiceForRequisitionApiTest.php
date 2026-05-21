<?php

namespace Tests\Feature;

use App\Enum\ProductServiceStatus;
use App\Models\Category;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\ProductService;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductServiceForRequisitionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_staff_can_load_active_products_for_an_assigned_cost_center(): void
    {
        $context = $this->createContext();

        $response = $this->actingAs($context['user'])
            ->getJson(route('products-services.api.active-for-requisitions', [
                'company_id' => $context['company']->id,
                'cost_center_id' => $context['costCenter']->id,
            ]));

        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $context['product']->id,
            'code' => 'PROD-000001',
            'short_name' => 'Licencia M365',
        ]);
    }

    public function test_staff_cannot_load_products_for_an_unassigned_cost_center(): void
    {
        $context = $this->createContext();

        $otherUser = User::factory()->create();
        $otherUser->assignRole('staff');
        $otherUser->companies()->attach($context['company']->id);

        $response = $this->actingAs($otherUser)
            ->getJson(route('products-services.api.active-for-requisitions', [
                'company_id' => $context['company']->id,
                'cost_center_id' => $context['costCenter']->id,
            ]));

        $response->assertForbidden();
        $response->assertJsonFragment([
            'success' => false,
            'message' => 'No tienes permiso para consultar este centro de costo.',
        ]);
    }

    private function createContext(): array
    {
        $user = User::factory()->create();
        $user->assignRole('staff');

        $company = Company::create([
            'code' => 'DGA',
            'name' => 'Diaz Gas',
            'legal_name' => 'Diaz Gas SA',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'TECNOLOGIA',
            'description' => 'Tecnologia',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $costCenter = CostCenter::create([
            'code' => '00184',
            'name' => 'APLICACIONES Y SOFTWARE',
            'purchase_type' => 'Gasto Staff',
            'category_id' => $category->id,
            'company_id' => $company->id,
            'responsible_user_id' => $user->id,
            'budget_type' => 'ANNUAL',
            'status' => 'ACTIVO',
            'created_by' => $user->id,
        ]);

        $product = ProductService::create([
            'code' => 'PROD-000001',
            'technical_description' => 'Licencia Microsoft 365 Business Standard anual.',
            'short_name' => 'Licencia M365',
            'product_type' => 'SERVICIO',
            'category_id' => $category->id,
            'cost_center_id' => $costCenter->id,
            'company_id' => $company->id,
            'unit_of_measure' => 'SERVICIO',
            'estimated_price' => 1000,
            'currency_code' => 'MXN',
            'status' => ProductServiceStatus::ACTIVE->value,
            'is_active' => true,
            'account_major' => '6000',
            'account_sub' => '6100',
            'account_subsub' => '6101',
            'created_by' => $user->id,
        ]);

        $user->companies()->attach($company->id);
        $user->costCenters()->attach($costCenter->id, [
            'is_active' => true,
            'is_default' => true,
        ]);

        return [
            'user' => $user,
            'company' => $company,
            'costCenter' => $costCenter,
            'product' => $product,
        ];
    }
}
