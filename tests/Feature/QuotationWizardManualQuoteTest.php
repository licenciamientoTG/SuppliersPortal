<?php

namespace Tests\Feature;

use App\Livewire\Rfq\QuotationWizard;
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

class QuotationWizardManualQuoteTest extends TestCase
{
    use RefreshDatabase;

    private function makeGroupWithOneItem(Requisition $requisition): QuotationGroup
    {
        $group = QuotationGroup::factory()->create(['requisition_id' => $requisition->id]);
        $item = RequisitionItem::factory()->create(['requisition_id' => $requisition->id]);
        $group->items()->attach($item->id, ['sort_order' => 1]);

        return $group;
    }

    public function test_saving_manual_quote_for_a_single_supplier_group_marks_rfq_received(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $requisition = Requisition::factory()->create(['validated_at' => now()]);
        $group = $this->makeGroupWithOneItem($requisition);
        $item = $group->items->first();
        $supplier = Supplier::factory()->create();

        Livewire::test(QuotationWizard::class, ['requisition' => $requisition])
            ->call('openManualQuoteModal', $group->id)
            ->assertSet('showManualQuoteModal', true)
            ->set('manualQuoteSupplierId', $supplier->id)
            ->set("manualQuoteItems.{$item->id}.unit_price", 150)
            ->set("manualQuoteItems.{$item->id}.iva_rate", 16)
            ->set("manualQuoteItems.{$item->id}.delivery_days", 5)
            ->call('saveManualQuote')
            ->assertSet('showManualQuoteModal', false)
            ->assertSet('currentStep', 5);

        $rfq = Rfq::where('quotation_group_id', $group->id)->firstOrFail();
        $this->assertEquals('RECEIVED', $rfq->status);

        $response = RfqResponse::where('rfq_id', $rfq->id)
            ->where('supplier_id', $supplier->id)
            ->where('requisition_item_id', $item->id)
            ->firstOrFail();

        $this->assertEquals('SUBMITTED', $response->status);
        $this->assertEquals('buyer_manual', $response->entry_source);
        $this->assertEquals($user->id, $response->entered_by);
        $this->assertEquals(150.0, (float) $response->unit_price);

        $pivot = $rfq->suppliers()->where('supplier_id', $supplier->id)->first();
        $this->assertNotNull($pivot->pivot->responded_at);
    }

    public function test_mixed_group_stays_sent_until_portal_supplier_also_responds(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $requisition = Requisition::factory()->create(['validated_at' => now()]);
        $group = $this->makeGroupWithOneItem($requisition);
        $item = $group->items->first();

        $manualSupplier = Supplier::factory()->create();
        $portalSupplier = Supplier::factory()->create();

        $rfq = Rfq::factory()->create([
            'requisition_id' => $requisition->id,
            'quotation_group_id' => $group->id,
            'status' => 'SENT',
        ]);
        $rfq->suppliers()->attach($portalSupplier->id, ['invited_at' => now()]);

        Livewire::test(QuotationWizard::class, ['requisition' => $requisition])
            ->call('openManualQuoteModal', $group->id)
            ->set('manualQuoteSupplierId', $manualSupplier->id)
            ->set("manualQuoteItems.{$item->id}.unit_price", 80)
            ->set("manualQuoteItems.{$item->id}.iva_rate", 16)
            ->set("manualQuoteItems.{$item->id}.delivery_days", 3)
            ->call('saveManualQuote');

        $this->assertEquals('SENT', $rfq->fresh()->status);
    }

    public function test_not_available_item_is_saved_without_requiring_price(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $requisition = Requisition::factory()->create(['validated_at' => now()]);
        $group = $this->makeGroupWithOneItem($requisition);
        $item = $group->items->first();
        $supplier = Supplier::factory()->create();

        Livewire::test(QuotationWizard::class, ['requisition' => $requisition])
            ->call('openManualQuoteModal', $group->id)
            ->set('manualQuoteSupplierId', $supplier->id)
            ->set("manualQuoteItems.{$item->id}.not_available", true)
            ->call('saveManualQuote')
            ->assertHasNoErrors();

        $response = RfqResponse::where('supplier_id', $supplier->id)->firstOrFail();
        $this->assertTrue($response->not_available);
        $this->assertEquals(0, (float) $response->unit_price);
    }

    public function test_new_supplier_email_colliding_with_existing_supplier_fails_validation_gracefully(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $existingSupplier = Supplier::factory()->create(['email' => 'duplicado@example.com']);

        $requisition = Requisition::factory()->create(['validated_at' => now()]);
        $group = $this->makeGroupWithOneItem($requisition);
        $item = $group->items->first();

        $suppliersBefore = Supplier::where('email', 'duplicado@example.com')->count();

        Livewire::test(QuotationWizard::class, ['requisition' => $requisition])
            ->call('openManualQuoteModal', $group->id)
            ->set('manualQuoteSupplierId', null)
            ->set('manualQuoteNewSupplier', [
                'company_name' => 'Proveedor Nuevo SA',
                'rfc' => 'PNS900101AB1',
                'postal_code' => '64000',
                'contact_person' => '',
                'email' => 'duplicado@example.com',
                'phone_number' => '',
            ])
            ->set("manualQuoteItems.{$item->id}.unit_price", 100)
            ->set("manualQuoteItems.{$item->id}.iva_rate", 16)
            ->set("manualQuoteItems.{$item->id}.delivery_days", 5)
            ->call('saveManualQuote')
            ->assertHasErrors('manualQuoteNewSupplier.email');

        $this->assertSame(
            $suppliersBefore,
            Supplier::where('email', 'duplicado@example.com')->count()
        );
        $this->assertNull(Supplier::where('rfc', 'PNS900101AB1')->first());
    }

    public function test_new_supplier_email_unique_validation_uses_spanish_message(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $existingSupplier = Supplier::factory()->create(['email' => 'test@example.com']);

        $requisition = Requisition::factory()->create(['validated_at' => now()]);
        $group = $this->makeGroupWithOneItem($requisition);
        $item = $group->items->first();

        $component = Livewire::test(QuotationWizard::class, ['requisition' => $requisition])
            ->call('openManualQuoteModal', $group->id)
            ->set('manualQuoteSupplierId', null)
            ->set('manualQuoteNewSupplier', [
                'company_name' => 'Nueva Empresa',
                'rfc' => 'NEM900101CD1',
                'postal_code' => '28001',
                'contact_person' => '',
                'email' => 'test@example.com',
                'phone_number' => '',
            ])
            ->set("manualQuoteItems.{$item->id}.unit_price", 100)
            ->set("manualQuoteItems.{$item->id}.iva_rate", 16)
            ->set("manualQuoteItems.{$item->id}.delivery_days", 5)
            ->call('saveManualQuote');

        $component->assertHasErrors(['manualQuoteNewSupplier.email']);

        $errorBag = $component->instance()->getErrorBag();
        $errorMessages = $errorBag->get('manualQuoteNewSupplier.email');
        $this->assertNotEmpty($errorMessages);
        $this->assertStringContainsString('Ya existe un proveedor registrado con este correo electrónico', $errorMessages[0]);
    }
}
