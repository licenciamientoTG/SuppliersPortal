<?php

namespace Tests\Feature;

use App\Models\QuotationGroup;
use App\Models\RequisitionItem;
use App\Models\Rfq;
use App\Models\RfqResponse;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfqComparisonStatsTest extends TestCase
{
    use RefreshDatabase;

    private function buildRfqWithItems(int $itemCount): array
    {
        $rfq = Rfq::factory()->create();
        $group = QuotationGroup::factory()->create(['requisition_id' => $rfq->requisition_id]);
        $items = [];
        for ($i = 0; $i < $itemCount; $i++) {
            $item = RequisitionItem::factory()->create(['requisition_id' => $rfq->requisition_id]);
            $group->items()->attach($item->id, ['sort_order' => $i + 1]);
            $items[] = $item;
        }
        $rfq->update(['quotation_group_id' => $group->id]);

        return [$rfq->fresh(), $items];
    }

    public function test_quoted_item_count_excludes_not_available(): void
    {
        [$rfq, $items] = $this->buildRfqWithItems(3);
        $supplier = Supplier::factory()->create();

        RfqResponse::factory()->create(['rfq_id' => $rfq->id, 'supplier_id' => $supplier->id, 'requisition_item_id' => $items[0]->id, 'status' => 'SUBMITTED', 'not_available' => false]);
        RfqResponse::factory()->create(['rfq_id' => $rfq->id, 'supplier_id' => $supplier->id, 'requisition_item_id' => $items[1]->id, 'status' => 'SUBMITTED', 'not_available' => true]);

        $this->assertEquals(1, $rfq->quotedItemCountForSupplier($supplier->id));
    }

    public function test_quoted_item_count_counts_multiple_distinct_items_and_excludes_not_available(): void
    {
        [$rfq, $items] = $this->buildRfqWithItems(3);
        $supplier = Supplier::factory()->create();

        // El mismo proveedor cotiza DOS partidas distintas...
        RfqResponse::factory()->create(['rfq_id' => $rfq->id, 'supplier_id' => $supplier->id, 'requisition_item_id' => $items[0]->id, 'status' => 'SUBMITTED', 'not_available' => false]);
        RfqResponse::factory()->create(['rfq_id' => $rfq->id, 'supplier_id' => $supplier->id, 'requisition_item_id' => $items[1]->id, 'status' => 'SUBMITTED', 'not_available' => false]);
        // ...y marca una tercera como no disponible (no debe contar).
        RfqResponse::factory()->create(['rfq_id' => $rfq->id, 'supplier_id' => $supplier->id, 'requisition_item_id' => $items[2]->id, 'status' => 'SUBMITTED', 'not_available' => true]);

        $this->assertEquals(2, $rfq->quotedItemCountForSupplier($supplier->id));
    }

    public function test_items_quoted_by_no_supplier_returns_only_uncovered_items(): void
    {
        [$rfq, $items] = $this->buildRfqWithItems(2);
        $supplierA = Supplier::factory()->create();
        $supplierB = Supplier::factory()->create();

        // item[0] lo cotiza A; item[1] nadie (B lo marca no disponible)
        RfqResponse::factory()->create(['rfq_id' => $rfq->id, 'supplier_id' => $supplierA->id, 'requisition_item_id' => $items[0]->id, 'status' => 'SUBMITTED', 'not_available' => false]);
        RfqResponse::factory()->create(['rfq_id' => $rfq->id, 'supplier_id' => $supplierB->id, 'requisition_item_id' => $items[1]->id, 'status' => 'SUBMITTED', 'not_available' => true]);

        $uncovered = $rfq->itemsQuotedByNoSupplier();

        $this->assertEquals([$items[1]->id], $uncovered->pluck('id')->all());
    }

    public function test_items_quoted_by_no_supplier_includes_items_with_zero_responses(): void
    {
        [$rfq, $items] = $this->buildRfqWithItems(3);
        $supplierA = Supplier::factory()->create();
        $supplierB = Supplier::factory()->create();

        // item[0]: lo cotiza A (cubierto).
        RfqResponse::factory()->create(['rfq_id' => $rfq->id, 'supplier_id' => $supplierA->id, 'requisition_item_id' => $items[0]->id, 'status' => 'SUBMITTED', 'not_available' => false]);
        // item[1]: B lo marca no disponible (nadie lo cotiza realmente).
        RfqResponse::factory()->create(['rfq_id' => $rfq->id, 'supplier_id' => $supplierB->id, 'requisition_item_id' => $items[1]->id, 'status' => 'SUBMITTED', 'not_available' => true]);
        // item[2]: nadie respondió absolutamente nada (cero RfqResponse).

        $uncovered = $rfq->itemsQuotedByNoSupplier();
        $uncoveredIds = $uncovered->pluck('id')->all();

        $this->assertCount(2, $uncoveredIds);
        $this->assertContains($items[1]->id, $uncoveredIds);
        $this->assertContains($items[2]->id, $uncoveredIds);
    }
}
