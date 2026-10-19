<?php

namespace Tests\Unit;

use App\Models\ExchangeRate;
use App\Models\QuotationSummary;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Rfq;
use App\Models\RfqResponse;
use App\Models\Supplier;
use App\Services\BudgetAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * El fallback de buildQuotationSummaryBudgetLines() se usa para el
 * QuotationSummary transitorio de RfqAwardService::supplierDiagnostics(),
 * antes de que existan QuotationSummaryItem. Debe convertir a MXN igual
 * que la rama de items, no sumar montos en moneda extranjera sin convertir.
 */
class BudgetAllocationCurrencyConversionTest extends TestCase
{
    use RefreshDatabase;

    private function makeTransientSummary(array $responseOverrides = []): QuotationSummary
    {
        $requisition = Requisition::factory()->create();
        $item = RequisitionItem::factory()->create(['requisition_id' => $requisition->id]);
        $rfq = Rfq::factory()->create([
            'requisition_id' => $requisition->id,
            'requisition_item_id' => $item->id,
        ]);
        $supplier = Supplier::factory()->create();

        $response = RfqResponse::factory()->create(array_merge([
            'rfq_id' => $rfq->id,
            'supplier_id' => $supplier->id,
            'requisition_item_id' => $item->id,
            'status' => 'SUBMITTED',
            'unit_price' => 100,
            'quantity' => 2,
            'subtotal' => 200,
            'iva_amount' => 32,
            'total' => 232,
        ], $responseOverrides));

        $summary = new QuotationSummary([
            'requisition_id' => $requisition->id,
            'rfq_id' => $rfq->id,
            'subtotal' => (float) $response->subtotal,
            'iva_amount' => (float) $response->iva_amount,
            'total' => (float) $response->total,
            'selected_supplier_id' => $supplier->id,
        ]);
        $summary->setRelation('rfq', $rfq->loadMissing('rfqResponses.requisitionItem.costCenter'));
        $summary->setRelation('items', collect());

        return $summary;
    }

    public function test_fallback_converts_foreign_currency_response_before_building_line(): void
    {
        ExchangeRate::factory()->create([
            'currency_from' => 'USD',
            'currency_to' => 'MXN',
            'rate' => 18.0000,
        ]);

        $summary = $this->makeTransientSummary([
            'currency' => 'USD',
            'unit_price' => 100,
            'quantity' => 2,
            'subtotal' => 200,
        ]);

        $lines = app(BudgetAllocationService::class)->buildQuotationSummaryBudgetLines($summary);

        $this->assertEquals(3600.0, array_sum(array_column($lines, 'amount')));
    }

    public function test_fallback_fails_loud_when_foreign_currency_has_no_synced_rate(): void
    {
        $summary = $this->makeTransientSummary(['currency' => 'USD']);

        $this->expectException(RuntimeException::class);

        app(BudgetAllocationService::class)->buildQuotationSummaryBudgetLines($summary);
    }
}
