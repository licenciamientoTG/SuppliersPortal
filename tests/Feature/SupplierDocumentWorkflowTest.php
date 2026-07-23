<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Models\SupplierDocumentType;
use App\Models\User;
use App\Notifications\BuyerWorkflowNotification;
use App\Notifications\SupplierAccountReviewedNotification;
use App\Notifications\SupplierDocumentRenewalNotification;
use App\Notifications\SupplierDocumentReviewedNotification;
use App\Services\SupplierDocumentAutoAcceptanceService;
use App\Services\SupplierDocumentRequirementService;
use App\Services\SupplierDocumentReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupplierDocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('buyer', 'web');
        Role::findOrCreate('superadmin', 'web');
        Storage::fake('supplier_documents');
    }

    public function test_manual_acceptance_notifies_supplier_and_updates_current_requirement(): void
    {
        Notification::fake();
        $buyer = User::factory()->create();
        $buyer->assignRole('buyer');
        $supplier = Supplier::factory()->create(['person_type' => 'fisica', 'document_status' => 'pending']);
        $type = SupplierDocumentType::query()->where('code', 'comprobante_domicilio')->firstOrFail();
        $requirements = app(SupplierDocumentRequirementService::class);
        $requirement = $requirements->requirementForUpload($supplier, $type);
        $document = $this->document($supplier, $type, $requirement?->id);

        $this->actingAs($buyer)
            ->postJson(route('admin.review.documents.accept', $document), [
                'compliance_status' => 'NO APLICA',
            ])
            ->assertOk()
            ->assertJsonPath('new_status', 'accepted');

        $this->assertSame('accepted', $document->fresh()->status);
        $this->assertSame('compliant', $requirement?->fresh()->status);
        $this->assertSame($document->id, $requirement?->fresh()->current_document_id);
        Notification::assertSentTo($supplier, SupplierDocumentReviewedNotification::class);
    }

    public function test_rejection_notifies_supplier_with_recorded_reason(): void
    {
        Notification::fake();
        $buyer = User::factory()->create();
        $buyer->assignRole('buyer');
        $supplier = Supplier::factory()->create(['person_type' => 'fisica']);
        $type = SupplierDocumentType::query()->where('code', 'comprobante_domicilio')->firstOrFail();
        $requirements = app(SupplierDocumentRequirementService::class);
        $requirement = $requirements->requirementForUpload($supplier, $type);
        $document = $this->document($supplier, $type, $requirement?->id);

        $this->actingAs($buyer)
            ->postJson(route('admin.review.documents.reject', $document), [
                'reason' => 'El comprobante no es legible.',
            ])
            ->assertOk();

        $this->assertSame('rejected', $document->fresh()->status);
        $this->assertSame('El comprobante no es legible.', $document->fresh()->rejection_reason);
        Notification::assertSentTo($supplier, SupplierDocumentReviewedNotification::class);
    }

    public function test_automatic_acceptance_notifies_supplier_and_buyers(): void
    {
        Notification::fake();
        $buyer = User::factory()->create();
        $buyer->assignRole('buyer');
        $supplier = Supplier::factory()->create(['person_type' => 'fisica', 'rfc' => 'AAA010101AAA']);
        $type = SupplierDocumentType::query()->where('code', 'opinion_sat')->firstOrFail();
        $type->update([
            'renewal_mode' => 'periodic',
            'renewal_interval_value' => 3,
            'renewal_interval_unit' => 'months',
        ]);
        $requirements = app(SupplierDocumentRequirementService::class);
        $requirement = $requirements->requirementForUpload($supplier, $type);
        $document = $this->document($supplier, $type, $requirement?->id, [
            'issued_at' => now()->subMonth(),
            'issue_date_extraction_data' => [
                'rfc' => 'AAA010101AAA',
                'compliance_status' => 'POSITIVA',
                'issued_at' => now()->subMonth()->toDateString(),
            ],
        ]);

        $this->assertTrue(app(SupplierDocumentAutoAcceptanceService::class)->acceptIfEligible($document, $requirements));

        Notification::assertSentTo($supplier, SupplierDocumentReviewedNotification::class);
        Notification::assertSentTo($buyer, BuyerWorkflowNotification::class);
    }

    public function test_private_document_can_only_be_opened_by_owner_or_authorized_buyer(): void
    {
        $owner = Supplier::factory()->create();
        $otherSupplier = Supplier::factory()->create();
        $buyer = User::factory()->create();
        $buyer->assignRole('buyer');
        $type = SupplierDocumentType::query()->where('code', 'comprobante_domicilio')->firstOrFail();
        $document = $this->document($owner, $type);
        Storage::disk('supplier_documents')->put($document->path_file, '%PDF-private');

        $this->actingAs($owner, 'supplier')
            ->get(route('supplier-documents.file', $document))
            ->assertOk();

        $this->actingAs($otherSupplier, 'supplier')
            ->get(route('supplier-documents.file', $document))
            ->assertForbidden();

        $this->actingAs($buyer)
            ->get(route('supplier-documents.file', $document))
            ->assertOk();
    }

    public function test_rejected_renewal_does_not_invalidate_a_still_current_document(): void
    {
        Notification::fake();
        $buyer = User::factory()->create();
        $buyer->assignRole('buyer');
        $supplier = Supplier::factory()->create(['person_type' => 'fisica']);
        $type = SupplierDocumentType::query()->where('code', 'opinion_sat')->firstOrFail();
        SupplierDocumentType::query()->whereKeyNot($type->id)->update(['is_required' => false]);
        $type->update([
            'renewal_mode' => 'periodic',
            'renewal_interval_value' => 3,
            'renewal_interval_unit' => 'months',
        ]);
        $requirements = app(SupplierDocumentRequirementService::class);
        $requirement = $requirements->requirementForUpload($supplier, $type);
        $current = $this->document($supplier, $type, $requirement?->id, ['status' => 'accepted']);
        $requirements->accept($current, now()->subMonth()->toDateString(), $buyer->id);
        $renewal = $this->document($supplier, $type, $requirement?->id, [
            'path_file' => "suppliers/{$supplier->id}/documents/renewal.pdf",
        ]);

        $requirements->markSubmitted($requirement->fresh());
        $this->assertSame('compliant', $requirement->fresh()->status);

        app(SupplierDocumentReviewService::class)->reject($renewal, 'La opinión no es positiva.', $buyer);

        $this->assertSame('compliant', $requirement->fresh()->status);
        $this->assertSame($current->id, $requirement->fresh()->current_document_id);
        $this->assertFalse($requirements->hasBlockingRequirements($supplier));
    }

    public function test_private_storage_migration_moves_existing_public_documents(): void
    {
        Storage::fake('public');
        $supplier = Supplier::factory()->create();
        $type = SupplierDocumentType::query()->where('code', 'comprobante_domicilio')->firstOrFail();
        $document = $this->document($supplier, $type);
        Storage::disk('public')->put($document->path_file, '%PDF-legacy');

        $this->artisan('supplier-documents:migrate-private')->assertSuccessful();

        Storage::disk('supplier_documents')->assertExists($document->path_file);
        Storage::disk('public')->assertMissing($document->path_file);
    }

    public function test_supplier_cannot_be_finally_approved_until_document_file_is_complete(): void
    {
        Notification::fake();
        $buyer = User::factory()->create();
        $buyer->assignRole('buyer');
        $supplier = Supplier::factory()->create([
            'person_type' => 'fisica',
            'approval_status' => 'pending',
            'document_status' => 'pending',
        ]);

        $this->actingAs($buyer)
            ->postJson(route('admin.suppliers.approve', $supplier))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El expediente documental debe estar completo antes de aprobar el alta del proveedor.');

        SupplierDocumentType::query()->update(['is_required' => false]);
        $supplier->documentRequirements()->update(['is_enforced' => false]);
        $supplier->recalculateDocumentStatus();

        $this->actingAs($buyer)
            ->postJson(route('admin.suppliers.approve', $supplier))
            ->assertOk();

        $this->assertSame('approved', $supplier->fresh()->approval_status);
        Notification::assertSentTo($supplier, SupplierAccountReviewedNotification::class);
    }

    public function test_renewal_command_recovers_a_missed_seven_day_notice(): void
    {
        Notification::fake();
        $supplier = Supplier::factory()->create(['person_type' => 'fisica']);
        $type = SupplierDocumentType::query()->where('code', 'opinion_sat')->firstOrFail();
        $type->update([
            'renewal_mode' => 'periodic',
            'renewal_interval_value' => 3,
            'renewal_interval_unit' => 'months',
        ]);
        $requirements = app(SupplierDocumentRequirementService::class);
        $requirement = $requirements->requirementForUpload($supplier, $type);
        $document = $this->document($supplier, $type, $requirement?->id, ['status' => 'accepted']);
        $requirements->accept($document, now()->subMonths(3)->addDays(6)->toDateString(), null);

        $this->artisan('supplier-documents:notify-renewals')->assertSuccessful();

        Notification::assertSentTo($supplier, SupplierDocumentRenewalNotification::class);
        $this->assertDatabaseHas('supplier_document_requirement_notifications', [
            'supplier_document_requirement_id' => $requirement?->id,
            'milestone_days' => 7,
        ]);
    }

    private function document(
        Supplier $supplier,
        SupplierDocumentType $type,
        ?int $requirementId = null,
        array $overrides = [],
    ): SupplierDocument {
        return SupplierDocument::create(array_merge([
            'supplier_id' => $supplier->id,
            'doc_type' => $type->code,
            'supplier_document_type_id' => $type->id,
            'supplier_document_requirement_id' => $requirementId,
            'path_file' => "suppliers/{$supplier->id}/documents/test-{$type->code}.pdf",
            'mime_type' => 'application/pdf',
            'status' => 'pending_review',
            'uploaded_at' => now(),
        ], $overrides));
    }
}
