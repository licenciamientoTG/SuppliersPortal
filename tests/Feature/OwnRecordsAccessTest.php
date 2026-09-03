<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckLockScreen;
use App\Http\Middleware\ModuleAccess;
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
}
