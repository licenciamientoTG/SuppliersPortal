<?php

namespace Tests\Feature;

use App\Models\ProductService;
use App\Models\QuotationGroup;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Rfq;
use App\Models\RfqResponse;
use App\Models\Supplier;
use App\Services\Rfq\PriceMemoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceMemoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeHistoricResponse(ProductService $product, array $overrides = []): RfqResponse
    {
        $requisition = Requisition::factory()->create();
        $item = RequisitionItem::factory()->create([
            'requisition_id' => $requisition->id,
            'product_service_id' => $product->id,
        ]);
        $rfq = Rfq::factory()->create(['requisition_id' => $requisition->id]);

        return RfqResponse::factory()->create(array_merge([
            'rfq_id' => $rfq->id,
            'supplier_id' => Supplier::factory()->create()->id,
            'requisition_item_id' => $item->id,
            'status' => 'SUBMITTED',
            'submitted_at' => now(),
        ], $overrides));
    }

    public function test_returns_most_recent_reference_for_same_product_across_requisitions(): void
    {
        $product = ProductService::factory()->create();

        $this->makeHistoricResponse($product, [
            'quotation_date' => now()->subDays(40)->toDateString(),
            'unit_price' => 90,
        ]);
        $recent = $this->makeHistoricResponse($product, [
            'quotation_date' => now()->subDays(5)->toDateString(),
            'unit_price' => 120,
        ]);

        $requisition = Requisition::factory()->create();
        $item = RequisitionItem::factory()->create([
            'requisition_id' => $requisition->id,
            'product_service_id' => $product->id,
        ]);

        $references = app(PriceMemoryService::class)->latestForItems(collect([$item]));

        $this->assertArrayHasKey($item->id, $references);
        $this->assertEquals(120.0, $references[$item->id]['unit_price']);
        $this->assertEquals($recent->supplier_id, $references[$item->id]['supplier_id']);
        $this->assertEquals(now()->subDays(5)->toDateString(), $references[$item->id]['quotation_date']);
    }

    public function test_ignores_not_available_and_draft_responses(): void
    {
        $product = ProductService::factory()->create();

        $this->makeHistoricResponse($product, [
            'quotation_date' => now()->subDay()->toDateString(),
            'not_available' => true,
        ]);
        $this->makeHistoricResponse($product, [
            'quotation_date' => now()->toDateString(),
            'status' => 'DRAFT',
        ]);

        $requisition = Requisition::factory()->create();
        $item = RequisitionItem::factory()->create([
            'requisition_id' => $requisition->id,
            'product_service_id' => $product->id,
        ]);

        $references = app(PriceMemoryService::class)->latestForItems(collect([$item]));

        $this->assertArrayNotHasKey($item->id, $references);
    }

    public function test_group_hint_counts_only_recent_references(): void
    {
        $freshProduct = ProductService::factory()->create();
        $staleProduct = ProductService::factory()->create();
        $unknownProduct = ProductService::factory()->create();

        $this->makeHistoricResponse($freshProduct, [
            'quotation_date' => now()->subDays(10)->toDateString(),
        ]);
        $this->makeHistoricResponse($staleProduct, [
            'quotation_date' => now()->subDays(90)->toDateString(),
        ]);

        $requisition = Requisition::factory()->create();
        $group = QuotationGroup::factory()->create(['requisition_id' => $requisition->id]);

        foreach ([$freshProduct, $staleProduct, $unknownProduct] as $i => $product) {
            $item = RequisitionItem::factory()->create([
                'requisition_id' => $requisition->id,
                'product_service_id' => $product->id,
            ]);
            $group->items()->attach($item->id, ['sort_order' => $i + 1]);
        }

        $hint = app(PriceMemoryService::class)->groupHint($group, 30);

        $this->assertEquals(['with_recent' => 1, 'total' => 3, 'fresh_days' => 30], $hint);
    }
}
