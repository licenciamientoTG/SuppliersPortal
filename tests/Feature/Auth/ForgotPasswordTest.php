<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_form_renders(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_link_is_sent_for_existing_user(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $response = $this->post('/forgot-password', ['email' => $user->email]);

        $response->assertSessionHas('status');
        $this->assertEquals(
            'Te hemos enviado un enlace para restablecer tu contraseña.',
            session('status')
        );
    }

    public function test_throttle_is_applied_on_repeated_attempts(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        // Primer intento — exitoso
        $this->post('/forgot-password', ['email' => $user->email]);

        // Segundo intento inmediato — throttled (política: 60 segundos entre intentos)
        $response = $this->post('/forgot-password', ['email' => $user->email]);

        $response->assertSessionHasErrors(['email']);
        $this->assertEquals(
            'Por favor espera antes de intentarlo de nuevo.',
            session('errors')->first('email')
        );
    }

    public function test_unknown_email_returns_spanish_error(): void
    {
        $response = $this->post('/forgot-password', ['email' => 'noexiste@example.com']);

        $response->assertSessionHasErrors(['email']);
        $this->assertEquals(
            'No encontramos un usuario con esa dirección de correo electrónico.',
            session('errors')->first('email')
        );
    }
}
