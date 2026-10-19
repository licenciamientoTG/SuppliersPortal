<?php

namespace Tests\Unit;

use App\Models\ExchangeRate;
use App\Models\QuotationSummary;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Rfq;
use App\Models\RfqResponse;
use App\Models\Supplier;
use App\Services\QuotationSummaryItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class QuotationSummaryItemCurrencyConversionTest extends TestCase
{
    use RefreshDatabase;

    private function makeSummaryWithResponse(array $responseOverrides = []): array
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
            'iva_rate' => 16,
            'iva_amount' => 32,
            'total' => 232,
        ], $responseOverrides));

        $summary = QuotationSummary::factory()->create([
            'requisition_id' => $requisition->id,
            'rfq_id' => $rfq->id,
            'selected_supplier_id' => $supplier->id,
        ]);
        $summary->setRelation('rfq', $rfq->loadMissing('rfqResponses'));

        return [$summary, $response];
    }

    public function test_item_keeps_native_amounts_when_response_is_mxn(): void
    {
        [$summary, $response] = $this->makeSummaryWithResponse(['currency' => 'MXN']);

        $items = app(QuotationSummaryItemService::class)->syncFromSelectedSupplier($summary);
        $item = $items->first();

        $this->assertSame('MXN', $item->currency);
        $this->assertSame(1.0, (float) $item->exchange_rate);
        $this->assertEquals(100.0, (float) $item->unit_price);
        $this->assertEquals(100.0, (float) $item->unit_price_original);
        $this->assertEquals(200.0, (float) $item->subtotal);
    }

    public function test_item_converts_unit_price_to_mxn_when_response_is_foreign_currency(): void
    {
        ExchangeRate::factory()->create([
            'currency_from' => 'USD',
            'currency_to' => 'MXN',
            'rate' => 18.0000,
        ]);

        [$summary, $response] = $this->makeSummaryWithResponse([
            'currency' => 'USD',
            'unit_price' => 100,
            'quantity' => 2,
            'subtotal' => 200,
            'iva_amount' => 32,
            'total' => 232,
        ]);

        $items = app(QuotationSummaryItemService::class)->syncFromSelectedSupplier($summary);
        $item = $items->first();

        $this->assertSame('USD', $item->currency);
        $this->assertSame(18.0, (float) $item->exchange_rate);
        $this->assertEquals(100.0, (float) $item->unit_price_original);
        $this->assertEquals(1800.0, (float) $item->unit_price);
        $this->assertEquals(3600.0, (float) $item->subtotal);
    }

    public function test_sync_fails_loud_when_foreign_currency_has_no_synced_rate(): void
    {
        [$summary, $response] = $this->makeSummaryWithResponse(['currency' => 'USD']);

        $this->expectException(RuntimeException::class);

        app(QuotationSummaryItemService::class)->syncFromSelectedSupplier($summary);
    }
}
