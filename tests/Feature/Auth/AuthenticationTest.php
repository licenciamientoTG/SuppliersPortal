<?php

namespace Tests\Feature\Auth;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_root_renders_login_without_an_extra_redirect(): void
    {
        $response = $this->get('/');

        $response->assertOk();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_suppliers_can_authenticate_with_their_own_guard(): void
    {
        $supplier = Supplier::factory()->create([
            'email' => 'proveedor@login.test',
            'password' => 'Password123!',
            'approval_status' => 'pending',
            'document_status' => 'pending',
        ]);

        $response = $this->post('/login', [
            'email' => $supplier->email,
            'password' => 'Password123!',
        ]);

        $response->assertRedirect(route('supplier.documents.index'));
        $this->assertAuthenticated('supplier');
    }
}
