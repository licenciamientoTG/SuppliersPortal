<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckLockScreen;
use App\Http\Middleware\ModuleAccess;
use App\Models\AnnualBudget;
use App\Models\BudgetCedula;
use App\Models\BudgetCommitment;
use App\Models\BudgetMonthlyDistribution;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Models\ProductService;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReceivingLocation;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderReactivationBudgetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Reproduce el error de produccion: una OC cerrada por inactividad libero su
     * compromiso presupuestal, otra orden consumio ese saldo mientras tanto, y al
     * reactivarla ya no hay presupuesto disponible para volver a comprometerlo.
     * Antes del fix, el RuntimeException de BudgetAllocationService::commitLine()
     * no se capturaba y el usuario recibia un error 500 en vez de un mensaje claro.
     */
    public function test_reactivating_a_purchase_order_without_available_budget_shows_friendly_error(): void
    {
        $this->withoutMiddleware([ModuleAccess::class, CheckLockScreen::class]);

        $user = User::factory()->create(['id' => 20]);

        $company = Company::factory()->create();
        $costCenter = CostCenter::factory()->create([
            'company_id' => $company->id,
            'responsible_user_id' => $user->id,
            'budget_type' => 'ANNUAL',
            'created_by' => $user->id,
        ]);
        $expenseCategory = ExpenseCategory::factory()->create(['created_by' => $user->id]);
        $budgetCedula = BudgetCedula::factory()->create([
            'name' => 'Limpieza',
            'expense_category_id' => $expenseCategory->id,
            'created_by' => $user->id,
        ]);
        $receivingLocation = ReceivingLocation::factory()->create([
            'company_id' => $company->id,
            'type' => 'corporate',
        ]);
        $productService = ProductService::factory()->create(['created_by' => $user->id]);
        $supplier = Supplier::factory()->create(['approval_status' => 'approved']);

        $applicationMonth = now()->format('Y-m');

        $annualBudget = AnnualBudget::create([
            'cost_center_id' => $costCenter->id,
            'fiscal_year' => (int) now()->format('Y'),
            'total_annual_amount' => 1000,
            'status' => 'APROBADO',
            'created_by' => $user->id,
        ]);

        $distribution = BudgetMonthlyDistribution::create([
            'annual_budget_id' => $annualBudget->id,
            'budget_cedula_id' => $budgetCedula->id,
            'expense_category_id' => $expenseCategory->id,
            'month' => (int) now()->format('n'),
            'assigned_amount' => 100,
            'consumed_amount' => 0,
            // Ya esta totalmente comprometido por otra orden: no queda disponible
            // para volver a comprometer el monto liberado de la OC reactivada.
            'committed_amount' => 100,
            'created_by' => $user->id,
        ]);

        $requisition = Requisition::factory()->create([
            'company_id' => $company->id,
            'receiving_location_id' => $receivingLocation->id,
            'requested_by' => $user->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $requisitionItem = RequisitionItem::factory()->create([
            'requisition_id' => $requisition->id,
            'product_service_id' => $productService->id,
            'expense_category_id' => $expenseCategory->id,
            'budget_cedula_id' => $budgetCedula->id,
            'cost_center_id' => $costCenter->id,
        ]);

        $purchaseOrder = PurchaseOrder::factory()->create([
            'requisition_id' => $requisition->id,
            'supplier_id' => $supplier->id,
            'receiving_location_id' => $receivingLocation->id,
            'status' => 'CLOSED_BY_INACTIVITY',
            'created_by' => $user->id,
            'issued_at' => now()->subDays(PurchaseOrder::INACTIVITY_DAYS + 1),
            'closed_at' => now(),
        ]);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'requisition_item_id' => $requisitionItem->id,
            'total' => 100,
        ]);

        // Compromiso previo liberado al cerrar la OC por inactividad.
        BudgetCommitment::create([
            'purchase_order_id' => $purchaseOrder->id,
            'cost_center_id' => $costCenter->id,
            'application_month' => $applicationMonth,
            'expense_category_id' => $expenseCategory->id,
            'budget_cedula_id' => $budgetCedula->id,
            'committed_amount' => 100,
            'consumed_amount' => 0,
            'status' => 'RELEASED',
            'committed_at' => now()->subDays(PurchaseOrder::INACTIVITY_DAYS + 1),
            'released_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->from(route('purchase-orders.show', $purchaseOrder))
            ->post(route('purchase-orders.reactivate', $purchaseOrder));

        $response->assertRedirect(route('purchase-orders.show', $purchaseOrder));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Limpieza', session('error'));

        $purchaseOrder->refresh();
        $this->assertSame('CLOSED_BY_INACTIVITY', $purchaseOrder->status);
        $this->assertSame(100.0, (float) $distribution->fresh()->committed_amount);
    }
}
