<?php

namespace Tests\Feature;

use App\Models\RfqResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfqResponseNotAvailableTest extends TestCase
{
    use RefreshDatabase;

    public function test_not_available_defaults_to_false_and_casts_to_boolean(): void
    {
        $response = RfqResponse::factory()->create();

        $this->assertIsBool($response->fresh()->not_available);
        $this->assertFalse($response->fresh()->not_available);
    }

    public function test_quoted_and_not_available_scopes_filter_rows(): void
    {
        $quoted = RfqResponse::factory()->create(['not_available' => false]);
        $unavailable = RfqResponse::factory()->create([
            'not_available' => true,
            'unit_price' => 0,
            'quantity' => 0,
            'subtotal' => 0,
            'iva_amount' => 0,
            'total' => 0,
        ]);

        $this->assertEquals([$quoted->id], RfqResponse::quoted()->pluck('id')->all());
        $this->assertEquals([$unavailable->id], RfqResponse::notAvailable()->pluck('id')->all());
    }
}
