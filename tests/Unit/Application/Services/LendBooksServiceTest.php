<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Services;

use App\Application\Exceptions\BookNotAvailable;
use App\Application\Exceptions\BookNotFound;
use App\Application\Exceptions\DuplicateBookInLoan;
use App\Application\Exceptions\MemberHasOverdue;
use App\Application\Exceptions\MemberLimitExceeded;
use App\Application\Exceptions\MemberNotFound;
use App\Application\Services\LendBooksService;
use App\Domain\Book;
use App\Domain\LoanStatus;
use App\Domain\Member;
use App\Domain\User;
use App\Domain\UserRole;
use App\Domain\Values\DocumentNumber;
use App\Domain\Values\Isbn;
use App\Domain\Values\Quantity;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Support\Fakes\FakeClock;
use Tests\Support\Fakes\InMemoryBookRepository;
use Tests\Support\Fakes\InMemoryCategoryRepository;
use Tests\Support\Fakes\InMemoryLoanRepository;
use Tests\Support\Fakes\InMemoryMemberRepository;

final class LendBooksServiceTest extends TestCase
{
    public function test_it_lends_books_and_saves_the_loan_and_inventory_together(): void
    {
        $book = $this->book('book-1');
        $books = new InMemoryBookRepository([$book]);
        $loans = new InMemoryLoanRepository;
        $service = $this->service($books, $loans);

        $loan = $service->execute($this->librarian(), 'loan-1', 'member-1', ['book-1'], ['item-1']);

        self::assertSame('user-1', $loan->loanedByUserId());
        self::assertSame('member-1', $loan->memberId());
        self::assertSame(LoanStatus::OPEN, $loan->status(new DateTimeImmutable('2026-10-02T09:00:00+00:00')));
        self::assertSame('Reference', $loan->items()[0]->categoryName());
        self::assertSame(1, $book->availableCopies());
        self::assertSame($loan, $loans->loan('loan-1'));
        self::assertSame($book, $loans->savedBook('book-1'));
        self::assertSame(1, $loans->saveCalls);
    }

    public function test_it_rejects_a_missing_member_without_changing_inventory(): void
    {
        $book = $this->book('book-1');
        $books = new InMemoryBookRepository([$book]);
        $loans = new InMemoryLoanRepository;
        $service = $this->service($books, $loans, includeMember: false);

        $this->expectException(MemberNotFound::class);
        $service->execute($this->librarian(), 'loan-1', 'missing-member', ['book-1'], ['item-1']);
    }

    public function test_it_rejects_a_member_with_overdue_items(): void
    {
        $book = $this->book('book-1');
        $books = new InMemoryBookRepository([$book]);
        $loans = new InMemoryLoanRepository;
        $loans->setHasOverdueItems('member-1', true);
        $service = $this->service($books, $loans);

        $this->expectException(MemberHasOverdue::class);
        $service->execute($this->librarian(), 'loan-1', 'member-1', ['book-1'], ['item-1']);
    }

    public function test_it_enforces_three_open_books_across_loans(): void
    {
        $book = $this->book('book-1');
        $books = new InMemoryBookRepository([$book]);
        $loans = new InMemoryLoanRepository;
        $loans->setOpenItemCount('member-1', 3);
        $service = $this->service($books, $loans);

        $this->expectException(MemberLimitExceeded::class);
        $service->execute($this->librarian(), 'loan-1', 'member-1', ['book-1'], ['item-1']);
    }

    public function test_it_rejects_duplicate_books_before_withdrawing_copies(): void
    {
        $book = $this->book('book-1');
        $books = new InMemoryBookRepository([$book]);
        $loans = new InMemoryLoanRepository;
        $service = $this->service($books, $loans);

        $this->expectException(DuplicateBookInLoan::class);
        $service->execute(
            $this->librarian(),
            'loan-1',
            'member-1',
            ['book-1', 'book-1'],
            ['item-1', 'item-2'],
        );
    }

    public function test_it_rejects_an_unavailable_book_without_saving_a_loan(): void
    {
        $book = $this->book('book-1', totalCopies: 1);
        $book->withdraw();
        $books = new InMemoryBookRepository([$book]);
        $loans = new InMemoryLoanRepository;
        $service = $this->service($books, $loans);

        $this->expectException(BookNotAvailable::class);
        $service->execute($this->librarian(), 'loan-1', 'member-1', ['book-1'], ['item-1']);
    }

    public function test_it_rejects_a_missing_book(): void
    {
        $books = new InMemoryBookRepository;
        $loans = new InMemoryLoanRepository;
        $service = $this->service($books, $loans);

        $this->expectException(BookNotFound::class);
        $service->execute($this->librarian(), 'loan-1', 'member-1', ['missing'], ['item-1']);
    }

    public function test_it_rejects_a_retired_book(): void
    {
        $retiredBook = $this->book('book-1');
        $retiredBook->retire(new DateTimeImmutable('2026-09-30T09:00:00+00:00'));
        $service = $this->service(
            new InMemoryBookRepository([$retiredBook]),
            new InMemoryLoanRepository,
        );

        $this->expectException(BookNotFound::class);
        $service->execute($this->librarian(), 'loan-1', 'member-1', ['book-1'], ['item-1']);
    }

    public function test_it_requires_one_to_three_books_and_matching_item_ids(): void
    {
        $service = $this->service(new InMemoryBookRepository, new InMemoryLoanRepository);

        $this->expectException(InvalidArgumentException::class);
        $service->execute($this->librarian(), 'loan-1', 'member-1', [], []);
    }

    private function service(
        InMemoryBookRepository $books,
        InMemoryLoanRepository $loans,
        bool $includeMember = true,
    ): LendBooksService {
        $members = $includeMember
            ? new InMemoryMemberRepository([$this->member()])
            : new InMemoryMemberRepository;

        return new LendBooksService(
            $books,
            new InMemoryCategoryRepository,
            $loans,
            $members,
            new FakeClock(new DateTimeImmutable('2026-10-01T09:00:00+00:00')),
        );
    }

    private function librarian(): User
    {
        return User::register('user-1', 'ana', 'hash', UserRole::LIBRARIAN);
    }

    private function member(): Member
    {
        return Member::register('member-1', 'Member', DocumentNumber::from('CC-1'));
    }

    private function book(string $id, int $totalCopies = 2): Book
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
