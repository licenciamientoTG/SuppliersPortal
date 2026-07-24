<?php

namespace Tests\Feature;

use App\Models\AuthorizerRole;
use App\Models\Employee;
use App\Models\User;
use App\Models\UserAuthorizerRole;
use App\Services\AuthorizerResolutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizerResolutionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_approver_when_leader_is_stored_as_string_but_leader_id_exists(): void
    {
        $requester = User::factory()->create();
        $leaderUser = User::factory()->create(['is_active' => true]);

        $leaderRole = AuthorizerRole::create([
            'name' => 'Jefatura QA',
            'approval_limit' => 5000,
            'display_order' => 1,
            'is_active' => true,
        ]);

        UserAuthorizerRole::create([
            'user_id' => $leaderUser->id,
            'authorizer_role_id' => $leaderRole->id,
        ]);

        $leader = Employee::factory()->create([
            'user_id' => $leaderUser->id,
            'employee_number' => '4600',
            'first_name' => 'Israel Kuwait',
            'last_name' => 'Valenzuela Flores',
            'is_active' => 'SI',
        ]);

        Employee::factory()->create([
            'user_id' => $requester->id,
            'employee_number' => '4271',
            'first_name' => 'Jose Aldo',
            'last_name' => 'Ochoa Hinojos',
            'leader' => 'Israel Kuwait Valenzuel',
            'leader_id' => $leader->id,
            'is_active' => 'SI',
        ]);

        $resolution = app(AuthorizerResolutionService::class)->resolveForRequester($requester, 1000);

        $this->assertSame($leaderUser->id, $resolution['approver_user']->id);
        $this->assertSame($leader->id, $resolution['approver_employee']->id);
        $this->assertSame('eligible', $resolution['chain'][0]['status']);
    }

    public function test_general_director_with_null_limit_can_authorize_any_amount(): void
    {
        $requester = User::factory()->create();
        $directorUser = User::factory()->create(['is_active' => true]);
        $directorRole = AuthorizerRole::create([
            'name' => 'Dirección General',
            'approval_limit' => null,
            'display_order' => 1,
            'is_active' => true,
        ]);
        UserAuthorizerRole::create([
            'user_id' => $directorUser->id,
            'authorizer_role_id' => $directorRole->id,
        ]);
        $director = Employee::factory()->create([
            'user_id' => $directorUser->id,
            'employee_number' => 'DG-1',
            'is_active' => 'SI',
        ]);
        Employee::factory()->create([
            'user_id' => $requester->id,
            'employee_number' => 'REQ-1',
            'leader_id' => $director->id,
            'leader' => $director->employee_number,
            'is_active' => 'SI',
        ]);

        $resolution = app(AuthorizerResolutionService::class)
            ->resolveForRequester($requester, 999999999.99);

        $this->assertSame($directorUser->id, $resolution['approver_user']->id);
        $this->assertNull($resolution['effective_limit']);
        $this->assertSame('eligible', $resolution['chain'][0]['status']);
    }
}
