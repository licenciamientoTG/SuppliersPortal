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
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Step3SuppliersManualQuoteViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_step_3_shows_manual_quote_button_and_badge_for_captured_supplier(): void
    {
        Role::findOrCreate('buyer', 'web');

        $user = User::factory()->create();
        $user->assignRole('buyer');
        $this->actingAs($user);

        $requisition = Requisition::factory()->create(['validated_at' => now()]);
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
            'entry_source' => 'buyer_manual',
        ]);

        $this->get(route('rfq.wizard.steps', $requisition).'?step=3')
            ->assertOk()
            ->assertSee('Cotización manual')
            ->assertSee('Cotización capturada')
            ->assertSee('Tornillos del Norte SA')
            ->assertSee('RFQ RECIBIDA')
            ->assertSee('data-ready-for-analysis="1"', false)
            ->assertSee('data-has-manual-quote="1"', false);
    }
}
