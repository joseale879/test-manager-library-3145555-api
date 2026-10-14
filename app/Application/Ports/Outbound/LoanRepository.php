<?php

declare(strict_types=1);

namespace App\Application\Ports\Outbound;

use App\Application\Services\Results\OverdueRecord;
use App\Domain\Book;
use App\Domain\Loan;
use DateTimeImmutable;

interface LoanRepository
{
    public function findById(string $loanId): ?Loan;

    public function countOpenItemsForMember(string $memberId): int;

    public function hasOverdueItemsForMember(string $memberId, DateTimeImmutable $now): bool;

    /** @return list<OverdueRecord> */
    public function findOverdueItems(DateTimeImmutable $now): array;

    /** @param list<Book> $books */
    public function saveWithBooks(Loan $loan, array $books): void;
}
