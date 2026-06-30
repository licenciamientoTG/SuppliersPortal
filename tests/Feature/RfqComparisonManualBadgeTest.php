<?php

namespace Tests\Feature;

use App\Models\QuotationGroup;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Rfq;
use App\Models\RfqResponse;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfqComparisonManualBadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_comparison_view_shows_manual_badge_for_buyer_captured_quote(): void
    {
        $this->withoutMiddleware([\App\Http\Middleware\ModuleAccess::class, \App\Http\Middleware\CheckLockScreen::class]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $requisition = Requisition::factory()->create();
        $group = QuotationGroup::factory()->create(['requisition_id' => $requisition->id]);
        $item = RequisitionItem::factory()->create(['requisition_id' => $requisition->id]);
        $group->items()->attach($item->id, ['sort_order' => 1]);

        $supplier = Supplier::factory()->external()->create(['company_name' => 'Tornillos del Norte SA']);
        $rfq = Rfq::factory()->create([
            'requisition_id' => $requisition->id,
            'quotation_group_id' => $group->id,
            'status' => 'RECEIVED',
        ]);
        $rfq->suppliers()->attach($supplier->id, ['invited_at' => now(), 'responded_at' => now()]);
        RfqResponse::factory()->create([
            'rfq_id' => $rfq->id,
            'supplier_id' => $supplier->id,
            'requisition_item_id' => $item->id,
            'status' => 'SUBMITTED',
            'entry_source' => 'buyer_manual',
        ]);

        $this->get(route('rfq.comparison.index', $rfq))
            ->assertOk()
            ->assertSee('CAPTURADA MANUALMENTE');
    }
}
