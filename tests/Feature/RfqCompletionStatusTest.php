<?php

namespace Tests\Feature;

use App\Models\Rfq;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfqCompletionStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_stays_sent_until_all_suppliers_respond(): void
    {
        $rfq = Rfq::factory()->create(['status' => 'SENT']);
        $supplierA = Supplier::factory()->create();
        $supplierB = Supplier::factory()->create();

        $rfq->suppliers()->attach([
            $supplierA->id => ['invited_at' => now(), 'responded_at' => now()],
            $supplierB->id => ['invited_at' => now(), 'responded_at' => null],
        ]);

        $rfq->refreshCompletionStatus();

        $this->assertEquals('SENT', $rfq->fresh()->status);
    }

    public function test_status_becomes_received_when_all_suppliers_responded(): void
    {
        $rfq = Rfq::factory()->create(['status' => 'SENT']);
        $supplierA = Supplier::factory()->create();
        $supplierB = Supplier::factory()->create();

        $rfq->suppliers()->attach([
            $supplierA->id => ['invited_at' => now(), 'responded_at' => now()],
            $supplierB->id => ['invited_at' => now(), 'responded_at' => now()],
        ]);

        $rfq->refreshCompletionStatus();

        $this->assertEquals('RECEIVED', $rfq->fresh()->status);
    }
}
