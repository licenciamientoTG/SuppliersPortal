<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\User;
use App\Notifications\ContractExpiringNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContractExpiryAlertTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;
    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('buyer', 'web');
        Role::findOrCreate('superadmin', 'web');

        $this->buyer      = User::factory()->create()->assignRole('buyer');
        $this->superadmin = User::factory()->create()->assignRole('superadmin');
    }

    public function test_command_notifies_buyers_and_superadmins_at_each_milestone(): void
    {
        Notification::fake();

        foreach ([30, 15, 1] as $days) {
            Contract::factory()->create([
                'status'   => 'active',
                'end_date' => now()->addDays($days)->toDateString(),
            ]);
        }

        $this->artisan('contracts:notify-expiring')->assertSuccessful();

        foreach ([$this->buyer, $this->superadmin] as $user) {
            Notification::assertSentToTimes($user, ContractExpiringNotification::class, 3);
        }
    }

    public function test_command_skips_contracts_outside_milestones(): void
    {
        Notification::fake();

        Contract::factory()->create([
            'status'   => 'active',
            'end_date' => now()->addDays(20)->toDateString(),
        ]);

        $this->artisan('contracts:notify-expiring')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_command_does_not_send_duplicate_for_same_milestone(): void
    {
        Notification::fake();

        Contract::factory()->create([
            'status'   => 'active',
            'end_date' => now()->addDays(15)->toDateString(),
        ]);

        $this->artisan('contracts:notify-expiring')->assertSuccessful();
        $this->artisan('contracts:notify-expiring')->assertSuccessful();

        Notification::assertSentToTimes($this->buyer, ContractExpiringNotification::class, 1);
    }

    public function test_command_ignores_cancelled_and_expired_contracts(): void
    {
        Notification::fake();

        Contract::factory()->create([
            'status'   => 'cancelled',
            'end_date' => now()->addDays(30)->toDateString(),
        ]);
        Contract::factory()->create([
            'status'   => 'active',
            'end_date' => now()->subDay()->toDateString(),
        ]);

        $this->artisan('contracts:notify-expiring')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_datatable_shows_days_remaining_badge_with_tooltip(): void
    {
        Contract::factory()->create([
            'status'   => 'active',
            'end_date' => now()->addDays(15)->toDateString(),
        ]);
        Contract::factory()->create([
            'status'   => 'active',
            'end_date' => now()->addDays(45)->toDateString(),
        ]);

        $response = $this->actingAs($this->buyer)
            ->get(route('contracts.datatable'), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();
        $html = json_encode($response->json('data'), JSON_UNESCAPED_UNICODE);

        $this->assertStringContainsString('Faltan 15 días para el vencimiento', $html);
        $this->assertStringContainsString('bg-danger', $html);
        $this->assertStringContainsString('Faltan 45 días para el vencimiento', $html);
        $this->assertStringContainsString('bg-success', $html);
    }
}
