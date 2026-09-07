<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckLockScreen;
use App\Http\Middleware\ModuleAccess;
use App\Models\Department;
use App\Models\DirectPurchaseOrder;
use App\Models\PurchaseOrder;
use App\Models\Requisition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class OwnRecordsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_only_sees_own_requisitions_and_cannot_open_another_users_requisition(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $own = Requisition::factory()->create(['requested_by' => $user->id, 'created_by' => $user->id]);
        $foreign = Requisition::factory()->create(['requested_by' => $otherUser->id, 'created_by' => $otherUser->id]);

        $this->assertSame([$own->id], Requisition::visibleTo($user)->pluck('id')->all());
        $this->assertTrue(Gate::forUser($user)->allows('view', $own));
        $this->assertFalse(Gate::forUser($user)->allows('view', $foreign));

        $this->withoutMiddleware([ModuleAccess::class, CheckLockScreen::class])
            ->actingAs($user)
            ->get(route('requisitions.show', $foreign))
            ->assertForbidden();
    }

    public function test_user_only_sees_orders_created_from_their_requisition(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $ownRequisition = Requisition::factory()->create(['requested_by' => $user->id, 'created_by' => $user->id]);
        $foreignRequisition = Requisition::factory()->create(['requested_by' => $otherUser->id, 'created_by' => $otherUser->id]);
        $ownOrder = PurchaseOrder::factory()->create(['requisition_id' => $ownRequisition->id, 'created_by' => $otherUser->id]);
        $foreignOrder = PurchaseOrder::factory()->create(['requisition_id' => $foreignRequisition->id, 'created_by' => $otherUser->id]);

        $this->assertSame([$ownOrder->id], PurchaseOrder::visibleTo($user)->pluck('id')->all());
        $this->assertTrue(Gate::forUser($user)->allows('view', $ownOrder));
        $this->assertFalse(Gate::forUser($user)->allows('view', $foreignOrder));

        $this->withoutMiddleware([ModuleAccess::class, CheckLockScreen::class])
            ->actingAs($user)
            ->get(route('purchase-orders.show', $foreignOrder))
            ->assertForbidden();
    }

    public function test_user_only_sees_own_direct_purchase_orders(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $ownOrder = DirectPurchaseOrder::factory()->create(['created_by' => $user->id]);
        $foreignOrder = DirectPurchaseOrder::factory()->create(['created_by' => $otherUser->id]);

        $this->assertSame([$ownOrder->id], DirectPurchaseOrder::visibleTo($user)->pluck('id')->all());
        $this->assertTrue(Gate::forUser($user)->allows('view', $ownOrder));
        $this->assertFalse(Gate::forUser($user)->allows('view', $foreignOrder));

        $this->withoutMiddleware([ModuleAccess::class, CheckLockScreen::class])
            ->actingAs($user)
            ->get(route('direct-purchase-orders.show', $foreignOrder))
            ->assertForbidden();
    }

    public function test_department_manager_and_assigned_authorizer_see_records_in_their_scope(): void
    {
        $departmentManager = User::factory()->create();
        $authorizer = User::factory()->create();
        $requester = User::factory()->create();
        $department = Department::create([
            'name' => 'Departamento de prueba',
            'abbreviated' => 'DPR',
            'is_active' => true,
            'manager_user_id' => $departmentManager->id,
        ]);
        $departmentRequisition = Requisition::factory()->create([
            'department_id' => $department->id,
            'requested_by' => $requester->id,
            'created_by' => $requester->id,
        ]);
        $assignedOrder = PurchaseOrder::factory()->create([
            'requisition_id' => $departmentRequisition->id,
            'assigned_approver_id' => $authorizer->id,
        ]);
        $assignedDirectOrder = DirectPurchaseOrder::factory()->create([
            'assigned_approver_id' => $authorizer->id,
            'created_by' => $requester->id,
        ]);

        $this->assertContains($departmentRequisition->id, Requisition::visibleTo($departmentManager)->pluck('id')->all());
        $this->assertTrue(Gate::forUser($departmentManager)->allows('view', $departmentRequisition));
        $this->assertContains($assignedOrder->id, PurchaseOrder::visibleTo($authorizer)->pluck('id')->all());
        $this->assertTrue(Gate::forUser($authorizer)->allows('view', $departmentRequisition));
        $this->assertContains($assignedDirectOrder->id, DirectPurchaseOrder::visibleTo($authorizer)->pluck('id')->all());
        $this->assertTrue(Gate::forUser($authorizer)->allows('view', $assignedDirectOrder));
    }
}
