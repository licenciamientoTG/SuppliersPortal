<?php

namespace Tests\Feature;

use App\Livewire\Rfq\Board\GroupCard;
use App\Models\QuotationGroup;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Rfq;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\NewRfqForSupplierNotification;
use App\Notifications\RfqSentToSuppliersNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fase 4: "Solicitar cotización" desde la tarjeta — guardar borrador y
 * enviar-ahora en una sola acción.
 */
class QuotationBoardSendNowTest extends TestCase
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
    }

    /** @return array{0: Requisition, 1: QuotationGroup} */
    private function makeGroup(array $requisitionOverrides = []): array
    {
        $requisition = Requisition::factory()->create(array_merge([
            'validated_at' => now(),
            'status' => 'IN_QUOTATION',
        ], $requisitionOverrides));
        $group = QuotationGroup::factory()->create(['requisition_id' => $requisition->id]);
        $item = RequisitionItem::factory()->create(['requisition_id' => $requisition->id]);
        $group->items()->attach($item->id, ['sort_order' => 1]);

        return [$requisition, $group];
    }

    public function test_save_draft_creates_draft_rfq_without_sending(): void
    {
        Notification::fake();

        [$requisition, $group] = $this->makeGroup();
        $supplier = Supplier::factory()->create(['approval_status' => 'approved']);

        Livewire::test(GroupCard::class, ['requisition' => $requisition, 'groupId' => $group->id])
            ->set('supplierIds', [(string) $supplier->id])
            ->set('responseDeadline', now()->addDays(5)->format('Y-m-d'))
            ->call('saveDraft')
            ->assertHasNoErrors();

        $rfq = Rfq::where('quotation_group_id', $group->id)->firstOrFail();
        $this->assertEquals('DRAFT', $rfq->status);
        $this->assertNull($rfq->sent_at);
        Notification::assertNothingSent();
    }

    public function test_send_now_creates_and_sends_rfq_in_one_action(): void
    {
        Notification::fake();

        [$requisition, $group] = $this->makeGroup();
        $suppliers = Supplier::factory()->count(2)->create(['approval_status' => 'approved']);

        $component = Livewire::test(GroupCard::class, ['requisition' => $requisition, 'groupId' => $group->id])
            ->set('supplierIds', $suppliers->pluck('id')->map(fn ($id) => (string) $id)->all())
            ->set('responseDeadline', now()->addDays(5)->format('Y-m-d'))
            ->set('notes', 'Urgente')
            ->call('sendNow')
            ->assertHasNoErrors();

        $rfq = Rfq::where('quotation_group_id', $group->id)->firstOrFail();
        $this->assertEquals('SENT', $rfq->status);
        $this->assertNotNull($rfq->sent_at);
        $this->assertMatchesRegularExpression('/^RFQ-\d{8}-\d{4}$/', $rfq->folio);
        $this->assertEquals('Urgente', $rfq->message);

        Notification::assertSentTo($requisition->requester, RfqSentToSuppliersNotification::class);
        $suppliers->each(fn (Supplier $s) => Notification::assertSentTo($s, NewRfqForSupplierNotification::class));

        // La tarjeta pasó a Seguimiento
        $this->assertEquals('sent', $component->instance()->state);
    }

    public function test_send_now_is_blocked_without_signed_validation(): void
    {
        Notification::fake();

        [$requisition, $group] = $this->makeGroup(['validated_at' => null]);
        $supplier = Supplier::factory()->create(['approval_status' => 'approved']);

        Livewire::test(GroupCard::class, ['requisition' => $requisition, 'groupId' => $group->id])
            ->set('supplierIds', [(string) $supplier->id])
            ->set('responseDeadline', now()->addDays(5)->format('Y-m-d'))
            ->call('sendNow');

        $this->assertEquals(0, Rfq::where('quotation_group_id', $group->id)->count());
        Notification::assertNothingSent();
    }

    public function test_send_now_over_existing_unchanged_draft_sends_it(): void
    {
        Notification::fake();

        [$requisition, $group] = $this->makeGroup();
        $supplier = Supplier::factory()->create(['approval_status' => 'approved']);
        $deadline = now()->addDays(5)->format('Y-m-d');

        $draft = Rfq::factory()->create([
            'requisition_id' => $requisition->id,
            'quotation_group_id' => $group->id,
            'status' => 'DRAFT',
            'response_deadline' => $deadline,
            'message' => null,
        ]);
        $draft->suppliers()->attach($supplier->id, ['invited_at' => now()]);

        // Sin cambios respecto al borrador: syncGroupRfq regresa null y se envía el existente
        Livewire::test(GroupCard::class, ['requisition' => $requisition, 'groupId' => $group->id])
            ->call('sendNow')
            ->assertHasNoErrors();

        $this->assertEquals('SENT', $draft->fresh()->status);
        $this->assertEquals(1, Rfq::where('quotation_group_id', $group->id)->count());
    }

    public function test_request_form_requires_supplier_and_future_deadline(): void
    {
        [$requisition, $group] = $this->makeGroup();

        Livewire::test(GroupCard::class, ['requisition' => $requisition, 'groupId' => $group->id])
            ->set('supplierIds', [])
            ->set('responseDeadline', now()->subDay()->format('Y-m-d'))
            ->call('sendNow')
            ->assertHasErrors(['supplierIds', 'responseDeadline']);
    }
}
