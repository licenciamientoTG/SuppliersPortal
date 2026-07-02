<?php

namespace Tests\Feature;

use App\Models\RfqResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfqResponseManualEntryFieldTest extends TestCase
{
    use RefreshDatabase;

    public function test_entry_source_defaults_to_supplier_portal(): void
    {
        $response = RfqResponse::factory()->create();

        $this->assertEquals('supplier_portal', $response->fresh()->entry_source);
        $this->assertNull($response->fresh()->entered_by);
    }

    public function test_entry_source_can_be_set_to_buyer_manual_with_entered_by(): void
    {
        $user = User::factory()->create();
        $response = RfqResponse::factory()->create([
            'entry_source' => 'buyer_manual',
            'entered_by' => $user->id,
        ]);

        $this->assertEquals('buyer_manual', $response->fresh()->entry_source);
        $this->assertTrue($response->fresh()->enteredBy->is($user));
    }
}
