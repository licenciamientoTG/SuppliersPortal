<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_dispatches_custom_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_reset_notification_has_spanish_subject(): void
    {
        $user = User::factory()->make();
        $notification = new ResetPasswordNotification('fake-token-123');

        $mail = $notification->toMail($user);

        $this->assertEquals(
            'Recuperación de contraseña — Portal de Proveedores',
            $mail->subject
        );
    }

    public function test_reset_notification_uses_custom_blade_view(): void
    {
        $user = User::factory()->make();
        $notification = new ResetPasswordNotification('fake-token-123');

        $mail = $notification->toMail($user);

        $this->assertEquals('emails.auth.reset-password', $mail->view[0]);
    }

    public function test_reset_notification_passes_url_to_view(): void
    {
        $user = User::factory()->make();
        $notification = new ResetPasswordNotification('fake-token-123');

        $mail = $notification->toMail($user);

        $this->assertArrayHasKey('url', $mail->view[1]);
        $this->assertStringContainsString('fake-token-123', $mail->view[1]['url']);
        $this->assertStringContainsString(
            urlencode($user->email),
            $mail->view[1]['url']
        );
    }
}
