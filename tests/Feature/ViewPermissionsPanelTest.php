<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ViewPermissionsPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_only_superadmin_can_open_the_permission_panels(): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');
        $staff = User::factory()->create();
        $staff->assignRole('staff');

        $this->actingAs($superadmin)->get(route('roles.catalog'))->assertOk();
        $this->actingAs($staff)->get(route('roles.catalog'))->assertForbidden();
        $this->actingAs($superadmin)->get(route('users.permissions.edit', $staff))->assertOk();
        $this->actingAs($staff)->get(route('users.permissions.edit', $staff))->assertForbidden();
    }

    public function test_role_view_permission_update_preserves_existing_action_permissions(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('superadmin');
        $role = Role::findByName('staff', 'web');

        $this->actingAs($admin)
            ->patch(route('roles.catalog.view-permissions.update', $role), [
                'permissions' => ['catalogos.ver'],
            ])
            ->assertRedirect();

        $role->refresh();
        $this->assertTrue($role->hasPermissionTo('catalogos.ver'));
        $this->assertTrue($role->hasPermissionTo('view_requisitions'));
    }

    public function test_direct_view_permission_grants_module_access_without_changing_roles(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('superadmin');
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findByName('catalogos.ver', 'web'));

        $this->actingAs($user)
            ->get(route('companies.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->patch(route('users.permissions.update', $user), ['permissions' => []])
            ->assertOk();

        $this->assertDatabaseMissing('model_has_permissions', [
            'model_id' => $user->id,
            'model_type' => User::class,
            'permission_id' => Permission::findByName('catalogos.ver', 'web')->id,
        ]);
    }

    public function test_removing_direct_permission_does_not_remove_access_inherited_from_role(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('superadmin');
        $user = User::factory()->create();
        $user->assignRole('catalog_admin');
        $user->givePermissionTo(Permission::findByName('catalogos.ver', 'web'));

        $this->actingAs($admin)
            ->patch(route('users.permissions.update', $user), ['permissions' => []])
            ->assertOk();

        $this->actingAs($user)
            ->get(route('companies.index'))
            ->assertOk();
    }
}
