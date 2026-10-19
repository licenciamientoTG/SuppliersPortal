<?php

namespace Tests\Unit;

use App\Models\ExchangeRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ExchangeRateConversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_rate_to_mxn_returns_one_for_mxn(): void
    {
        $this->assertSame(1.0, ExchangeRate::rateToMxn('MXN'));
    }

    public function test_rate_to_mxn_returns_synced_rate(): void
    {
        ExchangeRate::factory()->create([
            'currency_from' => 'USD',
            'currency_to' => 'MXN',
            'rate' => 18.2000,
            'fetched_at' => now(),
        ]);

        $this->assertSame(18.2, ExchangeRate::rateToMxn('USD'));
    }

    public function test_rate_to_mxn_fails_loud_when_no_rate_synced(): void
    {
        $this->expectException(RuntimeException::class);

        ExchangeRate::rateToMxn('EUR');
    }

    public function test_convert_to_mxn_returns_same_amount_for_mxn(): void
    {
        $this->assertSame(1500.0, ExchangeRate::convertToMxn(1500.0, 'MXN'));
    }

    public function test_convert_to_mxn_applies_synced_rate_and_rounds_to_two_decimals(): void
    {
        ExchangeRate::factory()->create([
            'currency_from' => 'USD',
            'currency_to' => 'MXN',
            'rate' => 18.3456,
            'fetched_at' => now(),
        ]);

        $this->assertSame(1834.56, ExchangeRate::convertToMxn(100.0, 'USD'));
    }

    public function test_convert_to_mxn_fails_loud_when_no_rate_synced(): void
    {
        $this->expectException(RuntimeException::class);

        ExchangeRate::convertToMxn(100.0, 'EUR');
    }
}
