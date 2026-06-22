<?php

namespace Tests\Feature;

use App\Models\QuotationGroup;
use App\Models\RequisitionItem;
use App\Models\Rfq;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierRfqDetailRendersToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_quotation_page_shows_not_available_toggle(): void
    {
        $supplier = Supplier::factory()->create();
        $rfq = Rfq::factory()->create(['status' => 'SENT']);
        $rfq->suppliers()->attach($supplier->id, ['invited_at' => now()]);

        $group = QuotationGroup::factory()->create(['requisition_id' => $rfq->requisition_id]);
        $item = RequisitionItem::factory()->create(['requisition_id' => $rfq->requisition_id]);
        $group->items()->attach($item->id, ['sort_order' => 1]);
        $rfq->update(['quotation_group_id' => $group->id]);

        $this->actingAs($supplier, 'supplier')
            ->get(route('supplier.rfq.show', $rfq))
            ->assertOk()
            ->assertSee('No puedo cotizar esta partida');
    }
}
