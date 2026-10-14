<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Values\Isbn;
use DateInterval;
use DateTimeImmutable;
use DomainException;

final readonly class LoanItem
{
    private function __construct(
        private readonly string $id,
        private readonly string $bookId,
        private readonly string $bookTitle,
        private readonly Isbn $isbn,
        private readonly string $categoryName,
        private readonly DateTimeImmutable $dueAt,
        private readonly ?DateTimeImmutable $returnedAt,
    ) {}

    public static function lend(
        string $id,
        Book $book,
        string $categoryName,
        DateTimeImmutable $loanedAt,
    ): self {
        return new self(
            $id,
            $book->id(),
            $book->title(),
            $book->isbn(),
            $categoryName,
            $loanedAt->add(new DateInterval('P14D')),
            null,
        );
    }

    public function markReturned(DateTimeImmutable $returnedAt): self
    {
        if ($this->returnedAt !== null) {
            throw new DomainException('Loan item has already been returned.');
        }

        return new self(
            $this->id,
            $this->bookId,
            $this->bookTitle,
            $this->isbn,
            $this->categoryName,
            $this->dueAt,
            $returnedAt,
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function bookId(): string
    {
        return $this->bookId;
    }

    public function bookTitle(): string
    {
        return $this->bookTitle;
    }

    public function isbn(): Isbn
    {
        return $this->isbn;
    }

    public function categoryName(): string
    {
        return $this->categoryName;
    }

    public function dueAt(): DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function returnedAt(): ?DateTimeImmutable
    {
        return $this->returnedAt;
    }

    public function isReturned(): bool
    {
        return $this->returnedAt !== null;
    }
}
