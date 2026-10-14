<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Application\Ports\Outbound\Clock;
use DateTimeImmutable;

final readonly class FakeClock implements Clock
{
    public function __construct(private DateTimeImmutable $instant) {}

    public function now(): DateTimeImmutable
    {
        return $this->instant;
    }
}
