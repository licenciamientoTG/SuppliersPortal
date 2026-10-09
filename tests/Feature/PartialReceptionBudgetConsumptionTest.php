<?php

namespace Tests\Feature;

use App\Models\AnnualBudget;
use App\Models\BudgetCedula;
use App\Models\BudgetCommitment;
use App\Models\BudgetMonthlyDistribution;
use App\Models\Category;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\BudgetAllocationService;
use App\Services\BudgetPositionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cubre REC-01: una recepción parcial debe consumir presupuesto en proporción
 * al valor efectivamente recibido, no el 100% del compromiso de la línea.
 */
class PartialReceptionBudgetConsumptionTest extends TestCase
{
    use RefreshDatabase;

    private BudgetAllocationService $service;

    private BudgetMonthlyDistribution $distribution;

    private PurchaseOrder $purchaseOrder;

    private PurchaseOrderItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(BudgetAllocationService::class);

        $user = User::factory()->create();

        $costCenter = CostCenter::factory()->create([
            'company_id' => Company::factory(),
            'category_id' => Category::factory(),
            'responsible_user_id' => $user->id,
            'budget_type' => 'ANNUAL',
            'global_amount' => 0,
            'status' => 'ACTIVO',
            'purchase_type' => 'Gasto Operativo',
        ]);

        $expenseCategory = ExpenseCategory::factory()->create();
        $budgetCedula = BudgetCedula::factory()->create([
            'expense_category_id' => $expenseCategory->id,
        ]);

        $annualBudget = AnnualBudget::create([
            'cost_center_id' => $costCenter->id,
            'fiscal_year' => (int) now()->format('Y'),
            'total_annual_amount' => 10000,
            'status' => 'APROBADO',
            'created_by' => $user->id,
        ]);

        $this->distribution = BudgetMonthlyDistribution::create([
            'annual_budget_id' => $annualBudget->id,
            'budget_cedula_id' => $budgetCedula->id,
            'expense_category_id' => $expenseCategory->id,
            'month' => (int) now()->format('m'),
            'assigned_amount' => 10000,
            'consumed_amount' => 0,
            'committed_amount' => 0,
            'created_by' => $user->id,
        ]);

        $requisition = Requisition::factory()->create([
            'requested_by' => $user->id,
            'created_by' => $user->id,
        ]);

        $requisitionItem = RequisitionItem::factory()->create([
            'requisition_id' => $requisition->id,
            'expense_category_id' => $expenseCategory->id,
            'budget_cedula_id' => $budgetCedula->id,
            'cost_center_id' => $costCenter->id,
            'quantity' => 10,
        ]);

        $this->purchaseOrder = PurchaseOrder::factory()->create([
            'requisition_id' => $requisition->id,
            'supplier_id' => Supplier::factory(),
            'status' => 'ISSUED',
        ]);

