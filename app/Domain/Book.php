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

    private string $author;

    private string $categoryId;

    private int $availableCopies;

    private Quantity $totalCopies;

    private ?string $coverKey;

    private ?DateTimeImmutable $deletedAt;

    private function __construct(
        private readonly string $id,
        string $title,
        string $author,
        private readonly Isbn $isbn,
        string $categoryId,
        Quantity $totalCopies,
        int $availableCopies,
        ?string $coverKey,
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
        $this->author = $author;
        $this->categoryId = $categoryId;
        $this->availableCopies = $availableCopies;
        $this->coverKey = self::validateCoverKey($coverKey);
        $this->deletedAt = $deletedAt;
    }

    public static function register(
        string $id,
        string $title,
        string $author,
        Isbn $isbn,
        string $categoryId,
        Quantity $totalCopies,
        ?string $coverKey = null,
    ): self {
        return new self(
            $id,
            $title,
            $author,
            $isbn,
            $categoryId,
            $totalCopies,
            $totalCopies->value(),
            $coverKey,
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
        ?string $coverKey = null,
    ): self {
        return new self(
            $id,
            $title,
            $author,
            $isbn,
            $categoryId,
            $totalCopies,
            $availableCopies,
            $coverKey,
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
        $this->assertActive();

        $availableAfterChange = $this->availableCopies + $totalCopies->value() - $this->totalCopies->value();

        if ($availableAfterChange < 0) {
            throw new DomainException('Total copies cannot be lower than the number of borrowed copies.');
        }

        $this->totalCopies = $totalCopies;
        $this->availableCopies = $availableAfterChange;
    }

    public function edit(string $title, string $author, string $categoryId, Quantity $totalCopies): void
    {
        $this->assertActive();
        $normalizedTitle = self::normalizeTitle($title);
        $availableAfterChange = $this->availableCopies + $totalCopies->value() - $this->totalCopies->value();

        if ($availableAfterChange < 0) {
            throw new DomainException('Total copies cannot be lower than the number of borrowed copies.');
        }

        $this->title = $normalizedTitle;
        $this->author = $author;
        $this->categoryId = $categoryId;
        $this->totalCopies = $totalCopies;
        $this->availableCopies = $availableAfterChange;
    }

    public function changeCoverKey(?string $coverKey): void
    {
        $this->assertActive();
        $this->coverKey = self::validateCoverKey($coverKey);
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

    public function coverKey(): ?string
    {
        return $this->coverKey;
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

    private static function validateCoverKey(?string $coverKey): ?string
    {
        if ($coverKey !== null && trim($coverKey) === '') {
            throw new InvalidArgumentException('Cover key cannot be empty.');
        }

        return $coverKey;
    }

    private function assertActive(): void
    {
        if (! $this->isActive()) {
            throw new DomainException('Retired books cannot change inventory.');
        }
    }
}
