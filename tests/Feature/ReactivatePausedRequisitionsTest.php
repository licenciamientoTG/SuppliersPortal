<?php

namespace Tests\Feature;

use App\Enum\ProductServiceStatus;
use App\Enum\RequisitionStatus;
use App\Events\ProductServiceApproved;
use App\Listeners\ReactivatePausedRequisitions;
use App\Models\ProductService;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReactivatePausedRequisitionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function pausedRequisitionFor(ProductService $product, User $user): Requisition
    {
        $requisition = Requisition::factory()->create([
            'requested_by' => $user->id,
            'created_by' => $user->id,
            'status' => RequisitionStatus::PAUSED->value,
            'pause_reason' => "Esperando aprobación de productos: {$product->code}",
            'paused_by' => $user->id,
            'paused_at' => now(),
        ]);

        RequisitionItem::factory()->create([
            'requisition_id' => $requisition->id,
            'product_service_id' => $product->id,
        ]);

        return $requisition;
    }

    private function approve(ProductService $product, User $approver): void
    {
        $product->update([
            'status' => ProductServiceStatus::ACTIVE->value,
            'is_active' => true,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        (new ReactivatePausedRequisitions)->handle(new ProductServiceApproved($product->fresh()));
    }

    public function test_approving_the_pending_product_returns_the_paused_requisition_to_pending(): void
    {
        $approver = User::factory()->create();
        $product = ProductService::factory()->create([
            'status' => ProductServiceStatus::PENDING->value,
            'is_active' => false,
        ]);

        $requisition = $this->pausedRequisitionFor($product, $approver);

        $this->approve($product, $approver);

        $requisition->refresh();

        $this->assertSame(RequisitionStatus::PENDING, $requisition->status);
        $this->assertSame($approver->id, $requisition->reactivated_by);
        $this->assertNotNull($requisition->reactivated_at);
        $this->assertNull($requisition->pause_reason);
        $this->assertNull($requisition->paused_by);
        $this->assertNull($requisition->paused_at);
    }

    public function test_requisition_stays_paused_while_another_item_product_is_still_pending(): void
    {
        $approver = User::factory()->create();

        $approvedProduct = ProductService::factory()->create([
            'status' => ProductServiceStatus::PENDING->value,
            'is_active' => false,
        ]);

        $stillPendingProduct = ProductService::factory()->create([
            'code' => 'PROD-PEND',
            'status' => ProductServiceStatus::PENDING->value,
            'is_active' => false,
        ]);

        $requisition = $this->pausedRequisitionFor($approvedProduct, $approver);

        RequisitionItem::factory()->create([
            'requisition_id' => $requisition->id,
            'product_service_id' => $stillPendingProduct->id,
        ]);

        $this->approve($approvedProduct, $approver);

        $requisition->refresh();

        $this->assertSame(RequisitionStatus::PAUSED, $requisition->status);
        $this->assertStringContainsString('PROD-PEND', (string) $requisition->pause_reason);
    }

    public function test_requisitions_that_are_not_paused_are_left_untouched(): void
    {
        $approver = User::factory()->create();
        $product = ProductService::factory()->create([
            'status' => ProductServiceStatus::PENDING->value,
            'is_active' => false,
        ]);

        $requisition = Requisition::factory()->create([
            'requested_by' => $approver->id,
            'created_by' => $approver->id,
            'status' => RequisitionStatus::DRAFT->value,
        ]);

        RequisitionItem::factory()->create([
            'requisition_id' => $requisition->id,
            'product_service_id' => $product->id,
        ]);

        $this->approve($product, $approver);

        $this->assertSame(RequisitionStatus::DRAFT, $requisition->fresh()->status);
    }
}
