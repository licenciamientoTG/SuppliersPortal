<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class FormatHelpersTest extends TestCase
{
    public function test_it_formats_money_with_grouping_and_two_decimals(): void
    {
        $this->assertSame('$12,345.68', \format_money(12345.678));
        $this->assertSame('US$9,876.50 USD', \format_money(9876.5, 'USD', true));
    }

    public function test_it_formats_days_as_whole_numbers(): void
    {
        $this->assertSame('4', \format_days(4.46584767495));
        $this->assertSame('-3', \format_days(-3.2));
    }
}
