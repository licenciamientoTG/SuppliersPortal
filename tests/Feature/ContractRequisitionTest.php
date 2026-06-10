<?php

namespace Tests\Feature;

use App\Models\BudgetCedula;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractProduct;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Models\PurchaseOrder;
use App\Models\Requisition;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ContractPurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractRequisitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_creates_one_po_per_supplier(): void
    {
        $user     = User::factory()->create();
        $supplier = Supplier::factory()->create(['status' => 'activo']);
        $company  = Company::factory()->create(['is_active' => true]);
        $contract = Contract::factory()->create([
            'supplier_id' => $supplier->id,
            'company_id'  => $company->id,
            'status'      => 'active',
        ]);
        $cp = ContractProduct::factory()->create([
            'contract_id'   => $contract->id,
            'unit_price'    => 200.00,
            'currency_code' => 'MXN',
        ]);

        $expenseCategory = ExpenseCategory::factory()->create();
        $budgetCedula    = BudgetCedula::factory()->create(['expense_category_id' => $expenseCategory->id]);
        $costCenter      = CostCenter::factory()->create();

        $requisition = Requisition::factory()->create([
            'source_type' => 'contract',
            'company_id'  => $company->id,
            'created_by'  => $user->id,
            'updated_by'  => $user->id,
        ]);

        $requisition->items()->create([
            'product_service_id'  => $cp->product_service_id,
            'description'         => 'Producto test',
            'item_category'       => 'producto',
            'product_code'        => 'TEST-001',
            'quantity'            => 5,
            'unit'                => 'PZA',
            'expense_category_id' => $expenseCategory->id,
            'budget_cedula_id'    => $budgetCedula->id,
            'cost_center_id'      => $costCenter->id,
            'contract_id'         => $contract->id,
            'contract_product_id' => $cp->id,
            'unit_price'          => $cp->unit_price,
            'currency_code'       => 'MXN',
        ]);

        $this->actingAs($user);
        app(ContractPurchaseOrderService::class)->generateFromRequisition($requisition);

        $this->assertDatabaseCount('purchase_orders', 1);
        $po = PurchaseOrder::first();
        $this->assertEquals('contract', $po->source_type);
        $this->assertEquals($supplier->id, $po->supplier_id);
        $this->assertNull($po->quotation_summary_id);
        $this->assertDatabaseCount('purchase_order_items', 1);
        $this->assertEquals(1000.00, $po->subtotal);
        $this->assertEquals(160.00, $po->iva_amount);
        $this->assertEquals(1160.00, $po->total);
    }

    public function test_two_suppliers_create_two_pos(): void
    {
        $user    = User::factory()->create();
        $company = Company::factory()->create(['is_active' => true]);

        $supplier1 = Supplier::factory()->create(['status' => 'activo']);
        $supplier2 = Supplier::factory()->create(['status' => 'activo']);

        $contract1 = Contract::factory()->create(['supplier_id' => $supplier1->id, 'company_id' => $company->id, 'status' => 'active']);
        $contract2 = Contract::factory()->create(['supplier_id' => $supplier2->id, 'company_id' => $company->id, 'status' => 'active']);
        $cp1 = ContractProduct::factory()->create(['contract_id' => $contract1->id, 'unit_price' => 100]);
        $cp2 = ContractProduct::factory()->create(['contract_id' => $contract2->id, 'unit_price' => 200]);

        $expenseCategory = ExpenseCategory::factory()->create();
        $budgetCedula    = BudgetCedula::factory()->create(['expense_category_id' => $expenseCategory->id]);
        $costCenter      = CostCenter::factory()->create();

        $requisition = Requisition::factory()->create([
            'source_type' => 'contract',
            'company_id'  => $company->id,
            'created_by'  => $user->id,
            'updated_by'  => $user->id,
        ]);

        foreach ([$cp1, $cp2] as $cp) {
            $requisition->items()->create([
                'product_service_id'  => $cp->product_service_id,
                'description'         => 'Test',
                'item_category'       => 'producto',
                'product_code'        => 'TEST-' . $cp->id,
                'quantity'            => 1,
                'unit'                => 'PZA',
                'expense_category_id' => $expenseCategory->id,
                'budget_cedula_id'    => $budgetCedula->id,
                'cost_center_id'      => $costCenter->id,
                'contract_id'         => $cp->contract_id,
                'contract_product_id' => $cp->id,
                'unit_price'          => $cp->unit_price,
                'currency_code'       => 'MXN',
            ]);
        }

        $this->actingAs($user);
        app(ContractPurchaseOrderService::class)->generateFromRequisition($requisition);

        $this->assertDatabaseCount('purchase_orders', 2);
        $this->assertDatabaseCount('purchase_order_items', 2);
        $this->assertDatabaseHas('purchase_orders', ['supplier_id' => $supplier1->id]);
        $this->assertDatabaseHas('purchase_orders', ['supplier_id' => $supplier2->id]);
    }
}
