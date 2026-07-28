<?php

namespace Tests\Feature;

use App\Models\AuthorizerRole;
use App\Models\QuotationSummary;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Rfq;
use App\Models\RfqResponse;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AuthorizerResolutionService;
use App\Services\BudgetAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Caracteriza el comportamiento actual de RfqComparisonController::select
 * (adjudicación) antes de extraerlo a RfqAwardService.
 */
class RfqAwardBaselineTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private User $approver;

    private AuthorizerRole $authorizerRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            \App\Http\Middleware\ModuleAccess::class,
            \App\Http\Middleware\CheckLockScreen::class,
        ]);
        Role::findOrCreate('buyer', 'web');

        $this->buyer = User::factory()->create();
        $this->buyer->assignRole('buyer');
        $this->actingAs($this->buyer);

        $this->approver = User::factory()->create();
        $this->authorizerRole = AuthorizerRole::create([
            'name' => 'Gerencia',
            'approval_limit' => 10000,
            'display_order' => 1,
            'is_active' => true,
        ]);
    }

    /** @return array{0: Rfq, 1: Supplier, 2: RfqResponse} */
    private function makeAwardableRfq(array $responseOverrides = []): array
    {
        $requester = User::factory()->create();
        $requisition = Requisition::factory()->create([
            'requested_by' => $requester->id,
            'status' => 'IN_QUOTATION',
        ]);
        $item = RequisitionItem::factory()->create(['requisition_id' => $requisition->id]);

        $rfq = Rfq::factory()->create([
            'requisition_id' => $requisition->id,
            'quotation_group_id' => null,
            'requisition_item_id' => $item->id,
            'status' => 'RECEIVED',
            'response_deadline' => now()->addDays(7),
        ]);

        $supplier = Supplier::factory()->create();
        $rfq->suppliers()->attach($supplier->id, ['invited_at' => now(), 'responded_at' => now()]);

        $response = RfqResponse::factory()->create(array_merge([
            'rfq_id' => $rfq->id,
            'supplier_id' => $supplier->id,
            'requisition_item_id' => $item->id,
            'status' => 'SUBMITTED',
            'subtotal' => 200,
            'iva_amount' => 32,
            'total' => 232,
        ], $responseOverrides));

        return [$rfq, $supplier, $response];
    }

    private function mockApprovalDependencies(RequisitionItem $item): void
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
                'approver_user' => $this->approver,
                'authorizer_role' => $this->authorizerRole,
                'effective_limit' => 10000,
                'chain' => [['status' => 'eligible']],
                'resolution_notes' => 'Aprobador resuelto para prueba.',
            ]);
        $this->app->instance(AuthorizerResolutionService::class, $authorizerService);
    }

    public function test_award_creates_pending_summary_and_updates_rfq_and_requisition_statuses(): void
    {
        Notification::fake();

        [$rfq, $supplier, $response] = $this->makeAwardableRfq();
        $this->mockApprovalDependencies($response->requisitionItem);

        $this->post(route('rfq.comparison.select', $rfq), [
            'supplier_id' => $supplier->id,
            'justification' => 'Mejor precio y tiempo de entrega en la comparativa.',
        ])->assertRedirect(route('rfq.index'));

        $summary = QuotationSummary::where('rfq_id', $rfq->id)->firstOrFail();
        $this->assertEquals('pending', $summary->approval_status);
        $this->assertEquals($supplier->id, $summary->selected_supplier_id);
        $this->assertEquals($this->buyer->id, $summary->selected_by_user_id);
        $this->assertEquals($this->approver->id, $summary->current_approver_user_id);
        $this->assertEquals($this->authorizerRole->id, $summary->authorizer_role_id);
        $this->assertEquals(200.0, (float) $summary->subtotal);
        $this->assertEquals(32.0, (float) $summary->iva_amount);
        $this->assertEquals(232.0, (float) $summary->total);

        $this->assertEquals('EVALUATED', $rfq->fresh()->status);
        $this->assertEquals('QUOTED', $rfq->fresh()->requisition->status->value);
    }

    public function test_award_is_blocked_when_offer_is_expired(): void
    {
        Notification::fake();

        [$rfq, $supplier, $response] = $this->makeAwardableRfq([
            'quotation_date' => now()->subDays(60)->toDateString(),
            'validity_days' => 10,
        ]);
        $this->mockApprovalDependencies($response->requisitionItem);

        $this->post(route('rfq.comparison.select', $rfq), [
            'supplier_id' => $supplier->id,
            'justification' => 'Intento de adjudicar una oferta ya vencida.',
        ])->assertSessionHas('error');

        $this->assertNull(QuotationSummary::where('rfq_id', $rfq->id)->first());
        $this->assertEquals('RECEIVED', $rfq->fresh()->status);
    }

    public function test_award_is_blocked_when_supplier_has_no_submitted_responses(): void
    {
        Notification::fake();

        [$rfq, $supplier, $response] = $this->makeAwardableRfq();
        $this->mockApprovalDependencies($response->requisitionItem);

        $otherSupplier = Supplier::factory()->create();
        $rfq->suppliers()->attach($otherSupplier->id, ['invited_at' => now()]);

        $this->post(route('rfq.comparison.select', $rfq), [
            'supplier_id' => $otherSupplier->id,
            'justification' => 'Proveedor sin cotizaciones no debe poder adjudicarse.',
        ])->assertSessionHas('error');

        $this->assertNull(QuotationSummary::where('rfq_id', $rfq->id)->first());
    }
}
