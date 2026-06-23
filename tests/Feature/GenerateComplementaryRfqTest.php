<?php

namespace Tests\Feature;

use App\Models\QuotationGroup;
use App\Models\RequisitionItem;
use App\Models\Rfq;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateComplementaryRfqTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_sent_rfq_with_selected_items_and_suppliers(): void
    {
        $this->withoutMiddleware([\App\Http\Middleware\ModuleAccess::class, \App\Http\Middleware\CheckLockScreen::class]);

        $user = User::factory()->create();
        $origin = Rfq::factory()->create();
        $itemA = RequisitionItem::factory()->create(['requisition_id' => $origin->requisition_id]);
        $itemB = RequisitionItem::factory()->create(['requisition_id' => $origin->requisition_id]);
        $supplier = Supplier::factory()->create();

        $this->actingAs($user)
            ->post(route('rfq.comparison.generate-complementary', $origin), [
                'item_ids' => [$itemA->id, $itemB->id],
                'supplier_ids' => [$supplier->id],
                'response_deadline' => now()->addDays(5)->format('Y-m-d'),
                'message' => 'Faltó producto',
            ])->assertRedirect(route('rfq.comparison.index', $origin));

        $new = Rfq::where('supersedes_rfq_id', $origin->id)->first();
        $this->assertNotNull($new);
        $this->assertEquals('SENT', $new->status);
        $this->assertNotNull($new->sent_at);
        $this->assertEquals($origin->requisition_id, $new->requisition_id);

        $group = QuotationGroup::find($new->quotation_group_id);
        $this->assertNotNull($group);
        $this->assertEqualsCanonicalizing(
            [$itemA->id, $itemB->id],
            $group->items()->pluck('requisition_items.id')->all()
        );

        $this->assertTrue($new->suppliers->contains($supplier->id));
    }
}
