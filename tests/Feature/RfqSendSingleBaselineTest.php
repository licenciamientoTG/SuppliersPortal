<?php

namespace Tests\Feature;

use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Rfq;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\BuyerWorkflowNotification;
use App\Notifications\NewRfqForSupplierNotification;
use App\Notifications\RfqSentToSuppliersNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Caracteriza el comportamiento actual de RfqController::sendSingle
 * (envío de una RFQ en borrador) antes de extraerlo a RfqSendService.
 */
class RfqSendSingleBaselineTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            \App\Http\Middleware\ModuleAccess::class,
            \App\Http\Middleware\CheckLockScreen::class,
        ]);
        Role::findOrCreate('buyer', 'web');

        $this->buyer = User::factory()->create();
        $this->buyer->assignRole('buyer');
        $this->actingAs($this->buyer);
    }

    private function makeDraftRfq(): Rfq
    {
        $requester = User::factory()->create();
        $requisition = Requisition::factory()->create([
            'requested_by' => $requester->id,
            'status' => 'IN_QUOTATION',
        ]);
        $item = RequisitionItem::factory()->create(['requisition_id' => $requisition->id]);

        return Rfq::factory()->create([
            'requisition_id' => $requisition->id,
            'quotation_group_id' => null,
            'requisition_item_id' => $item->id,
            'status' => 'DRAFT',
            'response_deadline' => now()->addDays(7),
        ]);
    }

    public function test_sends_draft_rfq_marks_sent_stamps_pivot_and_notifies_everyone(): void
    {
        Notification::fake();

        $rfq = $this->makeDraftRfq();
        $suppliers = Supplier::factory()->count(2)->create();
        $rfq->suppliers()->attach($suppliers->pluck('id')->all());

        $this->post(route('rfq.send.single', $rfq))
            ->assertOk()
            ->assertJson(['success' => true]);

        $fresh = $rfq->fresh();
        $this->assertEquals('SENT', $fresh->status);
        $this->assertNotNull($fresh->sent_at);

        $fresh->suppliers->each(fn ($s) => $this->assertNotNull($s->pivot->invited_at));

        Notification::assertSentTo($rfq->requisition->requester, RfqSentToSuppliersNotification::class);
        $suppliers->each(fn (Supplier $s) => Notification::assertSentTo($s, NewRfqForSupplierNotification::class));
        Notification::assertSentTo(
            $this->buyer,
            BuyerWorkflowNotification::class,
            fn (BuyerWorkflowNotification $n) => $n->type === 'buyer_rfq_sent'
        );
    }

    public function test_rejects_rfq_that_is_not_draft(): void
    {
        Notification::fake();

        $rfq = $this->makeDraftRfq();
        $rfq->update(['status' => 'SENT', 'sent_at' => now()->subDay()]);
        $rfq->suppliers()->attach(Supplier::factory()->create()->id);

        $this->post(route('rfq.send.single', $rfq))
            ->assertStatus(400)
            ->assertJson(['success' => false]);

        Notification::assertNothingSent();
    }

    public function test_rejects_draft_without_suppliers(): void
    {
        Notification::fake();

        $rfq = $this->makeDraftRfq();

        $this->post(route('rfq.send.single', $rfq))
            ->assertStatus(400)
            ->assertJson(['success' => false]);

        $this->assertEquals('DRAFT', $rfq->fresh()->status);
        Notification::assertNothingSent();
    }
}
