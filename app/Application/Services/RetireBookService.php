<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\BookHasOpenLoans;
use App\Application\Exceptions\BookNotFound;
use App\Application\Exceptions\ForbiddenRole;
use App\Application\Ports\Outbound\BookRepository;
use App\Application\Ports\Outbound\Clock;
use App\Domain\Book;
use App\Domain\User;
use App\Domain\UserRole;

final readonly class RetireBookService
{
    public function __construct(
        private BookRepository $books,
        private Clock $clock,
    ) {}

    public function execute(User $actor, string $bookId): Book
    {
        if ($actor->role() !== UserRole::ADMIN) {
            throw new ForbiddenRole('Only an admin can retire books.');
        }

        $book = $this->books->findActiveById($bookId);

        if ($book === null) {
            throw new BookNotFound('Book does not exist.');
        }

        if ($this->books->hasOpenLoans($bookId)) {
            throw new BookHasOpenLoans('Book with open loans cannot be retired.');
        }

        $book->retire($this->clock->now());
        $this->books->save($book);

        return $book;
    }
}
