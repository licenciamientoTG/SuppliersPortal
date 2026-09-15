<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckLockScreen;
use App\Models\AuthorizerRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthorizerRoleUnlimitedFlagTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(CheckLockScreen::class);
        Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('superadmin');
    }

    public function test_role_flagged_unlimited_is_stored_without_approval_limit(): void
    {
        $this->actingAs($this->admin)
            ->post(route('authorizer-roles.store'), [
                'name' => 'Director General Corporativo',
                'approval_limit' => '',
                'is_unlimited' => '1',
                'is_active' => '1',
            ])
            ->assertRedirect(route('authorizer-roles.index'))
            ->assertSessionHasNoErrors();

        $role = AuthorizerRole::where('name', 'Director General Corporativo')->firstOrFail();
        $this->assertTrue($role->is_unlimited);
        $this->assertNull($role->approval_limit);
    }

    public function test_role_without_limit_must_be_flagged_unlimited(): void
    {
        $this->actingAs($this->admin)
            ->post(route('authorizer-roles.store'), [
                'name' => 'Dirección General',
                'approval_limit' => '',
                'is_unlimited' => '0',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors([
                'approval_limit' => 'Indica un límite de autorización o marca el rol como sin límite.',
            ]);

        $this->assertDatabaseCount('authorizer_roles', 0);
    }

    public function test_unlimited_role_can_be_renamed_and_keeps_its_flag(): void
    {
        $role = AuthorizerRole::create([
            'name' => 'Dirección General',
            'approval_limit' => null,
            'is_unlimited' => true,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->put(route('authorizer-roles.update', $role), [
                'name' => 'Dirección General Corporativa',
                'approval_limit' => '',
                'is_unlimited' => '1',
                'is_active' => '1',
            ])
            ->assertRedirect(route('authorizer-roles.index'))
            ->assertSessionHasNoErrors();

        $role->refresh();
        $this->assertSame('Dirección General Corporativa', $role->name);
        $this->assertTrue($role->is_unlimited);
        $this->assertNull($role->approval_limit);
    }
}
