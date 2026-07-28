<?php

namespace Tests\Feature;

use App\Models\Rfq;
use App\Services\Rfq\RfqFolioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfqFolioServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_folio_of_the_day_starts_at_one(): void
    {
        $folio = app(RfqFolioService::class)->next();

        $this->assertEquals('RFQ-'.now()->format('Ymd').'-0001', $folio);
    }

    public function test_continues_from_highest_existing_folio_of_the_day(): void
    {
        Rfq::factory()->create(['folio' => 'RFQ-'.now()->format('Ymd').'-0007']);
        Rfq::factory()->create(['folio' => 'RFQ-'.now()->format('Ymd').'-0002']);

        $folio = app(RfqFolioService::class)->next();

        $this->assertEquals('RFQ-'.now()->format('Ymd').'-0008', $folio);
    }

    public function test_ignores_folios_from_other_days(): void
    {
        Rfq::factory()->create(['folio' => 'RFQ-'.now()->subDay()->format('Ymd').'-0042']);

        $folio = app(RfqFolioService::class)->next();

        $this->assertEquals('RFQ-'.now()->format('Ymd').'-0001', $folio);
    }

    public function test_does_not_reuse_folio_of_soft_deleted_rfq(): void
    {
        $rfq = Rfq::factory()->create(['folio' => 'RFQ-'.now()->format('Ymd').'-0003']);
        $rfq->delete();

        $folio = app(RfqFolioService::class)->next();

        $this->assertEquals('RFQ-'.now()->format('Ymd').'-0004', $folio);
    }
}
