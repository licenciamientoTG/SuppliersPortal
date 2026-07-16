<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Models\SupplierDocumentRequirement;
use App\Models\SupplierDocumentType;
use App\Models\User;
use App\Services\SupplierDocumentRequirementService;
use Carbon\Carbon;
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
        $type->update(['renewal_mode' => 'periodic', 'renewal_interval_value' => 3, 'renewal_interval_unit' => 'months']);
        $service = app(SupplierDocumentRequirementService::class);
        $requirement = $service->requirementForUpload($supplier, $type);
        $document = SupplierDocument::create([
            'supplier_id' => $supplier->id, 'doc_type' => $type->code,
            'supplier_document_type_id' => $type->id, 'supplier_document_requirement_id' => $requirement->id,
            'path_file' => 'supplier/test.pdf', 'status' => 'accepted', 'uploaded_at' => now(), 'reviewed_at' => now(),
        ]);
        $service->accept($document, now()->subMonth()->toDateString(), null);

        $this->assertFalse($service->hasBlockingRequirements($supplier));
        $requirement->update(['expires_at' => now()->subDay(), 'status' => 'expired']);
        $this->assertTrue($service->hasBlockingRequirements($supplier));
    }

    public function test_periodic_expiry_is_calculated_from_issue_date_not_approval_date(): void
    {
        $supplier = Supplier::factory()->create(['person_type' => 'fisica']);
        $type = SupplierDocumentType::query()->where('code', 'opinion_sat')->firstOrFail();
        $type->update(['renewal_mode' => 'periodic', 'renewal_interval_value' => 3, 'renewal_interval_unit' => 'months']);
        $service = app(SupplierDocumentRequirementService::class);
        $requirement = $service->requirementForUpload($supplier, $type);
        $document = SupplierDocument::create([
            'supplier_id' => $supplier->id, 'doc_type' => $type->code,
            'supplier_document_type_id' => $type->id, 'supplier_document_requirement_id' => $requirement->id,
            'path_file' => 'supplier/opinion.pdf', 'status' => 'accepted', 'uploaded_at' => now(), 'reviewed_at' => now(),
        ]);

        $service->accept($document, '2026-01-31', null);

        $this->assertSame('2026-04-30', $requirement->fresh()->expires_at->toDateString());
        $this->assertSame('2026-01-31', $document->fresh()->issued_at->toDateString());
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

    public function test_periodicity_calculates_days_weeks_months_and_years_without_overflow(): void
    {
        $type = new SupplierDocumentType(['renewal_mode' => 'periodic']);

        $type->fill(['renewal_interval_value' => 9, 'renewal_interval_unit' => 'days']);
        $this->assertSame('2026-01-10', $type->calculateExpiry(Carbon::parse('2026-01-01'))->toDateString());

        $type->fill(['renewal_interval_value' => 17, 'renewal_interval_unit' => 'weeks']);
        $this->assertSame('2026-05-01', $type->calculateExpiry(Carbon::parse('2026-01-02'))->toDateString());

        $type->fill(['renewal_interval_value' => 1, 'renewal_interval_unit' => 'months']);
        $this->assertSame('2026-02-28', $type->calculateExpiry(Carbon::parse('2026-01-31'))->toDateString());

        $type->fill(['renewal_interval_value' => 2, 'renewal_interval_unit' => 'years']);
        $this->assertSame('2026-02-28', $type->calculateExpiry(Carbon::parse('2024-02-29'))->toDateString());
    }

    public function test_periodicity_label_uses_correct_singular_and_plural(): void
    {
        $type = new SupplierDocumentType(['renewal_mode' => 'periodic', 'renewal_interval_value' => 1, 'renewal_interval_unit' => 'years']);
        $this->assertSame('Cada 1 año', $type->periodicityLabel());

        $type->renewal_interval_value = 2;
        $this->assertSame('Cada 2 años', $type->periodicityLabel());
    }
}
