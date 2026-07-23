<?php

namespace Tests\Feature;

use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ExpiredSessionRedirectTest extends TestCase
{
    public function test_an_expired_csrf_token_redirects_browser_requests_to_login(): void
    {
        Route::post('/test-expired-csrf-token', function (): void {
            throw new TokenMismatchException('CSRF token mismatch.');
        });

        $response = $this->withoutMiddleware()->post('/test-expired-csrf-token');

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', 'Tu sesión expiró. Inicia sesión nuevamente.');
    }

    public function test_an_expired_csrf_token_returns_json_for_ajax_requests(): void
    {
        Route::post('/test-expired-csrf-token-json', function (): void {
            throw new TokenMismatchException('CSRF token mismatch.');
        });

        $response = $this->withoutMiddleware()
            ->postJson('/test-expired-csrf-token-json');

        $response->assertStatus(419)
            ->assertJson([
                'message' => 'Tu sesión expiró. Inicia sesión nuevamente.',
            ]);
    }
}
