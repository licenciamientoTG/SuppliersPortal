<?php

namespace Tests\Feature;

use App\Exceptions\Rfq\AwardNotAllowedException;
use App\Models\AuthorizerRole;
use App\Models\ExchangeRate;
use App\Models\QuotationSummary;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Rfq;
use App\Models\RfqResponse;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AuthorizerResolutionService;
use App\Services\Rfq\RfqAwardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * El tipo de cambio ya existía en rfq_responses.currency pero se perdía al
 * adjudicar: QuotationSummary siempre guardaba la suma en moneda nativa, sin
 * convertir a MXN. Esto corrompía el presupuesto comprometido y el total
 * mostrado al aprobador para cualquier cotización no-MXN.
 */
class RfqAwardCurrencyConversionTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('buyer', 'web');

        $this->buyer = User::factory()->create();
        $this->buyer->assignRole('buyer');
        $this->approver = User::factory()->create();

        AuthorizerRole::create([
            'name' => 'Gerencia',
            'approval_limit' => 1000000,
            'display_order' => 1,
            'is_active' => true,
        ]);

        $authorizerService = Mockery::mock(AuthorizerResolutionService::class);
        $authorizerService->shouldReceive('resolveForRequester')->andReturn(['approver_user' => $this->approver]);
        $authorizerService->shouldReceive('resolveForSummary')->andReturn([
            'approver_user' => $this->approver,
            'authorizer_role' => AuthorizerRole::first(),
            'effective_limit' => 1000000,
            'chain' => [['status' => 'eligible']],
            'resolution_notes' => 'Aprobador resuelto para prueba.',
        ]);
        $this->app->instance(AuthorizerResolutionService::class, $authorizerService);
    }

    /** @return array{0: Rfq, 1: Supplier} */
    private function makeAwardableRfq(array $responseOverrides = []): array
    {
        $requisition = Requisition::factory()->create(['status' => 'IN_QUOTATION']);
        $item = RequisitionItem::factory()->create(['requisition_id' => $requisition->id]);

        $rfq = Rfq::factory()->create([
            'requisition_id' => $requisition->id,
            'quotation_group_id' => null,
            'requisition_item_id' => $item->id,
            'status' => 'RECEIVED',
            'response_deadline' => now()->addDays(7),
        ]);

        $supplier = Supplier::factory()->create();
        $rfq->suppliers()->attach($supplier->id, ['invited_at' => now(), 'responded_at' => now()]);

        RfqResponse::factory()->create(array_merge([
            'rfq_id' => $rfq->id,
            'supplier_id' => $supplier->id,
            'requisition_item_id' => $item->id,
            'status' => 'SUBMITTED',
            'unit_price' => 100,
            'quantity' => 2,
            'subtotal' => 200,
            'iva_rate' => 16,
            'iva_amount' => 32,
            'total' => 232,
        ], $responseOverrides));

        return [$rfq, $supplier];
    }

    public function test_award_converts_foreign_currency_quote_totals_to_mxn(): void
    {
        Notification::fake();

        ExchangeRate::factory()->create([
            'currency_from' => 'USD',
            'currency_to' => 'MXN',
            'rate' => 18.0000,
        ]);

        [$rfq, $supplier] = $this->makeAwardableRfq(['currency' => 'USD']);

        $summary = app(RfqAwardService::class)->award(
            $rfq,
            $supplier->id,
            'Mejor precio y tiempo de entrega en la comparativa.',
            null,
            $this->buyer->id
        );

        $this->assertEquals(3600.0, (float) $summary->subtotal);
        $this->assertEquals(576.0, (float) $summary->iva_amount);
        $this->assertEquals(4176.0, (float) $summary->total);
    }

    public function test_award_is_blocked_when_foreign_currency_has_no_synced_rate(): void
    {
        Notification::fake();

        [$rfq, $supplier] = $this->makeAwardableRfq(['currency' => 'USD']);

        $this->expectException(AwardNotAllowedException::class);

        app(RfqAwardService::class)->award(
            $rfq,
            $supplier->id,
            'Intento de adjudicar sin tipo de cambio sincronizado.',
            null,
            $this->buyer->id
        );

        $this->assertNull(QuotationSummary::where('rfq_id', $rfq->id)->first());
    }
}
