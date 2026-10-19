<?php

namespace Tests\Unit;

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
 * La OCD no tiene etapa de cotización: el tipo de cambio se captura una vez,
 * al crearla, y getOrderBudgetLines() debe usarlo para comprometer presupuesto
 * en MXN aunque los items se capturen en la moneda nativa de la orden.
 */
class DirectPurchaseOrderBudgetCurrencyConversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_budget_line_amount_is_converted_using_the_order_exchange_rate(): void
    {
        $user = User::factory()->create();
        $costCenter = CostCenter::factory()->create([
            'company_id' => Company::factory(),
            'category_id' => Category::factory(),
            'responsible_user_id' => $user->id,
            'budget_type' => 'FREE_CONSUMPTION',
        ]);
        $expenseCategory = ExpenseCategory::factory()->create();

        $order = DirectPurchaseOrder::factory()->create([
            'currency' => 'USD',
            'exchange_rate' => 18.0000,
            'application_month' => now()->format('Y-m'),
        ]);

        DirectPurchaseOrderItem::factory()->create([
            'direct_purchase_order_id' => $order->id,
            'cost_center_id' => $costCenter->id,
            'expense_category_id' => $expenseCategory->id,
            'budget_cedula_id' => null,
            'quantity' => 2,
            'unit_price' => 100,
            'iva_rate' => 16,
        ]);

        $lines = app(BudgetAllocationService::class)->buildDirectPurchaseOrderBudgetLines($order->fresh());

        $this->assertCount(1, $lines);
        $this->assertEquals(3600.0, $lines[0]['amount']);
    }

    public function test_budget_line_amount_is_unchanged_for_mxn_orders(): void
    {
        $user = User::factory()->create();
        $costCenter = CostCenter::factory()->create([
            'company_id' => Company::factory(),
            'category_id' => Category::factory(),
            'responsible_user_id' => $user->id,
            'budget_type' => 'FREE_CONSUMPTION',
        ]);
        $expenseCategory = ExpenseCategory::factory()->create();

        $order = DirectPurchaseOrder::factory()->create([
            'application_month' => now()->format('Y-m'),
        ]);

        DirectPurchaseOrderItem::factory()->create([
            'direct_purchase_order_id' => $order->id,
            'cost_center_id' => $costCenter->id,
            'expense_category_id' => $expenseCategory->id,
            'budget_cedula_id' => null,
            'quantity' => 2,
            'unit_price' => 100,
            'iva_rate' => 16,
        ]);

        $lines = app(BudgetAllocationService::class)->buildDirectPurchaseOrderBudgetLines($order->fresh());

        $this->assertCount(1, $lines);
        $this->assertEquals(200.0, $lines[0]['amount']);
    }
}
