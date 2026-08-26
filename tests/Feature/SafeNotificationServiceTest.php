<?php

namespace Tests\Feature;

use App\Jobs\SendSafeNotificationJob;
use App\Notifications\RequisitionSubmittedNotification;
use App\Services\SafeNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SafeNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_smtp_failure_does_not_escape_and_alerts_superadmin_internally(): void
    {
        $superadmin = \App\Models\User::factory()->create(['is_active' => true]);
        $superadmin->assignRole(Role::findOrCreate('superadmin', 'web'));

        $failingRecipient = new class
        {
            public int $id = 987;

            public string $email = 'failing-recipient@example.test';

            public function notify(object $notification): void
            {
                throw new \RuntimeException('SMTP unavailable');
            }
        };

        app(SafeNotificationService::class)->notify(
            new RequisitionSubmittedNotification(new \App\Models\Requisition(['folio' => 'REQ-TEST-001'])),
            [$failingRecipient],
            'de prueba SMTP',
            'REQ-TEST-001',
            route('requisitions.index'),
        );

        $alert = $superadmin->notifications()->firstOrFail();

        $this->assertSame('mail_delivery_failed', $alert->data['type']);
        $this->assertSame('REQ-TEST-001', $alert->data['reference']);
    }

    public function test_model_notifications_are_queued_on_the_mail_queue_with_spaced_retries(): void
    {
        Queue::fake();

        $recipient = \App\Models\User::factory()->create();
        $notification = new RequisitionSubmittedNotification(new \App\Models\Requisition(['folio' => 'REQ-TEST-002']));

        app(SafeNotificationService::class)->notify(
            $notification,
            [$recipient],
            'de prueba SMTP',
            'REQ-TEST-002',
        );

        Queue::assertPushed(SendSafeNotificationJob::class, function (SendSafeNotificationJob $job) use ($recipient): bool {
            return $job->queue === 'mail'
                && $job->recipient->is($recipient)
                && $job->tries === 3
                && $job->backoff === [300, 1800];
        });
    }
}
