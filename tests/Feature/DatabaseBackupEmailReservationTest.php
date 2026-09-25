<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckLockScreen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseBackupEmailReservationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(CheckLockScreen::class);

        config(['db_backups.allowed_emails' => ['aldo.ochoa@totalgas.com', 'daniel.ramirez@totalgas.com']]);
    }

    public function test_staff_user_cannot_claim_a_reserved_backup_email_via_profile_update(): void
    {
        $user = User::factory()->create(['email' => 'staff@totalgas.com']);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => 'daniel.ramirez@totalgas.com',
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame('staff@totalgas.com', $user->fresh()->email);
    }

    public function test_owner_of_a_reserved_backup_email_can_keep_it_on_profile_update(): void
    {
        $user = User::factory()->create(['email' => 'aldo.ochoa@totalgas.com']);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => 'aldo.ochoa@totalgas.com',
            ])
            ->assertSessionDoesntHaveErrors('email');

        $this->assertSame('aldo.ochoa@totalgas.com', $user->fresh()->email);
    }
}
