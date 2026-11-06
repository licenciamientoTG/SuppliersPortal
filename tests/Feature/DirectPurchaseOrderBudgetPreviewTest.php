<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckLockScreen;
use App\Http\Middleware\ModuleAccess;
use App\Models\AnnualBudget;
use App\Models\BudgetCedula;
use App\Models\BudgetCommitment;
use App\Models\BudgetMonthlyDistribution;
use App\Models\Category;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\DirectPurchaseOrder;
use App\Models\ExchangeRate;
use App\Models\ExpenseCategory;
use App\Models\ProductService;
use App\Models\User;
use App\Services\ProductBudgetClassificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectPurchaseOrderBudgetPreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private CostCenter $costCenter;

    private ProductService $product;

    private BudgetMonthlyDistribution $distribution;

    protected function setUp(): void
    {
        parent::setUp();

        // Mitad de mes: entrega a 1 día no cambia el mes de aplicación.
        $this->travelTo(now()->setDate(2026, 6, 10)->setTime(10, 0));
        $this->withoutMiddleware([ModuleAccess::class, CheckLockScreen::class]);

        $this->user = User::factory()->create();
        $this->costCenter = $this->makeCostCenter('ANNUAL');

        $expenseCategory = ExpenseCategory::factory()->create();
        $cedula = BudgetCedula::factory()->create(['expense_category_id' => $expenseCategory->id]);
        $annualBudget = AnnualBudget::create([
            'cost_center_id' => $this->costCenter->id,
            'fiscal_year' => 2026,
            'total_annual_amount' => 10000,
            'status' => 'APROBADO',
            'created_by' => $this->user->id,
        ]);
        $this->distribution = BudgetMonthlyDistribution::create([
            'annual_budget_id' => $annualBudget->id,
            'budget_cedula_id' => $cedula->id,
            'expense_category_id' => $expenseCategory->id,
            'month' => 6,
            'assigned_amount' => 10000,
            'consumed_amount' => 0,
            'committed_amount' => 0,
            'created_by' => $this->user->id,
        ]);

        $this->product = ProductService::factory()->create();

        // La clasificación real depende de subcuentas; aquí se fija la cédula del producto.
        $this->mock(ProductBudgetClassificationService::class, function ($mock) use ($expenseCategory, $cedula) {
            $mock->shouldReceive('resolveForProduct')->andReturn([
                'expense_category_id' => $expenseCategory->id,
                'budget_cedula_id' => $cedula->id,
            ]);
        });
    }

    private function makeCostCenter(string $budgetType): CostCenter
    {
        $costCenter = CostCenter::factory()->create([
            'company_id' => Company::factory(),
            'category_id' => Category::factory(),
            'responsible_user_id' => $this->user->id,
            'budget_type' => $budgetType,
            'status' => 'ACTIVO',
        ]);
        $this->user->costCenters()->attach($costCenter->id, [
            'is_active' => true,
            'is_default' => false,
            'created_by' => $this->user->id,
        ]);

        return $costCenter;
    }

    private function preview(array $items, array $extra = [])
    {
        return $this->actingAs($this->user)->getJson(route('direct-purchase-orders.budget-preview', array_merge([
            'currency' => 'MXN',
            'estimated_delivery_days' => 1,
            'items' => $items,
        ], $extra)));
    }

    private function item(float $price, ?int $costCenterId = null): array
    {
        return [
            'cost_center_id' => $costCenterId ?? $this->costCenter->id,
            'product_service_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => $price,
        ];
    }

    public function test_items_sharing_a_cedula_consume_the_same_balance(): void
    {
        $response = $this->preview([0 => $this->item(6000), 1 => $this->item(5000)]);

        $response->assertOk()
            ->assertJsonPath('month', '2026-06')
            ->assertJsonPath('rows.0.status', 'ok')
            ->assertJsonPath('rows.0.allocations.0.available', 10000)
            ->assertJsonPath('rows.1.status', 'insufficient')
            ->assertJsonPath('rows.1.allocations.0.available', 4000)
            ->assertJsonPath('rows.1.allocations.0.required', 5000);
    }

    public function test_foreign_currency_is_converted_to_mxn_before_comparing(): void
    {
        ExchangeRate::create(['currency_from' => 'USD', 'currency_to' => 'MXN', 'rate' => 20, 'fetched_at' => now()]);

        $this->preview([0 => $this->item(600)], ['currency' => 'USD'])
            ->assertOk()
            ->assertJsonPath('rows.0.required_mxn', 12000)
            ->assertJsonPath('rows.0.status', 'insufficient');
    }

    public function test_missing_exchange_rate_is_reported(): void
    {
        $this->preview([0 => $this->item(100)], ['currency' => 'EUR'])
            ->assertOk()
            ->assertJsonPath('error', 'No hay tipo de cambio sincronizado para EUR -> MXN.');
    }

    public function test_free_consumption_cost_center_has_no_cap(): void
    {
        $free = $this->makeCostCenter('FREE_CONSUMPTION');

        $this->preview([0 => $this->item(999999, $free->id)])
            ->assertOk()
            ->assertJsonPath('rows.0.status', 'free');
    }

    public function test_cost_center_outside_the_users_scope_is_not_disclosed(): void
    {
        $foreign = CostCenter::factory()->create([
            'company_id' => Company::factory(),
            'category_id' => Category::factory(),
            'budget_type' => 'ANNUAL',
        ]);

        $this->preview([0 => $this->item(100, $foreign->id)])
            ->assertOk()
            ->assertJsonPath('rows.0.status', 'error')
            ->assertJsonMissingPath('rows.0.allocations');
    }

    public function test_incomplete_rows_are_skipped(): void
    {
        $this->preview([0 => ['cost_center_id' => $this->costCenter->id, 'product_service_id' => '', 'quantity' => 1, 'unit_price' => 10]])
            ->assertOk()
            ->assertJsonPath('rows.0.status', 'incomplete');
    }

    public function test_editing_counts_the_orders_own_reservation_as_available(): void
    {
        $ocd = DirectPurchaseOrder::factory()->create(['created_by' => $this->user->id, 'status' => 'RETURNED']);
        $this->distribution->update(['committed_amount' => 3000]);
        BudgetCommitment::create([
            'direct_purchase_order_id' => $ocd->id,
            'cost_center_id' => $this->costCenter->id,
            'application_month' => '2026-06',
            'expense_category_id' => $this->distribution->expense_category_id,
            'budget_cedula_id' => $this->distribution->budget_cedula_id,
            'committed_amount' => 3000,
            'consumed_amount' => 0,
            'status' => 'COMMITTED',
            'committed_at' => now(),
        ]);

        $this->preview([0 => $this->item(9000)], ['direct_purchase_order_id' => $ocd->id])
            ->assertJsonPath('rows.0.status', 'ok')
            ->assertJsonPath('rows.0.allocations.0.available', 10000);

        // Sin la OCD (u otra persona), esos 3,000 siguen apartados.
        $this->preview([0 => $this->item(9000)])
            ->assertJsonPath('rows.0.status', 'insufficient')
            ->assertJsonPath('rows.0.allocations.0.available', 7000);
    }
}
