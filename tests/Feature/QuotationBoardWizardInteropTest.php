<?php

namespace Tests\Feature;

use App\Livewire\Rfq\Board\GroupCard;
use App\Livewire\Rfq\Board\ManualQuoteModal;
use App\Livewire\Rfq\QuotationBoard;
use App\Livewire\Rfq\QuotationWizard;
use App\Models\QuotationGroup;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Rfq;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fase 6: una requisición avanzada en el tablero se abre coherente en el
 * wizard (determineCurrentStep) y viceversa — ambos flujos comparten estado.
 */
class QuotationBoardWizardInteropTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('buyer', 'web');
        $this->user = User::factory()->create();
        $this->user->assignRole('buyer');
        $this->actingAs($this->user);
        Notification::fake();
    }

    public function test_board_progress_is_reflected_in_wizard_steps(): void
    {
        $requisition = Requisition::factory()->create(['validated_at' => null, 'status' => 'PENDING']);
        $item = RequisitionItem::factory()->create(['requisition_id' => $requisition->id]);
        $supplier = Supplier::factory()->create(['approval_status' => 'approved']);

        // Sin validar: el wizard abre en paso 1
        Livewire::test(QuotationWizard::class, ['requisition' => $requisition->fresh()])
            ->assertSet('currentStep', 1);

        // Validar en el tablero → wizard en paso 2
        Livewire::test(QuotationBoard::class, ['requisition' => $requisition->fresh()])
            ->set('validationData.specs_clear', true)
            ->set('validationData.time_feasible', true)
            ->set('validationData.alternatives_evaluated', true)
            ->call('signValidation');

        Livewire::test(QuotationWizard::class, ['requisition' => $requisition->fresh()])
            ->assertSet('currentStep', 2);

        // Crear grupo en el tablero → wizard en paso 3
        Livewire::test(QuotationBoard::class, ['requisition' => $requisition->fresh()])
            ->call('createGroup', [$item->id]);

        Livewire::test(QuotationWizard::class, ['requisition' => $requisition->fresh()])
            ->assertSet('currentStep', 3);

        // Enviar RFQ desde la tarjeta → wizard en paso 4
        $group = QuotationGroup::where('requisition_id', $requisition->id)->firstOrFail();

        Livewire::test(GroupCard::class, ['requisition' => $requisition->fresh(), 'groupId' => $group->id])
            ->set('supplierIds', [(string) $supplier->id])
            ->set('responseDeadline', now()->addDays(5)->format('Y-m-d'))
            ->call('sendNow')
            ->assertHasNoErrors();

        Livewire::test(QuotationWizard::class, ['requisition' => $requisition->fresh()])
            ->assertSet('currentStep', 4);
    }

    public function test_full_manual_capture_on_board_moves_wizard_to_analysis_step(): void
    {
        $requisition = Requisition::factory()->create(['validated_at' => now(), 'status' => 'IN_QUOTATION']);
        $group = QuotationGroup::factory()->create(['requisition_id' => $requisition->id]);
        $item = RequisitionItem::factory()->create(['requisition_id' => $requisition->id]);
        $group->items()->attach($item->id, ['sort_order' => 1]);
        $supplier = Supplier::factory()->create();

        Livewire::test(ManualQuoteModal::class, ['requisition' => $requisition])
            ->call('open', $group->id)
            ->set('supplierId', $supplier->id)
            ->set("items.{$item->id}.unit_price", 500)
            ->set("items.{$item->id}.iva_rate", 16)
            ->set("items.{$item->id}.delivery_days", 3)
            ->call('save')
            ->assertHasNoErrors();

        // Todas las RFQ activas quedaron RECEIVED → el wizard salta al paso 5
        Livewire::test(QuotationWizard::class, ['requisition' => $requisition->fresh()])
            ->assertSet('currentStep', 5);
    }

    public function test_wizard_created_groups_and_drafts_appear_on_board(): void
    {
        $requisition = Requisition::factory()->create(['validated_at' => now(), 'status' => 'IN_QUOTATION']);
        $group = QuotationGroup::factory()->create(['requisition_id' => $requisition->id, 'name' => 'Creado en wizard']);
        $item = RequisitionItem::factory()->create(['requisition_id' => $requisition->id]);
        $group->items()->attach($item->id, ['sort_order' => 1]);
        $supplier = Supplier::factory()->create(['approval_status' => 'approved']);

        // Borrador creado vía wizard (completeStep3)
        Livewire::test(QuotationWizard::class, ['requisition' => $requisition])
            ->call('completeStep3', [[
                'group_id' => $group->id,
                'supplier_ids' => [$supplier->id],
                'response_deadline' => now()->addDays(7)->format('Y-m-d'),
                'notes' => null,
            ]]);

        // El tablero muestra el grupo en Preparación (DRAFT no pasa a Seguimiento)
        $board = Livewire::test(QuotationBoard::class, ['requisition' => $requisition->fresh()]);
        $this->assertEquals(['Creado en wizard'], $board->instance()->groups->pluck('name')->all());
        $this->assertEquals([], $board->instance()->followUpGroupIds);

        // La tarjeta pre-llena el formulario con el borrador del wizard
        $card = Livewire::test(GroupCard::class, ['requisition' => $requisition->fresh(), 'groupId' => $group->id]);
        $this->assertEquals('preparing', $card->instance()->state);
        $this->assertEquals([(string) $supplier->id], $card->get('supplierIds'));

        // Y el envío hecho en el tablero regresa el grupo a Seguimiento en ambos flujos
        $card->call('sendNow')->assertHasNoErrors();

        $this->assertEquals(
            [$group->id],
            Livewire::test(QuotationBoard::class, ['requisition' => $requisition->fresh()])->instance()->followUpGroupIds
        );
        Livewire::test(QuotationWizard::class, ['requisition' => $requisition->fresh()])
            ->assertSet('currentStep', 4);
    }
}
