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

    public function test_listed_user_can_clear_the_log_and_the_action_is_recorded(): void
    {
        $path = storage_path('logs/laravel.log');
        $backup = file_exists($path) ? file_get_contents($path) : null;

        try {
            file_put_contents($path, "[2026-01-01 00:00:00] testing.ERROR: ruido
");

            $this->actingAs($this->userWithId(1))
                ->post(route('dev.log.clear'))
                ->assertRedirect(route('dev.log.index'));

            $content = file_get_contents($path);
            $this->assertStringNotContainsString('ruido', $content);
            $this->assertStringContainsString('Log limpiado por', $content);
        } finally {
            $backup === null ? @unlink($path) : file_put_contents($path, $backup);
        }
    }

    public function test_other_users_cannot_clear_the_log(): void
    {
        $this->actingAs($this->userWithId(4))
            ->post(route('dev.log.clear'))
            ->assertForbidden();
    }
}
