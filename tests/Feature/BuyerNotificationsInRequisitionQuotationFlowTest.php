<?php

namespace Tests\Feature;

use App\Models\AuthorizerRole;
use App\Models\QuotationSummary;
use App\Models\ReceivingLocation;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Rfq;
use App\Models\RfqResponse;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\BuyerWorkflowNotification;
use App\Notifications\QuotationApprovalRequestNotification;
use App\Notifications\RfqCancelledForRequesterNotification;
use App\Notifications\RfqCancelledForSupplierNotification;
use App\Notifications\RfqSentToSuppliersNotification;
use App\Services\AuthorizerResolutionService;
use App\Services\BudgetAllocationService;
use App\Services\QuotationRejectionWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BuyerNotificationsInRequisitionQuotationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
        Role::findOrCreate('buyer', 'web');
    }

    public function test_buyers_are_notified_when_requisition_enters_quotation(): void
    {
        Notification::fake();

        [$actingBuyer, $secondBuyer] = $this->createBuyers()->all();
        $requester = User::factory()->create();
        $requisition = $this->createRequisition($requester, [
            'status' => 'PENDING',
        ]);

        $this->actingAs($actingBuyer)
            ->post(route('requisitions.validate', $requisition))
            ->assertRedirect();

        Notification::assertSentTo($actingBuyer, BuyerWorkflowNotification::class, fn (BuyerWorkflowNotification $notification) => $notification->type === 'buyer_requisition_in_quotation');
        Notification::assertSentTo($secondBuyer, BuyerWorkflowNotification::class, fn (BuyerWorkflowNotification $notification) => $notification->type === 'buyer_requisition_in_quotation');
        Notification::assertSentTo($requester, \App\Notifications\RequisitionInQuotationNotification::class);
        $this->assertCount(1, Notification::sent($actingBuyer, BuyerWorkflowNotification::class));
    }

    public function test_buyers_are_notified_when_rfq_is_sent_to_suppliers(): void
    {
        Notification::fake();

        [$actingBuyer, $secondBuyer] = $this->createBuyers()->all();
        $requester = User::factory()->create();
        $requisition = $this->createRequisition($requester, [
            'status' => 'IN_QUOTATION',
        ]);
        $rfq = $this->createRfq($requisition, [
            'status' => 'DRAFT',
            'created_by' => $actingBuyer->id,
            'updated_by' => $actingBuyer->id,
        ]);
        $suppliers = Supplier::factory()->count(2)->create();
        $rfq->suppliers()->attach($suppliers->pluck('id')->all());

        $this->actingAs($actingBuyer)
            ->post(route('rfq.send.single', $rfq))
            ->assertOk();

        Notification::assertSentTo($actingBuyer, BuyerWorkflowNotification::class, fn (BuyerWorkflowNotification $notification) => $notification->type === 'buyer_rfq_sent');
        Notification::assertSentTo($secondBuyer, BuyerWorkflowNotification::class, fn (BuyerWorkflowNotification $notification) => $notification->type === 'buyer_rfq_sent');
        Notification::assertSentTo($requester, RfqSentToSuppliersNotification::class);
        $suppliers->each(fn (Supplier $supplier) => Notification::assertSentTo($supplier, \App\Notifications\NewRfqForSupplierNotification::class));
        $this->assertCount(1, Notification::sent($actingBuyer, BuyerWorkflowNotification::class));
    }

    public function test_buyers_are_notified_when_adjudication_is_sent_for_approval(): void
    {
        Notification::fake();

        [$actingBuyer, $secondBuyer] = $this->createBuyers()->all();
        $requester = User::factory()->create();
        $approver = User::factory()->create();
        $authorizerRole = AuthorizerRole::create([
            'name' => 'Gerencia',
            'approval_limit' => 10000,
            'display_order' => 1,
            'is_active' => true,
        ]);

        $requisition = $this->createRequisition($requester, [
            'status' => 'IN_QUOTATION',
            'updated_by' => $actingBuyer->id,
        ]);
        $rfq = $this->createRfq($requisition, [
            'status' => 'SENT',
            'created_by' => $actingBuyer->id,
            'updated_by' => $actingBuyer->id,
        ]);
        $supplier = Supplier::factory()->create();
        $rfq->suppliers()->attach($supplier->id);
        $response = RfqResponse::factory()->create([
            'rfq_id' => $rfq->id,
            'supplier_id' => $supplier->id,
            'requisition_item_id' => $rfq->requisition_item_id,
            'status' => 'SUBMITTED',
            'subtotal' => 200,
            'iva_amount' => 32,
            'total' => 232,
        ]);

        $this->mockQuotationApprovalDependencies($response->requisitionItem, $approver, $authorizerRole);

        $this->actingAs($actingBuyer)
            ->post(route('rfq.comparison.select', $rfq), [
                'supplier_id' => $supplier->id,
                'justification' => 'Proveedor seleccionado por mejor precio y tiempo de entrega.',
            ])
            ->assertRedirect();

        Notification::assertSentTo($actingBuyer, BuyerWorkflowNotification::class, fn (BuyerWorkflowNotification $notification) => $notification->type === 'buyer_quotation_pending_approval');
        Notification::assertSentTo($secondBuyer, BuyerWorkflowNotification::class, fn (BuyerWorkflowNotification $notification) => $notification->type === 'buyer_quotation_pending_approval');
        Notification::assertSentTo($approver, QuotationApprovalRequestNotification::class);
        $this->assertCount(1, Notification::sent($actingBuyer, BuyerWorkflowNotification::class));
    }

    public function test_buyers_are_notified_when_rejected_rfq_is_reawarded_for_approval(): void
    {
        Notification::fake();

        [$actingBuyer, $secondBuyer] = $this->createBuyers()->all();
        $requester = User::factory()->create();
        $approver = User::factory()->create();
        $authorizerRole = AuthorizerRole::create([
            'name' => 'Dirección',
            'approval_limit' => 25000,
            'display_order' => 1,
            'is_active' => true,
        ]);

        $requisition = $this->createRequisition($requester, [
            'status' => 'IN_QUOTATION',
            'updated_by' => $actingBuyer->id,
        ]);
        $rfq = $this->createRfq($requisition, [
            'status' => 'REJECTED',
            'rejected_by' => $actingBuyer->id,
            'rejected_at' => now(),
            'rejection_reason' => 'Primera adjudicación rechazada.',
            'created_by' => $actingBuyer->id,
            'updated_by' => $actingBuyer->id,
        ]);
        $supplier = Supplier::factory()->create();
        $rfq->suppliers()->attach($supplier->id);
        $response = RfqResponse::factory()->create([
            'rfq_id' => $rfq->id,
            'supplier_id' => $supplier->id,
            'requisition_item_id' => $rfq->requisition_item_id,
            'status' => 'SUBMITTED',
            'subtotal' => 300,
            'iva_amount' => 48,
            'total' => 348,
        ]);

        $this->mockQuotationApprovalDependencies($response->requisitionItem, $approver, $authorizerRole);

        $replacementSummary = QuotationSummary::factory()->create([
            'requisition_id' => $requisition->id,
            'rfq_id' => $rfq->id,
            'selected_supplier_id' => $supplier->id,
            'requested_by_user_id' => $requester->id,
            'selected_by_user_id' => $actingBuyer->id,
            'current_approver_user_id' => $approver->id,
            'authorizer_role_id' => $authorizerRole->id,
            'subtotal' => 300,
            'iva_amount' => 48,
            'total' => 348,
            'approval_status' => 'pending',
            'approval_chain_snapshot' => [['status' => 'eligible']],
        ]);
        $replacementSummary->setRelation('currentApprover', $approver);
        $replacementSummary->setRelation('selectedSupplier', $supplier);
        $replacementSummary->setRelation('rfq', $rfq);
        $replacementSummary->setRelation('requisition', $requisition);

        $workflowService = Mockery::mock(QuotationRejectionWorkflowService::class, [
            app(BudgetAllocationService::class),
            app(AuthorizerResolutionService::class),
            app(\App\Services\BuyerNotificationService::class),
        ])->makePartial();
        $workflowService->shouldReceive('reawardRejectedQuotation')
            ->once()
            ->andReturn($replacementSummary);
        $this->app->instance(QuotationRejectionWorkflowService::class, $workflowService);

        $this->actingAs($actingBuyer)
            ->post(route('rfq.comparison.reaward', $rfq), [
                'supplier_id' => $supplier->id,
                'justification' => 'Se re-adjudica al proveedor alterno con mejor cobertura.',
            ])
            ->assertRedirect();

        Notification::assertSentTo($actingBuyer, BuyerWorkflowNotification::class, fn (BuyerWorkflowNotification $notification) => $notification->type === 'buyer_quotation_pending_approval' && ($notification->context['reawarded'] ?? false) === true);
        Notification::assertSentTo($secondBuyer, BuyerWorkflowNotification::class, fn (BuyerWorkflowNotification $notification) => $notification->type === 'buyer_quotation_pending_approval');
        Notification::assertSentTo($approver, QuotationApprovalRequestNotification::class);
        $this->assertCount(1, Notification::sent($actingBuyer, BuyerWorkflowNotification::class));
    }

    public function test_buyers_are_notified_when_rejected_rfq_is_cancelled(): void
    {
        Notification::fake();

        [$actingBuyer, $secondBuyer] = $this->createBuyers()->all();
        $requester = User::factory()->create();
        $requisition = $this->createRequisition($requester, [
            'status' => 'IN_QUOTATION',
            'updated_by' => $actingBuyer->id,
        ]);
        $rfq = $this->createRfq($requisition, [
            'status' => 'REJECTED',
            'rejected_by' => $actingBuyer->id,
            'rejected_at' => now(),
            'rejection_reason' => 'Sin autorización.',
            'created_by' => $actingBuyer->id,
            'updated_by' => $actingBuyer->id,
        ]);
        $suppliers = Supplier::factory()->count(2)->create();
        $rfq->suppliers()->attach($suppliers->pluck('id')->all());

        $service = app(QuotationRejectionWorkflowService::class);
        $service->cancelRejectedRfq($rfq->fresh(['requisition.requester', 'suppliers']), $actingBuyer->id, 'Se cancela la RFQ rechazada.');

        Notification::assertSentTo($actingBuyer, BuyerWorkflowNotification::class, fn (BuyerWorkflowNotification $notification) => $notification->type === 'buyer_rfq_cancelled');
        Notification::assertSentTo($secondBuyer, BuyerWorkflowNotification::class, fn (BuyerWorkflowNotification $notification) => $notification->type === 'buyer_rfq_cancelled');
        Notification::assertSentTo($requester, RfqCancelledForRequesterNotification::class);
        $suppliers->each(fn (Supplier $supplier) => Notification::assertSentTo($supplier, RfqCancelledForSupplierNotification::class));
        $this->assertCount(1, Notification::sent($actingBuyer, BuyerWorkflowNotification::class));
    }

    public function test_buyers_are_notified_when_requisition_is_cancelled_from_purchasing(): void
    {
        Notification::fake();

        [$actingBuyer, $secondBuyer] = $this->createBuyers()->all();
        $requester = User::factory()->create();
        $requisition = $this->createRequisition($requester, [
            'status' => 'IN_QUOTATION',
            'updated_by' => $actingBuyer->id,
        ]);
        $rfq = $this->createRfq($requisition, [
            'status' => 'SENT',
            'created_by' => $actingBuyer->id,
            'updated_by' => $actingBuyer->id,
        ]);
        $suppliers = Supplier::factory()->count(2)->create();
        $rfq->suppliers()->attach($suppliers->pluck('id')->all());

        $service = app(QuotationRejectionWorkflowService::class);
        $service->cancelRequisitionFromPurchasing($requisition->fresh(['requester', 'rfqs.suppliers']), $actingBuyer->id, 'Se cancela la requisición desde Compras.');

        Notification::assertSentTo($actingBuyer, BuyerWorkflowNotification::class, fn (BuyerWorkflowNotification $notification) => $notification->type === 'buyer_requisition_cancelled');
        Notification::assertSentTo($secondBuyer, BuyerWorkflowNotification::class, fn (BuyerWorkflowNotification $notification) => $notification->type === 'buyer_requisition_cancelled');
        Notification::assertSentTo($requester, RfqCancelledForRequesterNotification::class);
        $suppliers->each(fn (Supplier $supplier) => Notification::assertSentTo($supplier, RfqCancelledForSupplierNotification::class));
        $this->assertCount(1, Notification::sent($actingBuyer, BuyerWorkflowNotification::class));
    }

    private function createBuyers(): Collection
    {
        $buyers = User::factory()->count(2)->create();
        $buyers->each(fn (User $buyer) => $buyer->assignRole('buyer'));

        return $buyers;
    }

    private function createRequisition(User $requester, array $overrides = []): Requisition
    {
        return Requisition::factory()->create(array_merge([
            'requested_by' => $requester->id,
            'created_by' => $requester->id,
            'updated_by' => $requester->id,
            'company_id' => \App\Models\Company::factory()->create()->id,
            'receiving_location_id' => ReceivingLocation::factory()->create()->id,
            'required_date' => now()->addDays(5)->toDateString(),
            'status' => 'PENDING',
        ], $overrides));
    }

    private function createRfq(Requisition $requisition, array $overrides = []): Rfq
    {
        $item = RequisitionItem::factory()->create([
            'requisition_id' => $requisition->id,
        ]);

        return Rfq::factory()->create(array_merge([
            'requisition_id' => $requisition->id,
            'quotation_group_id' => null,
            'requisition_item_id' => $item->id,
            'supplier_id' => null,
            'response_deadline' => now()->addDays(7),
            'status' => 'DRAFT',
        ], $overrides));
    }

    private function mockQuotationApprovalDependencies(RequisitionItem $item, User $approver, AuthorizerRole $authorizerRole): void
    {
        $budgetService = Mockery::mock(BudgetAllocationService::class);
        $budgetService->shouldReceive('buildQuotationSummaryBudgetLines')
            ->andReturn([[
                'cost_center_id' => $item->cost_center_id,
                'year' => (int) now()->year,
                'month' => (int) now()->month,
                'application_month' => now()->format('Y-m'),
                'expense_category_id' => $item->expense_category_id,
                'budget_cedula_id' => $item->budget_cedula_id,
                'amount' => 232.00,
            ]]);
        $budgetService->shouldReceive('checkAvailability')
            ->andReturn([
                'available' => true,
                'message' => null,
                'assigned_amount' => 1000,
                'committed_amount' => 0,
                'available_amount' => 1000,
            ]);
        $budgetService->shouldReceive('reserveQuotationSummary')->andReturnNull();
        $this->app->instance(BudgetAllocationService::class, $budgetService);

        $authorizerService = Mockery::mock(AuthorizerResolutionService::class);
        $authorizerService->shouldReceive('resolveForSummary')
            ->andReturn([
                'approver_user' => $approver,
                'authorizer_role' => $authorizerRole,
                'effective_limit' => 10000,
                'chain' => [['status' => 'eligible']],
                'resolution_notes' => 'Aprobador resuelto para prueba.',
            ]);
        $this->app->instance(AuthorizerResolutionService::class, $authorizerService);
    }
}
