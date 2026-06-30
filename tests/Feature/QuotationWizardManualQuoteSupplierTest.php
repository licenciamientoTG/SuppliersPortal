<?php

namespace Tests\Feature;

use App\Livewire\Rfq\QuotationWizard;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationWizardManualQuoteSupplierTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_new_external_supplier_with_minimal_data(): void
    {
        $component = new QuotationWizard();
        $component->manualQuoteNewSupplier = [
            'company_name' => 'Tornillos del Norte SA',
            'rfc' => 'tdn900101ab1',
            'postal_code' => '64000',
            'contact_person' => '',
            'email' => '',
            'phone_number' => '',
        ];

        $supplier = $component->resolveManualQuoteSupplier();

        $this->assertNotNull($supplier);
        $this->assertTrue($supplier->is_external);
        $this->assertFalse($supplier->is_active);
        $this->assertEquals('TDN900101AB1', $supplier->rfc);
        $this->assertEquals('Tornillos del Norte SA', $supplier->company_name);
        $this->assertEquals('64000', $supplier->postal_code);
    }

    public function test_reuses_existing_supplier_by_id(): void
    {
        $existing = Supplier::factory()->create();
        $component = new QuotationWizard();
        $component->manualQuoteSupplierId = $existing->id;

        $supplier = $component->resolveManualQuoteSupplier();

        $this->assertTrue($supplier->is($existing));
    }

    public function test_rejects_duplicate_rfc_and_suggests_existing_supplier(): void
    {
        Supplier::factory()->create([
            'rfc' => 'TDN900101AB1',
            'company_name' => 'Tornillos del Norte SA',
        ]);

        $component = new QuotationWizard();
        $component->manualQuoteNewSupplier = [
            'company_name' => 'Otro nombre',
            'rfc' => 'tdn900101ab1',
            'postal_code' => '64000',
            'contact_person' => '',
            'email' => '',
            'phone_number' => '',
        ];

        $supplier = $component->resolveManualQuoteSupplier();

        $this->assertNull($supplier);
        $this->assertTrue($component->getErrorBag()->has('manualQuoteNewSupplier.rfc'));
        $this->assertStringContainsString(
            'Tornillos del Norte SA',
            $component->getErrorBag()->first('manualQuoteNewSupplier.rfc')
        );
    }
}
