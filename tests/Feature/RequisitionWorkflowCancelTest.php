<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckLockScreen;
use App\Http\Middleware\ModuleAccess;
use App\Models\Company;
use App\Models\ReceivingLocation;
use App\Models\Requisition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Restricciones de RequisitionWorkflowController::cancel: un borrador solo lo
 * cancela su requisitor (o un administrador).
 */
class RequisitionWorkflowCancelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([ModuleAccess::class, CheckLockScreen::class]);
        Notification::fake();
        Role::findOrCreate('buyer', 'web');
        Role::findOrCreate('admin', 'web');
    }

    public function test_a_buyer_cannot_cancel_someone_elses_draft(): void
    {
        $requisition = $this->createRequisition(User::factory()->create(), 'DRAFT');
        $buyer = User::factory()->create()->assignRole('buyer');

        $this->actingAs($buyer)
            ->postJson(route('requisitions.workflow.cancel', $requisition), ['reason' => 'Ya no se necesita.'])
            ->assertStatus(422)
            ->assertJson(['message' => 'Solo el requisitor puede cancelar un borrador.']);

        $this->assertSame('DRAFT', $requisition->fresh()->status->value);
    }

    public function test_the_requester_can_cancel_their_own_draft(): void
    {
        $requester = User::factory()->create();
        $requisition = $this->createRequisition($requester, 'DRAFT');

        $this->actingAs($requester)
            ->postJson(route('requisitions.workflow.cancel', $requisition), ['reason' => 'Ya no se necesita.'])
            ->assertOk();

        $this->assertSame('CANCELLED', $requisition->fresh()->status->value);
    }

    public function test_an_admin_can_cancel_someone_elses_draft(): void
    {
        $requisition = $this->createRequisition(User::factory()->create(), 'DRAFT');
        $admin = User::factory()->create()->assignRole(['admin', 'buyer']);

        $this->actingAs($admin)
            ->postJson(route('requisitions.workflow.cancel', $requisition), ['reason' => 'Ya no se necesita.'])
            ->assertOk();

        $this->assertSame('CANCELLED', $requisition->fresh()->status->value);
    }

    public function test_a_buyer_can_still_cancel_a_requisition_in_quotation(): void
    {
        $requisition = $this->createRequisition(User::factory()->create(), 'IN_QUOTATION');
        $buyer = User::factory()->create()->assignRole('buyer');

        $this->actingAs($buyer)
            ->postJson(route('requisitions.workflow.cancel', $requisition), ['reason' => 'Se cancela desde Compras.'])
            ->assertOk();

        $this->assertSame('CANCELLED', $requisition->fresh()->status->value);
    }

    private function createRequisition(User $requester, string $status): Requisition
    {
        return Requisition::factory()->create([
            'requested_by' => $requester->id,
            'created_by' => $requester->id,
            'updated_by' => $requester->id,
            'company_id' => Company::factory()->create()->id,
            'receiving_location_id' => ReceivingLocation::factory()->create()->id,
            'required_date' => now()->addDays(5)->toDateString(),
            'status' => $status,
        ]);
    }
}
