<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Application\Ports\Outbound\Clock;
use App\Domain\Book;
use App\Domain\Loan;
use App\Domain\LoanStatus;
use App\Domain\Values\Isbn;
use App\Domain\Values\Quantity;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;

final class LoanTest extends TestCase
{
    public function test_adding_an_item_freezes_book_details_and_sets_a_fourteen_day_due_date(): void
    {
        $loanedAt = new DateTimeImmutable('2026-10-01T09:00:00+00:00');
        $book = $this->book();
        $loan = $this->loan($loanedAt, $book);

        $item = $loan->items()[0];
        self::assertSame('book-1', $item->bookId());
        self::assertSame('Book title', $item->bookTitle());
        self::assertSame('9780306406157', $item->isbn()->value());
        self::assertSame('Reference', $item->categoryName());
        self::assertSame('2026-10-15T09:00:00+00:00', $item->dueAt()->format(DATE_ATOM));
        self::assertSame(1, $book->availableCopies());
    }

    public function test_a_loan_rejects_a_duplicate_book_without_withdrawing_another_copy(): void
    {
        $book = $this->book(totalCopies: 2);

        try {
            Loan::issue(
                'loan-1',
                'member-1',
                'user-1',
                new DateTimeImmutable('2026-10-01T09:00:00+00:00'),
                [
                    ['itemId' => 'item-1', 'book' => $book, 'categoryName' => 'Reference'],
                    ['itemId' => 'item-2', 'book' => $book, 'categoryName' => 'Reference'],
                ],
            );
            self::fail('A duplicate book should not be accepted.');
        } catch (DomainException) {
            self::assertSame(2, $book->availableCopies());
        }
    }

    public function test_a_loan_cannot_contain_more_than_three_items(): void
    {
        $books = [
            $this->book('book-1'),
            $this->book('book-2'),
            $this->book('book-3'),
            $this->book('book-4'),
        ];

        $this->expectException(DomainException::class);
        Loan::issue(
            'loan-1',
            'member-1',
            'user-1',
            new DateTimeImmutable('2026-10-01T09:00:00+00:00'),
            array_map(
                static fn (Book $book, int $index): array => [
                    'itemId' => 'item-'.($index + 1),
                    'book' => $book,
                    'categoryName' => 'Reference',
                ],
                $books,
                array_keys($books),
            ),
        );
    }

    public function test_a_loan_must_contain_at_least_one_item(): void
    {
        $this->expectException(DomainException::class);

        Loan::issue(
            'loan-1',
            'member-1',
            'user-1',
            new DateTimeImmutable('2026-10-01T09:00:00+00:00'),
            [],
        );
    }

    public function test_a_book_without_available_copies_is_not_added_to_the_loan(): void
    {
        $book = $this->book(totalCopies: 1);
        $book->withdraw();

        try {
            $this->loan(book: $book);
            self::fail('A book without available copies should not be accepted.');
        } catch (DomainException) {
            self::assertSame(0, $book->availableCopies());
        }
    }

    public function test_issue_does_not_withdraw_any_book_if_one_requested_book_is_unavailable(): void
    {
        $availableBook = $this->book('book-1');
        $unavailableBook = $this->book('book-2', totalCopies: 1);
        $unavailableBook->withdraw();

        try {
            Loan::issue(
                'loan-1',
                'member-1',
                'user-1',
                new DateTimeImmutable('2026-10-01T09:00:00+00:00'),
                [
                    ['itemId' => 'item-1', 'book' => $availableBook, 'categoryName' => 'Reference'],
                    ['itemId' => 'item-2', 'book' => $unavailableBook, 'categoryName' => 'Fiction'],
                ],
            );
            self::fail('The loan should fail when one requested book is unavailable.');
        } catch (DomainException) {
            self::assertSame(2, $availableBook->availableCopies());
            self::assertSame(0, $unavailableBook->availableCopies());
        }
    }

    public function test_status_is_open_before_the_due_date(): void
    {
        $loan = $this->loan();
        $clock = new FrozenClock(new DateTimeImmutable('2026-10-14T09:00:00+00:00'));

        self::assertSame(LoanStatus::OPEN, $loan->status($clock->now()));
    }

    public function test_status_is_overdue_after_an_unreturned_item_due_date(): void
    {
        $loan = $this->loan();
        $clock = new FrozenClock(new DateTimeImmutable('2026-10-16T09:00:00+00:00'));

        self::assertSame(LoanStatus::OVERDUE, $loan->status($clock->now()));
    }

    public function test_status_is_closed_when_all_items_are_returned(): void
    {
        $book = $this->book();
        $loan = $this->loan(book: $book);
        $loan->returnItem('item-1', $book, new DateTimeImmutable('2026-10-10T09:00:00+00:00'));
        $clock = new FrozenClock(new DateTimeImmutable('2026-10-16T09:00:00+00:00'));

        self::assertSame(LoanStatus::CLOSED, $loan->status($clock->now()));
    }

    public function test_returning_an_item_marks_it_and_restocks_its_book_once(): void
    {
        $book = $this->book();
        $loan = $this->loan(book: $book);
        $returnedAt = new DateTimeImmutable('2026-10-10T09:00:00+00:00');

        $loan->returnItem('item-1', $book, $returnedAt);

        self::assertSame($returnedAt, $loan->items()[0]->returnedAt());
        self::assertSame(2, $book->availableCopies());

        $this->expectException(DomainException::class);
        $loan->returnItem('item-1', $book, $returnedAt);
    }

    public function test_returning_an_item_with_another_book_does_not_change_inventory(): void
    {
        $loanBook = $this->book('book-1');
        $otherBook = $this->book('book-2');
        $loan = $this->loan(book: $loanBook);

        try {
            $loan->returnItem('item-1', $otherBook, new DateTimeImmutable('2026-10-10T09:00:00+00:00'));
            self::fail('A different book cannot be returned for this loan item.');
        } catch (DomainException) {
            self::assertSame(1, $loanBook->availableCopies());
            self::assertSame(2, $otherBook->availableCopies());
            self::assertNull($loan->items()[0]->returnedAt());
        }
    }

    private function loan(?DateTimeImmutable $loanedAt = null, ?Book $book = null): Loan
    {
        return Loan::issue(
            'loan-1',
            'member-1',
            'user-1',
            $loanedAt ?? new DateTimeImmutable('2026-10-01T09:00:00+00:00'),
            [[
                'itemId' => 'item-1',
                'book' => $book ?? $this->book(),
                'categoryName' => 'Reference',
            ]],
        );
    }

    private function book(string $id = 'book-1', int $totalCopies = 2): Book
    {
        return Book::register(
            $id,
            'Book title',
            'Author',
            Isbn::from('9780306406157'),
            'category-1',
            Quantity::from($totalCopies),
        );
    }
}

final readonly class FrozenClock implements Clock
{
    public function __construct(private DateTimeImmutable $instant) {}

    public function now(): DateTimeImmutable
    {
        return $this->instant;
    }
}
