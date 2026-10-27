<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\DateRange;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class DateRangeTest extends TestCase
{
    private DateTimeImmutable $today;

    protected function setUp(): void
    {
        $this->today = new DateTimeImmutable('2026-10-27 15:30');
    }

    public function testPresetIncludesToday(): void
    {
        $range = DateRange::fromQuery(['range' => '7'], $this->today);

        $this->assertSame('2026-10-21', $range->from->format('Y-m-d'));
        $this->assertSame('2026-10-27', $range->to->format('Y-m-d'));
        $this->assertSame(7, $range->days());
        $this->assertSame(['range' => '7'], $range->query());
        $this->assertCount(7, $range->eachDay());
    }

    public function testInvalidInputFallsBackToThirtyDays(): void
    {
        foreach ([[], ['range' => '13'], ['range' => ['x']], ['from' => '2026-02-30', 'to' => '2026-03-01'], ['from' => '2026-01-01']] as $query) {
            $range = DateRange::fromQuery($query, $this->today);
            $this->assertSame(30, $range->days(), json_encode($query));
            $this->assertSame(30, $range->preset);
        }
    }

    public function testCustomRangeIsOrderedAndClamped(): void
    {
        $range = DateRange::fromQuery(['from' => '2026-03-31', 'to' => '2026-03-01'], $this->today);
        $this->assertSame('2026-03-01', $range->from->format('Y-m-d'));
        $this->assertSame('2026-03-31', $range->to->format('Y-m-d'));
        $this->assertNull($range->preset);
        $this->assertSame(['from' => '2026-03-01', 'to' => '2026-03-31'], $range->query());

        $huge = DateRange::fromQuery(['from' => '2000-01-01', 'to' => '2026-01-01'], $this->today);
        $this->assertSame(DateRange::MAX_DAYS, $huge->days());
    }

    public function testPreviousPeriodHasTheSameLengthAndEndsTheDayBefore(): void
    {
        $previous = DateRange::lastDays(30, $this->today)->previous();

        $this->assertSame(30, $previous->days());
        $this->assertSame('2026-09-27', $previous->to->format('Y-m-d'));
        $this->assertSame('2026-08-29', $previous->from->format('Y-m-d'));
    }
}
