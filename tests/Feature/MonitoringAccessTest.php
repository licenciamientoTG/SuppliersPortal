<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MonitoringAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitoring_routes_follow_the_operational_matrix(): void
    {
        $buyer = $this->userWithRole('buyer');
        $accounting = $this->userWithRole('accounting');
        $director = $this->userWithRole('general_director');
        $superadmin = $this->userWithRole('superadmin');

        $this->actingAs($buyer)->get(route('monitoring.alerts'))->assertOk();
        $this->actingAs($buyer)->get(route('monitoring.operations'))->assertOk();
        $this->actingAs($buyer)->get(route('monitoring.budget'))->assertForbidden();
        $this->actingAs($buyer)->get(route('monitoring.security'))->assertForbidden();

        $this->actingAs($accounting)->get(route('monitoring.alerts'))->assertOk();
        $this->actingAs($accounting)->get(route('monitoring.budget'))->assertOk();
        $this->actingAs($accounting)->get(route('monitoring.operations'))->assertForbidden();

        foreach (['alerts', 'operations', 'budget', 'suppliers'] as $monitor) {
            $this->actingAs($director)->get(route("monitoring.{$monitor}"))->assertOk();
        }
        $this->actingAs($director)->get(route('monitoring.security'))->assertForbidden();
        $this->actingAs($superadmin)->get(route('monitoring.security'))->assertOk();
    }

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
