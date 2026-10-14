<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\BookNotFound;
use App\Application\Exceptions\ForbiddenRole;
use App\Application\Exceptions\ItemAlreadyReturned;
use App\Application\Exceptions\LoanItemNotFound;
use App\Application\Exceptions\LoanNotFound;
use App\Application\Ports\Outbound\BookRepository;
use App\Application\Ports\Outbound\Clock;
use App\Application\Ports\Outbound\LoanRepository;
use App\Domain\Loan;
use App\Domain\User;
use App\Domain\UserRole;

final readonly class ReturnItemService
{
    public function __construct(
        private BookRepository $books,
        private LoanRepository $loans,
        private Clock $clock,
    ) {}

    public function execute(User $actor, string $loanId, string $itemId): Loan
    {
        if (! in_array($actor->role(), [UserRole::ADMIN, UserRole::LIBRARIAN], true)) {
            throw new ForbiddenRole('Only library staff can return loan items.');
        }

        $loan = $this->loans->findById($loanId);

        if ($loan === null) {
            throw new LoanNotFound('Loan does not exist.');
        }

        $loanItem = null;

        foreach ($loan->items() as $item) {
            if ($item->id() === $itemId) {
                $loanItem = $item;
                break;
            }
        }

        if ($loanItem === null) {
            throw new LoanItemNotFound('Loan item does not exist.');
        }

        if ($loanItem->isReturned()) {
            throw new ItemAlreadyReturned('Loan item has already been returned.');
        }

        $book = $this->books->findActiveById($loanItem->bookId());

        if ($book === null) {
            throw new BookNotFound('Book does not exist.');
        }

        $loan->returnItem($itemId, $book, $this->clock->now());

        $this->loans->saveWithBooks($loan, [$book]);

        return $loan;
    }
}
