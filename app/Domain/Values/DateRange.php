<?php

declare(strict_types=1);

namespace App\Domain\Values;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class DateRange
{
    private function __construct(
        private DateTimeImmutable $start,
        private DateTimeImmutable $end,
    ) {}

    public static function from(DateTimeImmutable $start, DateTimeImmutable $end): self
    {
        if ($end < $start) {
            throw new InvalidArgumentException('Date range end must be on or after its start.');
        }

        return new self($start, $end);
    }

    public function start(): DateTimeImmutable
    {
        return $this->start;
    }

    public function end(): DateTimeImmutable
    {
        return $this->end;
    }
}
