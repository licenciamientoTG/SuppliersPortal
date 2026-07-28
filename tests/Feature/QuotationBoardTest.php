<?php

namespace Tests\Feature;

use App\Livewire\Rfq\QuotationBoard;
use App\Models\QuotationGroup;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 3: esqueleto del tablero — render, gate de validación y agrupación.
 */
class QuotationBoardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_board_route_renders_livewire_component(): void
    {
        $this->withoutMiddleware([
            \App\Http\Middleware\ModuleAccess::class,
            \App\Http\Middleware\CheckLockScreen::class,
        ]);

        $requisition = Requisition::factory()->create();

        $this->get(route('rfq.board', $requisition))
            ->assertOk()
            ->assertSeeLivewire(QuotationBoard::class);
    }

    public function test_rfq_index_shows_board_beta_button(): void
    {
        $this->withoutMiddleware([
            \App\Http\Middleware\ModuleAccess::class,
            \App\Http\Middleware\CheckLockScreen::class,
        ]);

        Requisition::factory()->create(['status' => 'PENDING']);

        $this->get(route('rfq.index'))->assertOk();
        // El botón vive en el componente Livewire del listado
        Livewire::test(\App\Livewire\Rfq\RfqIndex::class)
            ->assertSee('Tablero');
    }

    public function test_sign_validation_requires_all_checks(): void
    {
        $requisition = Requisition::factory()->create(['validated_at' => null]);

        Livewire::test(QuotationBoard::class, ['requisition' => $requisition])
            ->set('validationData.specs_clear', true)
            ->call('signValidation');

        $this->assertNull($requisition->fresh()->validated_at);
    }

    public function test_sign_validation_marks_requisition_in_quotation_and_notifies_requester(): void
    {
        Notification::fake();

        $requester = User::factory()->create();
        $requisition = Requisition::factory()->create([
            'validated_at' => null,
            'requested_by' => $requester->id,
            'status' => 'PENDING',
        ]);

        Livewire::test(QuotationBoard::class, ['requisition' => $requisition])
            ->set('validationData.specs_clear', true)
            ->set('validationData.time_feasible', true)
            ->set('validationData.alternatives_evaluated', true)
            ->call('signValidation');

        $fresh = $requisition->fresh();
        $this->assertNotNull($fresh->validated_at);
        $this->assertEquals('IN_QUOTATION', $fresh->status->value);
        $this->assertEquals($this->user->id, $fresh->validated_by);
        Notification::assertSentTo($requester, \App\Notifications\RequisitionInQuotationNotification::class);
    }

    public function test_create_group_from_selection_moves_items_out_of_unassigned(): void
    {
        $requisition = Requisition::factory()->create(['validated_at' => now()]);
        $items = RequisitionItem::factory()->count(3)->create(['requisition_id' => $requisition->id]);

        $component = Livewire::test(QuotationBoard::class, ['requisition' => $requisition])
            ->set('selectedItemIds.'.$items[0]->id, true)
            ->set('selectedItemIds.'.$items[1]->id, true)
            ->set('newGroupName', 'Papelería')
            ->call('createGroupWithSelection');

        $group = QuotationGroup::where('requisition_id', $requisition->id)->firstOrFail();
        $this->assertEquals('Papelería', $group->name);
        $this->assertEqualsCanonicalizing(
            [$items[0]->id, $items[1]->id],
            $group->items->pluck('id')->all()
        );

        $this->assertCount(1, $component->instance()->unassignedItems);
    }

    public function test_drag_drop_create_group_uses_default_name(): void
    {
        $requisition = Requisition::factory()->create(['validated_at' => now()]);
        $item = RequisitionItem::factory()->create(['requisition_id' => $requisition->id]);

        Livewire::test(QuotationBoard::class, ['requisition' => $requisition])
            ->call('createGroup', [$item->id]);

        $group = QuotationGroup::where('requisition_id', $requisition->id)->firstOrFail();
        $this->assertEquals('Grupo 1', $group->name);
        $this->assertEquals([$item->id], $group->items->pluck('id')->all());
    }

    public function test_cancelled_group_returns_items_to_unassigned_pool(): void
    {
        $requisition = Requisition::factory()->create(['validated_at' => now()]);
        $item = RequisitionItem::factory()->create(['requisition_id' => $requisition->id]);
        $group = QuotationGroup::factory()->create(['requisition_id' => $requisition->id]);
        $group->items()->attach($item->id, ['sort_order' => 1]);

        $component = Livewire::test(QuotationBoard::class, ['requisition' => $requisition]);
        $this->assertCount(0, $component->instance()->unassignedItems);

        $component->call('cancelGroup', $group->id);

        $this->assertEquals('CANCELLED', $group->fresh()->status);
        $this->assertCount(1, $component->instance()->unassignedItems);
        $this->assertCount(0, $component->instance()->groups);
    }

    public function test_remove_item_from_group(): void
    {
        $requisition = Requisition::factory()->create(['validated_at' => now()]);
        $items = RequisitionItem::factory()->count(2)->create(['requisition_id' => $requisition->id]);
        $group = QuotationGroup::factory()->create(['requisition_id' => $requisition->id]);
        $group->items()->attach([$items[0]->id => ['sort_order' => 1], $items[1]->id => ['sort_order' => 2]]);

        Livewire::test(QuotationBoard::class, ['requisition' => $requisition])
            ->call('removeItemFromGroup', $group->id, $items[0]->id);

        $this->assertEquals([$items[1]->id], $group->fresh()->items->pluck('id')->all());
    }
}
