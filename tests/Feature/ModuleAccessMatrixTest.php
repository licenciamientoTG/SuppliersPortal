<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ModuleAccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_department_head_role_is_seeded(): void
    {
        $this->assertDatabaseHas('roles', [
            'name' => 'department_head',
            'guard_name' => 'web',
        ]);
    }

    public function test_department_head_can_access_budget_and_payments_modules(): void
    {
        $user = $this->userWithRole('department_head');

        $this->actingAs($user)
            ->get(route('annual_budgets.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('invoices.index'))
            ->assertOk();
    }

    public function test_department_head_and_authorizer_can_access_requisitions_module(): void
    {
        foreach (['department_head', 'authorizer'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('requisitions.index'))
                ->assertOk();
        }
    }

    public function test_department_head_cannot_access_products_services_module(): void
    {
        $user = $this->userWithRole('department_head');

        $this->actingAs($user)
            ->get(route('products-services.index'))
            ->assertForbidden();
    }

    public function test_staff_can_access_requisitions_and_purchase_orders(): void
    {
        $user = $this->userWithRole('staff');

        $this->actingAs($user)
            ->get(route('requisitions.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('purchase-orders.index'))
            ->assertOk();
    }

    public function test_buyer_dashboard_shows_only_modules_allowed_by_matrix(): void
    {
        $user = $this->userWithRole('buyer');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Cotizaciones (RFQ)')
            ->assertSeeText('Ordenes de Compra')
            ->assertSeeText('Recepciones')
            ->assertDontSeeText('Usuarios Staff')
            ->assertDontSeeText('Incidentes Reportados');
    }

    public function test_superadmin_can_access_staff_users_module(): void
    {
        $user = $this->userWithRole('superadmin');

        $this->actingAs($user)
            ->get(route('users.staff.index'))
            ->assertOk();
    }

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
