<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContractAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_access_contracts_index(): void
    {
        $this->actingAs($this->userWithRole('buyer'))
            ->get(route('contracts.index'))
            ->assertOk();
    }

    public function test_superadmin_can_access_contracts_index(): void
    {
        $this->actingAs($this->userWithRole('superadmin'))
            ->get(route('contracts.index'))
            ->assertOk();
    }

    public function test_staff_cannot_access_contracts_index(): void
    {
        $this->actingAs($this->userWithRole('staff'))
            ->get(route('contracts.index'))
            ->assertForbidden();
    }

    public function test_supplier_cannot_access_contracts_index(): void
    {
        $this->actingAs($this->userWithRole('supplier'))
            ->get(route('contracts.index'))
            ->assertForbidden();
    }

    public function test_user_without_role_cannot_access_contracts_index(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('contracts.index'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('contracts.index'))
            ->assertRedirect(route('login'));
    }

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
