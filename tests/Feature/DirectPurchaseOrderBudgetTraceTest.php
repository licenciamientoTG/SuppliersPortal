<?php

namespace Tests\Feature;

use App\Models\AnnualBudget;
use App\Models\BudgetCedula;
use App\Models\BudgetCommitment;
use App\Models\BudgetMonthlyDistribution;
use App\Models\Category;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\DirectPurchaseOrder;
use App\Models\DirectPurchaseOrderItem;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\BudgetAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Al emitir una OC directa, el registro de compromisos no debe revivir los
 * compromisos de rondas anteriores que ya se liberaron (caso OCD-2026-0004).
 */
class DirectPurchaseOrderBudgetTraceTest extends TestCase
{
    use RefreshDatabase;

    private BudgetAllocationService $service;

    private BudgetMonthlyDistribution $distribution;

    private DirectPurchaseOrder $order;

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
        ]);
        $expenseCategory = ExpenseCategory::factory()->create();
        $cedula = BudgetCedula::factory()->create(['expense_category_id' => $expenseCategory->id]);
        $annualBudget = AnnualBudget::create([
            'cost_center_id' => $costCenter->id,
            'fiscal_year' => (int) now()->format('Y'),
            'total_annual_amount' => 10000,
            'status' => 'APROBADO',
            'created_by' => $user->id,
        ]);
        $this->distribution = BudgetMonthlyDistribution::create([
            'annual_budget_id' => $annualBudget->id,
            'budget_cedula_id' => $cedula->id,
            'expense_category_id' => $expenseCategory->id,
            'month' => (int) now()->format('m'),
            'assigned_amount' => 10000,
            'consumed_amount' => 0,
            'committed_amount' => 0,
            'created_by' => $user->id,
        ]);

        $this->order = DirectPurchaseOrder::factory()->create(['status' => 'PENDING_APPROVAL', 'created_by' => $user->id]);
        DirectPurchaseOrderItem::factory()->create([
            'direct_purchase_order_id' => $this->order->id,
            'cost_center_id' => $costCenter->id,
            'expense_category_id' => $expenseCategory->id,
            'budget_cedula_id' => $cedula->id,
            'quantity' => 1,
            'unit_price' => 2076.40,
            'iva_rate' => 0,
            'subtotal' => 2076.40,
            'iva_amount' => 0,
            'total' => 2076.40,
        ]);
    }

    public function test_issuing_after_a_resubmission_keeps_a_single_open_commitment(): void
    {
        // Primera ronda de autorización y reenvío: se libera y se vuelve a apartar.
        $this->service->reserveDirectPurchaseOrder($this->order->fresh());
        $this->service->releaseDirectPurchaseOrder($this->order->fresh());
        $this->service->reserveDirectPurchaseOrder($this->order->fresh());

        $this->order->fresh()->update(['status' => 'ISSUED']);

        $this->assertEquals(2076.40, (float) $this->distribution->fresh()->committed_amount);
        $open = BudgetCommitment::where('direct_purchase_order_id', $this->order->id)->where('status', 'COMMITTED')->get();
        $this->assertCount(1, $open);
        $this->assertEquals(2076.40, (float) $open->sum('committed_amount'));
        $this->assertSame(1, BudgetCommitment::where('direct_purchase_order_id', $this->order->id)->where('status', 'RELEASED')->count());
    }

    public function test_issuing_keeps_the_current_commitment_open(): void
    {
        $this->service->reserveDirectPurchaseOrder($this->order->fresh());

        $this->order->fresh()->update(['status' => 'ISSUED']);

        $commitment = BudgetCommitment::where('direct_purchase_order_id', $this->order->id)->sole();
        $this->assertSame('COMMITTED', $commitment->status);
        $this->assertEquals(2076.40, (float) $this->distribution->fresh()->committed_amount);
    }
}
