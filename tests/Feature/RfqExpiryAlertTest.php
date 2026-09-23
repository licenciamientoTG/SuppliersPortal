<?php

namespace Tests\Feature;

use App\Models\Requisition;
use App\Models\Rfq;
use App\Models\User;
use App\Notifications\RfqExpiredNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RfqExpiryAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_rfq_notifies_requester_and_buyers_once(): void
    {
        Notification::fake();
        Role::findOrCreate('buyer', 'web');

        $requester = User::factory()->create();
        $buyer = User::factory()->create()->assignRole('buyer');
        $requisition = Requisition::factory()->create([
            'requested_by' => $requester->id,
            'created_by' => $requester->id,
        ]);
        $rfq = Rfq::factory()->create([
            'requisition_id' => $requisition->id,
            'status' => 'SENT',
            'response_deadline' => now()->subMinute(),
        ]);

        $this->artisan('rfqs:notify-expired')->assertSuccessful();
        $this->artisan('rfqs:notify-expired')->assertSuccessful();

        Notification::assertSentToTimes($requester, RfqExpiredNotification::class, 1);
        Notification::assertSentToTimes($buyer, RfqExpiredNotification::class, 1);
        $this->assertDatabaseCount('rfq_expiry_notifications', 2);
        $this->assertDatabaseHas('rfq_expiry_notifications', [
            'rfq_id' => $rfq->id,
            'user_id' => $requester->id,
        ]);
    }
}
