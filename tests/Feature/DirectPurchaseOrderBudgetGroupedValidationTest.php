<?php

namespace Tests\Feature;

use App\Http\Controllers\DirectPurchaseOrderController;
use App\Models\AnnualBudget;
use App\Models\BudgetCedula;
use App\Models\BudgetException;
use App\Models\BudgetMonthlyDistribution;
use App\Models\Category;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\DirectPurchaseOrder;
use App\Models\DirectPurchaseOrderItem;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La validación al enviar una OCD debe sumar las partidas que comparten
 * centro de costo + cuenta + cédula, igual que cuando se aparta el presupuesto.
 */
class DirectPurchaseOrderBudgetGroupedValidationTest extends TestCase
{
    use RefreshDatabase;

    private CostCenter $costCenter;

    private ExpenseCategory $category;

    private BudgetCedula $cedula;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->costCenter = CostCenter::factory()->create([
            'company_id' => Company::factory(),
            'category_id' => Category::factory(),
            'responsible_user_id' => $this->user->id,
            'budget_type' => 'ANNUAL',
        ]);
        $this->category = ExpenseCategory::factory()->create();
        $this->cedula = BudgetCedula::factory()->create(['expense_category_id' => $this->category->id]);

        $annualBudget = AnnualBudget::create([
            'cost_center_id' => $this->costCenter->id,
            'fiscal_year' => (int) now()->format('Y'),
            'total_annual_amount' => 10000,
            'status' => 'APROBADO',
            'created_by' => $this->user->id,
        ]);
        BudgetMonthlyDistribution::create([
            'annual_budget_id' => $annualBudget->id,
            'budget_cedula_id' => $this->cedula->id,
            'expense_category_id' => $this->category->id,
            'month' => (int) now()->format('m'),
            'assigned_amount' => 10000,
            'consumed_amount' => 0,
            'committed_amount' => 0,
            'created_by' => $this->user->id,
        ]);
    }

    /** @param  float[]  $subtotals */
    private function orderWithItems(array $subtotals): DirectPurchaseOrder
    {
        $order = DirectPurchaseOrder::factory()->create([
            'created_by' => $this->user->id,
            'exchange_rate' => 1,
        ]);

        foreach ($subtotals as $subtotal) {
            DirectPurchaseOrderItem::factory()->create([
                'direct_purchase_order_id' => $order->id,
                'cost_center_id' => $this->costCenter->id,
                'expense_category_id' => $this->category->id,
                'budget_cedula_id' => $this->cedula->id,
                'quantity' => 1,
                'unit_price' => $subtotal,
                'iva_rate' => 0,
                'subtotal' => $subtotal,
                'iva_amount' => 0,
                'total' => $subtotal,
            ]);
        }

        return $order->fresh();
    }

    private function ensureBudgetAvailability(DirectPurchaseOrder $order): void
    {
        $method = new \ReflectionMethod(DirectPurchaseOrderController::class, 'ensureBudgetAvailability');
        $method->invoke(app(DirectPurchaseOrderController::class), $order);
    }

    public function test_items_sharing_a_cedula_are_validated_against_their_sum(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('suma de 2 partidas con la misma cédula');

        // 6,000 + 6,000 = 12,000 contra un saldo de 10,000: cada una por separado sí cabría.
        $this->ensureBudgetAvailability($this->orderWithItems([6000, 6000]));
    }

    public function test_items_that_fit_together_pass(): void
    {
        $this->ensureBudgetAvailability($this->orderWithItems([6000, 4000]));

        $this->addToAssertionCount(1);
    }

    public function test_approved_exceptions_cover_the_group_shortfall(): void
    {
        $order = $this->orderWithItems([6000, 6000]);
        $item = $order->items->first();

        BudgetException::create([
            'document_type' => 'direct_purchase_order',
            'document_id' => $order->id,
            'document_line_id' => $item->id,
            'cost_center_id' => $this->costCenter->id,
            'expense_category_id' => $this->category->id,
            'budget_cedula_id' => $this->cedula->id,
            'application_month' => $order->application_month,
            'line_amount' => 6000,
            'available_at_request' => 10000,
            'requested_excess' => 2000,
            'approved_excess' => 2000,
            'reason' => 'Prueba',
            'status' => 'APPROVED',
            'requested_by' => $this->user->id,
            'requested_at' => now(),
            'decided_by' => $this->user->id,
            'decided_at' => now(),
        ]);

        $this->ensureBudgetAvailability($order);

        $this->addToAssertionCount(1);
    }
}
