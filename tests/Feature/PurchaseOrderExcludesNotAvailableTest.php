<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckLockScreen;
use App\Http\Middleware\ModuleAccess;
use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\QuotationSummary;
use App\Models\ReceivingLocation;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Rfq;
use App\Models\RfqResponse;
use App\Models\Supplier;
use App\Models\User;
use App\Services\BudgetAllocationService;
use App\Services\QuotationRejectionWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

class PurchaseOrderExcludesNotAvailableTest extends TestCase
{
    use RefreshDatabase;

    public function test_generated_purchase_order_excludes_not_available_line(): void
    {
        Notification::fake();

        // Skip only the module/lock gates; keep real auth + route-model binding.
        $this->withoutMiddleware([ModuleAccess::class, CheckLockScreen::class]);

        $requester = User::factory()->create();
        $approver = User::factory()->create();

        $requisition = Requisition::factory()->create([
            'requested_by' => $requester->id,
            'created_by' => $requester->id,
            'updated_by' => $requester->id,
            'company_id' => Company::factory()->create()->id,
            'receiving_location_id' => ReceivingLocation::factory()->create()->id,
            'required_date' => now()->addDays(5)->toDateString(),
            'status' => 'IN_QUOTATION',
        ]);

        $itemA = RequisitionItem::factory()->create(['requisition_id' => $requisition->id]);
        $itemB = RequisitionItem::factory()->create(['requisition_id' => $requisition->id]);

        $rfq = Rfq::factory()->create([
            'requisition_id' => $requisition->id,
            'quotation_group_id' => null,
            'requisition_item_id' => $itemA->id,
            'supplier_id' => null,
            'response_deadline' => now()->addDays(7),
            'status' => 'SENT',
        ]);

        $supplier = Supplier::factory()->create();
        $rfq->suppliers()->attach($supplier->id);

        // itemA is quoted with real totals.
        RfqResponse::factory()->create([
            'rfq_id' => $rfq->id,
            'supplier_id' => $supplier->id,
            'requisition_item_id' => $itemA->id,
            'status' => 'SUBMITTED',
            'subtotal' => 100,
            'iva_amount' => 16,
            'total' => 116,
            'not_available' => false,
        ]);

        // itemB is marked not_available with zero totals; it must NOT reach the PO.
        RfqResponse::factory()->create([
            'rfq_id' => $rfq->id,
            'supplier_id' => $supplier->id,
            'requisition_item_id' => $itemB->id,
            'status' => 'SUBMITTED',
            'subtotal' => 0,
            'iva_amount' => 0,
            'total' => 0,
            'not_available' => true,
        ]);

        $summary = QuotationSummary::factory()->create([
            'requisition_id' => $requisition->id,
            'rfq_id' => $rfq->id,
            'selected_supplier_id' => $supplier->id,
            'requested_by_user_id' => $requester->id,
            'selected_by_user_id' => $approver->id,
            'current_approver_user_id' => $approver->id,
            'subtotal' => 100,
            'iva_amount' => 16,
            'total' => 116,
            'approval_status' => 'pending',
        ]);

        // Stub the service touchpoints in the approved branch so the real
        // budget/authorizer machinery does not run.
        $budgetService = Mockery::mock(BudgetAllocationService::class);
        $budgetService->shouldReceive('transferQuotationSummaryToPurchaseOrder')->andReturnNull();
        $this->app->instance(BudgetAllocationService::class, $budgetService);

        $workflowService = Mockery::mock(QuotationRejectionWorkflowService::class);
        $workflowService->shouldReceive('refreshRequisitionStatus')->andReturnNull();
        $this->app->instance(QuotationRejectionWorkflowService::class, $workflowService);

        $this->actingAs($approver)
            ->post(route('approvals.quotations.handle', $summary), ['status' => 'approved'])
            ->assertRedirect();

        $purchaseOrder = PurchaseOrder::where('requisition_id', $requisition->id)
            ->where('supplier_id', $supplier->id)
            ->first();

        $this->assertNotNull($purchaseOrder, 'Se esperaba que se generara una Orden de Compra.');

        $poItems = PurchaseOrderItem::where('purchase_order_id', $purchaseOrder->id)->get();

        $this->assertCount(1, $poItems, 'La ODC debe contener exactamente la partida disponible.');
        $this->assertEquals($itemA->id, $poItems->first()->requisition_item_id);
        $this->assertFalse(
            $poItems->pluck('requisition_item_id')->contains($itemB->id),
            'La partida not_available no debe aparecer en la ODC.'
        );
    }
}
