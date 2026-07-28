<?php

namespace Tests\Feature;

use App\Livewire\Rfq\Board\GroupCard;
use App\Models\AuthorizerRole;
use App\Models\QuotationGroup;
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
use Livewire\Livewire;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fase 5: adjudicación directa desde la tarjeta cuando todas las partidas
 * del grupo tienen precio capturado.
 */
class QuotationBoardDirectAwardTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private User $approver;

    private AuthorizerRole $authorizerRole;

    protected function setUp(): void
    {
        parent::setUp();

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

    /**
     * Grupo con RFQ RECEIVED y respuestas del proveedor para $coveredItems
     * de $totalItems partidas.
     *
     * @return array{0: Requisition, 1: QuotationGroup, 2: Supplier, 3: RequisitionItem}
     */
    private function makeGroupWithCapturedPrices(int $totalItems = 1, int $coveredItems = 1): array
    {
        $requisition = Requisition::factory()->create([
            'validated_at' => now(),
            'status' => 'IN_QUOTATION',
        ]);
        $group = QuotationGroup::factory()->create(['requisition_id' => $requisition->id]);
        $supplier = Supplier::factory()->create();

        $rfq = Rfq::factory()->create([
            'requisition_id' => $requisition->id,
            'quotation_group_id' => $group->id,
            'status' => 'RECEIVED',
        ]);
        $rfq->suppliers()->attach($supplier->id, ['invited_at' => now(), 'responded_at' => now()]);

        $firstItem = null;
        for ($i = 0; $i < $totalItems; $i++) {
            $item = RequisitionItem::factory()->create(['requisition_id' => $requisition->id]);
            $firstItem ??= $item;
            $group->items()->attach($item->id, ['sort_order' => $i + 1]);

            if ($i < $coveredItems) {
                RfqResponse::factory()->create([
                    'rfq_id' => $rfq->id,
                    'supplier_id' => $supplier->id,
                    'requisition_item_id' => $item->id,
                    'status' => 'SUBMITTED',
                    'entry_source' => 'buyer_manual',
                    'entered_by' => $this->buyer->id,
                    'subtotal' => 200,
                    'iva_amount' => 32,
                    'total' => 232,
                ]);
            }
        }

        return [$requisition, $group, $supplier, $firstItem];
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
            ->andReturn(['available' => true, 'message' => null, 'assigned_amount' => 1000, 'committed_amount' => 0, 'available_amount' => 1000]);
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

    public function test_award_button_available_only_with_full_price_coverage(): void
    {
        [$requisition, $group] = $this->makeGroupWithCapturedPrices(totalItems: 2, coveredItems: 1);

        $component = Livewire::test(GroupCard::class, ['requisition' => $requisition, 'groupId' => $group->id]);

        $this->assertCount(0, $component->instance()->awardableSuppliers);

        [$requisition2, $group2, $supplier2] = $this->makeGroupWithCapturedPrices(totalItems: 2, coveredItems: 2);

        $component2 = Livewire::test(GroupCard::class, ['requisition' => $requisition2, 'groupId' => $group2->id]);

        $this->assertEquals([$supplier2->id], $component2->instance()->awardableSuppliers->pluck('id')->all());
    }

    public function test_direct_award_creates_pending_summary_like_comparison_flow(): void
    {
        Notification::fake();

        [$requisition, $group, $supplier, $item] = $this->makeGroupWithCapturedPrices();
        $this->mockApprovalDependencies($item);

        Livewire::test(GroupCard::class, ['requisition' => $requisition, 'groupId' => $group->id])
            ->set('awardSupplierId', $supplier->id)
            ->set('awardJustification', 'Precio vigente capturado de cotización reciente.')
            ->call('awardDirect')
            ->assertHasNoErrors()
            ->assertDispatched('board-refresh');

        $rfq = Rfq::where('quotation_group_id', $group->id)->firstOrFail();

        $summary = QuotationSummary::where('rfq_id', $rfq->id)->firstOrFail();
        $this->assertEquals('pending', $summary->approval_status);
        $this->assertEquals($supplier->id, $summary->selected_supplier_id);
        $this->assertEquals($this->buyer->id, $summary->selected_by_user_id);
        $this->assertEquals($this->approver->id, $summary->current_approver_user_id);
        $this->assertEquals(232.0, (float) $summary->total);

        $this->assertEquals('EVALUATED', $rfq->fresh()->status);
        $this->assertEquals('QUOTED', $requisition->fresh()->status->value);

        Notification::assertSentTo($this->approver, \App\Notifications\QuotationApprovalRequestNotification::class);
    }

    public function test_direct_award_blocked_without_signed_validation(): void
    {
        Notification::fake();

        [$requisition, $group, $supplier, $item] = $this->makeGroupWithCapturedPrices();
        $requisition->update(['validated_at' => null]);
        $this->mockApprovalDependencies($item);

        Livewire::test(GroupCard::class, ['requisition' => $requisition->fresh(), 'groupId' => $group->id])
            ->set('awardSupplierId', $supplier->id)
            ->set('awardJustification', 'Intento sin validación técnica firmada.')
            ->call('awardDirect');

        $this->assertEquals(0, QuotationSummary::count());
        $this->assertEquals('RECEIVED', Rfq::where('quotation_group_id', $group->id)->first()->status);
    }

    public function test_direct_award_requires_justification(): void
    {
        [$requisition, $group, $supplier] = $this->makeGroupWithCapturedPrices();

        Livewire::test(GroupCard::class, ['requisition' => $requisition, 'groupId' => $group->id])
            ->set('awardSupplierId', $supplier->id)
            ->set('awardJustification', 'corta')
            ->call('awardDirect')
            ->assertHasErrors('awardJustification');

        $this->assertEquals(0, QuotationSummary::count());
    }
}
