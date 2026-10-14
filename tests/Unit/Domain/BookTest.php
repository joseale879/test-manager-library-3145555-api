<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Book;
use App\Domain\Values\Isbn;
use App\Domain\Values\Quantity;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BookTest extends TestCase
{
    public function test_registration_trims_the_title_and_starts_with_all_copies_available(): void
    {
        $book = $this->book(totalCopies: 3, title: '  Domain Driven Design  ');

        self::assertSame('Domain Driven Design', $book->title());
        self::assertSame(3, $book->totalCopies()->value());
        self::assertSame(3, $book->availableCopies());
        self::assertTrue($book->isActive());
    }

    public function test_registration_rejects_an_empty_title(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->book(title: '   ');
    }

    public function test_withdraw_decreases_available_copies_without_going_below_zero(): void
    {
        $book = $this->book(totalCopies: 1);

        $book->withdraw();

        self::assertSame(0, $book->availableCopies());
        $this->expectException(DomainException::class);
        $book->withdraw();
    }

    public function test_restock_increases_available_copies_without_exceeding_total(): void
    {
        $book = $this->book(totalCopies: 2);
        $book->withdraw();

        $book->restock();

        self::assertSame(2, $book->availableCopies());
        $this->expectException(DomainException::class);
        $book->restock();
    }

    public function test_changing_total_copies_adjusts_available_copies_by_the_same_difference(): void
    {
        $book = $this->book(totalCopies: 4);
        $book->withdraw();

        $book->changeTotalCopies(Quantity::from(6));

        self::assertSame(6, $book->totalCopies()->value());
        self::assertSame(5, $book->availableCopies());

        $book->changeTotalCopies(Quantity::from(3));

        self::assertSame(3, $book->totalCopies()->value());
        self::assertSame(2, $book->availableCopies());
    }

    public function test_changing_total_copies_cannot_go_below_the_number_of_borrowed_copies(): void
    {
        $book = $this->book(totalCopies: 3);
        $book->withdraw();
        $book->withdraw();

        $this->expectException(DomainException::class);

        $book->changeTotalCopies(Quantity::from(1));
    }

    public function test_retiring_a_book_records_a_logical_deletion_time(): void
    {
        $book = $this->book();
        $retiredAt = new DateTimeImmutable('2026-10-14T12:00:00+00:00');

        $book->retire($retiredAt);

        self::assertSame($retiredAt, $book->deletedAt());
        self::assertFalse($book->isActive());
    }

    public function test_a_book_with_outstanding_loans_cannot_be_retired(): void
    {
        $book = $this->book(totalCopies: 2);
        $book->withdraw();

        $this->expectException(DomainException::class);

        $book->retire(new DateTimeImmutable('2026-10-14T12:00:00+00:00'));
    }

    public function test_retired_books_cannot_be_withdrawn(): void
    {
        $book = $this->book();
        $book->retire(new DateTimeImmutable('2026-10-14T12:00:00+00:00'));

        $this->expectException(DomainException::class);
        $book->withdraw();
    }

    public function test_restore_rejects_a_retired_book_with_outstanding_loans(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Book::restore(
            'book-id',
            'Test Book',
            'Author',
            Isbn::from('9780306406157'),
            'category-id',
            Quantity::from(2),
            1,
            new DateTimeImmutable('2026-10-14T12:00:00+00:00'),
        );
    }

    private function book(int $totalCopies = 3, string $title = 'Test Book'): Book
    {
        return Book::register(
            'book-id',
            $title,
            'Author',
            Isbn::from('9780306406157'),
            'category-id',
            Quantity::from($totalCopies),
        );
    }
}
