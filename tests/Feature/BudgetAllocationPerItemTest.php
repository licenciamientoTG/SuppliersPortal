<?php

namespace Tests\Feature;

use App\Models\AnnualBudget;
use App\Models\BudgetCedula;
use App\Models\BudgetMonthlyDistribution;
use App\Models\Category;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\DirectPurchaseOrder;
use App\Models\DirectPurchaseOrderItem;
use App\Models\ExpenseCategory;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Rfq;
use App\Models\RfqResponse;
use App\Models\QuotationGroup;
use App\Models\QuotationSummary;
use App\Models\Supplier;
use App\Models\User;
use App\Services\BudgetAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetAllocationPerItemTest extends TestCase
{
    use RefreshDatabase;

    private BudgetAllocationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BudgetAllocationService::class);
    }

    private function makeFreeConsumptionCostCenter(): CostCenter
    {
        $company  = Company::factory()->create();
        $category = Category::factory()->create();
        $user     = User::factory()->create();

        return CostCenter::factory()->create([
            'company_id'          => $company->id,
            'category_id'         => $category->id,
            'responsible_user_id' => $user->id,
            'budget_type'         => 'FREE_CONSUMPTION',
            'global_amount'       => 999999,
            'status'              => 'ACTIVO',
            'purchase_type'       => 'Gasto Operativo',
        ]);
    }

    public function test_build_quotation_summary_budget_lines_uses_item_cost_center(): void
    {
        $costCenter1 = $this->makeFreeConsumptionCostCenter();
        $costCenter2 = $this->makeFreeConsumptionCostCenter();

        $expenseCategory = ExpenseCategory::factory()->create();
        $supplier        = Supplier::factory()->create();
        $user            = User::factory()->create();

        $requisition = Requisition::factory()->create([
            'requested_by' => $user->id,
            'created_by'   => $user->id,
        ]);

        $budgetCedula = BudgetCedula::factory()->create([
            'expense_category_id' => $expenseCategory->id,
        ]);

        $item1 = RequisitionItem::factory()->create([
            'requisition_id'      => $requisition->id,
            'expense_category_id' => $expenseCategory->id,
            'budget_cedula_id'    => $budgetCedula->id,
            'cost_center_id'      => $costCenter1->id,
            'quantity'            => 1,
        ]);

        $item2 = RequisitionItem::factory()->create([
            'requisition_id'      => $requisition->id,
            'expense_category_id' => $expenseCategory->id,
            'budget_cedula_id'    => $budgetCedula->id,
            'cost_center_id'      => $costCenter2->id,
            'quantity'            => 1,
        ]);

        $quotationGroup = QuotationGroup::factory()->create(['requisition_id' => $requisition->id]);
        $rfq = Rfq::factory()->create(['requisition_id' => $requisition->id, 'quotation_group_id' => $quotationGroup->id]);

        $response1 = RfqResponse::factory()->create([
            'rfq_id'               => $rfq->id,
            'supplier_id'          => $supplier->id,
            'requisition_item_id'  => $item1->id,
            'status'               => 'SUBMITTED',
            'total'                => 1000.00,
            'quotation_date'       => now()->toDateString(),
            'delivery_days'        => 5,
        ]);

        $response2 = RfqResponse::factory()->create([
            'rfq_id'               => $rfq->id,
            'supplier_id'          => $supplier->id,
            'requisition_item_id'  => $item2->id,
            'status'               => 'SUBMITTED',
            'total'                => 2000.00,
            'quotation_date'       => now()->toDateString(),
            'delivery_days'        => 5,
        ]);

        $summary = QuotationSummary::factory()->create([
            'requisition_id'       => $requisition->id,
            'rfq_id'               => $rfq->id,
            'selected_supplier_id' => $supplier->id,
        ]);

        $lines = $this->service->buildQuotationSummaryBudgetLines($summary);

        $this->assertCount(2, $lines);
        $costCenterIds = collect($lines)->pluck('cost_center_id')->unique()->sort()->values()->toArray();
        $this->assertEquals(
            collect([$costCenter1->id, $costCenter2->id])->sort()->values()->toArray(),
            $costCenterIds
        );
    }

    public function test_get_order_budget_lines_purchase_order_uses_item_cost_center(): void
    {
        $costCenter1 = $this->makeFreeConsumptionCostCenter();
        $costCenter2 = $this->makeFreeConsumptionCostCenter();

        $expenseCategory = ExpenseCategory::factory()->create();
        $supplier        = Supplier::factory()->create();
        $user            = User::factory()->create();

        $requisition = Requisition::factory()->create(['requested_by' => $user->id, 'created_by' => $user->id]);

        $reqItem1 = RequisitionItem::factory()->create([
            'requisition_id'      => $requisition->id,
            'expense_category_id' => $expenseCategory->id,
            'cost_center_id'      => $costCenter1->id,
        ]);

        $reqItem2 = RequisitionItem::factory()->create([
            'requisition_id'      => $requisition->id,
            'expense_category_id' => $expenseCategory->id,
            'cost_center_id'      => $costCenter2->id,
        ]);

        $purchaseOrder = \App\Models\PurchaseOrder::factory()->create([
            'requisition_id' => $requisition->id,
            'supplier_id'    => $supplier->id,
        ]);

        \App\Models\PurchaseOrderItem::factory()->create([
            'purchase_order_id'   => $purchaseOrder->id,
            'requisition_item_id' => $reqItem1->id,
            'total'               => 500,
        ]);

        \App\Models\PurchaseOrderItem::factory()->create([
            'purchase_order_id'   => $purchaseOrder->id,
            'requisition_item_id' => $reqItem2->id,
            'total'               => 800,
        ]);

        $ref = new \ReflectionMethod(BudgetAllocationService::class, 'getOrderBudgetLines');
        $ref->setAccessible(true);
        $lines = $ref->invoke($this->service, $purchaseOrder);

        $costCenterIds = collect($lines)->pluck('cost_center_id')->unique()->sort()->values()->toArray();
        $this->assertEquals(
            collect([$costCenter1->id, $costCenter2->id])->sort()->values()->toArray(),
            $costCenterIds
        );
    }

    public function test_get_order_budget_lines_direct_purchase_order_uses_item_cost_center(): void
    {
        $costCenter1 = $this->makeFreeConsumptionCostCenter();
        $costCenter2 = $this->makeFreeConsumptionCostCenter();

        $expenseCategory = ExpenseCategory::factory()->create();
        $supplier        = Supplier::factory()->create();
        $user            = User::factory()->create();

        $ocd = DirectPurchaseOrder::factory()->create([
            'supplier_id'       => $supplier->id,
            'created_by'        => $user->id,
            'application_month' => now()->format('Y-m'),
        ]);

        DirectPurchaseOrderItem::factory()->create([
            'direct_purchase_order_id' => $ocd->id,
            'expense_category_id'      => $expenseCategory->id,
            'cost_center_id'           => $costCenter1->id,
            'total'                    => 1500,
        ]);

        DirectPurchaseOrderItem::factory()->create([
            'direct_purchase_order_id' => $ocd->id,
            'expense_category_id'      => $expenseCategory->id,
            'cost_center_id'           => $costCenter2->id,
            'total'                    => 2500,
        ]);

        $ref = new \ReflectionMethod(BudgetAllocationService::class, 'getOrderBudgetLines');
        $ref->setAccessible(true);
        $lines = $ref->invoke($this->service, $ocd);

        $this->assertCount(2, $lines);
        $costCenterIds = collect($lines)->pluck('cost_center_id')->unique()->sort()->values()->toArray();
        $this->assertEquals(
            collect([$costCenter1->id, $costCenter2->id])->sort()->values()->toArray(),
            $costCenterIds
        );
    }
}
