<?php

namespace Tests\Feature;

use App\Models\ApprovalDelegation;
use App\Models\ApprovalDelegationMember;
use App\Models\AuthorizerRole;
use App\Models\DirectPurchaseOrder;
use App\Models\User;
use App\Models\UserAuthorizerRole;
use App\Notifications\ApprovalDelegationSummaryNotification;
use App\Notifications\DelegatedApprovalActionNotification;
use App\Services\ApprovalDecisionService;
use App\Services\ApprovalDelegationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApprovalDelegationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorizer_can_activate_delegation_and_delegate_can_act_for_principal(): void
    {
        Notification::fake();
        [$principal, $delegate] = $this->authorizers();

        $response = $this->actingAs($principal)->post(route('approval-delegations.activate'), [
            'delegate_ids' => [$delegate->id],
            'ends_at' => now()->addWeek()->format('Y-m-d H:i:s'),
        ]);

        $response->assertRedirect();
        $delegation = ApprovalDelegation::query()->firstOrFail();

        $this->assertSame('ACTIVE', $delegation->status);
        $this->assertDatabaseHas('approval_delegation_members', [
            'approval_delegation_id' => $delegation->id,
            'delegate_user_id' => $delegate->id,
            'removed_at' => null,
        ]);
        $this->assertTrue(app(ApprovalDelegationService::class)->canAct($delegate, $principal->id));
        Notification::assertSentTo($delegate, ApprovalDelegationSummaryNotification::class);
    }

    public function test_expired_or_removed_member_loses_access_immediately(): void
    {
        [$principal, $delegate] = $this->authorizers();
        $delegation = ApprovalDelegation::create([
            'delegator_user_id' => $principal->id,
            'status' => 'ACTIVE',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->subMinute(),
        ]);
        ApprovalDelegationMember::create([
            'approval_delegation_id' => $delegation->id,
            'delegate_user_id' => $delegate->id,
            'added_at' => now()->subDay(),
        ]);

        $this->assertFalse(app(ApprovalDelegationService::class)->canAct($delegate, $principal->id));

        $delegation->update(['ends_at' => now()->addDay()]);
        $this->assertTrue(app(ApprovalDelegationService::class)->canAct($delegate, $principal->id));

        $delegation->members()->update(['removed_at' => now()]);
        $this->assertFalse(app(ApprovalDelegationService::class)->canAct($delegate, $principal->id));
    }

    public function test_delegations_do_not_propagate_to_second_level(): void
    {
        [$principal, $firstDelegate, $secondDelegate] = $this->authorizers(3);
        $firstPeriod = $this->activeDelegation($principal, $firstDelegate);
        $this->activeDelegation($firstDelegate, $secondDelegate);

        $service = app(ApprovalDelegationService::class);

        $this->assertTrue($service->canAct($firstDelegate, $principal->id));
        $this->assertFalse($service->canAct($secondDelegate, $principal->id));
        $accessibleIds = $service->accessiblePrincipalIds($secondDelegate);
        $this->assertContains($secondDelegate->id, $accessibleIds);
        $this->assertContains($firstDelegate->id, $accessibleIds);
        $this->assertNotContains($principal->id, $accessibleIds);
        $this->assertNotNull($firstPeriod);
    }

    public function test_activation_rejects_non_authorizer_delegate(): void
    {
        [$principal] = $this->authorizers();
        $invalidDelegate = User::factory()->create(['is_active' => true]);

        $this->actingAs($principal)
            ->post(route('approval-delegations.activate'), [
                'delegate_ids' => [$invalidDelegate->id],
            ])
            ->assertSessionHasErrors('delegate_ids');

        $this->assertDatabaseCount('approval_delegations', 0);
    }

    public function test_delegated_decision_records_actor_principal_and_period(): void
    {
        Notification::fake();
        [$principal, $delegate] = $this->authorizers();
        $delegation = $this->activeDelegation($principal, $delegate);

        $decision = app(ApprovalDecisionService::class)->record(
            $principal,
            $principal->id,
            $delegate,
            'APPROVED',
            'Aprobado durante ausencia programada.'
        );

        $this->assertSame($principal->id, $decision->assigned_principal_user_id);
        $this->assertSame($delegate->id, $decision->acted_by_user_id);
        $this->assertSame($delegation->id, $decision->approval_delegation_id);
        Notification::assertSentTo($principal, DelegatedApprovalActionNotification::class);
    }

    public function test_superadmin_can_deactivate_with_required_reason(): void
    {
        [$principal, $delegate] = $this->authorizers();
        $delegation = $this->activeDelegation($principal, $delegate);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(Role::findOrCreate('superadmin', 'web'));

        $this->actingAs($admin)
            ->post(route('admin.approval-delegations.deactivate', $delegation), ['reason' => 'corto'])
            ->assertSessionHasErrors('reason');

        $this->actingAs($admin)
            ->post(route('admin.approval-delegations.deactivate', $delegation), [
                'reason' => 'Desactivación solicitada por soporte operativo.',
            ])
            ->assertRedirect();

        $delegation->refresh();
        $this->assertSame('ENDED', $delegation->status);
        $this->assertSame($admin->id, $delegation->deactivated_by_user_id);
    }

    public function test_delegate_can_approve_direct_purchase_order_for_principal(): void
    {
        Notification::fake();
        [$principal, $delegate] = $this->authorizers();
        $delegation = $this->activeDelegation($principal, $delegate);
        $order = DirectPurchaseOrder::factory()->create([
            'status' => 'PENDING_APPROVAL',
            'assigned_approver_id' => $principal->id,
            'total' => 500,
            'budget_reserved_at' => now(),
        ]);

        $this->actingAs($delegate)
            ->post(route('direct-purchase-orders.approve', $order))
            ->assertRedirect();

        $this->assertSame('ISSUED', $order->fresh()->status);
        $this->assertDatabaseHas('approval_decisions', [
            'approvable_type' => DirectPurchaseOrder::class,
            'approvable_id' => $order->id,
            'assigned_principal_user_id' => $principal->id,
            'acted_by_user_id' => $delegate->id,
            'approval_delegation_id' => $delegation->id,
            'action' => 'APPROVED',
        ]);
    }

    public function test_authorizer_can_render_delegation_module_and_unified_inbox(): void
    {
        [$principal] = $this->authorizers();

        $this->actingAs($principal)
            ->get(route('approval-delegations.index'))
            ->assertOk()
            ->assertSee('Mi delegación')
            ->assertSee('Delegados autorizadores');

        $this->actingAs($principal)
            ->get(route('authorizations.index'))
            ->assertOk()
            ->assertSee('Bandeja de autorizaciones')
            ->assertSee('Delegadas');
    }

    public function test_expiration_command_closes_finished_period(): void
    {
        [$principal, $delegate] = $this->authorizers();
        $delegation = $this->activeDelegation($principal, $delegate);
        $delegation->update(['ends_at' => now()->subMinute()]);

        $this->artisan('approval-delegations:expire')->assertSuccessful();

        $delegation->refresh();
        $this->assertSame('ENDED', $delegation->status);
        $this->assertSame('Finalización automática por fecha programada.', $delegation->deactivation_reason);
    }

    private function authorizers(int $count = 2): array
    {
        $portalRole = Role::findOrCreate('authorizer', 'web');
        $authorizationRole = AuthorizerRole::firstOrCreate(
            ['name' => 'Autorizador de pruebas'],
            ['approval_limit' => 100000, 'is_active' => true]
        );

        return User::factory()->count($count)->create(['is_active' => true])
            ->each(function (User $user) use ($portalRole, $authorizationRole) {
                $user->assignRole($portalRole);
                UserAuthorizerRole::create([
                    'user_id' => $user->id,
                    'authorizer_role_id' => $authorizationRole->id,
                ]);
            })
            ->all();
    }

    private function activeDelegation(User $principal, User $delegate): ApprovalDelegation
    {
        $delegation = ApprovalDelegation::create([
            'delegator_user_id' => $principal->id,
            'status' => 'ACTIVE',
            'starts_at' => now()->subMinute(),
        ]);
        ApprovalDelegationMember::create([
            'approval_delegation_id' => $delegation->id,
            'delegate_user_id' => $delegate->id,
            'added_at' => now()->subMinute(),
        ]);

        return $delegation;
    }
}
