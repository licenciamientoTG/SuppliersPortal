<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckLockScreen;
use App\Http\Middleware\ModuleAccess;
use App\Models\BudgetProfile;
use App\Models\Department;
use App\Models\ProductService;
use App\Models\Subaccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProductsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_only_products_reachable_through_the_users_subaccounts(): void
    {
        $this->withoutMiddleware([ModuleAccess::class, CheckLockScreen::class]);

        $department = Department::create([
            'name' => 'Compras Test',
            'abbreviated' => 'COMP',
            'is_active' => true,
        ]);

        $allowedSubaccount = Subaccount::factory()->create();
        $otherSubaccount = Subaccount::factory()->create();

        $profile = BudgetProfile::factory()->create(['department_id' => $department->id]);
        $profile->subaccounts()->sync([$allowedSubaccount->id]);

        $user = User::factory()->create(['department_id' => $department->id]);
        $user->budgetProfiles()->sync([$profile->id]);

        $reachableProduct = ProductService::factory()->create(['code' => 'PROD-REACH']);
        $reachableProduct->subaccounts()->sync([$allowedSubaccount->id]);

        $unreachableProduct = ProductService::factory()->create(['code' => 'PROD-OTHER']);
        $unreachableProduct->subaccounts()->sync([$otherSubaccount->id]);

        $administrator = User::factory()->create();

        $response = $this->actingAs($administrator)
            ->get(route('users.products', $user));

        $response->assertOk()
            ->assertSee('PROD-REACH')
            ->assertDontSee('PROD-OTHER');
    }

    public function test_it_shows_an_empty_state_when_the_user_has_no_accessible_products(): void
    {
        $this->withoutMiddleware([ModuleAccess::class, CheckLockScreen::class]);

        $user = User::factory()->create(['department_id' => null]);
        $administrator = User::factory()->create();

        $response = $this->actingAs($administrator)
            ->get(route('users.products', $user));

        $response->assertOk()
            ->assertSee('no tiene productos o servicios accesibles');
    }
}
