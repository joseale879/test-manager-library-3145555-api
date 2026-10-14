<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Application\Ports\Outbound\LoanRepository;
use App\Domain\Book;
use App\Domain\Loan;
use DateTimeImmutable;

final class InMemoryLoanRepository implements LoanRepository
{
    /** @var array<string, int> */
    private array $openItemCounts = [];

    /** @var array<string, bool> */
    private array $overdueMembers = [];

    /** @var array<string, Loan> */
    private array $loans = [];

    /** @var array<string, Book> */
    private array $savedBooks = [];

    public int $saveCalls = 0;

    public function countOpenItemsForMember(string $memberId): int
    {
        return $this->openItemCounts[$memberId] ?? 0;
    }

    public function hasOverdueItemsForMember(string $memberId, DateTimeImmutable $now): bool
    {
        return $this->overdueMembers[$memberId] ?? false;
    }

    public function saveWithBooks(Loan $loan, array $books): void
    {
        $this->saveCalls++;
        $this->loans[$loan->id()] = $loan;

        foreach ($books as $book) {
            $this->savedBooks[$book->id()] = $book;
        }
    }

    public function setOpenItemCount(string $memberId, int $count): void
    {
        $this->openItemCounts[$memberId] = $count;
    }

    public function setHasOverdueItems(string $memberId, bool $hasOverdueItems): void
    {
        $this->overdueMembers[$memberId] = $hasOverdueItems;
    }

    public function loan(string $id): ?Loan
    {
        return $this->loans[$id] ?? null;
    }

    public function savedBook(string $id): ?Book
    {
        return $this->savedBooks[$id] ?? null;
    }
}
