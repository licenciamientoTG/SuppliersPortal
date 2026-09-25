<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckLockScreen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemLogViewerAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(CheckLockScreen::class);

        Role::findOrCreate('staff', 'web');
        Role::findOrCreate('superadmin', 'web');
    }

    private function userWithId(int $id, string $role = 'staff'): User
    {
        $user = User::factory()->create(['id' => $id]);
        $user->assignRole($role);

        return $user;
    }

    public function test_listed_user_ids_can_open_the_log_and_see_the_icon(): void
    {
        foreach ([1, 2, 3] as $id) {
            $user = $this->userWithId($id);

            $this->actingAs($user)->get(route('dev.log.index'))->assertOk();
            $this->actingAs($user)->get(route('dashboard'))
                ->assertSee(route('dev.log.index'), false);
        }
    }

    public function test_other_users_cannot_open_the_log_nor_see_the_icon(): void
    {
        $user = $this->userWithId(4);

        $this->actingAs($user)->get(route('dev.log.index'))->assertForbidden();
        $this->actingAs($user)->get(route('dashboard'))
            ->assertDontSee(route('dev.log.index'), false);
    }

    public function test_superadmin_outside_the_list_keeps_access_to_the_route(): void
    {
        $admin = $this->userWithId(5, 'superadmin');

        $this->actingAs($admin)->get(route('dev.log.index'))->assertOk();
    }
}
