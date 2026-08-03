<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\NotificationCenterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_informational_notifications_do_not_remain_as_unread_work(): void
    {
        $user = User::factory()->create();
        $notification = $this->notificationFor($user, [
            'type' => 'rfq_sent_to_suppliers',
            'url' => route('dashboard'),
        ]);

        app(NotificationCenterService::class)->resolveObsoleteUnreadForUser($user);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_missing_related_resource_has_no_openable_target(): void
    {
        $user = User::factory()->create();
        $notification = $this->notificationFor($user, [
            'type' => 'new_rfq',
            'rfq_id' => 999999,
            'url' => route('supplier.rfq.show', 999999),
        ]);

        $this->assertNull(app(NotificationCenterService::class)->targetFor($notification));
    }

    public function test_opening_a_stale_notification_returns_to_history_instead_of_a_404(): void
    {
        $user = User::factory()->create();
        $notification = $this->notificationFor($user, [
            'type' => 'new_rfq',
            'rfq_id' => 999999,
            'url' => route('supplier.rfq.show', 999999),
        ]);

        $this->actingAs($user)
            ->get(route('notifications.open', $notification->id))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('warning');

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_legacy_financial_notification_is_normalized_before_it_is_shown(): void
    {
        $user = User::factory()->create();
        $notification = $this->notificationFor($user, [
            'financial_provision_id' => 999999,
            'supplier_invoice_id' => 5,
        ]);

        app(NotificationCenterService::class)->resolveObsoleteUnreadForUser($user);

        $this->assertSame('financial_provision_discrepancy', $notification->fresh()->data['type']);
        $this->assertArrayHasKey('url', $notification->fresh()->data);
        $this->assertArrayHasKey('message', $notification->fresh()->data);
    }

    private function notificationFor(User $user, array $data): DatabaseNotification
    {
        return DatabaseNotification::query()->create([
            'id' => (string) Str::uuid(),
            'type' => 'tests.notification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => $data,
        ]);
    }
}
