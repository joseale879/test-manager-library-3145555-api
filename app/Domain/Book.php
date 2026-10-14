<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Values\Isbn;
use App\Domain\Values\Quantity;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

final class Book
{
    private string $title;

    private int $availableCopies;

    private Quantity $totalCopies;

    private ?DateTimeImmutable $deletedAt;

    private function __construct(
        private readonly string $id,
        string $title,
        private readonly string $author,
        private readonly Isbn $isbn,
        private readonly string $categoryId,
        Quantity $totalCopies,
        int $availableCopies,
        ?DateTimeImmutable $deletedAt,
    ) {
        $this->title = self::normalizeTitle($title);

        if ($availableCopies < 0 || $availableCopies > $totalCopies->value()) {
            throw new InvalidArgumentException('Available copies must be between zero and total copies.');
        }

        if ($deletedAt !== null && $availableCopies < $totalCopies->value()) {
            throw new InvalidArgumentException('Retired books cannot have outstanding loans.');
        }

        $this->totalCopies = $totalCopies;
        $this->availableCopies = $availableCopies;
        $this->deletedAt = $deletedAt;
    }

    public static function register(
        string $id,
        string $title,
        string $author,
        Isbn $isbn,
        string $categoryId,
        Quantity $totalCopies,
    ): self {
        return new self(
            $id,
            $title,
            $author,
            $isbn,
            $categoryId,
            $totalCopies,
            $totalCopies->value(),
            null,
        );
    }

    public static function restore(
        string $id,
        string $title,
        string $author,
        Isbn $isbn,
        string $categoryId,
        Quantity $totalCopies,
        int $availableCopies,
        ?DateTimeImmutable $deletedAt,
    ): self {
        return new self(
            $id,
            $title,
            $author,
            $isbn,
            $categoryId,
            $totalCopies,
            $availableCopies,
            $deletedAt,
        );
    }

    public function withdraw(): void
    {
        $this->assertActive();

        if ($this->availableCopies === 0) {
            throw new DomainException('Book has no available copies.');
        }

        $this->availableCopies--;
    }

    public function restock(): void
    {
        $this->assertActive();

        if ($this->availableCopies === $this->totalCopies->value()) {
            throw new DomainException('Restocking would exceed total copies.');
        }

        $this->availableCopies++;
    }

    public function changeTotalCopies(Quantity $totalCopies): void
    {
        $availableAfterChange = $this->availableCopies + $totalCopies->value() - $this->totalCopies->value();

        if ($availableAfterChange < 0) {
            throw new DomainException('Total copies cannot be lower than the number of borrowed copies.');
        }

        $this->totalCopies = $totalCopies;
        $this->availableCopies = $availableAfterChange;
    }

    public function retire(DateTimeImmutable $at): void
    {
        if ($this->deletedAt !== null) {
            throw new DomainException('Book is already retired.');
        }

        if ($this->availableCopies < $this->totalCopies->value()) {
            throw new DomainException('Book with outstanding loans cannot be retired.');
        }

        $this->deletedAt = $at;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function author(): string
    {
        return $this->author;
    }

    public function isbn(): Isbn
    {
        return $this->isbn;
    }

    public function categoryId(): string
    {
        return $this->categoryId;
    }

    public function totalCopies(): Quantity
    {
        return $this->totalCopies;
    }

    public function availableCopies(): int
    {
        return $this->availableCopies;
    }

    public function deletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function isActive(): bool
    {
        return $this->deletedAt === null;
    }

    private static function normalizeTitle(string $title): string
    {
        $normalized = trim($title);

        if ($normalized === '') {
            throw new InvalidArgumentException('Book title cannot be empty.');
        }

        return $normalized;
    }

    private function assertActive(): void
    {
        if (! $this->isActive()) {
            throw new DomainException('Retired books cannot change inventory.');
        }
    }
}
