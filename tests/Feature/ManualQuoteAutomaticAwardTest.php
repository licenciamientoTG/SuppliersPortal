<?php

namespace Tests\Feature;

use App\Models\AuthorizerRole;
use App\Models\QuotationGroup;
use App\Models\QuotationSummary;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AuthorizerResolutionService;
use App\Services\BudgetAllocationService;
use App\Services\Rfq\ManualQuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManualQuoteAutomaticAwardTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_known_creates_external_rfq_and_sends_direct_award_to_approval(): void
    {
        Notification::fake();

        Role::findOrCreate('buyer', 'web');
        $buyer = User::factory()->create();
        $buyer->assignRole('buyer');
        $requester = User::factory()->create();
        $approver = User::factory()->create();
        $role = AuthorizerRole::create([
            'name' => 'Gerencia',
            'approval_limit' => 10000,
            'display_order' => 1,
            'is_active' => true,
        ]);
        $requisition = Requisition::factory()->create([
            'requested_by' => $requester->id,
            'validated_at' => now(),
            'status' => 'IN_QUOTATION',
        ]);
        $group = QuotationGroup::factory()->create(['requisition_id' => $requisition->id]);
        $item = RequisitionItem::factory()->create(['requisition_id' => $requisition->id]);
        $group->items()->attach($item->id, ['sort_order' => 1]);
        $supplier = Supplier::factory()->create();

        $budget = Mockery::mock(BudgetAllocationService::class);
        $budget->shouldReceive('buildQuotationSummaryBudgetLines')->andReturn([[
            'cost_center_id' => $item->cost_center_id,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'expense_category_id' => $item->expense_category_id,
            'budget_cedula_id' => $item->budget_cedula_id,
            'amount' => 116.00,
        ]]);
        $budget->shouldReceive('checkAvailability')->andReturn(['available' => true, 'message' => null]);
        $budget->shouldReceive('reserveQuotationSummary')->once();
        $this->app->instance(BudgetAllocationService::class, $budget);

        $authorizers = Mockery::mock(AuthorizerResolutionService::class);
        $authorizers->shouldReceive('resolveForSummary')->andReturn([
            'approver_user' => $approver,
            'authorizer_role' => $role,
            'effective_limit' => 10000,
            'chain' => [['status' => 'eligible']],
            'resolution_notes' => 'Aprobador resuelto para prueba.',
        ]);
        $authorizers->shouldReceive('resolveForRequester')->andReturn([
            'approver_user' => $approver,
            'authorizer_role' => $role,
            'effective_limit' => 10000,
            'chain' => [['status' => 'eligible']],
            'resolution_notes' => 'Aprobador resuelto para prueba.',
        ]);
        $this->app->instance(AuthorizerResolutionService::class, $authorizers);

        $result = app(ManualQuoteService::class)->save(
            $requisition,
            $group,
            $supplier,
            [$item->id => ['unit_price' => 100, 'iva_rate' => 16, 'currency' => 'MXN', 'delivery_days' => 2]],
            now()->toDateString(),
            30,
            null,
            $buyer->id,
        );

        $this->assertNull($result['award_error']);
        $rfq = $result['rfq'];
        $this->assertSame('external', $rfq->source);
        $this->assertSame('EVALUATED', $rfq->status);

        $summary = QuotationSummary::where('rfq_id', $rfq->id)->firstOrFail();
        $this->assertSame('pending', $summary->approval_status);
        $this->assertSame($supplier->id, $summary->selected_supplier_id);
        $this->assertSame($approver->id, $summary->current_approver_user_id);
    }
}