        $this->item = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $this->purchaseOrder->id,
            'requisition_item_id' => $requisitionItem->id,
            'quantity' => 10,
            'quantity_received' => 0,
            'unit_price' => 100,
            'subtotal' => 1000,
            'iva_amount' => 0,
            'total' => 1000,
        ]);

        $this->service->commitOrder($this->purchaseOrder);
        $this->distribution->refresh();

        $this->assertEquals(1000.00, (float) $this->distribution->committed_amount);
        $this->assertEquals(0.00, (float) $this->distribution->consumed_amount);
    }

    private function receive(float $cumulativeQuantity): void
    {
        $this->item->update(['quantity_received' => $cumulativeQuantity]);
        $this->service->consumeOrder($this->purchaseOrder->fresh());
        $this->distribution->refresh();
    }

    public function test_partial_reception_consumes_only_the_received_share_of_the_commitment(): void
    {
        $this->receive(4);

        $this->assertEquals(400.00, (float) $this->distribution->consumed_amount);
        $this->assertEquals(600.00, (float) $this->distribution->committed_amount);
    }

    public function test_partial_reception_keeps_the_commitment_open(): void
    {
        $this->receive(4);

        $commitment = BudgetCommitment::where('purchase_order_id', $this->purchaseOrder->id)->sole();

        $this->assertSame('COMMITTED', $commitment->status);
        $this->assertNull($commitment->received_at);
    }

    public function test_successive_partial_receptions_accumulate_without_double_consuming(): void
    {
        $this->receive(4);
        $this->receive(7);

        $this->assertEquals(700.00, (float) $this->distribution->consumed_amount);
        $this->assertEquals(300.00, (float) $this->distribution->committed_amount);
    }

    public function test_reception_completed_in_stages_ends_fully_consumed(): void
    {
        $this->receive(4);
        $this->receive(10);

        $this->assertEquals(1000.00, (float) $this->distribution->consumed_amount);
        $this->assertEquals(0.00, (float) $this->distribution->committed_amount);

        $commitment = BudgetCommitment::where('purchase_order_id', $this->purchaseOrder->id)->sole();

        $this->assertSame('RECEIVED', $commitment->status);
        $this->assertNotNull($commitment->received_at);
    }

    public function test_single_full_reception_consumes_the_whole_commitment(): void
    {
        $this->receive(10);

        $this->assertEquals(1000.00, (float) $this->distribution->consumed_amount);
        $this->assertEquals(0.00, (float) $this->distribution->committed_amount);

        $commitment = BudgetCommitment::where('purchase_order_id', $this->purchaseOrder->id)->sole();

        $this->assertSame('RECEIVED', $commitment->status);
    }

    public function test_consuming_again_after_full_reception_is_idempotent(): void
    {
        $this->receive(10);
        $this->service->consumeOrder($this->purchaseOrder->fresh());
        $this->distribution->refresh();

        $this->assertEquals(1000.00, (float) $this->distribution->consumed_amount);
        $this->assertEquals(0.00, (float) $this->distribution->committed_amount);
    }

    public function test_releasing_a_partially_received_order_frees_only_the_pending_share(): void
    {
        $this->receive(4);

        $this->service->releaseOrder($this->purchaseOrder->fresh());
        $this->distribution->refresh();

        $this->assertEquals(400.00, (float) $this->distribution->consumed_amount);
        $this->assertEquals(0.00, (float) $this->distribution->committed_amount);

        $commitment = BudgetCommitment::where('purchase_order_id', $this->purchaseOrder->id)->sole();

        $this->assertSame('RELEASED', $commitment->status);
        $this->assertEquals(400.00, (float) $commitment->consumed_amount);
    }

    public function test_releasing_a_partially_received_order_keeps_other_orders_commitments(): void
    {
        $this->receive(4);

        // Compromiso de otra OC sobre el mismo renglón.
        $this->distribution->update(['committed_amount' => (float) $this->distribution->committed_amount + 2000]);

        $this->service->releaseOrder($this->purchaseOrder->fresh());
        $this->distribution->refresh();

        $this->assertEquals(2000.00, (float) $this->distribution->committed_amount);
        $this->assertEquals(400.00, (float) $this->distribution->consumed_amount);
    }

    public function test_releasing_an_order_without_receptions_frees_the_whole_commitment(): void
    {
        $this->service->releaseOrder($this->purchaseOrder->fresh());
        $this->distribution->refresh();

        $this->assertEquals(0.00, (float) $this->distribution->committed_amount);
        $this->assertEquals(0.00, (float) $this->distribution->consumed_amount);
        $this->assertSame('RELEASED', BudgetCommitment::where('purchase_order_id', $this->purchaseOrder->id)->sole()->status);
    }

    public function test_budget_position_reports_only_the_freed_share_as_released(): void
    {
        $this->receive(4);
        $this->service->releaseOrder($this->purchaseOrder->fresh());

        $row = app(BudgetPositionService::class)->positions([])
            ->firstWhere('distribution_id', $this->distribution->id);

        $this->assertEquals(600.00, $row['released']);
        $this->assertEquals(400.00, $row['accrued']);
        $this->assertEquals(0.00, $row['committed']);
    }
}
