<?php

namespace Tests\Feature;

use App\Models\DirectPurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillDirectPurchaseOrderIssuedAtTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fills_issued_at_from_approved_at_only_for_orders_that_were_issued(): void
    {
        $approvedAt = now()->subDays(10)->startOfSecond();
        $updatedAt = now()->subDays(5)->startOfSecond();
        $existingIssuedAt = now()->subDay()->startOfSecond();

        $received = $this->order(['status' => 'RECEIVED', 'approved_at' => $approvedAt, 'updated_at' => $updatedAt]);
        $cancelledAfterIssue = $this->order(['status' => 'CANCELLED', 'approved_at' => $approvedAt]);
        $returnedAfterIssue = $this->order(['status' => 'RETURNED', 'approved_at' => $approvedAt]);
        $returnedNeverIssued = $this->order(['status' => 'RETURNED', 'approved_at' => null]);
        $delivered = $this->order(['status' => 'DELIVERED_PENDING_RECEPTION', 'approved_at' => $approvedAt]);
        $partial = $this->order(['status' => 'PARTIALLY_RECEIVED', 'approved_at' => $approvedAt]);
        $cancelledNeverIssued = $this->order(['status' => 'CANCELLED', 'approved_at' => null]);
        $pending = $this->order(['status' => 'PENDING_APPROVAL', 'approved_at' => null]);
        $rejected = $this->order(['status' => 'REJECTED', 'approved_at' => $approvedAt]);
        $alreadySet = $this->order(['status' => 'ISSUED', 'approved_at' => $approvedAt, 'issued_at' => $existingIssuedAt]);

        $this->artisan('direct-purchase-orders:backfill-issued-at')->assertSuccessful();

        $this->assertEquals($approvedAt, $received->fresh()->issued_at);
        $this->assertSame('RECEIVED', $received->fresh()->status);
        $this->assertEquals($updatedAt, $received->fresh()->updated_at, 'No debe tocar updated_at.');
        $this->assertEquals($approvedAt, $cancelledAfterIssue->fresh()->issued_at);
        $this->assertEquals($approvedAt, $returnedAfterIssue->fresh()->issued_at);
        $this->assertNull($cancelledNeverIssued->fresh()->issued_at);
        $this->assertNull($returnedNeverIssued->fresh()->issued_at);
        $this->assertEquals($approvedAt, $delivered->fresh()->issued_at);
        $this->assertEquals($approvedAt, $partial->fresh()->issued_at);
        $this->assertNull($pending->fresh()->issued_at);
        $this->assertNull($rejected->fresh()->issued_at);
        $this->assertEquals($existingIssuedAt, $alreadySet->fresh()->issued_at);

        // Segunda ejecución: idempotente.
        $this->artisan('direct-purchase-orders:backfill-issued-at')->assertSuccessful();
        $this->assertEquals($approvedAt, $received->fresh()->issued_at);
        $this->assertEquals($updatedAt, $received->fresh()->updated_at);
        $this->assertEquals($existingIssuedAt, $alreadySet->fresh()->issued_at);
        $this->assertNull($returnedNeverIssued->fresh()->issued_at);
    }

    public function test_dry_run_does_not_write(): void
    {
        $order = $this->order(['status' => 'ISSUED', 'approved_at' => now()]);

        $this->artisan('direct-purchase-orders:backfill-issued-at', ['--dry-run' => true])->assertSuccessful();

        $this->assertNull($order->fresh()->issued_at);
    }

    private function order(array $attributes): DirectPurchaseOrder
    {
        $order = DirectPurchaseOrder::factory()->create(array_merge(['issued_at' => null], $attributes));

        if (isset($attributes['updated_at'])) {
            DirectPurchaseOrder::query()->whereKey($order->id)->toBase()->update(['updated_at' => $attributes['updated_at']]);
        }

        return $order;
    }
}
