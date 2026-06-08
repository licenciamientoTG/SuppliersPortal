<?php

namespace Tests\Feature;

use App\Models\CostCenter;
use App\Models\Category;
use App\Models\Company;
use App\Models\DirectPurchaseOrder;
use App\Models\DirectPurchaseOrderItem;
use App\Models\ExpenseCategory;
use App\Models\Supplier;
use App\Models\ReceivingLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectPurchaseOrderPerItemCostCenterTest extends TestCase
{
    use RefreshDatabase;

    private function makeSetup(): array
    {
        $user          = User::factory()->create();
        $company       = Company::factory()->create();
        $category      = Category::factory()->create();
        $costCenter    = CostCenter::factory()->create([
            'company_id'          => $company->id,
            'category_id'         => $category->id,
            'responsible_user_id' => $user->id,
            'budget_type'         => 'FREE_CONSUMPTION',
            'global_amount'       => 999999,
            'status'              => 'ACTIVO',
            'purchase_type'       => 'Gasto Operativo',
        ]);
        $user->costCenters()->attach($costCenter->id, [
            'is_active'  => true,
            'is_default' => false,
            'created_by' => $user->id,
        ]);

        $supplier          = Supplier::factory()->create();
        $receivingLocation = ReceivingLocation::factory()->create();
        $expenseCategory   = ExpenseCategory::factory()->create();

        return compact('user', 'costCenter', 'supplier', 'receivingLocation', 'expenseCategory');
    }

    public function test_odc_header_does_not_have_cost_center_id_column(): void
    {
        // Verify the schema no longer has cost_center_id on the header
        $this->assertFalse(\Schema::hasColumn('odc_direct_purchase_orders', 'cost_center_id'));
        $this->assertTrue(\Schema::hasColumn('odc_direct_purchase_order_items', 'cost_center_id'));
    }

    public function test_direct_purchase_order_item_stores_cost_center_id(): void
    {
        ['user' => $user, 'costCenter' => $costCenter, 'supplier' => $supplier,
         'receivingLocation' => $receivingLocation, 'expenseCategory' => $expenseCategory] = $this->makeSetup();

        $ocd = DirectPurchaseOrder::factory()->create([
            'supplier_id'       => $supplier->id,
            'created_by'        => $user->id,
            'application_month' => now()->format('Y-m'),
        ]);

        $item = DirectPurchaseOrderItem::factory()->create([
            'direct_purchase_order_id' => $ocd->id,
            'expense_category_id'      => $expenseCategory->id,
            'cost_center_id'           => $costCenter->id,
            'total'                    => 1000,
        ]);

        $this->assertEquals($costCenter->id, $item->fresh()->cost_center_id);
        $this->assertInstanceOf(CostCenter::class, $item->costCenter);
    }

    public function test_each_item_can_have_different_cost_center(): void
    {
        ['user' => $user, 'supplier' => $supplier,
         'receivingLocation' => $receivingLocation, 'expenseCategory' => $expenseCategory] = $this->makeSetup();

        $company   = Company::factory()->create();
        $category  = Category::factory()->create();
        $cc1       = CostCenter::factory()->create([
            'company_id'          => $company->id,
            'category_id'         => $category->id,
            'responsible_user_id' => $user->id,
            'budget_type'         => 'FREE_CONSUMPTION',
            'global_amount'       => 999999,
            'status'              => 'ACTIVO',
            'purchase_type'       => 'Gasto Operativo',
        ]);
        $cc2 = CostCenter::factory()->create([
            'company_id'          => $company->id,
            'category_id'         => $category->id,
            'responsible_user_id' => $user->id,
            'budget_type'         => 'FREE_CONSUMPTION',
            'global_amount'       => 999999,
            'status'              => 'ACTIVO',
            'purchase_type'       => 'Gasto Operativo',
        ]);

        $ocd = DirectPurchaseOrder::factory()->create([
            'supplier_id'       => $supplier->id,
            'created_by'        => $user->id,
            'application_month' => now()->format('Y-m'),
        ]);

        DirectPurchaseOrderItem::factory()->create([
            'direct_purchase_order_id' => $ocd->id,
            'expense_category_id'      => $expenseCategory->id,
            'cost_center_id'           => $cc1->id,
            'total'                    => 500,
        ]);

        DirectPurchaseOrderItem::factory()->create([
            'direct_purchase_order_id' => $ocd->id,
            'expense_category_id'      => $expenseCategory->id,
            'cost_center_id'           => $cc2->id,
            'total'                    => 800,
        ]);

        $ocd->refresh()->load('items');
        $this->assertCount(2, $ocd->items);
        $this->assertEquals($cc1->id, $ocd->items[0]->cost_center_id);
        $this->assertEquals($cc2->id, $ocd->items[1]->cost_center_id);
    }
}
