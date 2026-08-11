<?php

namespace Tests\Feature;

use App\Models\QuotationGroup;
use App\Models\QuotationSummary;
use App\Models\Requisition;
use App\Models\Rfq;
use App\Models\RfqResponse;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\RfqBudgetBlockedNotification;
use App\Services\Rfq\BudgetBlockedNoticeService;
use App\Services\Rfq\ManualQuoteService;
use App\Services\Rfq\RfqAwardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RfqBudgetBlockedNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_sends_one_budget_blocked_notice_by_mail_and_database_notification(): void
    {
        Notification::fake();
        $this->mockBudgetOnlyBlock();

        [$buyer, $requester, $rfq, $supplier] = $this->fixture();

        $notice = app(BudgetBlockedNoticeService::class)->send(
            $rfq,
            $supplier->id,
            $buyer,
            'El importe requiere disponibilidad adicional para este mes.',
        );

        $this->assertDatabaseHas('rfq_budget_blocked_notices', [
            'id' => $notice->id,
            'rfq_id' => $rfq->id,
            'supplier_id' => $supplier->id,
            'buyer_user_id' => $buyer->id,
        ]);
        Notification::assertSentTo($requester, RfqBudgetBlockedNotification::class, function ($notification, array $channels) {
            return in_array('mail', $channels, true) && in_array('database', $channels, true);
        });

        $this->expectException(\DomainException::class);
        app(BudgetBlockedNoticeService::class)->send($rfq, $supplier->id, $buyer);
    }

    public function test_notice_cannot_be_sent_when_the_offer_has_a_non_budget_blocker(): void
    {
        $awardService = Mockery::mock(RfqAwardService::class);
        $awardService->shouldReceive('supplierDiagnostics')->andReturn([
            'budget_blocked' => true,
            'reasons' => ['No hay presupuesto suficiente.', 'La oferta está vencida.'],
            'budget_messages' => ['No hay presupuesto suficiente.'],
        ]);
        $this->app->instance(RfqAwardService::class, $awardService);

        [$buyer, , $rfq, $supplier] = $this->fixture();

        $this->expectException(\DomainException::class);
        app(BudgetBlockedNoticeService::class)->send($rfq, $supplier->id, $buyer);
    }

    public function test_non_buyer_cannot_send_the_notice(): void
    {
        $this->mockBudgetOnlyBlock();
        [, , $rfq, $supplier] = $this->fixture();

        $this->expectException(\DomainException::class);
        app(BudgetBlockedNoticeService::class)->send($rfq, $supplier->id, User::factory()->create());
    }

    public function test_only_a_manual_received_rfq_blocked_exclusively_by_budget_can_be_edited(): void
    {
        $this->mockBudgetOnlyBlock();
        [, , $rfq, $supplier] = $this->fixture();
        $rfq->update(['source' => 'external']);
        $item = \App\Models\RequisitionItem::factory()->create(['requisition_id' => $rfq->requisition_id]);
        RfqResponse::factory()->create([
            'rfq_id' => $rfq->id,
            'supplier_id' => $supplier->id,
            'requisition_item_id' => $item->id,
            'entry_source' => 'buyer_manual',
            'status' => 'SUBMITTED',
        ]);

        $this->assertSame($supplier->id, app(ManualQuoteService::class)->editableBudgetBlockedSupplierId($rfq));

        QuotationSummary::factory()->create([
            'rfq_id' => $rfq->id,
            'requisition_id' => $rfq->requisition_id,
        ]);

        $this->assertNull(app(ManualQuoteService::class)->editableBudgetBlockedSupplierId($rfq));
    }

    private function fixture(): array
    {
        Role::findOrCreate('buyer', 'web');
        $buyer = User::factory()->create();
        $buyer->assignRole('buyer');
        $requester = User::factory()->create(['email' => 'requisitor@example.com']);
        $requisition = Requisition::factory()->create(['requested_by' => $requester->id]);
        $group = QuotationGroup::factory()->create(['requisition_id' => $requisition->id]);
        $supplier = Supplier::factory()->create();
        $rfq = Rfq::factory()->create([
            'requisition_id' => $requisition->id,
            'quotation_group_id' => $group->id,
            'status' => 'RECEIVED',
        ]);

        return [$buyer, $requester, $rfq, $supplier];
    }

    private function mockBudgetOnlyBlock(): void
    {
        $awardService = Mockery::mock(RfqAwardService::class);
        $awardService->shouldReceive('supplierDiagnostics')->andReturn([
            'budget_blocked' => true,
            'reasons' => ['No hay presupuesto suficiente.'],
            'budget_messages' => ['No hay presupuesto suficiente.'],
        ]);
        $this->app->instance(RfqAwardService::class, $awardService);
    }
}
