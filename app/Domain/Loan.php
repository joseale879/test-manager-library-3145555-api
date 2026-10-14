<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;
use DomainException;

final class Loan
{
    /** @var list<LoanItem> */
    private array $items = [];

    private function __construct(
        private readonly string $id,
        private readonly string $memberId,
        private readonly string $loanedByUserId,
        private readonly DateTimeImmutable $loanedAt,
    ) {}

    /**
     * @param  non-empty-list<array{itemId: string, book: Book, categoryName: string}>  $items
     */
    public static function issue(
        string $id,
        string $memberId,
        string $loanedByUserId,
        DateTimeImmutable $loanedAt,
        array $items,
    ): self {
        if ($items === [] || count($items) > 3) {
            throw new DomainException('A loan must contain between one and three items.');
        }

        $bookIds = [];
        $itemIds = [];

        foreach ($items as $item) {
            $bookId = $item['book']->id();

            if (isset($bookIds[$bookId])) {
                throw new DomainException('A book cannot appear more than once in a loan.');
            }

            if (isset($itemIds[$item['itemId']])) {
                throw new DomainException('Loan item identifiers must be unique.');
            }

            if (! $item['book']->isActive() || $item['book']->availableCopies() === 0) {
                throw new DomainException('Book has no available copies.');
            }

            $bookIds[$bookId] = true;
            $itemIds[$item['itemId']] = true;
        }

        $loan = new self($id, $memberId, $loanedByUserId, $loanedAt);

        foreach ($items as $item) {
            $loan->addItem($item['itemId'], $item['book'], $item['categoryName']);
        }

        return $loan;
    }

    private function addItem(string $itemId, Book $book, string $categoryName): void
    {
        if (count($this->items) >= 3) {
            throw new DomainException('A loan cannot contain more than three items.');
        }

        foreach ($this->items as $item) {
            if ($item->bookId() === $book->id()) {
                throw new DomainException('A book cannot appear more than once in a loan.');
            }
        }

        $book->withdraw();
        $this->items[] = LoanItem::lend($itemId, $book, $categoryName, $this->loanedAt);
    }

    public function returnItem(string $itemId, Book $book, DateTimeImmutable $returnedAt): void
    {
        $itemIndex = $this->itemIndexById($itemId);
        $item = $this->items[$itemIndex];

        if ($item->bookId() !== $book->id()) {
            throw new DomainException('The book does not belong to this loan item.');
        }

        $returnedItem = $item->markReturned($returnedAt);
        $book->restock();
        $this->items[$itemIndex] = $returnedItem;
    }

    public function status(DateTimeImmutable $now): LoanStatus
    {
        if ($this->items === []) {
            throw new DomainException('A loan must contain at least one item.');
        }

        if (count(array_filter($this->items, static fn (LoanItem $item): bool => ! $item->isReturned())) === 0) {
            return LoanStatus::CLOSED;
        }

        foreach ($this->items as $item) {
            if (! $item->isReturned() && $item->dueAt() < $now) {
                return LoanStatus::OVERDUE;
            }
        }

        return LoanStatus::OPEN;
    }

    /** @return list<LoanItem> */
    public function items(): array
    {
        return $this->items;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function memberId(): string
    {
        return $this->memberId;
    }

    public function loanedByUserId(): string
    {
        return $this->loanedByUserId;
    }

    public function loanedAt(): DateTimeImmutable
    {
        return $this->loanedAt;
    }

    private function itemIndexById(string $itemId): int
    {
        foreach ($this->items as $index => $item) {
            if ($item->id() === $itemId) {
                return $index;
            }
        }

        throw new DomainException('Loan item does not exist.');
    }
}
