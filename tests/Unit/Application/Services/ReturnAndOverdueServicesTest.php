<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Services;

use App\Application\Exceptions\ItemAlreadyReturned;
use App\Application\Exceptions\LoanItemNotFound;
use App\Application\Exceptions\LoanNotFound;
use App\Application\Services\OverdueService;
use App\Application\Services\Results\OverdueRecord;
use App\Application\Services\ReturnItemService;
use App\Domain\Book;
use App\Domain\Loan;
use App\Domain\User;
use App\Domain\UserRole;
use App\Domain\Values\Isbn;
use App\Domain\Values\Quantity;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\Fakes\FakeClock;
use Tests\Support\Fakes\InMemoryBookRepository;
use Tests\Support\Fakes\InMemoryLoanRepository;

final class ReturnAndOverdueServicesTest extends TestCase
{
    public function test_return_marks_one_item_and_restocks_the_book(): void
    {
        [$book, $loan, $books, $loans] = $this->issuedLoan();
        $returnedAt = new DateTimeImmutable('2026-10-10T09:00:00+00:00');
        $service = new ReturnItemService($books, $loans, new FakeClock($returnedAt));

        $returnedLoan = $service->execute($this->librarian(), 'loan-1', 'item-1');

        self::assertSame($returnedAt, $returnedLoan->items()[0]->returnedAt());
        self::assertSame(2, $book->availableCopies());
        self::assertSame(2, $loans->saveCalls);
    }

    public function test_returning_an_item_twice_never_restocks_it_twice(): void
    {
        [$book, , $books, $loans] = $this->issuedLoan();
        $service = new ReturnItemService(
            $books,
            $loans,
            new FakeClock(new DateTimeImmutable('2026-10-10T09:00:00+00:00')),
        );
        $service->execute($this->librarian(), 'loan-1', 'item-1');

        try {
            $service->execute($this->librarian(), 'loan-1', 'item-1');
            self::fail('An already returned item must not be returned again.');
        } catch (ItemAlreadyReturned) {
            self::assertSame(2, $book->availableCopies());
            self::assertSame(2, $loans->saveCalls);
        }
    }

    public function test_return_rejects_a_missing_loan_or_item(): void
    {
        $service = new ReturnItemService(
            new InMemoryBookRepository,
            new InMemoryLoanRepository,
            new FakeClock(new DateTimeImmutable('2026-10-10T09:00:00+00:00')),
        );

        try {
            $service->execute($this->librarian(), 'missing-loan', 'item-1');
            self::fail('A missing loan should be rejected.');
        } catch (LoanNotFound) {
            self::assertTrue(true);
        }

        [, , $books, $loans] = $this->issuedLoan();
        $service = new ReturnItemService(
            $books,
            $loans,
            new FakeClock(new DateTimeImmutable('2026-10-10T09:00:00+00:00')),
        );

        $this->expectException(LoanItemNotFound::class);
        $service->execute($this->librarian(), 'loan-1', 'missing-item');
    }

    public function test_overdue_list_returns_open_items_before_now_sorted_by_days_late(): void
    {
        $loans = new InMemoryLoanRepository;
        $loans->setOverdueRecords([
            new OverdueRecord('loan-2', 'item-2', 'Second title', 'Ana', new DateTimeImmutable('2026-10-18T11:00:00+00:00')),
            new OverdueRecord('loan-1', 'item-1', 'First title', 'Luis', new DateTimeImmutable('2026-10-15T09:00:00+00:00')),
            new OverdueRecord('loan-3', 'item-3', 'Future title', 'Eva', new DateTimeImmutable('2026-10-21T09:00:00+00:00')),
        ]);
        $service = new OverdueService(
            $loans,
            new FakeClock(new DateTimeImmutable('2026-10-20T12:00:00+00:00')),
        );

        $items = $service->execute();

        self::assertCount(2, $items);
        self::assertSame('loan-1', $items[0]->loanId);
        self::assertSame('First title', $items[0]->bookTitle);
        self::assertSame('Luis', $items[0]->memberName);
        self::assertSame(5, $items[0]->daysLate);
        self::assertSame('loan-2', $items[1]->loanId);
        self::assertSame(2, $items[1]->daysLate);
    }

    /** @return array{Book, Loan, InMemoryBookRepository, InMemoryLoanRepository} */
    private function issuedLoan(): array
    {
        $book = Book::register(
            'book-1',
            'Book title',
            'Author',
            Isbn::from('9780306406157'),
            'category-1',
            Quantity::from(2),
        );
        $loan = Loan::issue(
            'loan-1',
            'member-1',
            'user-1',
            new DateTimeImmutable('2026-10-01T09:00:00+00:00'),
            [['itemId' => 'item-1', 'book' => $book, 'categoryName' => 'Reference']],
        );
        $books = new InMemoryBookRepository([$book]);
        $loans = new InMemoryLoanRepository;
        $loans->saveWithBooks($loan, [$book]);

        return [$book, $loan, $books, $loans];
    }

    private function librarian(): User
    {
        return User::register('user-1', 'ana', 'hash', UserRole::LIBRARIAN);
    }
}
