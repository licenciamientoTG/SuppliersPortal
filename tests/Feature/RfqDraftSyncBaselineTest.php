<?php

namespace Tests\Feature;

use App\Livewire\Rfq\QuotationWizard;
use App\Models\QuotationGroup;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Rfq;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Caracteriza el comportamiento actual de QuotationWizard::completeStep3
 * (sincronización de RFQs en borrador por grupo) antes de extraerlo a servicios.
 */
class RfqDraftSyncBaselineTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Requisition $requisition;

    private QuotationGroup $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->requisition = Requisition::factory()->create(['validated_at' => now()]);
        $this->group = QuotationGroup::factory()->create(['requisition_id' => $this->requisition->id]);
        $item = RequisitionItem::factory()->create(['requisition_id' => $this->requisition->id]);
        $this->group->items()->attach($item->id, ['sort_order' => 1]);
    }

    private function groupData(array $supplierIds, string $deadline, ?string $notes = null): array
    {
        return [[
            'group_id' => $this->group->id,
            'supplier_ids' => $supplierIds,
            'response_deadline' => $deadline,
            'notes' => $notes,
        ]];
    }

    public function test_creates_new_draft_rfq_with_suppliers_and_daily_folio_format(): void
    {
        $suppliers = Supplier::factory()->count(2)->create();
        $deadline = now()->addDays(7)->format('Y-m-d');

        Livewire::test(QuotationWizard::class, ['requisition' => $this->requisition])
            ->call('completeStep3', $this->groupData($suppliers->pluck('id')->all(), $deadline, 'Notas de prueba'));

        $rfq = Rfq::where('quotation_group_id', $this->group->id)->firstOrFail();

        $this->assertEquals('DRAFT', $rfq->status);
        $this->assertMatchesRegularExpression('/^RFQ-\d{8}-\d{4}$/', $rfq->folio);
        $this->assertEquals($deadline, $rfq->response_deadline->format('Y-m-d'));
        $this->assertEquals('Notas de prueba', $rfq->message);
        $this->assertEquals($this->user->id, $rfq->created_by);

        $this->assertEqualsCanonicalizing(
            $suppliers->pluck('id')->all(),
            $rfq->suppliers->pluck('id')->all()
        );
        $rfq->suppliers->each(fn ($s) => $this->assertNotNull($s->pivot->invited_at));
    }

    public function test_no_changes_leaves_existing_draft_untouched(): void
    {
        $supplier = Supplier::factory()->create();
        $deadline = now()->addDays(7)->format('Y-m-d');

        $rfq = Rfq::factory()->create([
            'requisition_id' => $this->requisition->id,
            'quotation_group_id' => $this->group->id,
            'status' => 'DRAFT',
            'response_deadline' => $deadline,
            'message' => 'Sin cambios',
        ]);
        $rfq->suppliers()->attach($supplier->id, ['invited_at' => now()]);

        Livewire::test(QuotationWizard::class, ['requisition' => $this->requisition])
            ->call('completeStep3', $this->groupData([$supplier->id], $deadline, 'Sin cambios'));

        $this->assertEquals(1, Rfq::where('quotation_group_id', $this->group->id)->count());
        $fresh = $rfq->fresh();
        $this->assertEquals('DRAFT', $fresh->status);
        $this->assertEquals($rfq->folio, $fresh->folio);
    }

    public function test_draft_with_changes_is_updated_and_suppliers_synced(): void
    {
        $originalSupplier = Supplier::factory()->create();
        $newSupplier = Supplier::factory()->create();

        $rfq = Rfq::factory()->create([
            'requisition_id' => $this->requisition->id,
            'quotation_group_id' => $this->group->id,
            'status' => 'DRAFT',
            'response_deadline' => now()->addDays(5),
            'message' => 'Original',
        ]);
        $rfq->suppliers()->attach($originalSupplier->id, ['invited_at' => now()]);

        $newDeadline = now()->addDays(10)->format('Y-m-d');

        Livewire::test(QuotationWizard::class, ['requisition' => $this->requisition])
            ->call('completeStep3', $this->groupData([$newSupplier->id], $newDeadline, 'Actualizado'));

        $this->assertEquals(1, Rfq::where('quotation_group_id', $this->group->id)->count());
        $fresh = $rfq->fresh();
        $this->assertEquals('DRAFT', $fresh->status);
        $this->assertEquals($newDeadline, $fresh->response_deadline->format('Y-m-d'));
        $this->assertEquals('Actualizado', $fresh->message);
        $this->assertEquals([$newSupplier->id], $fresh->suppliers->pluck('id')->all());
    }

    public function test_sent_rfq_with_changes_is_cancelled_and_replaced_by_new_draft(): void
    {
        $originalSupplier = Supplier::factory()->create();
        $newSupplier = Supplier::factory()->create();

        $rfq = Rfq::factory()->create([
            'requisition_id' => $this->requisition->id,
            'quotation_group_id' => $this->group->id,
            'status' => 'SENT',
            'response_deadline' => now()->addDays(5),
            'message' => 'Original',
        ]);
        $rfq->suppliers()->attach($originalSupplier->id, ['invited_at' => now()]);

        $newDeadline = now()->addDays(10)->format('Y-m-d');

        Livewire::test(QuotationWizard::class, ['requisition' => $this->requisition])
            ->call('completeStep3', $this->groupData([$newSupplier->id], $newDeadline));

        $cancelled = $rfq->fresh();
        $this->assertEquals('CANCELLED', $cancelled->status);
        $this->assertEquals($this->user->id, $cancelled->cancelled_by);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertEquals('Actualización manual de proveedores tras envío.', $cancelled->cancellation_reason);

        $replacement = Rfq::where('quotation_group_id', $this->group->id)
            ->where('id', '!=', $rfq->id)
            ->firstOrFail();
        $this->assertEquals('DRAFT', $replacement->status);
        $this->assertEquals([$newSupplier->id], $replacement->suppliers->pluck('id')->all());
    }
}
