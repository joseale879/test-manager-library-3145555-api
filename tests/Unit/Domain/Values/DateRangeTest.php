<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Values;

use App\Domain\Values\DateRange;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DateRangeTest extends TestCase
{
    public function test_it_accepts_a_range_whose_end_is_after_its_start(): void
    {
        $start = new DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $end = new DateTimeImmutable('2026-01-02T10:00:00+00:00');

        $range = DateRange::from($start, $end);

        self::assertSame($start, $range->start());
        self::assertSame($end, $range->end());
    }

    public function test_it_accepts_equal_start_and_end(): void
    {
        $instant = new DateTimeImmutable('2026-01-01T10:00:00+00:00');

        $range = DateRange::from($instant, $instant);

        self::assertSame($instant, $range->start());
        self::assertSame($instant, $range->end());
    }

    public function test_it_rejects_an_end_before_its_start(): void
    {
        $start = new DateTimeImmutable('2026-01-02T10:00:00+00:00');
        $end = new DateTimeImmutable('2026-01-01T10:00:00+00:00');

        $this->expectException(InvalidArgumentException::class);

        DateRange::from($start, $end);
    }
}
