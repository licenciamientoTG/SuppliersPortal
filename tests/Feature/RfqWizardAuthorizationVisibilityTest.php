<?php

namespace Tests\Feature;

use App\Models\QuotationGroup;
use App\Models\QuotationSummary;
use App\Models\Requisition;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RfqWizardAuthorizationVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_see_the_current_authorizer_for_an_rfq_group(): void
    {
        Role::findOrCreate('superadmin', 'web');
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');
        $approver = User::factory()->create(['name' => 'Responsable de autorización']);

        $requisition = Requisition::factory()->create();
        $group = QuotationGroup::factory()->create(['requisition_id' => $requisition->id]);
        $rfq = Rfq::factory()->create([
            'requisition_id' => $requisition->id,
            'quotation_group_id' => $group->id,
            'status' => 'EVALUATED',
        ]);
        QuotationSummary::factory()->create([
            'requisition_id' => $requisition->id,
            'rfq_id' => $rfq->id,
            'approval_status' => 'pending',
            'current_approver_user_id' => $approver->id,
        ]);

        $this->actingAs($superadmin)
            ->getJson(route('rfq.wizard.analysis.data', $requisition))
            ->assertOk()
            ->assertJsonPath('data.0.authorization.label', 'Enviada a autorizar')
            ->assertJsonPath('data.0.authorization.recipient', $approver->name);
    }
}
