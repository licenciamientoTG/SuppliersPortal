<?php

namespace Tests\Feature;

use App\Livewire\Rfq\QuotationWizard;
use App\Models\QuotationGroup;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Rfq;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuotationWizardStepDeterminationTest extends TestCase
{
    use RefreshDatabase;

    public function test_wizard_stays_on_step_4_if_any_active_group_is_still_pending(): void
    {
        $requisition = Requisition::factory()->create(['validated_at' => now()]);
        $groupA = QuotationGroup::factory()->create(['requisition_id' => $requisition->id]);
        $groupB = QuotationGroup::factory()->create(['requisition_id' => $requisition->id]);

        Rfq::factory()->create([
            'requisition_id' => $requisition->id,
            'quotation_group_id' => $groupA->id,
            'status' => 'RECEIVED',
        ]);
        Rfq::factory()->create([
            'requisition_id' => $requisition->id,
            'quotation_group_id' => $groupB->id,
            'status' => 'SENT',
        ]);

        Livewire::test(QuotationWizard::class, ['requisition' => $requisition])
            ->assertSet('currentStep', 4);
    }

    public function test_wizard_jumps_to_step_5_only_when_all_active_groups_received(): void
    {
        $requisition = Requisition::factory()->create(['validated_at' => now()]);
        $group = QuotationGroup::factory()->create(['requisition_id' => $requisition->id]);

        Rfq::factory()->create([
            'requisition_id' => $requisition->id,
            'quotation_group_id' => $group->id,
            'status' => 'RECEIVED',
        ]);

        Livewire::test(QuotationWizard::class, ['requisition' => $requisition])
            ->assertSet('currentStep', 5);
    }

    public function test_next_step_from_planning_loads_supplier_data_before_rendering_step_3(): void
    {
        $requisition = Requisition::factory()->create(['validated_at' => now()]);
        $item = RequisitionItem::factory()->create(['requisition_id' => $requisition->id]);
        $group = QuotationGroup::factory()->create(['requisition_id' => $requisition->id]);
        $group->items()->attach($item->id, ['sort_order' => 1]);

        $supplier = Supplier::factory()->create();
        $rfq = Rfq::factory()->create([
            'requisition_id' => $requisition->id,
            'quotation_group_id' => $group->id,
            'status' => 'DRAFT',
            'response_deadline' => now()->addDays(7),
        ]);
        $rfq->suppliers()->attach($supplier->id, ['invited_at' => now()]);

        Livewire::test(QuotationWizard::class, ['requisition' => $requisition])
            ->set('currentStep', 2)
            ->call('nextStep')
            ->assertSet('currentStep', 3)
            ->assertSet('suppliersData.0.group_id', $group->id)
            ->assertSet('suppliersData.0.supplier_ids.0', $supplier->id);
    }

    public function test_supplier_data_ignores_cancelled_rfqs_for_the_same_group(): void
    {
        $requisition = Requisition::factory()->create(['validated_at' => now()]);
        $group = QuotationGroup::factory()->create(['requisition_id' => $requisition->id]);
        $cancelledSupplier = Supplier::factory()->create();
        $activeSupplier = Supplier::factory()->create();

        $cancelledRfq = Rfq::factory()->create([
            'requisition_id' => $requisition->id,
            'quotation_group_id' => $group->id,
            'status' => 'CANCELLED',
        ]);
        $cancelledRfq->suppliers()->attach($cancelledSupplier->id, ['invited_at' => now(), 'responded_at' => now()]);

        $activeRfq = Rfq::factory()->create([
            'requisition_id' => $requisition->id,
            'quotation_group_id' => $group->id,
            'status' => 'DRAFT',
        ]);
        $activeRfq->suppliers()->attach($activeSupplier->id, ['invited_at' => now()]);

        Livewire::test(QuotationWizard::class, ['requisition' => $requisition])
            ->set('currentStep', 3)
            ->call('loadSuppliersData')
            ->assertSet('suppliersData.0.group_id', $group->id)
            ->assertSet('suppliersData.0.supplier_ids.0', $activeSupplier->id);
    }
}
