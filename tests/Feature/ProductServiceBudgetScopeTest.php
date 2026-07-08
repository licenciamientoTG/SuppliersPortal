<?php

namespace Tests\Feature;

use App\Enum\ProductServiceStatus;
use App\Models\Category;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\ProductService;
use App\Models\Subaccount;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductServiceBudgetScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_products_api_filters_by_user_allowed_subaccounts_when_configured(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('staff');

        $company = Company::factory()->create();
        $category = Category::factory()->create();
        $costCenter = CostCenter::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status' => 'ACTIVO',
        ]);

        $allowedSubaccount = Subaccount::factory()->create();
        $blockedSubaccount = Subaccount::factory()->create();

        $allowedProduct = ProductService::factory()->create([
            'company_id' => $company->id,
            'cost_center_id' => $costCenter->id,
            'category_id' => $category->id,
            'status' => ProductServiceStatus::ACTIVE->value,
            'is_active' => true,
            'account_major' => '6000',
            'account_sub' => '6100',
            'account_subsub' => '6101',
        ]);
        $allowedProduct->subaccounts()->sync([$allowedSubaccount->id]);

        $blockedProduct = ProductService::factory()->create([
            'company_id' => $company->id,
            'cost_center_id' => $costCenter->id,
            'category_id' => $category->id,
            'status' => ProductServiceStatus::ACTIVE->value,
            'is_active' => true,
            'account_major' => '6000',
            'account_sub' => '6200',
            'account_subsub' => '6201',
        ]);
        $blockedProduct->subaccounts()->sync([$blockedSubaccount->id]);

        $user->companies()->attach($company->id);
        $user->costCenters()->attach($costCenter->id, ['is_active' => true, 'is_default' => true]);
        $user->subaccounts()->sync([$allowedSubaccount->id]);

        $response = $this->actingAs($user)->getJson(route('products-services.api.active-for-requisitions', [
            'company_id' => $company->id,
            'cost_center_id' => $costCenter->id,
        ]));

        $response->assertOk();
        $response->assertJsonFragment(['id' => $allowedProduct->id]);
        $response->assertJsonMissing(['id' => $blockedProduct->id]);
    }
}
