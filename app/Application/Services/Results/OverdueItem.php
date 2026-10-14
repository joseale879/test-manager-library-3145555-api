<?php

declare(strict_types=1);

namespace App\Application\Services\Results;

use DateTimeImmutable;

final readonly class OverdueItem
{
    public function __construct(
        public string $loanId,
        public string $itemId,
        public string $bookTitle,
        public string $memberName,
        public DateTimeImmutable $dueAt,
        public int $daysLate,
    ) {}
}
