<?php

namespace Tests\Feature;

use App\Models\AuthorizerRole;
use App\Models\ApprovalDelegation;
use App\Models\ApprovalDelegationMember;
use App\Models\BudgetCedula;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractProduct;
use App\Models\CostCenter;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\PurchaseOrder;
use App\Models\Requisition;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserAuthorizerRole;
use App\Notifications\ContractPurchaseOrderPendingApprovalNotification;
use App\Notifications\ContractPurchaseOrderRejectedNotification;
use App\Notifications\PurchaseOrderIssuedNotification;
use App\Services\ContractPurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ContractConvenioAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;
    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();

        \Spatie\Permission\Models\Role::findOrCreate('authorizer', 'web');
        \Spatie\Permission\Models\Role::findOrCreate('buyer', 'web');

        $this->requester = User::factory()->create();
        $this->approver  = User::factory()->create(['is_active' => true]);
        $this->approver->assignRole('authorizer');

        $role = AuthorizerRole::create([
            'name'           => 'Gerencia Compras',
            'approval_limit' => 50000,
            'display_order'  => 1,
            'is_active'      => true,
        ]);
        UserAuthorizerRole::create([
            'user_id'            => $this->approver->id,
            'authorizer_role_id' => $role->id,
        ]);

        $leaderEmployee = Employee::factory()->create([
            'user_id'         => $this->approver->id,
            'employee_number' => '1000',
            'is_active'       => 'SI',
        ]);
        Employee::factory()->create([
            'user_id'         => $this->requester->id,
            'employee_number' => '2000',
            'leader'          => 'Lider Prueba',
            'leader_id'       => $leaderEmployee->id,
            'is_active'       => 'SI',
        ]);
    }

    /**
     * Crea contrato + requisición con una partida ligada; devuelve [requisition, supplier].
     */
    private function makeContractPurchase(string $contractType): array
    {
        $supplier = Supplier::factory()->create();
        $company  = Company::factory()->create(['is_active' => true]);
        $contract = Contract::factory()->create([
            'supplier_id'   => $supplier->id,
            'company_id'    => $company->id,
            'contract_type' => $contractType,
            'status'        => 'active',
            'created_by'    => $this->requester->id,
            'updated_by'    => $this->requester->id,
        ]);
        $cp = ContractProduct::factory()->create([
            'contract_id'   => $contract->id,
            'unit_price'    => 200.00,
            'currency_code' => 'MXN',
        ]);

        $expenseCategory = ExpenseCategory::factory()->create();
        $budgetCedula    = BudgetCedula::factory()->create(['expense_category_id' => $expenseCategory->id]);
        $costCenter      = CostCenter::factory()->create([
            'company_id'  => $company->id,
            'budget_type' => 'FREE_CONSUMPTION',
        ]);

        $requisition = Requisition::factory()->create([
            'source_type'  => 'contract',
            'company_id'   => $company->id,
            'created_by'   => $this->requester->id,
            'updated_by'   => $this->requester->id,
            'requested_by' => $this->requester->id,
        ]);

        $requisition->items()->create([
            'product_service_id'  => $cp->product_service_id,
            'description'         => 'Producto convenio',
            'item_category'       => 'PRODUCTO',
            'product_code'        => 'CONV-001',
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

        return [$requisition, $supplier];
    }

    private function makePendingPo(): array
    {
        [$requisition, $supplier] = $this->makeContractPurchase('convenio');

        $this->actingAs($this->requester);
        app(ContractPurchaseOrderService::class)->generateFromRequisition($requisition);

        return [PurchaseOrder::first(), $supplier];
    }

    public function test_convenio_purchase_creates_pending_approval_po(): void
    {
        Notification::fake();

        [$requisition, $supplier] = $this->makeContractPurchase('convenio');

        $this->actingAs($this->requester);
        app(ContractPurchaseOrderService::class)->generateFromRequisition($requisition);

        $po = PurchaseOrder::first();
        $this->assertSame('PENDING_APPROVAL', $po->status);
        $this->assertSame($this->approver->id, (int) $po->assigned_approver_id);
        $this->assertNull($po->issued_at);
        $this->assertNull($po->approved_at);
        $this->assertNotNull($po->approval_chain_snapshot);

        Notification::assertSentTo($this->approver, ContractPurchaseOrderPendingApprovalNotification::class);
        Notification::assertNotSentTo($supplier, PurchaseOrderIssuedNotification::class);
    }

    public function test_iguala_purchase_still_issues_directly(): void
    {
        Notification::fake();

        [$requisition, $supplier] = $this->makeContractPurchase('iguala');

        $this->actingAs($this->requester);
        app(ContractPurchaseOrderService::class)->generateFromRequisition($requisition);

        $po = PurchaseOrder::first();
        $this->assertSame('ISSUED', $po->status);
        $this->assertNull($po->assigned_approver_id);

        Notification::assertSentTo($supplier, PurchaseOrderIssuedNotification::class);
    }

    public function test_assigned_approver_can_approve_and_po_is_issued(): void
    {
        Notification::fake();

        [$po, $supplier] = $this->makePendingPo();

        $response = $this->actingAs($this->approver)
            ->post(route('purchase-orders.approve', $po));

        $response->assertRedirect();
        $po->refresh();
        $this->assertSame('ISSUED', $po->status);
        $this->assertSame($this->approver->id, (int) $po->approved_by);
        $this->assertNotNull($po->issued_at);
        $this->assertDatabaseHas('purchase_order_approvals', [
            'purchase_order_id' => $po->id,
            'approver_user_id'  => $this->approver->id,
            'action'            => 'APPROVED',
        ]);

        Notification::assertSentTo($supplier, PurchaseOrderIssuedNotification::class);
    }

    public function test_other_users_cannot_approve(): void
    {
        Notification::fake();

        [$po] = $this->makePendingPo();
        // Con rol buyer pasa el middleware de módulo: el 403 debe venir del guard de aprobador
        $intruder = User::factory()->create()->assignRole('buyer');

        $response = $this->actingAs($intruder)
            ->post(route('purchase-orders.approve', $po));

        $response->assertForbidden();
        $this->assertSame('PENDING_APPROVAL', $po->fresh()->status);
    }

    public function test_approver_can_reject_with_comments(): void
    {
        Notification::fake();

        [$po] = $this->makePendingPo();

        $response = $this->actingAs($this->approver)
            ->post(route('purchase-orders.reject', $po), [
                'comments' => str_repeat('Rechazo por presupuesto insuficiente. ', 3),
            ]);

        $response->assertRedirect();
        $po->refresh();
        $this->assertSame('REJECTED', $po->status);
        $this->assertSame($this->approver->id, (int) $po->rejected_by);
        $this->assertNull($po->assigned_approver_id);
        $this->assertDatabaseHas('purchase_order_approvals', [
            'purchase_order_id' => $po->id,
            'action'            => 'REJECTED',
        ]);

        Notification::assertSentTo($this->requester, ContractPurchaseOrderRejectedNotification::class);
    }

    public function test_delegate_can_approve_for_principal_and_decision_is_audited(): void
    {
        Notification::fake();
        $delegate = User::factory()->create(['is_active' => true]);
        $delegate->assignRole('authorizer');
        $delegation = ApprovalDelegation::create([
            'delegator_user_id' => $this->approver->id,
            'status' => 'ACTIVE',
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addWeek(),
        ]);
        ApprovalDelegationMember::create([
            'approval_delegation_id' => $delegation->id,
            'delegate_user_id' => $delegate->id,
            'added_at' => now()->subMinute(),
        ]);

        [$po] = $this->makePendingPo();

        Notification::assertSentTo($delegate, ContractPurchaseOrderPendingApprovalNotification::class);

        $this->actingAs($delegate)
            ->post(route('purchase-orders.approve', $po))
            ->assertRedirect();

        $this->assertSame('ISSUED', $po->fresh()->status);
        $this->assertDatabaseHas('approval_decisions', [
            'approvable_type' => PurchaseOrder::class,
            'approvable_id' => $po->id,
            'assigned_principal_user_id' => $this->approver->id,
            'acted_by_user_id' => $delegate->id,
            'approval_delegation_id' => $delegation->id,
            'action' => 'APPROVED',
        ]);
    }
}
