<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Models\SupplierDocumentRequirement;
use App\Models\SupplierDocumentType;
use App\Models\User;
use App\Services\SupplierDocumentRequirementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupplierDocumentCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_buyer_and_superadmin_can_open_document_catalog(): void
    {
        foreach (['buyer', 'superadmin'] as $role) {
            Role::findOrCreate($role, 'web');
            $user = User::factory()->create();
            $user->assignRole($role);
            $this->actingAs($user)->get(route('supplier-document-types.index'))->assertOk();
        }

        Role::findOrCreate('accounting', 'web');
        $accountant = User::factory()->create();
        $accountant->assignRole('accounting');
        $this->actingAs($accountant)->get(route('supplier-document-types.index'))->assertForbidden();
    }

    public function test_periodic_requirement_blocks_only_after_accepted_version_expires(): void
    {
        $supplier = Supplier::factory()->create(['person_type' => 'fisica', 'approval_status' => 'approved', 'is_active' => true]);
        $type = SupplierDocumentType::query()->where('code', 'opinion_sat')->firstOrFail();
        $type->update(['renewal_mode' => 'periodic', 'renewal_interval_days' => 30]);
        $service = app(SupplierDocumentRequirementService::class);
        $requirement = $service->requirementForUpload($supplier, $type);
        $document = SupplierDocument::create([
            'supplier_id' => $supplier->id, 'doc_type' => $type->code,
            'supplier_document_type_id' => $type->id, 'supplier_document_requirement_id' => $requirement->id,
            'path_file' => 'supplier/test.pdf', 'status' => 'accepted', 'uploaded_at' => now(), 'reviewed_at' => now(),
        ]);
        $service->accept($document, now()->addMonth()->toDateString(), null);

        $this->assertFalse($service->hasBlockingRequirements($supplier));
        $requirement->update(['expires_at' => now()->subDay(), 'status' => 'expired']);
        $this->assertTrue($service->hasBlockingRequirements($supplier));
    }

    public function test_new_catalog_requirement_grants_existing_supplier_fourteen_days(): void
    {
        $supplier = Supplier::factory()->create(['person_type' => 'fisica']);
        $type = SupplierDocumentType::create([
            'code' => 'nuevo_documento', 'name' => 'Nuevo documento', 'is_active' => true, 'is_required' => true,
            'applies_to_physical' => true, 'applies_to_legal' => false, 'renewal_mode' => 'once', 'validity_source' => 'manual',
        ]);
        app(SupplierDocumentRequirementService::class)->synchronizeExistingForType($type);

        $requirement = SupplierDocumentRequirement::where('supplier_id', $supplier->id)->where('supplier_document_type_id', $type->id)->firstOrFail();
        $this->assertTrue($requirement->due_at->isSameDay(now()->addDays(14)));
    }
}
