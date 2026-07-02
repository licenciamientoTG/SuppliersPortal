<?php

namespace Tests\Feature;

use App\Livewire\Rfq\QuotationWizard;
use App\Models\QuotationGroup;
use App\Models\Requisition;
use App\Models\Rfq;
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
}
