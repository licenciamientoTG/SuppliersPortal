<?php

namespace Tests\Feature;

use App\Livewire\Rfq\Board\ManualQuoteModal;
use App\Models\ProductService;
use App\Models\QuotationGroup;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Rfq;
use App\Models\RfqResponse;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 4: captura de precio conocido con pre-llenado de memoria de precios.
 */
class QuotationBoardManualQuotePrefillTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function makeHistoricPrice(ProductService $product, Supplier $supplier, float $price, string $date): void
    {
        $requisition = Requisition::factory()->create();
        $item = RequisitionItem::factory()->create([
            'requisition_id' => $requisition->id,
            'product_service_id' => $product->id,
        ]);
        $rfq = Rfq::factory()->create(['requisition_id' => $requisition->id]);

        RfqResponse::factory()->create([
            'rfq_id' => $rfq->id,
            'supplier_id' => $supplier->id,
            'requisition_item_id' => $item->id,
            'status' => 'SUBMITTED',
            'submitted_at' => now(),
            'quotation_date' => $date,
            'unit_price' => $price,
            'iva_rate' => 8,
            'delivery_days' => 3,
        ]);
    }

    public function test_open_prefills_items_from_price_memory_and_preselects_supplier(): void
    {
        $product = ProductService::factory()->create();
        $historicSupplier = Supplier::factory()->create();
        $this->makeHistoricPrice($product, $historicSupplier, 1250.50, now()->subDays(20)->toDateString());

        $requisition = Requisition::factory()->create(['validated_at' => now()]);
        $group = QuotationGroup::factory()->create(['requisition_id' => $requisition->id]);
        $item = RequisitionItem::factory()->create([
            'requisition_id' => $requisition->id,
            'product_service_id' => $product->id,
        ]);
        $group->items()->attach($item->id, ['sort_order' => 1]);

        Livewire::test(ManualQuoteModal::class, ['requisition' => $requisition])
            ->call('open', $group->id)
            ->assertSet('show', true)
            ->assertSet("items.{$item->id}.unit_price", 1250.5)
            ->assertSet("items.{$item->id}.iva_rate", 8.0)
            ->assertSet("items.{$item->id}.delivery_days", 3)
            ->assertSet('supplierId', $historicSupplier->id)
            ->assertSee($historicSupplier->company_name)
            ->assertSee('1,250.50');
    }

    public function test_open_without_history_leaves_defaults(): void
    {
        $requisition = Requisition::factory()->create(['validated_at' => now()]);
        $group = QuotationGroup::factory()->create(['requisition_id' => $requisition->id]);
        $item = RequisitionItem::factory()->create(['requisition_id' => $requisition->id]);
        $group->items()->attach($item->id, ['sort_order' => 1]);

        Livewire::test(ManualQuoteModal::class, ['requisition' => $requisition])
            ->call('open', $group->id)
            ->assertSet("items.{$item->id}.unit_price", null)
            ->assertSet("items.{$item->id}.iva_rate", 16)
            ->assertSet('supplierId', null)
            ->assertSee('Sin historial');
    }

    public function test_save_captures_manual_quote_and_marks_rfq_received(): void
    {
        $requisition = Requisition::factory()->create(['validated_at' => now()]);
        $group = QuotationGroup::factory()->create(['requisition_id' => $requisition->id]);
        $item = RequisitionItem::factory()->create(['requisition_id' => $requisition->id]);
        $group->items()->attach($item->id, ['sort_order' => 1]);
        $supplier = Supplier::factory()->create();

        Livewire::test(ManualQuoteModal::class, ['requisition' => $requisition])
            ->call('open', $group->id)
            ->set('supplierId', $supplier->id)
            ->set("items.{$item->id}.unit_price", 300)
            ->set("items.{$item->id}.iva_rate", 16)
            ->set("items.{$item->id}.delivery_days", 2)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('show', false)
            ->assertDispatched('board-refresh');

        $rfq = Rfq::where('quotation_group_id', $group->id)->firstOrFail();
        $this->assertEquals('RECEIVED', $rfq->status);

        $response = RfqResponse::where('rfq_id', $rfq->id)->firstOrFail();
        $this->assertEquals('buyer_manual', $response->entry_source);
        $this->assertEquals($this->user->id, $response->entered_by);
        $this->assertEquals(300.0, (float) $response->unit_price);
    }

    public function test_duplicate_rfc_for_new_supplier_shows_error_without_creating(): void
    {
        $existing = Supplier::factory()->create(['rfc' => 'ABC900101XX1', 'company_name' => 'Proveedor Existente']);

        $requisition = Requisition::factory()->create(['validated_at' => now()]);
        $group = QuotationGroup::factory()->create(['requisition_id' => $requisition->id]);
        $item = RequisitionItem::factory()->create(['requisition_id' => $requisition->id]);
        $group->items()->attach($item->id, ['sort_order' => 1]);

        $before = Supplier::count();

        Livewire::test(ManualQuoteModal::class, ['requisition' => $requisition])
            ->call('open', $group->id)
            ->set('supplierId', null)
            ->set('newSupplier', [
                'company_name' => 'Duplicado SA',
                'rfc' => 'ABC900101XX1',
                'postal_code' => '64000',
                'contact_person' => '',
                'email' => '',
                'phone_number' => '',
            ])
            ->set("items.{$item->id}.unit_price", 100)
            ->set("items.{$item->id}.iva_rate", 16)
            ->set("items.{$item->id}.delivery_days", 5)
            ->call('save')
            ->assertHasErrors('newSupplier.rfc');

        $this->assertEquals($before, Supplier::count());
    }
}
