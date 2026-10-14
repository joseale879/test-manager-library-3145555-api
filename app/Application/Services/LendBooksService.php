<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\BookNotAvailable;
use App\Application\Exceptions\BookNotFound;
use App\Application\Exceptions\DuplicateBookInLoan;
use App\Application\Exceptions\MemberHasOverdue;
use App\Application\Exceptions\MemberLimitExceeded;
use App\Application\Exceptions\MemberNotFound;
use App\Application\Ports\Outbound\BookRepository;
use App\Application\Ports\Outbound\CategoryRepository;
use App\Application\Ports\Outbound\Clock;
use App\Application\Ports\Outbound\LoanRepository;
use App\Application\Ports\Outbound\MemberRepository;
use App\Domain\Loan;
use App\Domain\User;
use App\Domain\UserRole;
use DomainException;
use InvalidArgumentException;

final readonly class LendBooksService
{
    public function __construct(
        private BookRepository $books,
        private CategoryRepository $categories,
        private LoanRepository $loans,
        private MemberRepository $members,
        private Clock $clock,
    ) {}

    /**
     * @param  list<string>  $bookIds
     * @param  list<string>  $itemIds
     */
    public function execute(
        User $actor,
        string $loanId,
        string $memberId,
        array $bookIds,
        array $itemIds,
    ): Loan {
        if (! in_array($actor->role(), [UserRole::ADMIN, UserRole::LIBRARIAN], true)) {
            throw new DomainException('Only library staff can register loans.');
        }

        if (count($bookIds) < 1 || count($bookIds) > 3 || count($itemIds) !== count($bookIds)) {
            throw new InvalidArgumentException('A loan must contain between one and three books.');
        }

        if (count(array_unique($bookIds)) !== count($bookIds)) {
            throw new DuplicateBookInLoan('A book cannot appear more than once in a loan.');
        }

        $member = $this->members->findById($memberId);

        if ($member === null) {
            throw new MemberNotFound('Member does not exist.');
        }

        $now = $this->clock->now();

        if ($this->loans->hasOverdueItemsForMember($memberId, $now)) {
            throw new MemberHasOverdue('Member has overdue books.');
        }

        if ($this->loans->countOpenItemsForMember($memberId) + count($bookIds) > 3) {
            throw new MemberLimitExceeded('Member cannot have more than three books without return.');
        }

        $booksById = $this->books->findActiveByIds($bookIds);

        foreach ($bookIds as $bookId) {
            if (! isset($booksById[$bookId])) {
                throw new BookNotFound('Book does not exist.');
            }
        }

        $loanItems = [];
        $booksToSave = [];

        foreach ($bookIds as $index => $bookId) {
            $book = $booksById[$bookId];

            if ($book->availableCopies() < 1) {
                throw new BookNotAvailable('Book has no available copies.');
            }

            $categoryName = $this->categories->nameForId($book->categoryId());

            if ($categoryName === null) {
                throw new DomainException('Book refers to an unknown category.');
            }

            $loanItems[] = [
                'itemId' => $itemIds[$index],
                'book' => $book,
                'categoryName' => $categoryName,
            ];
            $booksToSave[] = $book;
        }

        $loan = Loan::issue(
            $loanId,
            $member->id(),
            $actor->id(),
            $now,
            $loanItems,
        );

        $this->loans->saveWithBooks($loan, $booksToSave);

        return $loan;
    }
}
