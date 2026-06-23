<?php

namespace Tests\Feature;

use App\Models\QuotationGroup;
use App\Models\RequisitionItem;
use App\Models\Rfq;
use App\Models\RfqResponse;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfqComparisonViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_comparison_shows_not_available_badge_and_summary_and_button(): void
    {
        $this->withoutMiddleware([\App\Http\Middleware\ModuleAccess::class, \App\Http\Middleware\CheckLockScreen::class]);

        $user = User::factory()->create();
        $rfq = Rfq::factory()->create(['status' => 'RECEIVED']);
        $group = QuotationGroup::factory()->create(['requisition_id' => $rfq->requisition_id]);
        $itemA = RequisitionItem::factory()->create(['requisition_id' => $rfq->requisition_id]);
        $itemB = RequisitionItem::factory()->create(['requisition_id' => $rfq->requisition_id]);
        $group->items()->attach($itemA->id, ['sort_order' => 1]);
        $group->items()->attach($itemB->id, ['sort_order' => 2]);
        $rfq->update(['quotation_group_id' => $group->id]);

        $supplier = Supplier::factory()->create();
        $rfq->suppliers()->attach($supplier->id, ['invited_at' => now(), 'responded_at' => now()]);

        RfqResponse::factory()->create(['rfq_id' => $rfq->id, 'supplier_id' => $supplier->id, 'requisition_item_id' => $itemA->id, 'status' => 'SUBMITTED', 'not_available' => false]);
        RfqResponse::factory()->create(['rfq_id' => $rfq->id, 'supplier_id' => $supplier->id, 'requisition_item_id' => $itemB->id, 'status' => 'SUBMITTED', 'not_available' => true]);

        $this->actingAs($user)
            ->get(route('rfq.comparison.index', $rfq))
            ->assertOk()
            ->assertSee('Producto no disponible')
            ->assertSee('1 de 2 partidas cotizadas')
            ->assertSee('Generar RFQ con partidas faltantes');
    }
}
