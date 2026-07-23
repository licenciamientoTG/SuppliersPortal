<?php

namespace Tests\Feature;

use App\Livewire\ContractRequisitionForm;
use App\Models\AnnualBudget;
use App\Models\BudgetCedula;
use App\Models\BudgetMonthlyDistribution;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractProduct;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Models\ProductService;
use App\Models\PurchaseOrder;
use App\Models\ReceivingLocation;
use App\Models\Requisition;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\PurchaseOrderIssuedNotification;
use App\Services\ContractPurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class ContractRequisitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_creates_one_issued_po_per_supplier_and_notifies_supplier(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $company = Company::factory()->create(['is_active' => true]);
        $contract = Contract::factory()->create([
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
            'status' => 'active',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $cp = ContractProduct::factory()->create([
            'contract_id' => $contract->id,
            'unit_price' => 200.00,
            'currency_code' => 'MXN',
        ]);

        $expenseCategory = ExpenseCategory::factory()->create();
        $budgetCedula = BudgetCedula::factory()->create(['expense_category_id' => $expenseCategory->id]);
        $costCenter = CostCenter::factory()->create([
            'company_id' => $company->id,
            'budget_type' => 'FREE_CONSUMPTION',
        ]);

        $requisition = Requisition::factory()->create([
            'source_type' => 'contract',
            'company_id' => $company->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'requested_by' => $user->id,
        ]);

        $requisition->items()->create([
            'product_service_id' => $cp->product_service_id,
            'description' => 'Producto test',
            'item_category' => 'PRODUCTO',
            'product_code' => 'TEST-001',
            'quantity' => 5,
            'unit' => 'PZA',
            'expense_category_id' => $expenseCategory->id,
            'budget_cedula_id' => $budgetCedula->id,
            'cost_center_id' => $costCenter->id,
            'contract_id' => $contract->id,
            'contract_product_id' => $cp->id,
            'unit_price' => $cp->unit_price,
            'currency_code' => 'MXN',
        ]);

        $this->actingAs($user);
        app(ContractPurchaseOrderService::class)->generateFromRequisition($requisition);

        $this->assertDatabaseCount('purchase_orders', 1);

        $po = PurchaseOrder::first();
        $this->assertEquals('contract', $po->source_type);
        $this->assertEquals($supplier->id, $po->supplier_id);
        $this->assertNull($po->quotation_summary_id);
        $this->assertSame('ISSUED', $po->status);
        $this->assertNotNull($po->approved_at);
        $this->assertNotNull($po->issued_at);
        $this->assertDatabaseCount('purchase_order_items', 1);
        $this->assertEquals(1000.00, (float) $po->subtotal);
        $this->assertEquals(160.00, (float) $po->iva_amount);
        $this->assertEquals(1160.00, (float) $po->total);
        $this->assertSame('COMPLETED', $requisition->fresh()->status->value);

        Notification::assertSentTo($supplier, PurchaseOrderIssuedNotification::class);
    }

    public function test_two_suppliers_create_two_pos(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $company = Company::factory()->create(['is_active' => true]);

        $supplier1 = Supplier::factory()->create();
        $supplier2 = Supplier::factory()->create();

        $contract1 = Contract::factory()->create([
            'supplier_id' => $supplier1->id,
            'company_id' => $company->id,
            'status' => 'active',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $contract2 = Contract::factory()->create([
            'supplier_id' => $supplier2->id,
            'company_id' => $company->id,
            'status' => 'active',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $cp1 = ContractProduct::factory()->create(['contract_id' => $contract1->id, 'unit_price' => 100, 'currency_code' => 'MXN']);
        $cp2 = ContractProduct::factory()->create(['contract_id' => $contract2->id, 'unit_price' => 200, 'currency_code' => 'MXN']);

        $expenseCategory = ExpenseCategory::factory()->create();
        $budgetCedula = BudgetCedula::factory()->create(['expense_category_id' => $expenseCategory->id]);
        $costCenter = CostCenter::factory()->create(['company_id' => $company->id]);

        $requisition = Requisition::factory()->create([
            'source_type' => 'contract',
            'company_id' => $company->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'requested_by' => $user->id,
        ]);

        foreach ([$cp1, $cp2] as $cp) {
            $requisition->items()->create([
                'product_service_id' => $cp->product_service_id,
                'description' => 'Test',
                'item_category' => 'PRODUCTO',
                'product_code' => 'TEST-'.$cp->id,
                'quantity' => 1,
                'unit' => 'PZA',
                'expense_category_id' => $expenseCategory->id,
                'budget_cedula_id' => $budgetCedula->id,
                'cost_center_id' => $costCenter->id,
                'contract_id' => $cp->contract_id,
                'contract_product_id' => $cp->id,
                'unit_price' => $cp->unit_price,
                'currency_code' => 'MXN',
            ]);
        }

        $this->actingAs($user);
        app(ContractPurchaseOrderService::class)->generateFromRequisition($requisition);

        $this->assertDatabaseCount('purchase_orders', 2);
        $this->assertDatabaseCount('purchase_order_items', 2);
        $this->assertDatabaseHas('purchase_orders', ['supplier_id' => $supplier1->id, 'status' => 'ISSUED']);
        $this->assertDatabaseHas('purchase_orders', ['supplier_id' => $supplier2->id, 'status' => 'ISSUED']);
    }

    public function test_service_blocks_mixed_currencies_for_same_supplier(): void
    {
        Notification::fake();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No se permite mezclar monedas distintas para el mismo proveedor');

        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $company = Company::factory()->create(['is_active' => true]);
        $contract = Contract::factory()->create([
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
            'status' => 'active',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $cp1 = ContractProduct::factory()->create(['contract_id' => $contract->id, 'unit_price' => 100, 'currency_code' => 'MXN']);
        $cp2 = ContractProduct::factory()->create(['contract_id' => $contract->id, 'unit_price' => 50, 'currency_code' => 'USD']);

        $expenseCategory = ExpenseCategory::factory()->create();
        $budgetCedula = BudgetCedula::factory()->create(['expense_category_id' => $expenseCategory->id]);
        $costCenter = CostCenter::factory()->create(['company_id' => $company->id]);

        $requisition = Requisition::factory()->create([
            'source_type' => 'contract',
            'company_id' => $company->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'requested_by' => $user->id,
        ]);

        foreach ([$cp1, $cp2] as $cp) {
            $requisition->items()->create([
                'product_service_id' => $cp->product_service_id,
                'description' => 'Test',
                'item_category' => 'PRODUCTO',
                'product_code' => 'TEST-'.$cp->id,
                'quantity' => 1,
                'unit' => 'PZA',
                'expense_category_id' => $expenseCategory->id,
                'budget_cedula_id' => $budgetCedula->id,
                'cost_center_id' => $costCenter->id,
                'contract_id' => $cp->contract_id,
                'contract_product_id' => $cp->id,
                'unit_price' => $cp->unit_price,
                'currency_code' => $cp->currency_code,
            ]);
        }

        $this->actingAs($user);
        app(ContractPurchaseOrderService::class)->generateFromRequisition($requisition);
    }

    public function test_livewire_submit_creates_completed_requisition_and_issued_po(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $company = Company::factory()->create(['is_active' => true]);
        $location = ReceivingLocation::factory()->create(['company_id' => $company->id]);
        $supplier = Supplier::factory()->create();

        $costCenter = CostCenter::factory()->create([
            'company_id' => $company->id,
            'budget_type' => 'ANNUAL',
            'created_by' => $user->id,
        ]);
        $user->companies()->attach($company->id);
        $user->costCenters()->attach($costCenter->id, [
            'is_default' => true,
            'is_active' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $expenseCategory = ExpenseCategory::factory()->create();
        $budgetCedula = BudgetCedula::factory()->create(['expense_category_id' => $expenseCategory->id]);

        $annualBudget = AnnualBudget::create([
            'cost_center_id' => $costCenter->id,
            'fiscal_year' => (int) now()->year,
            'total_annual_amount' => 50000,
            'status' => 'APROBADO',
            'approved_by' => $user->id,
            'approved_at' => now(),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        BudgetMonthlyDistribution::create([
            'annual_budget_id' => $annualBudget->id,
            'budget_cedula_id' => $budgetCedula->id,
            'expense_category_id' => $expenseCategory->id,
            'month' => (int) now()->month,
            'assigned_amount' => 50000,
            'consumed_amount' => 0,
            'committed_amount' => 0,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $product = ProductService::factory()->create([
            'product_type' => 'PRODUCTO',
            'code' => 'PROD-001',
            'short_name' => 'Bomba industrial',
            'technical_description' => 'Bomba industrial modelo X',
            'created_by' => $user->id,
        ]);

        $contract = Contract::factory()->create([
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
            'status' => 'active',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $contractProduct = ContractProduct::factory()->create([
            'contract_id' => $contract->id,
            'product_service_id' => $product->id,
            'unit_price' => 500,
            'currency_code' => 'MXN',
            'unit_of_measure' => 'PZA',
        ]);

        $this->actingAs($user);

        Livewire::test(ContractRequisitionForm::class)
            ->set('company_id', $company->id)
            ->set('required_date', now()->addDay()->toDateString())
            ->set('receiving_location_id', $location->id)
            ->set('newItem.contract_id', $contract->id)
            ->call('updatedNewItemContractId', $contract->id)
            ->set('newItem.contract_product_id', $contractProduct->id)
            ->set('newItem.quantity', 2)
            ->set('newItem.cost_center_id', $costCenter->id)
            ->call('updatedNewItemCostCenterId')
            ->set('newItem.expense_category_id', $expenseCategory->id)
            ->call('updatedNewItemExpenseCategoryId')
            ->set('newItem.budget_cedula_id', $budgetCedula->id)
            ->call('addItem')
            ->assertSet('items.0.product_name', 'Bomba industrial')
            ->call('submit')
            ->assertHasNoErrors();

        $requisition = Requisition::first();
        $this->assertNotNull($requisition);
        $this->assertSame('contract', $requisition->source_type);
        $this->assertSame('COMPLETED', $requisition->status->value);
        $this->assertDatabaseCount('requisition_items', 1);
        $this->assertDatabaseHas('requisition_items', [
            'requisition_id' => $requisition->id,
            'item_category' => 'PRODUCTO',
            'product_code' => 'PROD-001',
            'description' => 'Bomba industrial modelo X',
            'budget_cedula_id' => $budgetCedula->id,
            'cost_center_id' => $costCenter->id,
        ]);
        $this->assertDatabaseHas('purchase_orders', [
            'requisition_id' => $requisition->id,
            'supplier_id' => $supplier->id,
            'status' => 'ISSUED',
            'source_type' => 'contract',
        ]);
        Notification::assertSentTo($supplier, PurchaseOrderIssuedNotification::class);
    }
}
