<?php

namespace Tests\Unit;

use App\Support\SmallNumbers;
use App\Support\Week;
use PHPUnit\Framework\TestCase;

class WeekAndSmallNumbersTest extends TestCase
{
    public function test_iso_week_labels_and_parsing(): void
    {
        $this->assertSame('2026-W47', Week::label('2026-11-16'));
        $this->assertSame('2026-11-16', Week::parse('2026-W47')->toDateString());
        $this->assertSame('2026-11-16', Week::parse('2026-11-19')->toDateString()); // a Thursday
        $this->assertSame('2026-W01', Week::label('2025-12-29'));                   // ISO year differs from calendar year
        $this->assertCount(3, Week::range(Week::parse('2026-W45'), Week::parse('2026-W47')));
    }

    public function test_small_numbers_are_flagged_and_suppressed_in_exports(): void
    {
        $this->assertFalse(SmallNumbers::isSmall(0));
        $this->assertTrue(SmallNumbers::isSmall(1));
        $this->assertTrue(SmallNumbers::isSmall(4));
        $this->assertFalse(SmallNumbers::isSmall(5));

        $this->assertSame('0', SmallNumbers::forExport(0));
        $this->assertSame('<5', SmallNumbers::forExport(3));
        $this->assertSame('12', SmallNumbers::forExport(12));
    }
}
