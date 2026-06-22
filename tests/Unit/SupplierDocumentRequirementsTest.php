<?php

namespace Tests\Unit;

use App\Models\Supplier;
use App\Models\SupplierDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierDocumentRequirementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_persona_fisica_uses_base_documents_only(): void
    {
        $supplier = Supplier::factory()->create([
            'person_type' => 'fisica',
            'provides_specialized_services' => false,
        ]);

        $required = SupplierDocument::requiredTypesFor($supplier);

        $this->assertContains('constancia_fiscal', $required);
        $this->assertContains('identificacion_oficial', $required);
        $this->assertNotContains('acta_constitutiva', $required);
        $this->assertNotContains('poder_legal', $required);
        $this->assertNotContains('repse', $required);
    }

    public function test_persona_moral_requires_constitutive_and_power_documents(): void
    {
        $supplier = Supplier::factory()->create([
            'person_type' => 'moral',
            'provides_specialized_services' => false,
        ]);

        $required = SupplierDocument::requiredTypesFor($supplier);

        $this->assertContains('acta_constitutiva', $required);
        $this->assertContains('poder_legal', $required);
        $this->assertNotContains('repse', $required);
    }

    public function test_repse_is_required_only_for_specialized_suppliers(): void
    {
        $supplier = Supplier::factory()->repse()->create([
            'person_type' => 'fisica',
        ]);

        $required = SupplierDocument::requiredTypesFor($supplier);

        $this->assertContains('repse', $required);
    }

    public function test_persona_fisica_can_reach_document_approved_without_moral_documents(): void
    {
        $supplier = Supplier::factory()->create([
            'person_type' => 'fisica',
            'provides_specialized_services' => false,
            'document_status' => 'pending',
        ]);

        foreach (SupplierDocument::requiredTypesFor($supplier) as $docType) {
            SupplierDocument::create([
                'supplier_id' => $supplier->id,
                'doc_type' => $docType,
                'path_file' => "suppliers/{$supplier->id}/{$docType}.pdf",
                'status' => 'accepted',
                'uploaded_at' => now(),
            ]);
        }

        $status = $supplier->recalculateDocumentStatus();

        $this->assertSame('approved', $status);
        $this->assertSame([], $supplier->fresh()->missingRequiredDocuments());
    }
}
