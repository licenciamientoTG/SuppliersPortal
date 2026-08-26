<?php

namespace Tests\Unit;

use App\Notifications\MailDeliveryFailedNotification;
use Tests\TestCase;

class MailDeliveryFailedNotificationTest extends TestCase
{
    public function test_it_creates_an_internal_alert_for_a_mail_delivery_failure(): void
    {
        $notification = new MailDeliveryFailedNotification(
            'de aprobación al proveedor',
            'OCD-2026-0002',
            15,
        );

        $data = $notification->toArray((object) []);

        $this->assertSame(['database'], $notification->via((object) []));
        $this->assertSame('mail_delivery_failed', $data['type']);
        $this->assertSame(15, $data['order_id']);
        $this->assertStringContainsString('OCD-2026-0002', $data['message']);
    }
}
