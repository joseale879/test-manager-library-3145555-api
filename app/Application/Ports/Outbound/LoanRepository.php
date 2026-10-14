<?php

declare(strict_types=1);

namespace App\Application\Ports\Outbound;

use App\Domain\Book;
use App\Domain\Loan;
use DateTimeImmutable;

interface LoanRepository
{
    public function countOpenItemsForMember(string $memberId): int;

    public function hasOverdueItemsForMember(string $memberId, DateTimeImmutable $now): bool;

    /** @param list<Book> $books */
    public function saveWithBooks(Loan $loan, array $books): void;
}
